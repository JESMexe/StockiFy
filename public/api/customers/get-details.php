<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Models/CustomerModel.php';

use App\Models\CustomerModel;

$user = getCurrentUser();
$id = $_GET['id'] ?? null;

if (!$user || !$id) { echo json_encode(['success'=>false]); exit; }

$model = new CustomerModel();
$customer = $model->getById($id, $user['id']);

if ($customer) {
    echo json_encode(['success' => true, 'customer' => $customer]);
} else {
    echo json_encode(['success' => false, 'message' => 'No encontrado']);
}