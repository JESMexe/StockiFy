<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/helpers/quota_helper.php';
require_once __DIR__ . '/../../../src/Models/InvitationModel.php';
require_once __DIR__ . '/../../../src/Services/MailService.php';

use App\Models\InvitationModel;
use App\Services\MailService;
use App\core\Database;

try {

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

    // --- Verificar si ya tiene algún registro en el inventario (activo o pendiente) ---
    $db = Database::getInstance();
    $stmtExisting = $db->prepare(
        "SELECT id, status FROM inventory_collaborators WHERE inventory_id = ? AND user_id = ? LIMIT 1"
    );
    $stmtExisting->execute([$inventoryId, $targetUser['id']]);
    $existing = $stmtExisting->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($existing['status'] === 'active') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Ese usuario ya es colaborador activo de este inventario.']);
            exit;
        }
        // Si está en otro estado (pending, removed), lo reactivamos más abajo via ON DUPLICATE KEY
    }

    // --- Obtener nombre del inventario ---
    $stmtInv = $db->prepare("SELECT name FROM inventories WHERE id = ?");
    $stmtInv->execute([$inventoryId]);
    $invName = $stmtInv->fetchColumn() ?: 'Inventario Compartido';

    $senderName = $user['full_name'] ?: $user['username'];
    $roleName   = $roleId === 2 ? 'Administrador' : 'Empleado';

    // --- Expirar invitaciones previas pendientes al mismo email+inventario ---
    $invModel->markPreviousExpired($inventoryId, $email);

    // --- Verificar cupo de colaboradores del Owner del inventario ---
    $stmtOwner = $db->prepare('SELECT user_id FROM inventories WHERE id = ? LIMIT 1');
    $stmtOwner->execute([$inventoryId]);
    $ownerId = (int)$stmtOwner->fetchColumn();

    $quota = getCollaboratorQuota($ownerId);

    if ($quota['locked']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'El plan <strong>' . $quota['plan_name'] . '</strong> no permite agregar colaboradores. Actualizá el plan para habilitar esta función.',
        ]);
        exit;
    }

    if (!$quota['allowed']) {
        $used = $quota['used'];
        $max  = $quota['max'];
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => "Alcanzaste el límite de colaboradores de tu plan <strong>{$quota['plan_name']}</strong> ({$used}/{$max}). Para agregar más, contactá a soporte.",
        ]);
        exit;
    }

    // --- Agregar directamente como colaborador activo ---
    $stmtInsert = $db->prepare(
        "INSERT INTO inventory_collaborators (inventory_id, user_id, role_id, status, invited_by) 
         VALUES (?, ?, ?, 'active', ?)
         ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), status = 'active', invited_by = VALUES(invited_by)"
    );
    $stmtInsert->execute([$inventoryId, $targetUser['id'], $roleId, $user['id']]);

    // --- Sincronizar Empleado ---
    require_once __DIR__ . '/../../../src/Models/EmployeeModel.php';
    $empModel = new \App\Models\EmployeeModel();
    
    // Check if employee already exists by email
    $stmtEmpCheck = $db->prepare("SELECT id FROM employees WHERE email = ? AND inventory_id = ? LIMIT 1");
    $stmtEmpCheck->execute([$email, $inventoryId]);
    $existingEmpId = $stmtEmpCheck->fetchColumn();
    
    if ($existingEmpId) {
        $empModel->updateIsCollaboratorStatus($existingEmpId, 1);
    } else {
        $empModel->createEmployee(
            $ownerId, 
            $targetUser['full_name'] ?: $targetUser['username'] ?: $email, 
            null, 
            null, 
            $email, 
            $inventoryId, 
            null, 
            null, 
            1
        );
    }

    // --- Guardar registro histórico de la invitación (auditoría) ---
    $token = $invModel->createInvitation($inventoryId, $email, $roleId, $user['id']);

    // --- Enviar email de notificación al nuevo colaborador ---
    $targetName = $targetUser['full_name'] ?: $targetUser['username'] ?: $email;

    try {
        require_once __DIR__ . '/../../../src/helpers/ActivityLogger.php';
        \App\helpers\ActivityLogger::log(
            'Colaboradores',
            'add',
            'collaborator',
            (string)$targetUser['id'],
            "Agregó al colaborador: " . $targetName,
            "Rol asignado: " . $roleName . " | Email: " . $email,
            (int)$inventoryId,
            (int)$user['id']
        );
    } catch (\Throwable $logErr) {
        error_log('ActivityLogger error in invitations/send: ' . $logErr->getMessage());
    }
    $dashboardLink = "https://" . $_SERVER['HTTP_HOST'] . "/api/invitations/accept?token=" . $token;

    // --- Enviar notificación WhatsApp al Owner ---
    if ($ownerId) {
        $stmtOwnerDetails = $db->prepare("SELECT email, full_name, cell FROM users WHERE id = ?");
        $stmtOwnerDetails->execute([$ownerId]);
        $ownerDetails = $stmtOwnerDetails->fetch(PDO::FETCH_ASSOC);
        if ($ownerDetails && !empty($ownerDetails['cell'])) {
            try {
                require_once __DIR__ . '/../../../src/Services/WhatsappService.php';
                $waSvc = new \App\Services\WhatsappService();
                $waSvc->sendNewCollaboratorAlert(
                    $ownerDetails['cell'],
                    $ownerDetails['full_name'] ?? 'Socio',
                    $targetName,
                    $email,
                    $invName,
                    $roleName
                );
            } catch (\Throwable $waErr) {
                error_log('WhatsApp sendNewCollaboratorAlert error: ' . $waErr->getMessage());
            }
        }
    }

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

} catch (PDOException $e) {
    // Error de base de datos
    http_response_code(500);
    $msg = 'Error de base de datos al procesar la invitación.';
    // Constraint duplicado (por si ON DUPLICATE KEY falla de otra manera)
    if (isset($e->errorInfo[1]) && in_array($e->errorInfo[1], [1062, 1586])) {
        http_response_code(422);
        $msg = 'Ese usuario ya tiene un registro en este inventario.';
    }
    echo json_encode(['success' => false, 'message' => $msg]);

} catch (Throwable $e) {
    // Cualquier otro error (MailService, etc.)
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ocurrió un error inesperado al procesar la invitación. Intentalo de nuevo.'
    ]);
}
