<?php

class Database {
    private static ?PDO $connection = null;

    /**
     * Skapar eller återanvänder en eksisterande PDO-anslutning baserat på konfiguration.
     * Stöder MySQL, SQLite och PostgreSQL.
     */
    public static function getConnection(array $config = []): PDO {
        if (self::$connection !== null) {
            return self::$connection;
        }

        // Om ingen konfiguration skickas med, försök ladda från config.php om den finns
        if (empty($config)) {
            $configFile = __DIR__ . '/config.php';
            if (file_exists($configFile)) {
                $loadedConfig = require $configFile;
                if (is_array($loadedConfig) && isset($loadedConfig['db'])) {
                    $config = $loadedConfig['db'];
                } elseif (is_array($loadedConfig)) {
                    $config = $loadedConfig;
                }
            }
        }

        $driver = strtolower($config['driver'] ?? 'mysql');

        try {
            switch ($driver) {
                case 'sqlite':
                    $path = $config['path'] ?? $config['database'] ?? __DIR__ . '/database.sqlite';
                    $dsn = "sqlite:" . $path;
                    $pdo = new PDO($dsn, null, null, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                    // Aktivera främmande nycklar för SQLite
                    $pdo->exec('PRAGMA foreign_keys = ON;');
                    break;

                case 'pgsql':
                case 'postgres':
                case 'postgresql':
                    $host = $config['host'] ?? '127.0.0.1';
                    $port = $config['port'] ?? 5432;
                    $dbname = $config['database'] ?? $config['dbname'] ?? '';
                    $user = $config['user'] ?? $config['username'] ?? '';
                    $pass = $config['password'] ?? $config['pass'] ?? '';

                    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
                    $pdo = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                    break;

                case 'mysql':
                default:
                    $host = $config['host'] ?? '127.0.0.1';
                    $port = $config['port'] ?? 3306;
                    $dbname = $config['database'] ?? $config['dbname'] ?? '';
                    $charset = $config['charset'] ?? 'utf8mb4';
                    $user = $config['user'] ?? $config['username'] ?? '';
                    $pass = $config['password'] ?? $config['pass'] ?? '';

                    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
                    $pdo = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);
                    break;
            }

            self::$connection = $pdo;
            return self::$connection;

        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new RuntimeException("Kunde inte ansluta till databasen. Kontrollera konfigurationen.");
        }
    }

    /**
     * Tillåter att manuellt sätta PDO-instans (t.ex. vid enhetstester/mocking).
     */
    public static function setConnection(PDO $pdo): void {
        self::$connection = $pdo;
    }

    /**
     * Stänger anslutningen (användbart vid tester eller långkörande skript).
     */
    public static function disconnect(): void {
        self::$connection = null;
    }
}

// Bakåtkompatibel $pdo för skript som kräver db.php direkt
$pdo = Database::getConnection();
