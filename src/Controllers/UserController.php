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

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_unset();
        session_destroy();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Sesión cerrada exitosamente']);
    }

    public function updateProfile(array $data): void
    {
        header('Content-Type: application/json');
        if (session_status() === PHP_SESSION_NONE) session_start();
        require_once dirname(__DIR__) . '/helpers/auth_helper.php';
        $user = getCurrentUser();

        if (!$user) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
            return;
        }

        try {
            $userModel = new UserModel();
            $success = $userModel->updateProfile($user['id'], $data);

            if ($success) {
                if (isset($data['full_name'])) {
                    $_SESSION['user_name'] = $data['full_name'];
                }
                if (isset($data['username'])) {
                    $_SESSION['username'] = $data['username'];
                }
                echo json_encode(['success' => true, 'message' => 'Perfil actualizado']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No se pudo actualizar']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
