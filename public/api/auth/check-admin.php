<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';


try{
    if (!getCurrentUser() || !isset($_SESSION['active_inventory_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No autorizado o inventario no activo.']);
        return; // Detiene la ejecución antes de que PHP lance un Notice HTML.
    }

    $user = getCurrentUser();
    $isAdmin = $user['is_admin'] == 1;

    $response = ['success' => true, 'isAdmin' => $isAdmin];

    echo json_encode($response, JSON_NUMERIC_CHECK);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Ha ocurrido un error interno']);
}
