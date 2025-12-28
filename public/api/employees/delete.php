<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 3) . '/src/helpers/auth_helper.php';
require_once dirname(__DIR__, 3) . '/src/Models/EmployeeModel.php';

use App\Models\EmployeeModel;

try {
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id'])) {
        echo json_encode(['success'=>false, 'message'=>'ID faltante']); exit;
    }

    $model = new EmployeeModel();
    $success = $model->deleteEmployee($input['id'], $user['id']);

    echo json_encode(['success' => $success]);

} catch (Throwable $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}