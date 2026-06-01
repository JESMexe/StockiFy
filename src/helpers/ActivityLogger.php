<?php
namespace App\helpers;

use App\core\Database;
use PDO;

class ActivityLogger
{
    /**
     * Registra un evento de auditoría en la tabla activity_logs.
     * Si no se especifican los parámetros de control, los resuelve a partir de la sesión activa.
     *
     * @param string $section Nombre del módulo/sección (ej: "Dashboard", "Ventas", "Configuración").
     * @param string $action Acción ejecutada: "create", "update", "delete", "login", etc.
     * @param string $entityType Tipo de entidad: "product", "sale", "purchase", "collaborator", "customer", "provider", etc.
     * @param string|null $entityId ID de la entidad afectada.
     * @param string $description Descripción principal de la acción.
     * @param string|null $extraDescription Descripción complementaria/secundaria (opcional).
     * @param int|null $overrideInventoryId ID de inventario para forzar/override.
     * @param int|null $overrideUserId ID de usuario para forzar/override.
     * @param string|null $overrideRoleName Nombre del rol para forzar/override.
     * @return bool True si se guardó con éxito, False de lo contrario.
     */
    public static function log(
        string $section,
        string $action,
        string $entityType,
        ?string $entityId,
        string $description,
        ?string $extraDescription = null,
        ?int $overrideInventoryId = null,
        ?int $overrideUserId = null,
        ?string $overrideRoleName = null
    ): bool {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $inventoryId = $overrideInventoryId !== null ? $overrideInventoryId : (int)($_SESSION['active_inventory_id'] ?? 0);
            if ($inventoryId <= 0) {
                return false;
            }

            $userId = $overrideUserId !== null ? $overrideUserId : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
            
            $roleName = $overrideRoleName;
            if ($roleName === null) {
                if ($userId) {
                    require_once __DIR__ . '/auth_helper.php';
                    $role = getInventoryRole($userId, $inventoryId);
                    $roleName = $role ? $role['name'] : 'Guest';
                } else {
                    $roleName = 'System';
                }
            }

            $db = Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO activity_logs 
                    (inventory_id, user_id, role_name, section, action, entity_type, entity_id, description, extra_description) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $inventoryId,
                $userId,
                $roleName,
                $section,
                $action,
                $entityType,
                $entityId,
                $description,
                $extraDescription
            ]);
        } catch (\Throwable $e) {
            error_log("ActivityLogger Error: " . $e->getMessage());
            return false;
        }
    }
}
