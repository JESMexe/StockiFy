<?php
namespace App\Controllers;

use App\Models\TableModel;
use App\Models\InventoryModel;
use Exception;

class TableController
{
    public function get(): void
    {
        header('Content-Type: application/json');
        $user = getCurrentUser();
        $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;


        if (!$user || !$activeInventoryId) {
            http_response_code(403); // Forbidden
            echo json_encode(['success' => false, 'message' => 'Acceso denegado o no se ha seleccionado un inventario.']);
            return;
        }

        try {
            $tableModel = new TableModel();
            $inventoryModel = new InventoryModel();
            $metadata = $tableModel->getTableMetadata($activeInventoryId);

            $inventoryName = null;
            $inventories = $inventoryModel->findByUserId($user['id']);
            foreach($inventories as $inv){
                if($inv['id'] == $activeInventoryId){
                    $inventoryName = $inv['name'];
                    break;
                }
            }

            if (!$metadata || $inventoryName === null) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'No se encontraron los datos de la tabla para este inventario.']);
                return;
            }

            $data = $tableModel->getData($metadata['table_name']);

            $columnsDecoded = json_decode($metadata['columns_json'], true);
            if ($columnsDecoded === null && json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(['debug_error' => 'Fallo al decodificar columns_json', 'json_error' => json_last_error_msg()]); exit;
            }
            $cleanColumns = array_map('trim', $columnsDecoded);

            $responseArray = [
                'success' => true,
                'inventoryName' => $inventoryName,
                'columns' => $cleanColumns,
                'data' => $data,
                'inventoryId' => $activeInventoryId,
            ];
            $jsonData = json_encode($responseArray);

            if ($jsonData === false) {
                echo json_encode(['debug_error' => 'Fallo en json_encode', 'json_last_error' => json_last_error_msg()]);
                exit;
            }

            echo $jsonData;

        } catch (\PDOException | Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()]);
            error_log("Error en TableController::get: " . $e->getMessage());
        }
    }

    public function addItem(): void
    {
        header('Content-Type: application/json');
        $user = getCurrentUser();
        $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

        if (!$user || !$activeInventoryId) { /* ... error 403 ... */ return; }

        $newItemData = json_decode(file_get_contents('php://input'), true);
        if (empty($newItemData) || !is_array($newItemData)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos inválidos o vacíos.']);
            return;
        }

        try {
            $tableModel = new TableModel();

            $metadata = $tableModel->getTableMetadata($activeInventoryId);
            if (!$metadata) { throw new Exception("Metadatos de tabla no encontrados."); }
            $tableName = $metadata['table_name'];

            $insertedRow = $tableModel->insertItem($tableName, $newItemData);

            if ($insertedRow) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Fila añadida con éxito.',
                    'newItem' => $insertedRow
                ]);
            } else {
                throw new Exception("No se pudo añadir la fila a la base de datos.");
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno al añadir fila: ' . $e->getMessage()]);
            error_log("Error en TableController::addItem: " . $e->getMessage());
        }
    }

    public function updateItem(): void
    {
        header('Content-Type: application/json');
        $user = getCurrentUser();
        $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

        if (!$user || !$activeInventoryId) { /* ... error 403 ... */ return; }

        $data = json_decode(file_get_contents('php://input'), true);
        $itemId = $data['itemId'] ?? null;
        $dataToUpdate = $data['dataToUpdate'] ?? null;

        if (!$itemId || !is_array($dataToUpdate) || empty($dataToUpdate)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos inválidos. Se requiere "itemId" y "dataToUpdate".']);
            return;
        }

        try {
            $tableModel = new TableModel();

            $metadata = $tableModel->getTableMetadata($activeInventoryId);
            if (!$metadata) { throw new Exception("Metadatos de tabla no encontrados."); }
            $tableName = $metadata['table_name'];

            $updatedItem = $tableModel->updateItemRow($tableName, $itemId, $dataToUpdate);

            if ($updatedItem) {
                echo json_encode(['success' => true, 'updatedItem' => $updatedItem]);
            } else {
                throw new Exception("No se pudo actualizar la fila.");
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()]);
        }
    }
}