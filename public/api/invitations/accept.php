<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Models/InvitationModel.php';

use App\Models\InvitationModel;

$token = $_GET['token'] ?? null;
if (!$token) {
    die("Token de invitación inválido.");
}

$invModel = new InvitationModel();
$invitation = $invModel->findByToken($token);

if (!$invitation) {
    die("La invitación no existe, ya fue aceptada, o expiró.");
}

$user = getCurrentUser();

if ($user) {
    // Si el usuario ya está logueado, aceptar la invitación al instante
    if ($invModel->acceptInvitation($invitation['id'], $user['id'])) {
        header("Location: /dashboard?invite_success=1");
        exit;
    } else {
        die("Error al vincular tu cuenta con el inventario.");
    }
} else {
    // Si no está logueado, guardar el token en la sesión y redirigir a registro/login
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['pending_invitation_token'] = $token;
    header("Location: /register.html?invite=1");
    exit;
}
