<?php
/**
 * POST /api/payments/webhook.php?source=mp
 *
 * Endpoint receptor de notificaciones asíncronas de Mercado Pago (Webhooks).
 *
 * SEGURIDAD:
 *   - Lectura del body RAW antes de cualquier procesamiento (integridad del payload)
 *   - Verificación HMAC-SHA256 de la firma en cabecera x-signature
 *   - Control de idempotencia via UNIQUE KEY (gateway, event_id)
 *   - Bloqueos pesimistas FOR UPDATE en la base de datos
 *   - Rate limiting implícito por la propia firma (firmas distintas → rechazadas)
 *
 * IMPORTANTE: Este endpoint NO debe estar protegido por sesión (llegan requests de MP).
 * La autenticación se realiza exclusivamente por firma criptográfica.
 */

// Leer el body RAW inmediatamente, ANTES de cualquier otra operación
// para garantizar que el payload no fue modificado
$rawPayload = file_get_contents('php://input');

// Registro de depuración temporal
file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] PAYLOAD: " . $rawPayload . " | SERVER: " . json_encode($_SERVER) . "\n", FILE_APPEND);

header('Content-Type: application/json');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/Services/Payments/MercadoPagoGateway.php';
require_once __DIR__ . '/../../../src/Services/Payments/PaymentService.php';

use App\Services\Payments\MercadoPagoGateway;
use App\Services\Payments\PaymentService;

// Rechazar métodos que no sean POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Rechazar payloads vacíos
if (empty($rawPayload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Empty payload']);
    exit;
}

// Obtener y normalizar cabeceras HTTP de forma robusta
$headers = [];
if (function_exists('getallheaders')) {
    $headers = getallheaders();
} else {
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_')) {
            $headerKey = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$headerKey] = $value;
        } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
            $headerKey = strtolower(str_replace('_', '-', $key));
            $headers[$headerKey] = $value;
        }
    }
}

try {
    $gateway        = new MercadoPagoGateway();
    $paymentService = new PaymentService($gateway);

    $result = $paymentService->processWebhookEvent($rawPayload, $headers);

    // Mercado Pago espera siempre un HTTP 200 para no reintentar el evento
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => $result['message']]);

} catch (\RuntimeException $e) {
    // Firma inválida o error de verificación → responder 401 para que MP NO reintente
    // (si reintentara con una firma inválida, siempre fallaría)
    error_log('[webhook.php] Verificación fallida: ' . $e->getMessage());
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Webhook verification failed.']);
} catch (\Throwable $e) {
    // Error interno → responder 500 para que MP reintente (puede ser temporal)
    error_log('[webhook.php] Error interno: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal error processing webhook.']);
}
