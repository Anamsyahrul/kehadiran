<?php
require __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

if (!isset($_GET['date'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing date parameter']);
    exit;
}

$date = $_GET['date'];
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

try {
    $pdo = pdo();
    $summaryStmt = $pdo->prepare(
        'SELECT 
            COUNT(DISTINCT CASE WHEN user_id IS NOT NULL THEN user_id END) AS hadir,
            COUNT(DISTINCT CASE WHEN user_id IS NOT NULL AND (
                JSON_UNQUOTE(JSON_EXTRACT(raw_json, "$.status")) = "late"
                OR JSON_EXTRACT(raw_json, "$.is_late") = true
            ) THEN user_id END) AS terlambat
         FROM kehadiran
         WHERE DATE(ts) = ?'
    );
    $summaryStmt->execute([$date]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: ['hadir' => 0, 'terlambat' => 0];

    $absentStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE is_active = 1 AND role = "student" AND id NOT IN (SELECT DISTINCT user_id FROM kehadiran WHERE DATE(ts) = ? AND user_id IS NOT NULL)');
    $absentStmt->execute([$date]);
    $absent = (int)$absentStmt->fetchColumn();

    $presentStmt = $pdo->prepare('SELECT u.name, u.kelas, MIN(a.ts) AS first_scan, MAX(a.ts) AS last_scan
        FROM kehadiran a
        JOIN users u ON u.id = a.user_id
        WHERE DATE(a.ts) = ?
        GROUP BY u.id
        ORDER BY first_scan');
    $presentStmt->execute([$date]);
    $presentRows = $presentStmt->fetchAll();

    $lateStmt = $pdo->prepare('SELECT u.name, u.kelas, a.ts, JSON_UNQUOTE(JSON_EXTRACT(a.raw_json, "$.notes")) AS notes
        FROM kehadiran a
        JOIN users u ON u.id = a.user_id
        WHERE DATE(a.ts) = ?
          AND (
            JSON_UNQUOTE(JSON_EXTRACT(a.raw_json, "$.status")) = "late"
            OR JSON_EXTRACT(a.raw_json, "$.is_late") = true
          )
        ORDER BY a.ts');
    $lateStmt->execute([$date]);
    $lateRows = $lateStmt->fetchAll();

    $absentListStmt = $pdo->prepare('SELECT name, kelas
        FROM users
        WHERE role = "student" AND is_active = 1
          AND id NOT IN (
            SELECT DISTINCT user_id FROM kehadiran WHERE DATE(ts) = ? AND user_id IS NOT NULL
          )
        ORDER BY kelas, name');
    $absentListStmt->execute([$date]);
    $absentRows = $absentListStmt->fetchAll();

    echo json_encode([
        'summary' => [
            'hadir' => (int)$summary['hadir'],
            'terlambat' => (int)$summary['terlambat'],
            'tidak_hadir' => $absent
        ],
        'present' => $presentRows,
        'late' => $lateRows,
        'absent' => $absentRows
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
