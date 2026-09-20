<?php

declare(strict_types=1);

/**
 * BakerSoft - Front controller.
 * Todas las peticiones entran por acá (ver .htaccess).
 */

// Entorno de desarrollo: mostramos los errores en pantalla.
ini_set('display_errors', '1');
error_reporting(E_ALL);

// php.ini de Laragon viene en UTC, pero MySQL usa la hora del sistema.
// Sin esto los timestamps de app.log salen corridos respecto del reloj real.
// Ajustar si el proyecto se despliega en otra zona.
date_default_timezone_set('America/Argentina/Buenos_Aires');

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/src/helpers/functions.php';
require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/config/permisos.php';
require_once BASE_PATH . '/src/config/env.php';

cargar_env(BASE_PATH . '/.env');

// PHPMailer (HT-02): solo si ya se corrió "composer install". Si no existe,
// AuthController cae al respaldo de desarrollo (mostrar el link en pantalla)
// en vez de romper el flujo de recuperación de contraseña.
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

// Controladores y modelos se cargan por nombre de clase, sin Composer.
spl_autoload_register(static function (string $clase): void {
    foreach (['controllers', 'models'] as $carpeta) {
        $archivo = BASE_PATH . '/src/' . $carpeta . '/' . $clase . '.php';

        if (is_file($archivo)) {
            require_once $archivo;
            return;
        }
    }
});

$route = current_route();

// /assets vive fuera de /public. Cuando el document root apunta a /public,
// Apache no puede alcanzar esa carpeta, así que la servimos desde acá.
// Si el document root es /www, Apache sirve los archivos directamente y
// esta rama nunca se ejecuta (ver la primera regla del .htaccess).
if (str_starts_with($route, 'assets/')) {
    servir_asset(substr($route, 7));
    exit;
}

// La sesión arranca después de los assets, que no la necesitan.
session_set_cookie_params([
    'httponly' => true,   // no accesible desde JavaScript
    'samesite' => 'Lax',  // no viaja en peticiones cross-site
]);
session_start();

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ABMs: sus rutas llevan el id adentro de la URL, así que no entran en el
// switch de rutas fijas de más abajo. Todos comparten la misma forma
// (listado, alta, edición, estado), así que los despacha una sola función.
$abms = [
    'tipo-producto' => TipoProductoController::class,
    'producto'      => ProductoController::class,
    'usuario'       => UsuarioController::class,
    'pedido'        => PedidoController::class,
];

foreach ($abms as $prefijo => $clase) {
    if ($route === $prefijo || str_starts_with($route, $prefijo . '/')) {
        despachar_abm($prefijo, new $clase(), $route, $metodo);
        exit;
    }
}

switch ($route) {
    case '':
    case 'home':
        view('home', ['db' => verificar_conexion()]);
        break;

    case 'login':
        $auth = new AuthController();
        $metodo === 'POST' ? $auth->login() : $auth->showLoginForm();
        break;

    case 'register':
        $auth = new AuthController();
        $metodo === 'POST' ? $auth->register() : $auth->showRegisterForm();
        break;

    case 'logout':
        (new AuthController())->logout();
        break;

    case 'forgot-password':
        $auth = new AuthController();
        $metodo === 'POST' ? $auth->forgotPassword() : $auth->showForgotPasswordForm();
        break;

    case 'reset-password':
        $auth = new AuthController();
        $metodo === 'POST' ? $auth->resetPassword() : $auth->showResetPasswordForm();
        break;

    case 'dashboard':
        (new DashboardController())->index();
        break;

    // Demo visual del layout de autenticación (sin lógica).
    // Se puede borrar junto con src/views/auth/_preview.php cuando existan
    // las vistas reales de login / registro / recuperación.
    case 'auth-preview':
        view('layouts/auth-layout', [
            'slot'   => 'auth/_preview',
            'titulo' => 'BakerSoft - Layout de autenticación',
        ]);
        break;

    default:
        http_response_code(404);
        view('errors/404', ['route' => $route]);
        break;
}

/**
 * Despacha las rutas estándar de un ABM:
 *
 *   {prefijo}                  GET   -> index()
 *   {prefijo}/nuevo            GET   -> showCreateForm()
 *   {prefijo}/nuevo            POST  -> store()
 *   {prefijo}/{id}/editar      GET   -> showEditForm($id)
 *   {prefijo}/{id}/editar      POST  -> update($id)
 *   {prefijo}/{id}/estado      POST  -> toggleEstado($id)
 *
 * Cualquier otra combinación cae en 404.
 */
function despachar_abm(string $prefijo, object $controlador, string $route, string $metodo): void
{
    $escapado = preg_quote($prefijo, '#');

    if ($route === $prefijo) {
        $controlador->index();
    } elseif ($route === $prefijo . '/nuevo') {
        $metodo === 'POST' ? $controlador->store() : $controlador->showCreateForm();
    } elseif (preg_match('#^' . $escapado . '/(\d+)/editar$#', $route, $m)) {
        $metodo === 'POST' ? $controlador->update((int) $m[1]) : $controlador->showEditForm((int) $m[1]);
    } elseif (preg_match('#^' . $escapado . '/(\d+)/estado$#', $route, $m) && $metodo === 'POST') {
        $controlador->toggleEstado((int) $m[1]);
    } else {
        http_response_code(404);
        view('errors/404', ['route' => $route]);
    }
}

/**
 * Prueba la conexión a MySQL y devuelve un resumen para la página de test.
 *
 * @return array{ok: bool, error: string, servidor: ?string, tablas: string[]}
 */
function verificar_conexion(): array
{
    $resultado = [
        'ok'       => false,
        'error'    => '',
        'servidor' => null,
        'tablas'   => [],
    ];

    try {
        $pdo = Database::getConnection();

        $resultado['ok']       = true;
        $resultado['servidor'] = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $resultado['tablas']   = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $resultado['error'] = $e->getMessage();
    }

    return $resultado;
}

/**
 * Entrega un archivo estático de /assets.
 *
 * @param string $relativo Ruta dentro de /assets, ej. "css/app.css".
 */
function servir_asset(string $relativo): void
{
    $tipos = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'webp'  => 'image/webp',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    $carpeta = realpath(BASE_PATH . '/assets');
    $archivo = realpath(BASE_PATH . '/assets/' . $relativo);
    $ext     = strtolower(pathinfo($relativo, PATHINFO_EXTENSION));

    // realpath() resuelve los "../", así que comparar el prefijo alcanza
    // para asegurarnos de no salir nunca de /assets.
    $dentro = $carpeta !== false
        && $archivo !== false
        && str_starts_with($archivo, $carpeta . DIRECTORY_SEPARATOR);

    if (!$dentro || !is_file($archivo) || !isset($tipos[$ext])) {
        http_response_code(404);
        return;
    }

    header('Content-Type: ' . $tipos[$ext]);
    header('Content-Length: ' . (string) filesize($archivo));
    readfile($archivo);
}
