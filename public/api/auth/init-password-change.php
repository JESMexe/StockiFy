<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
use App\Models\UserModel;
use App\Services\MailService;

header('Content-Type: application/json');
session_start();
$user = getCurrentUser();

$userModel = new UserModel();
$otp = (string) rand(100000, 999999);
$userModel->setOtp($user['id'], $otp);

$mailService = new MailService();
// Enviar al email ACTUAL registrado
if ($mailService->sendSecurityOTP($user['email'], $otp, 'password_change')) {
    echo json_encode(['success' => true, 'message' => 'Código de seguridad enviado a tu correo actual.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo enviar el correo.']);
}