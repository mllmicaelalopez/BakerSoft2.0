<?php
declare(strict_types=1);

/** @var string $route */

$titulo = 'BakerSoft - 404';
require BASE_PATH . '/src/views/layouts/header.php';
?>

<h1>404</h1>
<p class="lead">No hay ninguna ruta registrada para <code><?= e($route) ?></code>.</p>
<p><a class="link" href="<?= e(base_url()) ?>">Volver al inicio</a></p>

<?php require BASE_PATH . '/src/views/layouts/footer.php'; ?>
