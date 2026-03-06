<?php
// public/api/import/prepare-csv.php
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

function normKey($s) {
    $s = (string)$s;
    $s = trim($s);
    $s = mb_strtolower($s, 'UTF-8');

    // sacar acentos
    if (class_exists('Transliterator')) {
        $tr = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        if ($tr) $s = $tr->transliterate($s);
    } else {
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $s = mb_strtolower((string)$s, 'UTF-8');
    }

    // dejar solo a-z0-9
    $s = preg_replace('/[^a-z0-9]+/u', '', $s);
    return $s ?? '';
}

try {
    $user = getCurrentUser();
    if (!$user) jsonFail("Sesión expirada", 401);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonFail("Método no permitido", 405);
    }

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        jsonFail("No se recibió el archivo CSV.");
    }

    $inventoryId = $_POST['inventory_id'] ?? $_SESSION['active_inventory_id'] ?? null;
    if (!$inventoryId) jsonFail("No se identificó el inventario.");

    $mappingRaw = $_POST['mapping'] ?? '';
    if (!$mappingRaw) jsonFail("Mapping vacío.");

    $mapping = json_decode($mappingRaw, true);
    if (!is_array($mapping) || empty($mapping)) {
        jsonFail("Mapping inválido.");
    }

    $delimiter = $_POST['delimiter'] ?? ',';
    $overwrite = ($_POST['overwrite'] ?? '0') === '1';

    $db = Database::getInstance();

    // 1) validar tabla del inventario y ownership
    $stmtInv = $db->prepare("
        SELECT t.table_name
        FROM user_tables t
        JOIN inventories i ON t.inventory_id = i.id
        WHERE i.id = :invId AND i.user_id = :uid
        LIMIT 1
    ");
    $stmtInv->execute([':invId' => $inventoryId, ':uid' => $user['id']]);
    $rawTableName = $stmtInv->fetchColumn();
    if (!$rawTableName) jsonFail("Inventario no encontrado o acceso denegado.", 403);

    $tableName = "`" . str_replace("`", "``", $rawTableName) . "`";

    // 2) columnas válidas de la tabla
    $validCols = $db->query("SHOW COLUMNS FROM $tableName")->fetchAll(PDO::FETCH_COLUMN);
    $validColsNorm = [];
    foreach ($validCols as $c) $validColsNorm[normKey($c)] = $c;

    // 3) leer CSV
    $tmpPath = $_FILES['csv_file']['tmp_name'];
    $fh = fopen($tmpPath, 'r');
    if (!$fh) jsonFail("No se pudo leer el archivo CSV.");

    // headers
    $csvHeaders = fgetcsv($fh, 0, $delimiter);
    if (!$csvHeaders || !is_array($csvHeaders)) {
        fclose($fh);
        jsonFail("CSV inválido: no se detectaron cabeceras.");
    }

    // mapa header->index (exacto + normalizado)
    $headerIndexExact = [];
    $headerIndexNorm  = [];
    foreach ($csvHeaders as $i => $h) {
        $hStr = trim((string)$h);
        $headerIndexExact[$hStr] = $i;

        $nk = normKey($hStr);
        if ($nk !== '' && !isset($headerIndexNorm[$nk])) $headerIndexNorm[$nk] = $i;
    }

    // 4) resolver mapeo final (solo columnas existentes en la tabla)
    // mapping: [sysCol => csvHeaderName OR csvHeaderNormalized]
    $finalMap = [];     // [sysColReal => csvIndex]
    $mappedCols = [];   // lista de columnas a insertar

    foreach ($mapping as $sysCol => $csvCol) {
        $sysCol = trim((string)$sysCol);
        $csvCol = trim((string)$csvCol);

        if ($sysCol === '' || $csvCol === '') continue;
        if (in_array(mb_strtolower($sysCol, 'UTF-8'), ['id','created_at','updated_at'], true)) continue;

        // validar columna en DB (por exacto o por norm)
        $sysColReal = null;
        if (in_array($sysCol, $validCols, true)) {
            $sysColReal = $sysCol;
        } else {
            $nk = normKey($sysCol);
            if ($nk && isset($validColsNorm[$nk])) $sysColReal = $validColsNorm[$nk];
        }
        if (!$sysColReal) continue;

        // encontrar índice del header del CSV (exacto o por norm)
        $csvIndex = null;
        if (isset($headerIndexExact[$csvCol])) {
            $csvIndex = $headerIndexExact[$csvCol];
        } else {
            $nkCsv = normKey($csvCol);
            if ($nkCsv && isset($headerIndexNorm[$nkCsv])) $csvIndex = $headerIndexNorm[$nkCsv];
        }

        if ($csvIndex === null) continue;

        $finalMap[$sysColReal] = $csvIndex;
    }

    if (empty($finalMap)) {
        fclose($fh);
        jsonFail("No hay columnas mapeadas válidas para esta tabla.");
    }

    $mappedCols = array_keys($finalMap);

    // 5) leer filas y construir rows
    $rows = [];
    while (($line = fgetcsv($fh, 0, $delimiter)) !== false) {
        // ignorar filas vacías completas
        $allEmpty = true;
        foreach ($line as $v) {
            if (trim((string)$v) !== '') { $allEmpty = false; break; }
        }
        if ($allEmpty) continue;

        $row = [];
        foreach ($finalMap as $colName => $idx) {
            $row[$colName] = isset($line[$idx]) ? trim((string)$line[$idx]) : null;
        }
        $rows[] = $row;
    }
    fclose($fh);

    if (empty($rows)) {
        jsonFail("El CSV no contiene filas para importar.");
    }

    // 6) guardar plan en sesión para execute-import
    $_SESSION['import_plan'] = [
        'inventory_id' => $inventoryId,
        'table_name'   => $rawTableName,
        'map'          => $mappedCols,
        'rows'         => $rows,
        'overwrite'    => $overwrite,
        'delimiter'    => $delimiter,
        'prepared_at'  => time(),
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Datos preparados correctamente.',
        'mapped_columns' => $mappedCols,
        'rows_count' => count($rows),
        'overwrite' => $overwrite
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}