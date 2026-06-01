<?php
namespace App\Models;

require_once dirname(__DIR__) . '/core/Database.php';
require_once __DIR__ . '/InventoryModel.php';

use App\core\Database;
use App\Models\InventoryModel;
use PDO;
use Exception;

class SalesModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /* =========================================================
       HELPERS Y MÉTODOS EXISTENTES (Preservados)
       ========================================================= */

    public function getInventoryContext($userId)
    {
        $stmt = $this->db->prepare("SELECT i.id as inventory_id, i.preferences, t.table_name FROM inventories i JOIN user_tables t ON i.id = t.inventory_id WHERE i.user_id = :uid ORDER BY i.created_at DESC LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$res)
            throw new Exception("No se encontró un inventario activo.");
        $prefs = json_decode($res['preferences'], true);
        $stockCol = $prefs['mapping']['stock'] ?? null;
        if (!$stockCol)
            throw new Exception("Error Config: Columna Stock no definida.");
        return ['inventory_id' => $res['inventory_id'], 'table' => "`" . str_replace("`", "``", $res['table_name']) . "`", 'stock_col' => "`" . str_replace("`", "``", $stockCol) . "`"];
    }

    public function getInventoryContextById($userId, $inventoryId)
    {
        $stmt = $this->db->prepare("SELECT i.id as inventory_id, i.preferences, t.table_name FROM inventories i JOIN user_tables t ON i.id = t.inventory_id WHERE i.id = :invId AND i.user_id = :uid LIMIT 1");
        $stmt->execute([':invId' => $inventoryId, ':uid' => $userId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$res)
            throw new Exception("No se encontró el inventario especificado.");
        $prefs = json_decode($res['preferences'], true);
        $stockCol = $prefs['mapping']['stock'] ?? null;
        if (!$stockCol)
            throw new Exception("Error Config: Columna Stock no definida.");
        return ['inventory_id' => $res['inventory_id'], 'table' => "`" . str_replace("`", "``", $res['table_name']) . "`", 'stock_col' => "`" . str_replace("`", "``", $stockCol) . "`"];
    }

    public function createSale($userId, $inventoryId, $clientId, $data, array &$outAlerts = []): bool|string
    {
        try {
            if (!$this->db->inTransaction())
                $this->db->beginTransaction();

            $sql = "INSERT INTO sales 
                    (user_id, inventory_id, customer_id, seller_id, payment_method_id, sale_date, total_amount, amount_tendered, change_returned, commission_amount, discount_amount, notes, proof_file) 
                    VALUES 
                    (:user, :inv, :client, :seller, :pay_method, NOW(), :total, :tendered, :change, :comm, :disc, :notes, :file)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user' => $userId,
                ':inv' => $inventoryId,
                ':client' => $clientId,

                // --- CORRECCIÓN AQUÍ: Usar seller_id o employee_id ---
                ':seller' => !empty($data['seller_id']) ? $data['seller_id'] : ($data['employee_id'] ?? null),

                ':pay_method' => !empty($data['payment_method_id']) ? $data['payment_method_id'] : null,
                ':total' => $data['total'],
                ':tendered' => $data['amount_tendered'] ?? 0,
                ':change' => $data['change_returned'] ?? 0,
                ':comm' => $data['commission_amount'] ?? 0,
                ':disc' => $data['discount_amount'] ?? 0,
                ':notes' => $data['notes'] ?? null,
                ':file' => $data['proof_file'] ?? null
            ]);

            // ... resto del código igual (Items y Pagos) ...
            $saleId = $this->db->lastInsertId();

            // 2. Detalles y Stock
            if (!empty($data['items']) && is_array($data['items'])) {
                $inventoryModel = new InventoryModel(); // Asegúrate de tener esto importado o instanciado

                // PREPARAR METADATOS PARA ALERTAS DE GANANCIA
                $stmtInv = $this->db->prepare("SELECT i.preferences, t.table_name FROM inventories i JOIN user_tables t ON i.id = t.inventory_id WHERE i.id = :invId LIMIT 1");
                $stmtInv->execute([':invId' => $inventoryId]);
                $invRow = $stmtInv->fetch(PDO::FETCH_ASSOC);
                $tableName = $invRow ? "`" . str_replace("`", "``", $invRow['table_name']) . "`" : null;
                $prefs = $invRow ? json_decode($invRow['preferences'], true) : [];
                $costCol = $prefs['mapping']['receipt_price'] ?? null;
                $safeCostCol = $costCol ? "`" . str_replace("`", "``", $costCol) . "`" : null;
                $mailSvc = null;

                $stmtDet = $this->db->prepare("
                    INSERT INTO sale_details 
                    (sale_id, product_id, product_name, quantity, unit_price, subtotal) 
                    VALUES (:sid, :pid, :name, :qty, :price, :sub)
                ");

                foreach ($data['items'] as $item) {
                    $productId = !empty($item['id']) ? $item['id'] : null;

                    $stmtDet->execute([
                        ':sid' => $saleId,
                        ':pid' => $productId,
                        ':name' => $item['nombre'] ?? $item['nombre_producto'] ?? 'Item',
                        ':qty' => $item['cantidad'],
                        ':price' => $item['precio'] ?? $item['precio_unitario'],
                        ':sub' => ($item['precio'] ?? $item['precio_unitario']) * $item['cantidad']
                    ]);

                    // IMPORTANTE: Decrementar Stock y Controlar Ganancia
                    if ($productId) {
                        $prodName = $item['nombre'] ?? $item['nombre_producto'] ?? null;
                        $inventoryModel->decreaseStock($userId, $productId, $item['cantidad'], $inventoryId, $prodName, $outAlerts);

                        // ALERTA DE GANANCIA NEGATIVA
                        if ($tableName && $safeCostCol) {
                            $stmtCost = $this->db->prepare("SELECT $safeCostCol as cost FROM $tableName WHERE id = :id");
                            $stmtCost->execute([':id' => $productId]);
                            $costRow = $stmtCost->fetch(PDO::FETCH_ASSOC);

                            if ($costRow && isset($costRow['cost'])) {
                                $cost = (float) $costRow['cost'];
                                $salePrice = (float) ($item['precio'] ?? $item['precio_unitario']);

                                if ($salePrice > 0 && $salePrice < $cost && $cost > 0) {
                                    $prodName = $item['nombre'] ?? $item['nombre_producto'] ?? 'Producto';
                                    $outAlerts[] = [
                                        'type' => 'negative_profit',
                                        'product_name' => $prodName,
                                        'sale_price' => $salePrice,
                                        'cost_price' => $cost
                                    ];
                                }
                            }
                        }
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
            
            try {
                $clientName = 'Consumidor Final';
                if ($clientId) {
                    $stmtClient = $this->db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name, company_name FROM customers WHERE id = ?");
                    $stmtClient->execute([$clientId]);
                    $cRow = $stmtClient->fetch(PDO::FETCH_ASSOC);
                    if ($cRow) {
                        $company = !empty($cRow['company_name']) ? " (" . $cRow['company_name'] . ")" : "";
                        $clientName = trim($cRow['name']) ?: trim($cRow['company_name']) ?: 'Cliente #' . $clientId;
                        if ($clientName !== trim($cRow['company_name'])) {
                            $clientName .= $company;
                        }
                    }
                }

                $itemsList = [];
                if (!empty($data['items'])) {
                    foreach ($data['items'] as $item) {
                        $qty = $item['cantidad'] ?? 1;
                        $name = $item['nombre'] ?? $item['nombre_producto'] ?? 'Producto';
                        $price = $item['precio'] ?? $item['precio_unitario'] ?? 0;
                        $itemsList[] = "{$qty}x {$name} ($" . number_format((float)$price, 2, ',', '.') . ")";
                    }
                }
                $extra = "Cliente: $clientName | Detalle: " . implode(', ', $itemsList);

                require_once __DIR__ . '/../helpers/ActivityLogger.php';
                \App\helpers\ActivityLogger::log(
                    'Ventas',
                    'create',
                    'sale',
                    (string)$saleId,
                    "Registró una venta por $" . number_format((float)($data['total'] ?? 0), 2, ',', '.'),
                    $extra,
                    (int)$inventoryId,
                    (int)$userId
                );
            } catch (\Throwable $logErr) {
                error_log('ActivityLogger error in SalesModel::createSale: ' . $logErr->getMessage());
            }
            
            return $saleId;

        } catch (Exception $e) {
            if ($this->db->inTransaction())
                $this->db->rollBack();
            error_log("SalesModel Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function resetIds(): bool
    {
        try {
            $sql = "
                UPDATE sales
                CROSS JOIN (SELECT @cnt := 0) AS dummy
                SET sales.id = (@cnt := @cnt + 1)
                ORDER BY sale_date ASC
            ";
            $this->db->exec($sql);
            $this->db->exec("ALTER TABLE sales AUTO_INCREMENT = 1");
            return true;
        } catch (Exception $e) {
            error_log("Error resetIds: " . $e->getMessage());
            return false;
        }
    }

    public function updateSale($id, $userId, $data): bool
    {
        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
            }
            
            // Get old customer_id, inventory_id, total_amount
            $stmtOld = $this->db->prepare("SELECT customer_id, inventory_id, total_amount FROM sales WHERE id = :id");
            $stmtOld->execute([':id' => $id]);
            $oldRow = $stmtOld->fetch(PDO::FETCH_ASSOC);
            if (!$oldRow) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                return false;
            }

            $oldClientId = $oldRow['customer_id'];
            $inventoryId = $oldRow['inventory_id'];
            $totalAmount = (float)$oldRow['total_amount'];

            $newClientId = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;

            $stmt = $this->db->prepare("UPDATE sales SET customer_id = :cust WHERE id = :id AND user_id = :user");
            $success = $stmt->execute([':id' => $id, ':user' => $userId, ':cust' => $newClientId]);

            if ($success) {
                $this->db->commit();
                
                try {
                    $oldClientName = 'Consumidor Final';
                    if ($oldClientId) {
                        $stmtC = $this->db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name, company_name FROM customers WHERE id = ?");
                        $stmtC->execute([$oldClientId]);
                        $c = $stmtC->fetch(PDO::FETCH_ASSOC);
                        if ($c) {
                            $company = !empty($c['company_name']) ? " (" . $c['company_name'] . ")" : "";
                            $oldClientName = trim($c['name']) ?: trim($c['company_name']) ?: 'Cliente #' . $oldClientId;
                            if ($oldClientName !== trim($c['company_name'])) {
                                $oldClientName .= $company;
                            }
                        }
                    }

                    $newClientName = 'Consumidor Final';
                    if ($newClientId) {
                        $stmtC = $this->db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name, company_name FROM customers WHERE id = ?");
                        $stmtC->execute([$newClientId]);
                        $c = $stmtC->fetch(PDO::FETCH_ASSOC);
                        if ($c) {
                            $company = !empty($c['company_name']) ? " (" . $c['company_name'] . ")" : "";
                            $newClientName = trim($c['name']) ?: trim($c['company_name']) ?: 'Cliente #' . $newClientId;
                            if ($newClientName !== trim($c['company_name'])) {
                                $newClientName .= $company;
                            }
                        }
                    }

                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Ventas',
                        'update',
                        'sale',
                        (string)$id,
                        "Editó cliente de venta (ID: $id) por $" . number_format($totalAmount, 2, ',', '.'),
                        "Cliente anterior: $oldClientName | Cliente nuevo: $newClientName",
                        (int)$inventoryId,
                        (int)$userId
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in SalesModel::updateSale: ' . $logErr->getMessage());
                }

                return true;
            }
            $this->db->rollBack();
            return false;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return false;
        }
    }

    public function deleteSale($id, $userId): bool
    {
        try {
            $this->db->beginTransaction();
            $stmtGetItems = $this->db->prepare("SELECT product_id as item_id, quantity FROM sale_details WHERE sale_id = :id");
            $stmtGetItems->execute([':id' => $id]);
            $items = $stmtGetItems->fetchAll(PDO::FETCH_ASSOC);

            // Fetch extra info before deleting the sale
            $stmtGetSale = $this->db->prepare("SELECT total_amount, customer_id, inventory_id FROM sales WHERE id = :id");
            $stmtGetSale->execute([':id' => $id]);
            $saleInfo = $stmtGetSale->fetch(PDO::FETCH_ASSOC);
            $totalAmount = $saleInfo ? (float)$saleInfo['total_amount'] : 0.0;
            $clientId = $saleInfo ? $saleInfo['customer_id'] : null;
            $correctInvId = $saleInfo ? $saleInfo['inventory_id'] : null;

            $clientName = 'Consumidor Final';
            if ($clientId) {
                $stmtClient = $this->db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name, company_name FROM customers WHERE id = ?");
                $stmtClient->execute([$clientId]);
                $cRow = $stmtClient->fetch(PDO::FETCH_ASSOC);
                if ($cRow) {
                    $company = !empty($cRow['company_name']) ? " (" . $cRow['company_name'] . ")" : "";
                    $clientName = trim($cRow['name']) ?: trim($cRow['company_name']) ?: 'Cliente #' . $clientId;
                    if ($clientName !== trim($cRow['company_name'])) {
                        $clientName .= $company;
                    }
                }
            }

            $stmtGetDetails = $this->db->prepare("SELECT product_name, quantity, unit_price FROM sale_details WHERE sale_id = :id");
            $stmtGetDetails->execute([':id' => $id]);
            $detailsRows = $stmtGetDetails->fetchAll(PDO::FETCH_ASSOC);
            $itemsList = [];
            foreach ($detailsRows as $row) {
                $qty = $row['quantity'] ?? 1;
                $name = $row['product_name'] ?? 'Producto';
                $price = $row['unit_price'] ?? 0;
                $itemsList[] = "{$qty}x {$name} ($" . number_format((float)$price, 2, ',', '.') . ")";
            }
            $extra = "Cliente original: $clientName | Detalle: " . implode(', ', $itemsList);

            // Recuperar contexto (inventario de la venta) para devolver stock
            try {
                if ($correctInvId) {
                    $ctx = $this->getInventoryContextById($userId, $correctInvId);
                    $table = $ctx['table'];
                    $stockCol = $ctx['stock_col'];
                    
                    $stmtRestore = $this->db->prepare("UPDATE $table SET $stockCol = $stockCol + :qty WHERE id = :pid");
                    foreach ($items as $item) {
                        $stmtRestore->execute([':qty' => $item['quantity'], ':pid' => $item['item_id']]);
                    }
                }
            } catch (Exception $e) { /* Si falla contexto, borramos igual la venta */
            }

            $stmtDel = $this->db->prepare("DELETE FROM sales WHERE id = :id AND user_id = :user");
            $stmtDel->execute([':id' => $id, ':user' => $userId]);
            if ($stmtDel->rowCount() > 0) {
                $this->db->commit();
                
                try {
                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Ventas',
                        'delete',
                        'sale',
                        (string)$id,
                        "Eliminó una venta por $" . number_format($totalAmount, 2, ',', '.'),
                        $extra,
                        (int)($correctInvId ?? 0),
                        (int)$userId
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in SalesModel::deleteSale: ' . $logErr->getMessage());
                }

                return true;
            }
            $this->db->rollBack();
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
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

    public function getSalesByEmployee($userId, $inventoryId, $employeeId, $order = 'DESC'): array
    {
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        try {
            $sql = "
                SELECT 
                    s.id,
                    s.sale_date as created_at,
                    s.total_amount as total,
                    s.commission_amount as commission,
                    COALESCE(c.full_name, 'Cliente General') as customer_name,
                    (
                        SELECT GROUP_CONCAT(pm.name SEPARATOR '|||') 
                        FROM sale_payments sp 
                        LEFT JOIN payment_methods pm ON sp.payment_method_id = pm.id 
                        WHERE sp.sale_id = s.id
                    ) as payment_names_str
                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE s.user_id = :user AND s.seller_id = :employee
                " . ($inventoryId ? "AND s.inventory_id = :inv" : "") . "
                ORDER BY s.sale_date $order
                LIMIT 100
            ";

            $stmt = $this->db->prepare($sql);
            $params = [':user' => $userId, ':employee' => $employeeId];
            if ($inventoryId) {
                $params[':inv'] = $inventoryId;
            }
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as &$row) {
                if (!empty($row['payment_names_str'])) {
                    $row['payments'] = explode('|||', $row['payment_names_str']);
                } else {
                    $row['payments'] = [];
                }
                unset($row['payment_names_str']);
            }

            return $results;
        } catch (Exception $e) {
            error_log("Error getSalesByEmployee: " . $e->getMessage());
            return [];
        }
    }

    public function getDetails($saleId, $userId, $inventoryId = null): ?array
    {
        try {
            // 1. Cabecera (Traemos datos de la tabla nueva 'sales')
            $stmt = $this->db->prepare("
                SELECT 
                    s.*, 
                    s.sale_date as created_at,         -- JS usa 'created_at'
                    s.total_amount as total,           -- JS usa 'total'
                    c.full_name as customer_name,
                    c.full_name as nombre_cliente,     -- Compatibilidad
                    c.email as customer_email,
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

            if (!$sale)
                return null;

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
            } catch (Exception $ex) { /* Ignorar si no hay pagos */
            }

            return ['sale' => $sale, 'items' => $items, 'payments' => $payments];

        } catch (Exception $e) {
            error_log("Error SalesModel::getDetails: " . $e->getMessage());
            return null;
        }
    }
}