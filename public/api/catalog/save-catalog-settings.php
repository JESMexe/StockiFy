<?php
/**
 * save-catalog-settings.php
 * ============================================================
 * Guarda la configuración del Catálogo Online de un inventario.
 * Requiere sesión activa con rol Owner o Admin.
 *
 * POST /api/catalog/save-catalog-settings.php
 * Body (JSON):
 * {
 *   "inventory_id": 3,
 *   "catalog_active": true,
 *   "catalog_slug": "muebles-lopez",
 *   "whatsapp": "5491123456789",
 *   "instagram": "@muebles_lopez",
 *   "address": "Av. Corrientes 1234, CABA",
 *   "show_exact_stock": true,
 *   "show_price": true
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

// Leer body JSON
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Body inválido.']);
    exit;
}

$inventoryId   = (int)($body['inventory_id'] ?? 0);
$catalogActive = !empty($body['catalog_active']) ? 1 : 0;
$slug          = strtolower(trim($body['catalog_slug'] ?? ''));

if (!$inventoryId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'inventory_id requerido.']);
    exit;
}

// Validar rol del usuario en este inventario (solo Owner y Admin)
$role = getInventoryRole((int)$user['id'], $inventoryId);
if (!$role || !in_array((int)$role['role_id'], [1, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No tenés permisos para modificar este catálogo.']);
    exit;
}

// Validar slug si se activa el catálogo
if ($catalogActive && $slug !== '') {
    if (!preg_match('/^[a-z0-9][a-z0-9\-]{1,98}[a-z0-9]$/', $slug) && !preg_match('/^[a-z0-9]{3,100}$/', $slug)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Slug inválido. Solo letras minúsculas, números y guiones (mín. 3 caracteres).']);
        exit;
    }
}

// Construir JSON de settings de catálogo (solo info pública)
$catalogSettings = [
    'whatsapp'         => trim($body['whatsapp'] ?? ''),
    'instagram'        => trim($body['instagram'] ?? ''),
    'address'          => trim($body['address'] ?? ''),
    'logo_url'         => trim($body['logo_url'] ?? ''),
    'show_exact_stock' => !empty($body['show_exact_stock']),
    'show_price'       => isset($body['show_price']) ? (bool)$body['show_price'] : true,
    'show_action_button' => isset($body['show_action_button']) ? (bool)$body['show_action_button'] : true,
    'button_text'      => trim($body['button_text'] ?? ''),
    'button_link'      => trim($body['button_link'] ?? ''),
    'button_icon'      => trim($body['button_icon'] ?? ''),
    'button_color'     => trim($body['button_color'] ?? ''),
    'theme_color'      => trim($body['theme_color'] ?? 'accent-color'),
    'theme_pattern'    => trim($body['theme_pattern'] ?? 'dots'),
    // Colorimetría
    'color_bg'         => trim($body['color_bg']      ?? '#F4F4F6'),
    'color_pattern'    => trim($body['color_pattern'] ?? 'rgba(0,0,0,0.08)'),
    'color_card'       => trim($body['color_card']    ?? '#FFFFFF'),
    'color_accent'     => trim($body['color_accent']  ?? 'theme'),
    'color_label'      => trim($body['color_label']   ?? '#8A8A8A'),
    'color_title'      => trim($body['color_title']   ?? '#1A1A1A'),
    'color_price'      => trim($body['color_price']   ?? '#1A1A1A'),
];

try {
    $pdo = Database::getInstance();

    // Verificar que el inventario pertenece al usuario (o tiene rol Owner/Admin verificado arriba)
    $stmtCheck = $pdo->prepare("SELECT id, user_id FROM inventories WHERE id = ? LIMIT 1");
    $stmtCheck->execute([$inventoryId]);
    $inv = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$inv) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Inventario no encontrado.']);
        exit;
    }

    // Verificar unicidad del slug (excluyendo este inventario)
    if ($slug !== '') {
        $stmtSlug = $pdo->prepare("SELECT id FROM inventories WHERE catalog_slug = ? AND id != ? LIMIT 1");
        $stmtSlug->execute([$slug, $inventoryId]);
        if ($stmtSlug->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'El slug ya está en uso por otro catálogo.']);
            exit;
        }
    }

    // Guardar
    $slugValue = $slug !== '' ? $slug : null;
    $stmtUpdate = $pdo->prepare(
        "UPDATE inventories
         SET catalog_active = ?, catalog_slug = ?, catalog_settings = ?
         WHERE id = ?"
    );
    $stmtUpdate->execute([
        $catalogActive,
        $slugValue,
        json_encode($catalogSettings, JSON_UNESCAPED_UNICODE),
        $inventoryId,
    ]);

    $publicUrl = $slugValue
        ? rtrim($_SERVER['HTTP_HOST'] ?? 'stockify.com.ar', '/') . '/catalogo/' . $slugValue
        : null;

    echo json_encode([
        'success'    => true,
        'message'    => $catalogActive ? 'Catálogo activado correctamente.' : 'Configuración guardada.',
        'public_url' => $publicUrl,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("save-catalog-settings.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
}
