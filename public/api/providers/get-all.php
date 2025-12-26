<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

use App\Models\ProviderModel;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Models/ProviderModel.php';



    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false]); exit; }

    $model = new ProviderModel();
    $providers = $model->getAll($user['id'], $_GET['order'] ?? 'desc');

    echo json_encode(['success'=>true, 'providers'=>$providers]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}