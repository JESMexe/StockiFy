<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
$root = dirname(__DIR__, 3);
require_once $root . '/vendor/autoload.php';
require_once $root . '/src/helpers/auth_helper.php';
require_once $root . '/src/core/Database.php';
require_once $root . '/src/Services/TiendaNubeService.php';

use App\core\Database;
use App\Services\TiendaNubeService;

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$inventoryId = $input['inventory_id'] ?? $_SESSION['active_inventory_id'] ?? null;
$mapping = $input['mapping'] ?? null;

if (!$inventoryId || !$mapping) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // 1. Obtener token y tabla
    $stmt = $db->prepare("
        SELECT i.tiendanube_token, i.tiendanube_store_id, t.table_name
        FROM inventories i
        JOIN user_tables t ON i.id = t.inventory_id
        WHERE i.id = ? AND i.user_id = ?
    ");
    $stmt->execute([$inventoryId, $user['id']]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inv || empty($inv['tiendanube_token'])) {
        throw new Exception("TiendaNube no conectada para este inventario.");
    }

    $tnService = new TiendaNubeService($inv['tiendanube_token'], $inv['tiendanube_store_id']);
    $products = $tnService->getProducts();

    // 2. Preparar filas para insertar
    $rowsToInsert = [];
    foreach ($products as $p) {
        foreach ($p['variants'] as $v) {
            $row = [];
            foreach ($mapping as $sysCol => $tnField) {
                $val = '';
                switch ($tnField) {
                    case 'name':
                        $nameObj = $p['name'];
                        $val = $nameObj['es'] ?? reset($nameObj);
                        break;
                    case 'sku':
                        $val = $v['sku'];
                        break;
                    case 'price':
                        $val = $v['price'];
                        break;
                    case 'stock':
                        $val = $v['stock'];
                        break;
                    case 'barcode':
                        $val = $v['barcode'];
                        break;
                    case 'description':
                        $descObj = $p['description'];
                        $val = strip_tags($descObj['es'] ?? reset($descObj));
                        break;
                    case 'categories':
                        $cats = array_map(function($c) { return $c['name']['es'] ?? reset($c['name']); }, $p['categories']);
                        $val = implode(', ', $cats);
                        break;
                    case 'variant_name':
                        $variantValues = array_map(function($val) { return $val['es'] ?? reset($val); }, $v['values']);
                        $val = implode(' / ', $variantValues);
                        break;
                }
                $row[$sysCol] = $val;
            }
            
            // Si mapeamos nombre y variante, los combinamos si hay variante
            if (isset($mapping['name']) && isset($mapping['variant_name'])) {
                // Buscamos la columna de sistema que mapeó a 'name'
                $nameCol = array_search('name', $mapping);
                $variantCol = array_search('variant_name', $mapping);
                if ($nameCol && $variantCol && !empty($row[$variantCol])) {
                    $row[$nameCol] .= " (" . $row[$variantCol] . ")";
                    // No eliminamos la columna variant_name porque el usuario pudo haberla mapeado a otra columna de sistema
                }
            }

            $rowsToInsert[] = $row;
        }
    }

    if (empty($rowsToInsert)) {
        throw new Exception("No se encontraron productos con variantes para importar.");
    }

    // 3. Insertar en la DB
    $tableName = "`" . str_replace("`", "``", $inv['table_name']) . "`";
    $cols = array_keys($mapping);
    $safeCols = array_map(fn($c) => "`" . str_replace("`", "``", $c) . "`", $cols);
    $placeholders = "(" . implode(", ", array_fill(0, count($cols), "?")) . ")";
    $sql = "INSERT INTO $tableName (" . implode(", ", $safeCols) . ") VALUES $placeholders";
    
    $db->beginTransaction();
    $stmtIns = $db->prepare($sql);
    $count = 0;
    foreach ($rowsToInsert as $row) {
        $values = [];
        foreach ($cols as $c) {
            $values[] = $row[$c] ?? null;
        }
        $stmtIns->execute($values);
        $count++;
    }
    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => "Sincronización completada. Se importaron $count productos/variantes.",
        'count' => $count
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
