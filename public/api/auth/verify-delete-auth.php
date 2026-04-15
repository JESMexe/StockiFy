<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\Models\UserModel;
use App\Services\MailService;

header('Content-Type: application/json; charset=UTF-8');

function jsonResp(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResp(405, ['success' => false, 'message' => 'Método no permitido.']);
}

$user = getCurrentUser();
if (!$user) {
    jsonResp(401, ['success' => false, 'message' => 'No autenticado.']);
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim((string)($input['action'] ?? ''));

$userId      = (int)$user['id'];
$isGoogleUser = !empty($user['google_id']);
$userModel   = new UserModel();

switch ($action) {

    // ─── Step 1: ¿Qué tipo de verificación necesita este usuario? ───────────
    case 'check_type':
        $emailParts  = explode('@', (string)$user['email']);
        $maskedEmail = substr($emailParts[0], 0, 3) . '***@' . ($emailParts[1] ?? '');
        jsonResp(200, [
            'success'     => true,
            'auth_type'   => $isGoogleUser ? 'google' : 'password',
            'email_hint'  => $maskedEmail,
        ]);

    // ─── Step 2a: Enviar OTP por correo (todos los usuarios) ────────────────
    case 'send_otp':
        if (!$userModel->canRequestPasswordOtp($userId, 60)) {
            jsonResp(429, [
                'success' => false,
                'message' => 'Ya enviamos un código recientemente. Esperá un minuto antes de solicitar otro.',
            ]);
        }

        try {
            $otp = (string) random_int(100000, 999999);
        } catch (\Exception $e) {
            jsonResp(500, ['success' => false, 'message' => 'No se pudo generar el código de seguridad.']);
        }

        $expiresAt = (new DateTimeImmutable('+10 minutes'))->format('Y-m-d H:i:s');
        $saved = $userModel->setOtp($userId, $otp, 'delete_inventory', $expiresAt);

        if (!$saved) {
            jsonResp(500, ['success' => false, 'message' => 'No se pudo guardar el código.']);
        }

        $userFullName = trim((string)($user['full_name'] ?? $user['username'] ?? 'Usuario'));
        $mailService  = new MailService();
        $sent = $mailService->sendSecurityOTP(
            (string)$user['email'],
            $otp,
            'delete_inventory',
            $userFullName
        );

        if (!$sent) {
            $userModel->clearPasswordOtp($userId);
            jsonResp(500, ['success' => false, 'message' => 'No se pudo enviar el correo de verificación.']);
        }

        jsonResp(200, ['success' => true, 'message' => 'Código de seguridad enviado a tu correo.']);

    // ─── Step 2b: Verificar contraseña (solo usuarios con email+password) ───
    case 'verify_password':
        if ($isGoogleUser) {
            jsonResp(400, ['success' => false, 'message' => 'Los usuarios de Google no pueden verificar por contraseña.']);
        }

        $password = (string)($input['password'] ?? '');
        if ($password === '') {
            jsonResp(422, ['success' => false, 'message' => 'La contraseña no puede estar vacía.']);
        }

        if (!password_verify($password, (string)$user['password_hash'])) {
            jsonResp(403, ['success' => false, 'message' => 'Contraseña incorrecta.']);
        }

        // Contraseña OK → marcar en sesión que pasó la primera verificación
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['delete_auth_password_ok'] = true;

        jsonResp(200, ['success' => true, 'message' => 'Contraseña verificada.']);

    // ─── Step 3: Verificar OTP + emitir token de autorización ───────────────
    case 'verify_otp':
        $otpInput = trim((string)($input['otp'] ?? ''));

        if (!preg_match('/^\d{6}$/', $otpInput)) {
            jsonResp(422, ['success' => false, 'message' => 'El código debe tener exactamente 6 dígitos.']);
        }

        $valid = $userModel->verifyOtp($userId, $otpInput, 'delete_inventory');

        if (!$valid) {
            jsonResp(400, ['success' => false, 'message' => 'El código es inválido, expiró o superó el límite de intentos.']);
        }

        // Para usuarios con contraseña también verificar que pasaron ese step
        if (!$isGoogleUser) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            if (empty($_SESSION['delete_auth_password_ok'])) {
                jsonResp(403, ['success' => false, 'message' => 'Debés verificar tu contraseña antes de ingresar el OTP.']);
            }
            unset($_SESSION['delete_auth_password_ok']);
        }

        // OTP verificado → emitir token de sesión one-time
        $token     = bin2hex(random_bytes(32));
        $activeInv = $_SESSION['active_inventory_id'] ?? null;

        $_SESSION['delete_auth_token'] = [
            'token'        => $token,
            'inventory_id' => $activeInv,
            'expires_at'   => time() + 300, // 5 minutos para usar el token
        ];

        jsonResp(200, ['success' => true, 'message' => 'Identidad verificada. Podés proceder con la eliminación.']);

    default:
        jsonResp(400, ['success' => false, 'message' => "Acción desconocida: '{$action}'."]);
}
