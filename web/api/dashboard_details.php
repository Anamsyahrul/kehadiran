<?php
require __DIR__ . '/../bootstrap.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? '';
$dateParam = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
    $dateParam = date('Y-m-d');
}
$pdo = pdo();
$today = $dateParam;

switch ($type) {
    case 'students':
        $rows = $pdo->query('SELECT name, username, kelas, uid_hex, is_active FROM users WHERE role="student" ORDER BY name')->fetchAll();
        json_response(['data' => $rows]);
        break;
    case 'present':
        $stmt = $pdo->prepare('SELECT COALESCE(u.name, CONCAT("UID ", a.uid_hex)) AS name,
                u.kelas,
                MIN(a.ts) AS first_scan,
                MAX(a.ts) AS last_scan
            FROM kehadiran a
            LEFT JOIN users u ON u.id = a.user_id
            WHERE DATE(a.ts) = ?
            GROUP BY COALESCE(u.id, a.uid_hex)
            ORDER BY first_scan');
        $stmt->execute([$today]);
        json_response(['data' => $stmt->fetchAll()]);
        break;
    case 'late':
        $stmt = $pdo->prepare('SELECT COALESCE(u.name, CONCAT("UID ", a.uid_hex)) AS name,
                u.kelas,
                a.ts,
                JSON_UNQUOTE(JSON_EXTRACT(a.raw_json, "$.notes")) AS notes
            FROM kehadiran a
            LEFT JOIN users u ON u.id = a.user_id
            WHERE DATE(a.ts) = ?
              AND (
                JSON_UNQUOTE(JSON_EXTRACT(a.raw_json, "$.status")) = "late"
                OR JSON_EXTRACT(a.raw_json, "$.is_late") = true
              )
            ORDER BY a.ts');
        $stmt->execute([$today]);
        json_response(['data' => $stmt->fetchAll()]);
        break;
    case 'absent':
        $stmt = $pdo->prepare('SELECT name, kelas FROM users WHERE role="student" AND is_active = 1 AND id NOT IN (SELECT DISTINCT user_id FROM kehadiran WHERE DATE(ts)=? AND user_id IS NOT NULL) ORDER BY kelas, name');
        $stmt->execute([$today]);
        json_response(['data' => $stmt->fetchAll()]);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid type']);
}
