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

    private function resolveInventoryId($inventoryId = null): ?int
    {
        if ($inventoryId !== null) return (int)$inventoryId;
        if (session_status() === PHP_SESSION_NONE) @session_start();
        return isset($_SESSION['active_inventory_id']) ? (int)$_SESSION['active_inventory_id'] : null;
    }

    public function create($userId, $data, $inventoryId = null): bool|string
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return false;

            $stmt = $this->db->prepare("
                INSERT INTO payment_methods (user_id, inventory_id, name, type, currency, surcharge, is_active) 
                VALUES (:user, :inv, :name, :type, :currency, :surcharge, 1)
            ");
            $success = $stmt->execute([
                ':user'      => $userId,
                ':inv'       => $inv,
                ':name'      => $data['name'],
                ':type'      => $data['type'] ?? 'Other',
                ':currency'  => $data['currency'] ?? 'ARS',
                ':surcharge' => $data['surcharge'] ?? 0
            ]);

            if ($success) {
                $newId = $this->db->lastInsertId();
                try {
                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Métodos de Pago',
                        'create',
                        'payment_method',
                        (string)$newId,
                        "Registró método de pago: " . $data['name'],
                        "Tipo: " . ($data['type'] ?? 'Other') . " | Moneda: " . ($data['currency'] ?? 'ARS') . " | Recargo: " . ($data['surcharge'] ?? 0) . "%",
                        (int)$inv,
                        (int)$userId
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in PaymentMethodModel::create: ' . $logErr->getMessage());
                }
                return $newId;
            }
            return false;
        } catch (Exception $e) { return false; }
    }

    public function getAll($userId, $inventoryId = null): array
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return [];

            $stmt = $this->db->prepare("SELECT * FROM payment_methods WHERE user_id = :user AND inventory_id = :inv ORDER BY id ASC");
            $stmt->execute([':user' => $userId, ':inv' => $inv]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { return []; }
    }

    public function getById($id, $userId, $inventoryId = null) {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return null;

            $stmt = $this->db->prepare("SELECT * FROM payment_methods WHERE id = :id AND user_id = :user AND inventory_id = :inv");
            $stmt->execute([':id' => $id, ':user' => $userId, ':inv' => $inv]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    public function update($id, $userId, $data, $inventoryId = null): bool
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return false;

            // Fetch old record for logging comparison
            $oldRow = $this->getById($id, $userId, $inv);

            $stmt = $this->db->prepare("
                UPDATE payment_methods 
                SET name = :name, type = :type, currency = :currency, surcharge = :surcharge
                WHERE id = :id AND user_id = :user AND inventory_id = :inv
            ");
            $success = $stmt->execute([
                ':name'      => $data['name'],
                ':type'      => $data['type'],
                ':currency'  => $data['currency'],
                ':surcharge' => $data['surcharge'],
                ':id'        => $id,
                ':user'      => $userId,
                ':inv'       => $inv
            ]);

            if ($success && $oldRow) {
                try {
                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Métodos de Pago',
                        'update',
                        'payment_method',
                        (string)$id,
                        "Editó método de pago: " . $data['name'],
                        "Anterior - Nombre: {$oldRow['name']}, Tipo: {$oldRow['type']}, Moneda: {$oldRow['currency']}, Recargo: {$oldRow['surcharge']}% | " .
                        "Nuevo - Nombre: {$data['name']}, Tipo: {$data['type']}, Moneda: {$data['currency']}, Recargo: {$data['surcharge']}%",
                        (int)$inv,
                        (int)$userId
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in PaymentMethodModel::update: ' . $logErr->getMessage());
                }
                return true;
            }
            return $success;
        } catch (Exception $e) { return false; }
    }

    public function delete($id, $userId, $inventoryId = null): bool
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return false;

            // Fetch old record for logging
            $oldRow = $this->getById($id, $userId, $inv);

            $stmt = $this->db->prepare("DELETE FROM payment_methods WHERE id = :id AND user_id = :user AND inventory_id = :inv");
            $success = $stmt->execute([':id' => $id, ':user' => $userId, ':inv' => $inv]);

            if ($success && $oldRow) {
                try {
                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Métodos de Pago',
                        'delete',
                        'payment_method',
                        (string)$id,
                        "Eliminó método de pago: " . $oldRow['name'],
                        "Tipo: {$oldRow['type']} | Moneda: {$oldRow['currency']} | Recargo: {$oldRow['surcharge']}%",
                        (int)$inv,
                        (int)$userId
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in PaymentMethodModel::delete: ' . $logErr->getMessage());
                }
            }
            return $success;
        } catch (Exception $e) { return false; }
    }
}
