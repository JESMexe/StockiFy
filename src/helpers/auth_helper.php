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
     * Devuelve ['role_id' => X, 'name' => 'RoleName'] o null si no tiene acceso.
     */
    function getInventoryRole(int $userId, int $inventoryId): ?array
    {
        $db = \App\core\Database::getInstance();
        
        // 1. Verificar si es el dueño absoluto (Legacy fallback / Seguridad extra)
        $stmtOwner = $db->prepare("SELECT id FROM inventories WHERE id = ? AND user_id = ?");
        $stmtOwner->execute([$inventoryId, $userId]);
        if ($stmtOwner->fetch()) {
            return ['role_id' => 1, 'name' => 'Owner'];
        }

        // 2. Buscar en colaboradores activos
        $stmtCollab = $db->prepare("
            SELECT r.id as role_id, r.name 
            FROM inventory_collaborators ic
            JOIN roles r ON ic.role_id = r.id
            WHERE ic.inventory_id = ? AND ic.user_id = ? AND ic.status = 'active'
        ");
        $stmtCollab->execute([$inventoryId, $userId]);
        $res = $stmtCollab->fetch(PDO::FETCH_ASSOC);

        return $res ?: null;
    }
}