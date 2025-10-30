-- schema_complete.sql
-- Skema lengkap sistem Kehadiran RFID Enterprise
-- Menggabungkan struktur tabel inti + tabel tambahan + indeks performa

-- =====================
-- Tabel users
-- =====================
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(100) NULL,
  password VARCHAR(255) NULL,
  role ENUM('admin','teacher','student','parent') NOT NULL DEFAULT 'student',
  kelas VARCHAR(100) NULL,
  uid_hex VARCHAR(32) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE UNIQUE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_uid_hex ON users(uid_hex);
CREATE INDEX idx_users_role ON users(role);

-- =====================
-- Tabel devices
-- =====================
CREATE TABLE IF NOT EXISTS devices (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  device_secret VARCHAR(128) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_seen TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================
-- Tabel kehadiran
-- =====================
CREATE TABLE IF NOT EXISTS kehadiran (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  device_id VARCHAR(64) NULL,
  ts DATETIME NOT NULL,
  uid_hex VARCHAR(32) NOT NULL,
  raw_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_kehadiran_ts (ts),
  INDEX idx_kehadiran_uid (uid_hex),
  INDEX idx_kehadiran_device (device_id),
  INDEX idx_kehadiran_user (user_id),
  CONSTRAINT fk_kehadiran_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_kehadiran_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================
-- Tabel notifications
-- =====================
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('info','success','warning','error') NOT NULL DEFAULT 'info',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);

-- =====================
-- Tabel audit_logs
-- =====================
CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  details JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_audit_logs_user ON audit_logs(user_id);
CREATE INDEX idx_audit_logs_action ON audit_logs(action);

-- =====================
-- Tabel system_settings
-- =====================
CREATE TABLE IF NOT EXISTS system_settings (
  key_name VARCHAR(100) PRIMARY KEY,
  value_text TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================
-- Tabel holiday_calendar
-- =====================
CREATE TABLE IF NOT EXISTS holiday_calendar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  holiday_date DATE NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================
-- Tabel kehadiran_rules (opsional untuk konfigurasi keterlambatan)
-- =====================
CREATE TABLE IF NOT EXISTS kehadiran_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rule_name VARCHAR(100) NOT NULL,
  config JSON NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================
-- Tabel remember_tokens (fitur remember me)
-- =====================
CREATE TABLE IF NOT EXISTS remember_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  selector VARCHAR(24) NOT NULL UNIQUE,
  validator_hash VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_remember_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_remember_tokens_user ON remember_tokens(user_id);

-- =====================
-- Tabel login_attempts (attempt brute force limiter)
-- =====================
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NULL,
  ip_address VARCHAR(45) NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_login_attempts_user ON login_attempts(username);
CREATE INDEX idx_login_attempts_ip ON login_attempts(ip_address);

-- =====================
-- Tabel leave_requests
-- =====================
CREATE TABLE IF NOT EXISTS leave_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  request_date DATE NOT NULL,
  leave_date DATE NOT NULL,
  leave_type ENUM('excused','sick') NOT NULL,
  reason TEXT,
  attachment VARCHAR(255),
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  processed_by INT UNSIGNED NULL,
  processed_at DATETIME NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_leave_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_leave_requests_processed FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_leave_requests_user ON leave_requests(user_id);
CREATE INDEX idx_leave_requests_status ON leave_requests(status);
CREATE INDEX idx_leave_requests_date ON leave_requests(leave_date);

-- =====================
-- Data awal (opsional)
-- =====================
INSERT INTO system_settings(key_name, value_text) VALUES
  ('sekolah_NAME', 'SMA Peradaban Bumiayu'),
  ('sekolah_START', '07:15'),
  ('sekolah_END', '15:00'),
  ('LATE_THRESHOLD', '15'),
  ('REQUIRE_CHECKOUT', '0')
ON DUPLICATE KEY UPDATE value_text = VALUES(value_text);

-- Admin default (username & password sama: admin)
INSERT INTO users(name, username, password, role)
VALUES ('Administrator', 'admin', PASSWORD('admin'), 'admin')
ON DUPLICATE KEY UPDATE username = username;

-- =====================
-- Indeks tambahan untuk performa
-- =====================
CREATE INDEX idx_kehadiran_user_ts ON kehadiran(user_id, ts);
CREATE INDEX idx_notifications_created ON notifications(created_at);
CREATE INDEX idx_audit_logs_created ON audit_logs(created_at);
