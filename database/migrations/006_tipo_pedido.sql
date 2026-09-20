-- ---------------------------------------------------------------------------
-- BakerSoft - Migración 006: tipo_pedido (HU-09, Sprint 4)
--
-- Dominio cerrado de 2 valores fijos (Habitual / Evento), mismo criterio que
-- la tabla `roles`: sin ABM, se siembra directo acá. anticipacion_minima_horas
-- es la cantidad mínima de horas entre "ahora" y la fecha de entrega que pide
-- ese tipo de pedido (0 = sin exigencia, 48 = con exigencia y aviso).
--
--   mysql -u root -p < database/migrations/006_tipo_pedido.sql
-- ---------------------------------------------------------------------------

USE `bakersoft_db`;

CREATE TABLE IF NOT EXISTS `tipo_pedido` (
    `id_tipo_pedido`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`                     VARCHAR(30)  NOT NULL,
    `anticipacion_minima_horas`  INT UNSIGNED NOT NULL DEFAULT 0,
    `activo`                     BOOLEAN      NOT NULL DEFAULT TRUE,
    `fecha_creacion`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_tipo_pedido`),
    UNIQUE KEY `uq_tipo_pedido_nombre` (`nombre`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Los id_tipo_pedido van fijos (1-2) a propósito, mismo criterio que `roles`:
-- PedidoController los usa para decidir cuándo aplica el aviso de anticipación.
INSERT INTO `tipo_pedido` (`id_tipo_pedido`, `nombre`, `anticipacion_minima_horas`, `activo`) VALUES
    (1, 'Habitual', 0,  1),
    (2, 'Evento',   48, 1)
ON DUPLICATE KEY UPDATE
    `nombre`                    = VALUES(`nombre`),
    `anticipacion_minima_horas` = VALUES(`anticipacion_minima_horas`),
    `activo`                    = VALUES(`activo`);
