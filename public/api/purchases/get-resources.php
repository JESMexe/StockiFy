<?php
// public/api/purchases/get-resources.php
header('Content-Type: application/json');

// Desactivar errores HTML
ini_set('display_errors', 0);
error_reporting(E_ALL);

use App\core\Database;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/core/Database.php';

    // 1. Auth
    if (!function_exists('getCurrentUser')) throw new Exception('Auth helper error');
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    $userId = $user['id'];
    $db = Database::getInstance();

    // 2. OBTENER PROVEEDORES (Estándar)
    $providers = [];
    $checkTable = $db->query("SHOW TABLES LIKE 'providers'");
    if ($checkTable->rowCount() > 0) {
        $stmt = $db->prepare("SELECT id, full_name FROM providers WHERE user_id = ? ORDER BY full_name ASC");
        $stmt->execute([$userId]);
        $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. OBTENER PRODUCTOS (Usando Mapeo Inteligente)
    $products = [];

    // A. Buscar inventario activo y sus preferencias
    $stmtInv = $db->prepare("SELECT id, preferences FROM inventories WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmtInv->execute([$userId]);
    $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if ($inv) {
        // B. Leer Mapeo
        $prefs = json_decode($inv['preferences'] ?? '{}', true);
        $mapping = $prefs['mapping'] ?? [];

        // Columnas mapeadas por el usuario
        $colName = $mapping['name'] ?? null;
        $colPrice = $mapping['buy_price'] ?? null; // Costo (Precio Compra)
        $colStock = $mapping['stock'] ?? null;

        // C. Buscar tabla física
        $stmtTable = $db->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
        $stmtTable->execute([$inv['id']]);
        $tableRow = $stmtTable->fetch(PDO::FETCH_ASSOC);

        if ($tableRow) {
            $tableName = $tableRow['table_name'];
            $safeTable = "`" . str_replace("`", "``", $tableName) . "`";

            // D. Consultar datos reales
            $query = "SELECT * FROM $safeTable";
            $stmtProd = $db->query($query);

            while ($row = $stmtProd->fetch(PDO::FETCH_ASSOC)) {
                // LOGICA DE FALLBACKS (Lo que pediste)

                // 1. Nombre: Si no hay columna o está vacío -> Usar ID
                $finalName = null;
                if ($colName && !empty($row[$colName])) {
                    $finalName = $row[$colName];
                } else {
                    $finalName = "Producto ID: " . ($row['id'] ?? '?');
                }

                // 2. Precio: Crítico. Si no hay columna -> Error
                $finalPrice = 0;
                $canBuy = true;
                $priceError = null;

                if ($colPrice && isset($row[$colPrice])) {
                    $finalPrice = (float)$row[$colPrice];
                } else {
                    $canBuy = false; // BLOQUEAR COMPRA
                    $priceError = "Sin columna de Costo configurada.";
                }

                // 3. Stock: Informativo. Si no hay columna -> Warning
                $finalStock = 0;
                $stockWarning = false;

                if ($colStock && isset($row[$colStock])) {
                    $finalStock = (int)$row[$colStock];
                } else {
                    $stockWarning = true; // ADVERTENCIA VISUAL
                }

                $products[] = [
                    'id' => $row['id'],
                    'name' => $finalName,
                    'price' => $finalPrice,
                    'stock' => $finalStock,
                    'can_buy' => $canBuy,       // Flag para bloquear
                    'price_error' => $priceError,
                    'stock_warning' => $stockWarning // Flag para advertir
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