<?php
require __DIR__ . '/_auth.php';
requireRole(['admin']);

$pdo = pdo();

// Update settings
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    
    $settings = [
        'sekolah_NAME' => trim($_POST['sekolah_NAME'] ?? ''),
        'sekolah_START' => trim($_POST['sekolah_START'] ?? ''),
        'sekolah_END' => trim($_POST['sekolah_END'] ?? ''),
        'LATE_THRESHOLD' => (int)($_POST['LATE_THRESHOLD'] ?? 15),
        'REQUIRE_CHECKOUT' => isset($_POST['REQUIRE_CHECKOUT']) ? 1 : 0,
        'ALLOW_WEEKEND_HOLIDAY_SCAN' => isset($_POST['ALLOW_WEEKEND_HOLIDAY_SCAN']) ? 1 : 0,
        'REGISTRATION_MODE' => isset($_POST['REGISTRATION_MODE']) ? 1 : 0,
    ];
    $weeklyHolidays = $_POST['weekly_holidays'] ?? [];
    if (!is_array($weeklyHolidays)) {
        $weeklyHolidays = [];
    }
    $weeklyHolidays = array_values(array_unique(array_filter(array_map('intval', $weeklyHolidays), fn($d) => $d >= 1 && $d <= 7)));
    sort($weeklyHolidays);
    $settings['WEEKLY_HOLIDAYS'] = implode(',', $weeklyHolidays);
    
    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare('INSERT INTO system_settings(key_name, value_text) VALUES(?,?) ON DUPLICATE KEY UPDATE value_text=VALUES(value_text)');
        $stmt->execute([$key, (string)$value]);
    }
    
    writeAudit((int)($_SESSION['user_id'] ?? 0), 'settings_update', $settings);
    header('Location: /kehadiran/web/public/settings.php');
    exit;
}

// Get current settings
$settings = [];
$stmt = $pdo->query('SELECT key_name, value_text FROM system_settings');
while ($row = $stmt->fetch()) {
    $settings[$row['key_name']] = $row['value_text'];
}
$weeklyHolidaySetting = trim($settings['WEEKLY_HOLIDAYS'] ?? '');
$weeklyHolidayDays = $weeklyHolidaySetting === '' ? [6,7] : array_values(array_unique(array_filter(array_map('intval', explode(',', $weeklyHolidaySetting)), fn($d) => $d >= 1 && $d <= 7)));
if (!$weeklyHolidayDays) {
    $weeklyHolidayDays = [6,7];
}

$dayLabels = [
    1 => 'Senin',
    2 => 'Selasa',
    3 => 'Rabu',
    4 => 'Kamis',
    5 => 'Jumat',
    6 => 'Sabtu',
    7 => 'Minggu',
];
if (!isset($settings['ALLOW_WEEKEND_HOLIDAY_SCAN'])) {
    $settings['ALLOW_WEEKEND_HOLIDAY_SCAN'] = '0';
}
if (!isset($settings['REGISTRATION_MODE'])) {
    $settings['REGISTRATION_MODE'] = '0';
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <title>Pengaturan Sekolah - Sistem RFID Enterprise</title>
  <style>
    body {
      background-color: #f8f9fa;
    }
    .card {
      border: none;
      border-radius: 15px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main-content">
  <div class="container-fluid py-4">
    <div class="row">
      <div class="col-12 page-heading">
        <div class="page-heading__content">
          <div class="page-heading__title">
            <span class="page-heading__icon">
              <i class="fas fa-cog"></i>
            </span>
            <div class="page-heading__label">
              <h2>Pengaturan Sekolah</h2>
              <p class="page-heading__description">Konfigurasi pengaturan sistem kehadiran</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <form method="post" class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">Konfigurasi Sistem</h5>
      </div>
      <div class="card-body">
        <?= csrf_input() ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nama Sekolah</label>
            <input type="text" name="sekolah_NAME" value="<?= htmlspecialchars($settings['sekolah_NAME'] ?? 'SMA Peradaban Bumiayu') ?>" class="form-control" required />
          </div>
          <div class="col-md-3">
            <label class="form-label">Jam Mulai Sekolah (24 jam)</label>
            <input type="text"
                   name="sekolah_START"
                   value="<?= htmlspecialchars($settings['sekolah_START'] ?? '07:15') ?>"
                   class="form-control"
                   placeholder="HH:MM (contoh 07:15)"
                   pattern="^(?:[01]\d|2[0-3]):[0-5]\d$"
                   required />
          </div>
          <div class="col-md-3">
            <label class="form-label">Jam Selesai Sekolah (24 jam)</label>
            <input type="text"
                   name="sekolah_END"
                   value="<?= htmlspecialchars($settings['sekolah_END'] ?? '15:00') ?>"
                   class="form-control"
                   placeholder="HH:MM (contoh 15:00)"
                   pattern="^(?:[01]\d|2[0-3]):[0-5]\d$"
                   required />
          </div>
          <div class="col-md-3">
            <label class="form-label">Toleransi Keterlambatan (menit)</label>
            <input type="number" name="LATE_THRESHOLD" value="<?= htmlspecialchars($settings['LATE_THRESHOLD'] ?? '15') ?>" class="form-control" min="0" max="60" required />
          </div>
          <div class="col-12">
            <label class="form-label">Hari Libur Mingguan</label>
            <div class="d-flex flex-wrap gap-3">
              <?php foreach ($dayLabels as $dayNumber => $dayLabel): ?>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="weekly_holidays[]" id="weekly_day_<?= $dayNumber ?>" value="<?= $dayNumber ?>" <?= in_array($dayNumber, $weeklyHolidayDays, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="weekly_day_<?= $dayNumber ?>"><?= htmlspecialchars($dayLabel) ?></label>
                </div>
              <?php endforeach; ?>
            </div>
            <small class="text-muted d-block mt-2">Tap pada hari yang dicentang akan dianggap libur secara otomatis.</small>
          </div>
          <div class="col-md-9">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="REQUIRE_CHECKOUT" id="REQUIRE_CHECKOUT" <?= ($settings['REQUIRE_CHECKOUT'] ?? '0') ? 'checked' : '' ?>>
                  <label class="form-check-label" for="REQUIRE_CHECKOUT">Wajib Checkout</label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="ALLOW_WEEKEND_HOLIDAY_SCAN" id="ALLOW_WEEKEND_HOLIDAY_SCAN" <?= ($settings['ALLOW_WEEKEND_HOLIDAY_SCAN'] ?? '0') ? 'checked' : '' ?>>
                  <label class="form-check-label" for="ALLOW_WEEKEND_HOLIDAY_SCAN">Izinkan Scan di Hari Libur Mingguan</label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="REGISTRATION_MODE" id="REGISTRATION_MODE" <?= ($settings['REGISTRATION_MODE'] ?? '0') ? 'checked' : '' ?>>
                  <label class="form-check-label" for="REGISTRATION_MODE">Mode Registrasi</label>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-1"></i>Simpan Pengaturan
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
