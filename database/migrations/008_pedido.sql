-- ---------------------------------------------------------------------------
-- BakerSoft - Migración 008: pedido (HU-09, Sprint 4)
--
-- Depende de cliente (007), tipo_pedido (006) y usuarios. La baja es lógica
-- vía el campo `estado` (nunca se borra la fila).
--
-- Columnas que esta HU (alta) no usa todavía, pero que ya quedan listas para
-- las próximas HU del mismo sprint:
--   - motivo_cancelacion (HU-12, baja lógica / cancelación)
--   - fecha_ultima_modificacion, usuario_ultima_modificacion,
--     modificado_en_preparacion, fecha_hora_modificacion_en_preparacion (HU-10)
--
--   mysql -u root -p < database/migrations/008_pedido.sql
-- ---------------------------------------------------------------------------

USE `bakersoft_db`;

CREATE TABLE IF NOT EXISTS `pedido` (
    `id_pedido`                                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_cliente`                               INT UNSIGNED NOT NULL,
    `id_tipo_pedido`                           INT UNSIGNED NOT NULL,
    `usuario_registro`                         INT UNSIGNED NOT NULL,
    `fecha_creacion`                           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_entrega`                            DATETIME     NOT NULL,
    `estado`                                   ENUM('Nuevo', 'Confirmado', 'En preparación', 'Listo', 'Entregado', 'Cancelado')
                                                             NOT NULL DEFAULT 'Nuevo',
    `confirmado_fuera_de_anticipacion`         BOOLEAN      NOT NULL DEFAULT FALSE,
    `fecha_hora_confirmacion_fuera_plazo`      DATETIME     NULL     DEFAULT NULL,
    `motivo_cancelacion`                       VARCHAR(255) NULL     DEFAULT NULL,
    `fecha_ultima_modificacion`                DATETIME     NULL     DEFAULT NULL,
    `usuario_ultima_modificacion`              INT UNSIGNED NULL     DEFAULT NULL,
    `modificado_en_preparacion`                BOOLEAN      NOT NULL DEFAULT FALSE,
    `fecha_hora_modificacion_en_preparacion`   DATETIME     NULL     DEFAULT NULL,
    PRIMARY KEY (`id_pedido`),
    KEY `idx_pedido_cliente` (`id_cliente`),
    KEY `idx_pedido_estado` (`estado`),
    CONSTRAINT `fk_pedido_cliente`
        FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_pedido_tipo_pedido`
        FOREIGN KEY (`id_tipo_pedido`) REFERENCES `tipo_pedido` (`id_tipo_pedido`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_pedido_usuario_registro`
        FOREIGN KEY (`usuario_registro`) REFERENCES `usuarios` (`id_usuario`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_pedido_usuario_ultima_modificacion`
        FOREIGN KEY (`usuario_ultima_modificacion`) REFERENCES `usuarios` (`id_usuario`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
