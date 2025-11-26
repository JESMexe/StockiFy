<?php
// src/Controllers/InventoryController.php
namespace App\Controllers;

require_once __DIR__ . '/../helpers/auth_helper.php';

use App\Models\InventoryModel;
use App\Models\TableModel;
use Exception;

class InventoryController
{

    public function create(): void
    {

        header('Content-Type: application/json');
        $user = getCurrentUser();

        if (!$user) {
            http_response_code(401);
            //echo json_encode(['success' => false, 'message' => 'No autorizado.']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        $dbName = $data['dbName'] ?? null;
        $columns = $data['columns'] ?? null;
        $tablePreferences = $data['preferences'] ?? [];

        if (empty($dbName) || empty($columns)) {
            http_response_code(400);
            //echo json_encode(['success' => false, 'message' => 'El nombre y las columnas son obligatorios.']);
            return;
        }

        try {
            $inventoryModel = new InventoryModel();
            $columnsArray = explode(',', $columns);

            $creationResult = $inventoryModel->createInventoryAndTable(
                $dbName,
                $user['id'],
                $dbName,
                $columnsArray,
                $tablePreferences
            );
            $newInventoryId = $creationResult['id'];
            $tableName = $creationResult['tableName'];

            $_SESSION['active_inventory_id'] = $newInventoryId;

            $importStatusMsg = "";

            error_log("Verificando datos de importación pendientes..."); // DEBUG 1
            if (isset($_SESSION['pending_import_data']) && !empty($_SESSION['pending_import_data'])) {
                try {
                    error_log("Iniciando importación post-creación para tabla: " . $tableName);

                    $tableModel = new TableModel();
                    $preparedData = $_SESSION['pending_import_data'];

                    // IMPORTANTE: Validar que preparedData no esté vacío
                    if (count($preparedData) > 0) {
                        $overwrite = $_SESSION['pending_import_overwrite'] ?? false;

                        // Intentar insertar
                        $insertedRows = $tableModel->bulkInsertData($tableName, $preparedData, $overwrite);

                        $importStatusMsg = " Se importaron {$insertedRows} registros correctamente.";
                    }

                } catch (Exception $importError) {
                    // Si falla la importación, SOLO registramos el error, pero NO detenemos el script
                    error_log("Error NO FATAL en importación: " . $importError->getMessage());
                    $importStatusMsg = " La tabla se creó, pero hubo un error importando los datos: " . $importError->getMessage();
                } finally {
                    // Limpiar sesión SIEMPRE para no liberar memoria y evitar bucles
                    unset($_SESSION['pending_import_data']);
                    unset($_SESSION['pending_import_overwrite']);
                }
            }
            else
            {
                error_log("No se encontraron datos de importación pendientes en la sesión."); // DEBUG No Data
            }

            echo json_encode([
                'success' => true,
                'message' => "¡Base de datos '{$dbName}' creada!" . $importStatusMsg
            ]);


        } catch (Exception $e) {
            if (str_contains($e->getMessage(), '42S01') || (isset($e->errorInfo[1]) && ($e->errorInfo[1] == 1050 || $e->errorInfo[1] == 1062))) {
                http_response_code(409); // Conflict
                echo json_encode(['success' => false, 'message' => 'Ya existe una base de datos con ese nombre.']);
            }
            else if ($e instanceof \InvalidArgumentException) {
                http_response_code(400); // Bad Request
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Ocurrió un error inesperado al crear la tabla.']);
                error_log("Error en InventoryController::create: " . $e->getMessage());
            }
            if (str_contains($e->getMessage(), '42S01') || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1050)) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Ya existe una base de datos con ese nombre.']);
            } else {
                http_response_code(500); // Internal Server Error
                echo json_encode(['success' => false, 'message' => 'Error crítico: ' . $e->getMessage()]);
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

    public function delete(): void
    {
        header('Content-Type: application/json');
        $user = getCurrentUser();
        $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

        if (!$user || !$activeInventoryId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acción no permitida o no hay inventario seleccionado.']);
            return;
        }


        try {
            $inventoryModel = new InventoryModel();

            $success = $inventoryModel->deleteInventoryAndData($activeInventoryId, $user['id']);

            if ($success) {
                unset($_SESSION['active_inventory_id']);
                echo json_encode(['success' => true, 'message' => 'Base de datos eliminada con éxito.']);
            } else {
                throw new Exception("No se pudo completar la eliminación.");
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
        }
    }
}