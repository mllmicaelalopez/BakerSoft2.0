<?php

declare(strict_types=1);

/**
 * BakerSoft - Alta y edición de tipo de producto (HU-06 y HU-07).
 * La misma vista sirve para los dos modos.
 *
 * @var string $modo    "crear" o "editar".
 * @var array<string, mixed> $tipo
 * @var string $error
 * @var array<string, string> $errores
 */

$esEdicion = $modo === 'editar';

$accion = $esEdicion
    ? base_url('tipo-producto/' . $tipo['id_tipo_producto'] . '/editar')
    : base_url('tipo-producto/nuevo');

?>

<div class="abm-form-caja">

    <?php if ($error !== ''): ?>
        <p class="abm-alerta abm-alerta-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= e($accion) ?>" novalidate>

        <div class="abm-campo">
            <label class="abm-label" for="codigo">Código</label>
            <input
                class="abm-input<?= isset($errores['codigo']) ? ' abm-input-error' : '' ?>"
                type="text"
                id="codigo"
                name="codigo"
                value="<?= e((string) $tipo['codigo']) ?>"
                maxlength="30"
                inputmode="numeric"
                placeholder="Ej: 001"
                <?= $esEdicion ? 'disabled' : 'autofocus' ?>>
            <?php if (isset($errores['codigo'])): ?>
                <span class="abm-ayuda abm-ayuda-error"><?= e($errores['codigo']) ?></span>
            <?php elseif ($esEdicion): ?>
                <span class="abm-ayuda">El código no puede modificarse.</span>
            <?php endif; ?>
        </div>

        <div class="abm-campo">
            <label class="abm-label" for="nombre">Nombre</label>
            <input
                class="abm-input<?= isset($errores['nombre']) ? ' abm-input-error' : '' ?>"
                type="text"
                id="nombre"
                name="nombre"
                value="<?= e((string) $tipo['nombre']) ?>"
                maxlength="80"
                placeholder="Ej: Panadería"
                <?= $esEdicion ? 'autofocus' : '' ?>>
            <?php if (isset($errores['nombre'])): ?>
                <span class="abm-ayuda abm-ayuda-error"><?= e($errores['nombre']) ?></span>
            <?php endif; ?>
        </div>

        <div class="abm-campo">
            <label class="abm-label" for="descripcion">Descripción</label>
            <textarea
                class="abm-input abm-textarea<?= isset($errores['descripcion']) ? ' abm-input-error' : '' ?>"
                id="descripcion"
                name="descripcion"
                rows="3"
                maxlength="255"
                placeholder="Opcional"><?= e((string) ($tipo['descripcion'] ?? '')) ?></textarea>
            <?php if (isset($errores['descripcion'])): ?>
                <span class="abm-ayuda abm-ayuda-error"><?= e($errores['descripcion']) ?></span>
            <?php endif; ?>
        </div>

        <div class="abm-form-pie">
            <a class="abm-btn-secundario" href="<?= e(base_url('tipo-producto')) ?>">Cancelar</a>
            <button class="abm-btn" type="submit">Guardar</button>
        </div>

    </form>

</div>
