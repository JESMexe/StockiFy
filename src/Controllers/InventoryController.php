<?php
// src/Controllers/InventoryController.php
namespace App\Controllers;

use App\Models\InventoryModel;
use Exception;

class InventoryController
{
    public function create(): void
    {
        header('Content-Type: application/json');
        $user = getCurrentUser();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autorizado.']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['dbName']) || empty($data['columns'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El nombre y las columnas son obligatorios.']);
            return;
        }

        try {
            $inventoryModel = new InventoryModel();

            $existingInventories = $inventoryModel->findByUserId($user['id']);
            foreach ($existingInventories as $inv) {
                if (strtolower($inv['name']) === strtolower($data['dbName'])) {
                    http_response_code(409); // 409 Conflict
                    echo json_encode(['success' => false, 'message' => 'Ya tenés una Base de Datos con ese nombre.']);
                    return;
                }
            }


            $columnsArray = explode(',', $data['columns']);

            // El modelo se encarga de la transacción internamente
            $tableName = $inventoryModel->createInventoryAndTable(
                $data['dbName'],
                $user['id'],
                $data['dbName'],
                $columnsArray
            );

            // Si todo va bien, devuelvo éxito
            echo json_encode([
                'success' => true,
                'message' => "¡Tabla \"{$tableName}\" creada con éxito!"
            ]);

        } catch (Exception $e) {
            if (str_contains($e->getMessage(), '42S01')) {
                http_response_code(409); // 409 Conflict
                echo json_encode(['success' => false, 'message' => 'Ya existe una base de datos con ese nombre.']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Ocurrió un error inesperado en el servidor.']);
                // registro el error real
                error_log($e->getMessage());
            }
        }
    }

    public function select(): void
    {
        header('Content-Type: application/json');
        $user = getCurrentUser();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autorizado.']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $inventoryId = $data['inventoryId'] ?? null;

        if (!$inventoryId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID de inventario no proporcionado.']);
            return;
        }

        // Verificación de propiedad :: crucial para la seguridad
        $inventoryModel = new InventoryModel();
        $inventories = $inventoryModel->findByUserId($user['id']);
        $isOwner = false;
        foreach ($inventories as $inv) {
            if ($inv['id'] == $inventoryId) {
                $isOwner = true;
                break;
            }
        }

        if (!$isOwner) {
            http_response_code(403); // Forbidden
            echo json_encode(['success' => false, 'message' => 'No tienes permiso para acceder a este recurso.']);
            return;
        }

        // Si todo es correcto, guardo la selección en la sesión
        $_SESSION['active_inventory_id'] = $inventoryId;
        echo json_encode(['success' => true, 'message' => 'Inventario seleccionado.']);
    }
}