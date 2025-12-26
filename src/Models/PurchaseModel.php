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

    /**
     * Crea una compra. Puede ser una compra de inventario (con items)
     * o un gasto rápido (sin items, con categoría/notas).
     */
    public function createPurchase($userId, $data) {
        try {
            $this->db->beginTransaction();

            // 1. Insertar Cabecera (purchases)
            // El proveedor puede ser null. Category y notes son opcionales.
            $stmt = $this->db->prepare("
                INSERT INTO purchases (user_id, provider_id, total, category, notes, created_at) 
                VALUES (:user, :prov, :total, :cat, :notes, NOW())
            ");

            $providerId = !empty($data['provider_id']) ? $data['provider_id'] : null;
            $category = !empty($data['category']) ? $data['category'] : null;
            $notes = !empty($data['notes']) ? $data['notes'] : null;

            $stmt->execute([
                ':user' => $userId,
                ':prov' => $providerId,
                ':total' => $data['total'],
                ':cat' => $category,
                ':notes' => $notes
            ]);

            $purchaseId = $this->db->lastInsertId();

            // 2. Insertar Detalles (Si existen) - Para compras de inventario
            if (!empty($data['items']) && is_array($data['items'])) {
                $stmtDetail = $this->db->prepare("
                    INSERT INTO purchase_details (purchase_id, product_id, product_name, quantity, unit_price, subtotal)
                    VALUES (:pid, :prod_id, :name, :qty, :price, :subtotal)
                ");

                foreach ($data['items'] as $item) {
                    $stmtDetail->execute([
                        ':pid' => $purchaseId,
                        ':prod_id' => $item['id'], // ID original del producto (puede ser numérico o string según tu mapeo)
                        ':name' => $item['nombre_producto'],
                        ':qty' => $item['cantidad'],
                        ':price' => $item['precio_unitario'],
                        ':subtotal' => $item['subtotal']
                    ]);
                    // AQUÍ A FUTURO: Lógica para aumentar stock en la tabla de inventario
                }
            }

            $this->db->commit();
            return $purchaseId;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Purchase Creation Error: " . $e->getMessage());
            return false;
        }
    }

    public function getHistory($userId, $order = 'DESC'): array
    {
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        try {
            $check = $this->db->query("SHOW TABLES LIKE 'purchases'");
            if ($check->rowCount() === 0) return [];

            $stmt = $this->db->prepare("
                SELECT p.*, pr.full_name as provider_name 
                FROM purchases p
                LEFT JOIN providers pr ON p.provider_id = pr.id
                WHERE p.user_id = :user 
                ORDER BY p.created_at $order
            ");
            $stmt->execute([':user' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getDetails($purchaseId, $userId): ?array
    {
        try {
            // Cabecera
            $stmt = $this->db->prepare("
                SELECT p.*, pr.full_name as provider_name 
                FROM purchases p
                LEFT JOIN providers pr ON p.provider_id = pr.id
                WHERE p.id = :id AND p.user_id = :user
            ");
            $stmt->execute([':id' => $purchaseId, ':user' => $userId]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$purchase) return null;

            // Items
            $stmtDet = $this->db->prepare("SELECT * FROM purchase_details WHERE purchase_id = :id");
            $stmtDet->execute([':id' => $purchaseId]);
            $items = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            return ['purchase' => $purchase, 'items' => $items];
        } catch (Exception $e) {
            return null;
        }
    }

    public function updateProvider($purchaseId, $userId, $newProviderId): bool
    {
        try {
            // Si el ID es "null" string o vacío, lo guardamos como NULL SQL
            $provIdToSave = (!empty($newProviderId) && $newProviderId !== 'null') ? $newProviderId : null;

            $stmt = $this->db->prepare("
                UPDATE purchases SET provider_id = :provId 
                WHERE id = :purchId AND user_id = :userId
            ");
            $stmt->execute([
                ':provId' => $provIdToSave,
                ':purchId' => $purchaseId,
                ':userId' => $userId
            ]);
            return $stmt->rowCount() > 0; // Devuelve true si se actualizó algo
        } catch (Exception $e) {
            error_log("Update Provider Error: " . $e->getMessage());
            return false;
        }
    }
}