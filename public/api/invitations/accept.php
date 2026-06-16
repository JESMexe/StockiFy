<?php
/**
 * Endpoint de aceptación de invitación.
 * Con la nueva política (solo usuarios registrados), la invitación se activa
 * automáticamente al enviarla. Este endpoint solo se usa si alguien llegó
 * al link siendo aún invitado "pendiente" (caso de migración o edge case).
 * Lo principal: el usuario debe estar logueado.
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

if (session_status() === PHP_SESSION_NONE) session_start();

$token = $_GET['token'] ?? null;
if (!$token) {
    die("Enlace de invitación inválido.");
}

$user = getCurrentUser();

if (!$user) {
    // No está logueado — redirigir al login con mensaje
    $_SESSION['flash_info'] = 'Iniciá sesión para acceder al inventario compartido.';
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: /login");
    exit;
}

$db = Database::getInstance();

// Buscar la invitación por token
$stmt = $db->prepare("SELECT * FROM invitations WHERE token = ? LIMIT 1");
$stmt->execute([$token]);
$invitation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invitation) {
    die("El enlace de invitación no es válido o ya fue utilizado.");
}

// Verificar que el email de la invitación coincide con el usuario logueado
if (strtolower($invitation['email']) !== strtolower($user['email'])) {
    die("Este enlace no corresponde a tu cuenta (" . htmlspecialchars($user['email']) . "). Iniciá sesión con el correo al que se envió la invitación.");
}

// Si la invitación está pendiente, activar al colaborador
if ($invitation['status'] === 'pending') {
    $stmtCollab = $db->prepare(
        "INSERT INTO inventory_collaborators (inventory_id, user_id, role_id, status, invited_by, accepted_at)
         VALUES (?, ?, ?, 'active', ?, NOW())
         ON DUPLICATE KEY UPDATE status='active', role_id=VALUES(role_id), accepted_at=NOW()"
    );
    $stmtCollab->execute([
        $invitation['inventory_id'],
        $user['id'],
        $invitation['role_id'],
        $invitation['invited_by']
    ]);
    // Notify Owner of acceptance via WhatsApp and/or Email
    try {
        $stmtInv = $db->prepare("SELECT user_id, name FROM inventories WHERE id = ? LIMIT 1");
        $stmtInv->execute([$invitation['inventory_id']]);
        $invRow = $stmtInv->fetch(PDO::FETCH_ASSOC);
        $ownerId = $invRow ? (int)$invRow['user_id'] : null;
        $invName = $invRow ? $invRow['name'] : 'Inventario Compartido';

        if ($ownerId) {
            $stmtOwnerDetails = $db->prepare("SELECT email, full_name, cell FROM users WHERE id = ?");
            $stmtOwnerDetails->execute([$ownerId]);
            $ownerDetails = $stmtOwnerDetails->fetch(PDO::FETCH_ASSOC);

            $collaboratorName = $user['full_name'] ?: $user['username'] ?: $user['email'];
            $roleName = ((int)$invitation['role_id'] === 2) ? 'Administrador' : 'Empleado';

            if ($ownerDetails) {
                // WhatsApp al owner
                if (!empty($ownerDetails['cell'])) {
                    require_once __DIR__ . '/../../../src/Services/WhatsappService.php';
                    $waSvc = new \App\Services\WhatsappService();
                    $waOk = $waSvc->sendNewCollaboratorAlert(
                        $ownerDetails['cell'],
                        $ownerDetails['full_name'] ?? 'Socio',
                        $collaboratorName,
                        $user['email'],
                        $invName,
                        $roleName
                    );
                    if (!$waOk) {
                        error_log('[accept.php] WA sendNewCollaboratorAlert retornó false.');
                    }
                }

                // Email al owner como notificación de aceptación
                if (!empty($ownerDetails['email'])) {
                    try {
                        require_once __DIR__ . '/../../../src/Services/MailService.php';
                        $mailSvc = new \App\Services\MailService();
                        $mailSvc->sendInvitationEmail(
                            $ownerDetails['email'],
                            $invName,
                            $roleName,
                            'https://' . $_SERVER['HTTP_HOST'] . '/dashboard',
                            $collaboratorName . ' (aceptó la invitación)'
                        );
                    } catch (\Throwable $mailErr) {
                        error_log('[accept.php] Mail al owner falló: ' . $mailErr->getMessage());
                    }
                }
            } else {
                error_log('[accept.php] No se encontraron datos del owner ID: ' . $ownerId);
            }
        } else {
            error_log('[accept.php] No se pudo obtener ownerId para inventario: ' . $invitation['inventory_id']);
        }
    } catch (\Throwable $waErr) {
        error_log('[accept.php] Error en notificación al owner: ' . $waErr->getMessage());
    }

    $db->prepare("UPDATE invitations SET status = 'accepted' WHERE id = ?")->execute([$invitation['id']]);

    try {
        require_once __DIR__ . '/../../../src/helpers/ActivityLogger.php';
        $roleName = ((int)$invitation['role_id'] === 2) ? 'Administrador' : 'Empleado';
        $collaboratorName = $user['full_name'] ?: $user['username'] ?: $user['email'];
        \App\helpers\ActivityLogger::log(
            'Colaboradores',
            'accept_invite',
            'collaborator',
            (string)$user['id'],
            "Aceptó invitación al inventario: " . $collaboratorName,
            "Rol asignado: " . $roleName . " | Email: " . $user['email'],
            (int)$invitation['inventory_id'],
            (int)$user['id'],
            $roleName
        );
    } catch (\Throwable $logErr) {
        error_log('ActivityLogger error in invitations/accept: ' . $logErr->getMessage());
    }
}

// Activar el inventario y redirigir al dashboard
$_SESSION['active_inventory_id'] = $invitation['inventory_id'];
header("Location: /dashboard");
exit;
