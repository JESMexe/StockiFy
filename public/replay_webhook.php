<?php
// public/replay_webhook.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/Payments/MercadoPagoGateway.php';
require_once __DIR__ . '/../src/Services/Payments/PaymentService.php';

use App\Services\Payments\MercadoPagoGateway;
use App\Services\Payments\PaymentService;

// Payload y cabeceras exactas capturadas de la transacción real del usuario en el log
$rawPayload = '{"action":"payment.created","api_version":"v1","data":{"id":"166851503299"},"date_created":"2026-07-07T21:26:40Z","id":134366829633,"live_mode":true,"type":"payment","user_id":"200648001"}';

$headers = [
    "x-signature" => "ts=1783460722,v1=11ac771dad7f2ff91d362e96df36dc39c4f02605ef07f4ef9a8856b9b890b0c7",
    "x-request-id" => "5a8109f6-4419-49e8-9571-4a0cbeab8f03"
];

try {
    $gateway = new MercadoPagoGateway();
    $paymentService = new PaymentService($gateway);
    
    // Procesar el evento usando la lógica real y el gateway con la firma corregida
    $result = $paymentService->processWebhookEvent($rawPayload, $headers);
    
    echo "<h1>Replay de Webhook Exitoso 🎉</h1>";
    echo "<p>El webhook se validó y procesó correctamente en el servidor.</p>";
    echo "<pre>" . print_r($result, true) . "</pre>";
    echo "<p><a href='/check_db_debug.php'>👉 Ver estado de transacciones y suscripción actualizadas</a></p>";
} catch (\Throwable $e) {
    echo "<h1>Error al procesar el replay del webhook</h1>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
