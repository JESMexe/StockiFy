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