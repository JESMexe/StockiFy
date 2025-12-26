<?php
namespace App\Models;

require_once dirname(__DIR__) . '/core/Database.php';

use App\core\Database;
use PDO;
use Exception;

class ProviderModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function createProvider($userId, $data): bool|string
    {
        try {
            $sql = "INSERT INTO providers (user_id, full_name, phone, address, email, tax_id, created_at) 
                    VALUES (:user, :name, :phone, :address, :email, :tax_id, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user'    => $userId,
                ':name'    => $data['name'],
                ':phone'   => $data['phone'] ?? null,
                ':address' => $data['address'] ?? null,
                ':email'   => $data['email'] ?? null,
                ':tax_id'  => $data['tax_id'] ?? null
            ]);

            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("ProviderModel Error: " . $e->getMessage());
            return false;
        }
    }

    public function getAll($userId, $order = 'DESC'): array
    {
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        try {
            $check = $this->db->query("SHOW TABLES LIKE 'providers'");
            if($check->rowCount() == 0) return [];

            $stmt = $this->db->prepare("SELECT * FROM providers WHERE user_id = :user ORDER BY created_at $order");
            $stmt->execute([':user' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getById($id, $userId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM providers WHERE id = :id AND user_id = :user");
            $stmt->execute([':id' => $id, ':user' => $userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
}