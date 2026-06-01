<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/Models/ActivityLogModel.php';

use App\core\Database;
use App\Models\ActivityLogModel;

try {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $data = (isset($input['data']) && is_array($input['data'])) ? $input['data'] : [];
    $inventoryId = $input['inventory_id'] ?? ($_SESSION['active_inventory_id'] ?? null);

    if (!$inventoryId) {
        echo json_encode(['success' => false, 'message' => 'No se identificó el inventario.']);
        exit;
    }
    $inventoryId = (int)$inventoryId;

    if (empty($data)) {
        echo json_encode(['success' => false, 'message' => 'La fila está vacía.']);
        exit;
    }

    $db = Database::getInstance();

    // RBAC: verificar acceso al inventario (Owner, Admin o Employee)
    $myRole = getInventoryRole($user['id'], $inventoryId);
    if (!$myRole) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado a este inventario.']);
        exit;
    }

    $stmtInv = $db->prepare("
        SELECT t.table_name
        FROM user_tables t
        WHERE t.inventory_id = :invId
        LIMIT 1
    ");
    $stmtInv->execute([':invId' => $inventoryId]);
    $rawTableName = $stmtInv->fetchColumn();

    if (!$rawTableName) {
        throw new Exception("Tabla no encontrada para este inventario.");
    }

    $tableName = "`" . str_replace("`", "``", $rawTableName) . "`";

    $validCols = $db->query("SHOW COLUMNS FROM $tableName")->fetchAll(PDO::FETCH_COLUMN);
    $validMap = array_flip($validCols); // lookup rápido

    $insertData = [];
    foreach ($data as $col => $val) {
        if (!is_string($col)) continue;

        $col = trim($col);
        $colLower = strtolower($col);

        if ($col === '' || $colLower === 'id' || $colLower === 'created_at') continue;
        if (!isset($validMap[$col])) continue;

        $insertData[$col] = $val;
    }

    if (empty($insertData)) {
        throw new Exception("No hay datos válidos para las columnas de esta tabla.");
    }

    $cols = array_keys($insertData);

    $safeCols = [];
    $placeholders = [];
    $params = [];

    $i = 0;
    foreach ($cols as $col) {
        $safeCols[] = "`" . str_replace("`", "``", $col) . "`";
        $ph = ":v{$i}";
        $placeholders[] = $ph;
        $params[$ph] = $insertData[$col];
        $i++;
    }

    $sql = "INSERT INTO $tableName (" . implode(', ', $safeCols) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

    $stmt = $db->prepare($sql);

    if ($stmt->execute($params)) {
        $newId = $db->lastInsertId();

        $stmtGet = $db->prepare("SELECT * FROM $tableName WHERE `id` = :id");
        $stmtGet->execute([':id' => $newId]);
        $newItem = $stmtGet->fetch(PDO::FETCH_ASSOC);

        // Auditoría: registrar la creación en activity_logs
        try {
            $prodName = $newItem['nombre'] ?? $newItem['name'] ?? $newItem['producto'] ?? $newItem['description'] ?? '';
            $extraDesc = $prodName ? "Nombre: {$prodName}" : "";

            require_once __DIR__ . '/../../../src/helpers/ActivityLogger.php';
            \App\helpers\ActivityLogger::log(
                'Dashboard',
                'create',
                'product',
                (string)$newId,
                'Producto agregado (ID: ' . $newId . ')',
                $extraDesc
            );
        } catch (\Throwable $logErr) {
            error_log('ActivityLog error en add-item: ' . $logErr->getMessage());
        }

        echo json_encode(['success' => true, 'newItem' => $newItem]);
    } else {
        throw new Exception("Error SQL al insertar.");
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}