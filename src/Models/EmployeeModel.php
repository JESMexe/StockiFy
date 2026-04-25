<?php
namespace App\Models;

require_once dirname(__DIR__) . '/core/Database.php';

use App\core\Database;
use PDO;
use Exception;

class EmployeeModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    private function resolveInventoryId($inventoryId = null): ?int
    {
        if ($inventoryId !== null) return (int)$inventoryId;
        if (session_status() === PHP_SESSION_NONE) @session_start();
        return isset($_SESSION['active_inventory_id']) ? (int)$_SESSION['active_inventory_id'] : null;
    }

    public function createEmployee($userId, $name, $dni = null, $phone = null, $email = null, $inventoryId = null, $categoryId = null, $customData = null): bool|string
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return false;

            $stmt = $this->db->prepare("
                INSERT INTO employees (user_id, inventory_id, full_name, dni, phone, email, category_id, custom_data, created_at)
                VALUES (:user, :inv, :name, :dni, :phone, :email, :cat, :data, NOW())
            ");
            $stmt->execute([
                ':user' => $userId,
                ':inv' => $inv,
                ':name' => $name,
                ':dni' => $dni,
                ':phone' => $phone,
                ':email' => $email,
                ':cat' => $categoryId,
                ':data' => is_array($customData) ? json_encode($customData) : $customData
            ]);
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log('EmployeeModel Error: ' . $e->getMessage());
            return false;
        }
    }

    public function updateEmployee($id, $userId, $name, $dni, $phone, $email, $inventoryId = null, $categoryId = null, $customData = null): bool
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return false;

            $stmt = $this->db->prepare("
                UPDATE employees
                SET full_name = :name, dni = :dni, phone = :phone, email = :email, category_id = :cat, custom_data = :data
                WHERE id = :id AND user_id = :user AND inventory_id = :inv
            ");
            return $stmt->execute([
                ':id' => $id,
                ':user' => $userId,
                ':inv' => $inv,
                ':name' => $name,
                ':dni' => $dni,
                ':phone' => $phone,
                ':email' => $email,
                ':cat' => $categoryId,
                ':data' => is_array($customData) ? json_encode($customData) : $customData
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function deleteEmployee($id, $userId, $inventoryId = null): bool
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return false;

            $stmt = $this->db->prepare("DELETE FROM employees WHERE id = :id AND user_id = :user AND inventory_id = :inv");
            $stmt->execute([':id' => $id, ':user' => $userId, ':inv' => $inv]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getAll($userId, $order = 'DESC', $inventoryId = null): array
    {
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return [];

            $check = $this->db->query("SHOW TABLES LIKE 'employees'");
            if($check->rowCount() == 0) return [];

            $stmt = $this->db->prepare("
                SELECT e.*, c.name as category_name, c.fields as category_fields
                FROM employees e
                LEFT JOIN employee_categories c ON e.category_id = c.id
                WHERE e.user_id = :user AND e.inventory_id = :inv 
                ORDER BY e.created_at $order
            ");
            $stmt->execute([':user' => $userId, ':inv' => $inv]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as &$r) {
                $r['custom_data'] = json_decode($r['custom_data'], true) ?: [];
                $r['category_fields'] = json_decode($r['category_fields'], true) ?: [];
            }
            return $results;
        } catch (Exception $e) {
            return [];
        }
    }

    public function getById($id, $userId, $inventoryId = null) {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return null;

            $stmt = $this->db->prepare("SELECT * FROM employees WHERE id = :id AND user_id = :user AND inventory_id = :inv");
            $stmt->execute([':id' => $id, ':user' => $userId, ':inv' => $inv]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
}
