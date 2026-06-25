<?php
/**
 * check-slug.php
 * ============================================================
 * Verifica si un slug está disponible para el catálogo.
 * Requiere sesión activa (comerciante logueado).
 *
 * GET /api/catalog/check-slug.php?slug=XYZ&inventory_id=N
 *
 * Respuesta:
 *  { available: true }  → el slug está libre
 *  { available: false } → el slug ya está en uso
 *  { error: "..." }     → slug con formato inválido
 * ============================================================
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

header('Content-Type: application/json; charset=utf-8');

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado.']);
    exit;
}

$slug        = strtolower(trim($_GET['slug'] ?? ''));
$inventoryId = (int)($_GET['inventory_id'] ?? 0);

// Validar formato: solo minúsculas, números y guiones. 3–100 chars.
if (!preg_match('/^[a-z0-9][a-z0-9\-]{1,98}[a-z0-9]$/', $slug) && !preg_match('/^[a-z0-9]{3,100}$/', $slug)) {
    http_response_code(400);
    echo json_encode([
        'success'   => false,
        'available' => false,
        'error'     => 'Slug inválido. Usá solo letras minúsculas, números y guiones (mínimo 3 caracteres).',
    ]);
    exit;
}

try {
    $pdo = Database::getInstance();

    // Buscar si existe otro inventario con ese slug
    // (excluyendo el inventario actual del mismo usuario, que puede guardar el mismo slug)
    $stmt = $pdo->prepare(
        "SELECT id FROM inventories
         WHERE catalog_slug = ? AND id != ?
         LIMIT 1"
    );
    $stmt->execute([$slug, $inventoryId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'   => true,
        'available' => empty($existing),
        'slug'      => $slug,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
}
