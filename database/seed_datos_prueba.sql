-- ---------------------------------------------------------------------------
-- BakerSoft - Datos de prueba para testeo completo del sistema.
--
-- SOLO PARA DESARROLLO LOCAL. Requiere haber corrido antes:
--   - database/migrations/003_tipo_producto.sql, 004_producto.sql,
--     005_roles.sql, 006_tipo_pedido.sql, 007_cliente.sql, 008_pedido.sql,
--     009_detalle_pedido.sql
--   - database/migrations/010_producto_unidad_medida.sql (Ajuste 7)
--   - database/usuario_prueba.sql y database/seed_usuarios_roles.sql
--     (usuarios administrador/cajero/repartidor/maestro_panadero ya sembrados)
--
-- Las secciones A-D son idempotentes (se pueden correr de nuevo sin duplicar).
-- La sección E (pedidos) NO lo es: correrla una sola vez, si no se duplican
-- los pedidos de prueba.
--
--   mysql -u root -p < database/seed_datos_prueba.sql
-- ---------------------------------------------------------------------------

USE `bakersoft_db`;

-- Los textos con tilde ("En preparación", "Fogón", etc.) se guardan bien
-- aunque el cliente de MySQL de la PC tenga otra codificación por defecto.
SET NAMES utf8mb4;

-- ===========================================================================
-- A. Categorías (tipo_producto)
-- ===========================================================================

-- Códigos 006-010 (no 001-005) y nombres "Repostería"/"Tortas y Postres" en
-- vez de "Confitería"/"Pastelería": la base ya tenía 3 categorías de pruebas
-- anteriores (001=Panadería, 002=Pastelería, 003=Confitería, con
-- "Pastelería" y "Confitería" además invertidas respecto de este seed).
-- Cambiar solo el código no alcanza: `nombre` también es UNIQUE, así que una
-- categoría nueva llamada igual que una ya existente sigue chocando sin
-- importar el código — se detectó al probar el script contra un clon antes
-- de correrlo de verdad (el primer intento, solo con los códigos
-- reasignados, pisó en silencio la descripción de esas dos categorías
-- reales). Se decidió no tocar esas 3 categorías existentes y usar códigos
-- y nombres libres en el seed en su lugar.
INSERT INTO `tipo_producto` (`codigo`, `nombre`, `descripcion`) VALUES
    ('006', 'Panificados',      'Panes y afines, vendidos por peso.'),
    ('007', 'Repostería',       'Facturas, medialunas y alfajores, por unidad.'),
    ('008', 'Tortas y Postres', 'Tortas y tartas dulces.'),
    ('009', 'Salados',          'Empanadas y tartas saladas.'),
    ('010', 'Sin TACC',         'Línea apta para celíacos.')
