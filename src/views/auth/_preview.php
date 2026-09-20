<?php
declare(strict_types=1);

/**
 * Vista de previsualización del layout de autenticación.
 *
 * Solo maquetado: muestra cómo queda la tarjeta con los estilos de auth.css.
 * No tiene lógica ni envía el formulario a ningún lado; se puede borrar cuando
 * existan las vistas reales de login / registro / recuperación.
 */
?>

<div class="auth-avatar" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"/>
    </svg>
</div>

<h2 class="auth-titulo">Iniciar sesión</h2>
<p class="auth-subtitulo">Ingresa tus credenciales para continuar</p>

<div class="auth-campo">
    <label class="auth-label" for="preview-email">Correo electrónico</label>
    <input class="auth-input" type="email" id="preview-email" placeholder="ejemplo@email.com" disabled>
</div>

<div class="auth-campo">
    <label class="auth-label" for="preview-pass">Contraseña</label>
    <div class="auth-input-grupo">
        <input class="auth-input" type="password" id="preview-pass" placeholder="Ingresa tu contraseña" disabled>
        <button class="auth-input-accion" type="button" disabled>ver</button>
    </div>
    <span class="auth-ayuda">Debe cumplir la política de seguridad.</span>
</div>

<div class="auth-opciones">
    <label class="auth-recordarme">
        <input type="checkbox" disabled> Recordarme
    </label>
    <span class="auth-links">
        <a class="auth-link" href="#">Registrarse</a>
        <a class="auth-link" href="#">Olvidé mi contraseña</a>
    </span>
</div>

<button class="auth-btn" type="button" disabled>Ingresar</button>

<p class="auth-pie">Vista de previsualización &middot; sin lógica</p>
