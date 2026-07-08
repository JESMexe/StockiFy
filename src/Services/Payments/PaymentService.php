<?php
/**
 * PaymentService.php
 *
 * Core del motor de pagos de StockiFy.
 * Gestiona el ciclo de vida completo de una transacción:
 *   1. Crear intención de pago (initiateTransaction)
 *   2. Confirmar pago exitoso y aplicar efectos de negocio (confirmPayment)
 *   3. Manejar pagos fallidos o rechazados (failTransaction)
 *   4. Manejar cancelación de suscripción automática (cancelSubscription)
 *
 * Esta clase es AGNÓSTICA de la pasarela: recibe un PaymentGatewayInterface.
 * Toda la lógica de negocio (activar planes, pagar deudas) vive aquí.
 */

declare(strict_types=1);

namespace App\Services\Payments;

use App\core\Database;
use App\helpers\ActivityLogger;
use PDO;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../../config/payment_config.php';
require_once __DIR__ . '/PaymentGatewayInterface.php';
require_once __DIR__ . '/PricingService.php';

class PaymentService
{
    private PDO $db;
    private PaymentGatewayInterface $gateway;
    private PricingService $pricing;

    public function __construct(PaymentGatewayInterface $gateway)
    {
        $this->db      = Database::getInstance();
        $this->gateway = $gateway;
        $this->pricing = new PricingService();
    }

    // =========================================================================
    // SECCIÓN: Inicio de Pago
    // =========================================================================

    /**
     * Inicia una intención de pago manual (única).
     * Registra la transacción como 'pending' y genera la URL de checkout.
     *
     * @param int    $userId      ID del usuario que paga
     * @param string $nature      'plan_activation' | 'collaborator_slots'
     * @param array  $meta        { plan_id?: int, slots_count?: int, debt_id?: int, inventory_id?: int }
     * @param string $payerEmail  Email del pagador (para Mercado Pago)
     * @return array { checkout_url: string, preference_id: string, reference_id: string }
     */
    public function initiateManualPayment(int $userId, string $nature, array $meta, string $payerEmail): array
    {
        // Asegurar que user_id está en la metadata para resolver precios personalizados
        if (!isset($meta['user_id'])) {
            $meta['user_id'] = $userId;
        }

        // 1. Calcular el monto dinámicamente desde PricingService
        $amount    = $this->pricing->resolveAmount($nature, $meta);
        $title     = $this->pricing->buildItemTitle($nature, $meta);
        $inventoryId = (int)($meta['inventory_id'] ?? 0) ?: null;

        // 2. Generar UUID interno para idempotencia (reference_id)
        $referenceId = bin2hex(random_bytes(16));

        // 3. Registrar la transacción en estado 'pending' ANTES de llamar a la pasarela
        $this->persistTransaction([
            'user_id'      => $userId,
            'inventory_id' => $inventoryId,
            'reference_id' => $referenceId,
            'gateway'      => 'mercadopago',
            'amount'       => $amount,
            'nature'       => $nature,
            'metadata'     => $meta,
        ]);

        // 4. Crear la preferencia en Mercado Pago (si falla, la TX queda en 'pending' sin URL)
        $result = $this->gateway->createPreference([
            'reference_id' => $referenceId,
            'title'        => $title,
            'amount'       => $amount,
            'payer_email'  => $payerEmail,
            'back_urls'    => [
                'success' => PAYMENT_BASE_URL . '/settings?payment=success&ref=' . $referenceId,
                'failure' => PAYMENT_BASE_URL . '/settings?payment=failure&ref=' . $referenceId,
                'pending' => PAYMENT_BASE_URL . '/settings?payment=pending&ref=' . $referenceId,
            ],
            'metadata' => array_merge($meta, ['nature' => $nature, 'user_id' => $userId]),
        ]);

        // 5. Guardar el preference_id externo en la transacción
        $this->db->prepare("UPDATE payment_transactions SET gateway_preference_id = ? WHERE reference_id = ?")
                 ->execute([$result['preference_id'], $referenceId]);

        return $result;
    }

