<?php
// public/api/import/execute-import.php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\Controllers\ImportController;

try {
    if (session_status() === PHP_SESSION_NONE) session_start();

    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        throw new Exception("No autorizado");
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        // Aceptamos POST para ejecutar la acción
        throw new Exception("Método no permitido");
    }

    $controller = new ImportController();

    // Llamamos al método que acabamos de crear
    $result = $controller->finalizeImport();

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}