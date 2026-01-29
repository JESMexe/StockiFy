<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';

use App\core\Database;

try {
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'Sesión expirada']); exit; }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['id']) || !isset($input['data'])) {
        echo json_encode(['success'=>false, 'message'=>'Datos incompletos']); exit;
    }

    $id = $input['id'];
    $data = $input['data'];
    $meta = $input['meta'] ?? [];

    // CORRECCIÓN FINAL:
    // 1. Buscamos 'inventory_id' que manda el JS (gracias al cambio en TableController)
    // 2. Si no, buscamos 'active_inventory_id' que es como TU sistema llama a la sesión.
    $inventoryId = $input['inventory_id'] ?? $_SESSION['active_inventory_id'] ?? null;

    if (!$inventoryId) {
        // Log para depuración extrema si vuelve a fallar
        error_log("UpdateRow Fail. Input: " . print_r($input, true) . " Session: " . print_r($_SESSION, true));
        echo json_encode(['success'=>false, 'message'=>'Error: No se identifica el inventario activo.']);
        exit;
    }

    $db = Database::getInstance();

    // Validar y obtener nombre de tabla
    $stmtTable = $db->prepare("
        SELECT t.table_name 
        FROM user_tables t
        JOIN inventories i ON t.inventory_id = i.id
        WHERE i.id = :invId AND i.user_id = :uid
        LIMIT 1
    ");
    $stmtTable->execute([':invId' => $inventoryId, ':uid' => $user['id']]);
    $rawTableName = $stmtTable->fetchColumn();

    if (!$rawTableName) {
        throw new Exception("Tabla no encontrada o acceso denegado.");
    }

    $tableName = "`" . str_replace("`", "``", $rawTableName) . "`";

    // Limpieza de datos
    $cleanData = [];
    foreach ($data as $k => $v) {
        if ($k !== 'undefined' && $k !== 'null' && trim($k) !== '') $cleanData[$k] = $v;
    }
    if (empty($cleanData)) { echo json_encode(['success'=>true, 'message'=>'Sin cambios']); exit; }

    // Update Query
    $setClause = [];
    $params = [':id' => $id];
    $allData = array_merge($cleanData, $meta);

    foreach ($allData as $col => $val) {
        $safeCol = "`" . str_replace("`", "``", $col) . "`";

        // Auto-crear columnas meta
        if (strpos($col, '_meta_') === 0) {
            try { $db->exec("ALTER TABLE $tableName ADD COLUMN $safeCol VARCHAR(10) DEFAULT 'ARS'"); } catch (Exception $e) {}
        }

        $setClause[] = "$safeCol = :val_$col";
        $params[":val_$col"] = $val;
    }

    $sql = "UPDATE $tableName SET " . implode(', ', $setClause) . " WHERE id = :id";
    $stmt = $db->prepare($sql);

    if ($stmt->execute($params)) {
        $stmtGet = $db->prepare("SELECT * FROM $tableName WHERE id = :id");
        $stmtGet->execute([':id' => $id]);
        echo json_encode(['success' => true, 'updatedItem' => $stmtGet->fetch(PDO::FETCH_ASSOC)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error SQL']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}