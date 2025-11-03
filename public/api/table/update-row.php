<?php
// public/api/table/update-row.php
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\TableController;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$controller = new TableController();
$controller->updateItem();