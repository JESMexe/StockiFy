<?php
require_once __DIR__ . '/src/core/Database.php';
require_once __DIR__ . '/vendor/autoload.php';
use App\core\Database;

Dotenv\Dotenv::createImmutable(__DIR__)->load();

$db = Database::getInstance();
$stmt = $db->query("SELECT tiendanube_token, tiendanube_store_id FROM inventories WHERE tiendanube_token IS NOT NULL LIMIT 1");
$inv = $stmt->fetch();

if (!$inv) {
    die("No connected store found.\n");
}

$token = $inv['tiendanube_token'];
$storeId = $inv['tiendanube_store_id'];

echo "Store ID: $storeId\n";
echo "Token: " . substr($token, 0, 10) . "...\n";

$url = "https://api.tiendanube.com/v1/{$storeId}/products?per_page=1";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authentication: bearer {$token}",
    "User-Agent: StockiFy (no_reply@stockify.com.ar)"
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $status\n";
echo "Response: " . substr($response, 0, 500) . "\n";
