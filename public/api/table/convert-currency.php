<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_time_limit(300);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/Services/ExchangeService.php';

use App\core\Database;
use App\Services\ExchangeService;

try {
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'Sesión expirada']); exit; }

    $input = json_decode(file_get_contents('php://input'), true);

    $columnType = $input['column_type'] ?? null;
    $targetCurrency = $input['target_currency'] ?? null;
    $inventoryId = $input['inventory_id'] ?? $_SESSION['active_inventory_id'] ?? null;

    if (!$inventoryId || !$columnType || !$targetCurrency) throw new Exception("Faltan datos.");

    $db = Database::getInstance();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Obtener Tabla y Preferencias
    $stmtInv = $db->prepare("SELECT t.table_name, i.preferences FROM inventories i JOIN user_tables t ON i.id = t.inventory_id WHERE i.id = :invId AND i.user_id = :uid");
    $stmtInv->execute([':invId' => $inventoryId, ':uid' => $user['id']]);
    $invData = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if (!$invData) throw new Exception("Inventario no encontrado.");

    $tableName = "`" . str_replace("`", "``", $invData['table_name']) . "`";

    // 2. Resolver Columna
    $prefs = json_decode($invData['preferences'] ?? '{}', true);
    $mapping = $prefs['mapping'] ?? [];
    $realColumnName = $mapping[$columnType] ?? $columnType;

    $cols = $db->query("SHOW COLUMNS FROM $tableName")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array($realColumnName, $cols)) throw new Exception("La columna '$realColumnName' no existe.");

    $safeCol = "`" . str_replace("`", "``", $realColumnName) . "`";

    // ---------------------------------------------------------
    // SOLUCIÓN RAÍZ 1: ESTRUCTURA (Precisión)
    // ---------------------------------------------------------
    // Antes de convertir, el sistema GARANTIZA que la columna soporte 6 decimales.
    // Esto aplica hoy y para cualquier tabla futura que use esta función.
    try {
        $db->exec("ALTER TABLE $tableName MODIFY COLUMN $safeCol DECIMAL(20, 6) DEFAULT '0.000000'");
    } catch (Exception $e) {
        // Si hay basura, limpiamos y reintentamos. Es la única forma de garantizar la integridad matemática.
        $db->exec("UPDATE $tableName SET $safeCol = '0' WHERE $safeCol NOT REGEXP '^[0-9]+(\.[0-9]+)?$'");
        $db->exec("ALTER TABLE $tableName MODIFY COLUMN $safeCol DECIMAL(20, 6) DEFAULT '0.000000'");
    }

    // 3. Preparar Metadata
    $metaCol = ($columnType === 'sale_price') ? '_meta_currency_sale' : '_meta_currency_buy';
    if (!in_array($metaCol, $cols)) {
        $db->exec("ALTER TABLE $tableName ADD COLUMN $metaCol VARCHAR(10) DEFAULT 'ARS'");
    }

    // ---------------------------------------------------------
    // SOLUCIÓN RAÍZ 2: MATEMÁTICA (Eliminar Brecha)
    // ---------------------------------------------------------
    // Obtenemos la tasa real desde el Backend directamente
    $service = new ExchangeService();
    $rates = $service->getRates();

    // Calculamos el PROMEDIO. Esto hace que la operación sea simétrica.
    // (Compra + Venta) / 2
    $buy = floatval($rates['buy']);
    $sell = floatval($rates['sell']);

    if ($buy > 0 && $sell > 0) {
        $rate = ($buy + $sell) / 2;
    } else {
        $rate = $sell > 0 ? $sell : 1200; // Fallback
    }

    // 4. TRANSACCIÓN
    $db->beginTransaction();

    if ($targetCurrency === 'USD') {
        // ARS -> USD (División por Promedio)
        $sql = "UPDATE $tableName 
                SET $safeCol = (CAST($safeCol AS DECIMAL(65,30)) / :rate), $metaCol = 'USD' 
                WHERE $metaCol != 'USD' AND $safeCol > 0";
    } else {
        // USD -> ARS (Multiplicación por Promedio)
        $sql = "UPDATE $tableName 
                SET $safeCol = (CAST($safeCol AS DECIMAL(65,30)) * :rate), $metaCol = 'ARS' 
                WHERE $metaCol = 'USD' AND $safeCol > 0";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute([':rate' => $rate]);
    $rowsAffected = $stmt->rowCount();

    $db->exec("UPDATE $tableName SET $metaCol = '$targetCurrency' WHERE $metaCol != '$targetCurrency'");

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => "Conversión Simétrica Realizada (Tasa Promedio: $$rate). Filas: $rowsAffected",
        'rate_used' => $rate
    ]);

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}