<?php
// bootstrap.php
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');

$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['APP_TZ'] ?? 'Asia/Jakarta');

function pdo(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $cfg = $GLOBALS['config'];
    $host = $cfg['DB_HOST'] ?? '127.0.0.1';
    $port = (int)($cfg['DB_PORT'] ?? 3306);
    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $cfg['DB_NAME'] . ';charset=utf8mb4';
    $opt = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $cfg['DB_USER'], $cfg['DB_PASS'], $opt);
    return $pdo;
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
}

function verify_hmac(string $secret, string $message, string $provided): bool {
    $calc = hash_hmac('sha256', $message, $secret);
    return hash_equals($calc, strtolower($provided));
}

function isWeekend(DateTime $dt): bool {
    $configured = trim((string)getSetting('WEEKLY_HOLIDAYS', ''));
    $dayNumber = (int)$dt->format('N'); // 1 (Mon) - 7 (Sun)
    if ($configured !== '') {
        $days = array_filter(array_map('intval', explode(',', $configured)), fn($v) => $v >= 1 && $v <= 7);
        if ($days) {
            return in_array($dayNumber, $days, true);
        }
    }
    return $dayNumber >= 6; // default Saturday/Sunday
}

function isHoliday(DateTime $dt): bool {
    $pdo = pdo();
    $stmt = $pdo->prepare('SELECT 1 FROM holiday_calendar WHERE holiday_date = ? LIMIT 1');
    $stmt->execute([$dt->format('Y-m-d')]);
    return (bool)$stmt->fetchColumn();
}

function getSetting(string $key, $default = null) {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = pdo()->prepare('SELECT value_text FROM system_settings WHERE key_name = ?');
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    $cache[$key] = $val !== false ? $val : $default;
    return $cache[$key];
}

function findUserByUid(string $uidHex) {
    $stmt = pdo()->prepare('SELECT * FROM users WHERE uid_hex = ? AND is_active = 1');
    $stmt->execute([$uidHex]);
    return $stmt->fetch();
}

