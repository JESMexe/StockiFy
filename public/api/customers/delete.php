<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

use App\Models\CustomerModel;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Models/CustomerModel.php';

    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id'])) {
        echo json_encode(['success'=>false, 'message'=>'ID faltante']); exit;
    }

    $model = new CustomerModel();
    $success = $model->deleteCustomer($input['id'], $user['id']);

    if($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo eliminar. Verifica si tiene ventas asociadas.']);
    }

} catch (Throwable $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}