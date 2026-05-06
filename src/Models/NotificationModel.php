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
     * Obtiene notificaciones ACTIVAS para el buzón.
     */
    public function getByUser($userId): array
    {
        $inventoryId = $_SESSION['active_inventory_id'] ?? 0;
        $stmt = $this->db->prepare("SELECT * FROM notifications WHERE user_id = ? AND inventory_id = ? AND is_deleted = 0 ORDER BY created_at DESC");
        $stmt->execute([$userId, $inventoryId]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene TODAS las notificaciones (incluidas las "borradas") para el Historial Legal.
     */
    public function getAllForHistory($inventoryId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM notifications WHERE inventory_id = ? ORDER BY created_at DESC");
        $stmt->execute([$inventoryId]);
        return $stmt->fetchAll();
    }

    /**
     * Elimina una notificación específica, asegurándose
     * de que pertenezca al usuario que la solicita.
     */
    public function deleteById(int $notificationId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE notifications SET is_deleted = 1 WHERE id = :id AND user_id = :user_id AND inventory_id = :inventory_id"
        );
        $inventoryId = $_SESSION['active_inventory_id'] ?? 0;
        return $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $userId,
            ':inventory_id' => $inventoryId
        ]);
    }

    /**
     * Elimina todas las notificaciones de un usuario para el inventario activo.
     */
    public function deleteAllByUser(int $userId): bool
    {
        $inventoryId = $_SESSION['active_inventory_id'] ?? 0;
        $stmt = $this->db->prepare("UPDATE notifications SET is_deleted = 1 WHERE user_id = ? AND inventory_id = ?");
        return $stmt->execute([$userId, $inventoryId]);
    }
}