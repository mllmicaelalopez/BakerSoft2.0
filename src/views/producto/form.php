<?php

declare(strict_types=1);

/**
 * BakerSoft - Alta y edición de producto. La misma vista sirve para los dos.
 *
 * @var string $modo "crear" o "editar".
 * @var array<string, mixed> $producto
 * @var array<int, array<string, mixed>> $categorias
 * @var string $error
 * @var array<string, string> $errores
 */

$esEdicion = $modo === 'editar';

$accion = $esEdicion
    ? base_url('producto/' . $producto['id_producto'] . '/editar')
    : base_url('producto/nuevo');

$activo = (bool) ($producto['activo'] ?? true);

?>

<div class="abm-form-caja abm-form-ancho">

    <?php if ($error !== ''): ?>
        <p class="abm-alerta abm-alerta-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= e($accion) ?>" novalidate>

        <div class="abm-form-cols">

            <div class="abm-form-col">

                <div class="abm-campo">
                    <label class="abm-label" for="codigo">Código</label>
                    <input
                        class="abm-input<?= isset($errores['codigo']) ? ' abm-input-error' : '' ?>"
                        type="text"
                        id="codigo"
                        name="codigo"
                        value="<?= e((string) $producto['codigo']) ?>"
                        maxlength="30"
                        placeholder="Ej: PROD-001"
                        <?= $esEdicion ? 'disabled' : 'autofocus' ?>>
                    <?php if (isset($errores['codigo'])): ?>
                        <span class="abm-ayuda abm-ayuda-error"><?= e($errores['codigo']) ?></span>
                    <?php elseif ($esEdicion): ?>
                        <span class="abm-ayuda">El código no puede modificarse.</span>
                    <?php endif; ?>
                </div>

                <div class="abm-campo">
                    <label class="abm-label" for="id_tipo_producto">Categoría</label>
                    <select
                        class="abm-input<?= isset($errores['id_tipo_producto']) ? ' abm-input-error' : '' ?>"
                        id="id_tipo_producto"
                        name="id_tipo_producto">
                        <option value="">Elegí una categoría</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= e((string) $cat['id_tipo_producto']) ?>"
                                <?= (string) $producto['id_tipo_producto'] === (string) $cat['id_tipo_producto'] ? 'selected' : '' ?>>
                                <?= e($cat['nombre']) ?><?= (bool) $cat['activo'] ? '' : ' (inactiva)' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['id_tipo_producto'])): ?>
                        <span class="abm-ayuda abm-ayuda-error"><?= e($errores['id_tipo_producto']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="abm-campo">
                    <label class="abm-label" for="nombre">Nombre</label>
                    <input
                        class="abm-input<?= isset($errores['nombre']) ? ' abm-input-error' : '' ?>"
                        type="text"
                        id="nombre"
                        name="nombre"
                        value="<?= e((string) $producto['nombre']) ?>"
                        maxlength="120"
                        placeholder="Ej: Alfajores de maicena"
                        <?= $esEdicion ? 'autofocus' : '' ?>>
                    <?php if (isset($errores['nombre'])): ?>
                        <span class="abm-ayuda abm-ayuda-error"><?= e($errores['nombre']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="abm-campo">
                    <label class="abm-label" for="precio">Precio</label>
                    <div class="abm-input-moneda">
                        <span class="abm-moneda-signo" aria-hidden="true">$</span>
                        <input
                            class="abm-input<?= isset($errores['precio']) ? ' abm-input-error' : '' ?>"
                            type="text"
                            inputmode="decimal"
                            id="precio"
                            name="precio"
                            value="<?= e((string) $producto['precio']) ?>"
                            placeholder="0,00">
                    </div>
                    <?php if (isset($errores['precio'])): ?>
                        <span class="abm-ayuda abm-ayuda-error"><?= e($errores['precio']) ?></span>
                    <?php else: ?>
                        <span class="abm-ayuda">Se acepta coma o punto decimal.</span>
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
                        placeholder="Opcional"><?= e((string) ($producto['descripcion'] ?? '')) ?></textarea>
                    <?php if (isset($errores['descripcion'])): ?>
                        <span class="abm-ayuda abm-ayuda-error"><?= e($errores['descripcion']) ?></span>
                    <?php endif; ?>
                </div>

            </div>

            <div class="abm-form-col abm-form-col-lateral">

                <!--
                    Placeholder visual: todavía no hay backend de subida de
                    archivos, así que el botón no hace nada.
                -->
                <div class="abm-campo">
                    <span class="abm-label">Imagen</span>
                    <div class="abm-imagen">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                            <circle cx="9" cy="10" r="1.6"></circle>
                            <path d="m4 17 5-4.5 4 3.5 3-2.5 4 3.5"></path>
                        </svg>
                        <button class="abm-btn-secundario" type="button" disabled>Subir Imagen</button>
                        <span class="abm-ayuda">Disponible más adelante.</span>
                    </div>
                </div>

                <div class="abm-campo">
                    <span class="abm-label">Estado</span>
                    <!--
                        Deshabilitado a propósito: el alta y la baja lógica se
                        hacen desde el listado, no desde este formulario.
                    -->
                    <label class="abm-toggle">
                        <input type="checkbox" disabled <?= $activo ? 'checked' : '' ?>>
                        <span class="abm-toggle-pista" aria-hidden="true"></span>
                        <span class="abm-toggle-texto"><?= $activo ? 'Activo' : 'Inactivo' ?></span>
                    </label>
                    <span class="abm-ayuda">
                        <?= $esEdicion ? 'Se cambia desde el listado.' : 'Los productos nuevos nacen activos.' ?>
                    </span>
                </div>

            </div>

        </div>

        <div class="abm-form-pie">
            <a class="abm-btn-secundario" href="<?= e(base_url('producto')) ?>">Cancelar</a>
            <button class="abm-btn" type="submit">Guardar</button>
        </div>

    </form>

</div>
