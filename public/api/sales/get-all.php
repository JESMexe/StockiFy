<?php
// public/api/sales/get-all.php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Models/SalesModel.php';

use App\Models\SalesModel;

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$userId = $user['id'];
$order = $_GET['order'] ?? 'desc';

$salesModel = new SalesModel();

// CORRECCIÓN: Usamos el método en español que definimos en el Modelo
$sales = $salesModel->obtenerHistorial($userId, $order);

echo json_encode(['success' => true, 'sales' => $sales]);
