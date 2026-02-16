<?php
// public/api/analytics/get-cash-balance.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';

use App\core\Database;

try {
    session_start();
    $user = getCurrentUser();
    if (!$user) throw new Exception("No autorizado");

    $inventoryId = $_SESSION['active_inventory_id'] ?? null;
    if (!$inventoryId) throw new Exception("Inventario no seleccionado");

    // Recibimos el periodo: 'today', 'month', 'year'
    $period = $_GET['period'] ?? 'today';

    $db = Database::getInstance();

    // Definir rangos de fecha
    $startDate = '';
    $endDate = date('Y-m-d 23:59:59');

    if ($period === 'month') {
        $startDate = date('Y-m-01 00:00:00');
    } else if ($period === 'year') {
        $startDate = date('Y-01-01 00:00:00');
    } else {
        // Default: Hoy
        $startDate = date('Y-m-d 00:00:00');
    }

    // 1. Calcular Ventas (Ingresos)
    // TABLA: sales
    // COLUMNA MONTO: total_amount
    // FILTRO: inventory_id
    // FECHA: sale_date (Ojo, tu tabla dice sale_date, no created_at)
    $stmtSales = $db->prepare("
        SELECT SUM(total_amount) as total_sales, COUNT(*) as count_sales 
        FROM sales 
        WHERE inventory_id = ? AND sale_date BETWEEN ? AND ?
    ");
    $stmtSales->execute([$inventoryId, $startDate, $endDate]);
    $salesData = $stmtSales->fetch(PDO::FETCH_ASSOC);

    // 2. Calcular Compras (Egresos)
    // TABLA: purchases
    // COLUMNA MONTO: total
    // FILTRO: inventory_id (¡Ahora sí existe!)
    // FECHA: created_at
    $stmtPurchases = $db->prepare("
        SELECT SUM(total) as total_purchases, COUNT(*) as count_purchases 
        FROM purchases 
        WHERE inventory_id = ? AND created_at BETWEEN ? AND ?
    ");
    $stmtPurchases->execute([$inventoryId, $startDate, $endDate]);
    $purchasesData = $stmtPurchases->fetch(PDO::FETCH_ASSOC);

    $totalSales = (float)($salesData['total_sales'] ?? 0);
    $totalPurchases = (float)($purchasesData['total_purchases'] ?? 0);
    $balance = $totalSales - $totalPurchases;

    echo json_encode([
        'success' => true,
        'period' => $period,
        'range' => [
            'start' => $startDate,
            'end' => $endDate
        ],
        'data' => [
            'income' => $totalSales,
            'sales_count' => $salesData['count_sales'],
            'expenses' => $totalPurchases,
            'purchases_count' => $purchasesData['count_purchases'],
            'balance' => $balance
        ]
    ]);

} catch (Exception $e) {
    // Loguear el error real para debugging pero devolver JSON válido
    error_log("Error en Balance: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}