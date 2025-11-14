<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $providerID = $data['id'];

    $pdo = Database::getInstance();

    if (!getCurrentUser() || !isset($_SESSION['active_inventory_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No autorizado o inventario no activo.']);
        return; // Detiene la ejecución antes de que PHP lance un Notice HTML.
    }

    $user = getCurrentUser();
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("UPDATE providers SET full_name = ?, email = ?, phone = ?,
                       address = ? WHERE id = ?");
    $stmt->execute([$data['name'], $data['email'], $data['phone'], $data['address'], $providerID]);
    $response = ['success' => true];
} catch (Exception $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response, JSON_NUMERIC_CHECK);
