<?php
/**
 * Script de migración de base de datos para agregar columnas necesarias.
 * Protegido por CRON_SECRET_TOKEN.
 * URL recomendada para producción:
 * https://stockify.com.ar/api/database/migrate.php?token=STOCKIFY_CRON_2026
 */
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/core/Database.php';

    // Cargar variables de entorno
    if (class_exists('Dotenv\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable($root);
        $dotenv->load();
    }

    // 1. Validar el token de seguridad
    $secretToken = $_ENV['CRON_SECRET_TOKEN'] ?? null;
    if (!$secretToken || !isset($_GET['token']) || $_GET['token'] !== $secretToken) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado. Token inválido o no configurado.']);
        exit;
    }

    $db = \App\core\Database::getInstance();
    $logs = [];

    // Función auxiliar para verificar la existencia de columnas utilizando INFORMATION_SCHEMA
    function columnExists($db, $table, $column) {
        try {
            $stmt = $db->prepare("
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = ? 
                  AND COLUMN_NAME = ?
            ");
            $stmt->execute([$table, $column]);
            return $stmt->fetch() !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    // 2. Migrar la tabla activity_logs
    if (!columnExists($db, 'activity_logs', 'section')) {
        $db->exec("ALTER TABLE activity_logs ADD COLUMN section VARCHAR(50) NOT NULL DEFAULT 'Sistema' AFTER role_name");
        $logs[] = "Columna 'section' agregada a tabla 'activity_logs'.";
    } else {
        $logs[] = "Columna 'section' ya existe en 'activity_logs'.";
    }

    if (!columnExists($db, 'activity_logs', 'extra_description')) {
        $db->exec("ALTER TABLE activity_logs ADD COLUMN extra_description TEXT NULL AFTER description");
        $logs[] = "Columna 'extra_description' agregada a tabla 'activity_logs'.";
    } else {
        $logs[] = "Columna 'extra_description' ya existe en 'activity_logs'.";
    }

    // 3. Migrar la tabla inventories
    if (!columnExists($db, 'inventories', 'report_enabled')) {
        $db->exec("ALTER TABLE inventories ADD COLUMN report_enabled TINYINT(1) NOT NULL DEFAULT 1");
        $logs[] = "Columna 'report_enabled' agregada a tabla 'inventories'.";
    } else {
        $logs[] = "Columna 'report_enabled' ya existe en 'inventories'.";
    }

    if (!columnExists($db, 'inventories', 'inactivity_days')) {
        $db->exec("ALTER TABLE inventories ADD COLUMN inactivity_days INT(11) NOT NULL DEFAULT 0");
        $logs[] = "Columna 'inactivity_days' agregada a tabla 'inventories'.";
    } else {
        $logs[] = "Columna 'inactivity_days' ya existe en 'inventories'.";
    }

    echo json_encode([
        'success' => true,
        'message' => 'Migración completada con éxito.',
        'details' => $logs
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error durante la migración: ' . $e->getMessage()
    ]);
}
