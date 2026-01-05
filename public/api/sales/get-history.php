<?php
header('Content-Type: application/json');

// Ajusta estas rutas si es necesario según tu estructura
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Models/SalesModel.php';

use App\Models\SalesModel;

try {
    // 1. Verificación de seguridad
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    // 2. Instanciar el Modelo
    $model = new SalesModel();

    // CORRECCIÓN AQUÍ: Usamos getHistory, NO getAll
    // getAll() ya no existe en el modelo nuevo, por eso te daba error.
    $sales = $model->getHistory($user['id'], $_GET['order'] ?? 'desc');

    // 3. Respuesta limpia
    // Nota: El modelo ya devuelve los datos limpios y formateados,
    // así que no hace falta mapearlos de nuevo aquí.
    echo json_encode(['success' => true, 'sales' => $sales]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}