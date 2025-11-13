<?php
namespace App\Controllers;

use App\Models\UserModel;
use App\Models\InventoryModel;

class UserController
{
    public function getProfile(): void
    {
        header('Content-Type: application/json');

        $user = getCurrentUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Sesión no válida o expirada.'
            ]);
            return;
        }

        $inventoryModel = new InventoryModel();
        $inventories = $inventoryModel->findByUserId((int)$user['id']);

        $hasAnyDatabase   = !empty($inventories);
        $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

        $displayName = $user['full_name']
            ?? $user['username']
            ?? (isset($user['email']) ? explode('@', $user['email'])[0] : 'Usuario');

        echo json_encode([
            'success'         => true,
            'user'            => [
                'id'    => (int)$user['id'],
                'name'  => $displayName,
                'email' => $user['email'] ?? null,
            ],
            'databases'       => $inventories,
            'hasAnyDatabase'  => $hasAnyDatabase,
            'activeInventoryId' => $activeInventoryId,
        ]);
    }
}
