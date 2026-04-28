<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$root = dirname(__DIR__, 3);
require_once $root . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable($root)->load();

$appId = $_SERVER['TIENDANUBE_APP_ID'] ?? $_ENV['TIENDANUBE_APP_ID'] ?? null;
$scope = 'read_products'; // We only need to read for import

if (!$appId) {
    die("Error: No se encontró TIENDANUBE_APP_ID en la configuración (.env).");
}

$inventoryId = $_GET['inventory_id'] ?? $_SESSION['active_inventory_id'] ?? null;
if (!$inventoryId) {
    die("Error: Inventario no seleccionado.");
}

// Guardamos el inventory_id en la sesión para recuperarlo en el callback
$_SESSION['tn_pending_inventory_id'] = $inventoryId;

$authUrl = "https://www.tiendanube.com/apps/{$appId}/authorize?scope={$scope}";

header("Location: $authUrl");
exit;
