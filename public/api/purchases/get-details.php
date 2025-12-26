<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Models/PurchaseModel.php';

use App\Models\PurchaseModel;

$user = getCurrentUser();
$id = $_GET['id'] ?? null;

if (!$user || !$id) { echo json_encode(['success'=>false]); exit; }

$model = new PurchaseModel();
$data = $model->getDetails($id, $user['id']);

if ($data) {
    echo json_encode(['success' => true, 'purchase' => $data['purchase'], 'items' => $data['items']]);
} else {
    echo json_encode(['success' => false, 'message' => 'No encontrado']);
}