<?php
require __DIR__ . '/../bootstrap.php';

$pdo = pdo();
$targetDate = $_GET['date'] ?? date('Y-m-d');
$count = isset($_GET['count']) ? (int)$_GET['count'] : 50;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
    die('Format tanggal salah (YYYY-MM-DD)');
}
$count = max(1, min($count, 500));

$students = $pdo->query("SELECT id, name, kelas FROM users WHERE role='student' AND is_active=1")->fetchAll();
if (!$students) {
    die('Tidak ada siswa aktif. Tambahkan siswa dulu.');
}

$devices = $pdo->query("SELECT id FROM devices WHERE is_active=1")->fetchAll(PDO::FETCH_COLUMN);
if (!$devices) {
    $devices = ['manual-device'];
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO kehadiran (user_id, device_id, ts, uid_hex, raw_json) VALUES (?,?,?,?,?)');
    for ($i = 0; $i < $count; $i++) {
        $student = $students[array_rand($students)];
        $deviceId = $devices[array_rand($devices)];
        $hour = rand(6, 9);
        $minute = rand(0, 59);
        $second = rand(0, 59);
        $timestamp = sprintf('%s %02d:%02d:%02d', $targetDate, $hour, $minute, $second);
        $isLate = $hour > 7 || ($hour == 7 && $minute > 15);
        $status = $isLate ? 'late' : 'present';
        $payload = [
            'type' => 'checkin',
            'status' => $status,
            'is_late' => $isLate,
            'source' => 'seed',
            'notes' => $isLate ? 'Lewat dari jam masuk' : null
        ];
        $uidHex = $student['id'] ? sprintf('seed-%05d', $student['id']) : 'seed-random';
        $stmt->execute([
            $student['id'],
            $deviceId,
            $timestamp,
            $uidHex,
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        ]);
    }
    $pdo->commit();
    echo "Berhasil menambahkan {$count} entri kehadiran untuk tanggal {$targetDate}.";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo 'Error: ' . $e->getMessage();
}
