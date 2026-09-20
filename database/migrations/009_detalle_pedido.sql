-- ---------------------------------------------------------------------------
-- BakerSoft - Migración 009: detalle_pedido (HU-09, Sprint 4)
--
-- Asociativa entre pedido y producto. Depende de pedido (008) y producto.
-- A diferencia del resto de las FK del proyecto, id_pedido borra en cascada:
-- un detalle no tiene sentido sin su pedido (y los pedidos nunca se borran
-- físicamente, solo cambian de `estado`, así que en la práctica este ON
-- DELETE CASCADE no dispara nunca; queda por integridad referencial). El
-- producto, en cambio, sigue el mismo criterio que el resto del sistema:
-- RESTRICT, nunca se borra físicamente.
--
--   mysql -u root -p < database/migrations/009_detalle_pedido.sql
-- ---------------------------------------------------------------------------

USE `bakersoft_db`;

CREATE TABLE IF NOT EXISTS `detalle_pedido` (
    `id_detalle_pedido` INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `id_pedido`         INT UNSIGNED   NOT NULL,
    `id_producto`       INT UNSIGNED   NOT NULL,
    `cantidad`          DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (`id_detalle_pedido`),
    KEY `idx_detalle_pedido_pedido` (`id_pedido`),
    CONSTRAINT `fk_detalle_pedido_pedido`
        FOREIGN KEY (`id_pedido`) REFERENCES `pedido` (`id_pedido`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_detalle_pedido_producto`
        FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
