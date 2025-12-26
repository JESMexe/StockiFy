<?php
// public/api/sales/update-customer.php
header('Content-Type: application/json');
ini_set('display_errors', 0); error_reporting(E_ALL);

use App\Models\SalesModel;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Models/SalesModel.php';

    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false]); exit; }

    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['sale_id'])) throw new Exception("Falta ID");

    $model = new SalesModel();
    $model->updateCustomer($input['sale_id'], $user['id'], $input['client_id'] ?? null);

    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}