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
            // --- MANEJO DE ERRORES EMBELLECIDO ---

            // 1. Errores SQL Específicos (PDOException)
            // Verificamos si tiene errorInfo (propio de PDO) para sacar el código SQL exacto
            if (isset($e->errorInfo[1])) {
                $sqlCode = $e->errorInfo[1];

                if ($sqlCode == 1059) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "El nombre de la columna es demasiado largo. Por favor, usa menos de 64 caracteres."]);
                }
                else if ($sqlCode == 1060) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "Ya existe una columna con ese nombre en la base de datos."]);
                }
                else if ($sqlCode == 1054) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "Error de referencia: La columna solicitada no existe."]);
                }
                else if ($sqlCode == 1064) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "El nombre contiene caracteres inválidos. Usa solo letras, números y guiones bajos."]);
                }
                else if ($sqlCode == 1091) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "No se pudo borrar la columna porque no existe."]);
                }
                else if ($sqlCode == 1146) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => "Error crítico: La tabla de datos no se encuentra."]);
                }
                else if ($sqlCode == 1061) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "Ya existe un índice con ese nombre."]);
                }
                else {
                    // Otros errores SQL no mapeados
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error de Base de Datos: ' . $e->getMessage()]);
                }
            }
            // 2. Errores de Lógica (InvalidArgumentException)
            else if ($e instanceof \InvalidArgumentException) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            // 3. Errores Genéricos (Fallback)
            else {
                // Mantenemos tu chequeo de string por si acaso no viene el errorInfo
                if (str_contains($e->getMessage(), '1059')) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "El nombre de la columna es demasiado largo (Detectado por texto)."]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error inesperado: ' . $e->getMessage()]);
                    error_log("Error en TableStructureController: " . $e->getMessage());
                }
            }
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