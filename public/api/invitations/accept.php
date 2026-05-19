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
    $db->prepare("UPDATE invitations SET status = 'accepted' WHERE id = ?")->execute([$invitation['id']]);
}

// Activar el inventario y redirigir al dashboard
$_SESSION['active_inventory_id'] = $invitation['inventory_id'];
header("Location: /dashboard");
exit;
