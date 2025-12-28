<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/Services/ExchangeService.php';

use App\core\Database;
use App\Services\ExchangeService;

try {
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    $input = json_decode(file_get_contents('php://input'), true);
    $targetCurrency = $input['target_currency'] ?? 'ARS'; // 'USD' o 'ARS'
    $columnType = $input['column_type'] ?? 'sale_price';  // 'sale_price' o 'buy_price'

    $db = Database::getInstance();

    // 1. Obtener Tabla y Preferencias
    $stmt = $db->prepare("SELECT i.id, i.preferences, t.table_name FROM inventories i JOIN user_tables t ON i.id = t.inventory_id WHERE i.user_id = ? ORDER BY i.created_at DESC LIMIT 1");
    $stmt->execute([$user['id']]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) throw new Exception("No hay inventario activo.");

    $prefs = json_decode($data['preferences'], true);
    // Buscamos el nombre real de la columna en la BD (ej: 'col_2')
    $colName = $prefs['mapping'][$columnType] ?? null;

    if (!$colName) throw new Exception("Esta columna no está identificada en la configuración.");

    $tableName = "`" . str_replace("`", "``", $data['table_name']) . "`";
    $safeColName = "`" . str_replace("`", "``", $colName) . "`";

    // Definimos cuál es la columna META a modificar
    $metaColName = ($columnType === 'sale_price') ? '_meta_currency_sale' : '_meta_currency_buy';

    // 2. Asegurar que existe la columna META (si no, la creamos)
    try {
        $db->exec("ALTER TABLE $tableName ADD COLUMN $metaColName VARCHAR(5) DEFAULT 'ARS'");
    } catch (Exception $e) { /* Ya existe */ }

    // 3. Obtener Cotización
    $service = new ExchangeService();
    $rates = $service->getRates();
    // $rates['buy'] = Compra (te pagan pesos por dolares) -> Usar para USD a ARS
    // $rates['sell'] = Venta (te cuesta pesos comprar dolares) -> Usar para ARS a USD

    // 4. Leer datos actuales para convertir uno por uno
    $rows = $db->query("SELECT id, $safeColName, $metaColName FROM $tableName")->fetchAll(PDO::FETCH_ASSOC);

    $db->beginTransaction();
    $stmtUpdate = $db->prepare("UPDATE $tableName SET $safeColName = :val, $metaColName = :curr WHERE id = :id");

    $count = 0;

    foreach ($rows as $row) {
        $currentVal = (float)$row[$colName];
        $currentCurr = $row[$metaColName] ?: 'ARS'; // Si es null es ARS

        // Si ya es la moneda destino, no hacemos nada
        if ($currentCurr === $targetCurrency) continue;

        $newVal = $currentVal;

        // LÓGICA DE CONVERSIÓN
        if ($currentCurr === 'ARS' && $targetCurrency === 'USD') {
            // Pasamos de Pesos a Dólares (Dividimos por Venta)
            if ($rates['sell'] > 0) $newVal = $currentVal / $rates['sell'];
        }
        elseif ($currentCurr === 'USD' && $targetCurrency === 'ARS') {
            // Pasamos de Dólares a Pesos (Multiplicamos por Compra, o Venta según criterio contable. Usaremos Venta para reposición segura)
            $newVal = $currentVal * $rates['sell'];
        }

        $stmtUpdate->execute([
            ':val' => round($newVal, 2),
            ':curr' => $targetCurrency,
            ':id' => $row['id']
        ]);
        $count++;
    }

    $db->commit();
    echo json_encode(['success' => true, 'message' => "Se actualizaron $count filas a $targetCurrency", 'rate_used' => $rates['sell']]);

} catch (Throwable $e) {
    if (isset($db)) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}