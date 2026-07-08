<?php
/**
 * GET /api/cron/process-subscriptions.php?token=CRON_SECRET_TOKEN
 *
 * Cron job para gestionar renovaciones automáticas de suscripciones.
 * Se ejecuta diariamente junto con daily-balance.php.
 *
 * Tareas:
 *   1. Expira suscripciones vencidas (actualiza subscription_active = 0)
 *   2. Detecta usuarios con auto-débito activo y suscripción próxima a vencer
 *      para alertarlos vía email/WhatsApp (Mercado Pago gestiona el cobro automático)
 *   3. Renueva deudas mensuales de slots de colaboradores (usa CollaboratorDebtService)
 *
 * Seguridad: Protegido por CRON_SECRET_TOKEN en .env (igual que los otros cron jobs).
 */

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/core/Database.php';
    require_once $root . '/src/Services/MailService.php';
    require_once $root . '/src/Services/WhatsappService.php';
    require_once $root . '/src/Services/CollaboratorDebtService.php';
    require_once $root . '/src/Services/Payments/PricingService.php';

    // --- Verificación del token secreto ---
    $secretToken = $_ENV['CRON_SECRET_TOKEN'] ?? null;
    if (!$secretToken || !isset($_GET['token']) || $_GET['token'] !== $secretToken) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado. Token inválido.']);
        exit;
    }

    date_default_timezone_set('America/Argentina/Buenos_Aires');
    $db = \App\core\Database::getInstance();
    $db->query("SET time_zone = '-03:00'");

    $results = [
        'expired_plans'    => 0,
        'alerts_sent'      => 0,
        'debts_renewed'    => 0,
        'errors'           => [],
    ];

    // =========================================================================
    // TAREA 1: Expirar suscripciones vencidas
    // =========================================================================
    $stmtExpire = $db->query("
        UPDATE users
        SET subscription_active = 0, mp_auto_debit_enabled = 0
        WHERE subscription_active > 0
          AND subscription_active < 4
          AND subscription_expires_at IS NOT NULL
          AND subscription_expires_at < NOW()
          AND mp_auto_debit_enabled = 0
    ");
    $results['expired_plans'] = $stmtExpire->rowCount();

    // Plan Vitalicio (4) nunca expira - asegurar que no se toque
    // (la columna subscription_active = 4 no entra en la query anterior por el < 4)

    // =========================================================================
    // TAREA 2: Alertas de próximo vencimiento (7 días y 2 días antes)
    // Se notifica el estado del débito automático para evitar sorpresas.
    // =========================================================================
    $stmtExpiring = $db->query("
        SELECT id, email, full_name, cell, subscription_active, subscription_expires_at, mp_auto_debit_enabled,
               DATEDIFF(subscription_expires_at, NOW()) as days_left
        FROM users
        WHERE subscription_active BETWEEN 1 AND 3
          AND subscription_expires_at IS NOT NULL
          AND DATEDIFF(subscription_expires_at, NOW()) IN (2, 7)
          AND (email IS NOT NULL OR cell IS NOT NULL)
    ");
    $expiringUsers = $stmtExpiring->fetchAll(PDO::FETCH_ASSOC);

    $mailService     = new \App\Services\MailService();
    $whatsappService = new \App\Services\WhatsappService();
    $pricingService  = new \App\Services\Payments\PricingService();

    foreach ($expiringUsers as $u) {
        $name      = $u['full_name'] ?: 'Usuario';
        $expiresAt = (new \DateTime($u['subscription_expires_at']))->format('d/m/Y');
        $renewUrl  = 'https://stockify.com.ar/settings?tab=suscripcion';
        $planName  = $pricingService->getPlanName((int)$u['subscription_active']);
        $daysLeft  = (int)$u['days_left'];
        $autoDebitStatusText = $u['mp_auto_debit_enabled'] ? 'ACTIVADO - Se debitará automáticamente' : 'DESACTIVADO - Requiere pago manual';

        // Email (Solo para usuarios sin débito automático)
        if (!empty($u['email']) && !$u['mp_auto_debit_enabled']) {
            try {
                $mailService->sendSubscriptionExpiring($u['email'], $name, $expiresAt, $renewUrl);
                $results['alerts_sent']++;
            } catch (\Throwable $e) {
                $results['errors'][] = "Email para {$u['email']}: " . $e->getMessage();
            }
        }

        // WhatsApp (Para todos los usuarios, informando estado de débito)
        if (!empty($u['cell'])) {
            try {
                $whatsappService->sendSubscriptionExpiringAlert(
                    $u['cell'],
                    $name,
                    $planName,
                    $daysLeft,
                    $expiresAt,
                    $autoDebitStatusText
                );
            } catch (\Throwable $e) {
                $results['errors'][] = "WhatsApp para {$u['cell']}: " . $e->getMessage();
            }
        }
    }

    // =========================================================================
    // TAREA 3: Renovar deudas mensuales de slots de colaboradores
    // =========================================================================
    try {
        $debtSvc = new \App\Services\CollaboratorDebtService();
        $debtSvc->renewMonthlyDebts();
        $results['debts_renewed'] = 1; // El servicio no retorna count, pero registra en log
    } catch (\Throwable $e) {
        $results['errors'][] = 'CollaboratorDebtService::renewMonthlyDebts: ' . $e->getMessage();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Cron de suscripciones ejecutado correctamente.',
        'results' => $results,
        'run_at'  => (new \DateTime())->format('Y-m-d H:i:s'),
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[process-subscriptions.php] Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
