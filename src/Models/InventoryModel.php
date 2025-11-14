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
    /**
     * Realiza la operación completa de crear un inventario y su tabla física
     * dentro de una transacción segura.
     * (Versión corregida por StokiFyBot)
     */
    public function createInventoryAndTable(string $inventoryName, int $userId, string $baseTableName, array $columns, array $tablePreferences): array
    {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }

        try {
            // 1. INSERTAR EN LA TABLA MAESTRA 'inventories'
            // (Esto almacena si las columnas están activas)
            $stmt = $this->db->prepare("INSERT INTO inventories (name, user_id, min_stock, sale_price, receipt_price, hard_gain, percentage_gain,auto_price,auto_price_type) 
                                            VALUES (:name, :user_id, :min_stock, :sale_price, :receipt_price, :hard_gain, :percentage_gain, :auto_price, :auto_price_type)");
            $stmt->execute([':name' => $inventoryName, ':user_id' => $userId, ':min_stock' => $tablePreferences['min_stock']['active'],
                ':sale_price' => $tablePreferences['sale_price']['active'], ':receipt_price' => $tablePreferences['receipt_price']['active'],
                ':hard_gain' => $tablePreferences['hard_gain']['active'], ':percentage_gain' => $tablePreferences['percentage_gain']['active'],
                ':auto_price' => $tablePreferences['auto_price'], ':auto_price_type' => $tablePreferences['auto_price_type']]);

            $inventoryId = (int)$this->db->lastInsertId();
            if (!$inventoryId) {
                throw new \PDOException("No se pudo crear el registro del inventorio principal.");
            }

            // 2. SANITIZAR EL NOMBRE DE LA TABLA FÍSICA
            $safeBaseName = $this->sanitizeTableName($baseTableName); // Usa la función que ya existe
            $tableName = "user_{$userId}_{$safeBaseName}";

            // 3. PROCESAR Y SANITIZAR LAS COLUMNAS DE USUARIO
            $columnDefinitions = []; // Para el SQL (ej. `nombre` VARCHAR(255))
            $userColumnsJson = []; // Para el JSON (ej. 'nombre')

            foreach ($columns as $columnName) {
                $trimmedName = trim($columnName);
                if (empty($trimmedName)) continue;
                $safeColumnName = $this->sanitizeColumnName($trimmedName); // Usa la función que ya existe
                if (empty($safeColumnName)) continue;

                // Filtramos las columnas 100% protegidas
                if (in_array(strtolower($safeColumnName), ['id', 'created_at'])) continue;

                // Asignamos tipos de datos
                $colType = "TEXT"; // Default
                if (in_array(strtolower($safeColumnName), ['name', 'nombre'])) {
                    $colType = "VARCHAR(255) NOT NULL";
                } else if (strtolower($safeColumnName) === 'stock') {
                    $colType = "INT DEFAULT 0";
                }

                // Añadimos a ambas listas
                $columnDefinitions[] = "`{$safeColumnName}` {$colType}";
                $userColumnsJson[] = $safeColumnName;
            }

            // 4. OBTENER VALORES DEFAULT (DE NANO)
            $minStockDefault = (int) $tablePreferences['min_stock']['default'];
            $salePriceDefault = (float) $tablePreferences['sale_price']['default'];
            $receiptPriceDefault = (float) $tablePreferences['receipt_price']['default'];
            $gainDefault = (float) $tablePreferences['hard_gain']['default']; // Asumimos que hard_gain tiene el default

            // 5. CONSTRUIR LA CONSULTA SQL (CORREGIDA)
            $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `min_stock` INT DEFAULT {$minStockDefault},
                `sale_price` DECIMAL(10,2) DEFAULT {$salePriceDefault},
                `receipt_price` DECIMAL(10,2) DEFAULT {$receiptPriceDefault},
                `hard_gain` DECIMAL(10,2) DEFAULT {$gainDefault},
                `percentage_gain` DECIMAL(10,2) DEFAULT {$gainDefault},
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ";

            // Añadimos las columnas de usuario (si existen)
            if (!empty($columnDefinitions)) {
                $sql .= ", " . implode(', ', $columnDefinitions);
            }

            // Cerramos la consulta
            $sql .= ")";

            // 6. EJECUTAR EL CREATE TABLE
            if ($this->db->exec($sql) === false) {
                $errorInfo = $this->db->errorInfo();
                throw new \PDOException("Error al crear la tabla física: " . ($errorInfo[2] ?? 'Error desconocido'));
            }

            // 7. CONSTRUIR Y GUARDAR LOS METADATOS (JSON)
            $columnJson = ['id', 'created_at']; // Columnas base (no-editables)

            // Añadimos las recomendadas (si están activas)
            if ($tablePreferences['min_stock']['active']) { $columnJson[] = 'min_stock'; }
            if ($tablePreferences['sale_price']['active']) { $columnJson[] = 'sale_price'; }
            if ($tablePreferences['receipt_price']['active']) { $columnJson[] = 'receipt_price'; }
            if ($tablePreferences['hard_gain']['active']) { $columnJson[] = 'hard_gain'; }
            if ($tablePreferences['percentage_gain']['active']) { $columnJson[] = 'percentage_gain'; }

            // Añadimos las del usuario (ej. 'nombre', 'stock', 'sku')
            if (!empty($userColumnsJson)) {
                $columnJson = array_merge($columnJson, $userColumnsJson);
            }

            // Eliminamos duplicados por si acaso
            $columnJson = array_unique($columnJson);

            $checkMeta = $this->db->prepare("SELECT id FROM user_tables WHERE inventory_id = ?");
            $checkMeta->execute([$inventoryId]);

            if ($checkMeta->fetchColumn() === false) {
                $stmt = $this->db->prepare("INSERT INTO user_tables (inventory_id, table_name, columns_json) VALUES (?, ?, ?)");
                if (!$stmt->execute([$inventoryId, $tableName, json_encode(array_values($columnJson))])) { // Usamos array_values para reindexar
                    $errorInfo = $stmt->errorInfo();
                    throw new \PDOException("Error al guardar metadatos de la tabla: " . ($errorInfo[2] ?? 'Error desconocido'));
                }
            }

            // 8. COMMIT
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
     * Devuelve todas las bases de inventario de un usuario.
     *
     * @param int $userId
     * @return array
     */
    public function findByUserId(int $userId): array
    {
        $sql = "SELECT id, name, user_id, created_at
                    FROM inventories
                    WHERE user_id = :user_id
                    ORDER BY created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
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
    public function sanitizeColumnName(string $columnName): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
        if (empty($safeName) || is_numeric(substr($safeName, 0, 1))) {
            throw new \InvalidArgumentException("El nombre de la columna es inválido.");
        }
        return $safeName;
    }

    public function sanitizeTableName(string $baseTableName): string
    {
        // La misma lógica que usábamos antes, ahora encapsulada
        $safeBaseName = preg_replace('/[^a-zA-Z0-9_]/', '', $baseTableName);
        if (empty($safeBaseName)) {
            throw new \InvalidArgumentException("El nombre base de la tabla es inválido después de sanitizar.");
        }
        return $safeBaseName;
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