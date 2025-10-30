<?php
require __DIR__ . '/../bootstrap.php';

$secret = $_GET['secret'] ?? '';
if ($secret !== 'RESET') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$pdo = pdo();
$newPassword = 'admin123';
$hash = password_hash($newPassword, PASSWORD_BCRYPT);
$stmt = $pdo->prepare('UPDATE users SET password = ?, is_active = 1 WHERE username = ?');
$stmt->execute([$hash, 'admin']);
if ($stmt->rowCount() === 0) {
    $stmt = $pdo->prepare('INSERT INTO users(name, username, password, role, is_active) VALUES(?,?,?,?,1)');
    $stmt->execute(['Administrator', 'admin', $hash, 'admin']);
}

echo 'Password admin direset menjadi: ' . htmlspecialchars($newPassword) . '. Harap login lalu ubah password, dan hapus file reset_admin_password.php.';