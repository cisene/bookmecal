<?php
// config.php settings
define('DB_DRIVER', 'mysql'); // 'mysql' or 'sqlite'

define('MYSQL_HOST', '127.0.0.1');
define('MYSQL_DB',   'calendar_db');
define('MYSQL_USER', 'db_user');
define('MYSQL_PASS', 'db_password');

define('SQLITE_PATH', __DIR__ . '/database/calendar.sqlite');

function getDbConnection(): PDO {
    $driver = strtolower(DB_DRIVER);

    try {
        if ($driver === 'mysql') {
            $dsn = sprintf("mysql:host=%s;dbname=%s;charset=utf8mb4", MYSQL_HOST, MYSQL_DB);
            $pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } elseif ($driver === 'sqlite') {
            $pdo = new PDO("sqlite:" . SQLITE_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // Enable WAL mode for better concurrency performance in SQLite
            $pdo->exec("PRAGMA journal_mode = WAL;");
        } else {
            throw new Exception("Unsupported DB_DRIVER: " . DB_DRIVER);
        }

        return $pdo;
    } catch (PDOException $e) {
        die("Database connection error: " . $e->getMessage());
    }
}