<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';

use App\core\Database;

try {
    session_start();

    date_default_timezone_set('America/Argentina/Buenos_Aires');

    $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
    $now = new DateTime('now', $tz);

    $user = getCurrentUser();
    if (!$user) throw new Exception("No autorizado");

    $inventoryId = $_SESSION['active_inventory_id'] ?? null;
    if (!$inventoryId) throw new Exception("Inventario no seleccionado");

    $period = $_GET['period'] ?? 'today';
    $db = Database::getInstance();

    $db->query("SET time_zone = '-03:00'");

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

    $stmtSales = $db->prepare("
        SELECT SUM(total_amount) as total_sales, COUNT(*) as count_sales
        FROM sales
        WHERE inventory_id = ? AND sale_date >= ? AND sale_date < ?
    ");
    $stmtSales->execute([$inventoryId, $startDate, $endDate]);
    $salesData = $stmtSales->fetch(PDO::FETCH_ASSOC);

    $stmtPurchases = $db->prepare("
        SELECT SUM(total) as total_purchases, COUNT(*) as count_purchases
        FROM purchases
        WHERE inventory_id = ? AND created_at >= ? AND created_at < ?
    ");
    $stmtPurchases->execute([$inventoryId, $startDate, $endDate]);
    $purchasesData = $stmtPurchases->fetch(PDO::FETCH_ASSOC);

    $totalSales = (float)($salesData['total_sales'] ?? 0);
    $salesCount = (int)($salesData['count_sales'] ?? 0);
    $totalPurchases = (float)($purchasesData['total_purchases'] ?? 0);
    $purchasesCount = (int)($purchasesData['count_purchases'] ?? 0);
    $balance = $totalSales - $totalPurchases;

    // 1. Stock Valorizado (Costo x Stock)
    // Buscamos la configuración de columnas en 'user_tables' -> 'columns_json'
    $stmtPref = $db->prepare("SELECT columns_json FROM user_tables WHERE inventory_id = ?");
    $stmtPref->execute([$inventoryId]);
    $jsonCols = $stmtPref->fetchColumn();
    $prefData = json_decode($jsonCols ?? '{}', true);
    
    // El mapeo suele estar en la propiedad 'prefs' del JSON
    $colsMapping = $prefData['prefs'] ?? [];
    $buyCol = $colsMapping['buy_price'] ?? 'preciodecompra';
    $stockCol = $colsMapping['stock'] ?? 'stock';

    $valuation = 0;
    try {
        // Ejecutamos la suma dinámica. Envolvemos nombres de columna en backticks.
        $stmtValuation = $db->prepare("SELECT SUM(CAST(REPLACE(REPLACE(`$buyCol`, '.', ''), ',', '.') AS DECIMAL(15,2)) * CAST(`$stockCol` AS DECIMAL(15,2))) as total_valuation FROM items WHERE inventory_id = ?");
        $stmtValuation->execute([$inventoryId]);
        $valuation = (float)($stmtValuation->fetchColumn() ?? 0);
    } catch (Exception $e) {
        // Si las columnas no existen en la tabla 'items', devolvemos 0 en vez de explotar
        $valuation = 0; 
    }

    // 2. Top Productos (del periodo)
    try {
        $stmtTopProducts = $db->prepare("
            SELECT p.name, SUM(si.quantity) as qty 
            FROM sale_items si 
            JOIN products p ON si.product_id = p.id 
            JOIN sales s ON si.sale_id = s.id
            WHERE s.inventory_id = ? AND s.sale_date >= ? AND s.sale_date < ?
            GROUP BY p.id ORDER BY qty DESC LIMIT 3
        ");
        $stmtTopProducts->execute([$inventoryId, $startDate, $endDate]);
        $topProducts = $stmtTopProducts->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $topProducts = [];
    }

    // 3. Top Clientes
    try {
        $stmtTopClients = $db->prepare("
            SELECT c.name, SUM(s.total_amount) as total 
            FROM sales s 
            JOIN customers c ON s.customer_id = c.id
            WHERE s.inventory_id = ? AND s.sale_date >= ? AND s.sale_date < ?
            GROUP BY c.id ORDER BY total DESC LIMIT 3
        ");
        $stmtTopClients->execute([$inventoryId, $startDate, $endDate]);
        $topClients = $stmtTopClients->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $topClients = [];
    }

    // 4. Listado de Ventas/Gastos para el detalle
    $stmtRecentSales = $db->prepare("SELECT id, total_amount, sale_date, 'sale' as type FROM sales WHERE inventory_id = ? AND sale_date >= ? AND sale_date < ? ORDER BY sale_date DESC");
    $stmtRecentSales->execute([$inventoryId, $startDate, $endDate]);
    $recentSales = $stmtRecentSales->fetchAll(PDO::FETCH_ASSOC);

    $stmtRecentPurchases = $db->prepare("SELECT id, total as total_amount, created_at as sale_date, 'purchase' as type FROM purchases WHERE inventory_id = ? AND created_at >= ? AND created_at < ? ORDER BY created_at DESC");
    $stmtRecentPurchases->execute([$inventoryId, $startDate, $endDate]);
    $recentPurchases = $stmtRecentPurchases->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'period' => $period,
        'data' => [
            'income' => $totalSales,
            'expenses' => $totalPurchases,
            'balance' => $balance,
            'operationCount' => $salesCount + $purchasesCount,
            'sales_count' => $salesCount,
            'purchases_count' => $purchasesCount,
            'valuation' => $valuation,
            'top_products' => $topProducts,
            'top_clients' => $topClients,
            'recent_sales' => $recentSales,
            'recent_purchases' => $recentPurchases
        ]
    ]);

} catch (Exception $e) {
    ob_clean(); // Limpiar cualquier salida previa
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}