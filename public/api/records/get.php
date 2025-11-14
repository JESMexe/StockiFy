<?php
// Devuelve los registros de la tabla seleccionada (solo tablas permitidas)
require_once __DIR__ . '/../../vendor/autoload.php';

use App\core\Database;

header('Content-Type: application/json');

$allowed = [
    'reg_customers', 'reg_providers', 'reg_users', 'reg_inventories',
    'reg_receipts', 'reg_recitm', 'reg_sales', 'reg_salitm'
];

$table = $_GET['table'] ?? '';
if (!in_array($table, $allowed)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Tabla no permitida']);
    exit;
}

try {
    $pdo = Database::getInstance();
    $stmt = $pdo->query("SELECT * FROM `$table` ORDER BY id DESC LIMIT 200");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true,'data'=>$rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
