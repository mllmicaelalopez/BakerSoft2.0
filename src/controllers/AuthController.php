<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * BakerSoft - Autenticación (HU-02: login).
 *
 * El bloqueo por intentos fallidos (HU-04) todavía no está: los campos
 * intentos_fallidos y bloqueado_hasta de la tabla usuarios no se tocan acá.
 */
final class AuthController
{
    /**
     * Hash señuelo para verificar contra él cuando el email no existe.
     *
     * password_verify() tarda unos milisegundos; si solo se ejecutara cuando
     * el usuario existe, se podría deducir qué emails están registrados
     * midiendo el tiempo de respuesta. Verificar siempre iguala los tiempos.
     */
    private const HASH_SENUELO = '$2y$10$wYXjkIuV066NKAP3EtMh1u.zfnmMEm1TVjrfEEklrudHRrARAlfTy';

    private const ERROR_GENERICO = 'Credenciales inválidas';

    /* HU-04: límites y bloqueo. */

    /** Intentos por IP permitidos dentro de la ventana. */
    private const MAX_INTENTOS_IP = 10;

    /** Duración de la ventana del límite por IP, en minutos. */
    private const VENTANA_RATE_LIMIT_MIN = 1;

    /** Demora artificial al superar el límite por IP (microsegundos). */
    private const DEMORA_RATE_LIMIT_US = 750000;

    /** Fallos de contraseña seguidos antes de bloquear la cuenta. */
    private const MAX_INTENTOS_CUENTA = 5;

    /** Duración del bloqueo de cuenta, en minutos. */
    private const MINUTOS_BLOQUEO = 15;

    /** Bloqueos en 24 h que disparan el aviso a administración. */
    private const BLOQUEOS_PARA_ALERTA = 3;

    private const ERROR_BLOQUEADA = 'Cuenta bloqueada temporalmente. Intente en 15 minutos.';

    private const ERROR_RATE_LIMIT = 'Demasiados intentos. Intente nuevamente en unos minutos.';

    /**
     * GET /login
     *
     * @param array<string, mixed> $datos Datos extra para repoblar el formulario.
     */
    public function showLoginForm(array $datos = []): void
    {
        // Si ya hay sesión, no tiene sentido volver a mostrar el login.
        if (usuario_autenticado()) {
            redirect('dashboard');
        }

        view('layouts/auth-layout', array_merge([
            'slot'    => 'auth/login',
            'titulo'  => 'BakerSoft - Iniciar sesión',
            'error'   => '',      // mensaje genérico arriba del formulario
            'errores' => [],      // errores por campo: ['email' => '...']
            'email'   => '',      // se repuebla; la contraseña nunca
        ], $datos));
    }

