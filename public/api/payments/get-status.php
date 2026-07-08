<?php
/**
 * GET /api/payments/get-status.php
 *
 * Retorna el estado actual de suscripción y pago del usuario autenticado.
 * Usado por el frontend para renderizar la sección "Mi Suscripción" en settings.php.
 *
 * Respuesta:
 * {
 *   "success": true,
 *   "plan_id": int,
 *   "plan_name": string,
 *   "expires_at": string|null,
 *   "is_expired": bool,
 *   "auto_debit_enabled": bool,
 *   "has_subscription": bool,
 *   "plan_price": float|null,
 *   "public_key": string,
 *   "plans": [ { id, name, price, duration_days } ]
 * }
 */

header('Content-Type: application/json');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Services/Payments/MercadoPagoGateway.php';
require_once __DIR__ . '/../../../src/Services/Payments/PaymentService.php';
require_once __DIR__ . '/../../../src/Services/Payments/PricingService.php';

use App\Services\Payments\MercadoPagoGateway;
use App\Services\Payments\PaymentService;
use App\Services\Payments\PricingService;

try {
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado.']);
        exit;
    }

    $gateway        = new MercadoPagoGateway();
    $paymentService = new PaymentService($gateway);
    $pricing        = new PricingService();

    $status = $paymentService->getSubscriptionStatus((int)$user['id']);

    // Construir lista de planes disponibles para mostrar en la UI de plans.php
    $plans = [];
    $planIds = [1, 2, 4]; // Básico, Profesional, Vitalicio (Empresarial es cotización manual)
    foreach ($planIds as $planId) {
        $plans[] = [
            'id'            => $planId,
            'name'          => $pricing->getPlanName($planId),
            'price'         => $pricing->getPlanPrice($planId, (int)$user['id']),
            'duration_days' => PAYMENT_PLAN_DURATION_DAYS[$planId] ?? 30,
            'is_current'    => $planId === $status['plan_id'],
        ];
    }

    echo json_encode(array_merge(
        ['success' => true, 'public_key' => $paymentService->getGatewayPublicKey()],
        $status,
        [
            'plans' => $plans, 
            'slot_price' => $pricing->getSlotUnitPrice()
        ]
    ));

} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[get-status.php] Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener el estado de la suscripción.',
        'debug' => [
            'error' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}
