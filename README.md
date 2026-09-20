# BakerSoft

Sistema de gestión para panaderías. PHP puro (sin frameworks), MySQL y PDO.

Estado actual: **autenticación completa, ABM de Producto y Tipo de Producto (con unidad
de medida kg/unidad), Roles y Permisos, módulo de Pedidos completo (alta, modificación,
consulta y cancelación), y envío real de email de recuperación**. El dashboard todavía
usa datos mock (ventas, más vendidos, stock y próximas entregas): se reemplazan por
consultas reales cuando el dashboard empiece a consultar los módulos ya existentes
(Pedidos) y los que falten (Ventas, Stock).

Funciona:

- **Autenticación** (`AuthController`): registro, login con bcrypt, recuperación de
  contraseña por email real (PHPMailer), bloqueo de cuenta tras intentos fallidos y
  rate-limit por IP.
- **Roles y Permisos** (`UsuarioController`, tabla `roles`): 4 roles
  (administrador, cajero, repartidor, maestro_panadero). El mapa de capacidades por
  módulo vive en `src/config/permisos.php`.
- **ABM de Producto y Tipo de Producto** (`ProductoController`, `TipoProductoController`):
  alta, edición y baja lógica (nunca se borra, solo se apaga `activo`), reservado a
  `administrador`. Producto tiene unidad de medida (`kg` o `unidad`), que determina si
  acepta cantidades decimales en un pedido.
- **Pedidos** (`PedidoController`, tablas `pedido`, `detalle_pedido`, `cliente`,
  `tipo_pedido`): alta y modificación reservadas a `administrador` y `cajero` (con
  excepción de administrador para modificar pedidos "En preparación"); consulta abierta
  a los 4 roles; cancelación (baja lógica, nunca se borra la fila) con motivo, con
  excepción para que el propio cajero cancele su pedido reciente aunque su rol no tenga
  permiso general de cancelación.

## Requisitos

- PHP 8.1 o superior con la extensión `pdo_mysql`
- MySQL 5.7+ / MariaDB
- Apache (Laragon o XAMPP)
- Composer (opcional: solo hace falta para el envío real de email, ver más abajo)

## Puesta en marcha

**1. Ubicar el proyecto**

Copiá la carpeta `BakerSoft` dentro del directorio web de tu servidor:

- Laragon: `C:\laragon\www\BakerSoft`
- XAMPP: `C:\xampp\htdocs\BakerSoft`

**2. Crear la base de datos**

Importá `database/schema.sql`. Desde la terminal:

```bash
mysql -u root -p < database/schema.sql
```

O pegá el contenido del archivo en phpMyAdmin / HeidiSQL. Eso crea la base
`bakersoft_db` y la tabla `usuarios`.

Después corré las migraciones, en orden, de `database/migrations/`:

```bash
mysql -u root -p < database/migrations/001_password_resets.sql
mysql -u root -p < database/migrations/002_bloqueo_intentos.sql
mysql -u root -p < database/migrations/003_tipo_producto.sql
mysql -u root -p < database/migrations/004_producto.sql
mysql -u root -p < database/migrations/005_roles.sql
mysql -u root -p < database/migrations/006_tipo_pedido.sql
mysql -u root -p < database/migrations/007_cliente.sql
mysql -u root -p < database/migrations/008_pedido.sql
mysql -u root -p < database/migrations/009_detalle_pedido.sql
mysql -u root -p < database/migrations/010_producto_unidad_medida.sql
```

Todas son idempotentes (se pueden correr más de una vez sin romper nada). Para tener
usuarios de prueba en cada rol, además corré:

```bash
mysql -u root -p < database/usuario_prueba.sql
mysql -u root -p < database/seed_usuarios_roles.sql
```

Y para tener catálogo, clientes y pedidos de ejemplo con los que probar el sistema:

```bash
mysql -u root -p < database/seed_datos_prueba.sql
```

`seed_datos_prueba.sql` agrega categorías, productos, clientes y un segundo usuario
cajero de forma idempotente, pero la parte de pedidos de ejemplo no es idempotente:
pensado para correrse una sola vez.

**3. Configurar las credenciales**

Editá las constantes del principio de `src/config/database.php`:

```php
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'bakersoft_db';
const DB_USER = 'root';
const DB_PASS = '';
```

Los valores por defecto ya sirven para Laragon y XAMPP (usuario `root` sin contraseña).

**4. (Opcional) Configurar el envío real de email**

La recuperación de contraseña manda un email de verdad con PHPMailer. Sin este paso el
proyecto sigue funcionando igual: el link de recuperación se muestra en pantalla y queda
en `storage/logs/app.log`, como respaldo de desarrollo.

