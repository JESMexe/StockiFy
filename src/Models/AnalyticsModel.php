<?php
namespace App\Models;

require_once dirname(__DIR__) . '/core/Database.php';

use App\core\Database;
use PDO;
use Exception;

class AnalyticsModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Smart Currency Parser
     * Handles formats: "1,500.00" (US), "1.500,00" (AR/EU), or raw numbers.
     */
    private function parseCurrency($value): float {
        if (empty($value)) return 0.0;

        $clean = (string)$value;
        $clean = str_replace(['$', ' '], '', $clean);
        $clean = trim($clean);

        // Case A: Comma present (e.g. "1.500,50") -> AR/EU Format
        if (strpos($clean, ',') !== false) {
            $clean = str_replace('.', '', $clean); // Remove thousands separator
            $clean = str_replace(',', '.', $clean); // Comma to dot
            return (float)$clean;
        }

        // Case B: Only dots (Ambiguity: 300.000 vs 300.00)
        // Heuristic: If last dot is followed by exactly 3 digits, assume thousands separator.
        if (preg_match('/\.(\d{3})$/', $clean)) {
            $clean = str_replace('.', '', $clean);
        }

        return (float)$clean;
    }

    /**
     * Get financial totals (Revenue, Expenses, Balance)
     * Reads from 'sales' (total_amount) and 'purchases' (total).
     */
    public function getFinancialTotals($userId, $inventoryId = null, $startDate = null, $endDate = null) {
        $params = [':user' => $userId];

        // SALES FILTER
        // Note: Currently filtering by User. If 'sales' table gets 'inventory_id', add: "AND inventory_id = :inv"
        $whereSales = "WHERE user_id = :user";

        // PURCHASES FILTER
        $wherePurch = "WHERE user_id = :user";

        if ($startDate && $endDate) {
            $whereSales .= " AND sale_date BETWEEN :start AND :end";
            $wherePurch .= " AND created_at BETWEEN :start AND :end";
            $params[':start'] = $startDate;
            $params[':end'] = $endDate;
        }

        try {
            // 1. REVENUE (From 'sales' table)
            $stmt = $this->db->prepare("SELECT total_amount FROM sales $whereSales");
            $stmt->execute($params);
            $salesRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $revenue = 0.0;
            $salesCount = count($salesRows);
            foreach ($salesRows as $row) {
                $revenue += $this->parseCurrency($row['total_amount'] ?? 0);
            }

            // 2. EXPENSES (From 'purchases' table)
            // Separate params array for purchases just in case logic diverges
            $purchParams = $params;
            $stmt = $this->db->prepare("SELECT total FROM purchases $wherePurch");
            $stmt->execute($purchParams);
            $purchRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $expenses = 0.0;
            $purchCount = count($purchRows);
            foreach ($purchRows as $row) {
                $expenses += $this->parseCurrency($row['total'] ?? 0);
            }

            $avgTicket = $salesCount > 0 ? ($revenue / $salesCount) : 0;

            return [
                'revenue' => $revenue,
                'expenses' => $expenses,
                'balance' => $revenue - $expenses,
                'average_ticket' => $avgTicket, // <--- NUEVO DATO
                'sales_count' => $salesCount,
                'purchases_count' => $purchCount
            ];

        } catch (Exception $e) {
            return [
                'revenue' => 0, 'expenses' => 0, 'balance' => 0,
                'average_ticket' => 0,
                'sales_count' => 0, 'purchases_count' => 0
            ];
        }
    }



    /**
     * Calculate total inventory value (Stock * Cost)
     * Reads dynamically from the user's table defined in 'inventories'.
     */
    public function getInventoryValuation($userId, $inventoryId = null): float|int
    {
        try {
            // Get inventory config (Specific ID or Last Active)
            if ($inventoryId) {
                $stmt = $this->db->prepare("SELECT id, preferences FROM inventories WHERE id = ? AND user_id = ?");
                $stmt->execute([$inventoryId, $userId]);
            } else {
                $stmt = $this->db->prepare("SELECT id, preferences FROM inventories WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$userId]);
            }
            $inv = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$inv) return 0;

            $prefs = json_decode($inv['preferences'] ?? '{}', true);
            $mapping = $prefs['mapping'] ?? [];
            $stockCol = $mapping['stock'] ?? null;
            $costCol = $mapping['buy_price'] ?? null;

            if (!$stockCol || !$costCol) return 0;

            $stmtTable = $this->db->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
            $stmtTable->execute([$inv['id']]);
            $tableRow = $stmtTable->fetch(PDO::FETCH_ASSOC);

            if (!$tableRow) return 0;

            $tableName = "`" . str_replace("`", "``", $tableRow['table_name']) . "`";
            $colStock = "`" . str_replace("`", "``", $stockCol) . "`";
            $colCost = "`" . str_replace("`", "``", $costCol) . "`";

            // PHP Calculation for safety
            $sql = "SELECT $colStock as stk, $colCost as cst FROM $tableName";
            $query = $this->db->query($sql);

            $totalValuation = 0.0;
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $s = $this->parseCurrency($row['stk']);
                $c = $this->parseCurrency($row['cst']);
                $totalValuation += ($s * $c);
            }

            return $totalValuation;

        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get Chart Data (Daily totals for last 30 days)
     */
    public function getChartData($userId, $inventoryId = null): array
    {
        try {
            // SALES CHART DATA
            // Using 'sales' table, 'total_amount', 'sale_date'
            $stmt = $this->db->prepare("
                SELECT DATE_FORMAT(sale_date, '%Y-%m-%d') as date, total_amount as total 
                FROM sales 
                WHERE user_id = :user 
                  AND sale_date >= DATE(NOW()) - INTERVAL 30 DAY
                ORDER BY sale_date ASC
            ");
            $stmt->execute([':user' => $userId]);
            $rawSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $salesMap = [];
            foreach ($rawSales as $r) {
                $d = $r['date'];
                $val = $this->parseCurrency($r['total']);
                if (!isset($salesMap[$d])) $salesMap[$d] = 0.0;
                $salesMap[$d] += $val;
            }

            $finalSales = [];
            foreach ($salesMap as $k => $v) {
                $finalSales[] = ['date' => $k, 'total' => $v];
            }

            // PURCHASES CHART DATA
            $stmt = $this->db->prepare("
                SELECT DATE_FORMAT(created_at, '%Y-%m-%d') as date, total 
                FROM purchases 
                WHERE user_id = :user AND created_at >= DATE(NOW()) - INTERVAL 30 DAY
                ORDER BY created_at ASC
            ");
            $stmt->execute([':user' => $userId]);
            $rawPurch = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $purchMap = [];
            foreach ($rawPurch as $r) {
                $d = $r['date'];
                $val = $this->parseCurrency($r['total']);
                if (!isset($purchMap[$d])) $purchMap[$d] = 0.0;
                $purchMap[$d] += $val;
            }

            $finalPurch = [];
            foreach ($purchMap as $k => $v) {
                $finalPurch[] = ['date' => $k, 'total' => $v];
            }

            return ['sales' => $finalSales, 'purchases' => $finalPurch];

        } catch (Exception $e) {
            return ['sales' => [], 'purchases' => []];
        }
    }

    /**
     * Top Selling Products
     * Reads from 'sale_items' (as per your SalesModel structure)
     */
    public function getTopProducts($userId, $inventoryId = null): array
    {
        try {
            // 1. Get Inventory Config to find the 'Price' column
            if ($inventoryId) {
                $stmt = $this->db->prepare("SELECT id, preferences FROM inventories WHERE id = ? AND user_id = ?");
                $stmt->execute([$inventoryId, $userId]);
            } else {
                $stmt = $this->db->prepare("SELECT id, preferences FROM inventories WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$userId]);
            }
            $inv = $stmt->fetch(PDO::FETCH_ASSOC);

            $mappedPriceCol = null;
            $tableName = null;
            $currentInventoryId = $inv['id'] ?? null;

            if ($inv) {
                $stmtTable = $this->db->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
                $stmtTable->execute([$inv['id']]);
                $tableData = $stmtTable->fetch(PDO::FETCH_ASSOC);

                if ($tableData) {
                    $tableName = "`" . str_replace("`", "``", $tableData['table_name']) . "`";
                    $prefs = json_decode($inv['preferences'] ?? '{}', true);
                    $mapping = $prefs['mapping'] ?? [];

                    $rawColName = $mapping['sell_price']
                        ?? $mapping['sale_price']
                        ?? $mapping['price']
                        ?? $mapping['precio_venta']
                        ?? $mapping['precio']
                        ?? null;

                    if ($rawColName) {
                        $mappedPriceCol = "`" . str_replace("`", "``", $rawColName) . "`";
                    }
                }
            }

            // 2. Query 'sale_items'
            // We join with 'sales' to filter by user_id
            if ($mappedPriceCol && $tableName) {
                $sql = "
                    SELECT 
                        si.product_name as name, 
                        SUM(si.quantity) as qty,
                        (SELECT $mappedPriceCol FROM $tableName WHERE id = si.item_id LIMIT 1) as current_price
                    FROM sale_items si
                    JOIN sales s ON si.sale_id = s.id
                    WHERE s.user_id = :user
                    GROUP BY si.product_name, si.item_id
                    ORDER BY qty DESC
                    LIMIT 5
                ";
            } else {
                $sql = "
                    SELECT 
                        si.product_name as name, 
                        SUM(si.quantity) as qty,
                        NULL as current_price
                    FROM sale_items si
                    JOIN sales s ON si.sale_id = s.id
                    WHERE s.user_id = :user
                    GROUP BY si.product_name
                    ORDER BY qty DESC
                    LIMIT 5
                ";
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene la distribución de ingresos por método de pago.
     * Retorna: [{ name: 'Efectivo', total: 1500 }, { name: 'Tarjeta', total: 3000 }]
     */
    public function getPaymentDistribution($userId, $inventoryId = null): array
    {
        try {
            // Unimos sale_payments -> sales -> payment_methods
            // Filtramos por Usuario y (opcionalmente) Inventario
            $sql = "
                SELECT 
                    pm.name as name, 
                    SUM(sp.amount) as total
                FROM sale_payments sp
                JOIN sales s ON sp.sale_id = s.id
                JOIN payment_methods pm ON sp.payment_method_id = pm.id
                WHERE s.user_id = :user
            ";

            $params = [':user' => $userId];

            // Si usamos filtro de inventario
            if ($inventoryId) {
                $sql .= " AND s.inventory_id = :inv";
                $params[':inv'] = $inventoryId;
            }

            $sql .= " GROUP BY pm.name ORDER BY total DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Parseamos los totales para asegurar floats
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $final = [];
            foreach ($results as $row) {
                $final[] = [
                    'name' => $row['name'],
                    'total' => $this->parseCurrency($row['total'])
                ];
            }

            return $final;

        } catch (Exception $e) {
            return [];
        }
    }
}