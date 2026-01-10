<?php
// public/api/sales/create.php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

use App\Models\SalesModel;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Models/SalesModel.php';

    // ---------------------------------------------------------
    // 1. CAPTURA DE DATOS (FIX JSON VS POST)
    // ---------------------------------------------------------
    $input = [];
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    function getParam($key, $post, $input, $default = null) {
        return $input[$key] ?? $post[$key] ?? $default;
    }

    // ---------------------------------------------------------
    // 2. AUTENTICACIÓN
    // ---------------------------------------------------------
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!function_exists('getCurrentUser')) throw new Exception('Auth error');
    $user = getCurrentUser();

    if (!$user) {
        echo json_encode(['success'=>false, 'message'=>'No autorizado']);
        exit;
    }

    // ---------------------------------------------------------
    // 3. OBTENER INVENTARIO (CRÍTICO)
    // ---------------------------------------------------------
    $inventoryId = $_SESSION['active_inventory_id']
        ?? $_SESSION['inventory_id']
        ?? getParam('inventory_id', $_POST, $input)
        ?? null;

    if (!$inventoryId) {
        echo json_encode(['success'=>false, 'message'=>'Error: No hay inventario seleccionado.']);
        exit;
    }

    // ---------------------------------------------------------
    // 4. PROCESAR ITEMS
    // ---------------------------------------------------------
    $rawItems = getParam('items', $_POST, $input);
    $items = [];
    if (is_array($rawItems)) {
        $items = $rawItems;
    } elseif (is_string($rawItems)) {
        $items = json_decode($rawItems, true) ?? [];
    }

    // ---------------------------------------------------------
    // 5. ARMAR DATOS
    // ---------------------------------------------------------
    $data = [
        'total'             => (float)getParam('total', $_POST, $input, 0),
        'amount_tendered'   => (float)getParam('amount_tendered', $_POST, $input, 0),
        'change_returned'   => (float)getParam('change_returned', $_POST, $input, 0),
        'commission_amount' => (float)getParam('commission_amount', $_POST, $input, 0),
        'total_final'       => (float)getParam('total_final', $_POST, $input, 0),

        'employee_id'       => !empty(getParam('employee_id', $_POST, $input)) ? (int)getParam('employee_id', $_POST, $input) : null,
        'payment_method_id' => !empty(getParam('payment_method_id', $_POST, $input)) ? (int)getParam('payment_method_id', $_POST, $input) : null,
        'category'          => getParam('category', $_POST, $input),
        'notes'             => getParam('notes', $_POST, $input),
        'payments'          => getParam('payments', $_POST, $input) ?? [],
        'items'             => $items
    ];

    $clientIdParam = getParam('client_id', $_POST, $input) ?? getParam('customer_id', $_POST, $input);
    $clientId = !empty($clientIdParam) ? (int)$clientIdParam : null;

    // Archivo Comprobante
    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = $root . '/public/uploads/proofs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $ext = pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION);
        $filename = 'proof_' . $user['id'] . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $uploadDir . $filename)) {
            $data['proof_file'] = 'uploads/proofs/' . $filename;
        }
    }

    if (empty($data['items']) && empty($data['category'])) {
        echo json_encode(['success' => false, 'message' => 'Error: Sin items ni categoría.']);
        exit;
    }

    // ---------------------------------------------------------
    // 6. GUARDAR (CORREGIDO)
    // ---------------------------------------------------------
    $model = new SalesModel();

    // ¡AQUÍ ESTABA EL ERROR!
    // Ahora llamamos a createSale con 4 argumentos, incluyendo el inventoryId
    $saleId = $model->createSale($user['id'], $inventoryId, $clientId, $data);

    if ($saleId) echo json_encode(['success' => true, 'sale_id' => $saleId]);
    else echo json_encode(['success' => false, 'message' => 'Error al guardar en base de datos']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>