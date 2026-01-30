<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../src/bootstrap.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
use App\Models\UserModel;
use App\Services\MailService;

header('Content-Type: application/json');

$user = getCurrentUser();
// IMPORTANTE: Leer el JSON del fetch
$input = json_decode(file_get_contents('php://input'), true);
$newEmail = $input['new_email'] ?? '';

if (!$newEmail || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido.']);
    exit;
}

$userModel = new UserModel();
$otp = (string) rand(100000, 999999);

if ($userModel->setOtp($user['id'], $otp)) {
    $_SESSION['temp_new_email'] = $newEmail;
    $mailService = new MailService();

    // Intentar enviar y capturar si falla
    if ($mailService->sendSecurityOTP($newEmail, $otp, 'email_change')) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'El servidor de correo rechazó el envío.']);
    }
}