<?php
declare(strict_types=1);

/** @var string $modulo */

$titulo = 'BakerSoft - 403';
require BASE_PATH . '/src/views/layouts/header.php';
?>

<h1>403</h1>
<p class="lead">No tenés permiso para acceder a esta sección.</p>
<p>
    <a class="link" href="<?= e(base_url('dashboard')) ?>">Volver al inicio</a>
    &middot;
    <!-- Salida de emergencia: si "Volver al inicio" también diera 403 (rol sin
         acceso a ningún módulo), esto siempre saca a la persona del bucle. -->
    <a class="link" href="<?= e(base_url('logout')) ?>">Cerrar sesión</a>
</p>

<?php require BASE_PATH . '/src/views/layouts/footer.php'; ?>
