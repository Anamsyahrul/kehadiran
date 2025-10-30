<?php
require __DIR__ . '/../bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
    exit;
}

$deviceId = trim($_GET['device_id'] ?? '');
$ts = trim($_GET['ts'] ?? '');
$nonce = trim($_GET['nonce'] ?? '');
$providedHmac = strtolower(trim($_GET['hmac'] ?? ''));

if ($deviceId === '' || $ts === '' || $nonce === '' || $providedHmac === '') {
    json_response(['ok' => false, 'error' => 'Bad request'], 400);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $deviceId)) {
    json_response(['ok' => false, 'error' => 'Invalid device id'], 400);
    exit;
}

if (!preg_match('/^[0-9]{1,10}$/', $ts)) {
    json_response(['ok' => false, 'error' => 'Invalid timestamp'], 400);
    exit;
}

try {
    $stmt = pdo()->prepare('SELECT device_secret, is_active FROM devices WHERE id = ? LIMIT 1');
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch();
    if (!$device) {
        json_response(['ok' => false, 'error' => 'Device not found'], 404);
        exit;
    }
    if ((int)($device['is_active'] ?? 0) !== 1) {
        json_response(['ok' => false, 'error' => 'Device inactive'], 403);
        exit;
    }
    $secret = (string)$device['device_secret'];
    $message = $deviceId . '|' . $ts . '|' . $nonce;
    $calc = hash_hmac('sha256', $message, $secret);
    if (!hash_equals($calc, $providedHmac)) {
        json_response(['ok' => false, 'error' => 'Invalid signature'], 401);
        exit;
    }

    $regMode = filter_var(getSetting('REGISTRATION_MODE', '0'), FILTER_VALIDATE_BOOL);
    $weeklyHolidaySetting = trim((string)getSetting('WEEKLY_HOLIDAYS', ''));
    $allowWeekendScan = filter_var(getSetting('ALLOW_WEEKEND_HOLIDAY_SCAN', '0'), FILTER_VALIDATE_BOOL);

    $students = pdo()->query("SELECT uid_hex, name, kelas FROM users WHERE role='student' AND is_active=1 AND uid_hex IS NOT NULL AND uid_hex <> '' ORDER BY name")
                    ->fetchAll(PDO::FETCH_ASSOC);
    $hashBase = [];
    foreach ($students as $row) {
        $uid = strtolower(trim($row['uid_hex'] ?? ''));
        if ($uid === '') {
            continue;
        }
        $hashBase[] = $uid . '|' . ($row['name'] ?? '') . '|' . ($row['kelas'] ?? '');
    }
    $studentsVersion = hash('sha1', implode("\n", $hashBase));

    json_response([
        'ok' => true,
        'reg_mode' => $regMode,
        'weekly_holidays' => $weeklyHolidaySetting,
        'allow_holiday_scan' => $allowWeekendScan,
        'students_version' => $studentsVersion,
        'server_time' => date(DATE_ATOM),
    ]);
} catch (Throwable $e) {
    error_log('device_config error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Server error'], 500);
}
