-- ---------------------------------------------------------------------------
-- BakerSoft - Migración 003: tipo_producto (HU-05 a HU-08)
--
-- En la interfaz se muestra como "Categoría".
-- La baja es lógica: se apaga `activo`, nunca se borra la fila.
--
--   mysql -u root -p < database/migrations/003_tipo_producto.sql
-- ---------------------------------------------------------------------------

USE `bakersoft_db`;

CREATE TABLE IF NOT EXISTS `tipo_producto` (
    `id_tipo_producto` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `codigo`           VARCHAR(30)  NOT NULL,
    `nombre`           VARCHAR(80)  NOT NULL,
    `descripcion`      VARCHAR(255) NULL     DEFAULT NULL,
    `activo`           BOOLEAN      NOT NULL DEFAULT TRUE,
    `fecha_creacion`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_tipo_producto`),
    UNIQUE KEY `uq_tipo_producto_codigo` (`codigo`),
    UNIQUE KEY `uq_tipo_producto_nombre` (`nombre`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
