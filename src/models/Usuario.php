<?php

declare(strict_types=1);

/**
 * BakerSoft - Acceso a la tabla usuarios.
 */
final class Usuario
{
    /**
     * Rol que se asigna a las cuentas creadas por el registro público
     * (/register). Es el id_rol de "cajero" sembrado por la migración 005:
     * el registro público nunca deja una cuenta como administrador.
     *
     * Es una limitación conocida: hasta que exista una pantalla de alta
     * manual de usuarios, cualquier otro rol (repartidor, maestro panadero)
     * se asigna a mano desde /usuario una vez creada la cuenta.
     */
    private const ROL_REGISTRO_PUBLICO = 2;

    /**
     * Busca un usuario por email, con el estado de bloqueo y el rol ya
     * resueltos.
     *
     * esta_bloqueada se calcula en SQL a propósito: bloqueado_hasta lo escribe
     * MySQL con su NOW(), así que compararlo contra el reloj de PHP daría mal
     * si las zonas horarias no coinciden.
     *
     * @return array<string, mixed>|null
     */
    public static function buscarPorEmail(string $email): ?array
    {
        $sql = 'SELECT u.id_usuario, u.nombre_usuario, u.email, u.password_hash, u.activo,
                       u.intentos_fallidos, u.bloqueado_hasta, u.bloqueos_24h, u.id_rol,
                       r.codigo AS rol_codigo,
                       (u.bloqueado_hasta IS NOT NULL AND u.bloqueado_hasta > NOW()) AS esta_bloqueada
                FROM usuarios u
                INNER JOIN roles r ON r.id_rol = u.id_rol
                WHERE u.email = :email
                LIMIT 1';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute(['email' => $email]);

        $usuario = $stmt->fetch();

        return $usuario === false ? null : $usuario;
    }

    /**
     * Lista usuarios con su rol, con filtros opcionales.
     *
     * @param string|null $busqueda Texto a buscar en nombre o email.
     * @param string|null $estado   "activo", "inactivo" o null para todos.
     * @return array<int, array<string, mixed>>
     */
    public static function listar(?string $busqueda = null, ?string $estado = null): array
    {
        $sql = 'SELECT u.id_usuario, u.nombre_usuario, u.email, u.activo, u.fecha_creacion,
                       u.id_rol, r.codigo AS rol_codigo, r.nombre AS rol_nombre
                FROM usuarios u
                INNER JOIN roles r ON r.id_rol = u.id_rol
                WHERE 1 = 1';

        $params = [];

        if ($busqueda !== null && $busqueda !== '') {
            $sql .= ' AND (u.nombre_usuario LIKE :busqueda_nombre OR u.email LIKE :busqueda_email)';

            $patron = '%' . self::escaparLike($busqueda) . '%';

            $params['busqueda_nombre'] = $patron;
            $params['busqueda_email']  = $patron;
        }

        if ($estado === 'activo') {
            $sql .= ' AND u.activo = 1';
        } elseif ($estado === 'inactivo') {
            $sql .= ' AND u.activo = 0';
        }

        $sql .= ' ORDER BY u.nombre_usuario';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function buscarPorId(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT u.id_usuario, u.nombre_usuario, u.email, u.activo, u.fecha_creacion,
                    u.id_rol, r.codigo AS rol_codigo, r.nombre AS rol_nombre
             FROM usuarios u
             INNER JOIN roles r ON r.id_rol = u.id_rol
             WHERE u.id_usuario = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $usuario = $stmt->fetch();

        return $usuario === false ? null : $usuario;
    }

    /**
     * Cambia el rol de una cuenta (ABM de usuarios, HT-01).
     */
    public static function actualizarRol(int $id, int $idRol): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE usuarios SET id_rol = :rol WHERE id_usuario = :id'
        );
        $stmt->execute(['rol' => $idRol, 'id' => $id]);
    }

    /**
     * Baja y alta lógica de la cuenta. Nunca borra la fila.
     */
    public static function cambiarEstado(int $id, bool $activo): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE usuarios SET activo = :activo WHERE id_usuario = :id'
        );
        $stmt->execute(['activo' => $activo ? 1 : 0, 'id' => $id]);
    }

    /**
     * ¿Ya hay una cuenta con ese email?
     */
    public static function emailRegistrado(string $email): bool
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT 1 FROM usuarios WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Crea una cuenta activa y devuelve su id.
     *
     * La contraseña entra en claro y se hashea acá: ningún llamador debería
     * tener que acordarse de hacerlo. El rol siempre sale de
     * ROL_REGISTRO_PUBLICO: el registro público nunca crea administradores.
     *
     * @throws PDOException si el email ya existe (índice único).
     */
    public static function crear(string $nombreUsuario, string $email, string $password): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO usuarios (nombre_usuario, email, password_hash, activo, id_rol)
             VALUES (:nombre, :email, :hash, 1, :rol)'
        );

        $stmt->execute([
            'nombre' => $nombreUsuario,
            'email'  => $email,
            'hash'   => password_hash($password, PASSWORD_DEFAULT),
            'rol'    => self::ROL_REGISTRO_PUBLICO,
        ]);

        return (int) Database::getConnection()->lastInsertId();
    }

    private static function escaparLike(string $texto): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $texto);
    }
}