    /**
     * POST /login
     */
    public function login(): void
    {
        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $ip       = ip_cliente();

        // 1. Límite por IP, antes que cualquier otra cosa: es la defensa
        //    contra fuerza bruta repartida entre muchas cuentas, así que no
        //    debe depender de que el email exista ni de que el form sea válido.
        $permitido = $this->ipDentroDelLimite($ip);
        $this->registrarIntentoIp($ip);

        if (!$permitido) {
            // Demora artificial: le baja el ritmo a un script automatizado.
            usleep(self::DEMORA_RATE_LIMIT_US);

            app_log(sprintf('LOGIN FALLIDO email=%s ip=%s motivo=rate limit ip', $email, $ip));

            $this->showLoginForm(['error' => self::ERROR_RATE_LIMIT, 'email' => $email]);
            return;
        }

        // 2. Campos obligatorios.
        $errores = [];

        if ($email === '') {
            $errores['email'] = 'Este campo es obligatorio';
        }

        if ($password === '') {
            $errores['password'] = 'Este campo es obligatorio';
        }

        if ($errores !== []) {
            $this->showLoginForm(['errores' => $errores, 'email' => $email]);
            return;
        }

        // 3. Buscar el usuario.
        $usuario = $this->buscarPorEmail($email);

        // 4. Cuenta bloqueada: se corta acá, sin siquiera mirar la contraseña.
        if ($usuario !== null && $this->estaBloqueada($usuario)) {
            app_log(sprintf('LOGIN FALLIDO email=%s ip=%s motivo=cuenta bloqueada', $email, $ip));

            $this->showLoginForm(['error' => self::ERROR_BLOQUEADA, 'email' => $email]);
            return;
        }

        // 5. Verificar la contraseña. Si el email no existe igual se verifica
        //    contra el hash señuelo, para que los tiempos no delaten nada.
        $passwordOk = $usuario !== null
            ? password_verify($password, (string) $usuario['password_hash'])
            : password_verify($password, self::HASH_SENUELO);

        $activo = $usuario !== null && (bool) $usuario['activo'];

        // Email inexistente, contraseña incorrecta y cuenta inactiva se
        // responden todos igual, para no revelar cuál de los tres fue.
        if ($usuario === null || !$passwordOk || !$activo) {
            $motivo = match (true) {
                $usuario === null => 'email inexistente',
                !$passwordOk      => 'password incorrecta',
                default           => 'cuenta inactiva',
            };

            // Solo suma al contador de la cuenta si la contraseña estuvo mal:
            // una cuenta inactiva con la contraseña correcta no es un ataque.
            if ($usuario !== null && !$passwordOk) {
                $this->registrarFalloDeCuenta($usuario, $ip);
            }

            app_log(sprintf('LOGIN FALLIDO email=%s ip=%s motivo=%s', $email, $ip, $motivo));

            $this->showLoginForm([
                'error' => self::ERROR_GENERICO,
                'email' => $email,   // el campo de contraseña queda vacío
            ]);
            return;
        }

        // 6. Login correcto: se limpia el estado de bloqueo de la cuenta.
        $stmt = Database::getConnection()->prepare(
            'UPDATE usuarios
             SET intentos_fallidos = 0, bloqueado_hasta = NULL
             WHERE id_usuario = :id'
        );
        $stmt->execute(['id' => $usuario['id_usuario']]);

        // 7. Sesión.
        // Se regenera el ID antes de guardar los datos para que una sesión
        // preexistente (session fixation) no quede asociada al usuario.
        session_regenerate_id(true);

        $_SESSION['id_usuario']     = (int) $usuario['id_usuario'];
        $_SESSION['nombre_usuario'] = (string) $usuario['nombre_usuario'];
        $_SESSION['email']          = (string) $usuario['email'];
        $_SESSION['rol']            = (string) $usuario['rol_codigo'];

        app_log(sprintf(
            'LOGIN OK email=%s id_usuario=%d ip=%s',
            $usuario['email'],
            $usuario['id_usuario'],
            $ip
        ));

        redirect('dashboard');
    }

    /* -----------------------------------------------------------------------
       HU-01: registro de cuenta
       ----------------------------------------------------------------------- */

    /**
     * GET /register
     *
     * @param array<string, mixed> $datos
     */
    public function showRegisterForm(array $datos = []): void
    {
        if (usuario_autenticado()) {
            redirect('dashboard');
        }

        view('layouts/auth-layout', array_merge([
            'slot'          => 'auth/register',
            'titulo'        => 'BakerSoft - Crear cuenta',
            'error'         => '',
            'errores'       => [],
            'nombreUsuario' => '',
            'email'         => '',
        ], $datos));
    }

