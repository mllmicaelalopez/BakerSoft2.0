<?php

declare(strict_types=1);

/**
 * BakerSoft - Layout de las pantallas de autenticación.
 * Lo comparten login, registro y recuperación de contraseña.
 *
 * Un único fondo con gradiente durazno cubre toda la pantalla; encima se
 * apoyan el bloque de marca (izquierda) y la tarjeta blanca del formulario.
 *
 * Uso desde el front controller o un controlador:
 *
 *     view('layouts/auth-layout', [
 *         'slot'   => 'auth/login',        // vista que va adentro de la tarjeta
 *         'titulo' => 'Iniciar sesión',
 *     ]);
 *
 * Variables opcionales:
 *   $titulo       <title> de la página.
 *   $marca        Texto del label chico en mayúsculas.
 *   $encabezado   Título grande del panel izquierdo.
 *   $descripcion  Párrafo del panel izquierdo.
 *   $modificador  Clase extra sobre .auth, por si alguna pantalla necesita
 *                 una variante puntual del fondo o del espaciado.
 *
 * Cualquier otro dato que se pase llega también a la vista del slot.
 *
 * @var string $slot
 */

$resolverVista = static function (string $nombre): string {
    if (!preg_match('#^[A-Za-z0-9/_-]+$#', $nombre) || str_contains($nombre, '..')) {
        throw new RuntimeException("auth-layout: nombre de vista inválido: {$nombre}");
    }

    $archivo = BASE_PATH . '/src/views/' . $nombre . '.php';

    if (!is_file($archivo)) {
        throw new RuntimeException("auth-layout: no existe la vista: {$nombre}");
    }

    return $archivo;
};

if (!isset($slot)) {
    throw new RuntimeException('auth-layout: falta la variable $slot.');
}

$slotArchivo = $resolverVista($slot);

// Modal opcional encima de la tarjeta (ej. "Olvidé mi contraseña" sobre el login).
$modal        = $modal ?? null;
$modalArchivo = $modal !== null ? $resolverVista($modal) : null;

$titulo      ??= 'BakerSoft';
$marca       ??= 'BakerSoft';
$encabezado  ??= 'Sistema de Gestión';
$descripcion ??= 'Administra tu panadería de manera eficiente con nuestro sistema integral de gestión.';
$modificador ??= '';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/auth.css')) ?>">
</head>
<body>
    <!-- Fondo único con gradiente. Los dos bloques se apoyan encima. -->
    <div class="<?= e(trim('auth ' . $modificador)) ?>">

        <!-- Bloque de marca, sin fondo propio. Se oculta en pantallas angostas. -->
        <aside class="auth-marca">
            <p class="auth-marca-label"><?= e($marca) ?></p>
            <h1 class="auth-marca-titulo"><?= e($encabezado) ?></h1>
            <p class="auth-marca-texto"><?= e($descripcion) ?></p>
        </aside>

        <!-- Tarjeta con el contenido de cada pantalla. -->
        <main class="auth-tarjeta">
            <?php require $slotArchivo; ?>
        </main>

    </div>

    <?php if ($modalArchivo !== null): ?>
        <!-- Overlay: oscurece la pantalla y centra su propia tarjeta. -->
        <div class="auth-modal-fondo">
            <div class="auth-modal" role="dialog" aria-modal="true" aria-labelledby="modal-titulo">
                <?php require $modalArchivo; ?>
            </div>
        </div>
    <?php endif; ?>

    <script src="<?= e(asset('js/auth.js')) ?>" defer></script>
</body>
</html>
