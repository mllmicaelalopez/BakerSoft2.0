-- ---------------------------------------------------------------------------
-- BakerSoft - Migración 007: cliente (HU-09, Sprint 4)
--
-- Entidad de soporte mínima para el alta de pedido: todavía no tiene ABM
-- propio (sin edición ni baja), solo alta desde el formulario de pedido.
-- `activo` queda reservado para una eventual baja lógica futura; no se usa
-- todavía (todo cliente nace activo y así se mantiene por ahora).
--
--   mysql -u root -p < database/migrations/007_cliente.sql
-- ---------------------------------------------------------------------------

USE `bakersoft_db`;

CREATE TABLE IF NOT EXISTS `cliente` (
    `id_cliente`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre_razon_social` VARCHAR(150) NOT NULL,
    `tipo_cliente`        ENUM('Persona', 'Empresa') NOT NULL,
    `cuit_cuil`           VARCHAR(20)  NULL     DEFAULT NULL,
    `telefono`            VARCHAR(30)  NULL     DEFAULT NULL,
    `email`               VARCHAR(150) NULL     DEFAULT NULL,
    `direccion`           VARCHAR(255) NULL     DEFAULT NULL,
    `activo`              BOOLEAN      NOT NULL DEFAULT TRUE,
    `fecha_creacion`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_cliente`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
