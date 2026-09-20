<?php

declare(strict_types=1);

/**
 * BakerSoft - Tipos de producto (en la interfaz: "Categoría").
 *
 * No hay ningún DELETE acá a propósito: la baja es lógica, con cambiarEstado().
 */
final class TipoProducto
{
    /**
     * Lista los tipos, con filtros opcionales.
     *
     * @param string|null $busqueda Texto a buscar dentro del nombre.
     * @param string|null $estado   "activo", "inactivo" o null para todos.
     * @return array<int, array<string, mixed>>
     */
    public static function listar(?string $busqueda = null, ?string $estado = null): array
    {
        $sql = 'SELECT id_tipo_producto, codigo, nombre, descripcion, activo, fecha_creacion
                FROM tipo_producto
                WHERE 1 = 1';

        $params = [];

        if ($busqueda !== null && $busqueda !== '') {
            $sql .= ' AND nombre LIKE :busqueda';
            // Se escapan los comodines para que un "%" tipeado por el usuario
            // se busque como texto y no traiga toda la tabla.
            $params['busqueda'] = '%' . self::escaparLike($busqueda) . '%';
        }

        if ($estado === 'activo') {
            $sql .= ' AND activo = 1';
        } elseif ($estado === 'inactivo') {
            $sql .= ' AND activo = 0';
        }

        $sql .= ' ORDER BY nombre';

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
            'SELECT id_tipo_producto, codigo, nombre, descripcion, activo, fecha_creacion
             FROM tipo_producto
             WHERE id_tipo_producto = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $tipo = $stmt->fetch();

        return $tipo === false ? null : $tipo;
    }

    /**
     * @param array{codigo: string, nombre: string, descripcion: ?string} $datos
     * @throws PDOException si el código o el nombre ya existen (índices únicos).
     */
    public static function crear(array $datos): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO tipo_producto (codigo, nombre, descripcion)
             VALUES (:codigo, :nombre, :descripcion)'
        );

        $stmt->execute([
            'codigo'      => $datos['codigo'],
            'nombre'      => $datos['nombre'],
            'descripcion' => $datos['descripcion'] !== '' ? $datos['descripcion'] : null,
        ]);

        return (int) Database::getConnection()->lastInsertId();
    }

    /**
     * Actualiza nombre y descripción.
     *
     * El código queda deliberadamente afuera del UPDATE: es la referencia
     * estable del tipo y no se puede modificar desde ningún lado.
     *
     * @param array{nombre: string, descripcion: ?string} $datos
     */
    public static function actualizar(int $id, array $datos): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE tipo_producto
             SET nombre = :nombre, descripcion = :descripcion
             WHERE id_tipo_producto = :id'
        );

        $stmt->execute([
            'nombre'      => $datos['nombre'],
            'descripcion' => $datos['descripcion'] !== '' ? $datos['descripcion'] : null,
            'id'          => $id,
        ]);
    }

    /**
     * Baja y alta lógica. Nunca borra la fila.
     */
    public static function cambiarEstado(int $id, bool $activo): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE tipo_producto SET activo = :activo WHERE id_tipo_producto = :id'
        );

        $stmt->execute(['activo' => $activo ? 1 : 0, 'id' => $id]);
    }

    /**
     * ¿Hay otro tipo con ese código? $excepto permite ignorar el propio registro.
     */
    public static function existeCodigo(string $codigo, ?int $excepto = null): bool
    {
        return self::existeCampo('codigo', $codigo, $excepto);
    }

    /**
     * ¿Hay otro tipo con ese nombre?
     */
    public static function existeNombre(string $nombre, ?int $excepto = null): bool
    {
        return self::existeCampo('nombre', $nombre, $excepto);
    }

    private static function existeCampo(string $campo, string $valor, ?int $excepto): bool
    {
        // $campo nunca viene del request: sale de los dos métodos de arriba.
        $sql = sprintf('SELECT 1 FROM tipo_producto WHERE %s = :valor', $campo);
        $params = ['valor' => $valor];

        if ($excepto !== null) {
            $sql .= ' AND id_tipo_producto <> :excepto';
            $params['excepto'] = $excepto;
        }

        $stmt = Database::getConnection()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Escapa los comodines de LIKE para que se busquen como texto literal.
     */
    private static function escaparLike(string $texto): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $texto);
    }
}
