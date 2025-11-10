<?php
// public/api/table/manage-column.php
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\TableStructureController;

$controller = new TableStructureController();
$controller->handleRequest();