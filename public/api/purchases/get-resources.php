<?php
header('Content-Type: application/json');
ini_set('display_errors', 0); error_reporting(E_ALL);

use App\core\Database;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/core/Database.php';

    session_start();

    if (!function_exists('getCurrentUser')) throw new Exception('Auth helper error');
    $user = getCurrentUser();

    $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;
    if (!$user || !$activeInventoryId) {
        echo json_encode(['success'=>false, 'message'=>'Inventario no seleccionado']);
        exit;
    }

    $userId = $user['id'];
    $db = Database::getInstance();

    $providers = [];
    try {
        $stmt = $db->prepare("SELECT id, full_name FROM providers WHERE user_id = ? AND inventory_id = ? ORDER BY full_name ASC");
        $stmt->execute([$userId, $activeInventoryId]);
        $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

    $products = [];

    $stmtInv = $db->prepare("SELECT id, preferences FROM inventories WHERE id = ? AND user_id = ?");
    $stmtInv->execute([$activeInventoryId, $userId]);
    $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if ($inv) {
        $prefs = json_decode($inv['preferences'] ?? '{}', true);
        $mapping = $prefs['mapping'] ?? [];

        $colName = $mapping['name'] ?? null;
        $colPrice = $mapping['buy_price'] ?? $mapping['receipt_price'] ?? null;
        $colStock = $mapping['stock'] ?? null;

        $stmtTable = $db->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
        $stmtTable->execute([$inv['id']]);
        $tableRow = $stmtTable->fetch(PDO::FETCH_ASSOC);

        if ($tableRow) {
            $tableName = $tableRow['table_name'];
            $safeTable = "`" . str_replace("`", "``", $tableName) . "`";

            $query = "SELECT * FROM $safeTable";
            $stmtProd = $db->query($query);

            while ($row = $stmtProd->fetch(PDO::FETCH_ASSOC)) {
                $finalName = ($colName && !empty($row[$colName])) ? $row[$colName] : "Item #" . ($row['id']??'?');

                $finalPrice = 0;
                $canBuy = true;
                $priceError = null;

                if ($colPrice && isset($row[$colPrice])) {
                    $finalPrice = (float)$row[$colPrice];
                } else {
                    $canBuy = false;
                    $priceError = "Sin Costo asignado";
                }

                $finalStock = ($colStock && isset($row[$colStock])) ? (float)$row[$colStock] : 0;

                $currencyBuy = $row['_meta_currency_buy'] ?? 'ARS';

                $products[] = [
                    'id' => $row['id'],
                    'name' => $finalName,
                    'price' => $finalPrice,
                    'stock' => $finalStock,
                    'can_buy' => $canBuy,
                    'price_error' => $priceError,
                    'currency' => $currencyBuy // Usamos 'currency' genérico para estandarizar
                ];
            }
        }
    }

    echo json_encode(['success' => true, 'providers' => $providers, 'products' => $products]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>