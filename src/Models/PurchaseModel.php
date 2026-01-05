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

            if (!empty($data['items']) && is_array($data['items'])) {
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

    // --- NUEVO: Actualizar ---
    public function updatePurchase($id, $userId, $data): bool
    {
        try {
            // Si es gasto rápido, permitimos cambiar el total y categoría.
            // Si es compra de inventario, el total suele ser calculado, pero aquí permitiremos editar la cabecera.

            $sql = "UPDATE purchases SET 
                    provider_id = :prov, 
                    category = :cat, 
                    notes = :notes,
                    created_at = :date
                    WHERE id = :id AND user_id = :user";

            // Si viene el total (solo para gastos rápidos usualmente), lo agregamos al SQL
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
                ':user'  => $userId,
                ':prov'  => !empty($data['provider_id']) ? $data['provider_id'] : null,
                ':cat'   => !empty($data['category']) ? $data['category'] : null,
                ':notes' => !empty($data['notes']) ? $data['notes'] : null,
                ':date'  => !empty($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s')
            ];

            if (isset($data['total'])) {
                $params[':total'] = $data['total'];
            }

            return $stmt->execute($params);

        } catch (Exception $e) {
            error_log("Update Purchase Error: " . $e->getMessage());
            return false;
        }
    }

    // --- NUEVO: Eliminar ---
    public function deletePurchase($id, $userId): bool
    {
        try {
            $this->db->beginTransaction();

            // 1. Borrar detalles primero (Foreign Key cascade debería hacerlo, pero aseguramos)
            $stmtDet = $this->db->prepare("DELETE FROM purchase_details WHERE purchase_id = :id");
            $stmtDet->execute([':id' => $id]);

            // 2. Borrar cabecera verificando usuario
            $stmt = $this->db->prepare("DELETE FROM purchases WHERE id = :id AND user_id = :user");
            $stmt->execute([':id' => $id, ':user' => $userId]);

            if ($stmt->rowCount() > 0) {
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

    // En src/Models/PurchaseModel.php

    public function getHistory($userId, $order = 'DESC'): array
    {
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        try {
            // ESTRATEGIA SEGURA:
            // 1. Traemos p.* (todas las columnas) para no fallar si falta alguna específica.
            // 2. Traemos el nombre del proveedor con JOIN.
            // 3. Ordenamos por ID (que siempre existe) para evitar errores con fechas mal nombradas.

            $sql = "
                SELECT 
                    p.*,
                    pr.full_name as provider_real_name
                FROM purchases p
                LEFT JOIN providers pr ON p.provider_id = pr.id
                WHERE p.user_id = :user
                ORDER BY p.id $order 
                LIMIT 50
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user' => $userId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(function($row) {
                // DETECTIVE DE FECHAS: Buscamos cuál es la columna de fecha real
                $date = $row['created_at']
                    ?? $row['purchase_date']
                    ?? $row['date']
                    ?? $row['fecha']
                    ?? date('Y-m-d H:i:s'); // Fallback final

                // DETECTIVE DE NOMBRE:
                // Si hay proveedor en la tabla relacionada, úsalo.
                // Si no, busca si existe columna 'provider_name' en compras.
                // Si no, usa la categoría.
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
                    'created_at' => $date, // Ahora siempre tendrá un valor
                    'total' => (float)($row['total'] ?? $row['total_amount'] ?? 0),
                    'notes' => $row['notes'] ?? '',
                    'category' => $row['category'] ?? '',
                    'provider_name' => $provName
                ];
            }, $results);

        } catch (Exception $e) {
            // Si falla, escribimos el error en el log de PHP para poder verlo
            error_log("PurchaseModel Error: " . $e->getMessage());
            return [];
        }
    }

    public function resetIds(): bool
    {
        try {
            // MÉTODO ATÓMICO (CROSS JOIN)
            // Este es el método más robusto. Inicializa el contador dentro de la misma consulta
            // para que PHP no pierda la conexión entre líneas.

            $sql = "
                UPDATE receipts
                CROSS JOIN (SELECT @cnt := 0) AS dummy
                SET receipts.id = (@cnt := @cnt + 1)
                ORDER BY receipt_date ASC
            ";

            // Ejecutamos la actualización masiva
            $this->db->exec($sql);

            // Reseteamos el autoincrement para que la próxima compra sea la siguiente
            $this->db->exec("ALTER TABLE receipts AUTO_INCREMENT = 1");

            return true;

        } catch (Exception $e) {
            error_log("Error resetIds Compras: " . $e->getMessage());
            return false;
        }
    }

    public function getDetails($purchaseId, $userId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, pr.full_name as provider_name 
                FROM purchases p
                LEFT JOIN providers pr ON p.provider_id = pr.id
                WHERE p.id = :id AND p.user_id = :user
            ");
            $stmt->execute([':id' => $purchaseId, ':user' => $userId]);
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