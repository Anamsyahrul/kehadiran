<?php
require __DIR__ . '/../../bootstrap.php';

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
];

try {
    $pdo = pdo();

    // Drop tables
    foreach ($dropTables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    echo "Dropped existing tables." . PHP_EOL;

    // Create tables in correct order
    $sqlFiles = [
        __DIR__ . '/../../sql/schema.sql',
        __DIR__ . '/../../sql/auth_tables.sql',
        __DIR__ . '/../../sql/essential_tables.sql',
        __DIR__ . '/../../sql/perf_indexes.sql',
    ];

    foreach ($sqlFiles as $file) {
        if (!file_exists($file)) {
            continue;
        }
        $sql = file_get_contents($file);
        $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
        foreach ($statements as $stmt) {
            if ($stmt === '' || strpos($stmt, '--') === 0) {
                continue;
            }
            $pdo->exec($stmt);
        }
        echo 'Executed: ' . basename($file) . PHP_EOL;
    }

    echo "OK: Database migrated successfully." . PHP_EOL;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
