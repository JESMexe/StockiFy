<?php
namespace App\Controllers;

use App\Models\UserModel;
use App\Models\InventoryModel;

class UserController
{
    /**
     * Obtiene y devuelve el perfil del usuario actualmente logueado.
     */
    public function getProfile(): void
    {
        header('Content-Type: application/json');
        $user = getCurrentUser();

        if (!$user) {
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
            return;
        }

        $inventoryModel = new InventoryModel();
        // Uso findByUserId para obtener los inventarios del usuario.
        $inventories = $inventoryModel->findByUserId($user['id']);

        $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

        // Devuelvo una respuesta exitosa con los datos del usuario
        echo json_encode([
            'success' => true,
            'user' => $user,
            'databases' => $inventories,
            'activeInventoryId' => $activeInventoryId
        ]);
    }
}