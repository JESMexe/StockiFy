<?php
namespace App\Controllers;

use App\Models\InventoryModel;

class InventoryController
{
    // src/Controllers/InventoryController.php
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

        $inventoryModel = new InventoryModel();
        $this->db->beginTransaction(); // ¡Importante para transacciones!

        try {
            // 1. Crear el registro del "inventario"
            $inventoryId = $inventoryModel->create($data['dbName'], $user['id']);
            if (!$inventoryId) {
                throw new Exception("No se pudo crear el registro del inventario.");
            }

            // 2. Crear la tabla física
            $columnsArray = explode(',', $data['columns']);
            $tableName = $inventoryModel->createTableForInventory($user['id'], $inventoryId, $data['dbName'], $columnsArray);
            if (!$tableName) {
                throw new Exception("No se pudo crear la tabla física en la base de datos.");
            }

            // 3. Si todo salió bien, confirmamos los cambios
            $this->db->commit();
            echo json_encode([
                'success' => true,
                'message' => "¡Tabla '{$tableName}' creada con éxito!"
            ]);

        } catch (Exception $e) {
            // 4. Si algo falló, revertimos todo
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}