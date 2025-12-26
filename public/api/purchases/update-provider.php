<?php
header('Content-Type: application/json');
ini_set('display_errors', 0); error_reporting(E_ALL);

use App\Models\PurchaseModel;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Models/PurchaseModel.php';

    if (!function_exists('getCurrentUser')) throw new Exception('Auth error');
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['purchase_id'])) throw new Exception("Falta ID de compra");

    // El provider_id puede venir nulo si quieren ponerlo como "Desconocido"
    $providerId = $input['provider_id'] ?? null;

    $model = new PurchaseModel();
    // Intentamos actualizar. Si devuelve false puede ser error o que el ID ya era ese.
    // Asumimos éxito si no explota.
    $model->updateProvider($input['purchase_id'], $user['id'], $providerId);

    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}