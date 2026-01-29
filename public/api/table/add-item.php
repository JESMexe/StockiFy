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

    // Obtenemos datos y el ID de inventario seguro
    $data = $input['data'] ?? [];
    $inventoryId = $input['inventory_id'] ?? $_SESSION['active_inventory_id'] ?? null;

    if (!$inventoryId) {
        echo json_encode(['success'=>false, 'message'=>'No se identificó el inventario.']); exit;
    }

    if (empty($data)) {
        echo json_encode(['success'=>false, 'message'=>'La fila está vacía.']); exit;
    }

    $db = Database::getInstance();

    // 1. Obtener Nombre de la Tabla (Verificando propiedad)
    $stmtInv = $db->prepare("
        SELECT t.table_name 
        FROM user_tables t
        JOIN inventories i ON t.inventory_id = i.id
        WHERE i.id = :invId AND i.user_id = :uid
    ");
    $stmtInv->execute([':invId' => $inventoryId, ':uid' => $user['id']]);
    $rawTableName = $stmtInv->fetchColumn();

    if (!$rawTableName) throw new Exception("Inventario no encontrado.");

    $tableName = "`" . str_replace("`", "``", $rawTableName) . "`";

    // 2. Limpieza de columnas (Solo insertar columnas que existen)
    $validCols = $db->query("SHOW COLUMNS FROM $tableName")->fetchAll(PDO::FETCH_COLUMN);

    $insertData = [];
    foreach ($data as $col => $val) {
        // Ignoramos ID y created_at, y verificamos que la columna exista
        if (in_array($col, $validCols) && $col !== 'id' && $col !== 'created_at') {
            $insertData[$col] = $val;
        }
    }

    if (empty($insertData)) {
        throw new Exception("No hay datos válidos para las columnas de esta tabla.");
    }

    // 3. Construir INSERT dinámico
    $cols = array_keys($insertData);
    $placeholders = array_map(fn($c) => ":$c", $cols);
    $safeCols = array_map(fn($c) => "`" . str_replace("`", "``", $c) . "`", $cols);

    $sql = "INSERT INTO $tableName (" . implode(', ', $safeCols) . ") VALUES (" . implode(', ', $placeholders) . ")";

    $stmt = $db->prepare($sql);

    // Bind parameters
    foreach ($insertData as $col => $val) {
        $stmt->bindValue(":$col", $val);
    }

    if ($stmt->execute()) {
        $newId = $db->lastInsertId();

        // Devolver la fila completa creada
        $stmtGet = $db->prepare("SELECT * FROM $tableName WHERE id = ?");
        $stmtGet->execute([$newId]);
        $newItem = $stmtGet->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'newItem' => $newItem]);
    } else {
        throw new Exception("Error SQL al insertar.");
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}