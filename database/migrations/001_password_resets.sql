-- ---------------------------------------------------------------------------
-- BakerSoft - Migración 001: tabla password_resets (HU-03)
--
-- Para bases donde ya se corrió schema.sql antes de que existiera esta tabla.
-- Es idempotente: se puede correr más de una vez sin romper nada.
--
--   mysql -u root -p < database/migrations/001_password_resets.sql
-- ---------------------------------------------------------------------------

USE `bakersoft_db`;

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`          VARCHAR(150) NOT NULL,
    `token`          VARCHAR(64)  NOT NULL,
    `expira_en`      DATETIME     NOT NULL,
    `usado`          BOOLEAN      NOT NULL DEFAULT FALSE,
    `fecha_creacion` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_password_resets_token` (`token`),
    KEY `idx_password_resets_email` (`email`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
