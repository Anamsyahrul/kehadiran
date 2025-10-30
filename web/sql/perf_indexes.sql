-- perf_indexes.sql: indeks tambahan untuk performa laporan
CREATE INDEX IF NOT EXISTS idx_kehadiran_user_ts ON kehadiran (user_id, ts);
CREATE INDEX IF NOT EXISTS idx_notif_user_read ON notifications (user_id, is_read, created_at);
CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_logs (created_at);
