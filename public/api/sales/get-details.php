<?php
// public/api/sales/get-details.php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';

use App\core\Database;

// 1. Auth
$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$saleId = $_GET['id'] ?? null;
if (!$saleId) {
    echo json_encode(['success' => false, 'message' => 'Falta ID de venta']);
    exit;
}

$userId = $user['id'];
$db = Database::getInstance();

try {
    // 2. Obtener Cabecera de Venta (Validando que sea del usuario)
    $stmt = $db->prepare("
        SELECT v.*, c.full_name as nombre_cliente 
        FROM ventas v
        LEFT JOIN customers c ON v.id_cliente = c.id
        WHERE v.id = :id AND v.id_usuario = :user
    ");
    $stmt->execute([':id' => $saleId, ':user' => $userId]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        echo json_encode(['success' => false, 'message' => 'Venta no encontrada o acceso denegado']);
        exit;
    }

    // 3. Obtener Detalles (Productos)
    $stmtDet = $db->prepare("SELECT * FROM detalle_venta WHERE id_venta = :id");
    $stmtDet->execute([':id' => $saleId]);
    $items = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'sale' => $sale,
        'items' => $items
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}