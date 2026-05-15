<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

try {
    $pdo = Database::getInstance();

    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado.']);
        exit; // Detiene la ejecución.
    }

    $user = getCurrentUser();
    $user_id = $_SESSION['user_id'];

    $activeId = $_SESSION['active_inventory_id'] ?? null;
    $stmt = $pdo->prepare("
        SELECT i.id, i.name, r.name as role_name 
        FROM inventories i 
        JOIN inventory_collaborators ic ON i.id = ic.inventory_id 
        JOIN roles r ON ic.role_id = r.id 
        WHERE ic.user_id = ? AND ic.status = 'active'
    ");
    $stmt->execute([$user_id]);
    $inventories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Marcamos cuál es el activo para el móvil y su rol
    foreach ($inventories as &$inv) {
        $inv['is_active'] = ($inv['id'] == $activeId);
        $inv['is_owner'] = ($inv['role_name'] === 'Owner');
    }

    $response = ['verifiedInventories' => $inventories, 'success' => true];

} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}

echo json_encode($response, JSON_NUMERIC_CHECK);

