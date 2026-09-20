<?php

declare(strict_types=1);

/**
 * BakerSoft - Edición de rol de usuario (HT-01: Roles y Permisos).
 *
 * Nombre y email se muestran de solo lectura: lo único que se edita acá es
 * el rol. La contraseña no se toca desde este formulario.
 *
 * La variable se llama $cuenta y no $usuario a propósito: app-layout usa
 * $usuario para los datos del usuario logueado (sidebar).
 *
 * @var array<string, mixed> $cuenta
 * @var array<int, array<string, mixed>> $roles
 * @var string $error
 * @var array<string, string> $errores
 */

?>

<div class="abm-form-caja">

    <?php if ($error !== ''): ?>
        <p class="abm-alerta abm-alerta-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= e(base_url('usuario/' . $cuenta['id_usuario'] . '/editar')) ?>" novalidate>

        <div class="abm-campo">
            <span class="abm-label">Nombre</span>
            <input class="abm-input" type="text" value="<?= e((string) $cuenta['nombre_usuario']) ?>" disabled>
        </div>

        <div class="abm-campo">
            <span class="abm-label">Email</span>
            <input class="abm-input" type="text" value="<?= e((string) $cuenta['email']) ?>" disabled>
        </div>

        <div class="abm-campo">
            <label class="abm-label" for="id_rol">Rol</label>
            <select
                class="abm-input<?= isset($errores['id_rol']) ? ' abm-input-error' : '' ?>"
                id="id_rol"
                name="id_rol"
                autofocus>
                <?php foreach ($roles as $rol): ?>
                    <option value="<?= e((string) $rol['id_rol']) ?>"
                        <?= (string) $cuenta['id_rol'] === (string) $rol['id_rol'] ? 'selected' : '' ?>>
                        <?= e($rol['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errores['id_rol'])): ?>
                <span class="abm-ayuda abm-ayuda-error"><?= e($errores['id_rol']) ?></span>
            <?php endif; ?>
        </div>

        <div class="abm-form-pie">
            <a class="abm-btn-secundario" href="<?= e(base_url('usuario')) ?>">Cancelar</a>
            <button class="abm-btn" type="submit">Guardar</button>
        </div>

    </form>

</div>
