<?php
namespace App\Models;

require_once dirname(__DIR__) . '/core/Database.php';

use App\core\Database;
use PDO;
use Exception;

class EmployeeCategoryModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function createCategory($userId, $inventoryId, $name, $fields)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO employee_categories (user_id, inventory_id, name, fields, created_at)
                VALUES (:user, :inv, :name, :fields, NOW())
            ");
            $stmt->execute([
                ':user' => $userId,
                ':inv' => $inventoryId,
                ':name' => $name,
                ':fields' => json_encode($fields)
            ]);
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            return false;
        }
    }

    public function updateCategory($id, $userId, $inventoryId, $name, $fields)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE employee_categories
                SET name = :name, fields = :fields
                WHERE id = :id AND user_id = :user AND inventory_id = :inv
            ");
            return $stmt->execute([
                ':id' => $id,
                ':user' => $userId,
                ':inv' => $inventoryId,
                ':name' => $name,
                ':fields' => json_encode($fields)
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function deleteCategory($id, $userId, $inventoryId)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM employee_categories WHERE id = :id AND user_id = :user AND inventory_id = :inv");
            return $stmt->execute([':id' => $id, ':user' => $userId, ':inv' => $inventoryId]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function getAll($userId, $inventoryId)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM employee_categories WHERE user_id = :user AND inventory_id = :inv ORDER BY name ASC");
            $stmt->execute([':user' => $userId, ':inv' => $inventoryId]);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($categories as &$cat) {
                $cat['fields'] = json_decode($cat['fields'], true) ?: [];
            }
            return $categories;
        } catch (Exception $e) {
            return [];
        }
    }

    public function getById($id, $userId, $inventoryId)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM employee_categories WHERE id = :id AND user_id = :user AND inventory_id = :inv");
            $stmt->execute([':id' => $id, ':user' => $userId, ':inv' => $inventoryId]);
            $cat = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cat) {
                $cat['fields'] = json_decode($cat['fields'], true) ?: [];
            }
            return $cat;
        } catch (Exception $e) {
            return null;
        }
    }
}
