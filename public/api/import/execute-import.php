<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';

use App\core\Database;

function jsonFail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

try {
    $user = getCurrentUser();
    if (!$user) jsonFail("Sesión expirada", 401);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonFail("Método no permitido", 405);
    }

    $plan = $_SESSION['import_plan'] ?? null;
    if (!$plan || !is_array($plan)) {
        jsonFail("No hay una importación preparada. Volvé a preparar el CSV.");
    }

    $inventoryId  = $plan['inventory_id'] ?? null;
    $rawTableName = $plan['table_name'] ?? null;
    $cols         = $plan['map'] ?? [];
    $rows         = $plan['rows'] ?? [];
    $overwrite    = (bool)($plan['overwrite'] ?? false);

    if (!$inventoryId || !$rawTableName) jsonFail("Plan inválido (falta inventario/tabla).");
    if (empty($cols) || empty($rows)) jsonFail("Plan inválido (sin columnas o filas).");

    $db = Database::getInstance();

    $stmt = $db->prepare("
        SELECT t.table_name
        FROM user_tables t
        JOIN inventories i ON t.inventory_id = i.id
        WHERE i.id = :invId AND i.user_id = :uid
        LIMIT 1
    ");
    $stmt->execute([':invId' => $inventoryId, ':uid' => $user['id']]);
    $checkTable = $stmt->fetchColumn();
    if (!$checkTable || $checkTable !== $rawTableName) {
        jsonFail("Tabla no encontrada o acceso denegado.", 403);
    }

    $tableName = "`" . str_replace("`", "``", $rawTableName) . "`";

    $safeCols = array_map(fn($c) => "`" . str_replace("`", "``", $c) . "`", $cols);
    $placeholders = "(" . implode(", ", array_fill(0, count($cols), "?")) . ")";
    $sqlInsert = "INSERT INTO $tableName (" . implode(", ", $safeCols) . ") VALUES $placeholders";
    $stmtIns = $db->prepare($sqlInsert);

    $inserted = 0;
    $txStarted = false;

    try {
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $txStarted = true;
        }

        if ($overwrite) {
            // Fetch old rows to preserve unmapped columns
            $stmtOld = $db->query("SELECT * FROM $tableName ORDER BY id ASC");
            $oldRows = $stmtOld->fetchAll(PDO::FETCH_ASSOC);
            
            // Get all columns in the table
            $stmtCols = $db->query("DESCRIBE $tableName");
            $allTableCols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
            
            $db->exec("DELETE FROM $tableName");
            $db->exec("ALTER TABLE $tableName AUTO_INCREMENT = 1");
            
            // We insert all columns except id, created_at, updated_at
            $colsToInsert = array_filter($allTableCols, fn($c) => !in_array(strtolower($c), ['id', 'created_at', 'updated_at']));
            $safeColsToInsert = array_map(fn($c) => "`" . str_replace("`", "``", $c) . "`", $colsToInsert);
            $placeholders = "(" . implode(", ", array_fill(0, count($colsToInsert), "?")) . ")";
            $sqlInsertAll = "INSERT INTO $tableName (" . implode(", ", $safeColsToInsert) . ") VALUES $placeholders";
            $stmtInsAll = $db->prepare($sqlInsertAll);
            
            foreach ($rows as $i => $r) {
                $values = [];
                foreach ($colsToInsert as $c) {
                    if (in_array($c, $cols)) {
                        $values[] = $r[$c] ?? null;
                    } else {
                        $values[] = $oldRows[$i][$c] ?? null;
                    }
                }
                $stmtInsAll->execute($values);
                $inserted++;
            }
        } else {
            foreach ($rows as $r) {
                $values = [];
                foreach ($cols as $c) {
                    $values[] = $r[$c] ?? null;
                }
                $stmtIns->execute($values);
                $inserted++;
            }
        }

        if ($txStarted && $db->inTransaction()) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($txStarted && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    // Auditoría
    try {
        require_once __DIR__ . '/../../../src/helpers/ActivityLogger.php';
        \App\helpers\ActivityLogger::log(
            'Dashboard',
            'import',
            'product',
            null,
            $overwrite ? 'Importación masiva (Sobrescribir)' : 'Importación masiva',
            "Filas importadas: {$inserted}"
        );
    } catch (\Throwable $logErr) {
        error_log('ActivityLogger error en execute-import: ' . $logErr->getMessage());
    }

    unset($_SESSION['import_plan']);

    echo json_encode([
        'success' => true,
        'message' => $overwrite
            ? "Importación completada. Se sobrescribieron los datos y se importaron $inserted filas."
            : "Importación completada. Se importaron $inserted filas.",
        'inserted' => $inserted,
        'overwrite' => $overwrite
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}