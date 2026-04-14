<?php
header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';

use App\core\Database;

try {
    if (!function_exists('getCurrentUser')) throw new Exception('Auth helper error');

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success'=>false, 'message'=>'No autorizado']);
        exit;
    }

    $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

    if (!$activeInventoryId) {
        http_response_code(400);
        throw new Exception("No hay un inventario seleccionado en la sesión actual.");
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $db = Database::getInstance();

    $stmt = $db->prepare("SELECT id, preferences, min_stock FROM inventories WHERE id = ? AND user_id = ?");
    $stmt->execute([$activeInventoryId, $user['id']]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inv) {
        http_response_code(404);
        throw new Exception("El inventario seleccionado no existe o no te pertenece.");
    }

    $invId = $inv['id'];
    $currentPrefs = json_decode($inv['preferences'] ?? '{}', true) ?? [];

    if (isset($input['mapping'])) {
        $currentPrefs['mapping'] = $input['mapping'];
    }

    if (isset($input['features'])) {
        $currentPrefs['features'] = $input['features'];

        $minStockActive = $input['features']['min_stock'];

        $stmtUpdateFlag = $db->prepare("UPDATE inventories SET min_stock = ?, hard_gain = ? WHERE id = ?");
        $stmtUpdateFlag->execute([
            $minStockActive ? 1 : 0,
            $input['features']['gain'] ? 1 : 0,
            $invId
        ]);

        $stmtTable = $db->prepare("SELECT table_name, columns_json FROM user_tables WHERE inventory_id = ?");
        $stmtTable->execute([$invId]);
        $tableData = $stmtTable->fetch(PDO::FETCH_ASSOC);

        if ($tableData) {
            $tableName = $tableData['table_name'];
            $safeTable = "`" . str_replace("`", "``", $tableName) . "`";

            $colsMeta = json_decode($tableData['columns_json'], true) ?? [];
            $metaUpdated = false;

            if ($minStockActive) {
                $checkPhysical = $db->query("SHOW COLUMNS FROM $safeTable LIKE 'min_stock'");
                $physicalExists = $checkPhysical->rowCount() > 0;

                if (!$physicalExists) {
                    $defVal = (int)($input['features']['min_stock_val'] ?? 0);
                    $db->exec("ALTER TABLE $safeTable ADD COLUMN `min_stock` INT DEFAULT $defVal");
                }

                if (!in_array('min_stock', $colsMeta)) {
                    $colsMeta[] = 'min_stock';
                    $metaUpdated = true;
                }
            } else {
            }

            if ($metaUpdated) {
                $stmtMeta = $db->prepare("UPDATE user_tables SET columns_json = ? WHERE inventory_id = ?");
                $stmtMeta->execute([json_encode(array_values($colsMeta)), $invId]);
            }
        }
    }

    if (isset($input['visible_columns'])) {
        $currentPrefs['visible_columns'] = $input['visible_columns'];
    }

    if (isset($input['hidden_columns'])) {
        $currentPrefs['hidden_columns'] = $input['hidden_columns'];
    }

    if (isset($input['column_order'])) {
        $currentPrefs['column_order'] = $input['column_order'];
    }

    if (isset($input['column_colors'])) {
        $currentPrefs['column_colors'] = $input['column_colors'];
    }

    if (isset($input['exchange_config'])) {
        $currentPrefs['exchange_config'] = $input['exchange_config'];
    }

    $stmtSave = $db->prepare("UPDATE inventories SET preferences = ? WHERE id = ?");
    $stmtSave->execute([json_encode($currentPrefs), $invId]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    if (str_contains($e->getMessage(), '42S01') || (isset($e->errorInfo[1]) && ($e->errorInfo[1] == 1050 || $e->errorInfo[1] == 1062))) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Ya existe una columna o registro con ese nombre.']);
    }
    else if (str_contains($e->getMessage(), '42S01') || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1054)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Estás intentando asignar datos a una columna que no existe."]);
    }
    else if (str_contains($e->getMessage(), '42S01') || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1059)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "El nombre de la columna es demasiado largo."]);
    }
    else if (str_contains($e->getMessage(), '42S01') || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1060)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Ya existe una columna con ese nombre en la base de datos."]);
    }
    else if (str_contains($e->getMessage(), '42S01') || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1061)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Ya existe un índice con ese nombre."]);
    }
    else if (str_contains($e->getMessage(), '42S01') || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1064)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "El nombre de la columna contiene caracteres no válidos o palabras reservadas."]);
    }
    else if (str_contains($e->getMessage(), '42S01') || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1091)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "No se pudo encontrar la columna solicitada para modificar o borrar."]);
    }
    else if (str_contains($e->getMessage(), '42S01') || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1146)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "La tabla de la base de datos no existe o está corrupta."]);
    }
    else if (str_contains($e->getMessage(), '42S01') || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1264)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "El número ingresado es demasiado grande para esta columna."]);
    }
    else if (str_contains($e->getMessage(), '42S01') || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1265)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "El texto es demasiado largo para esta columna o el formato es incorrecto."]);
    }
    else if (str_contains($e->getMessage(), '42S01') || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1366)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Se esperaba un número entero pero se recibió texto o decimales."]);
    }
    else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Ocurrió un error inesperado en la base de datos: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    if ($e instanceof \InvalidArgumentException) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Ocurrió un error inesperado al procesar la solicitud: ' . $e->getMessage()]);
    }
}