    /**
     * POST /register
     */
    public function register(): void
    {
        $nombreUsuario = trim((string) ($_POST['nombre_usuario'] ?? ''));
        $email         = trim((string) ($_POST['email'] ?? ''));
        $password      = (string) ($_POST['password'] ?? '');
        $confirma      = (string) ($_POST['password_confirm'] ?? '');

        $errores = [];

        if ($nombreUsuario === '') {
            $errores['nombre_usuario'] = 'Este campo es obligatorio';
        } elseif (mb_strlen($nombreUsuario) > 60) {
            $errores['nombre_usuario'] = 'No puede superar los 60 caracteres';
        }

        if ($email === '') {
            $errores['email'] = 'Este campo es obligatorio';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores['email'] = 'Ingresá un correo electrónico válido';
        } elseif (mb_strlen($email) > 150) {
            $errores['email'] = 'No puede superar los 150 caracteres';
        } elseif (Usuario::emailRegistrado($email)) {
            $errores['email'] = 'Ya existe una cuenta con ese correo';
        }

        if ($password === '') {
            $errores['password'] = 'Este campo es obligatorio';
        } else {
            $faltan = requisitos_password_faltantes($password);

            if ($faltan !== []) {
                $errores['password'] = 'La contraseña necesita ' . $this->enumerar($faltan) . '.';
            }
        }

        if ($confirma === '') {
            $errores['password_confirm'] = 'Este campo es obligatorio';
        } elseif ($password !== $confirma) {
            $errores['password_confirm'] = 'Las contraseñas no coinciden';
        }

        if ($errores !== []) {
            $this->showRegisterForm([
                'errores'       => $errores,
                'nombreUsuario' => $nombreUsuario,
                'email'         => $email,   // las contraseñas nunca se repueblan
            ]);
            return;
        }

        try {
            $idUsuario = Usuario::crear($nombreUsuario, $email, $password);
        } catch (PDOException $e) {
            // 23000 = violación de la clave única del email. Puede pasar si dos
            // registros con el mismo email entran a la vez, entre el chequeo de
            // más arriba y el INSERT.
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            $this->showRegisterForm([
                'errores'       => ['email' => 'Ya existe una cuenta con ese correo'],
                'nombreUsuario' => $nombreUsuario,
                'email'         => $email,
            ]);
            return;
        }

        app_log(sprintf(
            'REGISTRO OK email=%s id_usuario=%d ip=%s',
            $email,
            $idUsuario,
            ip_cliente()
        ));

        flash_set('Tu cuenta se creó. Ya podés iniciar sesión.', 'ok');
        redirect('login');
    }

    /* -----------------------------------------------------------------------
       HU-04: límite por IP y bloqueo por cuenta
       ----------------------------------------------------------------------- */

    /**
     * ¿La IP todavía tiene cupo en su ventana de un minuto?
     */
    private function ipDentroDelLimite(string $ip): bool
    {
        // La ventana va como literal porque MySQL no acepta un parámetro
        // dentro de INTERVAL; el valor sale de una constante, no del request.
        $stmt = Database::getConnection()->prepare(sprintf(
            'SELECT intentos
             FROM rate_limit_ip
             WHERE ip = :ip AND ventana_inicio > NOW() - INTERVAL %d MINUTE',
            self::VENTANA_RATE_LIMIT_MIN
        ));
        $stmt->execute(['ip' => $ip]);

        $fila = $stmt->fetch();

        // Sin fila, o con la ventana ya vencida, arranca de cero.
        return $fila === false || (int) $fila['intentos'] < self::MAX_INTENTOS_IP;
    }

    /**
     * Suma un intento a la IP. Si la ventana venció, la reinicia en 1.
     */
    private function registrarIntentoIp(string $ip): void
    {
        $sql = sprintf(
            'INSERT INTO rate_limit_ip (ip, intentos, ventana_inicio)
             VALUES (:ip, 1, NOW())
             ON DUPLICATE KEY UPDATE
                 intentos = IF(ventana_inicio < NOW() - INTERVAL %1$d MINUTE, 1, intentos + 1),
                 ventana_inicio = IF(ventana_inicio < NOW() - INTERVAL %1$d MINUTE, NOW(), ventana_inicio)',
            self::VENTANA_RATE_LIMIT_MIN
        );

        // El orden de las asignaciones importa: intentos se calcula con el
        // ventana_inicio viejo, y recién después se pisa ventana_inicio.
        Database::getConnection()->prepare($sql)->execute(['ip' => $ip]);
    }

    /**
     * @param array<string, mixed> $usuario
     */
    private function estaBloqueada(array $usuario): bool
    {
        // La comparación con NOW() ya la hizo MySQL en buscarPorEmail().
        return (bool) ($usuario['esta_bloqueada'] ?? false);
    }

