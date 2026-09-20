<?php

declare(strict_types=1);

/**
 * BakerSoft - Registro de cuenta. Slot del auth-layout.
 *
 * @var string $error
 * @var array<string, string> $errores
 * @var string $nombreUsuario
 * @var string $email
 */

$error         = $error         ?? '';
$errores       = $errores       ?? [];
$nombreUsuario = $nombreUsuario ?? '';
$email         = $email         ?? '';

?>

<div class="auth-avatar" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"/>
    </svg>
</div>

<h2 class="auth-titulo">Crear cuenta</h2>
<p class="auth-subtitulo">Completá tus datos para empezar a usar BakerSoft</p>

<?php if ($error !== ''): ?>
    <p class="auth-alerta auth-alerta-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form method="post" action="<?= e(base_url('register')) ?>" novalidate>

    <div class="auth-campo">
        <label class="auth-label" for="nombre_usuario">Nombre de usuario</label>
        <input
            class="auth-input<?= isset($errores['nombre_usuario']) ? ' auth-input-error' : '' ?>"
            type="text"
            id="nombre_usuario"
            name="nombre_usuario"
            value="<?= e($nombreUsuario) ?>"
            placeholder="Tu nombre"
            maxlength="60"
            autocomplete="name"
            autofocus
            <?= isset($errores['nombre_usuario']) ? 'aria-invalid="true" aria-describedby="error-nombre"' : '' ?>>
        <?php if (isset($errores['nombre_usuario'])): ?>
            <span class="auth-ayuda auth-ayuda-error" id="error-nombre"><?= e($errores['nombre_usuario']) ?></span>
        <?php endif; ?>
    </div>

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
                autocomplete="new-password"
                <?= isset($errores['password']) ? 'aria-invalid="true" aria-describedby="error-password"' : '' ?>>
            <button class="auth-input-accion" type="button"
                    data-toggle-password="password" aria-controls="password"
                    aria-pressed="false">ver</button>
        </div>
        <?php if (isset($errores['password'])): ?>
            <span class="auth-ayuda auth-ayuda-error" id="error-password"><?= e($errores['password']) ?></span>
        <?php else: ?>
            <span class="auth-ayuda">Mínimo 8 caracteres, con una letra, un número y un carácter especial.</span>
        <?php endif; ?>
    </div>

    <div class="auth-campo">
        <label class="auth-label" for="password_confirm">Confirmar contraseña</label>
        <div class="auth-input-grupo">
            <input
                class="auth-input<?= isset($errores['password_confirm']) ? ' auth-input-error' : '' ?>"
                type="password"
                id="password_confirm"
                name="password_confirm"
                autocomplete="new-password"
                <?= isset($errores['password_confirm']) ? 'aria-invalid="true" aria-describedby="error-confirm"' : '' ?>>
            <button class="auth-input-accion" type="button"
                    data-toggle-password="password_confirm" aria-controls="password_confirm"
                    aria-pressed="false">ver</button>
        </div>
        <?php if (isset($errores['password_confirm'])): ?>
            <span class="auth-ayuda auth-ayuda-error" id="error-confirm"><?= e($errores['password_confirm']) ?></span>
        <?php endif; ?>
    </div>

    <button class="auth-btn" type="submit">Crear cuenta</button>

</form>

<p class="auth-pie">
    ¿Ya tenés cuenta? <a class="auth-link" href="<?= e(base_url('login')) ?>">Iniciar sesión</a>
</p>
