<?php
// install.php - Run once to set up the database tables

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function runInstaller(): void {
    echo "<h2>Calendar Booking Service - Database Installer</h2>";

    try {
        $driver = strtolower(DB_DRIVER);
        $pdo = getDbConnection();
        echo "<p style='color: green;'>✓ Successfully connected using driver: <strong>" . strtoupper($driver) . "</strong></p>";

        // Execute Driver-Specific Schema
        if ($driver === 'mysql') {
            setupMysqlTables($pdo);
        } elseif ($driver === 'sqlite') {
            setupSqliteTables($pdo);
        } else {
            throw new Exception("Unsupported driver: {$driver}");
        }

        // Seed Default Configurations
        seedDefaultData($pdo);

        echo "<h3 style='color: green;'>Installation Complete!</h3>";
        echo "<p>Please delete or restrict access to <code>install.php</code> before going to production.</p>";

    } catch (Exception $e) {
        echo "<h3 style='color: red;'>Installation Failed</h3>";
        echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

function setupMysqlTables(PDO $pdo): void {
    echo "<p>Creating MySQL tables...</p>";

    $queries = [
        "CREATE TABLE IF NOT EXISTS settings (
            key_name VARCHAR(50) PRIMARY KEY,
            value_text TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS slot_matrix (
            day_of_week INT PRIMARY KEY,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            is_active TINYINT DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS holiday_matrix (
            id INT AUTO_INCREMENT PRIMARY KEY,
            holiday_date DATE NOT NULL,
            description VARCHAR(255)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS booking_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_name VARCHAR(100) NOT NULL,
            client_email VARCHAR(100) NOT NULL,
            start_datetime DATETIME NOT NULL,
            end_datetime DATETIME NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            gcal_event_id VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];

    foreach ($queries as $sql) {
        $pdo->exec($sql);
    }
    echo "<p>✓ MySQL tables initialized successfully.</p>";
}

function setupSqliteTables(PDO $pdo): void {
    echo "<p>Creating SQLite tables...</p>";

    $queries = [
        "CREATE TABLE IF NOT EXISTS settings (
            key_name TEXT PRIMARY KEY,
            value_text TEXT
        );",

        "CREATE TABLE IF NOT EXISTS slot_matrix (
            day_of_week INTEGER PRIMARY KEY,
            start_time TEXT NOT NULL,
            end_time TEXT NOT NULL,
            is_active INTEGER DEFAULT 1
        );",

        "CREATE TABLE IF NOT EXISTS holiday_matrix (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            holiday_date TEXT NOT NULL,
            description TEXT
        );",

        "CREATE TABLE IF NOT EXISTS booking_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            client_name TEXT NOT NULL,
            client_email TEXT NOT NULL,
            start_datetime TEXT NOT NULL,
            end_datetime TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            gcal_event_id TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );"
    ];

    foreach ($queries as $sql) {
        $pdo->exec($sql);
    }
    echo "<p>✓ SQLite tables initialized successfully.</p>";
}

function seedDefaultData(PDO $pdo): void {
    echo "<p>Seeding initial default settings and weekly operating schedule...</p>";

    // 1. Seed Global Settings (e.g. 60-minute default slot duration)
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO settings (key_name, value_text) VALUES (?, ?)");
    // Fallback syntax for MySQL portability
    if (DB_DRIVER === 'mysql') {
        $stmt = $pdo->prepare("INSERT INTO settings (key_name, value_text) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_text=VALUES(value_text)");
    }
    $stmt->execute(['slot_duration_minutes', '60']);

    // 2. Seed Default Slot Matrix (Mon-Fri open 09:00 to 17:00, Sat-Sun closed)
    for ($day = 0; $day <= 6; $day++) {
        $isActive = ($day >= 1 && $day <= 5) ? 1 : 0; // 1 = Mon, 5 = Fri

        if (DB_DRIVER === 'mysql') {
            $stmt = $pdo->prepare("
                INSERT INTO slot_matrix (day_of_week, start_time, end_time, is_active) 
                VALUES (?, '09:00:00', '17:00:00', ?)
                ON DUPLICATE KEY UPDATE is_active=VALUES(is_active)
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT OR IGNORE INTO slot_matrix (day_of_week, start_time, end_time, is_active) 
                VALUES (?, '09:00:00', '17:00:00', ?)
            ");
        }

        $stmt->execute([$day, $isActive]);
    }

    echo "<p>✓ Default slot matrix (Mon–Fri, 09:00–17:00) populated.</p>";
}

// Execute the installer
runInstaller();