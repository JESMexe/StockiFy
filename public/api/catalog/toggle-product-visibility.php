<?php
/**
 * toggle-product-visibility.php
 * ============================================================
 * Activa o desactiva la visibilidad pública de un producto.
 * Requiere sesión activa con acceso al inventario.
 *
 * POST /api/catalog/toggle-product-visibility.php
 * Body (JSON):
 * {
 *   "product_id": 42,
 *   "inventory_id": 3,
 *   "visible": true
 * }
 * ============================================================
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit;
}

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Body inválido.']);
    exit;
}

$productId   = (int)($body['product_id']   ?? 0);
$inventoryId = (int)($body['inventory_id'] ?? 0);
$visible     = !empty($body['visible']) ? 1 : 0;

if (!$productId || !$inventoryId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'product_id e inventory_id son requeridos.']);
    exit;
}

// Verificar acceso al inventario
$role = getInventoryRole((int)$user['id'], $inventoryId);
if (!$role) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin acceso a este inventario.']);
    exit;
}

try {
    $pdo = Database::getInstance();

    // Obtener la tabla dinámica del inventario
    $stmtTable = $pdo->prepare(
        "SELECT ut.table_name FROM user_tables ut
         JOIN inventories i ON ut.inventory_id = i.id
         WHERE ut.inventory_id = ?
         LIMIT 1"
    );
    $stmtTable->execute([$inventoryId]);
    $tableRow = $stmtTable->fetch(PDO::FETCH_ASSOC);

    if (!$tableRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Tabla de inventario no encontrada.']);
        exit;
    }

    $safeTable = "`" . str_replace("`", "``", $tableRow['table_name']) . "`";

    // Actualizar public_visible
    $stmtUpdate = $pdo->prepare("UPDATE {$safeTable} SET public_visible = ? WHERE id = ?");
    $stmtUpdate->execute([$visible, $productId]);

    if ($stmtUpdate->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Producto no encontrado.']);
        exit;
    }

    echo json_encode([
        'success'    => true,
        'visible'    => (bool)$visible,
        'product_id' => $productId,
        'message'    => $visible ? 'Producto visible en el catálogo.' : 'Producto oculto del catálogo.',
    ]);

} catch (Exception $e) {
    error_log("toggle-product-visibility.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
}
