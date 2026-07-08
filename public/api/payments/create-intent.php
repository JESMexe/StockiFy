<?php
/**
 * POST /api/payments/create-intent.php
 *
 * Endpoint seguro para iniciar una intención de pago.
 * Puede ser invocado desde cualquier sección de la app (omnicanal).
 *
 * Body JSON esperado:
 * {
 *   "nature": "plan_activation" | "collaborator_slots",
 *   "plan_id": int,             // Si nature = plan_activation
 *   "slots_count": int,         // Si nature = collaborator_slots
 *   "debt_id": int,             // Si nature = collaborator_slots (deuda existente)
 *   "inventory_id": int,        // Requerido si nature = collaborator_slots
 *   "subscription": bool        // Si es true, crea suscripción recurrente (auto-débito)
 * }
 *
 * Respuesta:
 * {
 *   "success": true,
 *   "checkout_url": "https://...",
 *   "preference_id": "...",
 *   "reference_id": "..."
 * }
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
    // --- Autenticación ---
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado.']);
        exit;
    }

    // --- Validar método HTTP ---
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
        exit;
    }

    // --- Parsear y validar el body JSON ---
    $rawBody = file_get_contents('php://input');
    $data    = json_decode($rawBody, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Body JSON inválido.']);
        exit;
    }

    $nature      = $data['nature']      ?? '';
    $isSubscription = !empty($data['subscription']);

    // Validar naturaleza del cobro
    if (!in_array($nature, ['plan_activation', 'collaborator_slots'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Naturaleza de cobro '$nature' no válida."]);
        exit;
    }

    // --- Construir metadata según la naturaleza ---
    $meta = [];

    if ($nature === 'plan_activation') {
        $planId = (int)($data['plan_id'] ?? 0);
        if ($planId < 1 || $planId > 4) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'plan_id debe estar entre 1 y 4.']);
            exit;
        }
        // No permitir pagar un plan igual o inferior al actual sin una lógica de downgrade
        $currentPlan = (int)($user['subscription_active'] ?? 0);
        if ($planId !== 4 && $planId < $currentPlan) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No se puede adquirir un plan inferior al actual.']);
            exit;
        }
        $meta = ['plan_id' => $planId];
    }

    if ($nature === 'collaborator_slots') {
        $slotsCount  = (int)($data['slots_count'] ?? 0);
        $debtId      = (int)($data['debt_id'] ?? 0);
        $inventoryId = (int)($data['inventory_id'] ?? 0);
        if ($inventoryId <= 0) {
            $inventoryId = (int)($_SESSION['active_inventory_id'] ?? 0);
        }

        if ($slotsCount <= 0 && $debtId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Debe indicar slots_count o un debt_id válido.']);
            exit;
        }

        if (!$inventoryId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Se requiere inventory_id para pago de slots.']);
            exit;
        }

        // Verificar que el usuario es Owner del inventario
        $myRole = getInventoryRole($user['id'], $inventoryId);
        if (!$myRole || (int)$myRole['role_id'] !== 1) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo el propietario puede pagar slots de colaboradores.']);
            exit;
        }

        // Si se pasa un debt_id, verificar que pertenece al usuario
        if ($debtId > 0) {
            $dbConn = \App\core\Database::getInstance();
            $stmtDebt = $dbConn->prepare("SELECT id, slots_added FROM collaborator_slots_debts WHERE id = ? AND owner_id = ? LIMIT 1");
            $stmtDebt->execute([$debtId, $user['id']]);
            $debt = $stmtDebt->fetch(\PDO::FETCH_ASSOC);
            if (!$debt) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Deuda de slots no encontrada o no te pertenece.']);
                exit;
            }
            $slotsCount = (int)$debt['slots_added']; // Usar la cantidad de la deuda existente
        }

        $meta = ['slots_count' => $slotsCount, 'debt_id' => $debtId, 'inventory_id' => $inventoryId];
    }

    // --- Inicializar el motor de pagos ---
    $gateway        = new MercadoPagoGateway();
    $paymentService = new PaymentService($gateway);

    // --- Crear la intención de pago o suscripción ---
    if ($isSubscription && $nature === 'plan_activation') {
        $result = $paymentService->initiateSubscription(
            (int)$user['id'],
            (int)($meta['plan_id'] ?? 0),
            $user['email']
        );
        // Normalizar clave para el frontend (subscription_url vs checkout_url)
        $result['checkout_url'] = $result['subscription_url'] ?? $result['checkout_url'] ?? '';
    } else {
        $result = $paymentService->initiateManualPayment(
            (int)$user['id'],
            $nature,
            $meta,
            $user['email']
        );
    }

    echo json_encode([
        'success'       => true,
        'checkout_url'  => $result['checkout_url'],
        'preference_id' => $result['preference_id'] ?? $result['preapproval_id'] ?? null,
        'reference_id'  => $result['reference_id'],
        'public_key'    => $paymentService->getGatewayPublicKey(),
    ]);

} catch (\InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (\RuntimeException $e) {
    http_response_code(502);
    error_log('[create-intent.php] RuntimeException: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al conectar con la pasarela de pagos. Intentá de nuevo.']);
} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[create-intent.php] Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error inesperado al procesar el pago.']);
}
