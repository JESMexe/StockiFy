<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/helpers/db_helper.php'; // si tenés algo así
require_once __DIR__ . '/../../../src/Models/InventoryModel.php'; // si existe

$user = getCurrentUser();
if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

$inventoryId = $_SESSION['active_inventory_id'] ?? null;
if (!$inventoryId) { echo json_encode(['success'=>false, 'message'=>'Inventario no seleccionado']); exit; }

// ----------------------------------------------------
// ⚠️ ACÁ DEPENDE DE TU PROYECTO:
// Necesitamos obtener el nombre REAL de la tabla del inventario activo.
// Puede venir de session, o de una tabla inventories, etc.
// ----------------------------------------------------

// Opción A: si ya lo guardás en sesión:
$tableName = $_SESSION['active_table_name'] ?? null;

// Opción B: si tenés un modelo que lo resuelve:
if (!$tableName) {
    // Cambiá esto por tu forma real de obtener el nombre de tabla
    // Ej: InventoryModel->getTableName($inventoryId, $user['id'])
    // $tableName = (new InventoryModel())->getTableName($inventoryId, $user['id']);
}

// Validación mínima
if (!$tableName) {
    echo json_encode(['success'=>false, 'message'=>'No se pudo determinar la tabla del inventario']);
    exit;
}

try {
    $pdo = getPDO();
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
        echo json_encode(['success'=>false, 'message'=>'Tabla inválida']);
        exit;
    }

    $stmt = $pdo->query("SELECT * FROM `$tableName` ORDER BY id DESC LIMIT 5000");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success'=>true, 'products'=>$rows]);
} catch (Throwable $e) {
    echo json_encode(['success'=>false, 'message'=>'Error interno']);
}