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

    private function resolveInventoryId($inventoryId = null): ?int
    {
        if ($inventoryId !== null) return (int)$inventoryId;
        if (session_status() === PHP_SESSION_NONE) @session_start();
        return isset($_SESSION['active_inventory_id']) ? (int)$_SESSION['active_inventory_id'] : null;
    }

    public function createProvider($userId, $data, $inventoryId = null): bool|string
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return false;

            $sql = "INSERT INTO providers (user_id, inventory_id, full_name, phone, address, email, tax_id, created_at) 
                    VALUES (:user, :inv, :name, :phone, :address, :email, :tax_id, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user'    => $userId,
                ':inv'     => $inv,
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

    public function updateProvider($id, $userId, $data, $inventoryId = null): bool
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return false;

            $sql = "UPDATE providers 
                    SET full_name = :name, phone = :phone, address = :address, email = :email, tax_id = :tax_id 
                    WHERE id = :id AND user_id = :user AND inventory_id = :inv";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':id'      => $id,
                ':user'    => $userId,
                ':inv'     => $inv,
                ':name'    => $data['name'],
                ':phone'   => $data['phone'] ?? null,
                ':address' => $data['address'] ?? null,
                ':email'   => $data['email'] ?? null,
                ':tax_id'  => $data['tax_id'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Update Provider Error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteProvider($id, $userId, $inventoryId = null): bool
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return false;

            $stmt = $this->db->prepare("DELETE FROM providers WHERE id = :id AND user_id = :user AND inventory_id = :inv");
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

            $check = $this->db->query("SHOW TABLES LIKE 'providers'");
            if($check->rowCount() == 0) return [];

            $stmt = $this->db->prepare("SELECT * FROM providers WHERE user_id = :user AND inventory_id = :inv ORDER BY created_at $order");
            $stmt->execute([':user' => $userId, ':inv' => $inv]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getById($id, $userId, $inventoryId = null) {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return null;

            $stmt = $this->db->prepare("SELECT * FROM providers WHERE id = :id AND user_id = :user AND inventory_id = :inv");
            $stmt->execute([':id' => $id, ':user' => $userId, ':inv' => $inv]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
}
