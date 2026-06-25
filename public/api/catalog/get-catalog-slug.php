<?php
/**
 * get-catalog-slug.php
 * ============================================================
 * Devuelve el slug del catálogo y si está activo para un inventario.
 * Requiere sesión activa con acceso al inventario.
 *
 * GET /api/catalog/get-catalog-slug.php?inventory_id=N
 * ============================================================
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/bootstrap.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

header('Content-Type: application/json; charset=utf-8');

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado.']);
    exit;
}

$inventoryId = (int)($_GET['inventory_id'] ?? 0);
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

    $stmt = $pdo->prepare("SELECT catalog_slug, catalog_active FROM inventories WHERE id = ? LIMIT 1");
    $stmt->execute([$inventoryId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Inventario no encontrado.']);
        exit;
    }

    echo json_encode([
        'success'        => true,
        'catalog_slug'   => $row['catalog_slug'],
        'catalog_active' => (bool)$row['catalog_active']
    ]);

} catch (Exception $e) {
    error_log("get-catalog-slug.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
}
