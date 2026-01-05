<?php
namespace App\Models;

require_once dirname(__DIR__) . '/core/Database.php';

use App\core\Database;
use PDO;
use Exception;

class SalesModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /* =========================================================
       MÉTODO NUEVO: HISTORIAL ROBUSTO (Estilo Compras)
       ========================================================= */
    public function getHistory($userId, $order = 'DESC'): array
    {
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        try {
            // USAMOS SUBCONSULTAS PARA EVITAR QUE EL 'LEFT JOIN' ROMPA LOS DATOS
            // Y RENOMBRAMOS LAS COLUMNAS PARA QUE COINCIDAN CON LO QUE ESPERA JS
            $sql = "
                SELECT 
                    s.id,
                    s.sale_date as created_at,  -- Alias directo para JS
                    s.total_amount as total,    -- Alias directo para JS
                    s.commission_amount as commission,
                    
                    -- 1. Nombre Cliente
                    COALESCE(c.full_name, 'Cliente General') as customer_name,
                    
                    -- 2. Nombre Vendedor (Busca en Empleados -> Usuarios -> ID)
                    COALESCE(
                        (SELECT full_name FROM employees WHERE id = s.seller_id),
                        (SELECT full_name FROM users WHERE id = s.seller_id),
                        CASE WHEN s.seller_id IS NOT NULL THEN CONCAT('ID: ', s.seller_id) ELSE NULL END,
                        '-'
                    ) as seller_name,

                    -- 3. Métodos de Pago (Lista separada por coma)
                    (
                        SELECT GROUP_CONCAT(pm.name SEPARATOR ', ')
                        FROM sale_payments sp
                        JOIN payment_methods pm ON sp.payment_method_id = pm.id
                        WHERE sp.sale_id = s.id
                    ) as payment_methods_str

                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE s.user_id = :user
                ORDER BY s.sale_date $order
                LIMIT 100
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user' => $userId]);

            // Procesamos los pagos aquí mismo para devolver un array limpio
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(function($row) {
                // Convertir string de pagos en array
                $row['payments'] = !empty($row['payment_methods_str']) ? explode(', ', $row['payment_methods_str']) : [];
                // Asegurar tipos numéricos
                $row['total'] = (float)$row['total'];
                $row['commission'] = (float)$row['commission'];
                return $row;
            }, $results);

        } catch (Exception $e) {
            error_log("SalesModel::getHistory Error: " . $e->getMessage());
            return [];
        }
    }

    /* =========================================================
       HELPERS Y MÉTODOS EXISTENTES (Preservados)
       ========================================================= */

    private function getInventoryContext($userId) {
        $stmt = $this->db->prepare("SELECT i.id as inventory_id, i.preferences, t.table_name FROM inventories i JOIN user_tables t ON i.id = t.inventory_id WHERE i.user_id = :uid ORDER BY i.created_at DESC LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$res) throw new Exception("No se encontró un inventario activo.");
        $prefs = json_decode($res['preferences'], true);
        $stockCol = $prefs['mapping']['stock'] ?? null;
        if (!$stockCol) throw new Exception("Error Config: Columna Stock no definida.");
        return ['inventory_id' => $res['inventory_id'], 'table' => "`" . str_replace("`", "``", $res['table_name']) . "`", 'stock_col' => "`" . str_replace("`", "``", $stockCol) . "`"];
    }

    public function createSale($userId, $data): bool|string {
        try {
            $this->db->beginTransaction();

            // Contexto del inventario (solo si hay items de stock)
            $ctx = null;
            try { $ctx = $this->getInventoryContext($userId); } catch(Exception $e) { /* Si no hay inventario, permitimos venta manual igual */ }

            $dynamicTable = $ctx ? $ctx['table'] : null;
            $stockCol = $ctx ? $ctx['stock_col'] : null;
            $inventoryId = $ctx ? $ctx['inventory_id'] : null;

            // 1. VALIDAR STOCK (Solo para productos reales con ID)
            if (!empty($data['items']) && $dynamicTable) {
                $checkStock = $this->db->prepare("SELECT $stockCol FROM $dynamicTable WHERE id = :id");
                foreach ($data['items'] as $item) {
                    // Si tiene ID, es un producto de inventario -> Validamos Stock
                    if (!empty($item['id'])) {
                        $checkStock->execute([':id' => $item['id']]);
                        $current = $checkStock->fetchColumn();
                        if ($current === false) throw new Exception("Prod ID {$item['id']} no existe.");
                        if ($current < $item['cantidad']) throw new Exception("Stock insuficiente para '{$item['nombre']}'");
                    }
                }
            }

            // 2. INSERTAR CABECERA VENTA
            $stmt = $this->db->prepare("INSERT INTO sales (user_id, customer_id, seller_id, total_amount, commission_amount, notes, sale_date) VALUES (:user, :cust, :seller, :total, :comm, :notes, NOW())");
            $stmt->execute([
                ':user' => $userId,
                ':cust' => $data['customer_id'] ?: null,
                ':seller' => $data['seller_id'] ?: null,
                ':total' => $data['total_final'],
                ':comm' => $data['commission_amount'] ?: 0,
                ':notes' => $data['notes'] ?? null
            ]);
            $saleId = $this->db->lastInsertId();

            // 3. INSERTAR ITEMS
            if (!empty($data['items'])) {
                // Preparamos consultas
                $stmtDet = $this->db->prepare("INSERT INTO sale_items (sale_id, inventory_id, item_id, product_name, quantity, unit_price, total_price) VALUES (:sid, :inv, :pid, :name, :qty, :price, :sub)");

                if ($dynamicTable) {
                    $stmtStock = $this->db->prepare("UPDATE $dynamicTable SET $stockCol = $stockCol - :qty WHERE id = :pid");
                }

                foreach ($data['items'] as $item) {
                    // Es producto real?
                    $isRealProduct = !empty($item['id']);

                    $stmtDet->execute([
                        ':sid' => $saleId,
                        ':inv' => $isRealProduct ? $inventoryId : null, // NULL si es manual
                        ':pid' => $isRealProduct ? $item['id'] : null,  // NULL si es manual
                        ':name' => $item['nombre'],
                        ':qty' => $item['cantidad'],
                        ':price' => $item['precio'],
                        ':sub' => $item['subtotal']
                    ]);

                    // Solo descontamos stock si es producto real
                    if ($isRealProduct && $dynamicTable) {
                        $stmtStock->execute([':qty' => $item['cantidad'], ':pid' => $item['id']]);
                    }
                }
            }

            // 4. INSERTAR PAGOS
            if (!empty($data['payments'])) {
                $checkTable = $this->db->query("SHOW TABLES LIKE 'sale_payments'");
                if($checkTable->rowCount() > 0) {
                    $stmtPay = $this->db->prepare("INSERT INTO sale_payments (sale_id, payment_method_id, amount, surcharge_amount) VALUES (:sid, :pmid, :amt, :sur)");
                    foreach ($data['payments'] as $pay) {
                        $stmtPay->execute([':sid'=>$saleId,':pmid'=>$pay['method_id'],':amt'=>$pay['amount'],':sur'=>$pay['surcharge_val']]);
                    }
                }
            }

            $this->db->commit();
            return $saleId;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function resetIds(): bool
    {
        try {
            $sql = "
                SET @count = 0;
                UPDATE sales SET id = @count:= @count + 1 ORDER BY sale_date ASC;
                ALTER TABLE sales AUTO_INCREMENT = 1;
            ";
            $this->db->exec($sql);
            return true;
        } catch (Exception $e) {
            error_log("Error resetIds: " . $e->getMessage());
            return false;
        }
    }

    public function updateSale($id, $userId, $data): bool {
        try {
            $stmt = $this->db->prepare("UPDATE sales SET customer_id = :cust WHERE id = :id AND user_id = :user");
            return $stmt->execute([':id' => $id, ':user' => $userId, ':cust' => !empty($data['customer_id']) ? $data['customer_id'] : null]);
        } catch (Exception $e) { return false; }
    }

    public function deleteSale($id, $userId): bool {
        try {
            $this->db->beginTransaction();
            $stmtGetItems = $this->db->prepare("SELECT item_id, quantity FROM sale_items WHERE sale_id = :id");
            $stmtGetItems->execute([':id' => $id]);
            $items = $stmtGetItems->fetchAll(PDO::FETCH_ASSOC);

            // Recuperar contexto (asumimos el activo para devolver stock)
            try {
                $ctx = $this->getInventoryContext($userId);
                $table = $ctx['table']; $stockCol = $ctx['stock_col'];
                foreach ($items as $item) {
                    $this->db->exec("UPDATE $table SET $stockCol = $stockCol + {$item['quantity']} WHERE id = {$item['item_id']}");
                }
            } catch(Exception $e) { /* Si falla contexto, borramos igual la venta */ }

            $stmtDel = $this->db->prepare("DELETE FROM sales WHERE id = :id AND user_id = :user");
            $stmtDel->execute([':id' => $id, ':user' => $userId]);
            if ($stmtDel->rowCount() > 0) { $this->db->commit(); return true; }
            $this->db->rollBack(); return false;
        } catch (Exception $e) { $this->db->rollBack(); return false; }
    }

    public function getDetails($saleId, $userId): ?array {
        try {
            $stmt = $this->db->prepare("SELECT s.*, c.full_name as customer_name, (SELECT full_name FROM employees WHERE id = s.seller_id) as seller_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.id = :id AND s.user_id = :user");
            $stmt->execute([':id' => $saleId, ':user' => $userId]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sale) return null;
            $stmtDet = $this->db->prepare("SELECT * FROM sale_items WHERE sale_id = :id");
            $stmtDet->execute([':id' => $saleId]);
            $items = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
            $stmtPay = $this->db->prepare("SELECT sp.*, pm.name as payment_method_name FROM sale_payments sp LEFT JOIN payment_methods pm ON sp.payment_method_id = pm.id WHERE sp.sale_id = :id");
            $stmtPay->execute([':id' => $saleId]);
            $sale['payments'] = $stmtPay->fetchAll(PDO::FETCH_ASSOC);
            return ['sale' => $sale, 'items' => $items];
        } catch (Exception $e) { return null; }
    }
}