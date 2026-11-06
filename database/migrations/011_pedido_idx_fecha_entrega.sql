-- ---------------------------------------------------------------------------
-- BakerSoft - Migración 011: índice por fecha_entrega en pedido (HU-13, Sprint 5)
--
-- `pedido` ya tiene índices por estado y por cliente, pero no por
-- `fecha_entrega`, que es por donde filtra y ordena el listado de próximas
-- entregas (HU-13) y por donde va a filtrar el informe de HU-14. Sin este
-- índice esas consultas recorren toda la tabla a medida que crece la
-- cantidad de pedidos.
--
-- Idempotente: si el índice ya existe, no hace nada.
--
--   mysql -u root -p < database/migrations/011_pedido_idx_fecha_entrega.sql
-- ---------------------------------------------------------------------------

USE `bakersoft_db`;

SET @existe := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'pedido'
      AND INDEX_NAME   = 'idx_pedido_fecha_entrega'
);
SET @sql := IF(@existe = 0,
    'ALTER TABLE `pedido` ADD INDEX `idx_pedido_fecha_entrega` (`fecha_entrega`)',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
