-- ---------------------------------------------------------------------------
-- BakerSoft - Migración 010: unidad_medida en producto (ajuste post Sprint 4)
--
-- Agrega la unidad de medida de venta (kg o unidad) al catálogo de Producto,
-- para distinguir productos que se venden por peso (pan, tortas) de los que
-- se venden por unidad (alfajores, facturas). DEFAULT 'unidad' para que
-- ningún producto existente quede sin valor; los que se vendan por peso hay
-- que pasarlos a 'kg' a mano desde el ABM de Producto una vez corrida esta
-- migración (no es algo que el script pueda adivinar).
--
--   mysql -u root -p < database/migrations/010_producto_unidad_medida.sql
-- ---------------------------------------------------------------------------

USE `bakersoft_db`;

SET @existe := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'producto'
      AND COLUMN_NAME  = 'unidad_medida'
);
SET @sql := IF(@existe = 0,
    "ALTER TABLE `producto` ADD COLUMN `unidad_medida` ENUM('kg', 'unidad') NOT NULL DEFAULT 'unidad' AFTER `nombre`",
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
