<?php
// public/api/providers/create.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

// Desactivar errores HTML para no romper JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

use App\Models\ProviderModel;

try {
    // 1. Definir ruta raíz y cargar dependencias
    $projectRoot = dirname(__DIR__, 3);
    require_once $projectRoot . '/vendor/autoload.php';
    require_once $projectRoot . '/src/helpers/auth_helper.php';
    require_once $projectRoot . '/src/Models/ProviderModel.php';

    // 2. Verificar Autenticación
    if (!function_exists('getCurrentUser')) throw new Exception('Auth helper no cargado');

    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    
$inventoryId = $_SESSION['active_inventory_id'] ?? null;
if (!$inventoryId) { echo json_encode(['success'=>false, 'message'=>'Inventario no seleccionado']); exit; }
// 3. Leer JSON (Plano, sin 'provider' wrapper)
    $input = json_decode(file_get_contents('php://input'), true);

    // Validación básica
    if (empty($input['name'])) {
        echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
        exit;
    }

    // 4. Guardar usando el Modelo
    $model = new ProviderModel();
    // Pasamos el array $input directo porque el modelo ya sabe qué campos usar
    $id = $model->createProvider($user['id'], $input, $inventoryId);

    if ($id) {
        echo json_encode(['success' => true, 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar en base de datos']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error 500: ' . $e->getMessage()]);
}
?>