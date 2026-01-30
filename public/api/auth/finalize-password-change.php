<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
use App\Models\UserModel;

header('Content-Type: application/json');
session_start();
$user = getCurrentUser();
$input = json_decode(file_get_contents('php://input'), true);
$otp = $input['code'] ?? '';
$newPass = $input['new_password'] ?? '';

if (strlen($newPass) < 6) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres.']); exit;
}

$userModel = new UserModel();
if ($userModel->verifyOtp($user['id'], $otp)) {
    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    $userModel->updatePassword($user['id'], $newHash);
    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Código incorrecto.']);
}