function enforceDailyLimit(string $uidHex, string $type, DateTime $dt): bool {
    // return true if allowed to insert, false if should be skipped (duplicate)
    $start = (clone $dt)->setTime(0,0,0);
    $end   = (clone $start)->modify('+1 day');
    $stmt = pdo()->prepare('SELECT JSON_EXTRACT(raw_json, "$.type") AS t FROM kehadiran WHERE uid_hex = ? AND ts >= ? AND ts < ?');
    $stmt->execute([$uidHex, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
    $types = array_column($stmt->fetchAll(), 't');
    if ($type === 'checkin' && in_array('"checkin"', $types, true)) return false;
    if ($type === 'checkout' && in_array('"checkout"', $types, true)) return false;
    return true;
}

function writeAudit(?int $userId, string $action, $details = null): void {
    try {
        $stmt = pdo()->prepare('INSERT INTO audit_logs(user_id, action, details, ip_address, user_agent) VALUES(?,?,?,?,?)');
        $stmt->execute([
            $userId,
            $action,
            is_array($details) || is_object($details) ? json_encode($details, JSON_UNESCAPED_SLASHES) : $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('audit failed: ' . $e->getMessage());
    }
}

function createNotification(?int $userId, string $title, string $message, string $type = 'info'): void {
    static $allowedTypes = ['info', 'success', 'warning', 'error'];
    $type = in_array($type, $allowedTypes, true) ? $type : 'info';
    try {
        $stmt = pdo()->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $title, $message, $type]);
    } catch (Throwable $e) {
        error_log('createNotification failed: ' . $e->getMessage());
    }
}

function notifyAdmins(string $title, string $message, string $type = 'info'): void {
    try {
        $pdo = pdo();
        $stmt = $pdo->query('SELECT id FROM users WHERE role = "admin" AND is_active = 1');
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $adminId) {
            createNotification((int)$adminId, $title, $message, $type);
        }
    } catch (Throwable $e) {
        error_log('notifyAdmins failed: ' . $e->getMessage());
    }
}

function notifyUser(int $userId, string $title, string $message, string $type = 'info'): void {
    createNotification($userId, $title, $message, $type);
}

function generateUniqueUsername(string $base): string {
    $pdo = pdo();
    $base = strtolower(preg_replace('/[^a-z0-9_]/', '_', $base));
    $base = $base !== '' ? $base : 'rfid_user';
    $username = $base;
    $suffix = 1;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    while (true) {
        $stmt->execute([$username]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $username;
        }
        $username = $base . '_' . $suffix;
        $suffix++;
    }
}

/**
 * Pastikan UID terdaftar sebagai placeholder user yang belum aktif.
 * Mengembalikan ['user' => array user, 'created' => bool] untuk menandai apakah baris baru dibuat.
 */
function ensurePlaceholderUser(string $uidHex): array {
    $uid = strtoupper(trim($uidHex));
    $pdo = pdo();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE uid_hex = ? LIMIT 1');
    $stmt->execute([$uid]);
    $existing = $stmt->fetch();
    if ($existing) {
        return ['user' => $existing, 'created' => false];
    }

    $tempPassword = bin2hex(random_bytes(6));

    $insert = $pdo->prepare('INSERT INTO users(name, username, password, role, kelas, uid_hex, is_active) VALUES(?,?,?,?,?,?,0)');
    $insert->execute([
        '',
        null,
        password_hash($tempPassword, PASSWORD_BCRYPT),
        'student',
        null,
        $uid
    ]);
    $newId = (int)$pdo->lastInsertId();

    writeAudit(null, 'user_placeholder_create', ['uid_hex' => $uid, 'user_id' => $newId]);
    notifyAdmins('Kartu baru membutuhkan data', sprintf('UID %s berhasil ditangkap. Lengkapi nama siswa di menu Manajemen Pengguna.', $uid), 'info');

    $fetch = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $fetch->execute([$newId]);
    $user = $fetch->fetch();

    return ['user' => $user, 'created' => true];
}

function formatDateIndo(string $dateString, bool $includeDay = false): string {
    static $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    try {
        $dt = new DateTime($dateString);
    } catch (Exception $e) {
        return $dateString;
    }

    $formatted = $dt->format('j') . ' ' . ($months[(int)$dt->format('n')] ?? $dt->format('F')) . ' ' . $dt->format('Y');
    return $includeDay ? dayNameIndo($dateString) . ', ' . $formatted : $formatted;
}

function dayNameIndo(string $dateString): string {
    static $days = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];

    try {
        $dt = new DateTime($dateString);
        return $days[$dt->format('l')] ?? $dt->format('l');
    } catch (Exception $e) {
        return $dateString;
    }
}

function formatTimeIndo(string $timeString, bool $includeSeconds = false): string {
    try {
        $dt = new DateTime($timeString);
        return $includeSeconds ? $dt->format('H.i.s') : $dt->format('H.i');
    } catch (Exception $e) {
        return $timeString;
    }
}

function formatDateTimeIndo(string $datetimeString, bool $includeSeconds = false, bool $includeDay = false): string {
    try {
        $dt = new DateTime($datetimeString);
    } catch (Exception $e) {
        return $datetimeString;
    }
    $datePart = formatDateIndo($dt->format('Y-m-d'), $includeDay);
    $timePart = formatTimeIndo($dt->format('Y-m-d H:i:s'), $includeSeconds);
    return $datePart . ' ' . $timePart;
}

// CSRF helpers
function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string {
    $t = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_token" value="' . $t . '">';
}

function csrf_verify(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $ok = isset($_POST['_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$_POST['_token']);
    if (!$ok) {
        http_response_code(419);
        echo 'CSRF token mismatch';
        exit;
    }
}
