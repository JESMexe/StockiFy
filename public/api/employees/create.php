<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

use App\Models\EmployeeModel;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Models/EmployeeModel.php';

    if (!function_exists('getCurrentUser')) throw new Exception('Auth error');
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['name'])) { echo json_encode(['success'=>false, 'message'=>'Nombre obligatorio']); exit; }

    $model = new EmployeeModel();
    $id = $model->createEmployee($user['id'], $input['name']);

    if ($id) echo json_encode(['success'=>true, 'id'=>$id]);
    else echo json_encode(['success'=>false, 'message'=>'Error al crear']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}