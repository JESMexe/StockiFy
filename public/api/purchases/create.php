<?php
// public/api/purchases/create.php
use App\Models\PurchaseModel;

header('Content-Type: application/json');

// Desactivar errores HTML
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Models/PurchaseModel.php';

    // 1. Auth
    if (!function_exists('getCurrentUser')) throw new Exception('Auth error');
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    // 2. Input
    $input = json_decode(file_get_contents('php://input'), true);

    // Preparar datos unificados
    $data = [
        'provider_id' => !empty($input['provider_id']) ? (int)$input['provider_id'] : null,
        'category'    => !empty($input['category']) ? $input['category'] : null,
        'notes'       => !empty($input['notes']) ? $input['notes'] : null,
        'total'       => isset($input['total']) ? (float)$input['total'] : 0,
        'items'       => $input['items'] ?? []
    ];

    // 3. VALIDACIÓN INTELIGENTE
    // Aceptamos la operación si:
    // A) Tiene items (Es una compra de mercadería)
    // B) Tiene una categoría (Es un gasto rápido)
    if (empty($data['items']) && empty($data['category'])) {
        echo json_encode(['success' => false, 'message' => 'Error: Debes agregar productos al carrito o definir una categoría de gasto.']);
        exit;
    }

    $model = new PurchaseModel();
    // Usamos createPurchase (que soporta ambos tipos)
    $id = $model->createPurchase($user['id'], $data);

    if ($id) {
        echo json_encode(['success' => true, 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al registrar en base de datos']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>