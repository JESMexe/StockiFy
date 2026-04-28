<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
$root = dirname(__DIR__, 3);
require_once $root . '/vendor/autoload.php';
require_once $root . '/src/helpers/auth_helper.php';
require_once $root . '/src/core/Database.php';

use App\core\Database;

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$inventoryId = $_GET['inventory_id'] ?? $_SESSION['active_inventory_id'] ?? null;
if (!$inventoryId) {
    echo json_encode(['success' => false, 'message' => 'Inventario no seleccionado']);
    exit;
}

try {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT tiendanube_token, tiendanube_store_id FROM inventories WHERE id = ? AND user_id = ?");
    $stmt->execute([$inventoryId, $user['id']]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);

    $connected = !empty($inv['tiendanube_token']) && !empty($inv['tiendanube_store_id']);

    echo json_encode([
        'success' => true,
        'connected' => $connected,
        'store_id' => $inv['tiendanube_store_id'] ?? null
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
