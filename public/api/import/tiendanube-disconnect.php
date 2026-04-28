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

$input = json_decode(file_get_contents('php://input'), true);
$inventoryId = $input['inventory_id'] ?? $_SESSION['active_inventory_id'] ?? null;

if (!$inventoryId) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
    exit;
}

try {
    $db = Database::getInstance();
    $stmt = $db->prepare("UPDATE inventories SET tiendanube_token = NULL, tiendanube_store_id = NULL WHERE id = ? AND user_id = ?");
    $stmt->execute([$inventoryId, $user['id']]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