ON DUPLICATE KEY UPDATE
    `nombre`      = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`);

-- ===========================================================================
-- B. Productos (requiere unidad_medida del Ajuste 7)
-- ===========================================================================

-- Panificados
-- "Baguette rústica" en vez de "Pan francés": la base ya tenía un producto
-- real "Pan Frances" (PROD-001) y la collation utf8mb4_unicode_ci no
-- distingue tildes, así que "Pan francés" == "Pan Frances" para MySQL —
-- el ON DUPLICATE KEY UPDATE hubiera pisado ese producto en silencio.
INSERT INTO `producto` (`codigo`, `id_tipo_producto`, `nombre`, `precio`, `unidad_medida`, `descripcion`)
SELECT '020', t.`id_tipo_producto`, 'Baguette rústica', 3200.00, 'kg', 'Pan tipo francés, horneado diario.'
FROM `tipo_producto` t WHERE t.`nombre` = 'Panificados'
ON DUPLICATE KEY UPDATE `id_tipo_producto` = VALUES(`id_tipo_producto`), `precio` = VALUES(`precio`), `unidad_medida` = VALUES(`unidad_medida`), `descripcion` = VALUES(`descripcion`);

INSERT INTO `producto` (`codigo`, `id_tipo_producto`, `nombre`, `precio`, `unidad_medida`, `descripcion`)
SELECT '021', t.`id_tipo_producto`, 'Criollos comunes', 4200.00, 'kg', 'Criollos de manteca, receta clásica.'
FROM `tipo_producto` t WHERE t.`nombre` = 'Panificados'
ON DUPLICATE KEY UPDATE `id_tipo_producto` = VALUES(`id_tipo_producto`), `precio` = VALUES(`precio`), `unidad_medida` = VALUES(`unidad_medida`), `descripcion` = VALUES(`descripcion`);

INSERT INTO `producto` (`codigo`, `id_tipo_producto`, `nombre`, `precio`, `unidad_medida`, `descripcion`)
SELECT '022', t.`id_tipo_producto`, 'Criollos de hojaldre', 5800.00, 'kg', 'Criollos de masa hojaldrada.'
FROM `tipo_producto` t WHERE t.`nombre` = 'Panificados'
ON DUPLICATE KEY UPDATE `id_tipo_producto` = VALUES(`id_tipo_producto`), `precio` = VALUES(`precio`), `unidad_medida` = VALUES(`unidad_medida`), `descripcion` = VALUES(`descripcion`);

-- Repostería (antes "Confitería": ese nombre ya lo usaba una categoría real)
INSERT INTO `producto` (`codigo`, `id_tipo_producto`, `nombre`, `precio`, `unidad_medida`, `descripcion`)
SELECT '023', t.`id_tipo_producto`, 'Facturas surtidas', 650.00, 'unidad', 'Mix de facturas dulces.'
FROM `tipo_producto` t WHERE t.`nombre` = 'Repostería'
ON DUPLICATE KEY UPDATE `id_tipo_producto` = VALUES(`id_tipo_producto`), `precio` = VALUES(`precio`), `unidad_medida` = VALUES(`unidad_medida`), `descripcion` = VALUES(`descripcion`);

INSERT INTO `producto` (`codigo`, `id_tipo_producto`, `nombre`, `precio`, `unidad_medida`, `descripcion`)
SELECT '024', t.`id_tipo_producto`, 'Medialunas', 500.00, 'unidad', 'De manteca.'
FROM `tipo_producto` t WHERE t.`nombre` = 'Repostería'
ON DUPLICATE KEY UPDATE `id_tipo_producto` = VALUES(`id_tipo_producto`), `precio` = VALUES(`precio`), `unidad_medida` = VALUES(`unidad_medida`), `descripcion` = VALUES(`descripcion`);

INSERT INTO `producto` (`codigo`, `id_tipo_producto`, `nombre`, `precio`, `unidad_medida`, `descripcion`)
SELECT '025', t.`id_tipo_producto`, 'Alfajor de maicena', 900.00, 'unidad', 'Relleno de dulce de leche.'
FROM `tipo_producto` t WHERE t.`nombre` = 'Repostería'
ON DUPLICATE KEY UPDATE `id_tipo_producto` = VALUES(`id_tipo_producto`), `precio` = VALUES(`precio`), `unidad_medida` = VALUES(`unidad_medida`), `descripcion` = VALUES(`descripcion`);

INSERT INTO `producto` (`codigo`, `id_tipo_producto`, `nombre`, `precio`, `unidad_medida`, `descripcion`)
SELECT '026', t.`id_tipo_producto`, 'Alfajor de chocolate', 1100.00, 'unidad', 'Bañado en chocolate.'
FROM `tipo_producto` t WHERE t.`nombre` = 'Repostería'
ON DUPLICATE KEY UPDATE `id_tipo_producto` = VALUES(`id_tipo_producto`), `precio` = VALUES(`precio`), `unidad_medida` = VALUES(`unidad_medida`), `descripcion` = VALUES(`descripcion`);

-- Tortas y Postres (antes "Pastelería": ese nombre ya lo usaba una categoría real)
INSERT INTO `producto` (`codigo`, `id_tipo_producto`, `nombre`, `precio`, `unidad_medida`, `descripcion`)
SELECT '027', t.`id_tipo_producto`, 'Torta de cumpleaños chocolate', 18500.00, 'kg', 'Bizcochuelo de chocolate con relleno.'
FROM `tipo_producto` t WHERE t.`nombre` = 'Tortas y Postres'
ON DUPLICATE KEY UPDATE `id_tipo_producto` = VALUES(`id_tipo_producto`), `precio` = VALUES(`precio`), `unidad_medida` = VALUES(`unidad_medida`), `descripcion` = VALUES(`descripcion`);

INSERT INTO `producto` (`codigo`, `id_tipo_producto`, `nombre`, `precio`, `unidad_medida`, `descripcion`)
SELECT '028', t.`id_tipo_producto`, 'Torta de cumpleaños vainilla', 17200.00, 'kg', 'Bizcochuelo de vainilla con relleno.'
FROM `tipo_producto` t WHERE t.`nombre` = 'Tortas y Postres'
ON DUPLICATE KEY UPDATE `id_tipo_producto` = VALUES(`id_tipo_producto`), `precio` = VALUES(`precio`), `unidad_medida` = VALUES(`unidad_medida`), `descripcion` = VALUES(`descripcion`);

INSERT INTO `producto` (`codigo`, `id_tipo_producto`, `nombre`, `precio`, `unidad_medida`, `descripcion`)
SELECT '029', t.`id_tipo_producto`, 'Tarta de frutilla', 6500.00, 'unidad', 'Base de masa dulce con crema y frutillas.'
FROM `tipo_producto` t WHERE t.`nombre` = 'Tortas y Postres'
ON DUPLICATE KEY UPDATE `id_tipo_producto` = VALUES(`id_tipo_producto`), `precio` = VALUES(`precio`), `unidad_medida` = VALUES(`unidad_medida`), `descripcion` = VALUES(`descripcion`);

INSERT INTO `producto` (`codigo`, `id_tipo_producto`, `nombre`, `precio`, `unidad_medida`, `descripcion`)
SELECT '030', t.`id_tipo_producto`, 'Tarta de manzana', 6200.00, 'unidad', 'Con manzanas caramelizadas.'
FROM `tipo_producto` t WHERE t.`nombre` = 'Tortas y Postres'
ON DUPLICATE KEY UPDATE `id_tipo_producto` = VALUES(`id_tipo_producto`), `precio` = VALUES(`precio`), `unidad_medida` = VALUES(`unidad_medida`), `descripcion` = VALUES(`descripcion`);

-- Salados
INSERT INTO `producto` (`codigo`, `id_tipo_producto`, `nombre`, `precio`, `unidad_medida`, `descripcion`)
SELECT '031', t.`id_tipo_producto`, 'Empanada de carne', 800.00, 'unidad', 'Horneada, receta casera.'
FROM `tipo_producto` t WHERE t.`nombre` = 'Salados'
ON DUPLICATE KEY UPDATE `id_tipo_producto` = VALUES(`id_tipo_producto`), `precio` = VALUES(`precio`), `unidad_medida` = VALUES(`unidad_medida`), `descripcion` = VALUES(`descripcion`);

INSERT INTO `producto` (`codigo`, `id_tipo_producto`, `nombre`, `precio`, `unidad_medida`, `descripcion`)
SELECT '032', t.`id_tipo_producto`, 'Tarta de jamón y queso', 9800.00, 'kg', 'Masa salada, relleno de jamón y queso.'
FROM `tipo_producto` t WHERE t.`nombre` = 'Salados'
ON DUPLICATE KEY UPDATE `id_tipo_producto` = VALUES(`id_tipo_producto`), `precio` = VALUES(`precio`), `unidad_medida` = VALUES(`unidad_medida`), `descripcion` = VALUES(`descripcion`);

-- Sin TACC
INSERT INTO `producto` (`codigo`, `id_tipo_producto`, `nombre`, `precio`, `unidad_medida`, `descripcion`)
SELECT '033', t.`id_tipo_producto`, 'Pan sin TACC', 5200.00, 'kg', 'Apto celíacos.'
FROM `tipo_producto` t WHERE t.`nombre` = 'Sin TACC'
ON DUPLICATE KEY UPDATE `id_tipo_producto` = VALUES(`id_tipo_producto`), `precio` = VALUES(`precio`), `unidad_medida` = VALUES(`unidad_medida`), `descripcion` = VALUES(`descripcion`);

INSERT INTO `producto` (`codigo`, `id_tipo_producto`, `nombre`, `precio`, `unidad_medida`, `descripcion`)
SELECT '034', t.`id_tipo_producto`, 'Alfajor sin TACC', 1400.00, 'unidad', 'Apto celíacos.'
FROM `tipo_producto` t WHERE t.`nombre` = 'Sin TACC'
ON DUPLICATE KEY UPDATE `id_tipo_producto` = VALUES(`id_tipo_producto`), `precio` = VALUES(`precio`), `unidad_medida` = VALUES(`unidad_medida`), `descripcion` = VALUES(`descripcion`);

-- ===========================================================================
-- C. Clientes (sin unique key propia: se guarda con NOT EXISTS para que
--    correr el script de nuevo no duplique clientes)
-- ===========================================================================

INSERT INTO `cliente` (`nombre_razon_social`, `tipo_cliente`, `cuit_cuil`, `telefono`, `email`, `direccion`)
SELECT 'María Fernández', 'Persona', '27-32456789-4', '351-4567890', 'maria.fernandez@example.com', 'Av. Colón 1234, Córdoba'
WHERE NOT EXISTS (SELECT 1 FROM `cliente` WHERE `nombre_razon_social` = 'María Fernández');

INSERT INTO `cliente` (`nombre_razon_social`, `tipo_cliente`, `cuit_cuil`, `telefono`, `email`, `direccion`)
SELECT 'Juan Pérez', 'Persona', '20-30123456-7', '351-4551234', 'juan.perez@example.com', 'Bv. San Juan 567, Córdoba'
WHERE NOT EXISTS (SELECT 1 FROM `cliente` WHERE `nombre_razon_social` = 'Juan Pérez');

INSERT INTO `cliente` (`nombre_razon_social`, `tipo_cliente`, `cuit_cuil`, `telefono`, `email`, `direccion`)
SELECT 'Restaurante El Fogón', 'Empresa', '30-71234567-8', '351-4988776', 'pedidos@elfogon.com.ar', 'Rivera Indarte 890, Córdoba'
WHERE NOT EXISTS (SELECT 1 FROM `cliente` WHERE `nombre_razon_social` = 'Restaurante El Fogón');

INSERT INTO `cliente` (`nombre_razon_social`, `tipo_cliente`, `cuit_cuil`, `telefono`, `email`, `direccion`)
SELECT 'Panificados Norte S.A.', 'Empresa', '30-70456123-1', '351-4223344', 'compras@panificadosnorte.com.ar', 'Av. Recta Martinolli 4500, Córdoba'
WHERE NOT EXISTS (SELECT 1 FROM `cliente` WHERE `nombre_razon_social` = 'Panificados Norte S.A.');

INSERT INTO `cliente` (`nombre_razon_social`, `tipo_cliente`, `cuit_cuil`, `telefono`, `email`, `direccion`)
SELECT 'Eventos Dulce Ocasión', 'Empresa', '30-71567890-2', '351-4776655', 'contacto@dulceocasion.com.ar', 'Rodríguez Peña 220, Córdoba'
WHERE NOT EXISTS (SELECT 1 FROM `cliente` WHERE `nombre_razon_social` = 'Eventos Dulce Ocasión');

-- ===========================================================================
-- D. Usuario de prueba adicional (segundo cajero)
--
-- Necesario para poder probar la excepción de HU-12: un cajero solo puede
-- cancelar los pedidos que ÉL MISMO cargó, dentro de los 30 minutos. Con un
-- solo cajero de prueba no se puede verificar que el OTRO cajero no pueda.
--
--   cajero2@bakersoft.local / Cajero2123!
--
-- Hash generado con password_hash('Cajero2123!', PASSWORD_DEFAULT).
-- ===========================================================================

INSERT INTO `usuarios` (`nombre_usuario`, `email`, `password_hash`, `activo`, `id_rol`)
VALUES (
    'Otro cajero de prueba',
    'cajero2@bakersoft.local',
    '$2y$12$m/tL3ZUaC3WGhIbp.VfF3ewZmy9m8VKjDQx.uRO.uZhjcXwfZfQLu',
    1,
    2
)
ON DUPLICATE KEY UPDATE
    `nombre_usuario` = VALUES(`nombre_usuario`),
    `password_hash`  = VALUES(`password_hash`),
    `activo`         = VALUES(`activo`),
    `id_rol`         = VALUES(`id_rol`);

-- ===========================================================================
-- E. Pedidos + detalle — NO IDEMPOTENTE, correr una sola vez.
--
-- Cubre los 6 estados posibles y los 2 tipos de pedido, con productos por kg
-- y por unidad mezclados en el mismo pedido para poder probar el Ajuste 7.
-- Las fechas están escritas relativas al 20/09/2026; si corrés esto más
-- adelante, no hace falta que las cambies, son solo datos de prueba.
-- ===========================================================================

-- E.1 — Habitual / Nuevo
INSERT INTO `pedido` (`id_cliente`, `id_tipo_pedido`, `usuario_registro`, `fecha_entrega`, `estado`)
SELECT c.`id_cliente`, tp.`id_tipo_pedido`, u.`id_usuario`, '2026-09-23 10:00:00', 'Nuevo'
FROM `cliente` c, `tipo_pedido` tp, `usuarios` u
WHERE c.`nombre_razon_social` = 'Juan Pérez' AND tp.`nombre` = 'Habitual' AND u.`email` = 'cajero@bakersoft.local';
SET @id_pedido_1 := LAST_INSERT_ID();

INSERT INTO `detalle_pedido` (`id_pedido`, `id_producto`, `cantidad`)
SELECT @id_pedido_1, p.`id_producto`, 5.5 FROM `producto` p WHERE p.`nombre` = 'Baguette rústica';
INSERT INTO `detalle_pedido` (`id_pedido`, `id_producto`, `cantidad`)
SELECT @id_pedido_1, p.`id_producto`, 12 FROM `producto` p WHERE p.`nombre` = 'Facturas surtidas';

-- E.2 — Evento / Confirmado, fuera de la anticipación mínima de 48hs
INSERT INTO `pedido` (`id_cliente`, `id_tipo_pedido`, `usuario_registro`, `fecha_entrega`, `estado`,
                       `confirmado_fuera_de_anticipacion`, `fecha_hora_confirmacion_fuera_plazo`)
SELECT c.`id_cliente`, tp.`id_tipo_pedido`, u.`id_usuario`, '2026-09-22 09:00:00', 'Confirmado',
       1, NOW()
FROM `cliente` c, `tipo_pedido` tp, `usuarios` u
WHERE c.`nombre_razon_social` = 'Eventos Dulce Ocasión' AND tp.`nombre` = 'Evento' AND u.`email` = 'test@bakersoft.local';
SET @id_pedido_2 := LAST_INSERT_ID();

INSERT INTO `detalle_pedido` (`id_pedido`, `id_producto`, `cantidad`)
SELECT @id_pedido_2, p.`id_producto`, 3 FROM `producto` p WHERE p.`nombre` = 'Torta de cumpleaños chocolate';
INSERT INTO `detalle_pedido` (`id_pedido`, `id_producto`, `cantidad`)
SELECT @id_pedido_2, p.`id_producto`, 2 FROM `producto` p WHERE p.`nombre` = 'Tarta de frutilla';

-- E.3 — Habitual / En preparación
INSERT INTO `pedido` (`id_cliente`, `id_tipo_pedido`, `usuario_registro`, `fecha_entrega`, `estado`)
SELECT c.`id_cliente`, tp.`id_tipo_pedido`, u.`id_usuario`, '2026-09-21 08:00:00', 'En preparación'
FROM `cliente` c, `tipo_pedido` tp, `usuarios` u
WHERE c.`nombre_razon_social` = 'Restaurante El Fogón' AND tp.`nombre` = 'Habitual' AND u.`email` = 'cajero@bakersoft.local';
SET @id_pedido_3 := LAST_INSERT_ID();

INSERT INTO `detalle_pedido` (`id_pedido`, `id_producto`, `cantidad`)
SELECT @id_pedido_3, p.`id_producto`, 20 FROM `producto` p WHERE p.`nombre` = 'Baguette rústica';
INSERT INTO `detalle_pedido` (`id_pedido`, `id_producto`, `cantidad`)
SELECT @id_pedido_3, p.`id_producto`, 50 FROM `producto` p WHERE p.`nombre` = 'Medialunas';
INSERT INTO `detalle_pedido` (`id_pedido`, `id_producto`, `cantidad`)
SELECT @id_pedido_3, p.`id_producto`, 8 FROM `producto` p WHERE p.`nombre` = 'Criollos comunes';

-- E.4 — Habitual / Listo (registrado por el segundo cajero, para las pruebas de HU-12)
INSERT INTO `pedido` (`id_cliente`, `id_tipo_pedido`, `usuario_registro`, `fecha_entrega`, `estado`)
SELECT c.`id_cliente`, tp.`id_tipo_pedido`, u.`id_usuario`, '2026-09-20 18:00:00', 'Listo'
FROM `cliente` c, `tipo_pedido` tp, `usuarios` u
WHERE c.`nombre_razon_social` = 'María Fernández' AND tp.`nombre` = 'Habitual' AND u.`email` = 'cajero2@bakersoft.local';
SET @id_pedido_4 := LAST_INSERT_ID();

INSERT INTO `detalle_pedido` (`id_pedido`, `id_producto`, `cantidad`)
SELECT @id_pedido_4, p.`id_producto`, 6 FROM `producto` p WHERE p.`nombre` = 'Alfajor de maicena';
INSERT INTO `detalle_pedido` (`id_pedido`, `id_producto`, `cantidad`)
SELECT @id_pedido_4, p.`id_producto`, 1.5 FROM `producto` p WHERE p.`nombre` = 'Torta de cumpleaños vainilla';

-- E.5 — Habitual / Entregado
INSERT INTO `pedido` (`id_cliente`, `id_tipo_pedido`, `usuario_registro`, `fecha_entrega`, `estado`)
SELECT c.`id_cliente`, tp.`id_tipo_pedido`, u.`id_usuario`, '2026-09-18 09:00:00', 'Entregado'
FROM `cliente` c, `tipo_pedido` tp, `usuarios` u
WHERE c.`nombre_razon_social` = 'Panificados Norte S.A.' AND tp.`nombre` = 'Habitual' AND u.`email` = 'test@bakersoft.local';
SET @id_pedido_5 := LAST_INSERT_ID();

INSERT INTO `detalle_pedido` (`id_pedido`, `id_producto`, `cantidad`)
SELECT @id_pedido_5, p.`id_producto`, 4 FROM `producto` p WHERE p.`nombre` = 'Pan sin TACC';
INSERT INTO `detalle_pedido` (`id_pedido`, `id_producto`, `cantidad`)
SELECT @id_pedido_5, p.`id_producto`, 3 FROM `producto` p WHERE p.`nombre` = 'Criollos de hojaldre';

-- E.6 — Evento / Cancelado
INSERT INTO `pedido` (`id_cliente`, `id_tipo_pedido`, `usuario_registro`, `fecha_entrega`, `estado`, `motivo_cancelacion`)
SELECT c.`id_cliente`, tp.`id_tipo_pedido`, u.`id_usuario`, '2026-09-25 12:00:00', 'Cancelado', 'Cliente canceló el pedido'
FROM `cliente` c, `tipo_pedido` tp, `usuarios` u
WHERE c.`nombre_razon_social` = 'Eventos Dulce Ocasión' AND tp.`nombre` = 'Evento' AND u.`email` = 'cajero@bakersoft.local';
SET @id_pedido_6 := LAST_INSERT_ID();

INSERT INTO `detalle_pedido` (`id_pedido`, `id_producto`, `cantidad`)
SELECT @id_pedido_6, p.`id_producto`, 2 FROM `producto` p WHERE p.`nombre` = 'Tarta de jamón y queso';
INSERT INTO `detalle_pedido` (`id_pedido`, `id_producto`, `cantidad`)
SELECT @id_pedido_6, p.`id_producto`, 30 FROM `producto` p WHERE p.`nombre` = 'Empanada de carne';
