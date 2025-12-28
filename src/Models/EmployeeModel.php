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

    public function createEmployee($userId, $name, $dni = null, $phone = null, $email = null): bool|string
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO employees (user_id, full_name, dni, phone, email, created_at) VALUES (:user, :name, :dni, :phone, :email, NOW())");
            $stmt->execute([
                ':user' => $userId,
                ':name' => $name,
                ':dni' => $dni,
                ':phone' => $phone,
                ':email' => $email
            ]);
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("EmployeeModel Error: " . $e->getMessage());
            return false;
        }
    }

    // --- NUEVO: Actualizar ---
    public function updateEmployee($id, $userId, $name, $dni, $phone, $email): bool
    {
        try {
            // Verificamos que pertenezca al usuario para seguridad
            $stmt = $this->db->prepare("UPDATE employees SET full_name = :name, dni = :dni, phone = :phone, email = :email WHERE id = :id AND user_id = :user");
            return $stmt->execute([
                ':id' => $id,
                ':user' => $userId,
                ':name' => $name,
                ':dni' => $dni,
                ':phone' => $phone,
                ':email' => $email
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    // --- NUEVO: Eliminar ---
    public function deleteEmployee($id, $userId): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM employees WHERE id = :id AND user_id = :user");
            $stmt->execute([':id' => $id, ':user' => $userId]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getAll($userId, $order = 'DESC'): array
    {
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        try {
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