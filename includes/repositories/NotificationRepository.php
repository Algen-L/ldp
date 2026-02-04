<?php
/**
 * Notification Repository
 * Handles system notifications sent between users (primarily Admin to Personnel).
 */
class NotificationRepository
{
    private $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    /**
     * Send a notification
     */
    public function sendNotification($senderId, $recipientId, $message)
    {
        $sql = "INSERT INTO notifications (sender_id, recipient_id, message) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$senderId, $recipientId, $message]);
    }

    /**
     * Get unread notifications for a user
     */
    public function getUnreadNotifications($userId)
    {
        $sql = "SELECT n.*, u.full_name as sender_name, u.profile_picture as sender_picture 
                FROM notifications n 
                JOIN users u ON n.sender_id = u.id 
                WHERE n.recipient_id = ? AND n.is_read = 0 
                ORDER BY n.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all notifications for a user
     */
    public function getAllNotifications($userId, $limit = 50)
    {
        $sql = "SELECT n.*, u.full_name as sender_name, u.profile_picture as sender_picture 
                FROM notifications n 
                JOIN users u ON n.sender_id = u.id 
                WHERE n.recipient_id = ? 
                ORDER BY n.created_at DESC 
                LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, (int) $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead($notificationId, $userId)
    {
        $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$notificationId, $userId]);
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($userId)
    {
        $sql = "UPDATE notifications SET is_read = 1 WHERE recipient_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId]);
    }

    /**
     * Get unread count
     */
    /**
     * Get unread count
     */
    public function getUnreadCount($userId)
    {
        $sql = "SELECT COUNT(*) FROM notifications WHERE recipient_id = ? AND is_read = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }

    /**
     * Delete a notification
     */
    public function deleteNotification($notificationId, $userId)
    {
        $sql = "DELETE FROM notifications WHERE id = ? AND recipient_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$notificationId, $userId]);
    }

    /**
     * Get all notifications for a user (no limit)
     */
    public function getAllUserNotifications($userId)
    {
        $sql = "SELECT n.*, u.full_name as sender_name, u.profile_picture as sender_picture 
                FROM notifications n 
                JOIN users u ON n.sender_id = u.id 
                WHERE n.recipient_id = ? 
                ORDER BY n.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete all notifications for a user
     */
    public function deleteAllNotifications($userId)
    {
        $sql = "DELETE FROM notifications WHERE recipient_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId]);
    }
}
?>