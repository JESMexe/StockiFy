<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../auth_helper.php';

use App\core\Database;

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $pdo = Database::getInstance();

    $user = getCurrentUser();
    $user_id = $_SESSION['user_id'];

    $inventoryID = $_SESSION['active_inventory_id'];
    $stmt = $pdo->prepare("UPDATE inventories SET min_stock = ?, sale_price = ?, receipt_price = ?,
                       hard_gain = ?, percentage_gain = ?, auto_price = ?, auto_price_type = ? WHERE id = ?");
    $stmt->execute([
        $data['min_stock']['active'],
        $data['sale_price']['active'],
        $data['receipt_price']['active'],
        $data['hard_gain']['active'],
        $data['percentage_gain']['active'],
        $data['auto_price'],
        $data['auto_price_type'],
        $inventoryID]);

    $stmt = $pdo->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
    $stmt->execute([$inventoryID]);
    $tableName = $stmt->fetchColumn();

    $sql = "ALTER TABLE {$tableName} MODIFY COLUMN min_stock INT DEFAULT {$data['min_stock']['default']},
    MODIFY COLUMN sale_price DECIMAL(10,2) DEFAULT {$data['sale_price']['default']},
    MODIFY COLUMN receipt_price DECIMAL(10,2) DEFAULT {$data['receipt_price']['default']},
    MODIFY COLUMN hard_gain DECIMAL(10,2) DEFAULT {$data['hard_gain']['default']},
    MODIFY COLUMN percentage_gain DECIMAL(10,2) DEFAULT {$data['percentage_gain']['default']}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    echo true;
} catch (Exception $e) {
    echo false;
}
