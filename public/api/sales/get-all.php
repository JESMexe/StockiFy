<?php
header('Content-Type: application/json');

// Ajusta la ruta al autoloader según tu estructura
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Models/SalesModel.php';

use App\Models\SalesModel;

try {
    // 1. Auth
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    // 2. Modelo
    $model = new SalesModel();

    // 3. OBTENER DATOS
    // AQUÍ ESTABA EL ERROR: Cambiamos getAll() por getHistory()
    // getHistory es el método potente que creamos en el modelo nuevo.
    $sales = $model->getHistory($user['id'], $_GET['order'] ?? 'desc');

    // 4. Responder
    // El modelo ya devuelve los datos limpios, solo los enviamos.
    echo json_encode(['success' => true, 'sales' => $sales]); // Nota: El JS espera "sales", no "purchases"

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}