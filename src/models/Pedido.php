<?php

declare(strict_types=1);

/**
 * BakerSoft - Pedidos (HU-09 alta, HU-10 modificación, HU-11 consulta,
 * HU-12 baja lógica / cancelación).
 *
 * La baja es lógica vía el campo `estado` (se pasa a "Cancelado"); acá no hay
 * ningún DELETE. La transacción que junta el alta de un pedido con su detalle
 * vive en PedidoController::store(), no en este modelo (mismo criterio que
 * AuthController::resetPassword(), que también arma su propia transacción).
 */
final class Pedido
{
    /**
     * Listado con filtros opcionales (HU-11), todos aplicados en el WHERE de
     * la consulta (nunca trayendo todo para filtrar después en PHP), mismo
     * criterio que Producto::listar().
     *
     * @param array{
     *     cliente_nombre?: string, id_tipo_pedido?: int, estado?: string,
     *     fecha_desde?: string, fecha_hasta?: string
     * } $filtros
     * @return array<int, array<string, mixed>>
     */
    public static function listar(array $filtros = []): array
    {
        $sql = 'SELECT p.id_pedido, p.fecha_creacion, p.fecha_entrega, p.estado,
                       p.confirmado_fuera_de_anticipacion,
                       c.nombre_razon_social AS cliente,
                       tp.nombre AS tipo_pedido,
                       COUNT(d.id_detalle_pedido) AS cantidad_productos
                FROM pedido p
                INNER JOIN cliente c ON c.id_cliente = p.id_cliente
                INNER JOIN tipo_pedido tp ON tp.id_tipo_pedido = p.id_tipo_pedido
                LEFT JOIN detalle_pedido d ON d.id_pedido = p.id_pedido
                WHERE 1 = 1';

        $params = [];

        if (isset($filtros['cliente_nombre'])) {
            $sql .= ' AND c.nombre_razon_social LIKE :cliente';
            $params['cliente'] = '%' . self::escaparLike($filtros['cliente_nombre']) . '%';
        }

        if (isset($filtros['id_tipo_pedido'])) {
            $sql .= ' AND p.id_tipo_pedido = :tipo';
            $params['tipo'] = $filtros['id_tipo_pedido'];
        }

        if (isset($filtros['estado'])) {
            $sql .= ' AND p.estado = :estado';
            $params['estado'] = $filtros['estado'];
        } else {
            // Bandeja de pendientes (HU-12): sin un filtro de estado explícito,
            // los pedidos cancelados no se listan. Elegir el estado "Cancelado"
            // a propósito en el filtro (ver filtrosDesdeQuery()) los sigue
            // trayendo, para no perder el historial.
            $sql .= " AND p.estado != 'Cancelado'";
        }

        if (isset($filtros['fecha_desde'])) {
            $sql .= ' AND DATE(p.fecha_entrega) >= :fecha_desde';
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }

        if (isset($filtros['fecha_hasta'])) {
            $sql .= ' AND DATE(p.fecha_entrega) <= :fecha_hasta';
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }

        $sql .= ' GROUP BY p.id_pedido ORDER BY p.fecha_creacion DESC';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function buscarPorId(int $id): ?array
    {
        $sql = 'SELECT p.id_pedido, p.id_cliente, p.id_tipo_pedido, p.usuario_registro,
                       p.fecha_creacion, p.fecha_entrega, p.estado,
                       p.confirmado_fuera_de_anticipacion, p.fecha_hora_confirmacion_fuera_plazo,
                       c.nombre_razon_social AS cliente,
                       tp.nombre AS tipo_pedido
                FROM pedido p
                INNER JOIN cliente c ON c.id_cliente = p.id_cliente
                INNER JOIN tipo_pedido tp ON tp.id_tipo_pedido = p.id_tipo_pedido
                WHERE p.id_pedido = :id
                LIMIT 1';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute(['id' => $id]);

        $pedido = $stmt->fetch();

        return $pedido === false ? null : $pedido;
    }

    /**
     * Da de alta un pedido en estado "Nuevo" y devuelve su id.
     *
     * El estado sale siempre fijo en "Nuevo" acá adentro: nunca lo decide el
     * llamador ni, mucho menos, el request entrante.
     *
     * @param array{
     *     id_cliente: int, id_tipo_pedido: int, usuario_registro: int, fecha_entrega: string,
     *     confirmado_fuera_de_anticipacion: bool, fecha_hora_confirmacion_fuera_plazo: ?string
     * } $datos
     */
    public static function crear(array $datos): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO pedido (
                 id_cliente, id_tipo_pedido, usuario_registro, fecha_entrega, estado,
                 confirmado_fuera_de_anticipacion, fecha_hora_confirmacion_fuera_plazo
             ) VALUES (
                 :id_cliente, :id_tipo_pedido, :usuario_registro, :fecha_entrega, \'Nuevo\',
                 :confirmado, :fecha_confirmacion
             )'
        );

        $stmt->execute([
            'id_cliente'          => $datos['id_cliente'],
            'id_tipo_pedido'      => $datos['id_tipo_pedido'],
            'usuario_registro'    => $datos['usuario_registro'],
            'fecha_entrega'       => $datos['fecha_entrega'],
            'confirmado'          => $datos['confirmado_fuera_de_anticipacion'] ? 1 : 0,
            'fecha_confirmacion'  => $datos['fecha_hora_confirmacion_fuera_plazo'],
        ]);

        return (int) Database::getConnection()->lastInsertId();
    }

    /**
     * Modificación de pedido (HU-10): fecha de entrega + trazabilidad de la
     * edición. Cliente, tipo de pedido y estado no se tocan acá (no son
     * editables en esta HU).
     *
     * `modificado_en_preparacion` es un flag que una vez en true queda en
     * true (OR con el valor actual): registra que el pedido fue editado
     * alguna vez estando "En preparación", no solo en la última edición.
     *
     * @param array{
     *     fecha_entrega: string, usuario_ultima_modificacion: int, modificado_en_preparacion: bool
     * } $datos
     */
    public static function actualizar(int $id, array $datos): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE pedido
             SET fecha_entrega = :fecha_entrega,
                 fecha_ultima_modificacion = NOW(),
                 usuario_ultima_modificacion = :usuario,
                 modificado_en_preparacion = modificado_en_preparacion OR :en_preparacion_1,
                 fecha_hora_modificacion_en_preparacion = IF(:en_preparacion_2, NOW(), fecha_hora_modificacion_en_preparacion)
             WHERE id_pedido = :id'
        );

        $enPreparacion = $datos['modificado_en_preparacion'] ? 1 : 0;

        $stmt->execute([
            'fecha_entrega'     => $datos['fecha_entrega'],
            'usuario'           => $datos['usuario_ultima_modificacion'],
            'en_preparacion_1'  => $enPreparacion,
            'en_preparacion_2'  => $enPreparacion,
            'id'                => $id,
        ]);
    }

    /**
     * Baja lógica (HU-12): pasa el pedido a "Cancelado" y guarda el motivo.
     * Nunca borra la fila.
     */
    public static function cancelar(int $id, string $motivo): void
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE pedido SET estado = 'Cancelado', motivo_cancelacion = :motivo WHERE id_pedido = :id"
        );

        $stmt->execute(['motivo' => $motivo, 'id' => $id]);
    }

    private static function escaparLike(string $texto): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $texto);
    }
}
