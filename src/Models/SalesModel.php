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

    public function registrarVenta($userId, $clientId, $data): bool|string
    {
        try {
            if (!$this->db->inTransaction()) $this->db->beginTransaction();

            // 1. Insertar Venta (Con todos los campos nuevos)
            $stmt = $this->db->prepare("
                INSERT INTO ventas 
                (id_usuario, id_cliente, employee_id, payment_method_id, fecha_hora, total, amount_tendered, change_returned, commission_amount, category, notes, proof_file) 
                VALUES (:user, :client, :emp, :pay_method, NOW(), :total, :tendered, :change, :comm, :cat, :notes, :file)
            ");

            $stmt->execute([
                ':user' => $userId,
                ':client' => $clientId,
                ':emp' => $data['employee_id'] ?? null,
                ':pay_method' => $data['payment_method_id'] ?? null,
                ':total' => $data['total'],
                ':tendered' => $data['amount_tendered'] ?? 0,
                ':change' => $data['change_returned'] ?? 0,
                ':comm' => $data['commission_amount'] ?? 0,
                ':cat' => $data['category'] ?? null,
                ':notes' => $data['notes'] ?? null,
                ':file' => $data['proof_file'] ?? null
            ]);
            $saleId = $this->db->lastInsertId();

            // 2. Procesar Items (Si hay)
            if (!empty($data['items']) && is_array($data['items'])) {
                $inventoryModel = new InventoryModel();
                $stmtDet = $this->db->prepare("INSERT INTO detalle_venta (id_venta, id_producto, nombre_producto, cantidad, precio_unitario, subtotal) VALUES (:sid, :pid, :name, :qty, :price, :sub)");

                foreach ($data['items'] as $item) {
                    $stmtDet->execute([
                        ':sid' => $saleId,
                        ':pid' => $item['id'],
                        ':name' => $item['nombre_producto'],
                        ':qty' => $item['cantidad'],
                        ':price' => $item['precio_unitario'],
                        ':sub' => $item['subtotal']
                    ]);
                    // Descontar Stock
                    $inventoryModel->decreaseStock($userId, $item['id'], $item['cantidad']);
                }
            }

            $this->db->commit();
            return $saleId;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("Error SalesModel: " . $e->getMessage());
            return false;
        }
    }


    public function obtenerHistorial($userId, $order = 'DESC'): array
    {
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        try {
            $sql = "SELECT v.*, c.full_name as nombre_cliente, e.full_name as nombre_empleado 
                    FROM ventas v
                    LEFT JOIN customers c ON v.id_cliente = c.id
                    LEFT JOIN employees e ON v.employee_id = e.id
                    WHERE v.id_usuario = :user ORDER BY v.fecha_hora $order";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { return []; }
    }

    // IMPORTANTE: Agregar getSaleDetailsNew para mostrar los datos nuevos en el modal del ojo
    public function obtenerDetalle($saleId, $userId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT v.*, c.full_name as nombre_cliente, e.full_name as nombre_empleado, pm.name as metodo_pago
                FROM ventas v
                LEFT JOIN customers c ON v.id_cliente = c.id
                LEFT JOIN employees e ON v.employee_id = e.id
                LEFT JOIN payment_methods pm ON v.payment_method_id = pm.id
                WHERE v.id = :id AND v.id_usuario = :user
            ");
            $stmt->execute([':id' => $saleId, ':user' => $userId]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sale) return null;

            $stmtDet = $this->db->prepare("SELECT * FROM detalle_venta WHERE id_venta = :id");
            $stmtDet->execute([':id' => $saleId]);
            $items = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            return ['sale' => $sale, 'items' => $items];
        } catch (Exception $e) { return null; }
    }

    // ... updateCustomer ...
    public function updateCustomer($saleId, $userId, $newClientId): bool
    {
        try {
            $clientId = (!empty($newClientId) && $newClientId !== 'null') ? $newClientId : null;
            $stmt = $this->db->prepare("UPDATE ventas SET id_cliente = :client WHERE id = :id AND id_usuario = :user");
            $stmt->execute([':client' => $clientId, ':id' => $saleId, ':user' => $userId]);
            return true;
        } catch (Exception $e) { return false; }
    }
}