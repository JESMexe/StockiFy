<?php
// public/api/table/get.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Es posible que el autoload de Composer no esté cargando los helpers
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
// Y si el TableController usa un Modelo o Database directamente
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\TableController;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

$controller = new TableController();

$controller->get();

