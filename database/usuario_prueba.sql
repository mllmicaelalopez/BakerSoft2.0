-- ---------------------------------------------------------------------------
-- BakerSoft - Usuario de prueba para el login (HU-02).
--
-- SOLO PARA DESARROLLO LOCAL. Borralo antes de cualquier despliegue:
--   DELETE FROM usuarios WHERE email = 'test@bakersoft.local';
--
--   email:      test@bakersoft.local
--   contraseña: Test1234!
--
-- El hash se generó con password_hash('Test1234!', PASSWORD_DEFAULT).
-- ---------------------------------------------------------------------------

USE `bakersoft_db`;

INSERT INTO `usuarios` (`nombre_usuario`, `email`, `password_hash`, `activo`)
VALUES (
    'Administrador de prueba',
    'test@bakersoft.local',
    '$2y$10$OL8hidgMY1mUIvYUH8k/OesX03cTXxI4T.F/yp6ie4GbtbdxTG162',
    1
)
ON DUPLICATE KEY UPDATE
    `nombre_usuario` = VALUES(`nombre_usuario`),
    `password_hash`  = VALUES(`password_hash`),
    `activo`         = VALUES(`activo`);
