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
    public function create(int $userId, $inventoryId, string $type, string $title, string $message): bool
    {
        $stmt = $this->db->prepare("INSERT INTO notifications (user_id, inventory_id, type, title, message) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$userId, $inventoryId, $type, $title, $message]);
    }

    /**
     * Obtiene todas las notificaciones de un usuario.
     */
    public function getByUser($userId): array
    {
        $inventoryId = $_SESSION['active_inventory_id'] ?? 0;

        $stmt = $this->db->prepare("SELECT * FROM notifications WHERE user_id = ? AND inventory_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId, $inventoryId]);
        return $stmt->fetchAll();
    }

    /**
     * Elimina una notificación específica, asegurándose
     * de que pertenezca al usuario que la solicita.
     */
    public function deleteById(int $notificationId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM notifications WHERE id = :id AND user_id = :user_id AND inventory_id = :inventory_id"
        );
        $inventoryId = $_SESSION['active_inventory_id'] ?? 0;
        return $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $userId,
            ':inventory_id' => $inventoryId
        ]);
    }
}