<?php
namespace App\Models;

use App\core\Database;
use PDO;
use Exception;

class ComboModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Crear un nuevo combo y asociarle sus productos componentes.
     */
    public function createCombo(int $inventoryId, string $name, float $price, array $items): int
    {
        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
            }

            // Insertar cabecera del combo
            $stmt = $this->db->prepare("
                INSERT INTO combos (inventory_id, name, price, is_active) 
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute([$inventoryId, $name, $price]);
            $comboId = (int)$this->db->lastInsertId();

            // Insertar componentes
            $stmtItem = $this->db->prepare("
                INSERT INTO combo_items (combo_id, product_id, quantity) 
                VALUES (?, ?, ?)
            ");
            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $quantity = (float)$item['quantity'];
                if ($quantity <= 0) continue;
                $stmtItem->execute([$comboId, $productId, $quantity]);
            }

            $this->db->commit();
            return $comboId;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Actualizar un combo existente y sus componentes.
     */
    public function updateCombo(int $comboId, string $name, float $price, array $items): bool
    {
        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
            }

            // Actualizar cabecera
            $stmt = $this->db->prepare("
                UPDATE combos 
                SET name = ?, price = ? 
                WHERE id = ?
            ");
            $stmt->execute([$name, $price, $comboId]);

            // Eliminar componentes viejos
            $stmtDel = $this->db->prepare("DELETE FROM combo_items WHERE combo_id = ?");
            $stmtDel->execute([$comboId]);

            // Insertar componentes nuevos
            $stmtItem = $this->db->prepare("
                INSERT INTO combo_items (combo_id, product_id, quantity) 
                VALUES (?, ?, ?)
            ");
            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $quantity = (float)$item['quantity'];
                if ($quantity <= 0) continue;
                $stmtItem->execute([$comboId, $productId, $quantity]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Eliminar un combo (los componentes se borran por ON DELETE CASCADE en la DB).
     */
    public function deleteCombo(int $comboId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM combos WHERE id = ?");
        return $stmt->execute([$comboId]);
    }

    /**
     * Cambiar el estado de activación de un combo.
     */
    public function toggleComboStatus(int $comboId, int $status): bool
    {
        $stmt = $this->db->prepare("UPDATE combos SET is_active = ? WHERE id = ?");
        return $stmt->execute([$status, $comboId]);
    }

    /**
     * Cambiar el estado de visibilidad en el catálogo de un combo.
     */
    public function toggleComboVisibility(int $comboId, int $visibility): bool
    {
        $stmt = $this->db->prepare("UPDATE combos SET public_visible = ? WHERE id = ?");
        return $stmt->execute([$visibility, $comboId]);
    }

    /**
     * Obtener los detalles de un combo específico (incluyendo ingredientes y cálculos).
     */
    public function getCombo(int $comboId, int $inventoryId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM combos WHERE id = ? AND inventory_id = ? LIMIT 1");
        $stmt->execute([$comboId, $inventoryId]);
        $combo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$combo) return null;

        // Obtener ingredientes y enriquecerlos con datos del producto
        $combo['items'] = $this->getComboIngredients($comboId, $inventoryId);
        
        // Calcular valores dinámicos
        $dynamicStock = null;
        $totalCost = 0.00;

        foreach ($combo['items'] as $ing) {
            $reqQty = (float)$ing['quantity'];
            $prodStock = (float)($ing['prod_stock'] ?? 0);
            $prodCost = (float)($ing['prod_cost'] ?? 0);

            // Calcular stock disponible basado en este componente
            $possibleComboStock = floor($prodStock / $reqQty);
            if ($dynamicStock === null || $possibleComboStock < $dynamicStock) {
                $dynamicStock = $possibleComboStock;
            }

            // Sumar al costo del combo
            $totalCost += $prodCost * $reqQty;
        }

        $combo['dynamic_stock'] = ($dynamicStock === null) ? 0 : max(0, (int)$dynamicStock);
        $combo['cost_price'] = $totalCost;

        return $combo;
    }

    /**
     * Obtener listado de combos de un inventario con cálculos dinámicos de stock y costo.
     */
    public function getCombosByInventory(int $inventoryId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM combos WHERE inventory_id = ? ORDER BY name ASC");
        $stmt->execute([$inventoryId]);
        $combos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si no hay combos, retornar vacío
        if (empty($combos)) return [];

        // Obtener contexto de mapeo para este inventario
        $stmtInv = $this->db->prepare("SELECT preferences FROM inventories WHERE id = ? LIMIT 1");
        $stmtInv->execute([$inventoryId]);
        $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);
        $prefs = $inv ? json_decode($inv['preferences'] ?? '{}', true) : [];
        $mapping = $prefs['mapping'] ?? [];

        $colName = $mapping['name'] ?? 'name';
        $colStock = $mapping['stock'] ?? 'stock';
        $colCost = $mapping['receipt_price'] ?? $mapping['buy_price'] ?? 'receipt_price';

        // Obtener nombre de la tabla física del inventario
        $stmtTable = $this->db->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ? LIMIT 1");
        $stmtTable->execute([$inventoryId]);
        $tableRow = $stmtTable->fetch(PDO::FETCH_ASSOC);

        if (!$tableRow) return [];
        $tableName = $tableRow['table_name'];
        $safeTable = "`" . str_replace("`", "``", $tableName) . "`";
        $safeNameCol = "`" . str_replace("`", "``", $colName) . "`";
        $safeStockCol = "`" . str_replace("`", "``", $colStock) . "`";
        $safeCostCol = "`" . str_replace("`", "``", $colCost) . "`";

        foreach ($combos as &$combo) {
            $comboId = $combo['id'];

            // Consultar los componentes e inyectar valores físicos de stock, costo y nombre
            $sql = "
                SELECT ci.product_id, ci.quantity, 
                       p.{$safeNameCol} as prod_name, 
                       p.{$safeStockCol} as prod_stock, 
                       p.{$safeCostCol} as prod_cost 
                FROM combo_items ci
                JOIN {$safeTable} p ON ci.product_id = p.id
                WHERE ci.combo_id = ?
            ";
            $stmtItems = $this->db->prepare($sql);
            $stmtItems->execute([$comboId]);
            $ingredients = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            $dynamicStock = null;
            $totalCost = 0.00;
            $combo['items'] = [];

            foreach ($ingredients as $ing) {
                $reqQty = (float)$ing['quantity'];
                $prodStock = (float)($ing['prod_stock'] ?? 0);
                $prodCost = (float)($ing['prod_cost'] ?? 0);

                // Calcular stock dinámico para este componente
                $possibleComboStock = floor($prodStock / $reqQty);
                if ($dynamicStock === null || $possibleComboStock < $dynamicStock) {
                    $dynamicStock = $possibleComboStock;
                }

                $totalCost += $prodCost * $reqQty;

                $combo['items'][] = [
                    'product_id' => $ing['product_id'],
                    'quantity' => $reqQty,
                    'name' => $ing['prod_name'] ?? 'Producto #' . $ing['product_id'],
                    'stock' => $prodStock,
                    'cost' => $prodCost
                ];
            }

            $combo['dynamic_stock'] = ($dynamicStock === null) ? 0 : max(0, (int)$dynamicStock);
            $combo['cost_price'] = $totalCost;
        }

        return $combos;
    }

    /**
     * Obtener ingredientes detallados para un combo.
     */
    private function getComboIngredients(int $comboId, int $inventoryId): array
    {
        $stmtTable = $this->db->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ? LIMIT 1");
        $stmtTable->execute([$inventoryId]);
        $tableRow = $stmtTable->fetch(PDO::FETCH_ASSOC);
        if (!$tableRow) return [];

        $stmtInv = $this->db->prepare("SELECT preferences FROM inventories WHERE id = ? LIMIT 1");
        $stmtInv->execute([$inventoryId]);
        $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);
        $prefs = $inv ? json_decode($inv['preferences'] ?? '{}', true) : [];
        $mapping = $prefs['mapping'] ?? [];

        $colName = $mapping['name'] ?? 'name';
        $colStock = $mapping['stock'] ?? 'stock';
        $colCost = $mapping['receipt_price'] ?? $mapping['buy_price'] ?? 'receipt_price';

        $safeTable = "`" . str_replace("`", "``", $tableRow['table_name']) . "`";
        $safeNameCol = "`" . str_replace("`", "``", $colName) . "`";
        $safeStockCol = "`" . str_replace("`", "``", $colStock) . "`";
        $safeCostCol = "`" . str_replace("`", "``", $colCost) . "`";

        $sql = "
            SELECT ci.product_id, ci.quantity, 
                   p.{$safeNameCol} as prod_name, 
                   p.{$safeStockCol} as prod_stock, 
                   p.{$safeCostCol} as prod_cost 
            FROM combo_items ci
            JOIN {$safeTable} p ON ci.product_id = p.id
            WHERE ci.combo_id = ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$comboId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
