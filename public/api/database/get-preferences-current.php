<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';

use App\core\Database;

session_start();

$user = getCurrentUser();
$activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

if (!$user || !$activeInventoryId) {
    echo json_encode(['success' => false, 'message' => 'Sesión o inventario no activo']);
    exit;
}

$db = Database::getInstance();

// RBAC: el acceso ya fue validado al seleccionar el inventario.
// No filtramos por user_id — las preferencias pertenecen al inventario, no al usuario activo.
$stmt = $db->prepare("SELECT preferences, report_enabled FROM inventories WHERE id = ?");
$stmt->execute([$activeInventoryId]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);

if ($inv) {
    $prefs = json_decode($inv['preferences'] ?? '{}', true);

    $response = [
        'success' => true,
        'mapping' => $prefs['mapping'] ?? [
                'name' => null,
                'stock' => null,
                'sale_price' => null,
                'buy_price' => null
            ],
        'features' => $prefs['features'] ?? [],
        'exchange_config' => $prefs['exchange_config'] ?? null,
        'visible_columns' => $prefs['visible_columns'] ?? [],
        'hidden_columns' => $prefs['hidden_columns'] ?? null,
        'column_order' => $prefs['column_order'] ?? [],
        'column_colors' => $prefs['column_colors'] ?? [],
        'report_enabled' => isset($inv['report_enabled']) ? (int)$inv['report_enabled'] : 1
    ];
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => 'Inventario no encontrado']);
}
?>