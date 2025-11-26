<?php
// public/api/database/set-preferences-current.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

header('Content-Type: application/json');

try {
    // 1. Verificar Auth
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado.']);
        exit;
    }

    // 2. Recibir Datos
    $data = json_decode(file_get_contents('php://input'), true);
    $inventoryID = $_SESSION['active_inventory_id'] ?? null;

    if (!$inventoryID) {
        throw new Exception("No hay inventario seleccionado.");
    }

    $pdo = Database::getInstance();

    // 3. Actualizar Preferencias (Interruptores)
    $stmt = $pdo->prepare("UPDATE inventories SET min_stock = ?, sale_price = ?, receipt_price = ?,
                       hard_gain = ?, percentage_gain = ?, auto_price = ?, auto_price_type = ? WHERE id = ?");

    $stmt->execute([
        $data['min_stock']['active'],
        $data['sale_price']['active'],
        $data['receipt_price']['active'],
        $data['hard_gain']['active'],
        $data['percentage_gain']['active'],
        $data['auto_price'],
        $data['auto_price_type'],
        $inventoryID
    ]);

    // 4. Sincronización Física (Instalar Lámparas)
    // Obtenemos el nombre real de la tabla
    $stmtTable = $pdo->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
    $stmtTable->execute([$inventoryID]);
    $tableName = $stmtTable->fetchColumn();

    if ($tableName) {
        // Obtenemos las columnas que YA existen físicamente
        $stmtCols = $pdo->query("SHOW COLUMNS FROM `{$tableName}`");
        $existingColumns = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
        $existingColumns = array_map('strtolower', $existingColumns); // Normalizamos a minúsculas

        // Definimos qué columnas queremos asegurar y sus tipos
        $columnsToEnsure = [
            'min_stock'       => "INT DEFAULT " . (int)$data['min_stock']['default'],
            'sale_price'      => "DECIMAL(10,2) DEFAULT " . (float)$data['sale_price']['default'],
            'receipt_price'   => "DECIMAL(10,2) DEFAULT " . (float)$data['receipt_price']['default'],
            'hard_gain'       => "DECIMAL(10,2) DEFAULT " . (float)$data['hard_gain']['default'],
            'percentage_gain' => "DECIMAL(10,2) DEFAULT " . (float)$data['percentage_gain']['default']
        ];

        // Recorremos las columnas recomendadas activas
        foreach ($columnsToEnsure as $colName => $colDef) {
            // Si la preferencia está ACTIVA (1)
            if ($data[$colName]['active'] == 1) {
                // Y la columna NO existe físicamente
                if (!in_array($colName, $existingColumns)) {
                    // La creamos (ADD COLUMN)
                    $pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN `{$colName}` {$colDef}");
                } else {
                    // Si ya existe, actualizamos su valor por defecto (MODIFY COLUMN)
                    $pdo->exec("ALTER TABLE `{$tableName}` MODIFY COLUMN `{$colName}` {$colDef}");
                }
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'Preferencias y estructura actualizadas.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}