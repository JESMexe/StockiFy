<?php
/**
 * MercadoPagoGateway.php
 *
 * Adaptador de Mercado Pago para StockiFy.
 * Implementa PaymentGatewayInterface usando el SDK oficial mercadopago/dx-php v3.
 *
 * Soporta:
 *   - Checkout Bricks (pagos manuales con tarjeta/billetera)
 *   - Suscripciones recurrentes vía Preapproval (débito automático mensual)
 *   - Verificación criptográfica de webhooks con HMAC-SHA256
 */

declare(strict_types=1);

namespace App\Services\Payments;

require_once __DIR__ . '/../../config/payment_config.php';

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\PreApproval\PreApprovalClient;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use RuntimeException;

class MercadoPagoGateway implements PaymentGatewayInterface
{
    public function __construct()
    {
        if (empty(MP_ACCESS_TOKEN)) {
            throw new RuntimeException('MP_ACCESS_TOKEN no configurado en .env');
        }
        MercadoPagoConfig::setAccessToken(MP_ACCESS_TOKEN);
    }

    /**
     * {@inheritdoc}
     *
     * Crea una Preferencia de Pago en Mercado Pago para Checkout Bricks.
     * El reference_id de StockiFy se inyecta en external_reference para correlacionar
     * el webhook con la transacción interna.
     */
    public function createPreference(array $params): array
    {
        $client = new PreferenceClient();

        $preferenceData = [
            'items' => [
                [
                    'id'          => $params['reference_id'],
                    'title'       => $params['title'],
                    'quantity'    => 1,
                    'unit_price'  => (float)$params['amount'],
                    'currency_id' => PAYMENT_CURRENCY,
                ]
            ],
            'payer' => [
                'email' => $params['payer_email'],
            ],
            'external_reference' => $params['reference_id'], // Clave de idempotencia
            'back_urls'          => [
                'success' => $params['back_urls']['success'] ?? PAYMENT_BASE_URL . '/settings?payment=success',
                'failure' => $params['back_urls']['failure'] ?? PAYMENT_BASE_URL . '/settings?payment=failure',
                'pending' => $params['back_urls']['pending'] ?? PAYMENT_BASE_URL . '/settings?payment=pending',
            ],
            'auto_return'        => 'approved',
            'notification_url'   => PAYMENT_BASE_URL . '/api/payments/webhook.php?source=mp',
            'metadata'           => $params['metadata'] ?? [],
            'statement_descriptor' => PAYMENT_APP_NAME,
        ];

        try {
            $preference = $client->create($preferenceData);
        } catch (\Exception $e) {
            error_log('[MercadoPagoGateway] createPreference error: ' . $e->getMessage());
            throw new RuntimeException('Error al crear preferencia de pago en Mercado Pago: ' . $e->getMessage());
        }

        $checkoutUrl = MP_ENV === 'production'
            ? $preference->init_point
            : $preference->sandbox_init_point;

        return [
            'checkout_url'  => $checkoutUrl,
            'preference_id' => $preference->id,
            'reference_id'  => $params['reference_id'],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Crea un plan de pre-aprobación (suscripción recurrente mensual) en Mercado Pago.
     * El usuario aprueba el débito automático una vez y Mercado Pago lo renueva mensualmente.
     */
    public function createSubscription(array $params): array
    {
        $client = new PreApprovalClient();

        $subscriptionData = [
            'reason'              => $params['reason'] ?? (PAYMENT_APP_NAME . ' — Suscripción Mensual'),
            'payer_email'         => $params['payer_email'],
            'auto_recurring'      => [
                'frequency'       => 1,
                'frequency_type'  => 'months',
                'transaction_amount' => (float)$params['amount'],
                'currency_id'     => PAYMENT_CURRENCY,
            ],
            'back_url'            => $params['back_url'] ?? PAYMENT_BASE_URL . '/settings?payment=subscription_success',
            'external_reference'  => $params['reference_id'],
            'status'              => 'pending', // El usuario activa con su aprobación
        ];

        try {
            $preapproval = $client->create($subscriptionData);
        } catch (\Exception $e) {
            error_log('[MercadoPagoGateway] createSubscription error: ' . $e->getMessage());
            throw new RuntimeException('Error al crear suscripción en Mercado Pago: ' . $e->getMessage());
        }

        return [
            'subscription_url' => $preapproval->init_point,
            'preapproval_id'   => $preapproval->id,
            'reference_id'     => $params['reference_id'],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Verifica la firma criptográfica HMAC-SHA256 del webhook de Mercado Pago.
     *
     * Mercado Pago envía en la cabecera x-signature:
     *   ts=<timestamp>,v1=<hmac_hex>
     *
     * El mensaje a firmar es: "id:<event_id>;request-id:<request_id>;ts:<timestamp>"
     * Ref: https://www.mercadopago.com.ar/developers/es/docs/notifications/webhooks
     *
     * @throws RuntimeException si la firma es inválida o el secreto no está configurado
     */
    public function verifyAndParseWebhook(string $rawPayload, array $headers): array
    {
        $secret = MP_WEBHOOK_SECRET;
        if (empty($secret)) {
            throw new RuntimeException('MP_WEBHOOK_SECRET no configurado en .env. No se puede verificar el webhook.');
        }

        // Extraer cabeceras relevantes (keys normalizadas a minúsculas)
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
        $xSignature  = $normalizedHeaders['x-signature']   ?? '';
        $xRequestId  = $normalizedHeaders['x-request-id']  ?? '';

        // Parsear timestamp y hash del header x-signature
        $ts = '';
        $v1 = '';
        foreach (explode(',', $xSignature) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 'ts') $ts = $v;
            if ($k === 'v1') $v1 = $v;
        }

        if (empty($ts) || empty($v1)) {
            throw new RuntimeException('Webhook rechazado: cabecera x-signature malformada.');
        }

        // Obtener el data.id del payload para construir la firma
        $payload = json_decode($rawPayload, true);
        $dataId  = $payload['data']['id'] ?? '';

        // Construir el template de la firma según la documentación oficial de MP
        $signatureTemplate = "id:{$dataId};request-id:{$xRequestId};ts:{$ts};";
        $expectedHash = hash_hmac('sha256', $signatureTemplate, $secret);

        // Comparación segura resistente a timing attacks
        if (!hash_equals($expectedHash, $v1)) {
            error_log("[MercadoPagoGateway] Firma inválida. Expected: $expectedHash | Received: $v1 | Template: $signatureTemplate");
            throw new RuntimeException('Webhook rechazado: firma HMAC-SHA256 inválida.');
        }

        // Firma verificada. Ahora extraer y normalizar el evento.
        $eventType = $payload['type']   ?? 'unknown';
        $eventId   = $payload['id']     ?? $dataId; // ID único del evento
        $action    = $payload['action'] ?? '';

        // Para pagos individuales, obtenemos detalles del pago
        $normalizedEvent = [
            'event_id'     => (string)$eventId,
            'type'         => $eventType,
            'action'       => $action,
            'data_id'      => $dataId,
            'raw_payload'  => $rawPayload,
            'raw_headers'  => $normalizedHeaders,
            'reference_id' => null,
            'gateway_tx_id'=> null,
            'status'       => null,
            'amount'       => 0.0,
        ];

        // Si es un evento de pago, consultamos la API para obtener datos verificados
        if ($eventType === 'payment' && !empty($dataId)) {
            try {
                $paymentClient = new PaymentClient();
                $paymentDetail = $paymentClient->get((int)$dataId);

                $normalizedEvent['reference_id']  = $paymentDetail->external_reference ?? null;
                $normalizedEvent['gateway_tx_id'] = (string)($paymentDetail->id ?? $dataId);
                $normalizedEvent['status']        = $paymentDetail->status ?? null;
                $normalizedEvent['amount']        = (float)($paymentDetail->transaction_amount ?? 0);
            } catch (\Exception $e) {
                error_log('[MercadoPagoGateway] Error al obtener detalles del pago: ' . $e->getMessage());
                // No fallamos aquí para registrar el webhook aunque no podamos obtener detalles
            }
        }

        // Si es un evento de suscripción (preapproval)
        if (in_array($eventType, ['subscription_preapproval', 'subscription_authorized_payment'])) {
            $normalizedEvent['reference_id']  = $payload['data']['id'] ?? null;
            $normalizedEvent['gateway_tx_id'] = $dataId;
            $normalizedEvent['status']        = $payload['data']['status'] ?? null;
        }

        return $normalizedEvent;
    }

    /**
     * {@inheritdoc}
     */
    public function getPublicKey(): string
    {
        return MP_PUBLIC_KEY;
    }
}
