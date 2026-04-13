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
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['id']) || !isset($input['data'])) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }

    $id = (int)$input['id'];
    $data = is_array($input['data']) ? $input['data'] : [];
    $meta = (isset($input['meta']) && is_array($input['meta'])) ? $input['meta'] : [];

    $inventoryId = $input['inventory_id'] ?? ($_SESSION['active_inventory_id'] ?? null);
    if (!$inventoryId) {
        error_log("UpdateRow Fail. Input: " . print_r($input, true) . " Session: " . print_r($_SESSION, true));
        echo json_encode(['success' => false, 'message' => 'Error: No se identifica el inventario activo.']);
        exit;
    }
    $inventoryId = (int)$inventoryId;

    $db = Database::getInstance();

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

    $cleanData = [];
    foreach ($data as $k => $v) {
        if (!is_string($k)) continue;
        $kTrim = trim($k);
        if ($kTrim === '' || $kTrim === 'undefined' || $kTrim === 'null') continue;
        $cleanData[$kTrim] = $v;
    }

    $allData = array_merge($cleanData, $meta);
    if (empty($allData)) {
        echo json_encode(['success' => true, 'message' => 'Sin cambios']);
        exit;
    }

    $setClause = [];
    $params = [':id' => $id];

    $i = 0;
    foreach ($allData as $col => $val) {
        if (!is_string($col)) continue;
        $colLower = strtolower(trim($col));
        if ($colLower === 'id') continue;
        $col = trim($col);
        if ($col === '' || $col === 'undefined' || $col === 'null') continue;

        $safeCol = "`" . str_replace("`", "``", $col) . "`";

        if (strpos($col, '_meta_') === 0) {
            try {
                $db->exec("ALTER TABLE $tableName ADD COLUMN $safeCol VARCHAR(10) DEFAULT 'ARS'");
            } catch (Throwable $e) {
            }
        }

        $ph = ":v{$i}";
        $setClause[] = "$safeCol = $ph";
        $params[$ph] = $val;

        $i++;
    }

    if (empty($setClause)) {
        echo json_encode(['success' => true, 'message' => 'Sin cambios']);
        exit;
    }

    $sql = "UPDATE $tableName SET " . implode(', ', $setClause) . " WHERE `id` = :id";
    $stmt = $db->prepare($sql);

    if ($stmt->execute($params)) {
        $stmtGet = $db->prepare("SELECT * FROM $tableName WHERE `id` = :id");
        $stmtGet->execute([':id' => $id]);

        echo json_encode([
            'success' => true,
            'updatedItem' => $stmtGet->fetch(PDO::FETCH_ASSOC)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error SQL']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}