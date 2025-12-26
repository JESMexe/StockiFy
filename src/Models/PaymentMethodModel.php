<?php
namespace App\Models;

require_once dirname(__DIR__) . '/core/Database.php';

use App\core\Database;
use PDO;
use Exception;

class PaymentMethodModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($userId, $data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO payment_methods (user_id, name, type, currency, surcharge, is_active) 
                VALUES (:user, :name, :type, :currency, :surcharge, 1)
            ");
            $stmt->execute([
                ':user'      => $userId,
                ':name'      => $data['name'],
                ':type'      => $data['type'] ?? 'Other',
                ':currency'  => $data['currency'] ?? 'ARS',
                ':surcharge' => $data['surcharge'] ?? 0
            ]);
            return $this->db->lastInsertId();
        } catch (Exception $e) { return false; }
    }

    public function getAll($userId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM payment_methods WHERE user_id = :user ORDER BY id ASC");
            $stmt->execute([':user' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { return []; }
    }

    public function update($id, $userId, $data) {
        try {
            $stmt = $this->db->prepare("
                UPDATE payment_methods 
                SET name = :name, type = :type, currency = :currency, surcharge = :surcharge
                WHERE id = :id AND user_id = :user
            ");
            return $stmt->execute([
                ':name'      => $data['name'],
                ':type'      => $data['type'],
                ':currency'  => $data['currency'],
                ':surcharge' => $data['surcharge'],
                ':id'        => $id,
                ':user'      => $userId
            ]);
        } catch (Exception $e) { return false; }
    }

    public function delete($id, $userId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM payment_methods WHERE id = :id AND user_id = :user");
            return $stmt->execute([':id' => $id, ':user' => $userId]);
        } catch (Exception $e) { return false; }
    }
}