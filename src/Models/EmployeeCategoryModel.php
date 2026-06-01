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
            $success = $stmt->execute([
                ':user' => $userId,
                ':inv' => $inventoryId,
                ':name' => $name,
                ':fields' => json_encode($fields)
            ]);

            if ($success) {
                $newId = $this->db->lastInsertId();
                try {
                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Empleados',
                        'create_category',
                        'employee_category',
                        (string)$newId,
                        "Creó la categoría de empleado: " . $name,
                        "Campos: " . implode(', ', (array)$fields),
                        (int)$inventoryId,
                        (int)$userId
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in createCategory: ' . $logErr->getMessage());
                }
                return $newId;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function updateCategory($id, $userId, $inventoryId, $name, $fields)
    {
        try {
            // Fetch old record for logging comparison
            $oldRow = $this->getById($id, $userId, $inventoryId);

            $stmt = $this->db->prepare("
                UPDATE employee_categories
                SET name = :name, fields = :fields
                WHERE id = :id AND user_id = :user AND inventory_id = :inv
            ");
            $success = $stmt->execute([
                ':id' => $id,
                ':user' => $userId,
                ':inv' => $inventoryId,
                ':name' => $name,
                ':fields' => json_encode($fields)
            ]);

            if ($success && $oldRow) {
                try {
                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Empleados',
                        'update_category',
                        'employee_category',
                        (string)$id,
                        "Editó la categoría de empleado: " . $name,
                        "Anterior - Nombre: {$oldRow['name']}, Campos: " . implode(', ', (array)$oldRow['fields']) . " | " .
                        "Nuevo - Nombre: {$name}, Campos: " . implode(', ', (array)$fields),
                        (int)$inventoryId,
                        (int)$userId
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in updateCategory: ' . $logErr->getMessage());
                }
                return true;
            }
            return $success;
        } catch (Exception $e) {
            return false;
        }
    }

    public function deleteCategory($id, $userId, $inventoryId)
    {
        try {
            // Fetch old record for logging
            $oldRow = $this->getById($id, $userId, $inventoryId);

            $stmt = $this->db->prepare("DELETE FROM employee_categories WHERE id = :id AND user_id = :user AND inventory_id = :inv");
            $success = $stmt->execute([':id' => $id, ':user' => $userId, ':inv' => $inventoryId]);

            if ($success && $oldRow) {
                try {
                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Empleados',
                        'delete_category',
                        'employee_category',
                        (string)$id,
                        "Eliminó la categoría de empleado: " . $oldRow['name'],
                        "Campos: " . implode(', ', (array)$oldRow['fields']),
                        (int)$inventoryId,
                        (int)$userId
                    );
                } catch (\Throwable $logErr) {
                    error_log('ActivityLogger error in deleteCategory: ' . $logErr->getMessage());
                }
            }
            return $success;
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
