<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$root = dirname(__DIR__, 3);
require_once $root . '/vendor/autoload.php';
require_once $root . '/src/core/Database.php';

use App\core\Database;

Dotenv\Dotenv::createImmutable($root)->load();

$code = $_GET['code'] ?? null;
$inventoryId = $_SESSION['tn_pending_inventory_id'] ?? null;

$appId = $_SERVER['TIENDANUBE_APP_ID'] ?? $_ENV['TIENDANUBE_APP_ID'] ?? null;
$clientSecret = $_SERVER['TIENDANUBE_CLIENT_SECRET'] ?? $_ENV['TIENDANUBE_CLIENT_SECRET'] ?? null;

if (!$code || !$inventoryId) {
    die("Error: Datos de autorización no válidos o sesión expirada.");
}

if (!$appId || !$clientSecret) {
    die("Error: Credenciales de TiendaNube no encontradas en .env.");
}

// Intercambiar código por token
$ch = curl_init("https://www.tiendanube.com/apps/authorize/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'client_id' => $appId,
    'client_secret' => $clientSecret,
    'grant_type' => 'authorization_code',
    'code' => $code
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);

if (isset($data['access_token'])) {
    $token = $data['access_token'];
    $storeId = $data['user_id']; // En TiendaNube user_id es el store_id

    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE inventories SET tiendanube_token = ?, tiendanube_store_id = ? WHERE id = ?");
        $stmt->execute([$token, $storeId, $inventoryId]);

        // Redirigir de vuelta al dashboard con éxito
        header("Location: /dashboard?tn_success=1");
        exit;
    } catch (Exception $e) {
        die("Error al guardar token: " . $e->getMessage());
    }
} else {
    die("Error al obtener token de TiendaNube: " . ($data['error_description'] ?? 'Error desconocido'));
}
