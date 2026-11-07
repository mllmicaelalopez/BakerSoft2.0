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
                       p.motivo_cancelacion,
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
     * `confirmado_fuera_plazo` (true cuando se confirmó una fecha con menos
     * anticipación que la mínima del tipo de pedido, HU-09/HU-10) marca
     * `confirmado_fuera_de_anticipacion` y guarda la hora de esa confirmación.
     * Con false esas dos columnas quedan como están: una edición posterior no
     * borra la traza de una confirmación anterior (mismo criterio que
     * `modificado_en_preparacion`). El usuario que confirmó queda en
     * `usuario_ultima_modificacion`.
     *
     * @param array{
     *     fecha_entrega: string, usuario_ultima_modificacion: int,
     *     modificado_en_preparacion: bool, confirmado_fuera_plazo: bool
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
                 fecha_hora_modificacion_en_preparacion = IF(:en_preparacion_2, NOW(), fecha_hora_modificacion_en_preparacion),
                 confirmado_fuera_de_anticipacion = confirmado_fuera_de_anticipacion OR :fuera_plazo_1,
                 fecha_hora_confirmacion_fuera_plazo = IF(:fuera_plazo_2, NOW(), fecha_hora_confirmacion_fuera_plazo)
             WHERE id_pedido = :id'
        );

        $enPreparacion = $datos['modificado_en_preparacion'] ? 1 : 0;
        $fueraDePlazo  = $datos['confirmado_fuera_plazo'] ? 1 : 0;

        $stmt->execute([
            'fecha_entrega'     => $datos['fecha_entrega'],
            'usuario'           => $datos['usuario_ultima_modificacion'],
            'en_preparacion_1'  => $enPreparacion,
            'en_preparacion_2'  => $enPreparacion,
            'fuera_plazo_1'     => $fueraDePlazo,
            'fuera_plazo_2'     => $fueraDePlazo,
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

    /**
     * Próximas entregas (HU-13): pedidos "Confirmado", "En preparación" o
     * "Listo" a entregar en el rango de fechas, más los atrasados, ordenados
     * por fecha y hora de entrega (y por id ante un empate).
     *
     * Todo se resuelve en la consulta: el WHERE (compartido con
     * contarProximasEntregas(), ver condicionesProximasEntregas()), el orden,
     * la paginación, el resumen de productos (GROUP_CONCAT) y la urgencia
     * (CASE), así la pantalla, el CSV y el Inicio muestran exactamente lo
     * mismo. Mismo criterio que listar(): nunca se trae todo para filtrar
     * después en PHP.
     *
     * Sin $limite devuelve todas las filas (lo usa la exportación CSV).
     *
     * Columnas: id_pedido, fecha_entrega, estado, tipo_pedido, cliente,
     * telefono, direccion (los dos últimos pueden ser NULL), productos
     * ("Nombre: cantidad unidad", separados por ", ") y urgencia
     * ("Atrasado", "Hoy" o "Próximos días").
     *
     * @param array{
     *     fecha_desde: string, fecha_hasta: string, estado?: string,
     *     id_tipo_pedido?: int, cliente_nombre?: string
     * } $filtros
     * @return array<int, array<string, mixed>>
     */
    public static function listarProximasEntregas(array $filtros, ?int $limite = null, int $desplazamiento = 0): array
    {
        [$where, $params] = self::condicionesProximasEntregas($filtros);

        // La cantidad sale sin ceros de más y con coma decimal ("24", "1,5");
        // la unidad es "kg" o "uds." (mismo texto que usaba el Inicio).
        $sql = "SELECT p.id_pedido, p.fecha_entrega, p.estado,
                       tp.nombre AS tipo_pedido,
                       c.nombre_razon_social AS cliente, c.telefono, c.direccion,
                       GROUP_CONCAT(
                           CONCAT(
                               pr.nombre, ': ',
                               REPLACE(TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(d.cantidad AS CHAR))), '.', ','),
                               ' ', IF(pr.unidad_medida = 'kg', 'kg', 'uds.')
                           )
                           ORDER BY pr.nombre SEPARATOR ', '
                       ) AS productos,
                       CASE
                           WHEN p.fecha_entrega < NOW() THEN 'Atrasado'
                           WHEN DATE(p.fecha_entrega) = CURDATE() THEN 'Hoy'
                           ELSE 'Próximos días'
                       END AS urgencia
                FROM pedido p
                INNER JOIN cliente c ON c.id_cliente = p.id_cliente
                INNER JOIN tipo_pedido tp ON tp.id_tipo_pedido = p.id_tipo_pedido
                LEFT JOIN detalle_pedido d ON d.id_pedido = p.id_pedido
                LEFT JOIN producto pr ON pr.id_producto = d.id_producto
                WHERE {$where}
                GROUP BY p.id_pedido
                ORDER BY p.fecha_entrega ASC, p.id_pedido ASC";

        if ($limite !== null) {
            // Enteros ya casteados: no hay nada del usuario en estos dos valores.
            $sql .= sprintf(' LIMIT %d OFFSET %d', $limite, $desplazamiento);
        }

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Cantidad total de próximas entregas que cumplen los filtros (HU-13),
     * sin paginar: el mismo WHERE que listarProximasEntregas().
     *
     * @param array{
     *     fecha_desde: string, fecha_hasta: string, estado?: string,
     *     id_tipo_pedido?: int, cliente_nombre?: string
     * } $filtros
     */
    public static function contarProximasEntregas(array $filtros): int
    {
        [$where, $params] = self::condicionesProximasEntregas($filtros);

        $sql = "SELECT COUNT(DISTINCT p.id_pedido)
                FROM pedido p
                INNER JOIN cliente c ON c.id_cliente = p.id_cliente
                WHERE {$where}";

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * WHERE y parámetros de las próximas entregas (HU-13), en un solo lugar
     * para que el listado, el conteo, el CSV y el Inicio no puedan quedar
     * desalineados.
     *
     * Regla 2 de la documentación: los atrasados (fecha de entrega ya pasada)
     * entran siempre, aunque queden fuera del rango de fechas. Esa excepción
     * es solo del rango: el OR abarca únicamente la condición de fechas, y
     * los filtros opcionales (estado, tipo, cliente) van con AND por fuera
     * del paréntesis, así que se aplican igual a los atrasados.
     *
     * El rango se compara contra la columna sin envolverla en DATE() (desde
     * las 00:00 del día "desde" hasta antes de las 00:00 del día siguiente a
     * "hasta", que es lo mismo que DATE(fecha_entrega) BETWEEN desde AND
     * hasta) para que la consulta pueda usar idx_pedido_fecha_entrega
     * (migración 011).
     *
     * @param array<string, mixed> $filtros
     * @return array{0: string, 1: array<string, int|string>}
     */
    private static function condicionesProximasEntregas(array $filtros): array
    {
        $where = "p.estado IN ('Confirmado', 'En preparación', 'Listo')
                  AND (p.fecha_entrega < NOW()
                       OR (p.fecha_entrega >= :desde
                           AND p.fecha_entrega < DATE_ADD(:hasta, INTERVAL 1 DAY)))";

        $params = [
            'desde' => $filtros['fecha_desde'],
            'hasta' => $filtros['fecha_hasta'],
        ];

        if (isset($filtros['estado'])) {
            $where .= ' AND p.estado = :estado';
            $params['estado'] = $filtros['estado'];
        }

        if (isset($filtros['id_tipo_pedido'])) {
            $where .= ' AND p.id_tipo_pedido = :tipo';
            $params['tipo'] = $filtros['id_tipo_pedido'];
        }

        if (isset($filtros['cliente_nombre'])) {
            $where .= ' AND c.nombre_razon_social LIKE :cliente';
            $params['cliente'] = '%' . self::escaparLike($filtros['cliente_nombre']) . '%';
        }

        return [$where, $params];
    }

    private static function escaparLike(string $texto): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $texto);
    }

    /**
     * Ranking de productos vendidos en un período (HU-14): una fila por
     * producto que aparece en al menos un pedido "Entregado" cuya fecha de
     * entrega cae en el período, ordenado por cantidad de pedidos (de mayor a
     * menor) y, ante un empate, alfabéticamente por nombre.
     *
     * Se ordena por pedidos y no por cantidad porque kg y unidades no se
     * pueden sumar entre sí; la cantidad total vendida va aparte, en la unidad
     * propia de cada producto. Todo (filtro, agrupamiento, conteos y totales)
     * se resuelve en la consulta, nunca sumando en PHP; el WHERE es el mismo
     * que usa contarEntregadosPeriodo() (ver condicionesMasVendidos()).
     *
     * Sin $limite devuelve todas las filas (lo usan el informe y el PDF).
     *
     * Columnas: id_producto, producto (nombre), categoria (nombre del tipo de
     * producto), unidad_medida ("kg" o "unidad"), pedidos (cantidad de pedidos
     * entregados en los que aparece) y total (SUM de cantidad, en su unidad).
     *
     * @param string $desde "Y-m-d" (inclusive)
     * @param string $hasta "Y-m-d" (inclusive: se cuenta todo ese día)
     * @return array<int, array<string, mixed>>
     */
    public static function rankingMasVendidos(string $desde, string $hasta, ?int $idTipoProducto = null, ?int $limite = null): array
    {
        [$where, $params] = self::condicionesMasVendidos($desde, $hasta, $idTipoProducto);

        $sql = "SELECT pr.id_producto, pr.nombre AS producto, tp.nombre AS categoria, pr.unidad_medida,
                       COUNT(DISTINCT p.id_pedido) AS pedidos,
                       SUM(d.cantidad) AS total
                FROM pedido p
                INNER JOIN detalle_pedido d ON d.id_pedido = p.id_pedido
                INNER JOIN producto pr ON pr.id_producto = d.id_producto
                INNER JOIN tipo_producto tp ON tp.id_tipo_producto = pr.id_tipo_producto
                WHERE {$where}
                GROUP BY pr.id_producto, pr.nombre, tp.nombre, pr.unidad_medida
                ORDER BY pedidos DESC, producto ASC";

        if ($limite !== null) {
            // Entero ya casteado: no hay nada del usuario en este valor.
            $sql .= sprintf(' LIMIT %d', $limite);
        }

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Cantidad de pedidos "Entregado" con fecha de entrega en el período
     * (HU-14): el valor del indicador "Pedidos entregados" y el denominador
     * del "% de pedidos" de cada producto.
     *
     * Sin categoría cuenta todos los pedidos entregados del período; con
     * categoría, solo los que tienen al menos un producto de esa categoría
     * (por eso ahí se une con detalle y producto).
     */
    public static function contarEntregadosPeriodo(string $desde, string $hasta, ?int $idTipoProducto = null): int
    {
        [$where, $params] = self::condicionesMasVendidos($desde, $hasta, $idTipoProducto);

        $uniones = $idTipoProducto === null
            ? ''
            : 'INNER JOIN detalle_pedido d ON d.id_pedido = p.id_pedido
               INNER JOIN producto pr ON pr.id_producto = d.id_producto';

        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(DISTINCT p.id_pedido) FROM pedido p {$uniones} WHERE {$where}"
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Condición común (estado, fechas y categoría) y parámetros del informe de
     * más vendidos (HU-14), en un solo lugar para que el ranking, el conteo,
     * el PDF y el Inicio no puedan quedar desalineados.
     *
     * Regla 1 de la documentación: como todavía no hay módulo de Ventas, un
     * producto se considera vendido cuando forma parte de un pedido
     * "Entregado"; el período se toma por fecha_entrega. Ningún otro estado
     * cuenta.
     *
     * Las fechas se comparan contra la columna sin envolverla en DATE() (desde
     * las 00:00 del día "desde" hasta antes de las 00:00 del día siguiente a
     * "hasta"), igual que condicionesProximasEntregas(), para poder usar
     * idx_pedido_fecha_entrega (migración 011).
     *
     * El filtro de categoría usa el alias "pr" (producto): quien lo pida tiene
     * que unir detalle_pedido y producto.
     *
     * @return array{0: string, 1: array<string, int|string>}
     */
    private static function condicionesMasVendidos(string $desde, string $hasta, ?int $idTipoProducto): array
    {
        $where = "p.estado = 'Entregado'
                  AND p.fecha_entrega >= :desde
                  AND p.fecha_entrega < DATE_ADD(:hasta, INTERVAL 1 DAY)";

        $params = ['desde' => $desde, 'hasta' => $hasta];

        if ($idTipoProducto !== null) {
            $where .= ' AND pr.id_tipo_producto = :categoria';
            $params['categoria'] = $idTipoProducto;
        }

        return [$where, $params];
    }
}
