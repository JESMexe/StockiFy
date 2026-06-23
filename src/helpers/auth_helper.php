<?php
// src/helpers/auth_helper.php

use App\Models\UserModel;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Obtiene los datos del usuario que tiene la sesión activa.
 * ...
 */

if (!function_exists('getCurrentUser')) {
    /**
     * Obtiene los datos del usuario que tiene la sesión activa.
     *
     * @return array|null Devuelve un array con los datos del usuario si está logueado, o null si no.
     */
    function getCurrentUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $userModel = new UserModel();
        $user = $userModel->findById($_SESSION['user_id']);

        return $user ?: null;
    }
}

if (!function_exists('getInventoryRole')) {
    /**
     * Obtiene el rol activo de un usuario para un inventario específico.
     * Consulta ÚNICAMENTE inventory_collaborators (fuente de verdad del RBAC).
     * El Owner es insertado automáticamente en esta tabla al crear el inventario.
     * Devuelve ['role_id' => X, 'name' => 'RoleName'] o null si no tiene acceso activo.
     */
    function getInventoryRole(int $userId, int $inventoryId): ?array
    {
        $db = \App\core\Database::getInstance();

        $stmt = $db->prepare("
            SELECT ic.role_id, r.name
            FROM inventory_collaborators ic
            JOIN roles r ON ic.role_id = r.id
            WHERE ic.inventory_id = ? AND ic.user_id = ? AND ic.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$inventoryId, $userId]);
        $res = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $res ?: null;
    }
}

if (!function_exists('getInventoryOwnerId')) {
    /**
     * Dado el ID de un inventario, devuelve el user_id del propietario (Owner).
     * Útil para que colaboradores (Admin/Employee) puedan acceder a los datos
     * del inventario activo, que están guardados bajo el user_id del Owner.
     *
     * @param int $inventoryId
     * @return int|null El user_id del propietario, o null si no se encuentra.
     */
    function getInventoryOwnerId(int $inventoryId): ?int
    {
        $db = \App\core\Database::getInstance();
        $stmt = $db->prepare("SELECT user_id FROM inventories WHERE id = ? LIMIT 1");
        $stmt->execute([$inventoryId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? (int)$row['user_id'] : null;
    }
}

if (!function_exists('getActiveRolePermissions')) {
    /**
     * Devuelve el array de permisos del usuario activo para el inventario en sesión.
     * - Owner (role_id=1): retorna NULL (acceso total, sin restricciones).
     * - Admin/Employee: retorna el array de permisos desde inventory_role_settings.
     *   Si no hay fila en la tabla, retorna [] (ningún permiso explícito = todo permitido por defecto).
     *
     * @return array|null null = Owner (sin restricciones), array = permisos del colaborador.
     */
    function getActiveRolePermissions(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $userId      = (int)($_SESSION['user_id']      ?? 0);
        $inventoryId = (int)($_SESSION['active_inventory_id'] ?? 0);

        if (!$userId || !$inventoryId) return [];

        $role = getInventoryRole($userId, $inventoryId);
        if (!$role) return []; // Sin acceso activo

        // Owner: acceso total, no hay restricciones
        if ((int)$role['role_id'] === 1) return null;

        $db   = \App\core\Database::getInstance();

        // Si es Empleado (role_id = 3), verificar si tiene una categoría de empleado asignada y con permisos personalizados
        if ((int)$role['role_id'] === 3) {
            $stmtEmp = $db->prepare("
                SELECT ec.permissions_json 
                FROM employees e
                JOIN employee_categories ec ON e.category_id = ec.id
                WHERE e.email COLLATE utf8mb4_unicode_ci = (SELECT email COLLATE utf8mb4_unicode_ci FROM users WHERE id = ? LIMIT 1)
                  AND e.inventory_id = ?
                LIMIT 1
            ");
            $stmtEmp->execute([$userId, $inventoryId]);
            $ecRow = $stmtEmp->fetch(\PDO::FETCH_ASSOC);
            if ($ecRow && !empty($ecRow['permissions_json'])) {
                return json_decode($ecRow['permissions_json'], true) ?? [];
            }
        }

        // Admin/Employee: leer sus permisos desde la tabla general de roles
        $stmt = $db->prepare(
            "SELECT permissions_json FROM inventory_role_settings
             WHERE inventory_id = ? AND role_id = ? LIMIT 1"
        );
        $stmt->execute([$inventoryId, (int)$role['role_id']]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? (json_decode($row['permissions_json'], true) ?? []) : [];
    }
}

if (!function_exists('requireSectionAccess')) {
    /**
     * Verifica que el usuario activo tenga permiso para acceder a una sección.
     * Si no tiene permiso, responde 403 JSON y termina la ejecución.
     *
     * @param string $permissionKey  Ej: 'can_view_analytics'
     */
    function requireSectionAccess(string $permissionKey): void
    {
        $permissions = getActiveRolePermissions();

        // null = Owner, acceso total
        if ($permissions === null) return;

        // Si la key está explícitamente en false, denegar
        if (isset($permissions[$permissionKey]) && $permissions[$permissionKey] === false) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Tu rol no tiene acceso a esta sección.',
                'blocked' => true
            ]);
            exit;
        }
        // Ausente o true = permitido
    }
}