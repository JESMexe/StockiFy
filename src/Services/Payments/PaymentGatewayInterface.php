<?php
/**
 * PaymentGatewayInterface.php
 *
 * Contrato que debe cumplir cualquier pasarela de pago integrada en StockiFy.
 * Permite intercambiar Mercado Pago por MODO u otro proveedor futuro
 * sin tocar la lógica de negocio de PaymentService.
 */

declare(strict_types=1);

namespace App\Services\Payments;

interface PaymentGatewayInterface
{
    /**
     * Crea una preferencia/intención de pago en la pasarela.
     * Retorna un array con al menos:
     *   - 'checkout_url': URL para redirigir al usuario
     *   - 'preference_id': ID externo de la preferencia
     *   - 'reference_id': ID interno de idempotencia (el mismo que se pasa en $params)
     *
     * @param array $params {
     *   reference_id: string,   // UUID interno de StockiFy para idempotencia
     *   title: string,           // Descripción del ítem a pagar
     *   amount: float,           // Monto en ARS
     *   payer_email: string,     // Email del comprador
     *   back_urls: array,        // URLs de retorno (success, failure, pending)
     *   metadata: array,         // Datos de contexto para el webhook
     * }
     * @return array
     * @throws \RuntimeException si la pasarela no responde o rechaza la solicitud
     */
    public function createPreference(array $params): array;

    /**
     * Crea una suscripción recurrente (pre-aprobación) en la pasarela.
     * Retorna un array con:
     *   - 'subscription_url': URL de activación de la suscripción
     *   - 'preapproval_id': ID externo de la suscripción
     *
     * @param array $params {
     *   reference_id: string,
     *   plan_id: int,
     *   payer_email: string,
     *   amount: float,
     *   back_url: string,
     * }
     * @return array
     */
    public function createSubscription(array $params): array;

    /**
     * Valida criptográficamente la firma del Webhook entrante.
     * Lanza una excepción si la firma es inválida (posible fraude o replay attack).
     * Si es válida, retorna los datos del evento normalizado:
     *   - 'event_id': string   — ID único del evento en la pasarela
     *   - 'type': string       — Tipo de evento ('payment', 'subscription', etc.)
     *   - 'status': string     — Estado final ('approved', 'rejected', 'cancelled', etc.)
     *   - 'reference_id': string — El reference_id interno enviado en la preferencia
     *   - 'gateway_tx_id': string — ID de la transacción en la pasarela
     *   - 'amount': float      — Monto verificado
     *
     * @param string $rawPayload  Body crudo del POST (sin decodificar)
     * @param array  $headers     Cabeceras HTTP del request
     * @return array
     * @throws \RuntimeException si la firma no es válida
     */
    public function verifyAndParseWebhook(string $rawPayload, array $headers): array;

    /**
     * Retorna la Public Key de la pasarela (para usar en el frontend con Checkout Bricks).
     * @return string
     */
    public function getPublicKey(): string;
}