    /**
     * Suma un fallo a la cuenta y, si toca, la bloquea.
     *
     * @param array<string, mixed> $usuario
     */
    private function registrarFalloDeCuenta(array $usuario, string $ip): void
    {
        $pdo = Database::getConnection();
        $id  = (int) $usuario['id_usuario'];

        $intentos = (int) $usuario['intentos_fallidos'] + 1;

        if ($intentos < self::MAX_INTENTOS_CUENTA) {
            $stmt = $pdo->prepare(
                'UPDATE usuarios SET intentos_fallidos = :intentos WHERE id_usuario = :id'
            );
            $stmt->execute(['intentos' => $intentos, 'id' => $id]);
            return;
        }

        // Se llegó al tope: bloquear y volver el contador a cero.
        // bloqueos_24h y primer_bloqueo_en se resuelven en el mismo UPDATE:
        // si la ventana venció (o es el primer bloqueo), arranca de nuevo en 1.
        $sql = sprintf(
            'UPDATE usuarios
             SET bloqueado_hasta   = NOW() + INTERVAL %1$d MINUTE,
                 intentos_fallidos = 0,
                 bloqueos_24h      = IF(primer_bloqueo_en IS NULL
                                        OR primer_bloqueo_en < NOW() - INTERVAL 24 HOUR,
                                        1, bloqueos_24h + 1),
                 primer_bloqueo_en = IF(primer_bloqueo_en IS NULL
                                        OR primer_bloqueo_en < NOW() - INTERVAL 24 HOUR,
                                        NOW(), primer_bloqueo_en)
             WHERE id_usuario = :id',
            self::MINUTOS_BLOQUEO
        );

        $pdo->prepare($sql)->execute(['id' => $id]);

        app_log(sprintf(
            'CUENTA BLOQUEADA email=%s ip=%s minutos=%d',
            $usuario['email'],
            $ip,
            self::MINUTOS_BLOQUEO
        ));

        // ¿Cuántos bloqueos lleva en la ventana de 24 h?
        $stmt = $pdo->prepare('SELECT bloqueos_24h FROM usuarios WHERE id_usuario = :id');
        $stmt->execute(['id' => $id]);
        $bloqueos = (int) $stmt->fetchColumn();

        if ($bloqueos >= self::BLOQUEOS_PARA_ALERTA) {
            $mensaje = sprintf(
                'ALERTA ADMIN: cuenta %s bloqueada %d veces en 24hs',
                $usuario['email'],
                $bloqueos
            );

            $stmt = $pdo->prepare(
                'INSERT INTO alertas_admin (email, ip, mensaje)
                 VALUES (:email, :ip, :mensaje)'
            );
            $stmt->execute([
                'email'   => $usuario['email'],
                'ip'      => $ip,
                'mensaje' => $mensaje,
            ]);

            app_log($mensaje);
        }
    }

