<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';

/**
 * Database connection (PDO).
 *
 * Credentials come from environment variables (DB_HOST, DB_PORT, DB_NAME,
 * DB_USER, DB_PASSWORD), or from a git-ignored `.env` file in the project
 * root. They are never stored in the repository.
 *
 * Usage: $pdo = Database::connection();
 */
final class Database
{
    private static ?PDO $pdo = null;

    /**
     * Shared PDO connection. Throws a generic RuntimeException on failure;
     * technical detail goes to the server error log only.
     */
    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect(self::config());
        }

        return self::$pdo;
    }

    /**
     * Open a new connection from an explicit config array
     * (host, port, name, user, password).
     *
     * @param array{host:string,port:string,name:string,user:string,password:string} $config
     */
    public static function connect(array $config): PDO
    {
        // Without the driver PHP would fail with a cryptic "undefined constant"
        // error. Say what is actually wrong, in the server log only.
        if (!extension_loaded('pdo_mysql')) {
            error_log('Database: the PHP extension "pdo_mysql" is not enabled (check php.ini: extension=pdo_mysql).');
            throw new RuntimeException('Database connection failed.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['name']
        );

        try {
            return new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                // UTC timestamps and strict mode on every connection.
                PDO::MYSQL_ATTR_INIT_COMMAND =>
                    "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00', "
                    . "sql_mode = CONCAT_WS(',', @@sql_mode, 'STRICT_ALL_TABLES')",
            ]);
        } catch (PDOException $e) {
            // Log the driver message (it does not contain the password);
            // never show it to the user.
            error_log('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed.');
        }
    }

    /**
     * @return array{host:string,port:string,name:string,user:string,password:string}
     */
    public static function config(): array
    {
        return [
            'host'     => AppConfig::env('DB_HOST', '127.0.0.1'),
            'port'     => AppConfig::env('DB_PORT', '3306'),
            'name'     => AppConfig::env('DB_NAME'),
            'user'     => AppConfig::env('DB_USER'),
            'password' => AppConfig::env('DB_PASSWORD'),
        ];
    }
}
