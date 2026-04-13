<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
use App\Models\UserModel;

header('Content-Type: application/json');
session_start();
$user = getCurrentUser();
$input = json_decode(file_get_contents('php://input'), true);
$otp = $input['code'] ?? '';
$newEmail = $_SESSION['temp_new_email'] ?? null;

if (!$newEmail) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada. Reinicia el proceso.']);
    exit;
}

$userModel = new UserModel();
if ($userModel->verifyOtp($user['id'], $otp, 'email_change')) {
    $userModel->updateEmail($user['id'], $newEmail);
    $_SESSION['user_email'] = $newEmail; // Actualizar sesión
    unset($_SESSION['temp_new_email']); // Limpiar
    echo json_encode(['success' => true, 'message' => 'Email actualizado correctamente.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Código incorrecto o expirado.']);
}