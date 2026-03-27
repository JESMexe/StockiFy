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

    session_start(); // Asegurar sesión iniciada

    if (!function_exists('getCurrentUser')) throw new Exception('Auth error');
    $user = getCurrentUser();

    // VALIDACIÓN DE INVENTARIO ACTIVO
    $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

    if (!$user || !$activeInventoryId) {
        echo json_encode(['success'=>false, 'message'=>'No hay inventario seleccionado.']);
        exit;
    }

    $userId = $user['id'];
    $db = Database::getInstance();

    // 1. CLIENTES (Filtrados por inventario)
    $customers = [];
    try {
        $stmt = $db->prepare("SELECT id, full_name FROM customers WHERE user_id = ? AND inventory_id = ? ORDER BY full_name ASC");
        $stmt->execute([$userId, $activeInventoryId]);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* Ignorar si no existe tabla */ }

    // 2. EMPLEADOS (Filtrados por inventario)
    $employees = [];
    try {
        $stmt = $db->prepare("SELECT id, full_name FROM employees WHERE user_id = ? AND inventory_id = ? ORDER BY full_name ASC");
        $stmt->execute([$userId, $activeInventoryId]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* Ignorar */ }

    // 3. MÉTODOS DE PAGO
    $paymentMethods = [];
    try {
        $stmtPM = $db->prepare("SELECT id, name, surcharge, currency FROM payment_methods WHERE user_id = ? AND is_active = 1");
        $stmtPM->execute([$userId]);
        $paymentMethods = $stmtPM->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* Ignorar */ }

    // 4. PRODUCTOS (DEL INVENTARIO ACTIVO)
    $products = [];

    // [CORRECCIÓN] Buscamos EL inventario activo, no el último creado.
    $stmtInv = $db->prepare("SELECT id, preferences FROM inventories WHERE id = ? AND user_id = ?");
    $stmtInv->execute([$activeInventoryId, $userId]);
    $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if ($inv) {
        $prefs = json_decode($inv['preferences'] ?? '{}', true);
        $mapping = $prefs['mapping'] ?? [];

        // Mapeo de columnas
        $colName = $mapping['name'] ?? null;
        $colPrice = $mapping['sale_price'] ?? null; // Precio de VENTA
        $colStock = $mapping['stock'] ?? null;
        $colCode = $mapping['code'] ?? null; // Si tienes código de barras mapeado

        // Buscar tabla dinámica
        $stmtTable = $db->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
        $stmtTable->execute([$inv['id']]);
        $tableRow = $stmtTable->fetch(PDO::FETCH_ASSOC);

        if ($tableRow) {
            $tableName = $tableRow['table_name'];
            $safeTable = "`" . str_replace("`", "``", $tableName) . "`";

            // Consultar datos de la tabla dinámica
            $query = "SELECT * FROM $safeTable";
            $stmtProd = $db->query($query);

            while ($row = $stmtProd->fetch(PDO::FETCH_ASSOC)) {
                // Obtener valores según el mapeo
                $finalName = ($colName && !empty($row[$colName])) ? $row[$colName] : "Producto #" . ($row['id']??'?');
                $finalPrice = ($colPrice && isset($row[$colPrice])) ? (float)$row[$colPrice] : 0;
                $finalStock = ($colStock && isset($row[$colStock])) ? (float)$row[$colStock] : 0; // float por si es kg
                $finalCode = ($colCode && isset($row[$colCode])) ? $row[$colCode] : null;

                // Moneda de venta (Metadata)
                $currency = $row['_meta_currency_sale'] ?? 'ARS';

                // String de búsqueda (concatena todo)
                $searchString = strtolower(implode(' ', array_values($row)));

                $products[] = [
                    'id' => $row['id'],
                    'name' => $finalName,
                    'price' => $finalPrice,
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