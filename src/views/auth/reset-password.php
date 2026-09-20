<?php

declare(strict_types=1);

/**
 * BakerSoft - "Restablecer contraseña" con token (HU-03).
 *
 * @var bool   $tokenValido
 * @var string $token
 * @var string $email    Email asociado al token (solo lectura).
 * @var string $error    Mensaje general del formulario.
 * @var array<string, string> $errores
 */

$tokenValido = $tokenValido ?? false;
$token       = $token       ?? '';
$email       = $email       ?? '';
$error       = $error       ?? '';
$errores     = $errores     ?? [];

?>

<div class="auth-avatar" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"/>
    </svg>
</div>

<h2 class="auth-titulo">Restablecer contraseña</h2>
<p class="auth-subtitulo">Ingresa una nueva contraseña segura para tu cuenta.</p>

<?php if (!$tokenValido): ?>

    <p class="auth-alerta auth-alerta-error" role="alert">Token inválido o expirado</p>

    <p class="auth-ayuda">
        El enlace puede haber vencido, o ya haberse usado para cambiar la contraseña.
    </p>

    <p style="margin-top:1.2rem">
        <a class="auth-btn" href="<?= e(base_url('forgot-password')) ?>"
           style="text-align:center;text-decoration:none">Solicitar un enlace nuevo</a>
    </p>

<?php else: ?>

    <?php if ($error !== ''): ?>
        <p class="auth-alerta auth-alerta-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= e(base_url('reset-password')) ?>" novalidate>

        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="auth-campo">
            <label class="auth-label" for="email-token">Correo electrónico</label>
            <!-- Deshabilitado: el email sale del token, no de lo que se envíe. -->
            <input class="auth-input" type="email" id="email-token"
                   value="<?= e($email) ?>" disabled>
        </div>

        <div class="auth-campo">
            <label class="auth-label" for="password">Nueva contraseña</label>
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

        <button class="auth-btn" type="submit">Guardar</button>

    </form>

<?php endif; ?>

<p class="auth-pie"><a class="auth-link" href="<?= e(base_url('login')) ?>">Volver a iniciar sesión</a></p>
