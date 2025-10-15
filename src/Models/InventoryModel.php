<?php
// src/Models/InventoryModel.php
namespace App\Models;

use App\Core\DataBase;
use PDO;
use Exception;

class InventoryModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DataBase::getInstance();
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
    public function createInventoryAndTable(string $inventoryName, int $userId, string $baseTableName, array $columns): string
    {
        $this->db->beginTransaction();
        try {
            // Crear el registro del "inventario"
            $stmt = $this->db->prepare("INSERT INTO inventories (name, user_id) VALUES (:name, :user_id)");
            $stmt->execute([':name' => $inventoryName, ':user_id' => $userId]);
            $inventoryId = (int)$this->db->lastInsertId();
            if (!$inventoryId) {
                throw new Exception("No se pudo crear el registro del inventario.");
            }

            // Seguridad: Sanitizar Nombres
            $safeBaseName = preg_replace('/[^a-zA-Z0-9_]/', '', $baseTableName);
            $tableName = "user_{$userId}_{$safeBaseName}";

            // Construir la Definicion de Columnas
            $columnDefinitions = [];
            foreach ($columns as $columnName) {
                $safeColumnName = preg_replace('/[^a-zA-Z0-9_]/', '', trim($columnName));
                if (empty($safeColumnName) || strtolower($safeColumnName) === 'id') continue;
                $columnDefinitions[] = "`{$safeColumnName}` TEXT";
            }
            if (empty($columnDefinitions)) {
                throw new Exception("No se proporcionaron columnas válidas.");
            }

            // Construir y Ejecutar la Consulta CREATE TABLE // IF NOT EXISTS
            $sql = "CREATE TABLE `{$tableName}` ( 
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                " . implode(', ', $columnDefinitions) . ",
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $this->db->exec($sql);

            // Guardar Metadatos en user_tables
            $stmt = $this->db->prepare("INSERT INTO user_tables (inventory_id, table_name, columns_json) VALUES (?, ?, ?)");
            $stmt->execute([$inventoryId, $tableName, json_encode($columns)]);

            // Si todo salio bien, confirmo los cambios
            $this->db->commit();
            return $tableName;

        } catch (Exception $e) {
            // Si algo falló, revierto todo
            $this->db->rollBack();
            // Relanzo la excepción para que el controlador la atrape.
            throw new Exception("Error en la base de datos: " . $e->getMessage());
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
}