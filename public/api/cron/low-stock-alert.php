<?php
/**
 * /api/cron/low-stock-alert.php
 *
 * Cron job: Alerta de Stock Crítico vía WhatsApp y Email.
 *
 * Recorre TODOS los usuarios con suscripción >= 2, sus inventarios activos
 * que tengan min_stock habilitado, y envía una alerta por cada producto
 * que haya caído por debajo del mínimo configurado.
 *
 * Para evitar spam, usa la tabla `stock_alerts_sent` para no re-enviar
 * una alerta del mismo producto si ya fue enviada hoy.
 *
 * Llamar con: GET /api/cron/low-stock-alert.php?token=TU_CRON_SECRET_TOKEN
 */

header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/core/Database.php';
    require_once $root . '/src/Services/MailService.php';
    require_once $root . '/src/Services/WhatsappService.php';

    // --- Verificar token de seguridad ---
    $secretToken = $_ENV['CRON_SECRET_TOKEN'] ?? null;
    if (!$secretToken || !isset($_GET['token']) || $_GET['token'] !== $secretToken) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado. Token inválido o no configurado.']);
        exit;
    }

    date_default_timezone_set('America/Argentina/Buenos_Aires');
    $todayKey = date('Y-m-d'); // Clave del día para evitar re-envíos

    $db             = \App\core\Database::getInstance();
    $mailService    = new \App\Services\MailService();
    $whatsappSvc    = new \App\Services\WhatsappService();

    // Asegurar que la tabla de control anti-spam existe
    $db->exec("
        CREATE TABLE IF NOT EXISTS stock_alerts_sent (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id      INT NOT NULL,
            inventory_id INT NOT NULL,
            product_id   INT NOT NULL,
            sent_date    DATE NOT NULL,
            UNIQUE KEY uq_alert_day (user_id, inventory_id, product_id, sent_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // --- Obtener todos los usuarios elegibles ---
    $stmtUsers = $db->query(
        "SELECT id, email, full_name, cell
         FROM users
         WHERE subscription_active >= 2
           AND (email IS NOT NULL OR cell IS NOT NULL)"
    );
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    $alertsSent    = 0;
    $alertsSkipped = 0;
    $errors        = [];

    foreach ($users as $u) {
        $userId = (int)$u['id'];

        // Obtener inventarios del usuario con min_stock habilitado
        $stmtInv = $db->prepare(
            "SELECT i.id, i.name, i.preferences, ut.table_name
             FROM inventories i
             JOIN user_tables ut ON ut.inventory_id = i.id
             WHERE i.user_id = ?
             LIMIT 50"
        );
        $stmtInv->execute([$userId]);
        $inventories = $stmtInv->fetchAll(PDO::FETCH_ASSOC);

        foreach ($inventories as $inv) {
            $invId   = (int)$inv['id'];
            $invName = $inv['name'];
            $prefs   = json_decode($inv['preferences'] ?? '{}', true) ?? [];

            $mapping  = $prefs['mapping']  ?? [];
            $features = $prefs['features'] ?? [];

            // Solo procesar si min_stock está habilitado Y hay columnas mapeadas
            if (empty($features['min_stock'])) continue;

            $colStock = $mapping['stock'] ?? null;
            $colName  = $mapping['name']  ?? null;
            if (!$colStock || !$colName) continue;

            $tableSafe     = '`' . str_replace('`', '``', $inv['table_name']) . '`';
            $colStockSafe  = '`' . str_replace('`', '``', $colStock) . '`';
            $colNameSafe   = '`' . str_replace('`', '``', $colName) . '`';

            // Buscar productos bajo el mínimo
            $query = "SELECT id, {$colNameSafe} AS pname, {$colStockSafe} AS pstock, min_stock
                      FROM {$tableSafe}
                      WHERE {$colStockSafe} IS NOT NULL
                        AND min_stock IS NOT NULL
                        AND CAST({$colStockSafe} AS DECIMAL(10,2)) <= CAST(min_stock AS DECIMAL(10,2))
                      ORDER BY (CAST(min_stock AS DECIMAL(10,2)) - CAST({$colStockSafe} AS DECIMAL(10,2))) DESC
                      LIMIT 20";

            $stmtProducts = $db->query($query);
            $criticalProducts = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);

            foreach ($criticalProducts as $product) {
                $productId   = (int)$product['id'];
                $productName = (string)($product['pname'] ?? 'Producto');
                $stock       = (float)($product['pstock'] ?? 0);
                $minStock    = (float)($product['min_stock'] ?? 0);

                // Anti-spam: verificar si ya se envió alerta hoy para este producto
                $stmtCheck = $db->prepare(
                    "SELECT 1 FROM stock_alerts_sent
                     WHERE user_id = ? AND inventory_id = ? AND product_id = ? AND sent_date = ?"
                );
                $stmtCheck->execute([$userId, $invId, $productId, $todayKey]);
                if ($stmtCheck->fetch()) {
                    $alertsSkipped++;
                    continue; // Ya enviado hoy, saltar
                }

                // Enviar por Email
                $mailSent = false;
                if (!empty($u['email'])) {
                    try {
                        $mailSent = $mailService->sendLowStockAlert(
                            $u['email'],
                            $u['full_name'] ?? 'Usuario',
                            $productName,
                            $stock,
                            $minStock,
                            $invName
                        );
                    } catch (\Throwable $me) {
                        $errors[] = "Mail [{$u['email']}] {$productName}: " . $me->getMessage();
                    }
                }

                // Enviar por WhatsApp
                $waSent = false;
                if (!empty($u['cell'])) {
                    $waSent = $whatsappSvc->sendLowStockAlert(
                        $u['cell'],
                        $u['full_name'] ?? 'Usuario',
                        $productName,
                        $stock,
                        $minStock,
                        $invName,
                        $productId
                    );
                    if (!$waSent) {
                        $errors[] = "WA [{$u['cell']}] {$productName}: " . $whatsappSvc->lastError;
                    }
                }

                // Registrar como enviado (aunque haya fallado email, para no hacer spam)
                if ($mailSent || $waSent) {
                    $db->prepare(
                        "INSERT IGNORE INTO stock_alerts_sent (user_id, inventory_id, product_id, sent_date)
                         VALUES (?, ?, ?, ?)"
                    )->execute([$userId, $invId, $productId, $todayKey]);
                    $alertsSent++;
                }
            }
        }
    }

    echo json_encode([
        'success'        => true,
        'message'        => 'Cron de Alerta de Stock Crítico ejecutado.',
        'alerts_sent'    => $alertsSent,
        'alerts_skipped' => $alertsSkipped,
        'errors'         => array_slice($errors, 0, 5), // Primeros 5 errores
    ]);

} catch (\Throwable $e) {
    error_log('[low-stock-alert cron] ERROR: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}
