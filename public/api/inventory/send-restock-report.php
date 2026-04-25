<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/Models/InventoryModel.php';
require_once __DIR__ . '/../../../src/Services/MailService.php';
require_once __DIR__ . '/../../../src/Services/WhatsappService.php';

use App\core\Database;
use App\Models\InventoryModel;

header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// session_start() lo maneja auth_helper con session_status() check

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$currentUser = getCurrentUser();
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$inventoryId = intval($data['inventory_id'] ?? 0);

if (!$inventoryId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Se requiere especificar un inventario.']);
    exit;
}

try {
    $db = Database::getInstance();

    // --- 1. Verificar ownership y obtener el nombre del inventario ---
    $stmtInv = $db->prepare(
        "SELECT i.name, i.preferences, ut.table_name
         FROM inventories i
         JOIN user_tables ut ON ut.inventory_id = i.id
         WHERE i.id = ? AND i.user_id = ?
         LIMIT 1"
    );
    $stmtInv->execute([$inventoryId, $currentUser['id']]);
    $invRow = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if (!$invRow) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para acceder a este inventario.']);
        exit;
    }

    $inventoryName = $invRow['name'];
    $tableName = $invRow['table_name'];
    $prefs = json_decode($invRow['preferences'] ?? '{}', true) ?? [];

    // --- 2. Leer mapping y features desde inventories.preferences ---
    $mapping = $prefs['mapping'] ?? [];
    $features = $prefs['features'] ?? [];

    $colStock = $mapping['stock'] ?? null;
    $colName = $mapping['name'] ?? null;
    $minStockEnabled = !empty($features['min_stock']);

    if (!$colStock || !$colName) {
        throw new Exception("El inventario no tiene configurado el mapeo de columnas (Nombre y Stock). Por favor, configura el inventario primero.");
    }

    if (!$minStockEnabled) {
        throw new Exception("La función de Stock Mínimo no está habilitada en este inventario. Actívala desde la configuración del inventario.");
    }

    // --- 3. Consultar productos en stock crítico ---
    $colStockSafe = '`' . str_replace('`', '``', $colStock) . '`';
    $colNameSafe = '`' . str_replace('`', '``', $colName) . '`';
    $tableSafe = '`' . str_replace('`', '``', $tableName) . '`';

    $query = "SELECT {$colNameSafe} as item_name, {$colStockSafe} as item_stock, min_stock
              FROM {$tableSafe}
              WHERE {$colStockSafe} IS NOT NULL
                AND min_stock IS NOT NULL
                AND CAST({$colStockSafe} AS DECIMAL(10,2)) <= CAST(min_stock AS DECIMAL(10,2))
              ORDER BY (CAST(min_stock AS DECIMAL(10,2)) - CAST({$colStockSafe} AS DECIMAL(10,2))) DESC";

    $stmt = $db->query($query);
    $criticalItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($criticalItems)) {
        echo json_encode(['success' => true, 'message' => 'No hay productos con stock crítico en este momento. ¡Todo en orden!']);
        exit;
    }

    // --- 4. Construir lista de productos ---
    $productsList = [];
    foreach ($criticalItems as $item) {
        $stock = (float) ($item['item_stock'] ?? 0);
        $min = (float) ($item['min_stock'] ?? 0);
        $faltante = max(1, $min - $stock);

        $productsList[] = [
            'name' => (string) ($item['item_name'] ?? 'Producto'),
            'current' => $stock,
            'min' => $min,
            'faltante' => $faltante,
        ];
    }

    // --- 5. Enviar por email ---
    $mailService = new \App\Services\MailService();
    $mailSent = $mailService->sendRestockReport(
        $currentUser['email'],
        $currentUser['username'] ?? 'Usuario',
        $inventoryName,
        $productsList
    );

    // --- 6. Enviar por WhatsApp (si tiene celular configurado) ---
    $whatsappSent = false;
    if (!empty($currentUser['cell'])) {
        $whatsappService = new \App\Services\WhatsappService();
        $whatsappSent = $whatsappService->sendRestockReport(
            $currentUser['cell'],
            $currentUser['username'] ?? 'Usuario',
            $inventoryName,
            $productsList
        );
    }

    if (!$mailSent && !$whatsappSent) {
        throw new Exception("No se pudo enviar el reporte por ningún canal. Verificá la configuración de email y WhatsApp.");
    }

    $channels = array_keys(array_filter(['email' => $mailSent, 'WhatsApp' => $whatsappSent]));
    $channelStr = implode(' y ', $channels);

    echo json_encode([
        'success' => true,
        'message' => 'Reporte de ' . count($productsList) . ' productos enviado por ' . $channelStr . '.',
        'results' => [
            'email' => $mailSent,
            'whatsapp' => $whatsappSent,
        ],
    ]);

} catch (Exception $e) {
    $errMsg = $e->getMessage();
    $errTrace = $e->getTraceAsString();
    error_log("[send-restock-report] ERROR: " . $errMsg . " | TRACE: " . $errTrace);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $errMsg, 'trace' => $errTrace]);
}
?>