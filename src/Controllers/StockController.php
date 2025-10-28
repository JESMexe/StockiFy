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

        // 1. Verificaciones de Seguridad y Contexto
        if (!$user || !$activeInventoryId) { /* ... error 403 ... */ return; }

        $data = json_decode(file_get_contents('php://input'), true);
        $itemId = filter_var($data['itemId'] ?? null, FILTER_VALIDATE_INT);
        $value = $data['value'] ?? null; // Puede ser el nuevo valor o la cantidad a sumar/restar
        $action = $data['action'] ?? 'set'; // 'set', 'add', 'remove'

        if (!$itemId || $value === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan datos: ID del item o valor son requeridos.']);
            return;
        }

        // Validamos la acción
        if (!in_array($action, ['set', 'add', 'remove'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción inválida.']);
            return;
        }
        // Validamos que el valor sea numérico
        if (!is_numeric($value)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El valor proporcionado debe ser numérico.']);
            return;
        }
        $numericValue = (int)$value; // Convertimos a entero


        try {
            $tableModel = new TableModel();

            // 2. Obtenemos metadatos para saber el nombre de la tabla y la columna de stock
            $metadata = $tableModel->getTableMetadata($activeInventoryId);
            if (!$metadata) { throw new Exception("Metadatos de tabla no encontrados."); }
            $tableName = $metadata['table_name'];
            $columns = json_decode($metadata['columns_json'], true);

            // Buscamos la columna de stock (insensible a mayúsculas/minúsculas)
            $stockColumnName = null;
            foreach ($columns as $col) {
                if (strcasecmp(trim($col), 'stock') === 0) {
                    $stockColumnName = trim($col); // Guardamos el nombre exacto
                    break;
                }
            }
            if (!$stockColumnName) {
                throw new Exception("No se encontró una columna 'Stock' definida para esta tabla.");
            }

            // 3. Ejecutamos la acción correspondiente
            $newStock = false;
            if ($action === 'set') {
                if ($numericValue < 0) $numericValue = 0; // No permitir setear a negativo
                $success = $tableModel->updateItemValue($tableName, $itemId, $stockColumnName, $numericValue);
                if ($success) $newStock = $numericValue;
            } else { // 'add' o 'remove'
                $amount = ($action === 'add') ? $numericValue : -$numericValue;
                $newStock = $tableModel->adjustStock($tableName, $itemId, $stockColumnName, $amount);
            }

            // 4. Devolvemos la respuesta
            if ($newStock !== false) {
                echo json_encode(['success' => true, 'newStock' => $newStock]);
            } else {
                // Si adjustStock devolvió false, lanzamos una excepción manejada abajo
                throw new Exception("No se pudo actualizar el stock.");
            }

        } catch (Exception $e) {
            // Manejo de errores específico para 'Stock insuficiente'
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