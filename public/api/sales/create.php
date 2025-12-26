<?php
// public/api/sales/create.php
header('Content-Type: application/json');
ini_set('display_errors', 0); error_reporting(E_ALL);

use App\Models\SalesModel;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Models/SalesModel.php';


    if (!function_exists('getCurrentUser')) throw new Exception('Auth error');
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    // --- CAMBIO CLAVE: Leer FormData ($_POST y $_FILES) ---
    // Si viene items como string JSON, lo decodificamos
    $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

    $data = [
        'total' => (float)($_POST['total'] ?? 0),
        'amount_tendered' => (float)($_POST['amount_tendered'] ?? 0),
        'change_returned' => (float)($_POST['change_returned'] ?? 0),
        'commission_amount' => (float)($_POST['commission_amount'] ?? 0),
        'employee_id' => !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : null,
        'payment_method_id' => !empty($_POST['payment_method_id']) ? (int)$_POST['payment_method_id'] : null,
        'category' => $_POST['category'] ?? null,
        'notes' => $_POST['notes'] ?? null,
        'items' => $items
    ];
    $clientId = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;

    // --- MANEJO DE ARCHIVO (Comprobante) ---
    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = $root . '/public/uploads/proofs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $ext = pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION);
        $filename = 'proof_' . $user['id'] . '_' . time() . '.' . $ext;

        if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $uploadDir . $filename)) {
            $data['proof_file'] = 'uploads/proofs/' . $filename; // Guardamos ruta relativa
        }
    }

    if (empty($data['items']) && empty($data['category'])) {
        echo json_encode(['success' => false, 'message' => 'Error: Sin items ni categoría.']);
        exit;
    }

    $model = new SalesModel();
    $saleId = $model->registrarVenta($user['id'], $clientId, $data);

    if ($saleId) echo json_encode(['success' => true, 'sale_id' => $saleId]);
    else echo json_encode(['success' => false, 'message' => 'Error al guardar']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>