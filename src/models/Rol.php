<?php

declare(strict_types=1);

/**
 * BakerSoft - Acceso a la tabla roles (HT-01: Roles y Permisos).
 */
final class Rol
{
    /**
     * Roles activos, para poblar el select de la pantalla de usuarios.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listar(): array
    {
        $stmt = Database::getConnection()->query(
            'SELECT id_rol, codigo, nombre, activo
             FROM roles
             WHERE activo = 1
             ORDER BY nombre'
        );

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function buscarPorId(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id_rol, codigo, nombre, activo FROM roles WHERE id_rol = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $rol = $stmt->fetch();

        return $rol === false ? null : $rol;
    }
}
