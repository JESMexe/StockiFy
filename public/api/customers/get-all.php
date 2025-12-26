<?php
header('Content-Type: application/json');

// Reporte de errores OFF para producción (evita romper JSON)
ini_set('display_errors', 0);
error_reporting(E_ALL);

use App\Models\CustomerModel;

try {
    // Definir raíz del proyecto (asumiendo public/api/customers/get-all.php)
    $projectRoot = dirname(__DIR__, 3);

    require_once $projectRoot . '/vendor/autoload.php';
    require_once $projectRoot . '/src/helpers/auth_helper.php';
    require_once $projectRoot . '/src/Models/CustomerModel.php';

    if (!function_exists('getCurrentUser')) {
        throw new Exception('Auth helper no cargado.');
    }

    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    $model = new CustomerModel();
    $customers = $model->getAll($user['id'], $_GET['order'] ?? 'desc');

    echo json_encode(['success' => true, 'customers' => $customers]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error 500: ' . $e->getMessage()]);
}