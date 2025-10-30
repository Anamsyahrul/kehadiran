-- essential_tables.sql: seed minimal dan konfigurasi awal
INSERT IGNORE INTO system_settings (key_name, value_text) VALUES
 ('sekolah_NAME','SMA Peradaban Bumiayu'),
 ('sekolah_START','07:15'),
 ('sekolah_END','15:00'),
 ('LATE_THRESHOLD','15'),
 ('REQUIRE_CHECKOUT','0'),
 ('ALLOW_WEEKEND_HOLIDAY_SCAN','0'),
 ('ADMIN_USER','Administrator'),
 ('ADMIN_PASS','admin');

-- password: admin (bcrypt)
INSERT IGNORE INTO users (name, username, password, role, kelas, is_active) VALUES
 ('Administrator','Administrator', '$2y$10$ZtcoYq5sJmM8rQm0F8kL3u6z6l1x0o4oQKf4oEDb7bOQ8aZhx4gja', 'admin', NULL, 1);
