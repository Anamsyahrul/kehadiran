<?php
require __DIR__ . '/../../bootstrap.php';

$files = [
    __DIR__ . '/../../sql/schema.sql',
    __DIR__ . '/../../sql/auth_tables.sql',
    __DIR__ . '/../../sql/essential_tables.sql',
    __DIR__ . '/../../sql/perf_indexes.sql',
];

try {
    $pdo = pdo();
    foreach ($files as $file) {
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
    }
    echo "OK: Database migrated." . PHP_EOL;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
