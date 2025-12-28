<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';

use App\core\Database;

try {
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    $input = json_decode(file_get_contents('php://input'), true);

    // ESTA ES LA VALIDACIÓN QUE ESTABA FALLANDO
    if (!isset($input['id']) || !isset($input['data'])) {
        // Log para ver qué llegó realmente si falla
        error_log("UpdateRow Error - Input recibido: " . print_r($input, true));
        echo json_encode(['success'=>false, 'message'=>'Datos incompletos']); exit;
    }

    $id = $input['id'];
    $data = $input['data'];
    $meta = $input['meta'] ?? [];

    $db = Database::getInstance();

    // 1. Obtener nombre de tabla
    $stmtTable = $db->prepare("SELECT t.table_name FROM inventories i JOIN user_tables t ON i.id = t.inventory_id WHERE i.user_id = :uid ORDER BY i.created_at DESC LIMIT 1");
    $stmtTable->execute([':uid' => $user['id']]);
    $rawTableName = $stmtTable->fetchColumn();
    if (!$rawTableName) throw new Exception("Tabla no encontrada");
    $tableName = "`" . str_replace("`", "``", $rawTableName) . "`";

    // 2. Preparar datos
    $allData = array_merge($data, $meta);
    if (empty($allData)) { echo json_encode(['success'=>true]); exit; }

    $setClause = [];
    $params = [':id' => $id];

    foreach ($allData as $col => $val) {
        $safeCol = "`" . str_replace("`", "``", $col) . "`";
        // Auto-crear columnas meta si faltan
        if (strpos($col, '_meta_') === 0) {
            try { $db->exec("ALTER TABLE $tableName ADD COLUMN $safeCol VARCHAR(10) DEFAULT 'ARS'"); } catch (Exception $e) {}
        }
        $setClause[] = "$safeCol = :val_$col";
        $params[":val_$col"] = $val;
    }

    // 3. Ejecutar
    $sql = "UPDATE $tableName SET " . implode(', ', $setClause) . " WHERE id = :id";
    $stmt = $db->prepare($sql);

    if ($stmt->execute($params)) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Error SQL']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}