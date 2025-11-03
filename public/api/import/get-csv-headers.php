<?php
// public/api/import/get-csv-headers.php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\ImportController;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$controller = new ImportController();
$controller->getCsvHeaders();