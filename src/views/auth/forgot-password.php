<?php

declare(strict_types=1);

/**
 * BakerSoft - "Olvidé mi contraseña", primer paso (HU-03).
 * Se renderiza como modal encima del login (ver $modal en auth-layout).
 *
 * Los nombres llevan sufijo "Reset" porque esta vista y login.php se renderizan
 * en el mismo scope: sin eso, un error del modal pintaría también el formulario
 * de login que queda de fondo.
 *
 * @var bool   $enviado      Ya se procesó el POST.
 * @var string $emailReset   Valor a repoblar.
 * @var string $devLink      Link de recuperación (solo en modo desarrollo).
 * @var array<string, string> $erroresReset
 */

$enviado      = $enviado      ?? false;
$emailReset   = $emailReset   ?? '';
$devLink      = $devLink      ?? '';
$erroresReset = $erroresReset ?? [];

?>

<a class="auth-modal-cerrar" href="<?= e(base_url('login')) ?>" aria-label="Cerrar">&times;</a>

<div class="auth-avatar" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"/>
    </svg>
</div>

<h2 class="auth-titulo" id="modal-titulo">Restablecer contraseña</h2>
<p class="auth-subtitulo">Ingresa tu email y te enviaremos instrucciones</p>

<?php if ($enviado): ?>
    <p class="auth-alerta auth-alerta-ok" role="status">Si existe una cuenta, enviamos un enlace</p>

    <?php if ($devLink !== ''): ?>
        <p class="auth-dev-link">
            <strong>Modo desarrollo — no se envía email</strong>
            <a class="auth-link" href="<?= e($devLink) ?>"><?= e($devLink) ?></a>
        </p>
    <?php endif; ?>
<?php endif; ?>

<form method="post" action="<?= e(base_url('forgot-password')) ?>" novalidate>

    <div class="auth-campo">
        <label class="auth-label" for="email-reset">Correo electrónico</label>
        <input
            class="auth-input<?= isset($erroresReset['email']) ? ' auth-input-error' : '' ?>"
            type="email"
            id="email-reset"
            name="email"
            value="<?= e($emailReset) ?>"
            placeholder="ejemplo@email.com"
            autocomplete="email"
            autofocus
            <?= isset($erroresReset['email']) ? 'aria-invalid="true" aria-describedby="error-email-reset"' : '' ?>>
        <?php if (isset($erroresReset['email'])): ?>
            <span class="auth-ayuda auth-ayuda-error" id="error-email-reset"><?= e($erroresReset['email']) ?></span>
        <?php endif; ?>
    </div>

    <button class="auth-btn" type="submit">Enviar enlace</button>

</form>

<p class="auth-pie"><a class="auth-link" href="<?= e(base_url('login')) ?>">Volver a iniciar sesión</a></p>