Instalá las dependencias con Composer:

```bash
composer install
```

Copiá `.env.example` a `.env` y completá tus credenciales SMTP:

```bash
cp .env.example .env
```

```env
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-cuenta-de-prueba@gmail.com
MAIL_PASSWORD=contraseña-de-aplicación-de-16-letras
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tu-cuenta-de-prueba@gmail.com
MAIL_FROM_NAME="BakerSoft"
```

Con Gmail, `MAIL_PASSWORD` tiene que ser una
[Contraseña de aplicación](https://myaccount.google.com/apppasswords) (no la contraseña
normal de la cuenta), y esa cuenta necesita la verificación en dos pasos activada.
`.env` nunca se sube al repositorio (está en `.gitignore`).

**5. Abrir el proyecto**

Con Apache levantado, entrá a:

```
http://localhost/BakerSoft/public/
```

Deberías ver la página de estado con el mensaje **"Conexión a MySQL: exitosa"**, la versión
del servidor y la lista de tablas de la base.

También podés usar el servidor embebido de PHP, sin Apache. Hay que pasarle
`public/index.php` como router para que todas las rutas entren por el front controller:

```bash
php -S localhost:8000 -t public public/index.php
```

## Estructura

```
BakerSoft/
├── public/                 # Único directorio expuesto a la web
│   ├── index.php           # Front controller: todas las peticiones entran acá
│   └── .htaccess           # URLs amigables (reescribe todo a index.php)
├── src/
│   ├── config/
│   │   ├── database.php    # Credenciales + clase Database (PDO singleton)
│   │   ├── permisos.php    # CAPACIDADES: mapa de roles permitidos por módulo
│   │   └── env.php         # Carga de .env (cargar_env(), env())
│   ├── controllers/        # AuthController, DashboardController, ProductoController,
│   │                       # TipoProductoController, UsuarioController, PedidoController
│   ├── models/              # Usuario, Rol, Producto, TipoProducto, TipoPedido, Cliente,
│   │                       # Pedido, DetallePedido
│   ├── views/               # Plantillas PHP
│   │   ├── layouts/         # app-layout (pantallas internas) / auth-layout / header / footer
│   │   ├── auth/            # login, registro, recuperación de contraseña
│   │   ├── producto/, tipo-producto/, usuario/, pedido/  # ABMs (index + form)
│   │   ├── errors/          # 404, 403
│   │   └── home.php         # Página de prueba de conexión
│   └── helpers/
│       └── functions.php   # e(), view(), base_url(), requireAuth(), requireRole(), etc.
├── assets/                 # css, js, imágenes
├── database/
│   ├── schema.sql          # Esquema inicial
│   ├── migrations/         # Cambios incrementales, numerados y ejecutados en orden
│   ├── usuario_prueba.sql  # Usuario de prueba (desarrollo local)
│   ├── seed_usuarios_roles.sql  # Un usuario de prueba por rol (desarrollo local)
│   └── seed_datos_prueba.sql    # Catálogo, clientes y pedidos de ejemplo (desarrollo local)
├── vendor/                 # Dependencias de Composer (PHPMailer). No se versiona.
├── composer.json
├── .env.example             # Plantilla de variables de entorno (SMTP)
└── .env                     # Credenciales locales reales. No se versiona.
```

## Cómo se agregan rutas

`public/index.php` resuelve la ruta con `current_route()` y la despacha en un `switch`.
Para una pantalla nueva se agrega un `case`, que llama al controlador correspondiente
de `src/controllers` y este renderiza con `view('nombre', $datos)`.

## Cómo se consulta la base

```php
$pdo  = Database::getConnection();
$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
$stmt->execute([$email]);
$usuario = $stmt->fetch();
```

PDO está configurado con excepciones activadas, `FETCH_ASSOC` por defecto y prepared
statements reales (sin emulación).

## Nota sobre `assets/` y virtual hosts

La carpeta `assets/` está fuera de `public/`, así que según dónde apunte el document
root Apache puede no alcanzarla:

- Document root en `www` (`http://localhost/BakerSoft/public/`): Apache sirve los
  archivos directamente, porque existen en disco.
- Document root en `public/` (virtual host tipo `bakersoft.test`, que es lo que arma
  Laragon automáticamente) o servidor embebido de PHP: la carpeta queda fuera del
  directorio publicado, y las peticiones a `/assets/...` las resuelve `public/index.php`
  con la función `servir_asset()`.

En producción conviene servir los estáticos desde el servidor web y no desde PHP:
un `Alias /assets` en el vhost, o mover `assets/` dentro de `public/` y ajustar el
helper `asset()`.
