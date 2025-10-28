<?php
// public/api/database/delete.php
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\InventoryController;

// Usaremos POST para una acción destructiva
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$controller = new InventoryController();
$controller->delete();