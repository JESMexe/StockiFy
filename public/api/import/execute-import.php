<?php
// public/api/import/execute-import.php
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

    // Revalidar ownership
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

    // Preparar INSERT
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
            $db->exec("DELETE FROM $tableName");
            $db->exec("ALTER TABLE $tableName AUTO_INCREMENT = 1");
        }

        foreach ($rows as $r) {
            $values = [];
            foreach ($cols as $c) {
                $values[] = $r[$c] ?? null;
            }
            $stmtIns->execute($values);
            $inserted++;
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