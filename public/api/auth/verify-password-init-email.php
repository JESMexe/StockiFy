<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
use App\Models\UserModel;
use App\Services\MailService;

header('Content-Type: application/json');
session_start();
$user = getCurrentUser();

if (!$user || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403); exit(json_encode(['success'=>false, 'message'=>'No autorizado']));
}

$input = json_decode(file_get_contents('php://input'), true);
$currentPassword = $input['current_password'] ?? '';
$newEmail = $input['new_email'] ?? '';

// 1. Verificar Contraseña Actual
$userModel = new UserModel();
$dbUser = $userModel->findById($user['id']);

if (!password_verify($currentPassword, $dbUser['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta.']);
    exit;
}

// 2. Verificar que el email nuevo no exista
if ($userModel->findByEmail($newEmail)) {
    echo json_encode(['success' => false, 'message' => 'Este email ya está en uso.']);
    exit;
}

// 3. Generar OTP y enviar al NUEVO email
$otp = (string) rand(100000, 999999);
// Guardamos el OTP en la DB asociado al usuario (aunque se envíe al nuevo email, valida al usuario actual)
// TRUCO: Guardamos el "nuevo email" en la sesión temporalmente para validarlo en el paso 2
$_SESSION['temp_new_email'] = $newEmail;
$userModel->setOtp($user['id'], $otp);

$mailService = new MailService();
if ($mailService->sendSecurityOTP($newEmail, $otp, 'email_change')) {
    echo json_encode(['success' => true, 'message' => 'Código enviado al nuevo email.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al enviar el correo.']);
}