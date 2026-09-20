<?php

declare(strict_types=1);

/**
 * BakerSoft - Configuración y conexión a MySQL (PDO).
 *
 * ---------------------------------------------------------------------------
 * CREDENCIALES: ajustá estos valores según tu entorno local.
 * Laragon y XAMPP por defecto usan el usuario "root" sin contraseña.
 * ---------------------------------------------------------------------------
 */
const DB_HOST    = '127.0.0.1';
const DB_PORT    = '3306';
const DB_NAME    = 'bakersoft_db';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

/**
 * Punto único de acceso a la base de datos.
 *
 * Uso:
 *     $pdo = Database::getConnection();
 *     $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
 *
 * La conexión se crea una sola vez por request y se reutiliza.
 */
final class Database
{
    private static ?PDO $connection = null;

    /**
     * Devuelve la conexión PDO activa (la crea si todavía no existe).
     *
     * @throws PDOException si no se puede conectar.
     */
    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        self::$connection = new PDO($dsn, DB_USER, DB_PASS, [
            // Los errores se lanzan como excepciones en lugar de fallar en silencio.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // fetch() devuelve arrays asociativos por defecto.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Prepared statements reales del lado del servidor.
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$connection;
    }

    /**
     * Cierra la conexión actual (útil en scripts de larga duración o tests).
     */
    public static function close(): void
    {
        self::$connection = null;
    }
}
