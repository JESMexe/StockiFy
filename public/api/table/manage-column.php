<?php
// public/api/table/manage-column.php
header('Content-Type: application/json');

// Desactivar errores HTML
ini_set('display_errors', 0);
error_reporting(E_ALL);

use App\Models\InventoryModel;
use App\core\Database;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Models/InventoryModel.php';

    // 1. Auth
    if (!function_exists('getCurrentUser')) throw new Exception('Auth helper error');
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    // 2. Input
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $inventoryId = $input['inventoryId'] ?? null;

    // --- CRÍTICO: OBTENER INVENTARIO ACTIVO ---
    // Si el JS no mandó el ID (a veces pasa en acciones globales), lo buscamos.
    // Pero idealmente debería venir del frontend.
    if (!$inventoryId) {
        $db = Database::getInstance();
        // Buscamos el último inventario MODIFICADO o CREADO (Mejor puntería)
        // O buscamos si hay alguna variable de sesión 'active_inventory'
        if (isset($_SESSION['active_inventory_id'])) {
            $inventoryId = $_SESSION['active_inventory_id'];
        } else {
            // Fallback: El último creado
            $stmt = $db->prepare("SELECT id FROM inventories WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$user['id']]);
            $inv = $stmt->fetch();
            $inventoryId = $inv['id'] ?? null;
        }
    }

    if (!$inventoryId) throw new Exception("No se pudo determinar qué inventario editar.");

    $model = new InventoryModel();
    $success = false;
    $message = '';

    switch ($action) {
        // CASO 1: COPIAR DATOS (IMPORTAR)
        case 'copy_data':
            $source = $input['source'] ?? '';
            $target = $input['target'] ?? '';

            if (empty($source) || empty($target)) {
                throw new Exception("Faltan columnas origen o destino.");
            }

            // Ahora pasamos el inventoryId CORRECTO
            if ($model->copyColumnData($inventoryId, $source, $target)) {
                $success = true;
                $message = "Datos importados correctamente.";
            } else {
                throw new Exception("Error al copiar. Verificá las columnas.");
            }
            break;

        // CASO 2: AÑADIR COLUMNA
        case 'add_column':
            $colName = $input['columnName'] ?? '';
            if (empty($colName)) throw new Exception("Nombre vacío.");
            $model->addColumn($inventoryId, $user['id'], $colName);
            $success = true;
            $message = "Columna añadida.";
            break;

        // CASO 3: ELIMINAR COLUMNA
        case 'drop_column':
            $colName = $input['columnName'] ?? '';
            if (empty($colName)) throw new Exception("Nombre vacío.");
            $model->dropColumn($inventoryId, $user['id'], $colName);
            $success = true;
            $message = "Columna eliminada.";
            break;

        // CASO 4: RENOMBRAR COLUMNA
        case 'rename_column':
            $old = $input['oldName'] ?? '';
            $new = $input['newName'] ?? '';
            if (empty($old) || empty($new)) throw new Exception("Nombres inválidos.");
            $model->renameColumn($inventoryId, $user['id'], $old, $new);
            $success = true;
            $message = "Columna renombrada.";
            break;

        default:
            http_response_code(400);
            throw new Exception("Acción no válida: $action");
    }

    echo json_encode(['success' => $success, 'message' => $message]);

} catch (Exception $e) {
    // --- MANEJO DE ERRORES ---
    $errorCode = 500;
    $userMessage = 'Ocurrió un error inesperado.';

    if ($e instanceof \PDOException && isset($e->errorInfo[1])) {
        $sqlCode = $e->errorInfo[1];
        switch ($sqlCode) {
            case 1059: $errorCode = 400; $userMessage = "Nombre muy largo."; break;
            case 1060: $errorCode = 409; $userMessage = "Columna ya existe."; break;
            case 1064: $errorCode = 400; $userMessage = "Nombre inválido."; break;
            case 1091: $errorCode = 404; $userMessage = "Columna no existe."; break;
            case 1146: $errorCode = 500; $userMessage = "Tabla no encontrada."; break;
            default: $userMessage = "Error DB (" . $sqlCode . ")"; break;
        }
    } else {
        $userMessage = $e->getMessage();
        if ($e instanceof \InvalidArgumentException) $errorCode = 400;
    }

    http_response_code($errorCode);
    echo json_encode(['success' => false, 'message' => $userMessage]);
}
?>