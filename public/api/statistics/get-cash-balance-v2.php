<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/Models/AnalyticsModel.php';

use App\core\Database;
use App\Models\AnalyticsModel;

try {
    session_start();
    date_default_timezone_set('America/Argentina/Buenos_Aires');

    $user = getCurrentUser();
    if (!$user) throw new Exception("No autorizado");

    $inventoryId = $_SESSION['active_inventory_id'] ?? null;
    if (!$inventoryId) throw new Exception("Inventario no seleccionado");

    $period = $_GET['period'] ?? 'today';
    $db = Database::getInstance();
    $db->query("SET time_zone = '-03:00'");

    $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
    $now = new DateTime('now', $tz);

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

    // 1. Totales de Ventas y Compras
    $stmtS = $db->prepare("SELECT SUM(total_amount) as total, COUNT(*) as qty FROM sales WHERE inventory_id = ? AND sale_date >= ? AND sale_date < ?");
    $stmtS->execute([$inventoryId, $startDate, $endDate]);
    $sRes = $stmtS->fetch(PDO::FETCH_ASSOC);

    $stmtP = $db->prepare("SELECT SUM(total) as total, COUNT(*) as qty FROM purchases WHERE inventory_id = ? AND created_at >= ? AND created_at < ?");
    $stmtP->execute([$inventoryId, $startDate, $endDate]);
    $pRes = $stmtP->fetch(PDO::FETCH_ASSOC);

    $totalS = (float)($sRes['total'] ?? 0);
    $countS = (int)($sRes['qty'] ?? 0);
    $totalP = (float)($pRes['total'] ?? 0);
    $countP = (int)($pRes['qty'] ?? 0);

    // 2. Stock Valorizado (Usando la tabla dinámica del inventario)
    $analyticsModel = new AnalyticsModel();
    $valuation = $analyticsModel->getInventoryValuation($user['id'], $inventoryId);

    // 3. Rankings (Usando sale_details)
    $stTopP = $db->prepare("
        SELECT sd.product_name as name, SUM(sd.quantity) as qty 
        FROM sale_details sd 
        JOIN sales s ON sd.sale_id = s.id 
        WHERE s.inventory_id = ? AND s.sale_date >= ? AND s.sale_date < ? 
        GROUP BY sd.product_name ORDER BY qty DESC LIMIT 3
    ");
    $stTopP->execute([$inventoryId, $startDate, $endDate]);
    $topP = $stTopP->fetchAll(PDO::FETCH_ASSOC);

    $stTopC = $db->prepare("
        SELECT c.full_name as name, SUM(s.total_amount) as total 
        FROM sales s 
        JOIN customers c ON s.customer_id = c.id 
        WHERE s.inventory_id = ? AND s.sale_date >= ? AND s.sale_date < ? 
        GROUP BY c.id ORDER BY total DESC LIMIT 3
    ");
    $stTopC->execute([$inventoryId, $startDate, $endDate]);
    $topC = $stTopC->fetchAll(PDO::FETCH_ASSOC);

    // 4. Listas de Ventas y Gastos recientes
    $stRS = $db->prepare("SELECT id, total_amount, sale_date, 'sale' as type FROM sales WHERE inventory_id = ? AND sale_date >= ? AND sale_date < ? ORDER BY sale_date DESC LIMIT 10");
    $stRS->execute([$inventoryId, $startDate, $endDate]);
    $recentS = $stRS->fetchAll(PDO::FETCH_ASSOC);

    $stRP = $db->prepare("SELECT id, total as total_amount, created_at as sale_date, 'purchase' as type FROM purchases WHERE inventory_id = ? AND created_at >= ? AND created_at < ? ORDER BY created_at DESC LIMIT 10");
    $stRP->execute([$inventoryId, $startDate, $endDate]);
    $recentP = $stRP->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'income' => $totalS,
            'expenses' => $totalP,
            'balance' => $totalS - $totalP,
            'operationCount' => $countS + $countP,
            'sales_count' => $countS,
            'valuation' => $valuation,
            'top_products' => $topP,
            'top_clients' => $topC,
            'recent_sales' => $recentS,
            'recent_purchases' => $recentP
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
