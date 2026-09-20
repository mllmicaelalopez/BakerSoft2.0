-- ---------------------------------------------------------------------------
-- BakerSoft - Usuarios de prueba para los 4 roles (HT-01).
--
-- SOLO PARA DESARROLLO LOCAL. Borralos antes de cualquier despliegue:
--   DELETE FROM usuarios WHERE email IN (
--     'cajero@bakersoft.local', 'repartidor@bakersoft.local', 'panadero@bakersoft.local'
--   );
--
-- El administrador de prueba ya existe: es test@bakersoft.local, sembrado por
-- database/usuario_prueba.sql y pasado a administrador por la migración 005.
--
--   cajero@bakersoft.local      / Cajero123!
--   repartidor@bakersoft.local  / Repartidor123!
--   panadero@bakersoft.local    / Panadero123!
--
-- Los hashes se generaron con password_hash('...', PASSWORD_DEFAULT).
-- Requiere haber corrido database/migrations/005_roles.sql antes (id_rol).
-- ---------------------------------------------------------------------------

USE `bakersoft_db`;

INSERT INTO `usuarios` (`nombre_usuario`, `email`, `password_hash`, `activo`, `id_rol`)
VALUES (
    'Cajero de prueba',
    'cajero@bakersoft.local',
    '$2y$10$gOxBqV1EMbRFtoaIl3.8Y./l0Rr7dtPyG.NW5p9DyNxgPAdoTIfuS',
    1,
    2
)
ON DUPLICATE KEY UPDATE
    `nombre_usuario` = VALUES(`nombre_usuario`),
    `password_hash`  = VALUES(`password_hash`),
    `activo`         = VALUES(`activo`),
    `id_rol`         = VALUES(`id_rol`);

INSERT INTO `usuarios` (`nombre_usuario`, `email`, `password_hash`, `activo`, `id_rol`)
VALUES (
    'Repartidor de prueba',
    'repartidor@bakersoft.local',
    '$2y$10$ReDICpgauwxL.tFRCB6Di.k0TXxp9rWSKJ7AHdUJ39NowGQhmdWD2',
    1,
    3
)
ON DUPLICATE KEY UPDATE
    `nombre_usuario` = VALUES(`nombre_usuario`),
    `password_hash`  = VALUES(`password_hash`),
    `activo`         = VALUES(`activo`),
    `id_rol`         = VALUES(`id_rol`);

INSERT INTO `usuarios` (`nombre_usuario`, `email`, `password_hash`, `activo`, `id_rol`)
VALUES (
    'Maestro panadero de prueba',
    'panadero@bakersoft.local',
    '$2y$10$7Hmp1KcH2fagdBEqAMECGecW/vnDeJUlLYpYKVvrrXaOXNaYe8Kq.',
    1,
    4
)
ON DUPLICATE KEY UPDATE
    `nombre_usuario` = VALUES(`nombre_usuario`),
    `password_hash`  = VALUES(`password_hash`),
    `activo`         = VALUES(`activo`),
    `id_rol`         = VALUES(`id_rol`);
