<?php
namespace App\Models;

use App\core\Database;
use PDO;

class NotificationModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Guarda una nueva notificación para un usuario.
     */
    public function create(int $userId, string $type, string $title, string $message): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO notifications (user_id, type, title, message) VALUES (:user_id, :type, :title, :message)"
        );
        return $stmt->execute([
            ':user_id' => $userId,
            ':type' => $type,
            ':title' => $title,
            ':message' => $message
        ]);
    }

    /**
     * Obtiene todas las notificaciones de un usuario.
     */
    public function getByUser(int $userId): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC"
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Elimina una notificación específica, asegurándose
     * de que pertenezca al usuario que la solicita.
     */
    public function deleteById(int $notificationId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM notifications WHERE id = :id AND user_id = :user_id"
        );
        return $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $userId
        ]);
    }
}