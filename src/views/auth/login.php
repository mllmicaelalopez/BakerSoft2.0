<?php

declare(strict_types=1);

/**
 * BakerSoft - Login (HU-02). Slot del auth-layout.
 *
 * @var string                $error   Mensaje genérico arriba del formulario.
 * @var array<string, string> $errores Errores por campo.
 * @var string                $email   Valor a repoblar en el campo email.
 */

$error   = $error   ?? '';
$errores = $errores ?? [];
$email   = $email   ?? '';

?>

<div class="auth-avatar" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"/>
    </svg>
</div>

<h2 class="auth-titulo">Iniciar sesión</h2>
<p class="auth-subtitulo">Ingresa tus credenciales para continuar</p>

<?php $flash = flash_get(); ?>
<?php if ($flash !== null): ?>
    <p class="auth-alerta auth-alerta-<?= e($flash['tipo']) ?>" role="status"><?= e($flash['mensaje']) ?></p>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <p class="auth-alerta auth-alerta-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form method="post" action="<?= e(base_url('login')) ?>" novalidate>

    <div class="auth-campo">
        <label class="auth-label" for="email">Correo electrónico</label>
        <input
            class="auth-input<?= isset($errores['email']) ? ' auth-input-error' : '' ?>"
            type="email"
            id="email"
            name="email"
            value="<?= e($email) ?>"
            placeholder="ejemplo@email.com"
            autocomplete="email"
            autofocus
            <?= isset($errores['email']) ? 'aria-invalid="true" aria-describedby="error-email"' : '' ?>>
        <?php if (isset($errores['email'])): ?>
            <span class="auth-ayuda auth-ayuda-error" id="error-email"><?= e($errores['email']) ?></span>
        <?php endif; ?>
    </div>

    <div class="auth-campo">
        <label class="auth-label" for="password">Contraseña</label>
        <div class="auth-input-grupo">
            <input
                class="auth-input<?= isset($errores['password']) ? ' auth-input-error' : '' ?>"
                type="password"
                id="password"
                name="password"
                placeholder="Ingresa tu contraseña"
                autocomplete="current-password"
                <?= isset($errores['password']) ? 'aria-invalid="true" aria-describedby="error-password"' : '' ?>>
            <button
                class="auth-input-accion"
                type="button"
                data-toggle-password="password"
                aria-controls="password"
                aria-pressed="false">ver</button>
        </div>
        <?php if (isset($errores['password'])): ?>
            <span class="auth-ayuda auth-ayuda-error" id="error-password"><?= e($errores['password']) ?></span>
        <?php endif; ?>
    </div>

    <div class="auth-opciones">
        <label class="auth-recordarme">
            <input type="checkbox" name="recordarme" value="1"> Recordarme
        </label>
        <span class="auth-links">
            <a class="auth-link" href="<?= e(base_url('register')) ?>">Registrarse</a>
            <a class="auth-link" href="<?= e(base_url('forgot-password')) ?>">Olvidé mi contraseña</a>
        </span>
    </div>

    <button class="auth-btn" type="submit">Ingresar</button>

</form>
