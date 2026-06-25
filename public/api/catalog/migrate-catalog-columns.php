<?php
/**
 * migrate-catalog-columns.php
 * ============================================================
 * Script de migración ONE-SHOT para el Catálogo Online Público.
 * Solo puede ejecutarlo un administrador (is_admin = 1).
 *
 * Qué hace:
 *  1. Agrega columnas de catálogo a `inventories` (si no existen).
 *  2. Agrega `public_visible` a cada tabla dinámica de productos
 *     registrada en `user_tables` (si no existe la columna).
 *
 * GET /api/catalog/migrate-catalog-columns.php
 * ============================================================
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/bootstrap.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

header('Content-Type: application/json');

$secret = trim($_GET['secret'] ?? '');
$cronToken = $_ENV['CRON_SECRET_TOKEN'] ?? getenv('CRON_SECRET_TOKEN') ?? '';

$authorized = false;

// 1. Autorización por token secreto
if (!empty($cronToken) && $secret === $cronToken) {
    $authorized = true;
}

// 2. Autorización por usuario administrador logueado
if (!$authorized) {
    $user = getCurrentUser();
    if ($user && !empty($user['is_admin'])) {
        $authorized = true;
    }
}

if (!$authorized) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado.']);
    exit;
}

try {
    $pdo = Database::getInstance();
    $log = [];

    // -------------------------------------------------------
    // 1. Columnas de catálogo en la tabla `inventories`
    // -------------------------------------------------------
    $catalogColumns = [
        'catalog_active'   => "ALTER TABLE `inventories` ADD COLUMN `catalog_active` TINYINT(1) NOT NULL DEFAULT 0",
        'catalog_slug'     => "ALTER TABLE `inventories` ADD COLUMN `catalog_slug` VARCHAR(100) DEFAULT NULL",
        'catalog_settings' => "ALTER TABLE `inventories` ADD COLUMN `catalog_settings` TEXT DEFAULT NULL",
    ];

    // Verificar cuáles ya existen
    $existingCols = $pdo->query("SHOW COLUMNS FROM `inventories`")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($catalogColumns as $colName => $alterSql) {
        if (!in_array($colName, $existingCols)) {
            $pdo->exec($alterSql);
            $log[] = "✓ Columna `{$colName}` agregada a `inventories`.";
        } else {
            $log[] = "→ Columna `{$colName}` ya existía en `inventories`. Omitida.";
        }
    }

    // Índice UNIQUE para catalog_slug
    $indexes = $pdo->query("SHOW INDEX FROM `inventories` WHERE Key_name = 'uq_catalog_slug'")->fetchAll();
    if (empty($indexes)) {
        $pdo->exec("ALTER TABLE `inventories` ADD UNIQUE KEY `uq_catalog_slug` (`catalog_slug`)");
        $log[] = "✓ Índice UNIQUE `uq_catalog_slug` creado.";
    } else {
        $log[] = "→ Índice UNIQUE `uq_catalog_slug` ya existía. Omitido.";
    }

    // -------------------------------------------------------
    // 2. Columna `public_visible` en cada tabla dinámica
    // -------------------------------------------------------
    $stmtTables = $pdo->query("SELECT table_name FROM user_tables");
    $userTables  = $stmtTables->fetchAll(PDO::FETCH_COLUMN);

    foreach ($userTables as $tableName) {
        // Verificar si la tabla existe en el motor
        $tableExists = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($tableName)
        )->fetchColumn();

        if (!$tableExists) {
            $log[] = "⚠ Tabla `{$tableName}` registrada en user_tables pero no existe en BD. Omitida.";
            continue;
        }

        // Verificar si la columna ya existe
        $colExists = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = " . $pdo->quote($tableName) . "
               AND column_name = 'public_visible'"
        )->fetchColumn();

        if (!$colExists) {
            $safeTable = "`" . str_replace("`", "``", $tableName) . "`";
            $pdo->exec("ALTER TABLE {$safeTable} ADD COLUMN `public_visible` TINYINT(1) NOT NULL DEFAULT 0");
            $log[] = "✓ Columna `public_visible` agregada a `{$tableName}`.";
        } else {
            $log[] = "→ Columna `public_visible` ya existía en `{$tableName}`. Omitida.";
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Migración completada.',
        'log'     => $log,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
