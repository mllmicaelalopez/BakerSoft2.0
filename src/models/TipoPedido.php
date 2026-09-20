<?php

declare(strict_types=1);

/**
 * BakerSoft - Tipos de pedido (HU-09, Sprint 4).
 *
 * Dominio cerrado de 2 valores fijos (Habitual / Evento), sembrados por la
 * migración 006. Sin ABM: no hay alta, edición ni baja desde la interfaz,
 * mismo criterio que la tabla `roles`.
 */
final class TipoPedido
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listar(): array
    {
        $stmt = Database::getConnection()->query(
            'SELECT id_tipo_pedido, nombre, anticipacion_minima_horas, activo
             FROM tipo_pedido
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
            'SELECT id_tipo_pedido, nombre, anticipacion_minima_horas, activo
             FROM tipo_pedido
             WHERE id_tipo_pedido = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $tipo = $stmt->fetch();

        return $tipo === false ? null : $tipo;
    }
}
