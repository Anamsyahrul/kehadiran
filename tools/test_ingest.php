<?php
// Uji manual endpoint ingest: POST payload contoh
$base = 'http://localhost/kehadiran/web/api/ingest.php';
$device_id = 'esp32-01';
$ts = date('c');
$nonce = bin2hex(random_bytes(8));
$events = [ [ 'uid' => 'A1B2C3D4', 'ts' => date('c'), 'type' => 'checkin' ] ];
$secret = '<secret-match-db>'; // ganti sesuai tabel devices
$message = $device_id . '|' . $ts . '|' . $nonce . '|' . json_encode($events, JSON_UNESCAPED_SLASHES);
$hmac = hash_hmac('sha256', $message, $secret);

$payload = [
  'device_id' => $device_id,
  'ts' => $ts,
  'nonce' => $nonce,
  'hmac' => $hmac,
  'events' => $events,
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
echo json_encode(['http_code'=>$code,'response'=>json_decode($out,true)], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
