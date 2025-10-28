<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// public/api/table/get.php
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\TableController;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

$controller = new TableController();

$controller->get();

