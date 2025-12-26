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
    $id = $_GET['id'] ?? null;
    if (!$user || !$id) { echo json_encode(['success'=>false]); exit; }

    $model = new ProviderModel();
    $provider = $model->getById($id, $user['id']);

    if ($provider) echo json_encode(['success'=>true, 'provider'=>$provider]);
    else echo json_encode(['success'=>false, 'message'=>'No encontrado']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}