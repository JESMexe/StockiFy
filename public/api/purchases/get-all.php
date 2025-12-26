<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Models/PurchaseModel.php';

use App\Models\PurchaseModel;

$user = getCurrentUser();
if (!$user) { echo json_encode(['success'=>false]); exit; }

$model = new PurchaseModel();
$purchases = $model->getHistory($user['id'], $_GET['order'] ?? 'desc');

echo json_encode(['success' => true, 'purchases' => $purchases]);