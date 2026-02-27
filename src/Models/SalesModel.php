<?php
namespace App\Models;

require_once dirname(__DIR__) . '/core/Database.php';
require_once __DIR__ . '/InventoryModel.php';

use App\core\Database;
use App\Models\InventoryModel;
use PDO;
use Exception;

class SalesModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /* =========================================================
       HELPERS Y MÉTODOS EXISTENTES (Preservados)
       ========================================================= */

    public function getInventoryContext($userId) {
        $stmt = $this->db->prepare("SELECT i.id as inventory_id, i.preferences, t.table_name FROM inventories i JOIN user_tables t ON i.id = t.inventory_id WHERE i.user_id = :uid ORDER BY i.created_at DESC LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$res) throw new Exception("No se encontró un inventario activo.");
        $prefs = json_decode($res['preferences'], true);
        $stockCol = $prefs['mapping']['stock'] ?? null;
        if (!$stockCol) throw new Exception("Error Config: Columna Stock no definida.");
        return ['inventory_id' => $res['inventory_id'], 'table' => "`" . str_replace("`", "``", $res['table_name']) . "`", 'stock_col' => "`" . str_replace("`", "``", $stockCol) . "`"];
    }

    public function createSale($userId, $inventoryId, $clientId, $data): bool|string
    {
        try {
            if (!$this->db->inTransaction()) $this->db->beginTransaction();

            $sql = "INSERT INTO sales 
                    (user_id, inventory_id, customer_id, seller_id, payment_method_id, sale_date, total_amount, amount_tendered, change_returned, commission_amount, notes, proof_file) 
                    VALUES 
                    (:user, :inv, :client, :seller, :pay_method, NOW(), :total, :tendered, :change, :comm, :notes, :file)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user'       => $userId,
                ':inv'        => $inventoryId,
                ':client'     => $clientId,

                // --- CORRECCIÓN AQUÍ: Usar seller_id o employee_id ---
                ':seller'     => !empty($data['seller_id']) ? $data['seller_id'] : ($data['employee_id'] ?? null),

                ':pay_method' => !empty($data['payment_method_id']) ? $data['payment_method_id'] : null,
                ':total'      => $data['total_final'] ?? $data['total'],
                ':tendered'   => $data['amount_tendered'] ?? 0,
                ':change'     => $data['change_returned'] ?? 0,
                ':comm'       => $data['commission_amount'] ?? 0,
                ':notes'      => $data['notes'] ?? null,
                ':file'       => $data['proof_file'] ?? null
            ]);

            // ... resto del código igual (Items y Pagos) ...
            $saleId = $this->db->lastInsertId();

            // 2. Detalles y Stock
            if (!empty($data['items']) && is_array($data['items'])) {
                $inventoryModel = new InventoryModel(); // Asegúrate de tener esto importado o instanciado
                $stmtDet = $this->db->prepare("
                    INSERT INTO sale_details 
                    (sale_id, product_id, product_name, quantity, unit_price, subtotal) 
                    VALUES (:sid, :pid, :name, :qty, :price, :sub)
                ");

                foreach ($data['items'] as $item) {
                    $productId = !empty($item['id']) ? $item['id'] : null;

                    $stmtDet->execute([
                        ':sid'   => $saleId,
                        ':pid'   => $productId,
                        ':name'  => $item['nombre'] ?? $item['nombre_producto'] ?? 'Item',
                        ':qty'   => $item['cantidad'],
                        ':price' => $item['precio'] ?? $item['precio_unitario'],
                        ':sub'   => ($item['precio'] ?? $item['precio_unitario']) * $item['cantidad']
                    ]);

                    // IMPORTANTE: Decrementar Stock solo si hay ID de producto
                    if ($productId) {
                        $inventoryModel->decreaseStock($userId, $productId, $item['cantidad'], $inventoryId);
                    }
                }
            }

            // 3. Pagos (Tabla detalle)
            if (!empty($data['payments']) && is_array($data['payments'])) {
                $stmtPay = $this->db->prepare("INSERT INTO sale_payments (sale_id, payment_method_id, amount, surcharge) VALUES (:sid, :pmid, :amt, :sur)");
                foreach ($data['payments'] as $pay) {
                    $stmtPay->execute([
                        ':sid' => $saleId,
                        ':pmid' => $pay['method_id'],
                        ':amt' => $pay['amount'],
                        ':sur' => $pay['surcharge_val'] ?? 0
                    ]);
                }
            }

            $this->db->commit();
            return $saleId;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("SalesModel Error: " . $e->getMessage());
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

    public function getHistory($userId, $inventoryId = null, $order = 'DESC'): array
    {
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        try {
            // Agregamos una subconsulta (payment_names_str) para traer los nombres de los pagos
            $sql = "
                SELECT 
                    s.id,
                    s.sale_date as created_at,
                    s.total_amount as total,
                    s.commission_amount as commission,
                    COALESCE(c.full_name, 'Cliente General') as customer_name,
                    COALESCE(
                        (SELECT full_name FROM employees WHERE id = s.seller_id),
                        (SELECT full_name FROM users WHERE id = s.seller_id),
                        '-'
                    ) as seller_name,
                    -- Subconsulta para obtener los métodos de pago separados por '|||'
                    (
                        SELECT GROUP_CONCAT(pm.name SEPARATOR '|||') 
                        FROM sale_payments sp 
                        LEFT JOIN payment_methods pm ON sp.payment_method_id = pm.id 
                        WHERE sp.sale_id = s.id
                    ) as payment_names_str
                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE s.user_id = :user
                " . ($inventoryId ? "AND s.inventory_id = :inv" : "") . "
                ORDER BY s.sale_date $order
                LIMIT 100
            ";

            $stmt = $this->db->prepare($sql);
            $params = [':user' => $userId];
            if ($inventoryId) {
                $params[':inv'] = $inventoryId;
            }
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Procesamos el string para convertirlo en el array que espera tu JS
            foreach ($results as &$row) {
                if (!empty($row['payment_names_str'])) {
                    // Convertimos "Efectivo|||Mercado Pago" -> ['Efectivo', 'Mercado Pago']
                    $row['payments'] = explode('|||', $row['payment_names_str']);
                } else {
                    $row['payments'] = [];
                }
                // Limpiamos la variable temporal para no ensuciar el JSON
                unset($row['payment_names_str']);
            }

            return $results;
        } catch (Exception $e) {
            error_log("Error getHistory: " . $e->getMessage());
            return [];
        }
    }

    public function getDetails($saleId, $userId, $inventoryId = null): ?array {
        try {
            // 1. Cabecera (Traemos datos de la tabla nueva 'sales')
            $stmt = $this->db->prepare("
                SELECT 
                    s.*, 
                    s.sale_date as created_at,         -- JS usa 'created_at'
                    s.total_amount as total_final,     -- JS usa 'total_final'
                    c.full_name as customer_name,
                    c.full_name as nombre_cliente,     -- Compatibilidad
                    COALESCE(e.full_name, u.full_name, 'Sistema') as seller_name
                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN employees e ON s.seller_id = e.id
                LEFT JOIN users u ON s.seller_id = u.id
                WHERE s.id = :id AND s.user_id = :user
                " . ($inventoryId ? " AND s.inventory_id = :inv" : "") . "
            ");
            $params = [':id' => $saleId, ':user' => $userId];
            if ($inventoryId) {
                $params[':inv'] = $inventoryId;
            }
            $stmt->execute($params);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) return null;

            // 2. Items (Buscamos en 'sale_details' y renombramos para JS)
            // Tu JS usa: i.product_name, i.quantity, i.price, i.subtotal
            $stmtDet = $this->db->prepare("
                SELECT 
                    id,
                    sale_id,
                    product_id,
                    product_name, 
                    product_name as nombre,    -- Para edición
                    quantity, 
                    quantity as cantidad,      -- Para edición
                    unit_price as price,       -- <--- IMPORTANTE: JS usa 'price'
                    unit_price as precio,      -- Para edición
                    subtotal
                FROM sale_details 
                WHERE sale_id = :id
            ");
            $stmtDet->execute([':id' => $saleId]);
            $items = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            // 3. Pagos (Tabla 'sale_payments')
            $payments = [];
            try {
                $stmtPay = $this->db->prepare("
                    SELECT sp.*, pm.name as payment_method_name 
                    FROM sale_payments sp
                    LEFT JOIN payment_methods pm ON sp.payment_method_id = pm.id
                    WHERE sp.sale_id = :id
                ");
                $stmtPay->execute([':id' => $saleId]);
                $payments = $stmtPay->fetchAll(PDO::FETCH_ASSOC);
            } catch(Exception $ex) { /* Ignorar si no hay pagos */ }

            return ['sale' => $sale, 'items' => $items, 'payments' => $payments];

        } catch (Exception $e) {
            error_log("Error SalesModel::getDetails: " . $e->getMessage());
            return null;
        }
    }
}