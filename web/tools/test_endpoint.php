<?php
// Test endpoint ingest dengan data real
$base = 'http://10.185.103.121/kehadiran/web/api/ingest.php';
$device_id = 'esp32-01';
$device_secret = 'anam123';
$ts = date('c');
$nonce = bin2hex(random_bytes(8));

// Test dengan UID siswa yang ada
$events = [
    ['uid' => 'A1B2C3D4', 'ts' => date('c'), 'type' => 'checkin'],
    ['uid' => 'B2C3D4E5', 'ts' => date('c'), 'type' => 'checkin']
];

$eventsJson = json_encode($events, JSON_UNESCAPED_SLASHES);
$message = $device_id . '|' . $ts . '|' . $nonce . '|' . $eventsJson;
$hmac = hash_hmac('sha256', $message, $device_secret);

$payload = [
    'device_id' => $device_id,
    'ts' => $ts,
    'nonce' => $nonce,
    'hmac' => $hmac,
    'events' => $events
];

$ch = curl_init($base);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
$out = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json');
echo json_encode([
    'http_code' => $code,
    'response' => json_decode($out, true),
    'payload' => $payload
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
