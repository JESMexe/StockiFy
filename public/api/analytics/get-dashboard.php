<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

use App\Models\AnalyticsModel;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Models/AnalyticsModel.php';

    if (!function_exists('getCurrentUser')) throw new Exception('Auth error');
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    $model = new AnalyticsModel();
    $userId = $user['id'];

    // 1. Totales Generales
    $financials = $model->getFinancialTotals($userId);

    // 2. Valor del Inventario
    $inventoryValue = $model->getInventoryValuation($userId);

    // 3. Datos para Gráficos
    $chartData = $model->getChartData($userId);

    // 4. Top Productos
    $topProducts = $model->getTopProducts($userId);

    echo json_encode([
        'success' => true,
        'financials' => $financials,
        'inventory_value' => $inventoryValue,
        'chart_data' => $chartData,
        'top_products' => $topProducts
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}