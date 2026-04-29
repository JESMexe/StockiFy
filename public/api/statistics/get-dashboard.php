<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

require_once dirname(__DIR__, 3) . '/src/core/Database.php';
require_once dirname(__DIR__, 3) . '/src/Models/AnalyticsModel.php';

use App\Models\AnalyticsModel;

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = $_SESSION['user_id'] ?? null;
$inventoryId = $_SESSION['active_inventory_id'] ?? ($_SESSION['inventory_id'] ?? null);

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $model = new AnalyticsModel();

    $financials = $model->getFinancialTotals($userId, $inventoryId);

    $inventoryVal = $model->getInventoryValuation($userId, $inventoryId);

    $chartData = $model->getChartData($userId, $inventoryId);

    $topProducts = $model->getTopProducts($userId, $inventoryId);

    $paymentDist = $model->getPaymentDistribution($userId, $inventoryId);

    $currencyDist = $model->getCurrencyDistribution($userId, $inventoryId);

    $topClients = $model->getTopClients($userId, $inventoryId);

    $peakHours = $model->getPeakHours($userId, $inventoryId);

    $topSellers = $model->getTopSellers($userId, $inventoryId);

    echo json_encode([
        'success' => true,
        'financials' => $financials,
        'inventory_value' => $inventoryVal,
        'chart_data' => $chartData,
        'top_products' => $topProducts,
        'payment_distribution' => $paymentDist,
        'currency_distribution' => $currencyDist,
        'top_clients' => $topClients,
        'peak_hours' => $peakHours,
        'top_sellers' => $topSellers
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}