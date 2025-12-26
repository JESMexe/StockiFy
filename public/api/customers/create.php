<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Models/CustomerModel.php';

use App\Models\CustomerModel;

$user = getCurrentUser();
if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

$input = json_decode(file_get_contents('php://input'), true);

// Validación básica: Solo Nombre es obligatorio
if (empty($input['name'])) {
    echo json_encode(['success'=>false, 'message'=>'El nombre es obligatorio']);
    exit;
}

$model = new CustomerModel();
$id = $model->createCustomer($user['id'], $input);

if ($id) {
    echo json_encode(['success' => true, 'message' => 'Cliente creado', 'id' => $id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al crear cliente']);
}