<?php
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../classes/NotificationManager.php';

session_start();
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { json_response(['ok'=>false,'error'=>'Unauthorized'],401); exit; }

$n = NotificationManager::markAllRead((int)$userId);
json_response(['ok'=>true,'updated'=>$n]);
