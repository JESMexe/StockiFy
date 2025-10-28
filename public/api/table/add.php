<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\TableController;

// Esta acción espera datos vía POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$controller = new TableController();
$controller->addItem();