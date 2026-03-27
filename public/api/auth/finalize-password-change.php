<?php

declare(strict_types = 1)
;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\Models\UserModel;

header('Content-Type: application/json; charset=UTF-8');

function jsonResponse(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $user = getCurrentUser();

    if (!$user || empty($user['id'])) {
        jsonResponse(401, [
            'success' => false,
            'message' => 'Usuario no autenticado.'
        ]);
    }

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput ?: '', true);

    if (!is_array($input)) {
        jsonResponse(400, [
            'success' => false,
            'message' => 'Solicitud inválida.'
        ]);
    }

    $otp = trim((string)($input['code'] ?? ''));
    $newPass = (string)($input['new_password'] ?? '');

    if ($otp === '' || !preg_match('/^\d{6}$/', $otp)) {
        jsonResponse(422, [
            'success' => false,
            'message' => 'El código debe contener exactamente 6 dígitos.'
        ]);
    }

    if (mb_strlen($newPass) < 8) {
        jsonResponse(422, [
            'success' => false,
            'message' => 'La contraseña debe tener al menos 8 caracteres, incluyendo letras y números.'
        ]);
    }

    $userId = (int)$user['id'];
    $userModel = new UserModel();

    $isValidOtp = $userModel->verifyOtp($userId, $otp);

    if (!$isValidOtp) {
        jsonResponse(400, [
            'success' => false,
            'message' => 'El código es inválido, expiró o superó el límite de intentos.'
        ]);
    }

    $newHash = password_hash($newPass, PASSWORD_DEFAULT);

    if ($newHash === false) {
        error_log("finalize-password-change: no se pudo hashear la nueva contraseña para user_id={$userId}");
        jsonResponse(500, [
            'success' => false,
            'message' => 'No se pudo actualizar la contraseña.'
        ]);
    }

    $updated = $userModel->updatePassword($userId, $newHash);

    if (!$updated) {
        error_log("finalize-password-change: fallo updatePassword para user_id={$userId}");
        jsonResponse(500, [
            'success' => false,
            'message' => 'No se pudo actualizar la contraseña.'
        ]);
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Contraseña actualizada correctamente.'
    ]);

}
catch (\Throwable $e) {
    error_log('finalize-password-change: error inesperado. ' . $e->getMessage());
    jsonResponse(500, [
        'success' => false,
        'message' => 'Ocurrió un error inesperado.'
    ]);
}