<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

try {
    $user = getCurrentUser();
    if (!$user) throw new Exception("Usuario no autenticado");

    $saleId = $_GET['id'] ?? null;
    if (!$saleId) throw new Exception("ID no proporcionado");

    $db = Database::getInstance();

    // 1. Configuración de Tablas Dinámicas
    $stmtConfig = $db->prepare("SELECT i.preferences, t.table_name FROM inventories i JOIN user_tables t ON i.id = t.inventory_id WHERE i.user_id = ? LIMIT 1");
    $stmtConfig->execute([$user['id']]);
    $config = $stmtConfig->fetch(PDO::FETCH_ASSOC);

    if (!$config) throw new Exception("Inventario no configurado");

    $realTableName = $config['table_name'];
    $prefs = json_decode($config['preferences'], true);
    $nameColumn = $prefs['mapping']['name'] ?? 'name';

    $safeTable = "`" . str_replace("`", "``", $realTableName) . "`";
    $safeNameCol = "`" . str_replace("`", "``", $nameColumn) . "`";

    // 2. CABECERA DE VENTA
    // CORRECCIÓN IMPORTANTE:
    // Cambiamos 'LEFT JOIN users u ON s.seller_id = u.id'
    // Por 'LEFT JOIN employees e ON s.seller_id = e.id'

    $stmtSale = $db->prepare("
        SELECT 
            s.id, 
            DATE_FORMAT(s.sale_date, '%Y-%m-%d %H:%i:%s') as created_at,
            s.total_amount as total_final,
            s.commission_amount,
            s.notes,
            c.full_name as customer_name, 
            e.full_name as seller_name   -- Leemos de la tabla empleados
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN employees e ON s.seller_id = e.id  -- <--- AQUÍ ESTABA EL ERROR
        WHERE s.id = ?
    ");
    $stmtSale->execute([$saleId]);
    $sale = $stmtSale->fetch(PDO::FETCH_ASSOC);

    if (!$sale) throw new Exception("Venta no encontrada");

    // 3. ITEMS
    $sqlItems = "
        SELECT 
            si.quantity, 
            si.unit_price as price, 
            si.total_price as subtotal,
            COALESCE(p.$safeNameCol, si.product_name, 'Producto Eliminado') as product_name
        FROM sale_items si
        LEFT JOIN $safeTable p ON si.inventory_id = p.id 
        WHERE si.sale_id = ?
    ";

    $stmtItems = $db->prepare($sqlItems);
    $stmtItems->execute([$saleId]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // 4. PAGOS
    try {
        $stmtPayments = $db->prepare("
            SELECT sp.amount, pm.name as payment_method_name 
            FROM sale_payments sp
            LEFT JOIN payment_methods pm ON sp.payment_method_id = pm.id
            WHERE sp.sale_id = ?
        ");
        $stmtPayments->execute([$saleId]);
        $payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $payments = [];
    }

    $sale['items'] = $items;
    $sale['payments'] = $payments;

    echo json_encode(['success' => true, 'sale' => $sale]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $e->getMessage()]);
}