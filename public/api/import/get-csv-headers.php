<?php
// public/api/import/get-csv-headers.php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\Controllers\ImportController;

try {
    session_start();
    $user = getCurrentUser();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido.');
    }

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir el archivo CSV o archivo no recibido.');
    }

    $tmpPath = $_FILES['csv_file']['tmp_name'];

    $controller = new ImportController();
    $result = $controller->getCsvHeaders($tmpPath);

    if (!$result['success']) {
        throw new Exception($result['message']);
    }

    echo json_encode([
        'success' => true,
        'headers' => $result['headers'],       // Para la DB (sanitizados)
        'ui_headers' => $result['ui_headers'], // Para el Usuario (bonitos)
        'delimiter' => $result['delimiter']    // Para el siguiente paso
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}