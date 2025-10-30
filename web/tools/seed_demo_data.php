<?php
require __DIR__ . '/../bootstrap.php';

$secret = $_GET['secret'] ?? '';
if ($secret !== 'SEED') {
    http_response_code(403);
    echo 'Tambahkan parameter ?secret=SEED untuk menjalankan seeder.';
    exit;
}

$studentCount = max(5, min((int)($_GET['students'] ?? 50), 500));
$deviceCount  = max(1, min((int)($_GET['devices'] ?? 3), 20));
$daysBack     = max(1, min((int)($_GET['days'] ?? 30), 180));

$pdo = pdo();
$pdo->beginTransaction();

try {
    // 1) Seed devices jika belum ada
    $existingDevices = $pdo->query('SELECT COUNT(*) FROM devices')->fetchColumn();
    if ((int)$existingDevices === 0) {
        for ($i = 1; $i <= $deviceCount; $i++) {
            $id = sprintf('demo-device-%02d', $i);
            $stmt = $pdo->prepare('INSERT INTO devices(id, name, device_secret, is_active) VALUES (?,?,?,1)');
            $stmt->execute([$id, 'Perangkat RFID Demo #' . $i, bin2hex(random_bytes(16))]);
        }
    }

    // 2) Seed students jika belum ada cukup
    $existingStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    if ((int)$existingStudents < $studentCount) {
        $firstNames = ['Adi','Budi','Citra','Dewi','Eka','Fajar','Gita','Hadi','Indah','Joko','Kiki','Lia','Maya','Nanda','Oka','Putri','Raka','Sari','Teguh','Utari','Vina','Wulan','Yuda','Zahra'];
        $lastNames  = ['Saputra','Santoso','Wijaya','Pratama','Susanto','Mahendra','Fauzi','Prasetyo','Handayani','Lestari'];
        $kelasList  = ['X IPA 1','X IPA 2','XI IPA 1','XI IPA 2','XII IPA 1','XII IPA 2'];
        $needed = $studentCount - (int)$existingStudents;
        $stmt = $pdo->prepare('INSERT INTO users(name, username, password, role, kelas, is_active) VALUES (?,?,?,?,?,1)');
        for ($i = 0; $i < $needed; $i++) {
            $name = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
            $kelas = $kelasList[array_rand($kelasList)];
            $username = strtolower(str_replace(' ', '.', $name)) . sprintf('%02d', rand(1,99));
            $passwordHash = password_hash('123456', PASSWORD_BCRYPT);
            $stmt->execute([$name, $username, $passwordHash, 'student', $kelas]);
        }
    }

    // Ambil data untuk penyusunan kehadiran
    $students = $pdo->query("SELECT id, kelas FROM users WHERE role='student' AND is_active=1")->fetchAll(PDO::FETCH_ASSOC);
    if (!$students) {
        throw new RuntimeException('Tidak ada siswa untuk dibuatkan kehadiran.');
    }
    $devices = $pdo->query('SELECT id FROM devices WHERE is_active=1')->fetchAll(PDO::FETCH_COLUMN);
    if (!$devices) {
        $devices = ['demo-device-01'];
    }

    // 3) Seed attendance
    $insertAttendance = $pdo->prepare('INSERT INTO kehadiran (user_id, device_id, ts, uid_hex, raw_json) VALUES (?,?,?,?,?)');
    $days = [];
    $today = new DateTime('today');
    for ($i = 0; $i < $daysBack; $i++) {
        $days[] = (clone $today)->modify("-$i days")->format('Y-m-d');
    }

    foreach ($days as $date) {
        $dailyStudents = $students;
        shuffle($dailyStudents);
        $sampleSize = max(5, (int)(count($students) * 0.6));
        $sample = array_slice($dailyStudents, 0, $sampleSize);

        foreach ($sample as $student) {
            $hour = rand(6, 8);
            $minute = rand(0, 59);
            $second = rand(0, 59);
            $timestamp = sprintf('%s %02d:%02d:%02d', $date, $hour, $minute, $second);
            $isLate = $hour > 7 || ($hour == 7 && $minute > 15);
            $status = $isLate ? 'late' : 'present';
            $payload = [
                'type' => 'checkin',
                'status' => $status,
                'is_late' => $isLate,
                'source' => 'seed-demo'
            ];
            $deviceId = $devices[array_rand($devices)];
            $uidHex = sprintf('demo-%05d', $student['id']);
            $insertAttendance->execute([
                $student['id'],
                $deviceId,
                $timestamp,
                $uidHex,
                json_encode($payload, JSON_UNESCAPED_UNICODE)
            ]);

            // Tambahkan checkout acak
            if (rand(0,1)) {
                $checkoutHour = rand(14, 16);
                $checkoutMinute = rand(0, 59);
                $timestampCheckout = sprintf('%s %02d:%02d:%02d', $date, $checkoutHour, $checkoutMinute, rand(0,59));
                $payloadCheckout = [
                    'type' => 'checkout',
                    'status' => $status,
                    'source' => 'seed-demo'
                ];
                $insertAttendance->execute([
                    $student['id'],
                    $deviceId,
                    $timestampCheckout,
                    $uidHex,
                    json_encode($payloadCheckout, JSON_UNESCAPED_UNICODE)
                ]);
            }
        }
    }

    $pdo->commit();
    echo "Seeder selesai. Total siswa: " . count($students) . ", rentang hari: {$daysBack}.";
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
