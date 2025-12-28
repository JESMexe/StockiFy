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

    /**
     * MAGIA: Busca dinámicamente el nombre real de tu tabla y tu columna de stock.
     */
    private function getInventoryContext($userId) {
        // 1. Buscamos el inventario activo y su nombre de tabla real
        $stmt = $this->db->prepare("
            SELECT i.id as inventory_id, i.preferences, t.table_name 
            FROM inventories i
            JOIN user_tables t ON i.id = t.inventory_id
            WHERE i.user_id = :uid 
            ORDER BY i.created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([':uid' => $userId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$res) throw new Exception("No se encontró un inventario activo. Creá una Base de Datos primero.");

        // 2. Buscamos qué columna definiste como "Stock" en la configuración
        $prefs = json_decode($res['preferences'], true);
        $stockCol = $prefs['mapping']['stock'] ?? null;

        if (!$stockCol) throw new Exception("Error de Configuración: No has identificado la columna de 'Stock'. Ve a Configuración > Identificación de Columnas.");

        return [
            'inventory_id' => $res['inventory_id'],
            'table' => "`" . str_replace("`", "``", $res['table_name']) . "`", // Sanitización (Backticks)
            'stock_col' => "`" . str_replace("`", "``", $stockCol) . "`"      // Sanitización
        ];
    }

    /**
     * Helper para Delete: Obtiene info de una tabla dado su ID de inventario
     */
    private function getContextByInventoryId($inventoryId) {
        $stmt = $this->db->prepare("SELECT table_name FROM user_tables WHERE inventory_id = :id");
        $stmt->execute([':id' => $inventoryId]);
        $tableName = $stmt->fetchColumn();

        $stmt2 = $this->db->prepare("SELECT preferences FROM inventories WHERE id = :id");
        $stmt2->execute([':id' => $inventoryId]);
        $prefsJson = $stmt2->fetchColumn();
        $prefs = json_decode($prefsJson, true);
        $stockCol = $prefs['mapping']['stock'] ?? null;

        if (!$tableName || !$stockCol) return null;

        return [
            'table' => "`" . str_replace("`", "``", $tableName) . "`",
            'stock_col' => "`" . str_replace("`", "``", $stockCol) . "`"
        ];
    }

    public function createSale($userId, $data): bool|string
    {
        try {
            $this->db->beginTransaction();

            // PASO 1: Obtener la tabla real donde están los productos
            $ctx = $this->getInventoryContext($userId);
            $dynamicTable = $ctx['table'];
            $stockCol = $ctx['stock_col'];
            $inventoryId = $ctx['inventory_id'];

            // PASO 2: Validación de Stock (Usando la tabla dinámica)
            if (!empty($data['items'])) {
                $checkStock = $this->db->prepare("SELECT $stockCol FROM $dynamicTable WHERE id = :id");
                foreach ($data['items'] as $item) {
                    $checkStock->execute([':id' => $item['id']]);
                    $current = $checkStock->fetchColumn();

                    if ($current === false) throw new Exception("El producto ID {$item['id']} no existe.");
                    if ($current < $item['cantidad']) {
                        throw new Exception("Stock insuficiente para '{$item['nombre']}'. Disponible: $current");
                    }
                }
            }

            // PASO 3: Insertar Venta
            // Nota: Se agregan columnas seller_id y commission_amount según tu SQL
            $stmt = $this->db->prepare("
                INSERT INTO sales (user_id, customer_id, seller_id, total_amount, commission_amount, sale_date) 
                VALUES (:user, :cust, :seller, :total, :comm, NOW())
            ");

            $stmt->execute([
                ':user'   => $userId,
                ':cust'   => !empty($data['customer_id']) ? $data['customer_id'] : null,
                ':seller' => !empty($data['seller_id']) ? $data['seller_id'] : null,
                ':total'  => $data['total_final'],
                ':comm'   => !empty($data['commission_amount']) ? $data['commission_amount'] : 0
            ]);

            $saleId = $this->db->lastInsertId();

            // PASO 4: Insertar Detalles y RESTAR Stock
            if (!empty($data['items'])) {
                // Usamos 'sale_items' según tu esquema
                $stmtDet = $this->db->prepare("
                    INSERT INTO sale_items (sale_id, inventory_id, item_id, product_name, quantity, unit_price, total_price) 
                    VALUES (:sid, :inv, :pid, :name, :qty, :price, :sub)
                ");

                // Update dinámico del stock
                $stmtStock = $this->db->prepare("UPDATE $dynamicTable SET $stockCol = $stockCol - :qty WHERE id = :pid");

                foreach ($data['items'] as $item) {
                    $stmtDet->execute([
                        ':sid'   => $saleId,
                        ':inv'   => $inventoryId,
                        ':pid'   => $item['id'],
                        ':name'  => $item['nombre'],
                        ':qty'   => $item['cantidad'],
                        ':price' => $item['precio'],
                        ':sub'   => $item['subtotal']
                    ]);

                    $stmtStock->execute([':qty' => $item['cantidad'], ':pid' => $item['id']]);
                }
            }

            // PASO 5: Insertar Pagos (Si ejecutaste el SQL de sale_payments)
            if (!empty($data['payments'])) {
                // Verificamos si existe la tabla antes de insertar para no romper si te olvidaste el SQL
                $checkTable = $this->db->query("SHOW TABLES LIKE 'sale_payments'");
                if($checkTable->rowCount() > 0) {
                    $stmtPay = $this->db->prepare("INSERT INTO sale_payments (sale_id, payment_method_id, amount, surcharge_amount) VALUES (:sid, :pmid, :amt, :sur)");
                    foreach ($data['payments'] as $pay) {
                        $stmtPay->execute([
                            ':sid'  => $saleId,
                            ':pmid' => $pay['method_id'],
                            ':amt'  => $pay['amount'],
                            ':sur'  => $pay['surcharge_val']
                        ]);
                    }
                }
            }

            $this->db->commit();
            return $saleId;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Sale Creation Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateSale($id, $userId, $data): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE sales SET 
                customer_id = :cust,
                sale_date = :date
                WHERE id = :id AND user_id = :user
            ");

            return $stmt->execute([
                ':id'    => $id,
                ':user'  => $userId,
                ':cust'  => !empty($data['customer_id']) ? $data['customer_id'] : null,
                ':date'  => !empty($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function deleteSale($id, $userId): bool
    {
        try {
            $this->db->beginTransaction();

            // 1. Obtener items para devolver stock
            $stmtGetItems = $this->db->prepare("SELECT item_id, quantity, inventory_id FROM sale_items WHERE sale_id = :id");
            $stmtGetItems->execute([':id' => $id]);
            $items = $stmtGetItems->fetchAll(PDO::FETCH_ASSOC);

            // 2. Devolver Stock (Iterando por si la venta tiene items de distintos inventarios, aunque raro)
            foreach ($items as $item) {
                $ctx = $this->getContextByInventoryId($item['inventory_id']);
                if ($ctx) {
                    $table = $ctx['table'];
                    $stockCol = $ctx['stock_col'];
                    // Sumamos (+) al stock
                    $this->db->exec("UPDATE $table SET $stockCol = $stockCol + {$item['quantity']} WHERE id = {$item['item_id']}");
                }
            }

            // 3. Borrar (Cascade se encarga de items y pagos, pero aseguramos)
            $stmtDel = $this->db->prepare("DELETE FROM sales WHERE id = :id AND user_id = :user");
            $stmtDel->execute([':id' => $id, ':user' => $userId]);

            if ($stmtDel->rowCount() > 0) {
                $this->db->commit();
                return true;
            } else {
                $this->db->rollBack();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    // --- MÉTODOS DE LECTURA (Adaptados a tu Schema) ---

    public function getAll($userId, $order = 'DESC'): array
    {
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        try {
            $sql = "
                SELECT s.*, 
                       c.full_name as customer_name
                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE s.user_id = :user
                ORDER BY s.sale_date $order
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getDetails($saleId, $userId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, c.full_name as customer_name
                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE s.id = :id AND s.user_id = :user
            ");
            $stmt->execute([':id' => $saleId, ':user' => $userId]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) return null;

            $stmtDet = $this->db->prepare("SELECT * FROM sale_items WHERE sale_id = :id");
            $stmtDet->execute([':id' => $saleId]);
            $items = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            return ['sale' => $sale, 'items' => $items];
        } catch (Exception $e) {
            return null;
        }
    }
}