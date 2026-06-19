<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;
use App\Models\InventoryModel;

try {
    $pdo = Database::getInstance();
    $inventoryModel = new InventoryModel();

    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }
    requireSectionAccess('can_view_data');
    $user_id = $_SESSION['user_id'];
    $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

    // RBAC: si hay un inventario activo, trabajar solo con ese (y con el owner_id real)
    if ($activeInventoryId) {
        $ownerId = getInventoryOwnerId((int)$activeInventoryId) ?? $user_id;
        $databases = [['id' => $activeInventoryId, 'user_id' => $ownerId]];
    } else {
        $databases = $inventoryModel->findByUserId($user_id);
    }

    $productList = [];

    foreach($databases as $database){
        $inventoryID = $database['id'];

        $stmt = $pdo->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
        $stmt->execute([$inventoryID]);
        $databaseName = $stmt->fetch(PDO::FETCH_COLUMN);

        $sql = "SELECT * FROM `$databaseName`";

        $productsStmt = $pdo->prepare($sql);
        $productsStmt->execute();
        $products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach($products as $product){
            $product['pID'] = $product['id'];
            $product['tID'] = $inventoryID;

            $searchName = $product['name'] ?? '';
            $searchSku  = $product['sku'] ?? ''; // Asumiendo que sku puede existir
            $product['search_data'] = strtolower("$searchName $searchSku");

            $productList[] = $product;
        }
    }

    $response = ['productList' => $productList, 'success' => true];
    header('Content-Type: application/json');

} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Error interno: ' . $message];
}
echo json_encode($response, JSON_NUMERIC_CHECK);