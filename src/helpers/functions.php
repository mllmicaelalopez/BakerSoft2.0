<?php

declare(strict_types=1);

/**
 * BakerSoft - Helpers globales.
 * Funciones chicas de uso general disponibles en todo el proyecto.
 */

/**
 * Escapa texto para imprimirlo en HTML de forma segura.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Prefijo web de la aplicación: "" con un virtual host apuntando a /public,
 * "/BakerSoft/public" si se entra por http://localhost/BakerSoft/public/.
 *
 * Se calcula con SCRIPT_NAME, pero solo cuando esa variable apunta de verdad al
 * front controller: el servidor embebido de PHP (php -S con router) guarda ahí
 * la URI pedida en lugar del script, y en ese caso el prefijo es vacío.
 */
function app_base(): string
{
    static $base = null;

    if ($base !== null) {
        return $base;
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $front  = basename(str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? 'index.php'));

    // dirname() usa el separador del sistema: en Windows devuelve "\" para "/index.php".
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');

    $base = str_ends_with($script, '/' . $front) ? $dir : '';

    return $base;
}

/**
 * URL base de la aplicación (la carpeta donde vive el front controller).
 */
function base_url(string $path = ''): string
{
    return app_base() . '/' . ltrim($path, '/');
}

/**
 * URL de un archivo dentro de /assets (que vive fuera de /public).
 */
function asset(string $path = ''): string
{
    $root = preg_replace('#/public$#', '', app_base()) ?? '';

    return $root . '/assets/' . ltrim($path, '/');
}

/**
 * Ruta solicitada, sin la carpeta base ni la query string.
 * Ej.: /BakerSoft/public/usuarios/nuevo  ->  "usuarios/nuevo"
 */
function current_route(): string
{
    $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = app_base();

    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }

    return trim(rawurldecode($uri), '/');
}

/**
 * Renderiza una vista de /src/views pasándole datos.
 *
 * @param string               $name Nombre sin extensión, ej. "home" o "errors/404".
 * @param array<string, mixed> $data Variables disponibles dentro de la vista.
 */
function view(string $name, array $data = []): void
{
    $file = BASE_PATH . '/src/views/' . $name . '.php';

    if (!is_file($file)) {
        throw new RuntimeException("Vista no encontrada: {$name}");
    }

    extract($data, EXTR_SKIP);
    require $file;
}

/**
 * Redirige a una ruta interna de la aplicación y corta la ejecución.
 */
function redirect(string $ruta = ''): never
{
    header('Location: ' . base_url($ruta));
    exit;
}

/**
 * URL absoluta de una ruta interna, ej. http://localhost/BakerSoft/public/login
 *
 * El host sale de HTTP_HOST, que lo manda el cliente y por lo tanto se puede
 * falsificar. Alcanza para armar el link de recuperación en desarrollo; si
 * alguna vez se manda por email de verdad, el host tiene que ser fijo.
 */
function url_absoluta(string $ruta = ''): string
{
    $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

    return $esquema . '://' . $host . base_url($ruta);
}

/* ---------------------------------------------------------------------------
   Mensajes flash: sobreviven a un redirect y se leen una sola vez.
   --------------------------------------------------------------------------- */

/**
 * @param string $tipo "ok" o "error".
 */
function flash_set(string $mensaje, string $tipo = 'ok'): void
{
    $_SESSION['flash'] = ['mensaje' => $mensaje, 'tipo' => $tipo];
}

/**
 * Devuelve el mensaje pendiente y lo borra.
 *
 * @return array{mensaje: string, tipo: string}|null
 */
