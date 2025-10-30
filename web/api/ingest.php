<?php
require __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok'=>false,'error'=>'Method Not Allowed'], 405);
    exit;
}

$body = file_get_contents('php://input');
$input = json_decode($body, true);
if (!is_array($input)) {
    json_response(['ok'=>false,'error'=>'Invalid JSON'], 400);
    exit;
}

$deviceId = $input['device_id'] ?? null;
$ts       = $input['ts'] ?? null;
$nonce    = $input['nonce'] ?? null;
$hmac     = $input['hmac'] ?? null;
$events   = $input['events'] ?? [];

if (!$deviceId || !$ts || !$nonce || !$hmac || !is_array($events)) {
    json_response(['ok'=>false,'error'=>'Missing fields'], 400);
    exit;
}

// Load device
$stmt = pdo()->prepare('SELECT * FROM devices WHERE id = ? AND is_active = 1');
$stmt->execute([$deviceId]);
$device = $stmt->fetch();
if (!$device) {
    json_response(['ok'=>false,'error'=>'Device not found or inactive'], 401);
    exit;
}

// Verify HMAC
$eventsJson = json_encode($events, JSON_UNESCAPED_SLASHES);
$message = $deviceId.'|'.$ts.'|'.$nonce.'|'.$eventsJson;
if (!verify_hmac($device['device_secret'], $message, $hmac)) {
    json_response(['ok'=>false,'error'=>'Invalid HMAC'], 401);
    exit;
}

$tzServer = new DateTimeZone(date_default_timezone_get());
$pdo = pdo();
$saved = 0; $errors = [];

