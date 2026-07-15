<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

$root = dirname(__DIR__, 3);
require_once $root . '/vendor/autoload.php';
require_once $root . '/src/helpers/auth_helper.php';
require_once $root . '/src/core/Database.php';
require_once $root . '/src/Services/ExchangeService.php';

use App\core\Database;
use App\Services\ExchangeService;

try {
    $user = getCurrentUser();
    $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;
    
    $exchangeConfig = null;

    if ($user && $activeInventoryId) {
        $role = getInventoryRole((int)$user['id'], (int)$activeInventoryId);
        if ($role) {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT preferences FROM inventories WHERE id = ?");
            $stmt->execute([(int)$activeInventoryId]);
            $inv = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($inv) {
                $prefs = json_decode($inv['preferences'] ?? '{}', true);
                $exchangeConfig = $prefs['exchange_config'] ?? null;
            }
        }
    }

    $forceRefresh = isset($_GET['force_refresh']) && $_GET['force_refresh'] === 'true';

    $service = new ExchangeService();
    $rates = $service->getContextualRate($exchangeConfig, $forceRefresh);

    echo json_encode([
        'success' => true,
        'buy' => $rates['buy'],
        'sell' => $rates['sell'],
        'avg' => $rates['avg'],
        'updated' => $rates['updated'],
        'source' => $rates['source'],
        'type' => $exchangeConfig['type'] ?? 'api',
        'api_source' => $exchangeConfig['api_source'] ?? 'blue'
    ]);

} catch (Throwable $e) {
    http_response_code($e->getMessage() === 'API_DOWN' ? 503 : 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}