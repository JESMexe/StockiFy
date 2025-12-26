<?php
header('Content-Type: application/json');
ini_set('display_errors', 0); error_reporting(E_ALL);
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/src/helpers/auth_helper.php';
require_once dirname(__DIR__, 3) . '/src/Models/PaymentMethodModel.php';
use App\Models\PaymentMethodModel;

$user = getCurrentUser();
if (!$user) { echo json_encode(['success'=>false]); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['name'])) { echo json_encode(['success'=>false, 'message'=>'Nombre vacío']); exit; }

$data = [
    'name'      => $input['name'],
    'type'      => $input['type'] ?? 'Other',
    'currency'  => $input['currency'] ?? 'ARS',
    'surcharge' => (float)($input['surcharge'] ?? 0)
];

$model = new PaymentMethodModel();
$id = $model->create($user['id'], $data);

echo json_encode(['success' => (bool)$id, 'id' => $id]);
?>