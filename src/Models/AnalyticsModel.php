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
     * Obtiene el valor del dólar actual.
     * Puedes conectar esto a tu tabla de configuración si quieres.
     */
    private function getDolarValue(): float {
        if (isset($_SESSION['dolar_oficial'])) {
            return (float)$_SESSION['dolar_oficial'];
        }
        return 1200.00; // Valor de respaldo
    }

    /**
     * CORREGIDO: Parseo simple.
     * MySQL ya devuelve los números bien (ej: 1234.56).
     * Solo quitamos basura visual ($ o espacios), NO tocamos los puntos.
     */
    private function parseCurrency($value): float {
        if (empty($value)) return 0.0;

        // Solo quitamos símbolos de moneda o espacios.
        $clean = str_replace(['$', ' '], '', (string)$value);

        return (float)$clean;
    }

    /**
     * 1. Totales Financieros
     */
    public function getFinancialTotals($userId, $inventoryId = null, $startDate = null, $endDate = null) {
        $params = [':user' => $userId];

        $whereSales = "WHERE user_id = :user";
        $wherePurch = "WHERE user_id = :user";

        if ($startDate && $endDate) {
            $whereSales .= " AND sale_date BETWEEN :start AND :end";
            $wherePurch .= " AND created_at BETWEEN :start AND :end";
            $params[':start'] = $startDate;
            $params[':end'] = $endDate;
        }

        try {
            // Ingresos
            $stmt = $this->db->prepare("SELECT total_amount FROM sales $whereSales");
            $stmt->execute($params);
            $salesRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $revenue = 0.0;
            $salesCount = count($salesRows);
            foreach ($salesRows as $row) {
                $revenue += $this->parseCurrency($row['total_amount'] ?? 0);
            }

            // Gastos
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
                'average_ticket' => $avgTicket,
                'sales_count' => $salesCount,
                'purchases_count' => $purchCount
            ];
        } catch (Exception $e) {
            return ['revenue'=>0, 'expenses'=>0, 'balance'=>0, 'average_ticket'=>0, 'sales_count'=>0, 'purchases_count'=>0];
        }
    }

    /**
     * 2. Valor del Inventario (CORREGIDO Y BLINDADO)
     */
    public function getInventoryValuation($userId, $inventoryId = null): float|int
    {
        try {
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
            $colStockSafe = "`" . str_replace("`", "``", $stockCol) . "`";
            $colCostSafe = "`" . str_replace("`", "``", $costCol) . "`";

            // TRUCO: Seleccionamos TODO (*) para buscar la moneda,
            // PERO TAMBIÉN seleccionamos las columnas específicas con alias (stk, cst) para asegurar el valor.
            $sql = "SELECT *, $colStockSafe as stk, $colCostSafe as cst FROM $tableName";
            $query = $this->db->query($sql);

            $totalValuation = 0.0;
            $dolar = $this->getDolarValue();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                // Usamos los alias seguros que creamos en la consulta
                $s = $this->parseCurrency($row['stk']);
                $c = $this->parseCurrency($row['cst']);

                // Detectar Moneda: Buscamos '_meta_currency_buy'
                $currency = 'ARS';
                if (!empty($row['_meta_currency_buy'])) {
                    $currency = strtoupper($row['_meta_currency_buy']);
                }

                // Conversión: Si es Dólar, multiplicamos
                if (in_array($currency, ['USD', 'USDT', 'DOLAR'])) {
                    $c = $c * $dolar;
                }

                $totalValuation += ($s * $c);
            }
            return $totalValuation;
        } catch (Exception $e) { return 0; }
    }

    /**
     * 3. Gráficos
     */
    public function getChartData($userId, $inventoryId = null): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT DATE_FORMAT(sale_date, '%Y-%m-%d') as date, total_amount as total 
                FROM sales 
                WHERE user_id = :user AND sale_date >= DATE(NOW()) - INTERVAL 30 DAY
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
            foreach ($salesMap as $k => $v) { $finalSales[] = ['date' => $k, 'total' => $v]; }

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
            foreach ($purchMap as $k => $v) { $finalPurch[] = ['date' => $k, 'total' => $v]; }

            return ['sales' => $finalSales, 'purchases' => $finalPurch];
        } catch (Exception $e) { return ['sales'=>[], 'purchases'=>[]]; }
    }

    /**
     * 4. Top Productos
     */
    public function getTopProducts($userId, $inventoryId = null): array
    {
        try {
            $sql = "
                SELECT 
                    sd.product_name as name, 
                    SUM(sd.quantity) as qty,
                    SUM(sd.subtotal) as total
                FROM sale_details sd
                JOIN sales s ON sd.sale_id = s.id
                WHERE s.user_id = :user
                GROUP BY sd.product_name, sd.product_id
                ORDER BY qty DESC
                LIMIT 5
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { return []; }
    }

    /**
     * 5. Top Clientes
     */
    public function getTopClients($userId): array
    {
        try {
            $sql = "
                SELECT 
                    c.full_name as name, 
                    COUNT(s.id) as sales_count,
                    SUM(s.total_amount) as total
                FROM sales s
                JOIN customers c ON s.customer_id = c.id
                WHERE s.user_id = :user
                GROUP BY s.customer_id, c.full_name
                ORDER BY total DESC
                LIMIT 5
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user' => $userId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as &$row) { $row['total'] = $this->parseCurrency($row['total']); }
            return $results;
        } catch (Exception $e) { return []; }
    }

    /**
     * 6. Horarios Pico
     */
    public function getPeakHours($userId): array
    {
        try {
            $sql = "
                SELECT HOUR(sale_date) as hour, COUNT(*) as count
                FROM sales 
                WHERE user_id = :user AND sale_date >= DATE(NOW()) - INTERVAL 30 DAY
                GROUP BY hour ORDER BY hour ASC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { return []; }
    }

    /**
     * 7. Top Vendedores
     */
    public function getTopSellers($userId): array
    {
        try {
            $sql = "
                SELECT 
                    COALESCE(e.full_name, 'Venta Directa') as name, 
                    COUNT(s.id) as sales_count,
                    SUM(s.total_amount) as total
                FROM sales s
                LEFT JOIN employees e ON s.seller_id = e.id
                WHERE s.user_id = :user
                GROUP BY s.seller_id, e.full_name
                ORDER BY total DESC
                LIMIT 5
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user' => $userId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as &$row) { $row['total'] = $this->parseCurrency($row['total']); }
            return $results;
        } catch (Exception $e) { return []; }
    }

    /**
     * 8. Distribución Monedas
     */
    public function getCurrencyDistribution($userId, $inventoryId = null): array
    {
        try {
            $sql = "
                SELECT 
                    pm.currency as name, 
                    SUM(s.total_amount) as total
                FROM sales s
                JOIN payment_methods pm ON s.payment_method_id = pm.id
                WHERE s.user_id = :user
                GROUP BY pm.currency 
                ORDER BY total DESC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user' => $userId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $final = [];
            foreach ($results as $row) {
                $final[] = [
                    'name' => $row['name'] ?: 'Desconocida',
                    'total' => $this->parseCurrency($row['total'])
                ];
            }
            return $final;
        } catch (Exception $e) { return []; }
    }

    /**
     * 9. Distribución Pagos
     */
    public function getPaymentDistribution($userId, $inventoryId = null): array
    {
        try {
            $sql = "
                SELECT 
                    pm.name as name, 
                    SUM(s.total_amount) as total
                FROM sales s
                JOIN payment_methods pm ON s.payment_method_id = pm.id
                WHERE s.user_id = :user
                GROUP BY pm.name 
                ORDER BY total DESC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user' => $userId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $final = [];
            foreach ($results as $row) {
                $final[] = [
                    'name' => $row['name'],
                    'total' => $this->parseCurrency($row['total'])
                ];
            }
            return $final;
        } catch (Exception $e) { return []; }
    }
}