<?php
// src/Controllers/InventoryController.php
namespace App\Controllers;

use App\Models\InventoryModel;
use App\Models\TableModel;
use Exception;

class InventoryController
{
    // src/Controllers/InventoryController.php

    public function create(): void
    {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

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
            $columnsArray = explode(',', $data['columns']);

            // 1. Creamos inventario y tabla
            $creationResult = $inventoryModel->createInventoryAndTable(
                $data['dbName'], $user['id'], $data['dbName'], $columnsArray
            );
            $newInventoryId = $creationResult['id'];
            $tableName = $creationResult['tableName'];

            // 2. Actualizamos la sesión
            $_SESSION['active_inventory_id'] = $newInventoryId;

            // ---- NUEVO: LÓGICA DE IMPORTACIÓN POST-CREACIÓN ----
            $importMessage = ""; // Mensaje adicional opcional
            error_log("Verificando datos de importación pendientes..."); // DEBUG 1
            if (isset($_SESSION['pending_import_data'])) {
                error_log("Verificando datos de importación pendientes..."); // DEBUG 1
                try {
                    $tableModel = new TableModel(); // Necesitamos TableModel aquí
                    $preparedData = $_SESSION['pending_import_data'];
                    $overwrite = $_SESSION['pending_import_overwrite'] ?? false;
                    error_log("Llamando a bulkInsertData para tabla: " . $tableName); // DEBUG 3

                    // Llamamos a una nueva función (que crearemos) para insertar los datos
                    $insertedRows = $tableModel->bulkInsertData($tableName, $preparedData, $overwrite);
                    error_log("bulkInsertData completado. Filas insertadas: " . $insertedRows); // DEBUG 4

                    $importMessage = " e importadas {$insertedRows} filas.";

                } catch (Exception $importError) {
                    error_log("ERROR durante la importación post-creación para tabla {$tableName}: " . $importError->getMessage()); // DEBUG Error
                    // Si la importación falla DESPUÉS de crear la tabla, solo registramos el error
                    error_log("Error durante la importación post-creación para tabla {$tableName}: " . $importError->getMessage());
                    $importMessage = " (pero falló la importación de datos preparados).";
                } finally {
                    // Limpiamos los datos de la sesión SIEMPRE
                    unset($_SESSION['pending_import_data']);
                    unset($_SESSION['pending_import_overwrite']);
                    error_log("Datos pendientes de sesión eliminados."); // DEBUG 5
                }
            }
            else
            {
                error_log("No se encontraron datos de importación pendientes en la sesión."); // DEBUG No Data
            }

            // Si todo va bien, devolvemos éxito
            echo json_encode([
                'success' => true,
                'message' => "¡Tabla '{$data['dbName']}' creada con éxito!"
            ]);

        } catch (Exception $e) {
            // Manejo de errores unificado y limpio
            // Primero, el caso específico de "tabla ya existe" (por si IF NOT EXISTS falla o hay otro conflicto)
            if (str_contains($e->getMessage(), '42S01') || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1050)) {
                http_response_code(409); // Conflict
                echo json_encode(['success' => false, 'message' => 'Ya existe una base de datos con ese nombre.']);
            }
            // Caso específico de columnas inválidas
            else if ($e instanceof \InvalidArgumentException) {
                http_response_code(400); // Bad Request
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            // Cualquier otro error de base de datos o inesperado
            else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Ocurrió un error inesperado al crear la tabla.']);
                error_log("Error en InventoryController::create: " . $e->getMessage()); // Registramos el error real
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

    // src/Controllers/InventoryController.php

// ... (después de select()) ...

    public function delete(): void
    {
        header('Content-Type: application/json');
        $user = getCurrentUser();
        $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

        // 1. Verifico usuario y si hay un inventario activo (que es el que se va a borrar)
        if (!$user || !$activeInventoryId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acción no permitida o no hay inventario seleccionado.']);
            return;
        }

        // No necesitamos recibir el ID del body, usamos el de la sesión

        try {
            $inventoryModel = new InventoryModel();

            // 2. Llamo al modelo para eliminar, pasando el ID activo y el ID del usuario para verificar
            $success = $inventoryModel->deleteInventoryAndData($activeInventoryId, $user['id']);

            if ($success) {
                // 3. Limpio la sesión si la eliminación fue exitosa
                unset($_SESSION['active_inventory_id']);
                echo json_encode(['success' => true, 'message' => 'Base de datos eliminada con éxito.']);
            } else {
                // Esto no debería pasar si el modelo lanza excepciones, pero por si acaso
                throw new Exception("No se pudo completar la eliminación.");
            }

        } catch (Exception $e) {
            http_response_code(500); // O 403/404 según el mensaje
            echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
        }
    }
}