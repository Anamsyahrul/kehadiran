<?php
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../classes/NotificationManager.php';

session_start();
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { json_response(['ok'=>false,'error'=>'Unauthorized'],401); exit; }

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { json_response(['ok'=>false,'error'=>'Invalid id'],400); exit; }

$ok = NotificationManager::markRead($id, (int)$userId);
json_response(['ok'=>$ok]);
