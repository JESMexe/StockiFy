<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Models/CustomerModel.php';

use App\Models\CustomerModel;

$user = getCurrentUser();
$id = $_GET['id'] ?? null;

$inventoryId = $data['inventoryId'] ?? $_SESSION['active_inventory_id'];

if (!$user || !$id || !$inventoryId) { echo json_encode(['success'=>false]); exit; }

$model = new CustomerModel();
$customer = $model->getById($id, $user['id'], (int)$inventoryId);

if ($customer) {
    echo json_encode(['success' => true, 'customer' => $customer]);
} else {
    echo json_encode(['success' => false, 'message' => 'No encontrado']);
}