    /**
     * GET /logout
     */
    public function logout(): void
    {
        $email = (string) ($_SESSION['email'] ?? '');

        // Se leen antes de destruir la sesión, para poder vencer la cookie.
        $nombreCookie = session_name();
        $params       = session_get_cookie_params();

        session_unset();
        session_destroy();

        // session_destroy() borra los datos del servidor, pero el navegador
        // sigue mandando el ID viejo hasta que se vence la cookie.
        setcookie($nombreCookie, '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);

        app_log(sprintf(
            'LOGOUT email=%s ip=%s',
            $email !== '' ? $email : '(sin sesión)',
            ip_cliente()
        ));

        redirect('login');
    }

    /* -----------------------------------------------------------------------
       HU-03: recuperación de contraseña

       El email se manda de verdad con PHPMailer (HT-02), usando la config
       SMTP de .env (ver .env.example). Si PHPMailer no está instalado
       (falta "composer install"), si .env no está configurado, o si el envío
       falla por cualquier otro motivo, se cae al respaldo de desarrollo: el
       link se muestra en pantalla y se escribe en storage/logs/app.log, para
       no bloquear el flujo mientras el email no esté configurado.
       ----------------------------------------------------------------------- */

    /** Minutos que vive un token de recuperación. */
    private const MINUTOS_VIGENCIA_TOKEN = 15;

    private const MENSAJE_ENVIO = 'Si existe una cuenta, enviamos un enlace';

    /**
     * GET /forgot-password — modal sobre el login.
     *
     * @param array<string, mixed> $datos
     */
    public function showForgotPasswordForm(array $datos = []): void
    {
        view('layouts/auth-layout', array_merge([
            'slot'         => 'auth/login',   // el login queda de fondo
            'modal'        => 'auth/forgot-password',
            'titulo'       => 'BakerSoft - Recuperar contraseña',
            'enviado'      => false,
            'emailReset'   => '',
            'devLink'      => '',
            'erroresReset' => [],
        ], $datos));
    }

    /**
     * POST /forgot-password
     */
    public function forgotPassword(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($email === '') {
            $this->showForgotPasswordForm([
                'erroresReset' => ['email' => 'Este campo es obligatorio'],
            ]);
            return;
        }

        $usuario = $this->buscarPorEmail($email);
        $devLink = '';

        // El mensaje que ve el usuario es siempre el mismo; lo único que
        // cambia es que atrás se genere (o no) un token.
        if ($usuario !== null) {
            $token = bin2hex(random_bytes(32));

            // El vencimiento lo calcula MySQL: buscarTokenVigente() compara
            // contra NOW(), así que las dos puntas tienen que usar el mismo
            // reloj (php.ini acá está en UTC y MySQL en la hora del sistema).
            $sql = sprintf(
                'INSERT INTO password_resets (email, token, expira_en)
                 VALUES (:email, :token, NOW() + INTERVAL %d MINUTE)',
                self::MINUTOS_VIGENCIA_TOKEN
            );

            $stmt = Database::getConnection()->prepare($sql);
            $stmt->execute([
                'email' => $usuario['email'],
                'token' => $token,
            ]);

            $link    = url_absoluta('reset-password') . '?token=' . $token;
            $enviado = $this->enviarEmailReset($usuario['email'], $link);

            if ($enviado) {
                app_log(sprintf(
                    'RESET SOLICITADO email=%s ip=%s email_enviado=si',
                    $usuario['email'],
                    ip_cliente()
                ));
            } else {
                // Respaldo de desarrollo: no se pudo mandar el email (PHPMailer
                // no instalado, .env sin configurar, credenciales inválidas,
                // etc.). Se muestra el link en pantalla para no bloquear el flujo.
                $devLink = $link;

                app_log(sprintf(
                    'RESET SOLICITADO email=%s ip=%s email_enviado=no link=%s',
                    $usuario['email'],
                    ip_cliente(),
                    $link
                ));
            }
        } else {
            app_log(sprintf(
                'RESET SOLICITADO email=%s ip=%s motivo=email inexistente',
                $email,
                ip_cliente()
            ));
        }

        $this->showForgotPasswordForm([
            'enviado'    => true,
            'emailReset' => $email,
            'devLink'    => $devLink,
        ]);
    }

    /**
     * Manda el email de recuperación con PHPMailer.
     *
     * Devuelve false (nunca lanza) ante cualquier problema: PHPMailer no
     * instalado, .env sin configurar o error de SMTP. forgotPassword() usa
     * ese false para caer al respaldo de desarrollo.
     */
    private function enviarEmailReset(string $email, string $link): bool
    {
        if (!class_exists(PHPMailer::class)) {
            return false;
        }

        $host = env('MAIL_HOST', '');

        if ($host === '' || env('MAIL_USERNAME', '') === '') {
            // .env no configurado todavía: ni se intenta conectar.
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->Port       = (int) env('MAIL_PORT', '587');
            $mail->SMTPAuth   = true;
            $mail->Username   = env('MAIL_USERNAME', '');
            $mail->Password   = env('MAIL_PASSWORD', '');
            $mail->SMTPSecure = env('MAIL_ENCRYPTION', 'tls');
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(env('MAIL_FROM_ADDRESS', $mail->Username), env('MAIL_FROM_NAME', 'BakerSoft'));
            $mail->addAddress($email);

            $mail->Subject = 'BakerSoft - Restablecer contraseña';
            $mail->Body    = "Recibimos un pedido para restablecer tu contraseña.\n\n"
                . "Si fuiste vos, entrá a este enlace (vence en " . self::MINUTOS_VIGENCIA_TOKEN . " minutos):\n"
                . $link . "\n\n"
                . "Si no fuiste vos, podés ignorar este mensaje.";

            $mail->send();

            return true;
        } catch (PHPMailerException $e) {
            app_log(sprintf('RESET EMAIL ERROR email=%s error=%s', $email, $mail->ErrorInfo));

            return false;
        }
    }

    /**
     * GET /reset-password?token=...
     *
     * @param array<string, mixed> $datos
     */
    public function showResetPasswordForm(array $datos = []): void
    {
        $token   = (string) ($_GET['token'] ?? $datos['token'] ?? '');
        $registro = $this->buscarTokenVigente($token);

        view('layouts/auth-layout', array_merge([
            'slot'        => 'auth/reset-password',
            'titulo'      => 'BakerSoft - Restablecer contraseña',
            'tokenValido' => $registro !== null,
            'token'       => $token,
            'email'       => $registro['email'] ?? '',
            'error'       => '',
            'errores'     => [],
        ], $datos));
    }

    /**
     * POST /reset-password
     */
    public function resetPassword(): void
    {
        $token    = (string) ($_POST['token'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirma = (string) ($_POST['password_confirm'] ?? '');

        // 1. El token se revalida en cada POST: pudo vencerse o usarse
        //    entre que se cargó el formulario y se envió.
        $registro = $this->buscarTokenVigente($token);

        if ($registro === null) {
            $this->showResetPasswordForm(['token' => $token, 'tokenValido' => false]);
            return;
        }

        // 2. Política de contraseña y confirmación.
        $errores = [];

        if ($password === '') {
            $errores['password'] = 'Este campo es obligatorio';
        } else {
            $faltan = requisitos_password_faltantes($password);

            if ($faltan !== []) {
                $errores['password'] = 'La contraseña necesita ' . $this->enumerar($faltan) . '.';
            }
        }

        if ($confirma === '') {
            $errores['password_confirm'] = 'Este campo es obligatorio';
        } elseif ($password !== $confirma) {
            $errores['password_confirm'] = 'Las contraseñas no coinciden';
        }

        if ($errores !== []) {
            $this->showResetPasswordForm([
                'token'       => $token,
                'tokenValido' => true,
                'email'       => $registro['email'],
                'errores'     => $errores,
            ]);
            return;
        }

        // 3. Guardar. Las dos escrituras van juntas: si falla una, no queda
        //    ni la contraseña cambiada con el token vivo ni al revés.
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = :hash WHERE email = :email');
            $stmt->execute([
                'hash'  => password_hash($password, PASSWORD_DEFAULT),
                'email' => $registro['email'],
            ]);

            // Se marcan como usados TODOS los tokens vivos de esa cuenta, no
            // solo este: si quedara otro pendiente, serviría para volver a
            // cambiar la contraseña después del reset.
            $stmt = $pdo->prepare(
                'UPDATE password_resets SET usado = 1 WHERE email = :email AND usado = 0'
            );
            $stmt->execute(['email' => $registro['email']]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        app_log(sprintf(
            'RESET PASSWORD OK email=%s ip=%s tipo=reset_password',
            $registro['email'],
            ip_cliente()
        ));

        flash_set('Tu contraseña se actualizó. Ya podés iniciar sesión.', 'ok');
        redirect('login');
    }

    /**
     * "a", "a y b", "a, b y c" — para armar el mensaje de la política.
     *
     * @param string[] $items
     */
    private function enumerar(array $items): string
    {
        if (count($items) < 2) {
            return implode('', $items);
        }

        $ultimo = array_pop($items);

        return implode(', ', $items) . ' y ' . $ultimo;
    }

    /**
     * Busca un token que exista, no esté usado y no haya expirado.
     *
     * @return array<string, mixed>|null
     */
    private function buscarTokenVigente(string $token): ?array
    {
        // 64 hex = bin2hex(random_bytes(32)). Descartar cualquier otra cosa
        // evita ir a la base con basura.
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $sql = 'SELECT id, email, expira_en, usado
                FROM password_resets
                WHERE token = :token AND usado = 0 AND expira_en > NOW()
                LIMIT 1';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute(['token' => $token]);

        $registro = $stmt->fetch();

        return $registro === false ? null : $registro;
    }

    /**
     * Busca un usuario por email. La consulta vive en el modelo.
     *
     * @return array<string, mixed>|null
     */
    private function buscarPorEmail(string $email): ?array
    {
        return Usuario::buscarPorEmail($email);
    }
}
