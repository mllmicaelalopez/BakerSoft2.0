-- ---------------------------------------------------------------------------
-- BakerSoft - Migración 005: roles y permisos (HT-01, deuda técnica de
-- Sprint 2 — "Gestión de Usuarios: Roles y Permisos").
--
-- Agrega la tabla `roles`, la siembra con los 4 roles del organigrama y suma
-- `usuarios.id_rol` como FK. Es idempotente: se puede correr más de una vez.
--
--   mysql -u root -p < database/migrations/005_roles.sql
-- ---------------------------------------------------------------------------

USE `bakersoft_db`;

CREATE TABLE IF NOT EXISTS `roles` (
    `id_rol` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `codigo` VARCHAR(30)  NOT NULL,
    `nombre` VARCHAR(60)  NOT NULL,
    `activo` BOOLEAN      NOT NULL DEFAULT TRUE,
    PRIMARY KEY (`id_rol`),
    UNIQUE KEY `uq_roles_codigo` (`codigo`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Los id_rol van fijos (1-4) a propósito: el registro público y el resto del
-- código dependen de un id conocido para el rol "cajero" (ver Usuario::crear
-- en src/models/Usuario.php). No cambiar estos ids sin actualizar ese código.
INSERT INTO `roles` (`id_rol`, `codigo`, `nombre`, `activo`) VALUES
    (1, 'administrador',     'Administrador',     1),
    (2, 'cajero',            'Cajero',             1),
    (3, 'repartidor',        'Repartidor',         1),
    (4, 'maestro_panadero',  'Maestro Panadero',   1)
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `activo` = VALUES(`activo`);

-- ---------------------------------------------------------------------------
-- usuarios.id_rol: se agrega con DEFAULT 2 (cajero) para que ninguna cuenta
-- preexistente quede como administrador sin que alguien lo decida a propósito.
-- MySQL no soporta ADD COLUMN IF NOT EXISTS, así que se consulta
-- information_schema y se arma el ALTER solo si hace falta (mismo patrón que
-- la migración 002).
-- ---------------------------------------------------------------------------
SET @existe := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'usuarios'
      AND COLUMN_NAME  = 'id_rol'
);
SET @sql := IF(@existe = 0,
    'ALTER TABLE `usuarios` ADD COLUMN `id_rol` INT UNSIGNED NOT NULL DEFAULT 2 AFTER `activo`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- La FK se agrega aparte (y también de forma idempotente) porque MySQL
-- tampoco soporta ADD CONSTRAINT IF NOT EXISTS.
SET @existe := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA      = DATABASE()
      AND TABLE_NAME        = 'usuarios'
      AND CONSTRAINT_NAME   = 'fk_usuarios_rol'
);
SET @sql := IF(@existe = 0,
    'ALTER TABLE `usuarios`
        ADD CONSTRAINT `fk_usuarios_rol`
        FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- El usuario de prueba (database/usuario_prueba.sql) queda como administrador
-- a propósito, para poder probar el ABM de Producto/Tipo de Producto y la
-- nueva pantalla de usuarios sin tener que tocar la base a mano.
UPDATE `usuarios` SET `id_rol` = 1 WHERE `email` = 'test@bakersoft.local';
