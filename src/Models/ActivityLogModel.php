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

    /**
     * Registra una acción en el historial de auditoría del inventario.
     * Silencioso — no dispara ninguna notificación visible al usuario.
     *
     * @param int $inventoryId ID del inventario afectado.
     * @param int|null $userId ID del usuario que ejecutó la acción.
     * @param string $roleName Nombre del rol en el momento de la acción (snapshot).
     * @param string $action Verbo de la acción: 'create', 'update', 'delete'.
     * @param string $entityType Módulo afectado: 'product', 'sale', 'purchase', 'collaborator'.
     * @param string|null $entityId ID del elemento afectado (opcional).
     * @param string|null $description Descripción legible de la acción.
     */
    public function log(
        int $inventoryId,
        ?int $userId,
        string $roleName,
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?string $description = null
    ): bool {
        if ($inventoryId <= 0) return false;

        $section = 'Sistema';
        if ($entityType === 'product') {
            $section = 'Dashboard';
        } elseif ($entityType === 'sale') {
            $section = 'Ventas';
        } elseif ($entityType === 'purchase') {
            $section = 'Compras';
        } elseif ($entityType === 'collaborator') {
            $section = 'Colaboradores';
        }

        require_once __DIR__ . '/../helpers/ActivityLogger.php';
        return \App\helpers\ActivityLogger::log(
            $section,
            $action,
            $entityType,
            $entityId,
            $description ?: '',
            null,
            $inventoryId,
            $userId,
            $roleName
        );
    }

    /**
     * Obtiene el historial de un inventario con datos de usuario,
     * paginado y opcionalmente filtrado por tipo de entidad.
     *
     * @param int $inventoryId
     * @param int $limit
     * @param int $offset
     * @param string|null $filterEntityType Filtrar por 'product', 'sale', 'purchase', etc.
     */
    public function getLogs(
        int $inventoryId,
        int $limit = 100,
        int $offset = 0,
        ?string $filterEntityType = null
    ): array {
        $where = "al.inventory_id = ?";
        $params = [$inventoryId];

        if ($filterEntityType) {
            $where .= " AND al.entity_type = ?";
            $params[] = $filterEntityType;
        }

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare("
            SELECT 
                al.id,
                al.action,
                al.entity_type,
                al.entity_id,
                al.description,
                al.extra_description,
                al.section,
                al.role_name,
                al.created_at,
                u.username,
                u.full_name
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE {$where}
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->bindValue(1, $inventoryId, PDO::PARAM_INT);
        $paramIndex = 2;
        if ($filterEntityType) {
            $stmt->bindValue($paramIndex, $filterEntityType, PDO::PARAM_STR);
            $paramIndex++;
        }
        $stmt->bindValue($paramIndex, $limit, PDO::PARAM_INT);
        $stmt->bindValue($paramIndex + 1, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta el total de registros de un inventario (para paginación).
     */
    public function countLogs(int $inventoryId, ?string $filterEntityType = null): int
    {
        $where = "inventory_id = ?";
        $params = [$inventoryId];

        if ($filterEntityType) {
            $where .= " AND entity_type = ?";
            $params[] = $filterEntityType;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM activity_logs WHERE {$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}
