<?php
// public/api/sales/get-all.php
header('Content-Type: application/json');

// Manejo de errores para evitar respuestas HTML en caso de fallo
ini_set('display_errors', 0);
error_reporting(E_ALL);

use App\Models\SalesModel;

try {
    require_once __DIR__ . '/../../../vendor/autoload.php';
    require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
    require_once __DIR__ . '/../../../src/Models/SalesModel.php';

    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    $userId = $user['id'];
    $order = $_GET['order'] ?? 'desc';

    $salesModel = new SalesModel();

    // CORRECCIÓN: Usamos 'getAll' que es el nombre real en el SalesModel.php
    $sales = $salesModel->getAll($userId, $order);

    echo json_encode(['success' => true, 'sales' => $sales]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}