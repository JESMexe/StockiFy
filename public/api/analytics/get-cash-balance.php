<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';

use App\core\Database;

try {
    session_start();

    date_default_timezone_set('America/Argentina/Buenos_Aires');

    // ✅ Fuerza timezone AR para evitar “salto” de día
    $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
    $now = new DateTime('now', $tz);

    $user = getCurrentUser();
    if (!$user) throw new Exception("No autorizado");

    $inventoryId = $_SESSION['active_inventory_id'] ?? null;
    if (!$inventoryId) throw new Exception("Inventario no seleccionado");

    $period = $_GET['period'] ?? 'today';
    $db = Database::getInstance();

    $db->query("SET time_zone = '-03:00'");

    // ✅ Rangos robustos: [start, end)
    if ($period === 'month') {
        $start = new DateTime($now->format('Y-m-01 00:00:00'), $tz);
        $end   = (clone $start)->modify('+1 month');
    } else if ($period === 'year') {
        $start = new DateTime($now->format('Y-01-01 00:00:00'), $tz);
        $end   = (clone $start)->modify('+1 year');
    } else {
        $start = new DateTime($now->format('Y-m-d 00:00:00'), $tz);
        $end   = (clone $start)->modify('+1 day');
    }

    $startDate = $start->format('Y-m-d H:i:s');
    $endDate   = $end->format('Y-m-d H:i:s');

    // 1) Ventas (Ingresos) — sale_date
    $stmtSales = $db->prepare("
        SELECT SUM(total_amount) as total_sales, COUNT(*) as count_sales
        FROM sales
        WHERE inventory_id = ? AND sale_date >= ? AND sale_date < ?
    ");
    $stmtSales->execute([$inventoryId, $startDate, $endDate]);
    $salesData = $stmtSales->fetch(PDO::FETCH_ASSOC);

    // 2) Compras (Egresos) — created_at
    $stmtPurchases = $db->prepare("
        SELECT SUM(total) as total_purchases, COUNT(*) as count_purchases
        FROM purchases
        WHERE inventory_id = ? AND created_at >= ? AND created_at < ?
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
            'end' => $endDate,
            'tz' => $tz->getName()
        ],
        'data' => [
            'income' => $totalSales,
            'sales_count' => (int)($salesData['count_sales'] ?? 0),
            'expenses' => $totalPurchases,
            'purchases_count' => (int)($purchasesData['count_purchases'] ?? 0),
            'balance' => $balance
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en Balance: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}