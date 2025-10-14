<?php
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

    // La función create() que ya teníamos se queda igual
    public function create(string $name, int $userId): int|false
    {
        $stmt = $this->db->prepare(
            "INSERT INTO inventories (name, user_id) VALUES (:name, :user_id)"
        );
        $success = $stmt->execute([
            ':name' => $name,
            ':user_id' => $userId
        ]);

        return $success ? (int)$this->db->lastInsertId() : false;
    }

    /*
     * Crea una tabla física en la base de datos para un inventario.
     * @param int $userId El ID del usuario.
     * @param int $inventoryId El ID del registro del inventario.
     * @param string $baseName El nombre base que el usuario le dio a la tabla.
     * @param array $columns Las columnas definidas por el usuario.
     * @return string|false El nombre real de la tabla creada o false si falla.
     */
    public function createTableForInventory(int $userId, int $inventoryId, string $baseName, array $columns): string|false
    {
        // --- 1. Seguridad: Sanitizar Nombres ---
        // Elimina cualquier carácter que no sea letra, número o guion bajo.
        $safeBaseName = preg_replace('/[^a-zA-Z0-9_]/', '', $baseName);
        $tableName = "user_{$userId}_{$safeBaseName}"; // ej: user_1_mis_productos

        // --- 2. Construir la Definición de Columnas ---
        $columnDefinitions = [];
        foreach ($columns as $columnName) {
            $safeColumnName = preg_replace('/[^a-zA-Z0-9_]/', '', trim($columnName));
            if (empty($safeColumnName)) continue; // Ignora columnas vacías o inválidas

            // Por simplicidad, asumimos que todas las columnas son de tipo TEXT.
            // En una app más avanzada, podrías permitir al usuario elegir el tipo.
            $columnDefinitions[] = "`{$safeColumnName}` TEXT";
        }

        if (count($columnDefinitions) === 0) {
            throw new Exception("No se proporcionaron columnas válidas.");
        }

        // --- 3. Construir y Ejecutar la Consulta CREATE TABLE ---
        $sql = "CREATE TABLE `{$tableName}` IF NOT EXISTS (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            " . implode(', ', $columnDefinitions) . ",
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        try {
            $this->db->exec($sql);

            // --- 4. Guardar Metadatos en nuestra tabla de registro ---
            $stmt = $this->db->prepare(
                "INSERT INTO user_tables (inventory_id, table_name, columns_json) VALUES (:inv_id, :t_name, :cols)"
            );
            $stmt->execute([
                ':inv_id' => $inventoryId,
                ':t_name' => $tableName,
                ':cols' => json_encode($columns) // Guardamos la definición original
            ]);

            return $tableName;
        } catch (Exception $e) {
            // Si algo falla, registramos el error (en un sistema real) y devolvemos false.
            error_log($e->getMessage());
            return false;
        }
    }
}