<?php

declare(strict_types=1);

/**
 * BakerSoft - Productos.
 *
 * No hay ningún DELETE acá a propósito: la baja es lógica, con cambiarEstado().
 */
final class Producto
{
    /**
     * Lista productos con el nombre de su categoría.
     *
     * @param string|null $busqueda       Texto a buscar en nombre o código.
     * @param string|null $estado         "activo", "inactivo" o null para todos.
     * @param int|null    $idTipoProducto Filtro por categoría.
     * @return array<int, array<string, mixed>>
     */
    public static function listar(
        ?string $busqueda = null,
        ?string $estado = null,
        ?int $idTipoProducto = null
    ): array {
        // El JOIN no filtra por tipo_producto.activo: un producto sigue
        // apareciendo aunque su categoría se haya dado de baja.
        $sql = 'SELECT p.id_producto, p.codigo, p.nombre, p.precio, p.descripcion,
                       p.activo, p.fecha_creacion, p.id_tipo_producto,
                       t.nombre AS categoria, t.activo AS categoria_activa
                FROM producto p
                INNER JOIN tipo_producto t ON t.id_tipo_producto = p.id_tipo_producto
                WHERE 1 = 1';

        $params = [];

        if ($busqueda !== null && $busqueda !== '') {
            // Dos placeholders distintos con el mismo valor: con prepares
            // nativos (ATTR_EMULATE_PREPARES = false) PDO no deja repetir un
            // parámetro con nombre en la misma consulta.
            $sql .= ' AND (p.nombre LIKE :busqueda_nombre OR p.codigo LIKE :busqueda_codigo)';

            $patron = '%' . self::escaparLike($busqueda) . '%';

            $params['busqueda_nombre'] = $patron;
            $params['busqueda_codigo'] = $patron;
        }

        if ($estado === 'activo') {
            $sql .= ' AND p.activo = 1';
        } elseif ($estado === 'inactivo') {
            $sql .= ' AND p.activo = 0';
        }

        if ($idTipoProducto !== null) {
            $sql .= ' AND p.id_tipo_producto = :tipo';
            $params['tipo'] = $idTipoProducto;
        }

        $sql .= ' ORDER BY p.nombre';

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
            'SELECT p.id_producto, p.codigo, p.id_tipo_producto, p.nombre, p.precio,
                    p.descripcion, p.activo, p.fecha_creacion,
                    t.nombre AS categoria
             FROM producto p
             INNER JOIN tipo_producto t ON t.id_tipo_producto = p.id_tipo_producto
             WHERE p.id_producto = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $producto = $stmt->fetch();

        return $producto === false ? null : $producto;
    }

    /**
     * @param array{codigo: string, id_tipo_producto: int, nombre: string, precio: string, descripcion: ?string} $datos
     * @throws PDOException si el código o el nombre ya existen.
     */
    public static function crear(array $datos): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO producto (codigo, id_tipo_producto, nombre, precio, descripcion)
             VALUES (:codigo, :tipo, :nombre, :precio, :descripcion)'
        );

        $stmt->execute([
            'codigo'      => $datos['codigo'],
            'tipo'        => $datos['id_tipo_producto'],
            'nombre'      => $datos['nombre'],
            'precio'      => $datos['precio'],
            'descripcion' => $datos['descripcion'] !== '' ? $datos['descripcion'] : null,
        ]);

        return (int) Database::getConnection()->lastInsertId();
    }

    /**
     * Actualiza todo menos el código, que es la referencia estable del producto
     * y queda deliberadamente afuera del UPDATE.
     *
     * @param array{id_tipo_producto: int, nombre: string, precio: string, descripcion: ?string} $datos
     */
    public static function actualizar(int $id, array $datos): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE producto
             SET id_tipo_producto = :tipo,
                 nombre           = :nombre,
                 precio           = :precio,
                 descripcion      = :descripcion
             WHERE id_producto = :id'
        );

        $stmt->execute([
            'tipo'        => $datos['id_tipo_producto'],
            'nombre'      => $datos['nombre'],
            'precio'      => $datos['precio'],
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
            'UPDATE producto SET activo = :activo WHERE id_producto = :id'
        );

        $stmt->execute(['activo' => $activo ? 1 : 0, 'id' => $id]);
    }

    /**
     * Cuántos productos activos tiene una categoría. Se usa para avisar antes
     * de desactivarla (HT-03): no hay cascada, así que esos productos
     * seguirían activos con su categoría inactiva.
     */
    public static function contarActivosPorTipo(int $idTipoProducto): int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(*) FROM producto WHERE id_tipo_producto = :tipo AND activo = 1'
        );
        $stmt->execute(['tipo' => $idTipoProducto]);

        return (int) $stmt->fetchColumn();
    }

    public static function existeCodigo(string $codigo, ?int $excepto = null): bool
    {
        return self::existeCampo('codigo', $codigo, $excepto);
    }

    public static function existeNombre(string $nombre, ?int $excepto = null): bool
    {
        return self::existeCampo('nombre', $nombre, $excepto);
    }

    private static function existeCampo(string $campo, string $valor, ?int $excepto): bool
    {
        // $campo nunca viene del request: lo fijan los dos métodos de arriba.
        $sql    = sprintf('SELECT 1 FROM producto WHERE %s = :valor', $campo);
        $params = ['valor' => $valor];

        if ($excepto !== null) {
            $sql .= ' AND id_producto <> :excepto';
            $params['excepto'] = $excepto;
        }

        $stmt = Database::getConnection()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    private static function escaparLike(string $texto): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $texto);
    }
}
