<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Models/InvitationModel.php';

use App\Models\InvitationModel;

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$inventoryId = $data['inventory_id'] ?? null;
$email = $data['email'] ?? null;
$roleId = $data['role_id'] ?? 3;

if (!$inventoryId || !$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan datos']);
    exit;
}

$role = getInventoryRole($user['id'], $inventoryId);
// Solo Owner (1) y Admin (2) pueden invitar
if (!$role || !in_array($role['role_id'], [1, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tienes permisos para invitar en este inventario']);
    exit;
}

$invModel = new InvitationModel();
$token = $invModel->createInvitation($inventoryId, $email, $roleId, $user['id']);

if ($token) {
    $acceptLink = "https://" . $_SERVER['HTTP_HOST'] . "/api/invitations/accept.php?token=" . $token;
    
    // Aquí se integraría con MailService en producción
    // MailService::sendInvitationEmail($email, $acceptLink);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Invitación enviada correctamente', 
        'debug_link' => $acceptLink // Para probar en dev local
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno al generar la invitación']);
}
