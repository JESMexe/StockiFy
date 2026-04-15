<?php
header('Content-Type: application/json');
ini_set('display_errors', 0); error_reporting(E_ALL);

use App\core\Database;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/core/Database.php';

    session_start(); // Asegurar sesión iniciada

    if (!function_exists('getCurrentUser')) throw new Exception('Auth error');
    $user = getCurrentUser();

    $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

    if (!$user || !$activeInventoryId) {
        echo json_encode(['success'=>false, 'message'=>'No hay inventario seleccionado.']);
        exit;
    }

    $userId = $user['id'];
    $db = Database::getInstance();

    $customers = [];
    try {
        $stmt = $db->prepare("SELECT id, full_name FROM customers WHERE user_id = ? AND inventory_id = ? ORDER BY full_name ASC");
        $stmt->execute([$userId, $activeInventoryId]);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* Ignorar si no existe tabla */ }

    $employees = [];
    try {
        $stmt = $db->prepare("SELECT id, full_name FROM employees WHERE user_id = ? AND inventory_id = ? ORDER BY full_name ASC");
        $stmt->execute([$userId, $activeInventoryId]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* Ignorar */ }

    $paymentMethods = [];
    try {
        $stmtPM = $db->prepare("SELECT id, name, surcharge, currency FROM payment_methods WHERE user_id = ? AND is_active = 1");
        $stmtPM->execute([$userId]);
        $paymentMethods = $stmtPM->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* Ignorar */ }

    $products = [];

    $stmtInv = $db->prepare("SELECT id, preferences FROM inventories WHERE id = ? AND user_id = ?");
    $stmtInv->execute([$activeInventoryId, $userId]);
    $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if ($inv) {
        $prefs = json_decode($inv['preferences'] ?? '{}', true);
        $mapping = $prefs['mapping'] ?? [];

        $colName = $mapping['name'] ?? null;
        $colPrice = $mapping['sale_price'] ?? null; // Precio de VENTA
        $colCost = $mapping['receipt_price'] ?? null; // Precio de COSTO (compra)
        $colStock = $mapping['stock'] ?? null;
        $colCode = $mapping['code'] ?? null; // Si tienes código de barras mapeado

        $stmtTable = $db->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
        $stmtTable->execute([$inv['id']]);
        $tableRow = $stmtTable->fetch(PDO::FETCH_ASSOC);

        if ($tableRow) {
            $tableName = $tableRow['table_name'];
            $safeTable = "`" . str_replace("`", "``", $tableName) . "`";

            $query = "SELECT * FROM $safeTable";
            $stmtProd = $db->query($query);

            while ($row = $stmtProd->fetch(PDO::FETCH_ASSOC)) {
                $finalName = ($colName && !empty($row[$colName])) ? $row[$colName] : "Producto #" . ($row['id']??'?');
                $finalPrice = ($colPrice && isset($row[$colPrice])) ? (float)$row[$colPrice] : 0;
                $finalCost = ($colCost && isset($row[$colCost])) ? (float)$row[$colCost] : null;
                $finalStock = ($colStock && isset($row[$colStock])) ? (float)$row[$colStock] : 0; // float por si es kg
                $finalCode = ($colCode && isset($row[$colCode])) ? $row[$colCode] : null;

                $currency = $row['_meta_currency_sale'] ?? 'ARS';

                $searchString = strtolower(implode(' ', array_values($row)));

                $products[] = [
                    'id' => $row['id'],
                    'name' => $finalName,
                    'price' => $finalPrice,
                    'cost_price' => $finalCost,
                    'stock' => $finalStock,
                    'currency' => $currency,
                    'code' => $finalCode,
                    'search_data' => $searchString
                ];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'inventory_id' => $activeInventoryId,
        'customers' => $customers,
        'employees' => $employees,
        'payment_methods' => $paymentMethods,
        'products' => $products
    ]);

} catch (Throwable $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>