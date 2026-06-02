<?php
namespace App\Models;

require_once dirname(__DIR__) . '/core/Database.php';

use App\core\Database;
use PDO;
use Exception;

class CustomerModel {
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

    public function createCustomer($userId, $data, $inventoryId = null): bool|string
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return false;

            require_once __DIR__ . '/../helpers/auth_helper.php';
            $ownerId = getInventoryOwnerId((int)$inv) ?? $userId;

            $sql = "INSERT INTO customers (user_id, inventory_id, full_name, phone, address, email, tax_id, birth_date, created_at) 
                    VALUES (:user, :inv, :name, :phone, :address, :email, :dni, :birth, NOW())";

            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute([
                ':user'    => $ownerId,
                ':inv'     => $inv,
                ':name'    => $data['name'],
                ':phone'   => $data['phone'] ?? null,
                ':address' => $data['address'] ?? null,
                ':email'   => $data['email'] ?? null,
                ':dni'     => $data['dni'] ?? null,
                ':birth'   => !empty($data['birth_date']) ? $data['birth_date'] : null
            ]);

            if ($success) {
                $newId = $this->db->lastInsertId();
                try {
                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Clientes',
                        'create',
                        'customer',
                        (string)$newId,
                        "Registró un cliente: " . $data['name'],
                        "Email: " . ($data['email'] ?? '-') . " | Teléfono: " . ($data['phone'] ?? '-') . " | DNI/CUIT: " . ($data['dni'] ?? '-'),
                        (int)$inv,
                        (int)$userId
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in createCustomer: ' . $logErr->getMessage());
                }
                return $newId;
            }
            return false;
        } catch (Exception $e) {
            error_log("CustomerModel Error: " . $e->getMessage());
            return false;
        }
    }

    public function updateCustomer($id, $userId, $data, $inventoryId = null): bool
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return false;

            require_once __DIR__ . '/../helpers/auth_helper.php';
            $ownerId = getInventoryOwnerId((int)$inv) ?? $userId;

            // Fetch old record for logging comparison
            $oldRow = $this->getById($id, $userId, $inv);

            $sql = "UPDATE customers 
                    SET full_name = :name, phone = :phone, address = :address, email = :email, tax_id = :dni, birth_date = :birth
                    WHERE id = :id AND user_id = :user AND inventory_id = :inv";

            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute([
                ':id'      => $id,
                ':user'    => $ownerId,
                ':inv'     => $inv,
                ':name'    => $data['name'],
                ':phone'   => $data['phone'] ?? null,
                ':address' => $data['address'] ?? null,
                ':email'   => $data['email'] ?? null,
                ':dni'     => $data['dni'] ?? null,
                ':birth'   => !empty($data['birth_date']) ? $data['birth_date'] : null
            ]);

            if ($success && $oldRow) {
                try {
                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Clientes',
                        'update',
                        'customer',
                        (string)$id,
                        "Editó el cliente: " . $data['name'],
                        "Anterior - Nombre: {$oldRow['full_name']}, Email: " . ($oldRow['email'] ?? '-') . ", Teléfono: " . ($oldRow['phone'] ?? '-') . ", DNI: " . ($oldRow['tax_id'] ?? '-') . " | " .
                        "Nuevo - Nombre: {$data['name']}, Email: " . ($data['email'] ?? '-') . ", Teléfono: " . ($data['phone'] ?? '-') . ", DNI: " . ($data['dni'] ?? '-'),
                        (int)$inv,
                        (int)$userId
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in updateCustomer: ' . $logErr->getMessage());
                }
                return true;
            }
            return $success;
        } catch (Exception $e) {
            error_log("Update Customer Error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteCustomer($id, $userId, $inventoryId = null): bool
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return false;

            require_once __DIR__ . '/../helpers/auth_helper.php';
            $ownerId = getInventoryOwnerId((int)$inv) ?? $userId;

            // Fetch old record for logging
            $oldRow = $this->getById($id, $userId, $inv);

            $stmt = $this->db->prepare("DELETE FROM customers WHERE id = :id AND user_id = :user AND inventory_id = :inv");
            $stmt->execute([':id' => $id, ':user' => $ownerId, ':inv' => $inv]);
            $success = $stmt->rowCount() > 0;

            if ($success && $oldRow) {
                try {
                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Clientes',
                        'delete',
                        'customer',
                        (string)$id,
                        "Eliminó el cliente: " . $oldRow['full_name'],
                        "Email: " . ($oldRow['email'] ?? '-') . " | Teléfono: " . ($oldRow['phone'] ?? '-'),
                        (int)$inv,
                        (int)$userId
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in deleteCustomer: ' . $logErr->getMessage());
                }
            }
            return $success;
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

            require_once __DIR__ . '/../helpers/auth_helper.php';
            $ownerId = getInventoryOwnerId((int)$inv) ?? $userId;

            $check = $this->db->query("SHOW TABLES LIKE 'customers'");
            if($check->rowCount() == 0) return [];

            $stmt = $this->db->prepare("SELECT * FROM customers WHERE user_id = :user AND inventory_id = :inv ORDER BY created_at $order");
            $stmt->execute([':user' => $ownerId, ':inv' => $inv]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getById($customerId, $userId, $inventoryId = null) {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return null;

            require_once __DIR__ . '/../helpers/auth_helper.php';
            $ownerId = getInventoryOwnerId((int)$inv) ?? $userId;

            $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = :id AND user_id = :user AND inventory_id = :inv");
            $stmt->execute([':id' => $customerId, ':user' => $ownerId, ':inv' => $inv]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
}