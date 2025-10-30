<?php
require __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

try {
    $pdo = pdo();
    $today = date('Y-m-d');
    $targetDate = $_GET['date'] ?? $today;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
        $targetDate = $today;
    }

    $latestStmt = $pdo->query('SELECT DATE(MAX(ts)) FROM kehadiran WHERE user_id IS NOT NULL');
    $latestDate = $latestStmt->fetchColumn();

    $totalStmt = $pdo->prepare('SELECT COUNT(DISTINCT COALESCE(CAST(user_id AS CHAR), uid_hex)) FROM kehadiran WHERE DATE(ts) = ?');
    $totalStmt->execute([$targetDate]);
    $totalScans = (int)$totalStmt->fetchColumn();

    if ($totalScans === 0 && $latestDate && $targetDate === $today && $latestDate !== $today) {
        $targetDate = $latestDate;
        $totalStmt->execute([$targetDate]);
        $totalScans = (int)$totalStmt->fetchColumn();
    }

    $lateStmt = $pdo->prepare('SELECT COUNT(DISTINCT COALESCE(CAST(user_id AS CHAR), uid_hex))
        FROM kehadiran
        WHERE DATE(ts) = ?
          AND (
            JSON_UNQUOTE(JSON_EXTRACT(raw_json, "$.status")) = "late"
            OR JSON_EXTRACT(raw_json, "$.is_late") = true
          )');
    $lateStmt->execute([$targetDate]);
    $lateCount = (int)$lateStmt->fetchColumn();

    $ontimeCount = max(0, $totalScans - $lateCount);
    
    // Active devices
    $stmt = $pdo->query('SELECT COUNT(*) FROM devices WHERE is_active = 1');
    $activeDevices = (int)$stmt->fetchColumn();
    
    echo json_encode([
        'totalScans' => $totalScans,
        'ontimeCount' => $ontimeCount,
        'lateCount' => $lateCount,
        'activeDevices' => $activeDevices,
        'targetDate' => $targetDate,
        'timestamp' => date('c')
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
