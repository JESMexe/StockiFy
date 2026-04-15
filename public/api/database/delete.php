<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\InventoryController;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Verificar token anti-replay de doble autenticación
if (session_status() === PHP_SESSION_NONE) session_start();

$authData    = $_SESSION['delete_auth_token'] ?? null;
$activeInvId = $_SESSION['active_inventory_id'] ?? null;

if (
    !$authData ||
    empty($authData['token']) ||
    ($authData['inventory_id'] != $activeInvId) ||
    ($authData['expires_at'] < time())
) {
    // Limpiar token inválido o expirado
    unset($_SESSION['delete_auth_token']);
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Verificación de identidad requerida o expirada. Por favor re-confirmá tu identidad.']);
    exit;
}

// Consumir token (one-time use)
unset($_SESSION['delete_auth_token']);

$controller = new InventoryController();
$controller->delete();