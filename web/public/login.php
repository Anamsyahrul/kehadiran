<?php
require __DIR__ . '/../bootstrap.php';
session_start();

function isBlocked(string $username, string $ip): bool {
    $stmt = pdo()->prepare('SELECT COUNT(*) FROM login_attempts WHERE (username = ? OR ip_address = ?) AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
    $stmt->execute([$username, $ip]);
    return (int)$stmt->fetchColumn() >= 5;
}

function recordAttempt(string $username, string $ip): void {
    $stmt = pdo()->prepare('INSERT INTO login_attempts(username, ip_address) VALUES(?,?)');
    $stmt->execute([$username, $ip]);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (isBlocked($username, $ip)) {
        http_response_code(429);
        echo 'Terlalu banyak percobaan. Coba lagi nanti.';
        exit;
    }

    $stmt = pdo()->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        recordAttempt($username, $ip);
        writeAudit(null, 'login_failed', ['username'=>$username]);
        echo 'Login gagal';
        exit;
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role'] = $user['role'];

    if ($remember) {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $hash = hash('sha256', $validator);
        $exp = (new DateTime('+30 days'))->format('Y-m-d H:i:s');
        $stmt = pdo()->prepare('INSERT INTO remember_tokens(user_id, selector, validator_hash, expires_at) VALUES(?,?,?,?)');
        $stmt->execute([$user['id'], $selector, $hash, $exp]);
        setcookie('rm_sel', $selector, time()+60*60*24*30, '/', '', false, true);
        setcookie('rm_val', $validator, time()+60*60*24*30, '/', '', false, true);
    }

    $stmt = pdo()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
    $stmt->execute([$user['id']]);
    writeAudit((int)$user['id'], 'login', null);

    $redirect = '/kehadiran/web/public/dashboard.php';
    if ($user['role'] !== 'admin') {
        $redirect = '/kehadiran/web/public/menu.php';
    }

    header('Location: ' . $redirect);
    exit;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <title>Login | Sistem Kehadiran RFID Enterprise</title>
  <style>
    :root {
      --primary: #2a5298;
      --primary-dark: #1e3c72;
      --primary-light: #7ea5ff;
      --surface: #ffffff;
      --surface-soft: rgba(255, 255, 255, 0.85);
      --text-main: #0f1a2e;
      --text-muted: #5f6a86;
    }
    * {
      box-sizing: border-box;
    }
    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 3rem 1.25rem;
      font-family: "Inter", system-ui, -apple-system, "Segoe UI", sans-serif;
      background: radial-gradient(circle at 15% 20%, rgba(42, 82, 152, 0.12), transparent 60%),
                  radial-gradient(circle at 85% 10%, rgba(126, 165, 255, 0.14), transparent 55%),
                  linear-gradient(180deg, #f4f7ff 0%, #ffffff 42%, #f1f5ff 100%);
    }
    body::before,
    body::after {
      content: '';
      position: fixed;
      width: 320px;
      height: 320px;
      border-radius: 50%;
      filter: blur(120px);
      opacity: 0.35;
      z-index: 0;
    }
    body::before {
      background: rgba(42, 82, 152, 0.35);
      top: -140px;
      left: -80px;
    }
    body::after {
      background: rgba(30, 60, 114, 0.3);
      bottom: -160px;
      right: -60px;
    }
    .login-shell {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 460px;
    }
    .login-card {
      border-radius: 26px;
      background: var(--surface);
      box-shadow: 0 22px 50px rgba(15, 26, 46, 0.18);
      padding: 2.6rem 2.4rem 2.3rem;
      border: 1px solid rgba(42, 82, 152, 0.1);
    }
    .logo-badge {
      width: 60px;
      height: 60px;
      border-radius: 18px;
      background: linear-gradient(140deg, var(--primary) 0%, var(--primary-dark) 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      color: #fff;
      box-shadow: 0 14px 28px rgba(42, 82, 152, 0.28);
      margin-bottom: 1.6rem;
    }
    .brand-heading {
      display: flex;
      flex-direction: column;
      gap: 0.4rem;
      margin-bottom: 2.1rem;
    }
    .brand-heading .label {
      font-size: 0.88rem;
      text-transform: uppercase;
      letter-spacing: 0.22em;
      color: rgba(47, 60, 100, 0.6);
    }
    .brand-heading h1 {
      margin: 0;
      color: var(--text-main);
      font-size: 1.8rem;
      font-weight: 700;
    }
    .brand-heading p {
      margin: 0;
      color: var(--text-muted);
      font-size: 0.96rem;
    }
    form {
      display: flex;
      flex-direction: column;
      gap: 1.15rem;
    }
    .form-label {
      font-size: 0.92rem;
      font-weight: 600;
      color: var(--text-main);
      margin-bottom: 0.35rem;
    }
    .form-control {
      border-radius: 14px;
      border: 1px solid rgba(42, 82, 152, 0.16);
      background: #f5f7fb;
      padding: 0.85rem 1rem;
      font-size: 0.95rem;
      color: var(--text-main);
      transition: border 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
    }
    .form-control::placeholder {
      color: rgba(95, 106, 134, 0.6);
    }
    .form-control:focus {
      outline: none;
      border-color: rgba(42, 82, 152, 0.65);
      box-shadow: 0 0 0 0.22rem rgba(42, 82, 152, 0.18);
      background: #fff;
    }
    .form-check-input {
      border-radius: 6px;
      border-color: rgba(42, 82, 152, 0.28);
    }
    .form-check-label {
      font-size: 0.9rem;
      color: var(--text-muted);
    }
    .login-btn {
      border-radius: 14px;
      border: none;
      padding: 0.95rem;
      font-weight: 600;
      letter-spacing: 0.4px;
      font-size: 0.96rem;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: #fff;
      box-shadow: 0 16px 36px rgba(42, 82, 152, 0.28);
      transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .login-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 22px 44px rgba(42, 82, 152, 0.34);
    }
    .login-btn:focus-visible {
      outline: none;
      box-shadow: 0 0 0 0.28rem rgba(42, 82, 152, 0.22);
    }
    .login-footer {
      margin-top: 1.8rem;
      font-size: 0.84rem;
      color: rgba(95, 106, 134, 0.75);
      text-align: center;
    }
    @media (max-width: 575.98px) {
      body {
        padding: 2.5rem 1rem;
      }
      .login-card {
        padding: 2.3rem 1.9rem;
        border-radius: 22px;
      }
      .brand-heading h1 {
        font-size: 1.6rem;
      }
    }
  </style>
</head>
<body>
  <div class="login-shell">
    <div class="login-card">
      <div class="logo-badge">
        <i class="fas fa-wave-square"></i>
      </div>
      <div class="brand-heading">
        <span class="label">RFID ENTERPRISE</span>
        <h1>Masuk ke Dashboard</h1>
        <p>Gunakan kredensial resmi untuk melanjutkan pemantauan kehadiran.</p>
      </div>
      <form method="post">
        <?= csrf_input() ?>
        <div>
          <label for="username" class="form-label">Username</label>
          <input id="username" name="username" class="form-control" placeholder="misal: admin01" required />
        </div>
        <div>
          <label for="password" class="form-label">Password</label>
          <input id="password" type="password" name="password" class="form-control" placeholder="Masukkan password" required />
        </div>
        <div class="form-check d-flex align-items-center gap-2">
          <input class="form-check-input" type="checkbox" name="remember" id="remember">
          <label class="form-check-label" for="remember">Ingat saya di perangkat ini</label>
        </div>
        <button class="login-btn w-100" type="submit">Masuk</button>
      </form>
    </div>
    <div class="login-footer">
      &copy; <?= date('Y') ?> Sistem Kehadiran RFID Enterprise
    </div>
  </div>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>
