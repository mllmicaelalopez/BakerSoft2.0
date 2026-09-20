-- ---------------------------------------------------------------------------
-- BakerSoft - Esquema inicial de base de datos
--
-- Importar con:
--   mysql -u root -p < database/schema.sql
-- o pegando este archivo en phpMyAdmin / HeidiSQL.
-- ---------------------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `bakersoft_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `bakersoft_db`;

-- ---------------------------------------------------------------------------
-- Tabla: usuarios
-- Cuentas que acceden al sistema. Los campos intentos_fallidos y
-- bloqueado_hasta quedan listos para el bloqueo por intentos de login.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id_usuario`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre_usuario`    VARCHAR(60)  NOT NULL,
    `email`             VARCHAR(150) NOT NULL,
    `password_hash`     VARCHAR(255) NOT NULL,
    `activo`            BOOLEAN      NOT NULL DEFAULT TRUE,
    `intentos_fallidos` INT UNSIGNED NOT NULL DEFAULT 0,
    `bloqueado_hasta`   DATETIME     NULL     DEFAULT NULL,
    -- Bloqueos acumulados en una ventana de 24 h (HU-04).
    `bloqueos_24h`      INT UNSIGNED NOT NULL DEFAULT 0,
    `primer_bloqueo_en` DATETIME     NULL     DEFAULT NULL,
    `fecha_creacion`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_usuario`),
    UNIQUE KEY `uq_usuarios_email` (`email`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Tabla: rate_limit_ip
-- Intentos de login por IP, independientes de la cuenta (HU-04).
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
-- Tabla: alertas_admin
-- Aviso cuando una cuenta se bloquea repetidamente en 24 h (HU-04).
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
-- Tabla: password_resets
-- Tokens de un solo uso para restablecer la contraseña (HU-03).
-- El token son 64 caracteres hex: bin2hex(random_bytes(32)).
-- ---------------------------------------------------------------------------
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

-- ---------------------------------------------------------------------------
-- Tabla: tipo_producto (migración 003)
-- En la interfaz se muestra como "Categoría". La baja es lógica: se apaga
-- `activo`, nunca se borra la fila.
-- ---------------------------------------------------------------------------
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

-- ---------------------------------------------------------------------------
-- Tabla: producto (migración 004)
-- La baja es lógica: se apaga `activo`, nunca se borra la fila.
-- ---------------------------------------------------------------------------
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

-- ---------------------------------------------------------------------------
-- Tabla: roles (migración 005 - HT-01, Roles y Permisos)
-- Dominio cerrado de 4 valores fijos (organigrama de PP1); sin ABM propio.
-- ---------------------------------------------------------------------------
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
-- usuarios.id_rol (migración 005): se agrega por ALTER, no en la propia
-- definición de `usuarios` de más arriba, para no tocar esa tabla. Se agrega
-- con DEFAULT 2 (cajero) para que ninguna cuenta preexistente quede como
-- administrador sin que alguien lo decida a propósito. MySQL no soporta ADD
-- COLUMN IF NOT EXISTS, así que se consulta information_schema y se arma el
-- ALTER solo si hace falta (mismo patrón que la migración 002).
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
-- pantalla de usuarios sin tener que tocar la base a mano.
UPDATE `usuarios` SET `id_rol` = 1 WHERE `email` = 'test@bakersoft.local';

-- ---------------------------------------------------------------------------
-- Tabla: tipo_pedido (migración 006 - HU-09, Sprint 4)
-- Dominio cerrado de 2 valores fijos (Habitual / Evento), mismo criterio que
-- `roles`: sin ABM, se siembra directo acá. anticipacion_minima_horas es la
-- cantidad mínima de horas entre "ahora" y la fecha de entrega que pide ese
-- tipo de pedido (0 = sin exigencia, 48 = con exigencia y aviso).
-- ---------------------------------------------------------------------------
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

-- ---------------------------------------------------------------------------
-- Tabla: cliente (migración 007 - HU-09, Sprint 4)
-- Entidad de soporte mínima para el alta de pedido: todavía no tiene ABM
-- propio (sin edición ni baja), solo alta desde el formulario de pedido.
-- `activo` queda reservado para una eventual baja lógica futura; no se usa
-- todavía (todo cliente nace activo y así se mantiene por ahora).
-- ---------------------------------------------------------------------------
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

-- ---------------------------------------------------------------------------
-- Tabla: pedido (migración 008 - HU-09, Sprint 4)
-- Depende de cliente (arriba), tipo_pedido (arriba) y usuarios. La baja es
-- lógica vía el campo `estado` (nunca se borra la fila).
--
-- Columnas que la HU-09 (alta) no usa todavía, pero que ya quedan listas para
-- las próximas HU del mismo sprint:
--   - motivo_cancelacion (HU-12, baja lógica / cancelación)
--   - fecha_ultima_modificacion, usuario_ultima_modificacion,
--     modificado_en_preparacion, fecha_hora_modificacion_en_preparacion (HU-10)
-- ---------------------------------------------------------------------------
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

-- ---------------------------------------------------------------------------
-- Tabla: detalle_pedido (migración 009 - HU-09, Sprint 4)
-- Asociativa entre pedido y producto. A diferencia del resto de las FK del
-- proyecto, id_pedido borra en cascada: un detalle no tiene sentido sin su
-- pedido (y los pedidos nunca se borran físicamente, solo cambian de
-- `estado`, así que en la práctica este ON DELETE CASCADE no dispara nunca;
-- queda por integridad referencial). El producto, en cambio, sigue el mismo
-- criterio que el resto del sistema: RESTRICT, nunca se borra físicamente.
-- ---------------------------------------------------------------------------
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
