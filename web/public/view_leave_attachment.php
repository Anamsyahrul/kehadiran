<?php
require __DIR__ . '/_auth.php';

$pdo = pdo();
$leaveId = (int)($_GET['id'] ?? 0);
if ($leaveId <= 0) {
    http_response_code(404);
    exit('Lampiran tidak ditemukan.');
}

$stmt = $pdo->prepare('SELECT lr.*, u.id AS owner_id FROM leave_requests lr JOIN users u ON u.id = lr.user_id WHERE lr.id = ?');
$stmt->execute([$leaveId]);
$leave = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$leave || empty($leave['attachment'])) {
    http_response_code(404);
    exit('Lampiran tidak ditemukan.');
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = $_SESSION['role'] ?? '';

if ($currentUserId !== (int)$leave['owner_id'] && !in_array($currentRole, ['admin', 'teacher'], true)) {
    http_response_code(403);
    exit('Anda tidak berhak mengakses lampiran ini.');
}

$filePath = dirname(__DIR__) . '/' . $leave['attachment'];
if (!is_file($filePath)) {
    http_response_code(404);
    exit('Lampiran tidak ditemukan di server.');
}

$mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
$fileName = basename($filePath);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
