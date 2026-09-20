<?php

declare(strict_types=1);

/**
 * BakerSoft - Clientes (HU-09, Sprint 4).
 *
 * Entidad de soporte mínima para el alta de pedido: todavía sin ABM propio.
 * Solo alta (desde el formulario de pedido) y las lecturas que ese formulario
 * necesita. Edición y baja lógica quedan para un sprint futuro.
 */
final class Cliente
{
    /**
     * Clientes activos, para el select del alta de pedido.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listar(): array
    {
        $stmt = Database::getConnection()->query(
            'SELECT id_cliente, nombre_razon_social, tipo_cliente, cuit_cuil,
                    telefono, email, direccion, activo
             FROM cliente
             WHERE activo = 1
             ORDER BY nombre_razon_social'
        );

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function buscarPorId(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id_cliente, nombre_razon_social, tipo_cliente, cuit_cuil,
                    telefono, email, direccion, activo
             FROM cliente
             WHERE id_cliente = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $cliente = $stmt->fetch();

        return $cliente === false ? null : $cliente;
    }

    /**
     * Da de alta un cliente nuevo y devuelve su id.
     *
     * @param array{nombre_razon_social: string, tipo_cliente: string, cuit_cuil: ?string,
     *              telefono: ?string, email: ?string, direccion: ?string} $datos
     */
    public static function crear(array $datos): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO cliente (nombre_razon_social, tipo_cliente, cuit_cuil, telefono, email, direccion)
             VALUES (:nombre, :tipo, :cuit, :telefono, :email, :direccion)'
        );

        $stmt->execute([
            'nombre'    => $datos['nombre_razon_social'],
            'tipo'      => $datos['tipo_cliente'],
            'cuit'      => $datos['cuit_cuil'] !== '' ? $datos['cuit_cuil'] : null,
            'telefono'  => $datos['telefono'] !== '' ? $datos['telefono'] : null,
            'email'     => $datos['email'] !== '' ? $datos['email'] : null,
            'direccion' => $datos['direccion'] !== '' ? $datos['direccion'] : null,
        ]);

        return (int) Database::getConnection()->lastInsertId();
    }
}
