<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Models/InventoryModel.php';
require_once __DIR__ . '/../../../src/Services/MailService.php';
require_once __DIR__ . '/../../../src/Services/WhatsappService.php';

header('Content-Type: application/json');

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
$inventoryId = $data['inventory_id'] ?? null;

if (empty($inventoryId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Se requiere especificar un inventario.']);
    exit;
}

try {
    $inventoryModel = new \App\Models\InventoryModel();
    
    if (!$inventoryModel->verifyOwnership($inventoryId, $currentUser['id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para acceder a este inventario.']);
        exit;
    }

    $prefs = $inventoryModel->getPreferences($inventoryId);
    if (!isset($prefs['mapping']['stock']) || !isset($prefs['mapping']['name']) || empty($prefs['features']['min_stock'])) {
        throw new Exception("El inventario no tiene configurado el 'Stock Mínimo' o el mapeo de columnas necesario.");
    }

    $colStock = $prefs['mapping']['stock'];
    $colName = $prefs['mapping']['name'];
    $colMin = 'min_stock';

    $invDetails = $inventoryModel->getInventoryById($inventoryId);
    $inventoryName = $invDetails['name'] ?? 'Inventario';

    $db = \App\core\Database::getInstance();
    $tableName = "inventory_" . $inventoryId;
    
    $colStockSafe = preg_replace('/[^a-zA-Z0-9_\ ]/', '', $colStock);
    $colNameSafe = preg_replace('/[^a-zA-Z0-9_\ ]/', '', $colName);
    
    $query = "SELECT `$colNameSafe` as name, `$colStockSafe` as stock, `$colMin` as min_stock 
              FROM `$tableName` 
              WHERE `$colStockSafe` <= `$colMin`";
              
    $stmt = $db->query($query);
    $criticalItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($criticalItems)) {
        echo json_encode(['success' => true, 'message' => 'No hay productos con stock crítico en este momento.']);
        exit;
    }

    $productsList = [];
    foreach ($criticalItems as $item) {
        $stock = (float)$item['stock'];
        $min = (float)$item['min_stock'];
        
        $faltante = $min - $stock;
        if ($faltante <= 0) $faltante = 1;
        
        $productsList[] = [
            'name' => (string)$item['name'],
            'current' => $stock,
            'min' => $min,
            'faltante' => $faltante
        ];
    }

    $mailService = new \App\Services\MailService();
    $mailSent = $mailService->sendRestockReport($currentUser['email'], $currentUser['username'] ?? 'Usuario', $inventoryName, $productsList);

    $whatsappSent = false;
    if (!empty($currentUser['cell'])) {
        $whatsappService = new \App\Services\WhatsappService();
        $whatsappSent = $whatsappService->sendRestockReport($currentUser['cell'], $currentUser['username'] ?? 'Usuario', $inventoryName, $productsList);
    }

    if (!$mailSent && !$whatsappSent) {
        throw new Exception("No se pudo enviar el reporte por ningún canal.");
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Reporte de ' . count($productsList) . ' productos generado y enviado.',
        'results' => [
            'email' => $mailSent,
            'whatsapp' => $whatsappSent
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in send-restock-report.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
