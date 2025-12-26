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

    public function createEmployee($userId, $name): bool|string
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO employees (user_id, full_name, created_at) VALUES (:user, :name, NOW())");
            $stmt->execute([':user' => $userId, ':name' => $name]);
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("EmployeeModel Error: " . $e->getMessage());
            return false;
        }
    }

    public function getAll($userId, $order = 'DESC'): array
    {
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        try {
            // Verificar tabla para evitar fatal error
            $check = $this->db->query("SHOW TABLES LIKE 'employees'");
            if($check->rowCount() == 0) return [];

            $stmt = $this->db->prepare("SELECT * FROM employees WHERE user_id = :user ORDER BY created_at $order");
            $stmt->execute([':user' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}