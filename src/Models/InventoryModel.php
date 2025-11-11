<?php
// src/Models/InventoryModel.php
namespace App\Models;

use App\Core\Database;
use PDO;
use Exception;

class InventoryModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Realiza la operación completa de crear un inventario y su tabla física
     * dentro de una transacción segura.
     * @param string $inventoryName El nombre del "proyecto" o inventario.
     * @param int $userId El ID del usuario propietario.
     * @param string $baseTableName El nombre que el usuario dio para su tabla.
     * @param array $columns Las columnas definidas por el usuario.
     * @return string El nombre real de la tabla creada.
     * @throws Exception Si algo falla, lanza una excepción.
     */
    public function createInventoryAndTable(string $inventoryName, int $userId, string $baseTableName, array $columns): array
    {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare("INSERT INTO inventories (name, user_id) VALUES (:name, :user_id)");
            $stmt->execute([':name' => $inventoryName, ':user_id' => $userId]);
            $inventoryId = (int)$this->db->lastInsertId();
            if (!$inventoryId) {
                throw new \PDOException("No se pudo crear el registro del inventorio principal.");
            }

            $safeBaseName = preg_replace('/[^a-zA-Z0-9_]/', '', $baseTableName);
            if (empty($safeBaseName)) {
                throw new \InvalidArgumentException("El nombre base de la tabla es inválido después de sanitizar.");
            }
            $tableName = "user_{$userId}_{$safeBaseName}";

            $columnDefinitions = [];
            foreach ($columns as $columnName) {
                $trimmedName = trim($columnName);
                if (empty($trimmedName)) continue;
                $safeColumnName = preg_replace('/[^a-zA-Z0-9_]/', '', $trimmedName);
                if (empty($safeColumnName)) continue;
                if (strtolower($safeColumnName) === 'id' || strtolower($safeColumnName) === 'created_at') continue;
                $columnDefinitions[] = "`{$safeColumnName}` TEXT";
            }
            if (empty($columnDefinitions)) {
                throw new \InvalidArgumentException("No se proporcionaron nombres de columna válidos.");
            }

            $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                " . implode(', ', $columnDefinitions) . ",
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $createStmt = $this->db->prepare($sql);
            if (!$createStmt->execute()) {
                $errorInfo = $createStmt->errorInfo();
                throw new \PDOException("Error al crear la tabla física: " . ($errorInfo[2] ?? 'Error desconocido'));
            }

            $checkMeta = $this->db->prepare("SELECT id FROM user_tables WHERE inventory_id = ?");
            $checkMeta->execute([$inventoryId]);
            if ($checkMeta->fetchColumn() === false) {
                $stmt = $this->db->prepare("INSERT INTO user_tables (inventory_id, table_name, columns_json) VALUES (?, ?, ?)");
                if (!$stmt->execute([$inventoryId, $tableName, json_encode($columns)])) {
                    $errorInfo = $stmt->errorInfo();
                    throw new \PDOException("Error al guardar metadatos de la tabla: " . ($errorInfo[2] ?? 'Error desconocido'));
                }
            }

            if ($this->db->inTransaction()) {
                if (!$this->db->commit()) {
                    throw new \PDOException("Fallo al confirmar la transacción (commit).");
                }
            }
            return ['id' => $inventoryId, 'tableName' => $tableName];

        } catch (\PDOException | \InvalidArgumentException $e) {
            if ($this->db->inTransaction()) {
                try {
                    $this->db->rollBack();
                } catch (\PDOException $rollbackEx) {
                    error_log("¡ERROR ADICIONAL EN ROLLBACK!: " . $rollbackEx->getMessage());
                }
            }
            throw $e;
        }
    }

    /**
     * Busca todos los inventarios que pertenecen a un usuario.
     * @param int $userId El ID del usuario.
     * @return array Una lista de los inventarios.
     */
    public function findByUserId(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name FROM inventories WHERE user_id = :user_id ORDER BY name ASC"
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }


    /**
     * Elimina un inventario, su tabla física y sus metadatos.
     * @param int $inventoryId ID del inventario a eliminar.
     * @param int $userId ID del usuario propietario (para verificación).
     * @return bool True si se eliminó con éxito, false si no.
     * @throws Exception Si el usuario no es el propietario o si falla la DB.
     */
    public function deleteInventoryAndData(int $inventoryId, int $userId): bool
    {
        try {
            if (!$this->db->inTransaction()) {
                if (!$this->db->beginTransaction()) {
                    throw new \PDOException("Fallo al iniciar la transacción para eliminar.");
                }
            }
        } catch (\PDOException $e) {
            error_log("Error fatal al iniciar transacción para eliminar: " . $e->getMessage());
            throw $e;
        }

        try {

            $stmtMeta = $this->db->prepare(
                "SELECT i.name, ut.table_name
                 FROM inventories i
                 LEFT JOIN user_tables ut ON i.id = ut.inventory_id
                 WHERE i.id = :inventory_id AND i.user_id = :user_id"
            );
            $stmtMeta->execute([':inventory_id' => $inventoryId, ':user_id' => $userId]);
            $meta = $stmtMeta->fetch(PDO::FETCH_ASSOC);

            if (!$meta) {
                throw new Exception("Inventario no encontrado o no tenés permiso para eliminarlo.");
            }
            $tableName = $meta['table_name'] ?? null;

            if ($tableName) {
                $safeTableName = "`" . str_replace("`", "``", $tableName) . "`";
                $dropStmt = $this->db->prepare("DROP TABLE IF EXISTS {$safeTableName}");
                if (!$dropStmt->execute()) {
                    $errorInfo = $dropStmt->errorInfo();
                    throw new \PDOException("Error al eliminar tabla física {$tableName}: " . ($errorInfo[2] ?? 'Error desconocido'));
                }
            }

            $stmtDeleteMeta = $this->db->prepare("DELETE FROM user_tables WHERE inventory_id = :inventory_id");
            if (!$stmtDeleteMeta->execute([':inventory_id' => $inventoryId])) {
                $errorInfo = $stmtDeleteMeta->errorInfo();
                throw new \PDOException("Error al eliminar metadatos: " . ($errorInfo[2] ?? 'Error desconocido'));
            }

            $stmtDeleteInv = $this->db->prepare("DELETE FROM inventories WHERE id = :inventory_id");
            if (!$stmtDeleteInv->execute([':inventory_id' => $inventoryId])) {
                $errorInfo = $stmtDeleteInv->errorInfo();
                throw new \PDOException("Error al eliminar registro de inventario: " . ($errorInfo[2] ?? 'Error desconocido'));
            }

            if ($this->db->inTransaction()) {
                if (!$this->db->commit()) {
                    throw new \PDOException("Fallo al confirmar la transacción de eliminación.");
                }
            }
            return true;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                try {
                    $this->db->rollBack();
                } catch(\PDOException $rollbackEx) {
                    error_log("Error adicional durante rollback de eliminación: " . $rollbackEx->getMessage());
                }
            }
            error_log("Error en deleteInventoryAndData: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sanitiza un nombre de columna para prevenir inyección SQL.
     * Permite solo alfanuméricos y guión bajo.
     */
    private function sanitizeColumnName(string $columnName): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
        if (empty($safeName) || is_numeric(substr($safeName, 0, 1))) {
            throw new \InvalidArgumentException("El nombre de la columna es inválido.");
        }
        return $safeName;
    }

    /**
     * Obtiene la información clave de la tabla de un inventario.
     * Verifica la propiedad del usuario.
     */
    private function getInventoryTableInfo(int $inventoryId, int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT ut.table_name, ut.columns_json
             FROM user_tables ut
             JOIN inventories i ON ut.inventory_id = i.id
             WHERE ut.inventory_id = :inventory_id AND i.user_id = :user_id"
        );
        $stmt->execute([':inventory_id' => $inventoryId, ':user_id' => $userId]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$info) {
            throw new Exception("Inventario no encontrado o no tienes permiso.");
        }

        $info['columns'] = json_decode($info['columns_json'], true) ?? [];
        return $info;
    }

    /**
     * Actualiza la lista de columnas (metadatos) en la tabla user_tables.
     */
    private function updateInventoryColumns(int $inventoryId, array $columns): bool
    {
        $stmt = $this->db->prepare("UPDATE user_tables SET columns_json = ? WHERE inventory_id = ?");
        return $stmt->execute([json_encode($columns), $inventoryId]);
    }

    /**
     * Añade una nueva columna a una tabla de inventario.
     */
    public function addColumn(int $inventoryId, int $userId, string $columnName): void
    {
        $safeColumnName = $this->sanitizeColumnName($columnName);
        if (in_array(strtolower($safeColumnName), ['id', 'created_at'])) {
            throw new \InvalidArgumentException("No se puede añadir una columna con ese nombre.");
        }

        $this->db->beginTransaction(); // Inicia transacción
        try {
            $tableInfo = $this->getInventoryTableInfo($inventoryId, $userId);
            $tableName = $tableInfo['table_name'];
            $columns = $tableInfo['columns'];

            if (in_array($safeColumnName, $columns)) {
                throw new \InvalidArgumentException("La columna '{$safeColumnName}' ya existe.");
            }

            // 1. Actualizar los metadatos (Transaccional)
            $columns[] = $safeColumnName;
            $this->updateInventoryColumns($inventoryId, $columns);

            // 2. Modificar la tabla física (Provoca "commit implícito")
            $sql = "ALTER TABLE `{$tableName}` ADD COLUMN `{$safeColumnName}` TEXT";
            $this->db->exec($sql);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e; // Re-lanza la excepción (ej. "La columna ya existe")
        }
    }

    /**
     * Elimina una columna de una tabla de inventario.
     */
    public function dropColumn(int $inventoryId, int $userId, string $columnName): void
    {
        $safeColumnName = $this->sanitizeColumnName($columnName);
        if (in_array(strtolower($safeColumnName), ['id', 'created_at'])) {
            throw new \InvalidArgumentException("No se pueden eliminar las columnas protegidas.");
        }

        $this->db->beginTransaction(); // Inicia transacción
        try {
            $tableInfo = $this->getInventoryTableInfo($inventoryId, $userId);
            $tableName = $tableInfo['table_name'];
            $columns = $tableInfo['columns'];

            // 1. Actualizar los metadatos (Transaccional)
            $newColumns = array_filter($columns, fn($col) => $col !== $safeColumnName);
            $this->updateInventoryColumns($inventoryId, array_values($newColumns));

            // 2. Modificar la tabla física (Provoca "commit implícito")
            $sql = "ALTER TABLE `{$tableName}` DROP COLUMN `{$safeColumnName}`";
            $this->db->exec($sql);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Renombra una columna de una tabla de inventario.
     */
    public function renameColumn(int $inventoryId, int $userId, string $oldName, string $newName): void
    {
        $safeOldName = $this->sanitizeColumnName($oldName);
        $safeNewName = $this->sanitizeColumnName($newName);

        if (in_array(strtolower($safeOldName), ['id', 'created_at'])) {
            throw new \InvalidArgumentException("No se pueden renombrar las columnas protegidas.");
        }
        if (in_array(strtolower($safeNewName), ['id', 'created_at'])) {
            throw new \InvalidArgumentException("No se puede usar ese nuevo nombre.");
        }

        $this->db->beginTransaction();
        try {
            $tableInfo = $this->getInventoryTableInfo($inventoryId, $userId);
            $tableName = $tableInfo['table_name'];
            $columns = $tableInfo['columns'];

            // 1. Actualizar los metadatos - ELIMINAR ESPACIOS en la comparación
            $newColumns = [];
            $found = false;

            foreach ($columns as $col) {
                // Comparar SIN espacios
                if (trim($col) === trim($oldName)) {
                    $newColumns[] = $newName; // Usar el nuevo nombre
                    $found = true;
                } else {
                    $newColumns[] = $col; // Mantener el original (incluso con espacios)
                }
            }

            if (!$found) {
                throw new Exception("No se encontró la columna '$oldName' en la configuración");
            }


            // 2. Actualizar JSON
            $this->updateInventoryColumns($inventoryId, $newColumns);

            // 3. Modificar la tabla física
            $sql = "ALTER TABLE `{$tableName}` CHANGE COLUMN `{$safeOldName}` `{$safeNewName}` TEXT";
            $this->db->exec($sql);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function cleanColumnSpaces(array $columns): array
    {
        return array_map('trim', $columns);
        // $columns = $this->cleanColumnSpaces($tableInfo['columns']);
    }
}