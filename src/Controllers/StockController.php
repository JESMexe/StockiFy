<?php
namespace App\Controllers;

use App\Models\TableModel;
use Exception;

class StockController
{
    /**
     * Actualiza (set, add, remove) el stock de un item específico.
     */
    public function update(): void
    {
        header('Content-Type: application/json');
        $user = getCurrentUser();
        $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

        if (!$user || !$activeInventoryId) { /* ... error 403 ... */ return; }

        $data = json_decode(file_get_contents('php://input'), true);
        $itemId = filter_var($data['itemId'] ?? null, FILTER_VALIDATE_INT);
        $value = $data['value'] ?? null;
        $action = $data['action'] ?? 'set';

        if (!$itemId || $value === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan datos: ID del item o valor son requeridos.']);
            return;
        }

        if (!in_array($action, ['set', 'add', 'remove'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción inválida.']);
            return;
        }
        if (!is_numeric($value)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El valor proporcionado debe ser numérico.']);
            return;
        }
        $numericValue = (int)$value;


        try {
            $tableModel = new TableModel();

            $metadata = $tableModel->getTableMetadata($activeInventoryId);
            if (!$metadata) { throw new Exception("Metadatos de tabla no encontrados."); }
            $tableName = $metadata['table_name'];
            $columns = json_decode($metadata['columns_json'], true);

            $stockColumnName = null;
            foreach ($columns as $col) {
                if (strcasecmp(trim($col), 'stock') === 0) {
                    $stockColumnName = trim($col);
                    break;
                }
            }
            if (!$stockColumnName) {
                throw new Exception("No se encontró una columna 'Stock' definida para esta tabla.");
            }

            $newStock = false;
            if ($action === 'set') {
                if ($numericValue < 0) $numericValue = 0; // No permitir setear a negativo
                $success = $tableModel->updateItemValue($tableName, $itemId, $stockColumnName, $numericValue);
                if ($success) $newStock = $numericValue;
            } else { // 'add' o 'remove'
                $amount = ($action === 'add') ? $numericValue : -$numericValue;
                $newStock = $tableModel->adjustStock($tableName, $itemId, $stockColumnName, $amount);
            }

            if ($newStock !== false) {
                echo json_encode(['success' => true, 'newStock' => $newStock]);
            } else {
                throw new Exception("No se pudo actualizar el stock.");
            }

        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'Stock insuficiente')) {
                http_response_code(400); // Bad Request
            } else {
                http_response_code(500); // Internal Server Error
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            error_log("Error en StockController::update: " . $e->getMessage());
        }
    }
}