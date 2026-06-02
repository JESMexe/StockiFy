<?php
namespace App\Models;

require_once dirname(__DIR__) . '/core/Database.php';

use App\core\Database;
use PDO;
use Exception;

class PurchaseModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function createPurchase($userId, $data): bool|string
    {
        try {
            $this->db->beginTransaction();

            $inventoryId = $data['inventory_id'] ?? $_SESSION['active_inventory_id'] ?? 0;

            if (!$inventoryId) {
                throw new Exception("No se ha definido un ID de inventario para esta compra.");
            }

            require_once __DIR__ . '/../helpers/auth_helper.php';
            $ownerId = getInventoryOwnerId((int)$inventoryId) ?? $userId;

            $stmt = $this->db->prepare("
                INSERT INTO purchases (user_id, inventory_id, provider_id, total, category, notes, created_at) 
                VALUES (:user, :inventory_id, :prov, :total, :cat, :notes, NOW())
            ");

            $providerId = !empty($data['provider_id']) ? $data['provider_id'] : null;
            $category = !empty($data['category']) ? $data['category'] : null;
            $notes = !empty($data['notes']) ? $data['notes'] : null;

            $stmt->execute([
                ':user' => $ownerId,
                ':inventory_id' => $inventoryId,
                ':prov' => $providerId,
                ':total' => $data['total'],
                ':cat' => $category,
                ':notes' => $notes
            ]);

            $purchaseId = $this->db->lastInsertId();

            if (!empty($data['items']) && is_array($data['items'])) {
                // Instanciar InventoryModel para incrementar stock
                require_once __DIR__ . '/InventoryModel.php';
                $inventoryModel = new \App\Models\InventoryModel();

                $stmtDetail = $this->db->prepare("
                    INSERT INTO purchase_details (purchase_id, product_id, product_name, quantity, unit_price, subtotal)
                    VALUES (:pid, :prod_id, :name, :qty, :price, :subtotal)
                ");

                foreach ($data['items'] as $item) {
                    $stmtDetail->execute([
                        ':pid' => $purchaseId,
                        ':prod_id' => $item['id'],
                        ':name' => $item['nombre_producto'],
                        ':qty' => $item['cantidad'],
                        ':price' => $item['precio_unitario'],
                        ':subtotal' => $item['subtotal']
                    ]);
                    
                    // Incremento estricto del inventario
                    if (!empty($item['id'])) {
                        $inventoryModel->increaseStock($ownerId, $item['id'], $item['cantidad'], $inventoryId);
                    }
                }
            }

            $this->db->commit();

            // Auditoría
            try {
                $providerName = 'Ninguno';
                if ($providerId) {
                    $stmtProv = $this->db->prepare("SELECT name FROM providers WHERE id = ?");
                    $stmtProv->execute([$providerId]);
                    $pName = $stmtProv->fetchColumn();
                    if ($pName) $providerName = $pName;
                }

                $itemsList = [];
                if (!empty($data['items'])) {
                    foreach ($data['items'] as $item) {
                        $qty = $item['cantidad'] ?? 1;
                        $name = $item['nombre_producto'] ?? 'Producto';
                        $price = $item['precio_unitario'] ?? 0;
                        $itemsList[] = "{$qty}x {$name} ($" . number_format((float)$price, 2, ',', '.') . ")";
                    }
                }

                $isGasto = empty($data['items']) && !empty($category);
                $logDesc = $isGasto 
                    ? "Registró un gasto por $" . number_format((float)($data['total'] ?? 0), 2, ',', '.')
                    : "Registró una compra por $" . number_format((float)($data['total'] ?? 0), 2, ',', '.');
                
                $logExtra = $isGasto
                    ? "Categoría: $category | Notas: " . ($notes ?: 'Ninguna')
                    : "Proveedor: $providerName | Detalle: " . (empty($itemsList) ? 'Sin productos' : implode(', ', $itemsList));

                require_once __DIR__ . '/../helpers/ActivityLogger.php';
                \App\helpers\ActivityLogger::log(
                    'Compras',
                    'create',
                    'purchase',
                    (string)$purchaseId,
                    $logDesc,
                    $logExtra,
                    (int)$inventoryId,
                    (int)$userId
                );
            } catch (\Throwable $logErr) {
                error_log('ActivityLogger error in createPurchase: ' . $logErr->getMessage());
            }

            return $purchaseId;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Purchase Creation Error: " . $e->getMessage());
            return false;
        }
    }

    public function updatePurchase($id, $userId, $data): bool
    {
        try {
            $stmtOld = $this->db->prepare("SELECT provider_id, category, notes, total, inventory_id, user_id FROM purchases WHERE id = :id");
            $stmtOld->execute([':id' => $id]);
            $oldRow = $stmtOld->fetch(PDO::FETCH_ASSOC);
            if (!$oldRow) {
                return false;
            }

            $inventoryId = $oldRow['inventory_id'];

            require_once __DIR__ . '/../helpers/auth_helper.php';
            $ownerId = getInventoryOwnerId((int)$inventoryId) ?? $userId;

            if ((int)$oldRow['user_id'] !== (int)$ownerId) {
                return false;
            }

            $sql = "UPDATE purchases SET 
                    provider_id = :prov, 
                    category = :cat, 
                    notes = :notes,
                    created_at = :date
                    WHERE id = :id AND user_id = :user";

            if (isset($data['total'])) {
                $sql = "UPDATE purchases SET 
                        provider_id = :prov, 
                        category = :cat, 
                        notes = :notes,
                        created_at = :date,
                        total = :total
                        WHERE id = :id AND user_id = :user";
            }

            $stmt = $this->db->prepare($sql);

            $params = [
                ':id'    => $id,
                ':user'  => $ownerId,
                ':prov'  => !empty($data['provider_id']) ? $data['provider_id'] : null,
                ':cat'   => !empty($data['category']) ? $data['category'] : null,
                ':notes' => !empty($data['notes']) ? $data['notes'] : null,
                ':date'  => !empty($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s')
            ];

            if (isset($data['total'])) {
                $params[':total'] = $data['total'];
            }

            $success = $stmt->execute($params);
            if ($success) {
                try {
                    $oldTotal = (float)$oldRow['total'];
                    $newTotal = isset($data['total']) ? (float)$data['total'] : $oldTotal;

                    // Get provider names
                    $oldProvName = 'Ninguno';
                    if (!empty($oldRow['provider_id'])) {
                        $stmtP = $this->db->prepare("SELECT name FROM providers WHERE id = ?");
                        $stmtP->execute([$oldRow['provider_id']]);
                        $oldProvName = $stmtP->fetchColumn() ?: 'Ninguno';
                    }

                    $newProvId = !empty($data['provider_id']) ? $data['provider_id'] : null;
                    $newProvName = 'Ninguno';
                    if ($newProvId) {
                        $stmtP = $this->db->prepare("SELECT name FROM providers WHERE id = ?");
                        $stmtP->execute([$newProvId]);
                        $newProvName = $stmtP->fetchColumn() ?: 'Ninguno';
                    }

                    $isGasto = empty($oldRow['provider_id']) && !empty($oldRow['category']);
                    $typeStr = $isGasto ? "gasto" : "compra";

                    $desc = "Editó " . $typeStr . " (ID: $id) por $" . number_format($newTotal, 2, ',', '.');
                    $extra = "Anterior - Total: $" . number_format($oldTotal, 2, ',', '.') . ", Proveedor: $oldProvName, Categoría: " . ($oldRow['category'] ?: 'Ninguna') . ", Notas: " . ($oldRow['notes'] ?: 'Ninguna') . " | " .
                             "Nuevo - Total: $" . number_format($newTotal, 2, ',', '.') . ", Proveedor: $newProvName, Categoría: " . ($data['category'] ?: 'Ninguna') . ", Notas: " . ($data['notes'] ?: 'Ninguna');

                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Compras',
                        'update',
                        'purchase',
                        (string)$id,
                        $desc,
                        $extra,
                        (int)$inventoryId,
                        (int)$userId
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in PurchaseModel::updatePurchase: ' . $logErr->getMessage());
                }
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Update Purchase Error: " . $e->getMessage());
            return false;
        }
    }

    public function deletePurchase($id, $userId): bool
    {
        try {
            // Fetch info before deleting the purchase
            $stmtGetPurchase = $this->db->prepare("SELECT total, provider_id, category, notes, inventory_id, user_id FROM purchases WHERE id = :id");
            $stmtGetPurchase->execute([':id' => $id]);
            $purchaseInfo = $stmtGetPurchase->fetch(PDO::FETCH_ASSOC);
            if (!$purchaseInfo) {
                return false;
            }
            $totalAmount = (float)$purchaseInfo['total'];
            $providerId = $purchaseInfo['provider_id'];
            $category = $purchaseInfo['category'];
            $notes = $purchaseInfo['notes'];
            $purchaseInvId = $purchaseInfo['inventory_id'];

            require_once __DIR__ . '/../helpers/auth_helper.php';
            $ownerId = $purchaseInvId ? (getInventoryOwnerId((int)$purchaseInvId) ?? $userId) : $userId;

            if ((int)$purchaseInfo['user_id'] !== (int)$ownerId) {
                return false;
            }

            $stmtGetDetails = $this->db->prepare("SELECT product_name, quantity, unit_price FROM purchase_details WHERE purchase_id = :id");
            $stmtGetDetails->execute([':id' => $id]);
            $detailsRows = $stmtGetDetails->fetchAll(PDO::FETCH_ASSOC);
            $itemsList = [];
            foreach ($detailsRows as $row) {
                $qty = $row['quantity'] ?? 1;
                $name = $row['product_name'] ?? 'Producto';
                $price = $row['unit_price'] ?? 0;
                $itemsList[] = "{$qty}x {$name} ($" . number_format((float)$price, 2, ',', '.') . ")";
            }

            $providerName = 'Ninguno';
            if ($providerId) {
                $stmtProv = $this->db->prepare("SELECT name FROM providers WHERE id = ?");
                $stmtProv->execute([$providerId]);
                $providerName = $stmtProv->fetchColumn() ?: 'Ninguno';
            }

            $isGasto = empty($detailsRows) && !empty($category);
            $typeStr = $isGasto ? "gasto" : "compra";
            
            $logDesc = "Eliminó un " . $typeStr . " por $" . number_format($totalAmount, 2, ',', '.');
            $logExtra = $isGasto
                ? "Categoría: $category | Notas: " . ($notes ?: 'Ninguna')
                : "Proveedor: $providerName | Detalle: " . (empty($itemsList) ? 'Sin productos' : implode(', ', $itemsList));

            $this->db->beginTransaction();

            // 0. Restablecer stock de los productos comprados
            $stmtItems = $this->db->prepare("SELECT product_id, quantity FROM purchase_details WHERE purchase_id = :id");
            $stmtItems->execute([':id' => $id]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
            
            require_once __DIR__ . '/InventoryModel.php';
            $invModel = new \App\Models\InventoryModel();
            
            if ($purchaseInvId) {
                foreach ($items as $item) {
                    if (!empty($item['product_id'])) {
                        $invModel->decreaseStock($ownerId, $item['product_id'], $item['quantity'], $purchaseInvId);
                    }
                }
            }

            // 1. Borrar detalles primero
            $stmtDet = $this->db->prepare("DELETE FROM purchase_details WHERE purchase_id = :id");
            $stmtDet->execute([':id' => $id]);

            // 2. Borrar cabecera verificando usuario
            $stmt = $this->db->prepare("DELETE FROM purchases WHERE id = :id AND user_id = :user");
            $stmt->execute([':id' => $id, ':user' => $ownerId]);

            if ($stmt->rowCount() > 0) {
                $this->db->commit();

                try {
                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Compras',
                        'delete',
                        'purchase',
                        (string)$id,
                        $logDesc,
                        $logExtra,
                        (int)($purchaseInvId ?? 0),
                        (int)$userId
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in PurchaseModel::deletePurchase: ' . $logErr->getMessage());
                }

                return true;
            } else {
                $this->db->rollBack();
                return false;
            }
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function getHistory($userId, $inventoryId = null, $order = 'DESC'): array
    {
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        try {
            if (session_status() === PHP_SESSION_NONE) @session_start();
            $inv = $inventoryId ?? $_SESSION['active_inventory_id'] ?? null;

            require_once __DIR__ . '/../helpers/auth_helper.php';
            $ownerId = $inv ? (getInventoryOwnerId((int)$inv) ?? $userId) : $userId;

            $sql = "
                SELECT 
                    p.*,
                    pr.full_name as provider_real_name
                FROM purchases p
                LEFT JOIN providers pr ON p.provider_id = pr.id
                WHERE p.user_id = :user
                " . ($inventoryId ? "AND p.inventory_id = :inv" : "") . "
                ORDER BY p.id $order 
                LIMIT 50
            ";

            $stmt = $this->db->prepare($sql);
            $params = [':user' => $ownerId];
            if ($inventoryId) {
                $params[':inv'] = $inventoryId;
            }
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(function($row) {
                $date = $row['created_at']
                    ?? $row['purchase_date']
                    ?? $row['date']
                    ?? $row['fecha']
                    ?? date('Y-m-d H:i:s');

                $provName = '-';
                if (!empty($row['provider_real_name'])) {
                    $provName = $row['provider_real_name'];
                } elseif (!empty($row['provider_name'])) {
                    $provName = $row['provider_name'];
                } elseif (!empty($row['category'])) {
                    $provName = $row['category'];
                }

                return [
                    'id' => $row['id'],
                    'created_at' => $date,
                    'total' => (float)($row['total'] ?? $row['total_amount'] ?? 0),
                    'notes' => $row['notes'] ?? '',
                    'category' => $row['category'] ?? '',
                    'provider_name' => $provName
                ];
            }, $results);

        } catch (Exception $e) {
            error_log("PurchaseModel Error: " . $e->getMessage());
            return [];
        }
    }

    public function resetIds(): bool
    {
        try {
            $sql = "
                UPDATE purchases
                CROSS JOIN (SELECT @cnt := 0) AS dummy
                SET purchases.id = (@cnt := @cnt + 1)
                ORDER BY created_at ASC
            ";

            $this->db->exec($sql);
            $this->db->exec("ALTER TABLE purchases AUTO_INCREMENT = 1");

            return true;

        } catch (Exception $e) {
            error_log("Error resetIds Compras: " . $e->getMessage());
            return false;
        }
    }

    public function getDetails($purchaseId, $userId, $inventoryId = null): ?array
    {
        try {
            if (session_status() === PHP_SESSION_NONE) @session_start();
            $inv = $inventoryId ?? $_SESSION['active_inventory_id'] ?? null;

            if (!$inv) {
                $stmtInv = $this->db->prepare("SELECT inventory_id FROM purchases WHERE id = ?");
                $stmtInv->execute([$purchaseId]);
                $inv = $stmtInv->fetchColumn();
            }

            require_once __DIR__ . '/../helpers/auth_helper.php';
            $ownerId = $inv ? (getInventoryOwnerId((int)$inv) ?? $userId) : $userId;

            $stmt = $this->db->prepare("
                SELECT p.*, pr.full_name as provider_name 
                FROM purchases p
                LEFT JOIN providers pr ON p.provider_id = pr.id
                WHERE p.id = :id AND p.user_id = :user
                " . ($inventoryId ? " AND p.inventory_id = :inv" : "") . "
            ");
            $params = [':id' => $purchaseId, ':user' => $ownerId];
            if ($inventoryId) {
                $params[':inv'] = $inventoryId;
            }
            $stmt->execute($params);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$purchase) return null;

            $stmtDet = $this->db->prepare("SELECT * FROM purchase_details WHERE purchase_id = :id");
            $stmtDet->execute([':id' => $purchaseId]);
            $items = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            return ['purchase' => $purchase, 'items' => $items];
        } catch (Exception $e) {
            return null;
        }
    }
}