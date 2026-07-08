<?php
/**
 * POST /api/payments/toggle-auto-debit.php
 *
 * Activa o desactiva el débito automático mensual del usuario.
 *
 * Body JSON: { "enabled": true | false }
 * Respuesta: { "success": bool, "message": string, "auto_debit_enabled": bool }
 */

header('Content-Type: application/json');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Services/Payments/MercadoPagoGateway.php';
require_once __DIR__ . '/../../../src/Services/Payments/PaymentService.php';

use App\Services\Payments\MercadoPagoGateway;
use App\Services\Payments\PaymentService;

try {
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
        exit;
    }

    $data    = json_decode(file_get_contents('php://input'), true);
    $enabled = isset($data['enabled']) ? (bool)$data['enabled'] : null;

    if ($enabled === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El campo "enabled" es requerido.']);
        exit;
    }

    // Solo usuarios con suscripción activa pueden activar el auto-débito
    if ($enabled && (int)($user['subscription_active'] ?? 0) === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Necesitás tener una suscripción activa para activar el débito automático.']);
        exit;
    }

    // Si se quiere activar, verificar si ya tiene una suscripción asociada y autorizada en MP
    if ($enabled && empty($user['mp_preapproval_id'])) {
        echo json_encode([
            'success' => false,
            'requires_subscription' => true,
            'message' => 'Para activar la renovación automática, es necesario que configures tu débito automático en Mercado Pago.'
        ]);
        exit;
    }

    $gateway        = new MercadoPagoGateway();
    $paymentService = new PaymentService($gateway);

    $ok = $paymentService->toggleAutoDebit((int)$user['id'], $enabled);

    if ($ok) {
        $msg = $enabled
            ? 'Débito automático activado. Tu plan se renovará mensualmente de forma automática.'
            : 'Débito automático desactivado. Deberás renovar tu plan manualmente cada mes.';

        echo json_encode([
            'success'            => true,
            'message'            => $msg,
            'auto_debit_enabled' => $enabled,
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la configuración de débito automático.']);
    }

} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[toggle-auto-debit.php] Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error inesperado.']);
}
