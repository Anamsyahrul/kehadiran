<?php
require __DIR__ . '/../bootstrap.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    // try remember me
    $sel = $_COOKIE['rm_sel'] ?? null; $val = $_COOKIE['rm_val'] ?? null;
    if ($sel && $val) {
        $stmt = pdo()->prepare('SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > NOW()');
        $stmt->execute([$sel]);
        $rt = $stmt->fetch();
        if ($rt && hash_equals($rt['validator_hash'], hash('sha256', $val))) {
            // restore session
            $_SESSION['user_id'] = (int)$rt['user_id'];
            $u = pdo()->prepare('SELECT role FROM users WHERE id = ?'); $u->execute([$rt['user_id']]);
            $_SESSION['role'] = $u->fetchColumn() ?: 'student';
        }
    }
}
if (!isset($_SESSION['user_id'])) {
    header('Location: /kehadiran/web/public/login.php');
    exit;
}
function requireRole(array $roles): void {
    if (!in_array($_SESSION['role'] ?? 'student', $roles, true)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}