<?php
// public/api/sales/get-details.php
header('Content-Type: application/json');

// Ajusta las rutas según tu estructura de carpetas
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Models/SalesModel.php'; // Importante incluir el modelo

use App\Models\SalesModel;

// 1. Auth
if (session_status() === PHP_SESSION_NONE) session_start();
$user = getCurrentUser();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$saleId = $_GET['id'] ?? null;
if (!$saleId) {
    echo json_encode(['success' => false, 'message' => 'Falta ID de venta']);
    exit;
}

try {
    // 2. Usar el Modelo en lugar de SQL directo
    $model = new SalesModel();
    $result = $model->getDetails($saleId, $user['id']);

    if (!$result || !$result['sale']) {
        echo json_encode(['success' => false, 'message' => 'Venta no encontrada o acceso denegado']);
        exit;
    }

    // 3. Devolver respuesta
    echo json_encode([
        'success' => true,
        'sale' => $result['sale'],
        'items' => $result['items'],
        'payments' => $result['payments'] ?? []
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>