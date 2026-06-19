<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Services/CollaboratorDebtService.php';

use App\Services\CollaboratorDebtService;

try {
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado.']);
        exit;
    }

    $inventoryId = (int)($_GET['inventory_id'] ?? $_SESSION['active_inventory_id'] ?? 0);
    if (!$inventoryId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Inventario no seleccionado.']);
        exit;
    }

    // Solo el Owner debe ver las advertencias de deuda
    $myRole = getInventoryRole($user['id'], $inventoryId);
    if (!$myRole || (int)$myRole['role_id'] !== 1) {
        // Para colaboradores no dueños, simplemente retornamos vacío sin tirar error
        echo json_encode(['success' => true, 'debts' => [], 'email' => $user['email']]);
        exit;
    }

    $debtSvc = new CollaboratorDebtService();
    $debts = $debtSvc->getPendingDebtsForInventory($inventoryId);

    // Formatear fechas legibles y calcular segundos restantes
    $formattedDebts = array_map(function($d) {
        return [
            'id' => (int)$d['id'],
            'slots_added' => (int)$d['slots_added'],
            'price_per_slot' => (float)$d['price_per_slot'],
            'total_amount' => (int)$d['slots_added'] * (float)$d['price_per_slot'],
            'created_at' => $d['created_at'],
            'deadline' => $d['deadline'],
            'seconds_left' => (int)$d['seconds_left']
        ];
    }, $debts);

    echo json_encode([
        'success' => true,
        'debts' => $formattedDebts,
        'email' => $user['email']
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    error_log("Error in get-pending-debts.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al consultar deudas pendientes.']);
}
