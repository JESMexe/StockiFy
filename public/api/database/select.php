<?php
// public/api/database/select.php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\InventoryController;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$controller = new InventoryController();
$controller->select();