<?php
class NotificationManager {
    public static function countUnread(int $userId): int {
        $stmt = pdo()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function markRead(int $notifId, int $userId): bool {
        $stmt = pdo()->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?');
        $stmt->execute([$notifId, $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function markAllRead(int $userId): int {
        $stmt = pdo()->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }
}
