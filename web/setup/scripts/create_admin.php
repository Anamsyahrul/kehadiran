<?php
require __DIR__ . '/../../bootstrap.php';

$pdo = pdo();

try {
    $username = 'admin';
    $password = 'admin';
    $name = 'Administrator';

    $select = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $select->execute([$username]);
    $existing = $select->fetchColumn();

    if ($existing) {
        if (isset($_GET['reset'])) {
            $stmt = $pdo->prepare('UPDATE users SET password = ?, is_active = 1, role = "admin" WHERE id = ?');
            $stmt->execute([password_hash($password, PASSWORD_BCRYPT), $existing]);
            echo "Password admin di-reset ke '{$password}'.";
        } else {
            echo "User admin sudah ada. Tambahkan ?reset=1 pada URL untuk reset password ke '{$password}'.";
        }
    } else {
        $stmt = $pdo->prepare('INSERT INTO users(name, username, password, role, kelas, uid_hex, is_active) VALUES(?,?,?,?,?,?,1)');
        $stmt->execute([$name, $username, password_hash($password, PASSWORD_BCRYPT), 'admin', null, null]);
        echo "User admin berhasil dibuat. Username: {$username}, Password: {$password}";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Terjadi kesalahan: ' . htmlspecialchars($e->getMessage());
}
