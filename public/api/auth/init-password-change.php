<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\Models\UserModel;
use App\Services\MailService;

header('Content-Type: application/json; charset=UTF-8');

function jsonResponse(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $user = getCurrentUser();

    if (!$user || empty($user['id']) || empty($user['email'])) {
        jsonResponse(401, [
            'success' => false,
            'message' => 'Usuario no autenticado.'
        ]);
    }

    $userId = (int)$user['id'];
    $userEmail = trim((string)$user['email']);
    $userFullName = trim((string)($user['full_name'] ?? 'Usuario'));

    if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        error_log("init-password-change: email inválido para user_id={$userId}");
        jsonResponse(400, [
            'success' => false,
            'message' => 'El correo asociado a la cuenta no es válido.'
        ]);
    }

    $userModel = new UserModel();

    $canRequestOtp = $userModel->canRequestPasswordOtp($userId, 60); // 60 segundos entre envíos

    if (!$canRequestOtp) {
        jsonResponse(429, [
            'success' => false,
            'message' => 'Ya enviamos un código recientemente. Esperá un minuto antes de solicitar otro.'
        ]);
    }

    try {
        $otp = (string) random_int(100000, 999999);
    } catch (\Exception $e) {
        error_log('init-password-change: fallo al generar OTP seguro. ' . $e->getMessage());
        jsonResponse(500, [
            'success' => false,
            'message' => 'No se pudo generar el código de seguridad.'
        ]);
    }

    $expiresAt = (new DateTimeImmutable('+10 minutes'))->format('Y-m-d H:i:s');

    $saved = $userModel->setOtp(
        userId: $userId,
        otp: $otp,
        actionType: 'password_change',
        expiresAt: $expiresAt
    );

    if (!$saved) {
        error_log("init-password-change: no se pudo guardar OTP para user_id={$userId}");
        jsonResponse(500, [
            'success' => false,
            'message' => 'No se pudo iniciar el cambio de contraseña.'
        ]);
    }

    $mailService = new MailService();

    $sent = $mailService->sendSecurityOTP(
        $userEmail,
        $otp,
        'password_change',
        $userFullName
    );

    if (!$sent) {
        $userModel->clearPasswordOtp($userId);
        error_log("init-password-change: fallo envío OTP para user_id={$userId}, email={$userEmail}");
        jsonResponse(500, [
            'success' => false,
            'message' => 'No se pudo enviar el correo de verificación.'
        ]);
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Código de seguridad enviado a tu correo actual.'
    ]);

} catch (\Exception $e) {
    error_log('init-password-change: error inesperado. ' . $e->getMessage());
    jsonResponse(500, [
        'success' => false,
        'message' => 'Ocurrió un error inesperado.'
    ]);
} 