    /**
     * Inicia una suscripción recurrente mensual (auto-débito).
     *
     * @param int    $userId
     * @param int    $planId
     * @param string $payerEmail
     * @return array { subscription_url: string, preapproval_id: string }
     */
    public function initiateSubscription(int $userId, int $planId, string $payerEmail): array
    {
        $amount      = $this->pricing->getPlanPrice($planId, $userId);
        $referenceId = bin2hex(random_bytes(16));

        $result = $this->gateway->createSubscription([
            'reference_id' => $referenceId,
            'reason'       => $this->pricing->buildItemTitle('plan_activation', ['plan_id' => $planId]),
            'payer_email'  => $payerEmail,
            'amount'       => $amount,
            'back_url'     => PAYMENT_BASE_URL . '/settings?payment=subscription_success',
        ]);

        // Registrar la transacción de suscripción como pendiente de aprobación
        $this->persistTransaction([
            'user_id'      => $userId,
            'inventory_id' => null,
            'reference_id' => $referenceId,
            'gateway'      => 'mercadopago',
            'amount'       => $amount,
            'nature'       => 'plan_activation',
            'metadata'     => ['plan_id' => $planId, 'type' => 'subscription', 'preapproval_id' => $result['preapproval_id']],
        ]);

        // Persistir el preapproval_id en users para gestionar el auto-débito
        $this->db->prepare("UPDATE users SET mp_preapproval_id = ?, mp_auto_debit_enabled = 1 WHERE id = ?")
                 ->execute([$result['preapproval_id'], $userId]);

        return $result;
    }

    // =========================================================================
    // SECCIÓN: Procesamiento de Webhooks
    // =========================================================================

    /**
     * Procesa un evento de webhook entrante de forma atómica e idempotente.
     * Este es el punto de entrada del webhook endpoint.
     *
     * @param string $rawPayload Body crudo del POST
     * @param array  $headers    Cabeceras HTTP normalizadas a lowercase
     * @return array { processed: bool, message: string }
     */
    public function processWebhookEvent(string $rawPayload, array $headers): array
    {
        // PASO 1: Verificación criptográfica de la firma (HMAC-SHA256)
        // Si la firma es inválida, lanza RuntimeException → el endpoint responde 401
        $event = $this->gateway->verifyAndParseWebhook($rawPayload, $headers);

        $eventId = $event['event_id'];
        $type    = $event['type'];
        $action  = $event['action'];
        $status  = $event['status'];

        // PASO 2: Control de idempotencia — verificar si este evento ya fue procesado
        $stmt = $this->db->prepare("SELECT id, status FROM payment_webhooks_log WHERE gateway = 'mercadopago' AND event_id = ? LIMIT 1");
        $stmt->execute([$eventId]);
        $existingLog = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingLog) {
            // Evento ya registrado → ignorar silenciosamente (responde 200 a MP para que no reintente)
            return ['processed' => true, 'message' => "Evento $eventId ya procesado (idempotencia). Estado: {$existingLog['status']}"];
        }

        // PASO 3: Registrar el evento en el log ANTES de procesar (marca de progreso)
        $this->logWebhookEvent($event, 'processed');

        // PASO 4: Enrutar según tipo de evento
        try {
            if ($type === 'payment' && $status === 'approved') {
                $this->handleApprovedPayment($event);
            } elseif ($type === 'payment' && in_array($status, ['rejected', 'cancelled'])) {
                $this->handleFailedPayment($event);
            } elseif ($type === 'subscription_preapproval' && $action === 'online_offline') {
                // El usuario activó o canceló la suscripción recurrente
                $this->handleSubscriptionStatusChange($event, $status);
            } elseif ($type === 'subscription_authorized_payment' && $status === 'approved') {
                // Cobro automático mensual exitoso de la suscripción
                $this->handleAutoDebitPayment($event);
            }
            // Otros tipos (como test notifications, etc.) se ignoran pero ya fueron logueados
        } catch (\Throwable $e) {
            // Actualizar el log del webhook con el error para auditoría
            $this->db->prepare("UPDATE payment_webhooks_log SET status = 'failed', error_message = ? WHERE event_id = ?")
                     ->execute([$e->getMessage(), $eventId]);
            error_log("[PaymentService] Error procesando webhook $eventId: " . $e->getMessage());
            throw $e; // Re-lanzar para que el endpoint responda 500 y MP reintente
        }