function flash_get(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

/**
 * Política de contraseñas: mínimo 8 caracteres, una letra, un número y un
 * carácter especial. Devuelve la lista de requisitos que NO se cumplen.
 *
 * @return string[]
 */
function requisitos_password_faltantes(string $password): array
{
    $faltan = [];

    if (mb_strlen($password) < 8) {
        $faltan[] = 'al menos 8 caracteres';
    }

    if (!preg_match('/\p{L}/u', $password)) {
        $faltan[] = 'una letra';
    }

    if (!preg_match('/\d/', $password)) {
        $faltan[] = 'un número';
    }

    // Especial = cualquier cosa que no sea letra, número ni espacio.
    if (!preg_match('/[^\p{L}\d\s]/u', $password)) {
        $faltan[] = 'un carácter especial';
    }

    return $faltan;
}

/* ---------------------------------------------------------------------------
   Sesión y registro de eventos
   --------------------------------------------------------------------------- */

/**
 * ¿Hay una sesión de usuario iniciada?
 */
function usuario_autenticado(): bool
{
    return isset($_SESSION['id_usuario']);
}

/**
 * Corta el acceso a una pantalla privada: si no hay sesión, manda al login.
 * Se llama al principio de los controladores que requieren autenticación.
 */
function requireAuth(): void
{
    if (!usuario_autenticado()) {
        redirect('login');
    }

    // Sesiones que arrancaron antes de que existiera el rol (HT-01) tienen
    // id_usuario pero no 'rol'. Sin este parche, requireRole() les negaría
    // hasta el dashboard y no tendrían forma de volver a loguearse (login
    // redirige a dashboard si ya hay sesión). Se autocompleta una vez,
    // leyendo el rol actual de la cuenta, y listo.
    if (!isset($_SESSION['rol'])) {
        $usuario = Usuario::buscarPorId((int) $_SESSION['id_usuario']);
        $_SESSION['rol'] = $usuario['rol_codigo'] ?? '';
    }
}

/**
 * Datos del usuario logueado, o null si no hay sesión.
 *
 * Existe para que cualquier vista (el sidebar, por ejemplo) pueda mostrar
 * quién está logueado sin depender de que cada controlador se lo pase.
 *
 * @return array{id: int, nombre: string, email: string, rol: string}|null
 */
function usuario_actual(): ?array
{
    if (!usuario_autenticado()) {
        return null;
    }

    return [
        'id'     => (int) $_SESSION['id_usuario'],
        'nombre' => (string) ($_SESSION['nombre_usuario'] ?? ''),
        'email'  => (string) ($_SESSION['email'] ?? ''),
        'rol'    => (string) ($_SESSION['rol'] ?? ''),
    ];
}

/**
 * Corta el acceso a una pantalla si el rol de la sesión no figura en
 * CAPACIDADES (src/config/permisos.php) para el módulo indicado.
 *
 * Se llama después de requireAuth(), al principio de cada acción del
 * controlador. Agregar un módulo nuevo es sumar una línea a CAPACIDADES: esta
 * función no necesita cambios.
 */
function requireRole(string $modulo): void
{
    $permitidos = CAPACIDADES[$modulo] ?? [];
    $rol        = (string) ($_SESSION['rol'] ?? '');

    if (!in_array($rol, $permitidos, true)) {
        http_response_code(403);
        view('errors/403', ['modulo' => $modulo]);
        exit;
    }
}

/**
 * Iniciales para el avatar: "Ana Lopez" -> "AL", "Ana" -> "AN".
 */
function iniciales(string $nombre): string
{
    $palabras = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];

    if ($palabras === []) {
        return '?';
    }

    if (count($palabras) === 1) {
        // Un solo nombre: se toman sus dos primeras letras.
        return mb_strtoupper(mb_substr($palabras[0], 0, 2));
    }

    return mb_strtoupper(mb_substr($palabras[0], 0, 1) . mb_substr($palabras[1], 0, 1));
}

/**
 * IP del cliente.
 *
 * Usa solo REMOTE_ADDR a propósito: las cabeceras tipo X-Forwarded-For las
 * puede falsificar el cliente, así que no sirven para un log de seguridad
 * mientras no haya un proxy de confianza por delante.
 */
function ip_cliente(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconocida');
}

/**
 * Escribe una línea en storage/logs/app.log.
 *
 * Los errores de escritura se ignoran a propósito: que falle el log no debe
 * romper la pantalla que está viendo el usuario.
 */
function app_log(string $mensaje): void
{
    $carpeta = BASE_PATH . '/storage/logs';

    if (!is_dir($carpeta) && !@mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
        return;
    }

    $linea = sprintf('[%s] %s%s', date('Y-m-d H:i:s'), $mensaje, PHP_EOL);

    @file_put_contents($carpeta . '/app.log', $linea, FILE_APPEND | LOCK_EX);
}