foreach ($events as $i => $e) {
    $uid  = strtoupper(trim($e['uid'] ?? ''));
    $ets  = $e['ts'] ?? null;
    $payloadType = strtolower(trim($e['type'] ?? ''));
    $eventType = in_array($payloadType, ['checkin', 'checkout'], true) ? $payloadType : 'auto';
    if (!$uid) { $errors[] = ["idx"=>$i,"error"=>'Missing uid']; continue; }

    $dt = new DateTime('now', $tzServer);
    $deviceTsIso = null;
    if ($ets) {
        try {
            $deviceDt = new DateTime($ets, $tzServer);
            $deviceTsIso = $deviceDt->format(DATE_ATOM);
        } catch (Throwable $ex) {
            $deviceTsIso = $ets;
        }
    }

    // Weekend/Holiday handling based on server timestamp
    $allowWeekendHoliday = filter_var(getSetting('ALLOW_WEEKEND_HOLIDAY_SCAN', '0'), FILTER_VALIDATE_BOOL);
    if ((isWeekend($dt) || isHoliday($dt)) && !$allowWeekendHoliday) {
        continue;
    }

    // Find user by UID (optional registration mode)
    $user = findUserByUid($uid);
    if (!$user) {
        $placeholder = ensurePlaceholderUser($uid);
        $raw = [
            'uid' => $uid,
            'ts' => $dt->format(DATE_ATOM),
            'type' => 'pending_registration',
            'status' => 'pending_registration',
            'device_id' => $deviceId,
            'placeholder_user_id' => $placeholder['user']['id'] ?? null
        ];
        if ($deviceTsIso !== null) {
            $raw['device_ts'] = $deviceTsIso;
        }
        $scanDate = $dt->format('Y-m-d');
        $dupStmt = $pdo->prepare('SELECT 1 FROM kehadiran WHERE uid_hex = ? AND DATE(ts) = ? LIMIT 1');
        $dupStmt->execute([$uid, $scanDate]);
        if ($dupStmt->fetchColumn()) {
            $errors[] = ["idx"=>$i,"error"=>'already_scanned_today'];
            continue;
        }
        $stmt = $pdo->prepare('INSERT INTO kehadiran(user_id, device_id, ts, uid_hex, raw_json) VALUES(NULL,?,?,?,?)');
        $stmt->execute([$deviceId, $dt->format('Y-m-d H:i:s'), $uid, json_encode($raw, JSON_UNESCAPED_SLASHES)]);
        $saved++;
        continue;
    }

    // Daily limit check
    $scanDate = $dt->format('Y-m-d');
    $lastTypeStmt = $pdo->prepare('SELECT JSON_UNQUOTE(JSON_EXTRACT(raw_json, "$.type")) AS last_type FROM kehadiran WHERE uid_hex = ? AND DATE(ts) = ? ORDER BY ts DESC LIMIT 1');
    $lastTypeStmt->execute([$uid, $scanDate]);
    $lastType = $lastTypeStmt->fetchColumn() ?: null;

    $schoolEnd = getSetting('sekolah_END', '15:00');
    $parsedEnd = explode(':', $schoolEnd);
    $endHour = isset($parsedEnd[0]) ? (int)$parsedEnd[0] : 15;
    $endMinute = isset($parsedEnd[1]) ? (int)$parsedEnd[1] : 0;
    $checkoutOpensAt = (clone $dt)->setTime($endHour, $endMinute);

    if ($eventType === 'auto') {
        $eventType = ($lastType === 'checkin') ? 'checkout' : 'checkin';
        if ($eventType === 'checkout' && $dt < $checkoutOpensAt) {
            $errors[] = ["idx"=>$i,"error"=>'checkout_not_open'];
            continue;
        }
    } elseif ($eventType === 'checkout' && $dt < $checkoutOpensAt) {
        $errors[] = ["idx"=>$i,"error"=>'checkout_not_open'];
        continue;
    } elseif ($eventType === 'checkout' && $lastType !== 'checkin') {
        // tanpa check-in sebelumnya, treat sebagai check-in baru
        $eventType = 'checkin';
    }

    if (!enforceDailyLimit($uid, $eventType, $dt)) {
        $errors[] = ["idx"=>$i,"error"=>'already_scanned_today'];
        continue; // duplicate within same day
    }

    // Late calculation
    $schoolStart = getSetting('sekolah_START', '07:15');
    $lateThresholdMin = (int) (getSetting('LATE_THRESHOLD', '15'));
    [$h,$m] = array_map('intval', explode(':', $schoolStart));
    $lateAt = (clone $dt)->setTime($h, $m)->modify('+' . $lateThresholdMin . ' minutes');
    $isLate = $dt > $lateAt;
    $lateMinutes = max(0, (int)floor(($dt->getTimestamp() - $lateAt->getTimestamp()) / 60));

    $status = $eventType === 'checkout'
        ? 'checkout'
        : ($isLate ? 'late' : 'present');
    $raw = [
        'uid' => $uid,
        'ts' => $dt->format('Y-m-d H:i:s'),
        'type' => $eventType,
        'status' => $status,
        'is_late' => $isLate,
        'late_minutes' => $lateMinutes,
        'device_id' => $deviceId
    ];
    if ($deviceTsIso !== null) {
        $raw['device_ts'] = $deviceTsIso;
    }

    $stmt = $pdo->prepare('INSERT INTO kehadiran(user_id, device_id, ts, uid_hex, raw_json) VALUES(?,?,?,?,?)');
    $stmt->execute([$user['id'], $deviceId, $dt->format('Y-m-d H:i:s'), $uid, json_encode($raw, JSON_UNESCAPED_SLASHES)]);

    if ($isLate && $eventType === 'checkin') {
        $lateLabel = $lateMinutes > 0 ? $lateMinutes . ' menit' : 'kurang dari 1 menit';
        $userTitle = 'Keterlambatan Kehadiran';
        $userMessage = sprintf(
            'Anda melakukan check-in pada %s (%s terlambat).',
            $dt->format('H:i'),
            $lateLabel
        );
        notifyUser((int)$user['id'], $userTitle, $userMessage, 'warning');

        $adminTitle = 'Siswa Terlambat';
        $adminMessage = sprintf(
            '%s (%s) terlambat %s pada %s melalui perangkat %s.',
            $user['name'] ?? 'Siswa',
            $user['kelas'] ?? 'Tanpa kelas',
            $lateLabel,
            $dt->format('H:i'),
            $deviceId
        );
        notifyAdmins($adminTitle, $adminMessage, 'warning');
    }

    // Optional: write audit
    writeAudit((int)$user['id'], 'scan', [ 'uid'=>$uid, 'type'=>$eventType, 'is_late'=>$isLate ]);

    $saved++;
}

json_response(['ok'=>true,'saved'=>$saved,'errors'=>$errors]);
