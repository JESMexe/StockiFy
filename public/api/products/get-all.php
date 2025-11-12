<?php


require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../auth_helper.php';

use App\core\Database;
use App\Models\InventoryModel;

try {

    $pdo = Database::getInstance();
    $inventoryModel = new InventoryModel();

    $user = getCurrentUser();
    $user_id = $_SESSION['user_id'];

    $databases = $inventoryModel->findByUserId($user_id);

    $productList = [];

    foreach($databases as $database){
        $inventoryID = $database['id'];

        $stmt = $pdo->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
        $stmt->execute([$inventoryID]);
        $databaseName = $stmt->fetch(PDO::FETCH_COLUMN);

        $sql = "SELECT id,name,stock,min_stock,sale_price,receipt_price FROM `$databaseName`";

        $productsStmt = $pdo->prepare($sql);
        $productsStmt->execute();
        $products = $productsStmt->fetchAll();

        foreach($products as $product){
            $productList[] = ['pID' => $product['id'], 'tID' => $inventoryID, 'stock' => $product['stock'],
                'name' => $product['name'], 'salePrice' => $product['sale_price'], 'receiptPrice' => $product['receipt_price']];
        }
    }

    $response = ['productList' => $productList, 'success' => true];

    header('Content-Type: application/json');
} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}
echo json_encode($response, JSON_NUMERIC_CHECK);
