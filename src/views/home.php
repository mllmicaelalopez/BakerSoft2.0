<?php
declare(strict_types=1);

/**
 * Página de prueba: confirma que el proyecto levanta y que la conexión
 * a MySQL funciona. Se puede borrar cuando empiece el desarrollo real.
 *
 * @var array{ok: bool, error: string, servidor: ?string, tablas: string[]} $db
 */

$titulo = 'BakerSoft - Estado del sistema';
require BASE_PATH . '/src/views/layouts/header.php';
?>

<h1>Instalación base</h1>
<p class="lead">Si estás viendo esta página, el front controller y la estructura de carpetas están funcionando.</p>

<section class="card <?= $db['ok'] ? 'card-ok' : 'card-error' ?>">
    <h2>
        <span class="dot"></span>
        Conexión a MySQL: <?= $db['ok'] ? 'exitosa' : 'fallida' ?>
    </h2>

    <?php if ($db['ok']): ?>
        <dl class="datos">
            <dt>Base de datos</dt><dd><?= e(DB_NAME) ?></dd>
            <dt>Host</dt><dd><?= e(DB_HOST) ?>:<?= e(DB_PORT) ?></dd>
            <dt>Usuario</dt><dd><?= e(DB_USER) ?></dd>
            <dt>Servidor MySQL</dt><dd><?= e($db['servidor']) ?></dd>
        </dl>

        <h3>Tablas encontradas (<?= count($db['tablas']) ?>)</h3>
        <?php if ($db['tablas'] === []): ?>
            <p class="hint">La base existe pero está vacía. Importá <code>database/schema.sql</code>.</p>
        <?php else: ?>
            <ul class="tablas">
                <?php foreach ($db['tablas'] as $tabla): ?>
                    <li><?= e($tabla) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php else: ?>
        <p>No se pudo conectar a la base de datos:</p>
        <pre class="error"><?= e($db['error']) ?></pre>
        <p class="hint">
            Revisá que MySQL esté corriendo, que la base <code><?= e(DB_NAME) ?></code> exista
            (importá <code>database/schema.sql</code>) y que las credenciales en
            <code>src/config/database.php</code> sean las correctas.
        </p>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Próximos pasos</h2>
    <ul class="pasos">
        <li>Definir modelos en <code>src/models</code>.</li>
        <li>Agregar controladores en <code>src/controllers</code> y rutearlos desde <code>public/index.php</code>.</li>
        <li>Armar las vistas en <code>src/views</code> sobre el layout de <code>src/views/layouts</code>.</li>
    </ul>
</section>

<?php require BASE_PATH . '/src/views/layouts/footer.php'; ?>
