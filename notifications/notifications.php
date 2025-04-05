<?php
session_start();
require_once('../config.php');

class NotificationManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // Send notification
    public function sendNotification($user_id, $type, $message, $related_id = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO notifications (user_id, type, message, related_id, created_at)
                VALUES (:user_id, :type, :message, :related_id, NOW())
            ");
            
            $stmt->execute([
                ':user_id' => $user_id,
                ':type' => $type,
                ':message' => $message,
                ':related_id' => $related_id
            ]);
            
            return true;
        } catch(PDOException $e) {
            error_log("Error sending notification: " . $e->getMessage());
            return false;
        }
    }
    
    // Get user notifications
    public function getUserNotifications($limit = 10) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM notifications 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT :limit
            ");
            
            $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching notifications: " . $e->getMessage());
            return [];
        }
    }
    
    // Mark notification as read
    public function markAsRead($notification_id) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE notifications 
                SET read_at = NOW() 
                WHERE notification_id = :notification_id 
                AND user_id = :user_id
            ");
            
            return $stmt->execute([
                ':notification_id' => $notification_id,
                ':user_id' => $_SESSION['user_id']
            ]);
        } catch(PDOException $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }
}
?>