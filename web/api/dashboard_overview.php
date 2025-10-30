<?php
require __DIR__ . '/../bootstrap.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
    exit;
}

$date = $_GET['date'] ?? (new DateTime('now'))->format('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    json_response(['ok' => false, 'error' => 'Invalid date'], 400);
    exit;
}

try {
    $pdo = pdo();

    $totalStudents = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1 AND role = "student"')->fetchColumn();

    $summaryStmt = $pdo->prepare(
        'SELECT 
            COUNT(DISTINCT COALESCE(CAST(user_id AS CHAR), uid_hex)) AS hadir,
            COUNT(DISTINCT CASE WHEN (
                JSON_UNQUOTE(JSON_EXTRACT(raw_json, "$.status")) = "late"
                OR JSON_EXTRACT(raw_json, "$.is_late") = true
            ) THEN COALESCE(CAST(user_id AS CHAR), uid_hex) END) AS terlambat
         FROM kehadiran
         WHERE DATE(ts) = ?'
    );
    $summaryStmt->execute([$date]);
    $summaryRow = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: ['hadir' => 0, 'terlambat' => 0];

    $absentStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE is_active = 1 AND role = "student" AND id NOT IN (SELECT DISTINCT user_id FROM kehadiran WHERE DATE(ts) = ? AND user_id IS NOT NULL)');
    $absentStmt->execute([$date]);
    $absent = (int)$absentStmt->fetchColumn();

    $baseDate = new DateTime($date);
    $countStmt = $pdo->prepare('SELECT COUNT(DISTINCT COALESCE(CAST(user_id AS CHAR), uid_hex)) FROM kehadiran WHERE DATE(ts) = ?');
    $chart = [];
    for ($i = 6; $i >= 0; $i--) {
        $dateObj = (clone $baseDate)->modify("-{$i} days");
        $dateStr = $dateObj->format('Y-m-d');
        $countStmt->execute([$dateStr]);
        $chart[] = [
            'date' => $dateStr,
            'scans' => (int)$countStmt->fetchColumn()
        ];
    }

    $notifStmt = $pdo->prepare('SELECT title, message, type, created_at FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT 5');
    $notifStmt->execute([$userId]);
    $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

    json_response([
        'ok' => true,
        'summary' => [
            'total_students' => $totalStudents,
            'hadir' => (int)$summaryRow['hadir'],
            'terlambat' => (int)$summaryRow['terlambat'],
            'tidak_hadir' => $absent
        ],
        'chart' => $chart,
        'notifications' => $notifications
    ]);
} catch (Throwable $e) {
    error_log('dashboard_overview error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Server error'], 500);
}
