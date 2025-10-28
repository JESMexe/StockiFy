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
        // Iniciamos la transacción fuera del try para asegurar que el rollback funcione si begin falla
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }

        try {
            // --- 1. Crear el registro del "inventario" ---
            $stmt = $this->db->prepare("INSERT INTO inventories (name, user_id) VALUES (:name, :user_id)");
            $stmt->execute([':name' => $inventoryName, ':user_id' => $userId]);
            $inventoryId = (int)$this->db->lastInsertId();
            if (!$inventoryId) {
                throw new \PDOException("No se pudo crear el registro del inventorio principal."); // Usamos PDOException
            }

            // --- 2. Seguridad: Sanitizar Nombres ---
            $safeBaseName = preg_replace('/[^a-zA-Z0-9_]/', '', $baseTableName);
            if (empty($safeBaseName)) { // Añadimos validación por si el nombre queda vacío
                throw new \InvalidArgumentException("El nombre base de la tabla es inválido después de sanitizar.");
            }
            $tableName = "user_{$userId}_{$safeBaseName}";

            // --- 3. Construir la Definición de Columnas ---
            $columnDefinitions = [];
            foreach ($columns as $columnName) {
                $trimmedName = trim($columnName);
                if (empty($trimmedName)) continue; // Ignora columnas vacías
                $safeColumnName = preg_replace('/[^a-zA-Z0-9_]/', '', $trimmedName);
                if (empty($safeColumnName)) continue; // Ignora columnas con solo caracteres inválidos
                if (strtolower($safeColumnName) === 'id' || strtolower($safeColumnName) === 'created_at') continue; // Evita duplicados
                $columnDefinitions[] = "`{$safeColumnName}` TEXT";
            }
            if (empty($columnDefinitions)) {
                throw new \InvalidArgumentException("No se proporcionaron nombres de columna válidos.");
            }

            // --- 4. Construir y Ejecutar la Consulta CREATE TABLE (CON IF NOT EXISTS) ---
            // Añadimos IF NOT EXISTS para prevenir el error si ya existe
            $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                " . implode(', ', $columnDefinitions) . ",
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            // Usamos prepare/execute que es ligeramente más seguro que exec para DDL si hay dudas
            $createStmt = $this->db->prepare($sql);
            if (!$createStmt->execute()) {
                // Si falla aquí (a pesar de IF NOT EXISTS), es un error grave
                $errorInfo = $createStmt->errorInfo();
                throw new \PDOException("Error al crear la tabla física: " . ($errorInfo[2] ?? 'Error desconocido'));
            }

            // --- 5. Guardar Metadatos en user_tables ---
            // Verificamos si ya existe una entrada para evitar duplicados en metadatos
            $checkMeta = $this->db->prepare("SELECT id FROM user_tables WHERE inventory_id = ?");
            $checkMeta->execute([$inventoryId]);
            if ($checkMeta->fetchColumn() === false) {
                $stmt = $this->db->prepare("INSERT INTO user_tables (inventory_id, table_name, columns_json) VALUES (?, ?, ?)");
                if (!$stmt->execute([$inventoryId, $tableName, json_encode($columns)])) {
                    $errorInfo = $stmt->errorInfo();
                    throw new \PDOException("Error al guardar metadatos de la tabla: " . ($errorInfo[2] ?? 'Error desconocido'));
                }
            }

            // --- 6. Si todo salió bien, confirmamos los cambios ---
            if ($this->db->inTransaction()) {
                if (!$this->db->commit()) {
                    throw new \PDOException("Fallo al confirmar la transacción (commit).");
                }
            }
            return ['id' => $inventoryId, 'tableName' => $tableName];

        } catch (\PDOException | \InvalidArgumentException $e) { // Capturamos excepciones más específicas
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
        // Inicio transacción fuera del try principal
        try {
            if (!$this->db->inTransaction()) {
                if (!$this->db->beginTransaction()) {
                    throw new \PDOException("Fallo al iniciar la transacción para eliminar.");
                }
            }
        } catch (\PDOException $e) {
            error_log("Error fatal al iniciar transacción para eliminar: " . $e->getMessage());
            throw $e; // Relanzo el error grave
        }

        // Bloque try principal para las operaciones
        try {
            // 1. Verifico propiedad y obtengo metadata
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
            $tableName = $meta['table_name'] ?? null; // Uso ?? null por si no hay tabla en user_tables

            // 2. Elimino la tabla física (si existe en metadatos)
            if ($tableName) {
                $safeTableName = "`" . str_replace("`", "``", $tableName) . "`";
                // Usamos prepare/execute que puede ser más seguro
                $dropStmt = $this->db->prepare("DROP TABLE IF EXISTS {$safeTableName}");
                if (!$dropStmt->execute()) {
                    $errorInfo = $dropStmt->errorInfo();
                    throw new \PDOException("Error al eliminar tabla física {$tableName}: " . ($errorInfo[2] ?? 'Error desconocido'));
                }
            }

            // 3. Elimino la entrada de metadatos (user_tables) - IMPORTANTE: Hacer ANTES de eliminar inventories por FK
            $stmtDeleteMeta = $this->db->prepare("DELETE FROM user_tables WHERE inventory_id = :inventory_id");
            if (!$stmtDeleteMeta->execute([':inventory_id' => $inventoryId])) {
                $errorInfo = $stmtDeleteMeta->errorInfo();
                throw new \PDOException("Error al eliminar metadatos: " . ($errorInfo[2] ?? 'Error desconocido'));
            }


            // 4. Elimino la entrada del inventario (Ahora sí, después de borrar la FK)
            $stmtDeleteInv = $this->db->prepare("DELETE FROM inventories WHERE id = :inventory_id");
            if (!$stmtDeleteInv->execute([':inventory_id' => $inventoryId])) {
                $errorInfo = $stmtDeleteInv->errorInfo();
                throw new \PDOException("Error al eliminar registro de inventario: " . ($errorInfo[2] ?? 'Error desconocido'));
            }

            // 5. Confirmo la transacción (SOLO SI SIGUE ACTIVA)
            if ($this->db->inTransaction()) {
                if (!$this->db->commit()) {
                    throw new \PDOException("Fallo al confirmar la transacción de eliminación.");
                }
            }
            return true;

        } catch (Exception $e) {
            // 6. Revierto (SOLO SI SIGUE ACTIVA)
            if ($this->db->inTransaction()) {
                try {
                    $this->db->rollBack();
                } catch(\PDOException $rollbackEx) {
                    error_log("Error adicional durante rollback de eliminación: " . $rollbackEx->getMessage());
                }
            }
            error_log("Error en deleteInventoryAndData: " . $e->getMessage());
            throw $e; // Relanzo para que el controlador lo maneje
        }
    }
}