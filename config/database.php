<?php
declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        Config::load();
        $connection = Config::get('DB_CONNECTION', 'mysql');

        try {
            if ($connection === 'sqlite') {
                $dbPath = Config::get('DB_DATABASE', dirname(__DIR__) . '/storage/database.sqlite');
                $pdo = new PDO("sqlite:" . $dbPath);
            } else {
                $host = Config::get('DB_HOST', '127.0.0.1');
                $port = Config::get('DB_PORT', '3306');
                $dbname = Config::get('DB_NAME', 'sensordb');
                $user = Config::get('DB_USER', 'root');
                $pass = Config::get('DB_PASS', '');
                $charset = Config::get('DB_CHARSET', 'utf8mb4');

                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => true,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci"
                ]);
            }

            self::$instance = $pdo;
            return self::$instance;
        } catch (PDOException $e) {
            error_log("Database connection failure: " . $e->getMessage());
            throw new RuntimeException("Database connection could not be established.");
        }
    }
}
