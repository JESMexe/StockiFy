<?php
$root = 'c:/Users/joaqu/PhpstormProjects/StockyApp_UProject';
require_once $root . '/vendor/autoload.php';
require_once $root . '/src/core/Database.php';

use App\core\Database;

try {
    $db = Database::getInstance();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting migration for TiendaNube...\n";

    // Add columns to inventories table
    $columns = $db->query("SHOW COLUMNS FROM inventories")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('tiendanube_token', $columns)) {
        $db->exec("ALTER TABLE inventories ADD COLUMN tiendanube_token TEXT NULL");
        echo "Column 'tiendanube_token' added.\n";
    }

    if (!in_array('tiendanube_store_id', $columns)) {
        $db->exec("ALTER TABLE inventories ADD COLUMN tiendanube_store_id VARCHAR(255) NULL");
        echo "Column 'tiendanube_store_id' added.\n";
    }

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
