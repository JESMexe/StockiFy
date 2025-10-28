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

        // Seguridad: Sanitizar nombre de tabla
        $safeTableName = "`" . str_replace("`", "``", $tableName) . "`";

        $this->db->beginTransaction(); // Usamos transacción para inserción masiva

        try {
            if ($overwrite) {
                // Usamos TRUNCATE que es más rápido que DELETE para vaciar
                $this->db->exec("TRUNCATE TABLE {$safeTableName}");
            }

            // Preparamos la consulta INSERT una sola vez
            // Obtenemos las columnas del primer registro (asumimos que todas las filas tienen las mismas)
            $columns = array_keys($data[0]);
            $safeColumns = array_map(fn($col) => "`" . str_replace("`", "``", $col) . "`", $columns);
            $placeholders = implode(',', array_fill(0, count($columns), '?'));

            $sql = "INSERT INTO {$safeTableName} (" . implode(',', $safeColumns) . ") VALUES ({$placeholders})";
            $stmt = $this->db->prepare($sql);

            $insertedCount = 0;
            foreach ($data as $row) {
                // Aseguramos que los valores estén en el mismo orden que las columnas
                $values = [];
                foreach($columns as $col) {
                    $values[] = $row[$col] ?? null; // Usamos null si falta un valor
                }
                if ($stmt->execute($values)) {
                    $insertedCount++;
                } else {
                    // Podríamos loggear el error específico de esta fila si quisiéramos
                    error_log("Error al insertar fila en {$tableName}: " . implode(', ', $stmt->errorInfo()));
                }
            }

            $this->db->commit();
            return $insertedCount;

        } catch (\PDOException $e) {
            $this->db->rollBack();
            throw $e; // Relanzamos para que el controlador lo maneje
        }
    }

    // src/Models/TableModel.php

// ... (después de bulkInsertData) ...

    /**
     * Actualiza el valor de una columna específica para una fila (item) dado.
     * ¡Función genérica útil para editar cualquier celda!
     *
     * @param string $tableName El nombre real de la tabla (user_X_...).
     * @param int $itemId El ID de la fila a actualizar.
     * @param string $columnName El nombre de la columna a actualizar.
     * @param mixed $newValue El nuevo valor para la celda.
     * @return bool True si la actualización fue exitosa, false si no.
     */
    public function updateItemValue(string $tableName, int $itemId, string $columnName, mixed $newValue): bool
    {
        // Seguridad: Sanitizar nombres
        $safeTableName = "`" . str_replace("`", "``", $tableName) . "`";
        // Asumimos que $columnName ya viene sanitizado o lo sanitizamos acá si es necesario
        $safeColumnName = "`" . str_replace("`", "``", $columnName) . "`";

        $sql = "UPDATE {$safeTableName} SET {$safeColumnName} = :newValue WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':newValue' => $newValue,
            ':id' => $itemId
        ]);
    }

    /**
     * Ajusta (suma o resta) el valor numérico de una columna (ej: Stock).
     *
     * @param string $tableName Nombre real de la tabla.
     * @param int $itemId ID de la fila.
     * @param string $stockColumnName Nombre de la columna de stock.
     * @param int $amountToAddOrSubtract Cantidad a sumar (positivo) o restar (negativo).
     * @return int|false El nuevo valor del stock o false si falló.
     */
    public function adjustStock(string $tableName, int $itemId, string $stockColumnName, int $amountToAddOrSubtract): int|false
    {
        // Seguridad
        $safeTableName = "`" . str_replace("`", "``", $tableName) . "`";
        $safeStockColumn = "`" . str_replace("`", "``", $stockColumnName) . "`";

        $this->db->beginTransaction(); // Usamos transacción para seguridad concurrente

        try {
            // 1. Obtenemos el valor actual y bloqueamos la fila (FOR UPDATE)
            $sqlSelect = "SELECT {$safeStockColumn} FROM {$safeTableName} WHERE id = :id FOR UPDATE";
            $stmtSelect = $this->db->prepare($sqlSelect);
            $stmtSelect->execute([':id' => $itemId]);
            $currentStock = $stmtSelect->fetchColumn();

            if ($currentStock === false) {
                throw new Exception("Item no encontrado."); // No encontró el item
            }

            // Calculamos el nuevo stock
            $newStock = (int)$currentStock + $amountToAddOrSubtract;

            // Validación: No permitir stock negativo (podrías hacerlo opcional)
            if ($newStock < 0) {
                throw new Exception("Stock insuficiente. Stock actual: {$currentStock}, se intentó quitar: " . abs($amountToAddOrSubtract));
            }

            // 2. Actualizamos al nuevo valor
            $sqlUpdate = "UPDATE {$safeTableName} SET {$safeStockColumn} = :newStock WHERE id = :id";
            $stmtUpdate = $this->db->prepare($sqlUpdate);
            $success = $stmtUpdate->execute([
                ':newStock' => $newStock,
                ':id' => $itemId
            ]);

            if ($success) {
                $this->db->commit();
                return $newStock; // Devolvemos el nuevo stock
            } else {
                throw new Exception("Error al actualizar el stock en la base de datos.");
            }

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error en adjustStock: " . $e->getMessage()); // Logueo el error
            return false; // Indicamos que falló
        }
    }

    // src/Models/TableModel.php

// ... (después de adjustStock o al final de la clase) ...

    /**
     * Inserta una nueva fila de datos en una tabla dinámica.
     *
     * @param string $tableName Nombre real de la tabla (user_X_...).
     * @param array $data Array asociativo ['columna' => 'valor'] con los datos a insertar.
     * @return array|false La fila recién insertada (incluyendo ID) o false si falla.
     */
    public function insertItem(string $tableName, array $data): array|false
    {
        // Seguridad: Sanitizar nombre de tabla
        $safeTableName = "`" . str_replace("`", "``", $tableName) . "`";

        // Filtro columnas válidas (ignoro 'id' y 'created_at' si vienen del front)
        $columns = [];
        $values = [];
        $placeholders = [];
        foreach ($data as $key => $value) {
            $trimmedKey = trim($key);
            // Solo incluyo columnas que no sean 'id' o 'created_at' (case-insensitive)
            if (strcasecmp($trimmedKey, 'id') !== 0 && strcasecmp($trimmedKey, 'created_at') !== 0) {
                // Sanitizo nombre de columna por seguridad
                $safeColumn = "`" . str_replace("`", "``", $trimmedKey) . "`";
                $columns[] = $safeColumn;
                $values[] = $value; // Guardo el valor
                $placeholders[] = '?'; // Añado un placeholder
            }
        }

        if (empty($columns)) {
            error_log("Intento de insertar fila vacía o solo con columnas automáticas en {$tableName}");
            return false; // No hay nada que insertar
        }

        $sql = "INSERT INTO {$safeTableName} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $this->db->beginTransaction(); // Transacción por si hay triggers o pasos futuros
        try {
            $stmt = $this->db->prepare($sql);
            if ($stmt->execute($values)) {
                $newId = $this->db->lastInsertId();
                // Ahora recupero la fila completa recién insertada
                $selectStmt = $this->db->prepare("SELECT * FROM {$safeTableName} WHERE id = ?");
                $selectStmt->execute([$newId]);
                $newRow = $selectStmt->fetch(PDO::FETCH_ASSOC);

                $this->db->commit();
                return $newRow ?: false; // Devuelvo la fila completa o false si no la encontró
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