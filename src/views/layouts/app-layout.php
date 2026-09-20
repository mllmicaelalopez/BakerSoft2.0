<?php

declare(strict_types=1);

/**
 * BakerSoft - Layout de las pantallas internas (dashboard, productos, etc.).
 *
 * Sidebar fijo a la izquierda y panel de contenido a la derecha, que recibe
 * la vista de cada pantalla por slot, igual que auth-layout.php.
 *
 * Uso:
 *     view('layouts/app-layout', [
 *         'slot'       => 'dashboard',
 *         'titulo'     => 'Inicio',
 *         'menuActivo' => 'inicio',
 *     ]);
 *
 * Variables opcionales:
 *   $titulo      Título del header y del <title>.
 *   $menuActivo  Clave del ítem de menú a resaltar.
 *   $usuario     Datos del usuario; por defecto sale de la sesión.
 *
 * @var string $slot
 */

if (!isset($slot) || !preg_match('#^[A-Za-z0-9/_-]+$#', $slot) || str_contains($slot, '..')) {
    throw new RuntimeException('app-layout: falta la variable $slot o tiene un valor inválido.');
}

$slotArchivo = BASE_PATH . '/src/views/' . $slot . '.php';

if (!is_file($slotArchivo)) {
    throw new RuntimeException("app-layout: no existe la vista del slot: {$slot}");
}

$titulo     ??= 'BakerSoft';
$menuActivo ??= '';

// El sidebar se sirve solo con los datos de sesión: así cualquier pantalla
// futura no tiene que acordarse de pasárselos.
$usuario ??= usuario_actual() ?? ['nombre' => '', 'email' => ''];

$inicialesUsuario = iniciales((string) $usuario['nombre']);

$menu = [
    'inicio'    => ['texto' => 'Inicio', 'ruta' => 'dashboard'],
    'productos' => [
        'texto' => 'Productos',
        'hijos' => [
            'producto'      => ['texto' => 'Productos',        'ruta' => 'producto'],
            'tipo-producto' => ['texto' => 'Tipo de Producto', 'ruta' => 'tipo-producto'],
            'stock'         => ['texto' => 'Stock',            'ruta' => 'stock'],
        ],
    ],
    'pedido'   => ['texto' => 'Pedidos',  'ruta' => 'pedido'],
    'clientes' => ['texto' => 'Clientes', 'ruta' => 'clientes'],
    'finanzas' => ['texto' => 'Finanzas', 'ruta' => 'finanzas'],
    'usuario'  => ['texto' => 'Usuarios', 'ruta' => 'usuario'],
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BakerSoft - <?= e($titulo) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="app-body">
<div class="app">

    <aside class="app-sidebar">

        <div class="app-marca">
            <p class="app-marca-label">BakerSoft</p>
            <p class="app-marca-nombre">Demo</p>
        </div>

        <div class="app-usuario">
            <span class="app-avatar" aria-hidden="true"><?= e($inicialesUsuario) ?></span>
            <div class="app-usuario-datos">
                <p class="app-usuario-nombre"><?= e($usuario['nombre']) ?></p>
                <p class="app-usuario-email"><?= e($usuario['email']) ?></p>
            </div>
        </div>

        <nav class="app-nav" aria-label="Navegación principal">
            <?php foreach ($menu as $clave => $item): ?>

                <?php if (!isset($item['hijos'])): ?>

                    <a class="app-nav-item<?= $clave === $menuActivo ? ' is-activo' : '' ?>"
                       href="<?= e(base_url($item['ruta'])) ?>"
                       <?= $clave === $menuActivo ? 'aria-current="page"' : '' ?>><?= e($item['texto']) ?></a>

                <?php else: ?>

                    <?php
                    // El grupo se abre si la pantalla actual es uno de sus hijos.
                    // <details> da el plegado nativo, sin una línea de JS.
                    $grupoActivo = array_key_exists($menuActivo, $item['hijos']);
                    ?>
                    <details class="app-nav-grupo"<?= $grupoActivo ? ' open' : '' ?>>
                        <summary class="app-nav-item<?= $grupoActivo ? ' is-activo-grupo' : '' ?>">
                            <?= e($item['texto']) ?>
                        </summary>

                        <div class="app-subnav">
                            <?php foreach ($item['hijos'] as $claveHijo => $hijo): ?>
                                <a class="app-subnav-item<?= $claveHijo === $menuActivo ? ' is-activo' : '' ?>"
                                   href="<?= e(base_url($hijo['ruta'])) ?>"
                                   <?= $claveHijo === $menuActivo ? 'aria-current="page"' : '' ?>><?= e($hijo['texto']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </details>

                <?php endif; ?>

            <?php endforeach; ?>
        </nav>

        <div class="app-sidebar-pie">
            <a class="app-nav-item" href="<?= e(base_url('configuraciones')) ?>">Configuraciones</a>
            <a class="app-nav-item app-nav-salir" href="<?= e(base_url('logout')) ?>">Cerrar sesión</a>
        </div>

    </aside>

    <div class="app-principal">

        <header class="app-header">
            <h1 class="app-header-titulo"><?= e($titulo) ?></h1>
            <span class="app-avatar app-avatar-chico" title="<?= e($usuario['nombre']) ?>"><?= e($inicialesUsuario) ?></span>
        </header>

        <main class="app-contenido">
            <?php require $slotArchivo; ?>
        </main>

    </div>

</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
