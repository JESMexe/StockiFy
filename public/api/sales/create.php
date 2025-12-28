<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

try {
    // 1. Autenticación
    $user = getCurrentUser();
    if (!$user) throw new Exception("Usuario no autenticado");

    // 2. Recibir datos
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) throw new Exception("Datos inválidos");
    if (empty($data['items'])) throw new Exception("El carrito está vacío");

    // Variables de cabecera
    $customerId = !empty($data['customer_id']) ? $data['customer_id'] : null;
    $sellerId = !empty($data['seller_id']) ? $data['seller_id'] : null;
    $commission = !empty($data['commission_amount']) ? $data['commission_amount'] : 0;
    $totalFinal = $data['total_final'];
    $notes = !empty($data['notes']) ? $data['notes'] : null;
    $userId = $user['id'];

    $db = Database::getInstance();
    $db->beginTransaction();

    try {
        // ---------------------------------------------------------
        // A. OBTENER INFO DEL INVENTARIO (ID y Nombre de Tabla)
        // ---------------------------------------------------------
        // Necesitamos el ID real del inventario (ej: 1) para la FK, y el table_name (ej: inventory_1) para descontar stock.
        $stmtConfig = $db->prepare("
            SELECT i.id as inventory_id, t.table_name 
            FROM inventories i 
            JOIN user_tables t ON i.id = t.inventory_id 
            WHERE i.user_id = ? 
            LIMIT 1
        ");
        $stmtConfig->execute([$userId]);
        $config = $stmtConfig->fetch(PDO::FETCH_ASSOC);

        if(!$config) throw new Exception("Error crítico: No tienes tabla de inventario asignada.");

        $inventoryIdFK = $config['inventory_id']; // El ID para la Foreign Key (ej: 1)
        $inventoryTable = "`" . str_replace("`", "``", $config['table_name']) . "`"; // La tabla real (ej: inventory_5)


        // ---------------------------------------------------------
        // B. INSERTAR VENTA (Encabezado)
        // ---------------------------------------------------------
        $stmt = $db->prepare("
            INSERT INTO sales 
            (user_id, customer_id, seller_id, total_amount, commission_amount, notes, sale_date) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $customerId, $sellerId, $totalFinal, $commission, $notes]);
        $saleId = $db->lastInsertId();


        // ---------------------------------------------------------
        // C. INSERTAR ITEMS (Productos)
        // ---------------------------------------------------------
        // CORRECCIÓN: Insertamos item_id (producto) Y inventory_id (contenedor) por separado.
        $stmtItem = $db->prepare("
            INSERT INTO sale_items 
            (sale_id, item_id, inventory_id, product_name, quantity, unit_price, total_price)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmtUpdateStock = $db->prepare("UPDATE $inventoryTable SET stock = stock - ? WHERE id = ?");

        foreach ($data['items'] as $item) {
            $stmtItem->execute([
                $saleId,
                $item['id'],       // item_id: El ID del producto (ej: 54)
                $inventoryIdFK,    // inventory_id: El ID del inventario (ej: 1) <- ESTO ARREGLA EL ERROR
                $item['nombre'],
                $item['cantidad'],
                $item['precio'],
                $item['subtotal']
            ]);

            // DESCONTAR STOCK
            $stmtUpdateStock->execute([$item['cantidad'], $item['id']]);
        }


        // ---------------------------------------------------------
        // D. INSERTAR PAGOS
        // ---------------------------------------------------------
        if (!empty($data['payments'])) {
            // Verificar si la tabla sale_payments existe antes de insertar (para evitar error si no corriste el SQL)
            // Asumimos que ya existe por tus mensajes anteriores.
            $stmtPay = $db->prepare("
                INSERT INTO sale_payments (sale_id, payment_method_id, amount, surcharge_amount)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($data['payments'] as $pay) {
                $stmtPay->execute([
                    $saleId,
                    $pay['method_id'],
                    $pay['amount'],
                    $pay['surcharge_val'] ?? 0
                ]);
            }
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Venta registrada correctamente', 'sale_id' => $saleId]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}