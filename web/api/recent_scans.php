<?php
require __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

try {
    $pdo = pdo();
    $limit = (int)($_GET['limit'] ?? 20);
    $dateFilter = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
        $dateFilter = date('Y-m-d');
    }
    
    $sql = 'SELECT k.id, k.ts, DATE(k.ts) AS ts_date, k.uid_hex, k.raw_json,
                   COALESCE(u.name, CONCAT("UID ", k.uid_hex)) AS user_name, u.kelas,
                   d.name as device_name,
                   JSON_EXTRACT(k.raw_json, "$.is_late") as is_late,
                   JSON_UNQUOTE(JSON_EXTRACT(k.raw_json, "$.status")) as status
            FROM kehadiran k
            LEFT JOIN users u ON k.user_id = u.id
            LEFT JOIN devices d ON k.device_id = d.id
            WHERE DATE(k.ts) = ?
            ORDER BY k.ts DESC
            LIMIT ?';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dateFilter, $limit]);
    $scans = $stmt->fetchAll();
    
    // Convert JSON fields
    foreach ($scans as &$scan) {
        $scan['is_late'] = (bool)$scan['is_late'];
        if ($scan['raw_json']) {
            $scan['raw_data'] = json_decode($scan['raw_json'], true);
        }
    }
    
    echo json_encode(['scans' => $scans]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
