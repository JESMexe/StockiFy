<?php
/**
 * bulk-toggle-visibility.php
 * ============================================================
 * Activa o desactiva la visibilidad pública de TODOS los productos.
 * Requiere sesión activa con acceso al inventario.
 *
 * POST /api/catalog/bulk-toggle-visibility.php
 * Body (JSON):
 * {
 *   "inventory_id": 3,
 *   "visible": true
 * }
 * ============================================================
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/bootstrap.php';
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

$inventoryId = (int)($body['inventory_id'] ?? 0);
$visible     = !empty($body['visible']) ? 1 : 0;

if (!$inventoryId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'inventory_id es requerido.']);
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

    // Actualizar todos los productos de esa tabla dinámica
    $stmtUpdate = $pdo->prepare("UPDATE {$safeTable} SET public_visible = ?");
    $stmtUpdate->execute([$visible]);
    $affectedRows = $stmtUpdate->rowCount();

    echo json_encode([
        'success'      => true,
        'visible'      => (bool)$visible,
        'affected'     => $affectedRows,
        'message'      => $visible 
            ? "Se publicaron todos los productos ({$affectedRows}) en el catálogo." 
            : "Se ocultaron todos los productos ({$affectedRows}) del catálogo.",
    ]);

} catch (Exception $e) {
    error_log("bulk-toggle-visibility.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
}
