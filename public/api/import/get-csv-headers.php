<?php
// public/api/import/get-csv-headers.php

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
// ------------------

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\ImportController;


try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception("Método no permitido.");
    }

    $controller = new ImportController();
    $controller->getCsvHeaders();

} catch (Throwable $e) {
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error al preparar datos: ' . $e->getMessage()]);

} finally {
    ob_end_flush();
}