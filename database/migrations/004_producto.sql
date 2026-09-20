-- ---------------------------------------------------------------------------
-- BakerSoft - Migración 004: producto (HU-01 a HU-04 de Sprint 2)
--
-- La baja es lógica: se apaga `activo`, nunca se borra la fila.
-- Depende de la migración 003 (tipo_producto).
--
--   mysql -u root -p < database/migrations/004_producto.sql
-- ---------------------------------------------------------------------------

USE `bakersoft_db`;

CREATE TABLE IF NOT EXISTS `producto` (
    `id_producto`      INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `codigo`           VARCHAR(30)    NOT NULL,
    `id_tipo_producto` INT UNSIGNED   NOT NULL,
    `nombre`           VARCHAR(120)   NOT NULL,
    `precio`           DECIMAL(10, 2) NOT NULL,
    `descripcion`      VARCHAR(255)   NULL     DEFAULT NULL,
    `activo`           BOOLEAN        NOT NULL DEFAULT TRUE,
    `fecha_creacion`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_producto`),
    UNIQUE KEY `uq_producto_codigo` (`codigo`),
    UNIQUE KEY `uq_producto_nombre` (`nombre`),
    KEY `idx_producto_tipo` (`id_tipo_producto`),
    CONSTRAINT `fk_producto_tipo_producto`
        FOREIGN KEY (`id_tipo_producto`)
        REFERENCES `tipo_producto` (`id_tipo_producto`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
