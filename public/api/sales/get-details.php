<?php
// public/api/sales/get-details.php
header('Content-Type: application/json');

// Ajuste de rutas para llegar a la raíz (subir 3 niveles desde public/api/sales)
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/src/helpers/auth_helper.php';
require_once dirname(__DIR__, 3) . '/src/Models/SalesModel.php';

use App\Models\SalesModel;

// 1. Verificación de Auth
try {
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

    // 2. Usar el Modelo para obtener los datos correctos (Tablas nuevas en Inglés)
    $model = new SalesModel();
    $data = $model->getDetails($saleId, $user['id']);

    if (!$data || empty($data['sale'])) {
        echo json_encode(['success' => false, 'message' => 'Venta no encontrada o acceso denegado']);
        exit;
    }

    // 3. Estructurar la respuesta para JS
    // sales.js espera: res.sale.items y res.sale.payments
    $sale = $data['sale'];
    $sale['items'] = $data['items'] ?? [];
    $sale['payments'] = $data['payments'] ?? [];

    echo json_encode([
        'success' => true,
        'sale' => $sale
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
?>