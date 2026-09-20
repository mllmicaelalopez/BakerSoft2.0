<?php

declare(strict_types=1);

/**
 * BakerSoft - Detalle de pedido: productos y cantidades de un pedido
 * (HU-09 alta, HU-10 modificación).
 */
final class DetallePedido
{
    /**
     * Agrega un producto al pedido y devuelve el id de la fila creada.
     */
    public static function crear(int $idPedido, int $idProducto, string $cantidad): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad)
             VALUES (:pedido, :producto, :cantidad)'
        );

        $stmt->execute([
            'pedido'   => $idPedido,
            'producto' => $idProducto,
            'cantidad' => $cantidad,
        ]);

        return (int) Database::getConnection()->lastInsertId();
    }

    /**
     * Productos cargados en un pedido, con el nombre del producto.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listarPorPedido(int $idPedido): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT d.id_detalle_pedido, d.id_producto, d.cantidad, p.nombre AS producto, p.codigo
             FROM detalle_pedido d
             INNER JOIN producto p ON p.id_producto = d.id_producto
             WHERE d.id_pedido = :pedido
             ORDER BY d.id_detalle_pedido'
        );
        $stmt->execute(['pedido' => $idPedido]);

        return $stmt->fetchAll();
    }

    /**
     * Borra todo el detalle de un pedido (HU-10: se usa para reemplazarlo
     * completo dentro de la misma transacción de PedidoController::update()).
     */
    public static function eliminarPorPedido(int $idPedido): void
    {
        $stmt = Database::getConnection()->prepare(
            'DELETE FROM detalle_pedido WHERE id_pedido = :pedido'
        );
        $stmt->execute(['pedido' => $idPedido]);
    }
}
