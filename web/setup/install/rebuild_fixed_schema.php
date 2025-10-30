<?php
require __DIR__ . '/../../bootstrap.php';

try {
    $pdo = pdo();

    // Drop existing tables in reverse order
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

    foreach ($dropTables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    echo "Dropped existing tables." . PHP_EOL;

    // Execute schema_fixed.sql
    $sql = file_get_contents(__DIR__ . '/../../sql/schema_fixed.sql');
    $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
    foreach ($statements as $stmt) {
        if ($stmt === '' || strpos($stmt, '--') === 0) {
            continue;
        }
        $pdo->exec($stmt);
    }
    echo "Created tables." . PHP_EOL;

    // Execute essential_tables.sql
    $sql = file_get_contents(__DIR__ . '/../../sql/essential_tables.sql');
    $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
    foreach ($statements as $stmt) {
        if ($stmt === '' || strpos($stmt, '--') === 0) {
            continue;
        }
        $pdo->exec($stmt);
    }
    echo "Seeded essential data." . PHP_EOL;

    echo "OK: Database migrated successfully." . PHP_EOL;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
