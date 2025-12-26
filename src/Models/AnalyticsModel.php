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
     * Obtiene los totales generales (Ventas, Compras, Balance).
     */
    /**
     * Obtiene los totales generales.
     */
    public function getFinancialTotals($userId, $startDate = null, $endDate = null) {
        $dateFilterVentas = "";
        $dateFilterPurch = "";
        $params = [':user' => $userId];

        if ($startDate && $endDate) {
            $dateFilterVentas = "AND fecha_hora BETWEEN :start AND :end";
            $dateFilterPurch = "AND created_at BETWEEN :start AND :end";
            $params[':start'] = $startDate;
            $params[':end'] = $endDate;
        }

        try {
            // 1. Total Ventas
            $sqlSales = "SELECT SUM(total) as total_sales, COUNT(*) as count_sales 
                         FROM ventas WHERE id_usuario = :user $dateFilterVentas";
            $stmt = $this->db->prepare($sqlSales);
            $stmt->execute($params);
            $salesData = $stmt->fetch(PDO::FETCH_ASSOC);

            // 2. Total Compras (INCLUYENDO GASTOS RÁPIDOS)
            // Esta consulta es simple: Suma todo de la tabla 'purchases' para este usuario.
            // Al no hacer JOINs, incluye gastos con provider_id NULL.
            $sqlPurch = "SELECT SUM(total) as total_purchases, COUNT(*) as count_purchases 
                         FROM purchases WHERE user_id = :user $dateFilterPurch";
            $stmt = $this->db->prepare($sqlPurch);
            $stmt->execute($params);
            $purchData = $stmt->fetch(PDO::FETCH_ASSOC);

            $revenue = (float)($salesData['total_sales'] ?? 0);
            $expenses = (float)($purchData['total_purchases'] ?? 0);

            return [
                'revenue' => $revenue,
                'expenses' => $expenses,
                'balance' => $revenue - $expenses,
                'sales_count' => (int)$salesData['count_sales'],
                'purchases_count' => (int)$purchData['count_purchases']
            ];

        } catch (Exception $e) {
            return ['revenue' => 0, 'expenses' => 0, 'balance' => 0, 'sales_count' => 0, 'purchases_count' => 0];
        }
    }

    /**
     * Calcula cuánto vale el inventario actual.
     */
    public function getInventoryValuation($userId): float|int
    {
        try {
            // 1. Buscar inventario activo
            $stmt = $this->db->prepare("SELECT id, preferences FROM inventories WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$userId]);
            $inv = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$inv) return 0;

            $prefs = json_decode($inv['preferences'] ?? '{}', true);
            $stockCol = $prefs['mapping']['stock'] ?? null;
            $costCol = $prefs['mapping']['buy_price'] ?? null;

            if (!$stockCol || !$costCol) return 0;

            // 2. Buscar tabla física
            $stmtTable = $this->db->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
            $stmtTable->execute([$inv['id']]);
            $tableRow = $stmtTable->fetch(PDO::FETCH_ASSOC);

            if (!$tableRow) return 0;

            $safeTable = "`" . str_replace("`", "``", $tableRow['table_name']) . "`";
            $safeStock = "`" . str_replace("`", "``", $stockCol) . "`";
            $safeCost = "`" . str_replace("`", "``", $costCol) . "`";

            // 3. Calcular
            $sql = "SELECT SUM(CAST($safeStock AS DECIMAL(10,2)) * CAST($safeCost AS DECIMAL(10,2))) as total_value FROM $safeTable";
            $query = $this->db->query($sql);
            $result = $query->fetch(PDO::FETCH_ASSOC);

            return (float)($result['total_value'] ?? 0);

        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Datos para el gráfico (Últimos 30 días).
     */
    public function getChartData($userId): array
    {
        try {
            // Ventas (Tabla 'ventas')
            $stmt = $this->db->prepare("
                SELECT DATE(fecha_hora) as date, SUM(total) as total 
                FROM ventas 
                WHERE id_usuario = :user AND fecha_hora >= DATE(NOW()) - INTERVAL 30 DAY
                GROUP BY DATE(fecha_hora)
                ORDER BY date ASC
            ");
            $stmt->execute([':user' => $userId]);
            $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Compras (Tabla 'purchases')
            $stmt = $this->db->prepare("
                SELECT DATE(created_at) as date, SUM(total) as total 
                FROM purchases 
                WHERE user_id = :user AND created_at >= DATE(NOW()) - INTERVAL 30 DAY
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([':user' => $userId]);
            $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['sales' => $sales, 'purchases' => $purchases];

        } catch (Exception $e) {
            return ['sales' => [], 'purchases' => []];
        }
    }

    /**
     * Top Productos (Tabla 'detalle_venta').
     */
    public function getTopProducts($userId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT nombre_producto as name, SUM(cantidad) as qty, SUM(subtotal) as total
                FROM detalle_venta dv
                JOIN ventas v ON dv.id_venta = v.id
                WHERE v.id_usuario = :user
                GROUP BY nombre_producto
                ORDER BY qty DESC
                LIMIT 5
            ");
            $stmt->execute([':user' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { return []; }
    }
}