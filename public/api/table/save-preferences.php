<?php
header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);

use App\core\Database;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';

    if (!function_exists('getCurrentUser')) throw new Exception('Auth helper error');
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    $input = json_decode(file_get_contents('php://input'), true);
    
    $inventoryId = $input['inventoryId'] ?? null;
    $prefs = $input['ui_preferences'] ?? null;
    $list = $input['column_order'] ?? null;

    if (!$inventoryId) {
        if (isset($_SESSION['active_inventory_id'])) {
            $inventoryId = $_SESSION['active_inventory_id'];
        }
    }

    if (!$inventoryId) throw new Exception("No se pudo determinar qué inventario editar.");
    if (!is_array($prefs) || !is_array($list)) {
        throw new Exception("Datos de preferencias o orden inválidos.");
    }

    $db = Database::getInstance();
    
    // Verificar propiedad
    $stmtCheck = $db->prepare("SELECT id FROM inventories WHERE id = ? AND user_id = ?");
    $stmtCheck->execute([$inventoryId, $user['id']]);
    if (!$stmtCheck->fetch()) throw new Exception("Inventario no encontrado o sin permisos.");

    // Obtener actual JSON
    $stmtSel = $db->prepare("SELECT columns_json FROM user_tables WHERE inventory_id = ?");
    $stmtSel->execute([$inventoryId]);
    $oldDecoded = json_decode($stmtSel->fetchColumn(), true) ?? [];

    $newJson = [
        'list' => $list,
        'prefs' => $prefs
    ];
    
    // Si la lista vieja no era el objeto nuevo, o si habían columnas que el usuario no mandó (ejid, created_at) no las borramos, pero `list` dictará el orden de todas.
    // 'id', 'created_at', etc siempre deben existir. Si el frontend omitió los campos protegidos en `list`, los agregamos al inicio para que no falten.
    $protected = ['id', 'created_at', 'updated_at', 'inventory_id', 'user_id'];
    $finalList = [];
    foreach ($protected as $p) {
        $found = false;
        $scan = isset($oldDecoded['list']) ? $oldDecoded['list'] : (is_array($oldDecoded) ? $oldDecoded : []);
        foreach ($scan as $col) {
            if (strcasecmp((string)$col, $p) === 0) {
                $finalList[] = $col;
                $found = true;
                break;
            }
        }
        if (!$found && $p === 'id') $finalList[] = 'id';
        if (!$found && $p === 'created_at') $finalList[] = 'created_at';
    }
    
    foreach ($list as $c) {
        if (!in_array(strtolower($c), array_map('strtolower', $protected))) {
            $finalList[] = $c;
        }
    }

    $newJson['list'] = $finalList;

    $stmtUpdate = $db->prepare("UPDATE user_tables SET columns_json = ? WHERE inventory_id = ?");
    if($stmtUpdate->execute([json_encode($newJson), $inventoryId])) {
        echo json_encode(['success' => true, 'message' => 'Preferencias guardadas.']);
    } else {
        throw new Exception("Error al guardar en base de datos.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
