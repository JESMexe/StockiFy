<?php
// public/check_db_debug.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers/auth_helper.php';
require_once __DIR__ . '/../src/core/Database.php';

use App\core\Database;

// $user = getCurrentUser();
// if (!$user) {
//     die("Debes iniciar sesion para ver este log.");
// }

try {
    $db = Database::getInstance();
    
    echo "<h1>Debug de Pagos General</h1>";

    // Cargar config de pagos para ver qué valores ve el servidor
    require_once __DIR__ . '/../src/config/payment_config.php';
    echo "<h2>Configuración en el Servidor (Producción)</h2>";
    echo "<ul>";
    echo "<li><strong>PAYMENT_BASE_URL (Constante):</strong> " . (defined('PAYMENT_BASE_URL') ? PAYMENT_BASE_URL : 'No definida') . "</li>";
    echo "<li><strong>\$_ENV['PAYMENT_BASE_URL']:</strong> " . ($_ENV['PAYMENT_BASE_URL'] ?? 'No definida') . "</li>";
    echo "<li><strong>MP_ENV (Constante):</strong> " . (defined('MP_ENV') ? MP_ENV : 'No definida') . "</li>";
    echo "<li><strong>MP_ACCESS_TOKEN (Parcial):</strong> " . (defined('MP_ACCESS_TOKEN') ? substr(MP_ACCESS_TOKEN, 0, 15) . "..." . substr(MP_ACCESS_TOKEN, -5) : 'No definida') . "</li>";
    echo "<li><strong>MP_WEBHOOK_SECRET (Parcial):</strong> " . (defined('MP_WEBHOOK_SECRET') && MP_WEBHOOK_SECRET ? substr(MP_WEBHOOK_SECRET, 0, 6) . "..." : 'Vacío o No definida') . "</li>";
    echo "</ul>";
    
    echo "<h2>Últimas 5 Transacciones Registradas en DB</h2>";
    $stmt = $db->query("SELECT id, user_id, amount, status, nature, reference_id, created_at FROM payment_transactions ORDER BY id DESC LIMIT 5");
    $rows = $stmt->fetchAll();
    echo "<pre>" . print_r($rows, true) . "</pre>";
    
    echo "<h2>Últimos 10 Webhooks Recibidos en Base de Datos</h2>";
    $stmt = $db->query("SELECT id, gateway, event_id, status, error_message, processed_at FROM payment_webhooks_log ORDER BY id DESC LIMIT 10");
    $webhooks = $stmt->fetchAll();
    echo "<pre>" . print_r($webhooks, true) . "</pre>";

    echo "<h2>Archivo temporal webhook_debug.log</h2>";
    $logPath = __DIR__ . '/api/payments/webhook_debug.log';
    if (file_exists($logPath)) {
        echo "<pre>" . htmlspecialchars(file_get_contents($logPath)) . "</pre>";
    } else {
        echo "<p>El archivo webhook_debug.log no existe aún (no se recibió ninguna llamada de webhook).</p>";
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
