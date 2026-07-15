<?php
header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);

use App\Models\InventoryModel;
use App\core\Database;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Models/InventoryModel.php';

    if (!function_exists('getCurrentUser')) throw new Exception('Auth helper error');
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $inventoryId = $input['inventoryId'] ?? null;

    if (!$inventoryId) {
        $db = Database::getInstance();
        if (isset($_SESSION['active_inventory_id'])) {
            $inventoryId = $_SESSION['active_inventory_id'];
        } else {
            $stmt = $db->prepare("
                SELECT inventory_id 
                FROM inventory_collaborators 
                WHERE user_id = ? AND status = 'active' 
                LIMIT 1
            ");
            $stmt->execute([$user['id']]);
            $inventoryId = $stmt->fetchColumn() ?: null;
        }
    }

    if (!$inventoryId) throw new Exception("No se pudo determinar qué inventario editar.");

    // Verificar rol / permisos
    $role = getInventoryRole((int)$user['id'], (int)$inventoryId);
    if (!$role) {
        throw new Exception("Inventario no encontrado o sin permisos.");
    }

    $ownerId = getInventoryOwnerId((int)$inventoryId);
    if (!$ownerId) {
        throw new Exception("Propietario del inventario no encontrado.");
    }

    $model = new InventoryModel();
    $success = false;
    $message = '';

    switch ($action) {
        case 'copy_data':
            $source = $input['source'] ?? '';
            $target = $input['target'] ?? '';

            if (empty($source) || empty($target)) {
                throw new Exception("Faltan columnas origen o destino.");
            }

            if ($model->copyColumnData($inventoryId, $source, $target)) {
                $success = true;
                $message = "Datos importados correctamente.";
                try {
                    require_once $root . '/src/helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Configuración',
                        'update',
                        'table_column',
                        (string)$inventoryId,
                        'Copia de datos de columna',
                        "Copiados datos de la columna '$source' a la columna '$target'"
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in manage-column copy_data: ' . $logErr->getMessage());
                }
            } else {
                throw new Exception("Error al copiar. Verificá las columnas.");
            }
            break;

        case 'add_column':
            $colName = $input['columnName'] ?? '';
            if (empty($colName)) throw new Exception("Nombre vacío.");
            $model->addColumn($inventoryId, $ownerId, $colName);
            $success = true;
            $message = "Columna añadida.";
            try {
                require_once $root . '/src/helpers/ActivityLogger.php';
                \App\helpers\ActivityLogger::log(
                    'Configuración',
                    'create',
                    'table_column',
                    (string)$inventoryId,
                    "Agregó columna: $colName",
                    "Nueva columna física agregada a la tabla del inventario."
                );
            } catch (\Throwable $logErr) {
                error_log('ActivityLogger error in manage-column add_column: ' . $logErr->getMessage());
            }
            break;

        case 'drop_column':
            $colName = $input['columnName'] ?? '';
            if (empty($colName)) throw new Exception("Nombre vacío.");
            $model->dropColumn($inventoryId, $ownerId, $colName);
            $success = true;
            $message = "Columna eliminada.";
            try {
                require_once $root . '/src/helpers/ActivityLogger.php';
                \App\helpers\ActivityLogger::log(
                    'Configuración',
                    'delete',
                    'table_column',
                    (string)$inventoryId,
                    "Eliminó columna: $colName",
                    "Columna física eliminada de la tabla del inventario."
                );
            } catch (\Throwable $logErr) {
                error_log('ActivityLogger error in manage-column drop_column: ' . $logErr->getMessage());
            }
            break;

        case 'rename_column':
            $old = $input['oldName'] ?? '';
            $new = $input['newName'] ?? '';
            if (empty($old) || empty($new)) throw new Exception("Nombres inválidos.");
            $model->renameColumn($inventoryId, $ownerId, $old, $new);
            $success = true;
            $message = "Columna renombrada.";
            try {
                require_once $root . '/src/helpers/ActivityLogger.php';
                \App\helpers\ActivityLogger::log(
                    'Configuración',
                    'update',
                    'table_column',
                    (string)$inventoryId,
                    "Renombró columna '$old' a '$new'",
                    "Cambio de nombre de columna física."
                );
            } catch (\Throwable $logErr) {
                error_log('ActivityLogger error in manage-column rename_column: ' . $logErr->getMessage());
            }
            break;

        default:
            http_response_code(400);
            throw new Exception("Acción no válida: $action");
    }

    echo json_encode(['success' => $success, 'message' => $message]);

} catch (Exception $e) {
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