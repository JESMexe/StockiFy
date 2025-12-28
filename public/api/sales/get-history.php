<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

try {
    $db = Database::getInstance();

    // CORRECCIÓN: Usamos LEFT JOIN employees para el seller_name
    $sql = "
        SELECT 
            s.id, 
            DATE_FORMAT(s.sale_date, '%Y-%m-%d %H:%i:%s') as created_at_fmt,
            s.total_amount as total,
            s.commission_amount,
            c.full_name as customer_name,
            e.full_name as seller_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN employees e ON s.seller_id = e.id -- <--- CAMBIO AQUÍ (users -> employees)
        ORDER BY s.sale_date DESC
        LIMIT 50
    ";

    $stmt = $db->query($sql);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cleanSales = [];
    foreach($sales as $s) {
        $cleanSales[] = [
            'id' => $s['id'],
            'created_at' => $s['created_at_fmt'],
            'total' => (float)$s['total'],
            'commission' => (float)($s['commission_amount'] ?? 0),
            'customer_name' => $s['customer_name'] ?? 'Cliente General',
            'seller_name' => $s['seller_name'] ?? '-'
        ];
    }

    echo json_encode(['success' => true, 'sales' => $cleanSales]);

} catch (Exception $e) {
    error_log("Error en get-history.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener historial']);
}