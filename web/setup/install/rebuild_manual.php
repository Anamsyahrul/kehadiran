<?php
require __DIR__ . '/../../bootstrap.php';

try {
    $pdo = pdo();

    // Drop existing tables
    $dropTables = [
        'remember_tokens',
        'audit_logs',
        'notifications',
        'kehadiran',
        'users',
        'devices',
        'system_settings',
        'holiday_calendar',
        'kehadiran_rules',
        'login_attempts',
    ];

    foreach ($dropTables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    echo "Dropped existing tables." . PHP_EOL;

    // Create tables one by one
    $tables = [
        'users' => "CREATE TABLE users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            username VARCHAR(100) UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','teacher','parent','student') NOT NULL DEFAULT 'student',
            kelas VARCHAR(100) NULL,
            uid_hex VARCHAR(32) UNIQUE,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_login TIMESTAMP NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'devices' => "CREATE TABLE devices (
            id VARCHAR(64) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            device_secret VARCHAR(128) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'system_settings' => "CREATE TABLE system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            key_name VARCHAR(100) NOT NULL UNIQUE,
            value_text TEXT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'holiday_calendar' => "CREATE TABLE holiday_calendar (
            id INT AUTO_INCREMENT PRIMARY KEY,
            holiday_date DATE NOT NULL,
            title VARCHAR(150) NOT NULL,
            UNIQUE KEY uniq_holiday (holiday_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'kehadiran_rules' => "CREATE TABLE kehadiran_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rule_key VARCHAR(100) NOT NULL UNIQUE,
            rule_value VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'kehadiran' => "CREATE TABLE kehadiran (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            device_id VARCHAR(64) NULL,
            ts DATETIME NOT NULL,
            uid_hex VARCHAR(32) NOT NULL,
            raw_json JSON NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'notifications' => "CREATE TABLE notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('info','success','warning','error') NOT NULL DEFAULT 'info',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'audit_logs' => "CREATE TABLE audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            action VARCHAR(100) NOT NULL,
            details JSON NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'remember_tokens' => "CREATE TABLE remember_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            selector VARCHAR(24) NOT NULL UNIQUE,
            validator_hash VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'login_attempts' => "CREATE TABLE login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NULL,
            ip_address VARCHAR(45) NULL,
            attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($tables as $name => $sql) {
        $pdo->exec($sql);
        echo "Created table: $name" . PHP_EOL;
    }

    // Create indexes
    $indexes = [
        "CREATE INDEX idx_kehadiran_ts ON kehadiran (ts)",
        "CREATE INDEX idx_kehadiran_uid ON kehadiran (uid_hex)",
        "CREATE INDEX idx_kehadiran_device ON kehadiran (device_id)",
        "CREATE INDEX idx_users_username ON users (username)",
    ];

    foreach ($indexes as $sql) {
        $pdo->exec($sql);
    }
    echo "Created indexes." . PHP_EOL;

    // Insert essential data
    $settings = [
        ['sekolah_NAME', 'SMA Peradaban Bumiayu'],
        ['sekolah_START', '07:15'],
        ['sekolah_END', '15:00'],
        ['LATE_THRESHOLD', '15'],
        ['REQUIRE_CHECKOUT', '0'],
        ['ALLOW_WEEKEND_HOLIDAY_SCAN', '0'],
        ['ADMIN_USER', 'admin'],
        ['ADMIN_PASS', 'admin'],
    ];

    $stmt = $pdo->prepare('INSERT IGNORE INTO system_settings (key_name, value_text) VALUES(?, ?)');
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
    echo "Inserted settings." . PHP_EOL;

    // Insert admin user
    $stmt = $pdo->prepare('INSERT IGNORE INTO users (name, username, password, role, kelas, is_active) VALUES(?,?,?,?,?,?)');
    $stmt->execute(['Administrator', 'admin', password_hash('admin', PASSWORD_BCRYPT), 'admin', null, 1]);
    echo "Inserted admin user." . PHP_EOL;

    echo "OK: Database migrated successfully!" . PHP_EOL;
    echo "Login at: http://localhost/kehadiran/web/public/login.php (Administrator/admin)" . PHP_EOL;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
