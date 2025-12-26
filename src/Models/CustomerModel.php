<?php
namespace App\Models;

// Rutas absolutas blindadas
require_once dirname(__DIR__) . '/core/Database.php';

use App\core\Database;
use PDO;
use Exception;

class CustomerModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function createCustomer($userId, $data): bool|string
    {
        try {
            $sql = "INSERT INTO customers (user_id, full_name, phone, address, email, tax_id, birth_date, created_at) 
                    VALUES (:user, :name, :phone, :address, :email, :dni, :birth, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user'    => $userId,
                ':name'    => $data['name'],
                ':phone'   => $data['phone'] ?? null,
                ':address' => $data['address'] ?? null,
                ':email'   => $data['email'] ?? null,
                ':dni'     => $data['dni'] ?? null,
                ':birth'   => !empty($data['birth_date']) ? $data['birth_date'] : null
            ]);

            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("CustomerModel Error: " . $e->getMessage());
            return false;
        }
    }

    public function getAll($userId, $order = 'DESC'): array
    {
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        try {
            // Verificamos si la tabla existe para evitar Fatal Errors si falta el SQL
            $check = $this->db->query("SHOW TABLES LIKE 'customers'");
            if($check->rowCount() == 0) return [];

            $stmt = $this->db->prepare("SELECT * FROM customers WHERE user_id = :user ORDER BY created_at $order");
            $stmt->execute([':user' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getById($customerId, $userId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = :id AND user_id = :user");
            $stmt->execute([':id' => $customerId, ':user' => $userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
}