        return ['processed' => true, 'message' => "Evento $eventId procesado correctamente."];
    }

    // =========================================================================
    // SECCIÓN: Handlers de Eventos (privados)
    // =========================================================================

    /**
     * Procesa un pago individual aprobado.
     * Actualiza la transacción e impacta el negocio de forma atómica.
     */
    private function handleApprovedPayment(array $event): void
    {
        $referenceId  = $event['reference_id'];
        $gatewayTxId  = $event['gateway_tx_id'];
        $verifiedAmt  = $event['amount'];

        if (!$referenceId) {
            error_log("[PaymentService] handleApprovedPayment: Sin reference_id en evento {$event['event_id']}");
            return;
        }

        $this->db->beginTransaction();
        try {
            // Bloquear fila para UPDATE con FOR UPDATE (previene race conditions)
            $stmt = $this->db->prepare("SELECT * FROM payment_transactions WHERE reference_id = ? FOR UPDATE");
            $stmt->execute([$referenceId]);
            $tx = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tx) {
                $this->db->rollBack();
                error_log("[PaymentService] Transacción no encontrada: reference_id=$referenceId");
                return;
            }

            // Idempotencia a nivel de transacción: si ya fue procesada, salir
            if ($tx['status'] === 'succeeded') {
                $this->db->rollBack();
                return;
            }

            // Validar que el monto recibido coincide con el registrado (anti-fraude de monto)
            if (abs((float)$tx['amount'] - $verifiedAmt) > 0.01) {
                $this->db->rollBack();
                throw new RuntimeException(
                    "FRAUDE DETECTADO: Monto pagado ($verifiedAmt) ≠ monto registrado ({$tx['amount']}) para reference_id=$referenceId"
                );
            }

            // Actualizar estado de la transacción a 'succeeded'
            $this->db->prepare("
                UPDATE payment_transactions
                SET status = 'succeeded', gateway_transaction_id = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$gatewayTxId, $tx['id']]);

            // Aplicar efecto de negocio según naturaleza del cobro
            $meta = json_decode($tx['metadata'], true) ?? [];
            if ($tx['nature'] === 'plan_activation') {
                $this->activateUserPlan((int)$tx['user_id'], (int)$meta['plan_id']);
            } elseif ($tx['nature'] === 'collaborator_slots') {
                $this->payCollaboratorDebt((int)($meta['debt_id'] ?? 0), (int)$tx['user_id'], (int)($tx['inventory_id'] ?? 0));
            }

            $this->db->commit();

            ActivityLogger::log(
                'Pagos',
                'payment_confirmed',
                'payment_transaction',
                (string)$tx['id'],
                "Pago aprobado: {$tx['nature']} | Monto: $" . number_format((float)$tx['amount'], 2, ',', '.') . ' ARS',
                "Gateway TX ID: $gatewayTxId | Reference: $referenceId",
                (int)($tx['inventory_id'] ?? 0),
                (int)$tx['user_id'],
                'System'
            );
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Marca una transacción como fallida.
     */
    private function handleFailedPayment(array $event): void
    {
        if (!$event['reference_id']) return;

        $this->db->prepare("
            UPDATE payment_transactions
            SET status = 'failed', gateway_transaction_id = ?, updated_at = NOW()
            WHERE reference_id = ? AND status = 'pending'
        ")->execute([$event['gateway_tx_id'], $event['reference_id']]);
    }

    /**
     * Maneja cambios de estado de suscripción recurrente (activación o cancelación).
     */
    private function handleSubscriptionStatusChange(array $event, string $status): void
    {
        $preapprovalId = $event['data_id'];
        if (!$preapprovalId) return;

        if ($status === 'cancelled') {
            // El usuario canceló la suscripción recurrente: desactivar auto-débito
            $this->db->prepare("UPDATE users SET mp_auto_debit_enabled = 0 WHERE mp_preapproval_id = ?")
                     ->execute([$preapprovalId]);
        }
    }

    /**
     * Procesa un cobro automático mensual de una suscripción activa.
     * Renueva el plan del usuario al recibir el cobro de Mercado Pago.
     */
    private function handleAutoDebitPayment(array $event): void
    {
        $preapprovalId = $event['data_id'];
        if (!$preapprovalId) return;

        // Buscar al usuario con este preapproval_id
        $stmt = $this->db->prepare("SELECT id, subscription_active FROM users WHERE mp_preapproval_id = ? AND mp_auto_debit_enabled = 1 LIMIT 1");
        $stmt->execute([$preapprovalId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            error_log("[PaymentService] handleAutoDebitPayment: No se encontró usuario para preapproval_id=$preapprovalId");
            return;
        }

        // Renovar el plan por otro mes
        $planId = (int)$user['subscription_active'];
        if ($planId > 0) {
            $this->activateUserPlan((int)$user['id'], $planId);
            ActivityLogger::log('Pagos', 'auto_debit_renewal', 'user', (string)$user['id'],
                "Renovación automática del Plan ID: $planId vía débito automático.",
                "Preapproval ID: $preapprovalId", 0, (int)$user['id'], 'System');
        }
    }

    // =========================================================================
    // SECCIÓN: Lógica de Negocio (efectos post-pago)
    // =========================================================================

    /**
     * Activa o renueva el plan de suscripción del usuario.
     * Extiende desde HOY (o desde la fecha actual de expiración si ya tiene plan activo).
     */
    private function activateUserPlan(int $userId, int $planId): void
    {
        date_default_timezone_set('America/Argentina/Buenos_Aires');
        $durationDays = PAYMENT_PLAN_DURATION_DAYS[$planId] ?? 30;

        // Extender desde hoy o desde la fecha de expiración actual si es posterior
        $stmt = $this->db->prepare("SELECT subscription_expires_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $baseDate = now_or_future($row['subscription_expires_at'] ?? null);
        $expiresAt = (new \DateTime($baseDate))->modify("+$durationDays days")->format('Y-m-d H:i:s');

        $this->db->prepare("
            UPDATE users
            SET subscription_active = ?, subscription_expires_at = ?
            WHERE id = ?
        ")->execute([$planId, $expiresAt, $userId]);
    }

    /**
     * Liquida una deuda de slots de colaboradores marcándola como 'paid'.
     */
    private function payCollaboratorDebt(int $debtId, int $ownerId, int $inventoryId): void
    {
        if (!$debtId) {
            error_log("[PaymentService] payCollaboratorDebt: debt_id no informado.");
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE collaborator_slots_debts
            SET status = 'paid', updated_at = NOW()
            WHERE id = ? AND owner_id = ? AND status IN ('pending', 'expired')
        ");
        $stmt->execute([$debtId, $ownerId]);

        if ($stmt->rowCount() === 0) {
            error_log("[PaymentService] payCollaboratorDebt: Deuda $debtId no encontrada o ya pagada.");
        }

        ActivityLogger::log('Pagos', 'debt_paid', 'collaborator_debt', (string)$debtId,
            "Deuda de slots de colaboradores saldada vía pago electrónico.",
            "Owner ID: $ownerId | Inventory ID: $inventoryId",
            $inventoryId, $ownerId, 'System');
    }

    // =========================================================================
    // SECCIÓN: Gestión de Auto-Débito
    // =========================================================================

    /**
     * Activa o desactiva el débito automático para un usuario.
     *
     * @param int  $userId
     * @param bool $enabled
     * @return bool
     */
    public function toggleAutoDebit(int $userId, bool $enabled): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET mp_auto_debit_enabled = ? WHERE id = ?");
        return $stmt->execute([(int)$enabled, $userId]);
    }

    /**
     * Retorna el estado actual de suscripción y auto-débito del usuario.
     *
     * @param int $userId
     * @return array
     */
    public function getSubscriptionStatus(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT subscription_active, subscription_expires_at, mp_auto_debit_enabled, mp_preapproval_id, custom_enterprise_price
            FROM users WHERE id = ? LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?? [];

        $planId   = (int)($row['subscription_active'] ?? 0);
        $expiresAt = $row['subscription_expires_at'] ?? null;
        $isExpired = $expiresAt && strtotime($expiresAt) < time();

        // 1. Obtener precios dinámicos desde PricingService
        $planPrice = 0.0;
        try {
            $planPrice = $this->pricing->getPlanPrice($planId, $userId);
        } catch (\InvalidArgumentException $e) {
            // Plan desconocido o nulo (por ejemplo, plan_id = 0)
            $planPrice = 0.0;
        }
        
        $slotUnitPrice = $this->pricing->getSlotUnitPrice();

        // 2. Calcular slots adicionales contratados y su costo total
        $stmtSlots = $this->db->prepare("
            SELECT SUM(slots_added) 
            FROM collaborator_slots_debts 
            WHERE owner_id = ? 
              AND (status = 'paid' OR (status = 'pending' AND created_at >= NOW() - INTERVAL 48 HOUR))
        ");
        $stmtSlots->execute([$userId]);
        $slotsCount = (int)$stmtSlots->fetchColumn();
        
        $slotsTotalPrice = $slotsCount * $slotUnitPrice;
        $totalMonthlyEstimate = $planPrice + $slotsTotalPrice;

        return [
            'plan_id'                 => $planId,
            'plan_name'               => $this->pricing->getPlanName($planId),
            'expires_at'              => $expiresAt,
            'is_expired'              => $isExpired,
            'auto_debit_enabled'      => (bool)($row['mp_auto_debit_enabled'] ?? false),
            'has_subscription'        => !empty($row['mp_preapproval_id']),
            'plan_price'              => $planPrice,
            'custom_enterprise_price' => $row['custom_enterprise_price'] !== null ? (float)$row['custom_enterprise_price'] : null,
            'slots_count'             => $slotsCount,
            'slots_unit_price'        => $slotUnitPrice,
            'slots_total_price'       => $slotsTotalPrice,
            'total_monthly_estimate'  => $totalMonthlyEstimate,
        ];
    }

    // =========================================================================
    // SECCIÓN: Utilidades internas
    // =========================================================================

    /**
     * Persiste una transacción de pago en estado 'pending'.
     */
    private function persistTransaction(array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO payment_transactions
                (user_id, inventory_id, reference_id, gateway, amount, currency, status, nature, metadata, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
        ");
        $stmt->execute([
            $data['user_id'],
            $data['inventory_id'],
            $data['reference_id'],
            $data['gateway'],
            $data['amount'],
            PAYMENT_CURRENCY,
            $data['nature'],
            json_encode($data['metadata'] ?? []),
        ]);
    }

    /**
     * Registra un evento de webhook en el log de auditoría.
     */
    private function logWebhookEvent(array $event, string $status): void
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO payment_webhooks_log
                (gateway, event_id, payload, headers, status, processed_at)
            VALUES
                ('mercadopago', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $event['event_id'],
            $event['raw_payload'] ?? '{}',
            json_encode($event['raw_headers'] ?? []),
            $status,
        ]);
    }

    /**
     * Retorna la Public Key de la pasarela activa (para el frontend).
     */
    public function getGatewayPublicKey(): string
    {
        return $this->gateway->getPublicKey();
    }
}

/**
 * Helper: retorna 'now' si la fecha es pasada o nula, o la fecha futura si es posterior a hoy.
 * Sirve para extender suscripciones encadenadas correctamente.
 */
function now_or_future(?string $dateStr): string
{
    if (!$dateStr) return 'now';
    $ts = strtotime($dateStr);
    return ($ts !== false && $ts > time()) ? $dateStr : 'now';
}
