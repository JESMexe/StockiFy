<?php
namespace App\Models;

use App\core\Database;
use PDO;

class ActivityLogModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function log(int $inventoryId, ?int $userId, string $action, string $entityType, ?string $entityId = null, ?string $description = null): bool
    {
        if ($inventoryId <= 0) return false;
        
        $stmt = $this->db->prepare("
            INSERT INTO activity_logs (inventory_id, user_id, action, entity_type, entity_id, description) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$inventoryId, $userId, $action, $entityType, $entityId, $description]);
    }

    public function getLogs(int $inventoryId, int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT al.*, u.username, u.full_name, r.name as role_name
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            LEFT JOIN inventory_collaborators ic ON ic.inventory_id = al.inventory_id AND ic.user_id = al.user_id AND ic.status = 'active'
            LEFT JOIN roles r ON ic.role_id = r.id
            WHERE al.inventory_id = ?
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $inventoryId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
