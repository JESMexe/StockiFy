<?php
namespace App\Models;

require_once dirname(__DIR__) . '/core/Database.php';

use App\core\Database;
use PDO;
use Exception;

class DeliveryModel {
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

    private function generateTicketCode(int $inventoryId): string
    {
        $stmt = $this->db->prepare("
            SELECT ticket_code 
            FROM deliveries 
            WHERE inventory_id = :inv 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([':inv' => $inventoryId]);
        $lastCode = $stmt->fetchColumn();

        $nextNum = 1;
        if ($lastCode && preg_match('/ENV-(\d+)/', $lastCode, $matches)) {
            $nextNum = (int)$matches[1] + 1;
        }

        return 'ENV-' . str_pad((string)$nextNum, 4, '0', STR_PAD_LEFT);
    }

    public function createDelivery($userId, $inventoryId, $saleId, $collaboratorId, $address, $phone = null, $email = null, $estimatedTime = null, $isPaid = 0): int|false
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return false;

            require_once __DIR__ . '/../helpers/auth_helper.php';
            $ownerId = getInventoryOwnerId((int)$inv) ?? $userId;

            $ticketCode = $this->generateTicketCode($inv);

            $sql = "INSERT INTO deliveries (inventory_id, sale_id, collaborator_id, ticket_code, address, phone, email, estimated_time, is_paid, status, created_at) 
                    VALUES (:inv, :sale, :collab, :ticket, :address, :phone, :email, :est_time, :is_paid, 'pending', NOW())";

            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute([
                ':inv'      => $inv,
                ':sale'     => $saleId,
                ':collab'   => $collaboratorId,
                ':ticket'   => $ticketCode,
                ':address'  => $address,
                ':phone'    => $phone,
                ':email'    => $email,
                ':est_time' => !empty($estimatedTime) ? $estimatedTime : null,
                ':is_paid'  => $isPaid
            ]);

            if ($success) {
                $newId = $this->db->lastInsertId();
                try {
                    // Get collaborator name for logging
                    $stmtCollab = $this->db->prepare("SELECT full_name FROM employees WHERE id = ?");
                    $stmtCollab->execute([$collaboratorId]);
                    $collabName = $stmtCollab->fetchColumn() ?: 'Desconocido';

                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Envíos',
                        'create',
                        'delivery',
                        (string)$newId,
                        "Registró envío {$ticketCode}",
                        "Asignado a: {$collabName} | Dirección: {$address} | Est.: " . ($estimatedTime ?: 'No definido'),
                        (int)$inv,
                        (int)$userId
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in createDelivery: ' . $logErr->getMessage());
                }
                return $newId;
            }
            return false;
        } catch (Exception $e) {
            error_log("DeliveryModel Error (create): " . $e->getMessage());
            return false;
        }
    }

    public function updateDelivery($id, $userId, $inventoryId, $collaboratorId, $address, $phone = null, $email = null, $estimatedTime = null, $status = null, $isPaid = 0): bool
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return false;

            require_once __DIR__ . '/../helpers/auth_helper.php';
            $ownerId = getInventoryOwnerId((int)$inv) ?? $userId;

            $oldRow = $this->getById($id, $userId, $inv);
            if (!$oldRow) return false;

            $sql = "UPDATE deliveries 
                    SET collaborator_id = :collab, address = :address, phone = :phone, email = :email, estimated_time = :est_time, is_paid = :is_paid" . 
                    ($status !== null ? ", status = :status" : "") . 
                    " WHERE id = :id AND inventory_id = :inv";

            $params = [
                ':id'       => $id,
                ':inv'      => $inv,
                ':collab'   => $collaboratorId,
                ':address'  => $address,
                ':phone'    => $phone,
                ':email'    => $email,
                ':est_time' => !empty($estimatedTime) ? $estimatedTime : null,
                ':is_paid'  => $isPaid
            ];
            if ($status !== null) {
                $params[':status'] = $status;
            }

            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute($params);

            if ($success) {
                try {
                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Envíos',
                        'update',
                        'delivery',
                        (string)$id,
                        "Editó envío {$oldRow['ticket_code']}",
                        "Dirección: {$address}",
                        (int)$inv,
                        (int)$userId
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in updateDelivery: ' . $logErr->getMessage());
                }
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("DeliveryModel Error (update): " . $e->getMessage());
            return false;
        }
    }

    public function completeDelivery($id, $userId, $inventoryId): bool
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return false;

            $oldRow = $this->getById($id, $userId, $inv);
            if (!$oldRow) return false;

            $stmt = $this->db->prepare("
                UPDATE deliveries 
                SET status = 'completed', delivered_at = NOW() 
                WHERE id = :id AND inventory_id = :inv
            ");
            $success = $stmt->execute([':id' => $id, ':inv' => $inv]);

            if ($success) {
                try {
                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Envíos',
                        'complete',
                        'delivery',
                        (string)$id,
                        "Entregó envío {$oldRow['ticket_code']}",
                        "Marcado como completado",
                        (int)$inv,
                        (int)$userId
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in completeDelivery: ' . $logErr->getMessage());
                }
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("DeliveryModel Error (complete): " . $e->getMessage());
            return false;
        }
    }

    public function deleteDelivery($id, $userId, $inventoryId = null): bool
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return false;

            $oldRow = $this->getById($id, $userId, $inv);
            if (!$oldRow) return false;

            $stmt = $this->db->prepare("DELETE FROM deliveries WHERE id = :id AND inventory_id = :inv");
            $success = $stmt->execute([':id' => $id, ':inv' => $inv]);

            if ($success) {
                try {
                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Envíos',
                        'delete',
                        'delivery',
                        (string)$id,
                        "Eliminó envío {$oldRow['ticket_code']}",
                        "Ubicación: {$oldRow['address']}",
                        (int)$inv,
                        (int)$userId
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in deleteDelivery: ' . $logErr->getMessage());
                }
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("DeliveryModel Error (delete): " . $e->getMessage());
            return false;
        }
    }

    public function getAll($userId, $status = null, $inventoryId = null): array
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return [];

            require_once __DIR__ . '/../helpers/auth_helper.php';
            $ownerId = getInventoryOwnerId((int)$inv) ?? $userId;

            $sql = "
                SELECT d.*, 
                       e.full_name AS collaborator_name,
                       s.total_amount AS sale_total,
                       s.sale_date AS sale_date,
                       c.full_name AS customer_name,
                       IFNULL(d.phone, c.phone) AS customer_phone,
                       IFNULL(d.email, c.email) AS customer_email
                FROM deliveries d
                LEFT JOIN employees e ON d.collaborator_id = e.id
                LEFT JOIN sales s ON d.sale_id = s.id
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE d.inventory_id = :inv
            ";

            if ($status !== null) {
                $sql .= " AND d.status = :status";
            }

            $sql .= " ORDER BY d.created_at DESC";

            $stmt = $this->db->prepare($sql);
            $params = [':inv' => $inv];
            if ($status !== null) {
                $params[':status'] = $status;
            }

            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("DeliveryModel Error (getAll): " . $e->getMessage());
            return [];
        }
    }

    public function getById($id, $userId, $inventoryId = null): ?array
    {
        try {
            $inv = $this->resolveInventoryId($inventoryId);
            if (!$inv) return null;

            $sql = "
                SELECT d.*, 
                       e.full_name AS collaborator_name,
                       s.total_amount AS sale_total,
                       c.full_name AS customer_name,
                       IFNULL(d.phone, c.phone) AS customer_phone,
                       IFNULL(d.email, c.email) AS customer_email
                FROM deliveries d
                LEFT JOIN employees e ON d.collaborator_id = e.id
                LEFT JOIN sales s ON d.sale_id = s.id
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE d.id = :id AND d.inventory_id = :inv
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id, ':inv' => $inv]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (Exception $e) {
            error_log("DeliveryModel Error (getById): " . $e->getMessage());
            return null;
        }
    }

    public function getByCollaborator($collaboratorId, $status = 'pending'): array
    {
        try {
            $sql = "
                SELECT d.*, 
                       s.total_amount AS sale_total,
                       c.full_name AS customer_name,
                       IFNULL(d.phone, c.phone) AS customer_phone,
                       IFNULL(d.email, c.email) AS customer_email
                FROM deliveries d
                LEFT JOIN sales s ON d.sale_id = s.id
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE d.collaborator_id = :collab AND d.status = :status
                ORDER BY d.created_at ASC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':collab' => $collaboratorId, ':status' => $status]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("DeliveryModel Error (getByCollaborator): " . $e->getMessage());
            return [];
        }
    }
}
