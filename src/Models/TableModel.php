<?php
namespace App\Models;

use App\core\Database;
use Exception;
use PDO;

class TableModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obtiene los metadatos de una tabla de usuario (nombre real y columnas).
     * @param int $inventoryId El ID del inventario activo.
     * @return array|false Un array con 'table_name' y 'columns_json' o false si no se encuentra.
     */
    public function getTableMetadata(int $inventoryId): array|false
    {
        $stmt = $this->db->prepare("SELECT table_name, columns_json FROM user_tables WHERE inventory_id = :id");
        $stmt->execute([':id' => $inventoryId]);
        return $stmt->fetch();
    }

    /**
     * Obtiene todos los datos de una tabla específica.
     * @param string $tableName El nombre real y seguro de la tabla (ej: user_1_mi_tienda).
     * @return array Una lista de todas las filas de la tabla.
     */
    public function getData(string $tableName): array
    {
        $safeTableName = "`" . str_replace("`", "``", $tableName) . "`";
        $stmt = $this->db->query("SELECT * FROM {$safeTableName}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Inserta múltiples filas de datos en una tabla dinámica.
     * @param string $tableName Nombre real de la tabla (user_X_...).
     * @param array $data Array de arrays asociativos ['columna' => 'valor'].
     * @param bool $overwrite Si es true, trunca la tabla antes de insertar.
     * @return int Número de filas insertadas.
     * @throws \PDOException Si ocurre un error SQL.
     */
    public function bulkInsertData(string $tableName, array $data, bool $overwrite = false): int
    {
        if (empty($data)) {
            return 0;
        }

        $safeTableName = "`" . str_replace("`", "``", $tableName) . "`";

        $this->db->beginTransaction();

        try {
            if ($overwrite) {
                $this->db->exec("TRUNCATE TABLE {$safeTableName}");
            }

            $columns = array_keys($data[0]);
            $safeColumns = array_map(fn($col) => "`" . str_replace("`", "``", $col) . "`", $columns);
            $placeholders = implode(',', array_fill(0, count($columns), '?'));

            $sql = "INSERT INTO {$safeTableName} (" . implode(',', $safeColumns) . ") VALUES ({$placeholders})";
            $stmt = $this->db->prepare($sql);

            $insertedCount = 0;
            foreach ($data as $row) {
                $values = [];
                foreach($columns as $col) {
                    $values[] = $row[$col] ?? null;
                }
                if ($stmt->execute($values)) {
                    $insertedCount++;
                } else {
                    error_log("Error al insertar fila en {$tableName}: " . implode(', ', $stmt->errorInfo()));
                }
            }

            $this->db->commit();
            return $insertedCount;

        } catch (\PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza una fila completa en una tabla dinámica.
     * @param string $tableName El nombre real de la tabla (user_X_...).
     * @param int $itemId El ID de la fila a actualizar.
     * @param array $dataToUpdate Un array asociativo ['columna' => 'valor', ...].
     * @return array|false La fila actualizada o false si falla.
     */
    public function updateItemRow(string $tableName, int $itemId, array $dataToUpdate): array|false
    {
        // Seguridad: Sanitizo el nombre de la tabla
        $safeTableName = "`" . str_replace("`", "``", $tableName) . "`";

        $setClauses = []; // Acá armo la parte SET de la consulta
        $bindings = [':id' => $itemId]; // Acá van los valores a bindear

        // Construyo la consulta dinámicamente
        foreach ($dataToUpdate as $column => $value) {
            // Me aseguro de no intentar actualizar las columnas automáticas
            if (strcasecmp($column, 'id') === 0 || strcasecmp($column, 'created_at') === 0) {
                continue; // Salto esta columna
            }

            // Sanitizo el nombre de la columna
            $safeColumn = "`" . str_replace("`", "``", trim($column)) . "`";
            $placeholder = ":" . preg_replace('/[^a-zA-Z0-9_]/', '', trim($column)); // ej: :Nombre

            $setClauses[] = "{$safeColumn} = {$placeholder}";
            $bindings[$placeholder] = $value;
        }

        if (empty($setClauses)) {
            // No se envió nada válido para actualizar
            throw new \InvalidArgumentException("No hay datos válidos para actualizar.");
        }

        $sql = "UPDATE {$safeTableName} SET " . implode(', ', $setClauses) . " WHERE id = :id";

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare($sql);
            if (!$stmt->execute($bindings)) {
                $errorInfo = $stmt->errorInfo();
                throw new \PDOException("Error al ejecutar UPDATE: " . ($errorInfo[2] ?? 'Error desconocido'));
            }

            // Si el UPDATE funcionó, busco la fila actualizada para devolverla
            $selectStmt = $this->db->prepare("SELECT * FROM {$safeTableName} WHERE id = ?");
            $selectStmt->execute([$itemId]);
            $updatedRow = $selectStmt->fetch(PDO::FETCH_ASSOC);

            $this->db->commit();
            return $updatedRow;

        } catch (\PDOException | \InvalidArgumentException $e) {
            $this->db->rollBack();
            error_log("Error en TableModel::updateItemRow: " . $e->getMessage());
            throw $e; // Relanzo para que el controlador lo maneje
        }
    }

    /**
     * Inserta una nueva fila de datos en una tabla dinámica.
     *
     * @param string $tableName Nombre real de la tabla (user_X_...).
     * @param array $data Array asociativo ['columna' => 'valor'] con los datos a insertar.
     * @return array|false La fila recién insertada (incluyendo ID) o false si falla.
     */
    public function insertItem(string $tableName, array $data): array|false
    {
        $safeTableName = "`" . str_replace("`", "``", $tableName) . "`";

        $columns = [];
        $values = [];
        $placeholders = [];
        foreach ($data as $key => $value) {
            $trimmedKey = trim($key);
            if (strcasecmp($trimmedKey, 'id') !== 0 && strcasecmp($trimmedKey, 'created_at') !== 0) {
                $safeColumn = "`" . str_replace("`", "``", $trimmedKey) . "`";
                $columns[] = $safeColumn;
                $values[] = $value; // Guardo el valor
                $placeholders[] = '?'; // Añado un placeholder
            }
        }

        if (empty($columns)) {
            error_log("Intento de insertar fila vacía o solo con columnas automáticas en {$tableName}");
            return false;
        }

        $sql = "INSERT INTO {$safeTableName} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare($sql);
            if ($stmt->execute($values)) {
                $newId = $this->db->lastInsertId();
                $selectStmt = $this->db->prepare("SELECT * FROM {$safeTableName} WHERE id = ?");
                $selectStmt->execute([$newId]);
                $newRow = $selectStmt->fetch(PDO::FETCH_ASSOC);

                $this->db->commit();
                return $newRow ?: false;
            } else {
                throw new \PDOException("Error al ejecutar la inserción: " . implode(', ', $stmt->errorInfo()));
            }
        } catch (\PDOException $e) {
            $this->db->rollBack();
            error_log("Error en TableModel::insertItem: " . $e->getMessage());
            return false;
        }
    }
}