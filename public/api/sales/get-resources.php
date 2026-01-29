<?php
// public/api/sales/get-resources.php
header('Content-Type: application/json');
ini_set('display_errors', 0); error_reporting(E_ALL);

use App\core\Database;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/core/Database.php';

    if (!function_exists('getCurrentUser')) throw new Exception('Auth error');
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    $userId = $user['id'];
    $db = Database::getInstance();

    // 1. CLIENTES
    $customers = [];
    $checkTable = $db->query("SHOW TABLES LIKE 'customers'");
    if ($checkTable->rowCount() > 0) {
        $stmt = $db->prepare("SELECT id, full_name FROM customers WHERE user_id = ? ORDER BY full_name ASC");
        $stmt->execute([$userId]);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. EMPLEADOS
    $employees = [];
    $checkEmp = $db->query("SHOW TABLES LIKE 'employees'");
    if ($checkEmp->rowCount() > 0) {
        $stmt = $db->prepare("SELECT id, full_name FROM employees WHERE user_id = ? ORDER BY full_name ASC");
        $stmt->execute([$userId]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. MÉTODOS DE PAGO (MODIFICADO: Agregamos 'currency')
    $paymentMethods = [];
    // Seleccionamos 'currency' explícitamente. Asumimos que la tabla ya tiene la columna como indicaste.
    $stmtPM = $db->prepare("SELECT id, name, surcharge, currency FROM payment_methods WHERE user_id = ? AND is_active = 1");
    $stmtPM->execute([$userId]);
    $paymentMethods = $stmtPM->fetchAll(PDO::FETCH_ASSOC);

    // 4. PRODUCTOS
    $products = [];
    $stmtInv = $db->prepare("SELECT id, preferences FROM inventories WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmtInv->execute([$userId]);
    $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if ($inv) {
        $prefs = json_decode($inv['preferences'] ?? '{}', true);
        $mapping = $prefs['mapping'] ?? [];
        $colName = $mapping['name'] ?? null;
        $colPrice = $mapping['sale_price'] ?? null;
        $colStock = $mapping['stock'] ?? null;

        $stmtTable = $db->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
        $stmtTable->execute([$inv['id']]);
        $tableRow = $stmtTable->fetch(PDO::FETCH_ASSOC);

        if ($tableRow) {
            $tableName = $tableRow['table_name'];
            $safeTable = "`" . str_replace("`", "``", $tableName) . "`";

            // SELECT * trae todas las columnas, incluidas las _meta
            $query = "SELECT * FROM $safeTable";
            $stmtProd = $db->query($query);

            while ($row = $stmtProd->fetch(PDO::FETCH_ASSOC)) {
                $finalName = ($colName && !empty($row[$colName])) ? $row[$colName] : "ID: " . ($row['id']??'?');
                $finalPrice = ($colPrice && isset($row[$colPrice])) ? (float)$row[$colPrice] : 0;
                $finalStock = ($colStock && isset($row[$colStock])) ? (int)$row[$colStock] : 0;

                // MODIFICADO: Capturamos la moneda original si existe
                $metaCurrency = $row['_meta_currency_sale'] ?? 'ARS';

                $searchString = implode(' ', array_values($row));

                $products[] = [
                    'id' => $row['id'],
                    'name' => $finalName,
                    'price' => $finalPrice,
                    'stock' => $finalStock,
                    'currency' => $metaCurrency, // Pasamos este dato vital al frontend
                    'search_data' => strtolower($searchString)
                ];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'customers' => $customers,
        'employees' => $employees,
        'payment_methods' => $paymentMethods,
        'products' => $products
    ]);

} catch (Throwable $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>