<?php
// public/api/analytics/get-dashboard.php

// 1. CARGAR EL AUTOLOADER DE COMPOSER (¡CRÍTICO!)
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

// 2. Cargar dependencias del proyecto
require_once dirname(__DIR__, 3) . '/src/core/Database.php';
require_once dirname(__DIR__, 3) . '/src/Models/AnalyticsModel.php';

use App\Models\AnalyticsModel;

header('Content-Type: application/json');

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) session_start();

$userId = $_SESSION['user_id'] ?? null;
$inventoryId = $_SESSION['inventory_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $model = new AnalyticsModel();

    // 1. Totales Financieros (Ingresos, Gastos, Ticket Promedio)
    $financials = $model->getFinancialTotals($userId, $inventoryId);

    // 2. Valor del Inventario
    $inventoryVal = $model->getInventoryValuation($userId, $inventoryId);

    // 3. Gráfico Principal (Líneas - Flujo de Caja)
    $chartData = $model->getChartData($userId, $inventoryId);

    // 4. Top Productos
    $topProducts = $model->getTopProducts($userId, $inventoryId);

    // 5. Distribución de Pagos (Gráfico de Dona)
    $paymentDist = $model->getPaymentDistribution($userId);

    // 6. Distribución por Moneda
    $currencyDist = $model->getCurrencyDistribution($userId);

    // 7. Top Clientes
    $topClients = $model->getTopClients($userId);

    // 8. Horarios Pico
    $peakHours = $model->getPeakHours($userId);

    // 9. Top Vendedores
    $topSellers = $model->getTopSellers($userId);

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