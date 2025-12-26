<?php
header('Content-Type: application/json');
ini_set('display_errors', 0); error_reporting(E_ALL);
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/src/helpers/auth_helper.php';
require_once dirname(__DIR__, 3) . '/src/Models/PaymentMethodModel.php';
use App\Models\PaymentMethodModel;

$user = getCurrentUser();
if (!$user) { echo json_encode(['success'=>false]); exit; }

$model = new PaymentMethodModel();
$methods = $model->getAll($user['id']);
echo json_encode(['success'=>true, 'methods'=>$methods]);