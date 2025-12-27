<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

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

        // CORRECCIÓN CRÍTICA: Usamos SELECT * para traer las columnas tal cual estén (identificadas o no)
        // Si el usuario identificó "Producto" como "name", vendrá la columna 'name'.
        // Si no, vendrá 'Producto' y el JS sabrá que falta 'name'.
        $sql = "SELECT * FROM `$databaseName`";

        $productsStmt = $pdo->prepare($sql);
        $productsStmt->execute();
        $products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach($products as $product){
            // Pasamos el producto completo.
            // Si las columnas "standard" existen, el JS las usará.
            // Añadimos IDs de referencia para el sistema.
            $product['pID'] = $product['id'];
            $product['tID'] = $inventoryID;

            // Normalización estricta para el buscador del frontend (solo si existe name)
            // Si 'name' no está definido, search_data quedará limitado, pero no fallará.
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