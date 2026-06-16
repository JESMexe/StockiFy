<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/Models/ActivityLogModel.php';

use App\core\Database;
use App\Models\ActivityLogModel;

try {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['id']) || !isset($input['data'])) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }

    $id = (int)$input['id'];
    $data = is_array($input['data']) ? $input['data'] : [];
    $meta = (isset($input['meta']) && is_array($input['meta'])) ? $input['meta'] : [];

    $inventoryId = $input['inventory_id'] ?? ($_SESSION['active_inventory_id'] ?? null);
    if (!$inventoryId) {
        error_log("UpdateRow Fail. Input: " . print_r($input, true) . " Session: " . print_r($_SESSION, true));
        echo json_encode(['success' => false, 'message' => 'Error: No se identifica el inventario activo.']);
        exit;
    }
    $inventoryId = (int)$inventoryId;

    $db = Database::getInstance();

    // RBAC: verificar acceso al inventario
    $myRole = getInventoryRole($user['id'], $inventoryId);
    if (!$myRole) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado a este inventario.']);
        exit;
    }

    $stmtTable = $db->prepare("
        SELECT t.table_name
        FROM user_tables t
        WHERE t.inventory_id = :invId
        LIMIT 1
    ");
    $stmtTable->execute([':invId' => $inventoryId]);
    $rawTableName = $stmtTable->fetchColumn();

    if (!$rawTableName) {
        throw new Exception("Tabla no encontrada o acceso denegado.");
    }

    $tableName = "`" . str_replace("`", "``", $rawTableName) . "`";

    $cleanData = [];
    foreach ($data as $k => $v) {
        if (!is_string($k)) continue;
        $kTrim = trim($k);
        if ($kTrim === '' || $kTrim === 'undefined' || $kTrim === 'null') continue;
        $cleanData[$kTrim] = $v;
    }

    $allData = array_merge($cleanData, $meta);
    if (empty($allData)) {
        echo json_encode(['success' => true, 'message' => 'Sin cambios']);
        exit;
    }

    $setClause = [];
    $params = [':id' => $id];

    $i = 0;
    foreach ($allData as $col => $val) {
        if (!is_string($col)) continue;
        $colLower = strtolower(trim($col));
        if ($colLower === 'id') continue;
        $col = trim($col);
        if ($col === '' || $col === 'undefined' || $col === 'null') continue;

        $safeCol = "`" . str_replace("`", "``", $col) . "`";

        if (strpos($col, '_meta_') === 0) {
            try {
                $db->exec("ALTER TABLE $tableName ADD COLUMN $safeCol VARCHAR(10) DEFAULT 'ARS'");
            } catch (Throwable $e) {
            }
        }

        $ph = ":v{$i}";
        $setClause[] = "$safeCol = $ph";
        $params[$ph] = $val;

        $i++;
    }

    if (empty($setClause)) {
        echo json_encode(['success' => true, 'message' => 'Sin cambios']);
        exit;
    }

    // Obtener preferencias del inventario para saber cuál es la columna de stock
    $stmtPref = $db->prepare("SELECT preferences FROM inventories WHERE id = :id");
    $stmtPref->execute([':id' => $inventoryId]);
    $invRow = $stmtPref->fetch(PDO::FETCH_ASSOC);
    $prefs = !empty($invRow['preferences']) ? json_decode($invRow['preferences'], true) : [];
    $stockColName = $prefs['mapping']['stock'] ?? 'stock';
    $hasStockUpdate = array_key_exists($stockColName, $allData);

    $oldStock = null;
    $oldMinStock = null;
    if ($hasStockUpdate) {
        $safeStockCol = "`" . str_replace("`", "``", $stockColName) . "`";
        $stmtBefore = $db->prepare("SELECT {$safeStockCol} as stock, min_stock FROM $tableName WHERE id = :id");
        $stmtBefore->execute([':id' => $id]);
        $rowBefore = $stmtBefore->fetch(PDO::FETCH_ASSOC);
        if ($rowBefore) {
            $oldStock = $rowBefore['stock'] !== null ? (float)$rowBefore['stock'] : null;
            $oldMinStock = $rowBefore['min_stock'] !== null ? (float)$rowBefore['min_stock'] : 0.0;
        }
    }

    $sql = "UPDATE $tableName SET " . implode(', ', $setClause) . " WHERE `id` = :id";
    $stmt = $db->prepare($sql);

    if ($stmt->execute($params)) {
        $stmtGet = $db->prepare("SELECT * FROM $tableName WHERE `id` = :id");
        $stmtGet->execute([':id' => $id]);
        $updatedItem = $stmtGet->fetch(PDO::FETCH_ASSOC);

        // Si se actualizó el stock, evaluar si se deben disparar alertas
        if ($hasStockUpdate && $oldStock !== null && $updatedItem) {
            $newStock = (float)($updatedItem[$stockColName] ?? 0);
            $minStock = (float)($updatedItem['min_stock'] ?? $oldMinStock ?? 0);
            $prodName = $updatedItem['nombre'] ?? $updatedItem['name'] ?? $updatedItem['producto'] ?? $updatedItem['description'] ?? 'Producto #' . $id;

            $shouldTriggerLowStock  = ($oldStock > $minStock && $newStock <= $minStock && $newStock > 0);
            $shouldTriggerOutOfStock = ($oldStock > 0 && $newStock <= 0);

            if ($shouldTriggerLowStock || $shouldTriggerOutOfStock) {
                // Obtener datos del Owner de este inventario
                $stmtOwner = $db->prepare("
                    SELECT u.email, u.full_name, u.cell, i.name as inv_name
                    FROM users u
                    JOIN inventories i ON u.id = i.user_id
                    WHERE i.id = ?
                ");
                $stmtOwner->execute([$inventoryId]);
                $u = $stmtOwner->fetch(PDO::FETCH_ASSOC);

                if ($u) {
                    $toEmail = $u['email'];
                    $toCell = $u['cell'];
                    $userName = $u['full_name'] ?? 'Socio';
                    $invName = $u['inv_name'] ?? 'Principal';

                    if ($shouldTriggerLowStock) {
                        if (!empty($toEmail)) {
                            require_once __DIR__ . '/../../../src/Services/MailService.php';
                            $mailSvc = new \App\Services\MailService();
                            $mailSvc->sendLowStockAlert($toEmail, $userName, $prodName, $newStock, $minStock);
                        }
                        if (!empty($toCell)) {
                            require_once __DIR__ . '/../../../src/Services/WhatsappService.php';
                            $waSvc = new \App\Services\WhatsappService();
                            $waSvc->sendLowStockAlert($toCell, $userName, $prodName, $newStock, $minStock, $invName, (string)$id);
                        }
                    }

                    if ($shouldTriggerOutOfStock) {
                        if (!empty($toEmail)) {
                            require_once __DIR__ . '/../../../src/Services/MailService.php';
                            $mailSvc = new \App\Services\MailService();
                            $mailSvc->sendOutOfStockAlert($toEmail, $userName, $prodName, $invName);
                        }
                        if (!empty($toCell)) {
                            require_once __DIR__ . '/../../../src/Services/WhatsappService.php';
                            $waSvc = new \App\Services\WhatsappService();
                            $waSvc->sendOutOfStockAlert($toCell, $userName, $prodName, $invName, (string)$id);
                        }
                    }
                }
            }
        }

        // Auditoría
        try {
            $prodName = $updatedItem['nombre'] ?? $updatedItem['name'] ?? $updatedItem['producto'] ?? $updatedItem['description'] ?? '';
            $extraDesc = $prodName ? "Nombre: {$prodName}" : "";

            require_once __DIR__ . '/../../../src/helpers/ActivityLogger.php';
            \App\helpers\ActivityLogger::log(
                'Dashboard',
                'update',
                'product',
                (string)$id,
                'Producto editado (ID: ' . $id . ')',
                $extraDesc
            );
        } catch (\Throwable $logErr) {
            error_log('ActivityLog error en update-row: ' . $logErr->getMessage());
        }

        echo json_encode(['success' => true, 'updatedItem' => $updatedItem]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error SQL']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}