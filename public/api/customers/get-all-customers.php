<?php


require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;


try {
    $pdo = Database::getInstance();
    $user = getCurrentUser();
    $inventoryId = $_SESSION['active_inventory_id'] ?? null;
    if (!$user || !$inventoryId) throw new Exception('No autorizado o inventario no seleccionado');

    // RBAC: clientes pertenecen al owner del inventario
    $user_id = getInventoryOwnerId((int)$inventoryId);
    if (!$user_id) throw new Exception('Inventario no encontrado');

    $clients = $pdo->prepare("SELECT * FROM customers WHERE user_id = ? AND inventory_id = ?");
    $clients ->execute([$user_id, $inventoryId]);
    $clients = $clients->fetchAll();

    $response = ['clientList' => $clients, 'success' => true];

    header('Content-Type: application/json');
} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}
echo json_encode($response, JSON_NUMERIC_CHECK);


