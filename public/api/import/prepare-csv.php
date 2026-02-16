<?php
// public/api/import/prepare-csv.php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\Controllers\ImportController;

try {
    if (session_status() === PHP_SESSION_NONE) session_start();

    // 1. Verificar Autenticación
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        throw new Exception("Sesión expirada o no autorizado.");
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception("Método no permitido");
    }

    // 2. Instanciar y procesar
    $controller = new ImportController();

    // AQUÍ ESTABA EL ERROR: Ahora pasamos los argumentos requeridos
    $result = $controller->processCsvPreparation($_POST, $_FILES);

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}