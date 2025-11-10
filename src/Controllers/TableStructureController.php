<?php
// src/Controllers/TableStructureController.php
namespace App\Controllers;

use App\Models\InventoryModel;
use Exception;

class TableStructureController
{
    private $inventoryModel;

    public function __construct()
    {
        $this->inventoryModel = new InventoryModel();
    }

    public function handleRequest(): void
    {
        header('Content-Type: application/json');
        $user = getCurrentUser();
        $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

        if (!$user || !$activeInventoryId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acción no permitida o no hay inventario seleccionado.']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        try {
            // Llama a las funciones PRIVADAS de este mismo controlador
            switch ($action) {
                case 'add_column':
                    $this->addColumn($activeInventoryId, $user['id'], $data);
                    break;
                case 'drop_column':
                    $this->dropColumn($activeInventoryId, $user['id'], $data);
                    break;
                case 'rename_column':
                    $this->renameColumn($activeInventoryId, $user['id'], $data);
                    break;
                default:
                    throw new \InvalidArgumentException('Acción no válida.');
            }
        } catch (Exception $e) {
            // Manejo de errores
            $code = ($e instanceof \InvalidArgumentException) ? 400 : 500;
            if (str_contains($e->getMessage(), '1050') || str_contains($e->getMessage(), '42S01')) {
                $code = 409; // Conflicto (ya existe)
            } else if (str_contains($e->getMessage(), '1054')) {
                $code = 404; // No encontrado
            }
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            error_log("Error en TableStructureController: " . $e->getMessage());
        }
    }

    private function addColumn(int $inventoryId, int $userId, array $data): void
    {
        $columnName = $data['columnName'] ?? '';
        if (empty($columnName)) {
            throw new \InvalidArgumentException('El nombre de la columna es obligatorio.');
        }

        // Llama al MODELO
        $this->inventoryModel->addColumn($inventoryId, $userId, $columnName);

        echo json_encode(['success' => true, 'message' => "Columna '{$columnName}' añadida con éxito."]);
    }

    private function dropColumn(int $inventoryId, int $userId, array $data): void
    {
        $columnName = $data['columnName'] ?? '';
        if (empty($columnName)) {
            throw new \InvalidArgumentException('El nombre de la columna es obligatorio.');
        }

        // Llama al MODELO
        $this->inventoryModel->dropColumn($inventoryId, $userId, $columnName);

        echo json_encode(['success' => true, 'message' => "Columna '{$columnName}' eliminada con éxito."]);
    }

    private function renameColumn(int $inventoryId, int $userId, array $data): void
    {
        // 1. Lee los datos DESDE el array $data
        $oldName = $data['oldName'] ?? '';
        $newName = $data['newName'] ?? '';

        if (empty($oldName) || empty($newName)) {
            throw new \InvalidArgumentException('Los nombres antiguo y nuevo son obligatorios.');
        }

        // 2. ✅ LLAMA AL MODELO (que ya hace ambas cosas: ALTER TABLE + actualizar JSON)
        $this->inventoryModel->renameColumn($inventoryId, $userId, $oldName, $newName);

        // 3. Devuelve un mensaje de éxito
        echo json_encode([
            'success' => true,
            'message' => "Columna '{$oldName}' renombrada a '{$newName}'."
        ]);
    }
}