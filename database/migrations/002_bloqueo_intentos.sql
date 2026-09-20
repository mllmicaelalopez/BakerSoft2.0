-- ---------------------------------------------------------------------------
-- BakerSoft - Migración 002: bloqueo por intentos fallidos (HU-04)
--
-- Agrega el control de intentos por IP, la alerta a administración y las dos
-- columnas que llevan la cuenta de bloqueos en una ventana de 24 horas.
--
-- Es idempotente: se puede correr más de una vez.
--
--   mysql -u root -p < database/migrations/002_bloqueo_intentos.sql
-- ---------------------------------------------------------------------------

USE `bakersoft_db`;

-- ---------------------------------------------------------------------------
-- Intentos por IP, independientes de la cuenta. Ventana fija de 1 minuto:
-- ventana_inicio marca cuándo arrancó, y al vencerse el contador vuelve a 1.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limit_ip` (
    `ip`             VARCHAR(45)  NOT NULL,
    `intentos`       INT UNSIGNED NOT NULL DEFAULT 0,
    `ventana_inicio` DATETIME     NOT NULL,
    PRIMARY KEY (`ip`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Aviso para administración cuando una cuenta se bloquea repetidamente.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `alertas_admin` (
    `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`   VARCHAR(150) NOT NULL,
    `ip`      VARCHAR(45)  NOT NULL,
    `fecha`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `mensaje` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_alertas_admin_email` (`email`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- usuarios: contador de bloqueos y arranque de la ventana de 24 horas.
--
-- MySQL no soporta ADD COLUMN IF NOT EXISTS, así que se consulta
-- information_schema y se arma el ALTER solo si hace falta.
-- ---------------------------------------------------------------------------
SET @existe := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'usuarios'
      AND COLUMN_NAME  = 'bloqueos_24h'
);
SET @sql := IF(@existe = 0,
    'ALTER TABLE `usuarios` ADD COLUMN `bloqueos_24h` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `bloqueado_hasta`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'usuarios'
      AND COLUMN_NAME  = 'primer_bloqueo_en'
);
SET @sql := IF(@existe = 0,
    'ALTER TABLE `usuarios` ADD COLUMN `primer_bloqueo_en` DATETIME NULL DEFAULT NULL AFTER `bloqueos_24h`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
