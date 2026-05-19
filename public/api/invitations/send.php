<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Models/InvitationModel.php';
require_once __DIR__ . '/../../../src/Services/MailService.php';

use App\Models\InvitationModel;
use App\Services\MailService;
use App\core\Database;

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data   = json_decode(file_get_contents('php://input'), true);
$email  = strtolower(trim($data['email'] ?? ''));
$roleId = (int)($data['role_id'] ?? 3);
$inventoryId = (int)($data['inventory_id'] ?? $_SESSION['active_inventory_id'] ?? 0);

// --- Validaciones básicas ---
if (!$inventoryId || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos inválidos o incompletos.']);
    exit;
}

if (!in_array($roleId, [2, 3])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Rol inválido.']);
    exit;
}

// --- Verificar que el invitante tiene permiso (solo Owner o Admin pueden invitar) ---
$myRole = getInventoryRole($user['id'], $inventoryId);
if (!$myRole || !in_array($myRole['role_id'], [1, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tenés permisos para invitar en este inventario.']);
    exit;
}

$invModel = new InvitationModel();

// --- Verificar que el email pertenece a un usuario registrado en StockiFy ---
$targetUser = $invModel->findUserByEmail($email);
if (!$targetUser) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => "No existe ningún usuario de StockiFy con el correo <strong>{$email}</strong>. Pedile primero que se registre en stockify.com.ar/register"
    ]);
    exit;
}

// --- No puede invitarse a sí mismo ---
if ((int)$targetUser['id'] === (int)$user['id']) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'No podés invitarte a vos mismo.']);
    exit;
}

// --- Verificar que no sea ya colaborador activo ---
$db = Database::getInstance();
$stmtExisting = $db->prepare(
    "SELECT id FROM inventory_collaborators WHERE inventory_id = ? AND user_id = ? AND status = 'active'"
);
$stmtExisting->execute([$inventoryId, $targetUser['id']]);
if ($stmtExisting->fetch()) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Ese usuario ya es colaborador activo de este inventario.']);
    exit;
}

// --- Obtener nombre del inventario ---
$stmtInv = $db->prepare("SELECT name FROM inventories WHERE id = ?");
$stmtInv->execute([$inventoryId]);
$invName = $stmtInv->fetchColumn() ?: 'Inventario Compartido';

$senderName = $user['full_name'] ?: $user['username'];
$roleName   = $roleId === 2 ? 'Administrador' : 'Empleado';

// --- Expirar invitaciones previas pendientes al mismo email+inventario ---
$invModel->markPreviousExpired($inventoryId, $email);

// --- Agregar directamente como colaborador activo (política: solo usuarios registrados) ---
$stmtInsert = $db->prepare(
    "INSERT INTO inventory_collaborators (inventory_id, user_id, role_id, status, invited_by) 
     VALUES (?, ?, ?, 'active', ?)
     ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), status = 'active', invited_by = VALUES(invited_by)"
);
$stmtInsert->execute([$inventoryId, $targetUser['id'], $roleId, $user['id']]);

// --- Guardar registro histórico de la invitación (auditoría) ---
$invModel->createInvitation($inventoryId, $email, $roleId, $user['id']);

// --- Enviar email de notificación al nuevo colaborador ---
$targetName = $targetUser['full_name'] ?: $targetUser['username'] ?: $email;
$dashboardLink = "https://" . $_SERVER['HTTP_HOST'] . "/dashboard";

$mailService = new MailService();
$sent = $mailService->sendInvitationEmail($email, $invName, $roleName, $dashboardLink, $senderName);

if ($sent) {
    echo json_encode([
        'success' => true,
        'message' => "¡{$targetName} fue agregado como {$roleName} y notificado por correo!"
    ]);
} else {
    // El colaborador fue agregado igualmente, solo falló el email
    echo json_encode([
        'success' => true,
        'message' => "{$targetName} fue agregado como {$roleName}. No se pudo enviar el email de notificación."
    ]);
}
