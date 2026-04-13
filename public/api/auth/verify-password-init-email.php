<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
use App\Models\UserModel;
use App\Services\MailService;

header('Content-Type: application/json');
session_start();
$user = getCurrentUser();

if (!$user || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

$input = json_decode(file_get_contents('php://input'), true);
$currentPassword = $input['current_password'] ?? '';
$newEmail = $input['new_email'] ?? '';

$userModel = new UserModel();
$dbUser = $userModel->findById($user['id']);

if (!password_verify($currentPassword, $dbUser['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta.']);
    exit;
}

if ($userModel->findByEmail($newEmail)) {
    echo json_encode(['success' => false, 'message' => 'Este email ya está en uso.']);
    exit;
}

$otp = (string)rand(100000, 999999);
$_SESSION['temp_new_email'] = $newEmail;
$userModel->setOtp($user['id'], $otp, 'email_change');

$mailService = new MailService();
$userName = $user['full_name'] ?? $user['username'] ?? 'Usuario';
if ($mailService->sendSecurityOTP($newEmail, $otp, 'email_change', $userName)) {
    echo json_encode(['success' => true, 'message' => 'Código enviado al nuevo email.']);
}
else {
    echo json_encode(['success' => false, 'message' => 'Error al enviar el correo.']);
}