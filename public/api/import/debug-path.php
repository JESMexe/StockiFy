<?php
$root = dirname(__DIR__, 3);
echo "DEBUG INFO:<br>";
echo "Current File: " . __FILE__ . "<br>";
echo "Resolved Root: " . realpath($root) . "<br>";
echo "Root exists: " . (is_dir($root) ? 'Yes' : 'No') . "<br>";
echo ".env exists: " . (file_exists($root . '/.env') ? 'Yes' : 'No') . "<br>";
echo "vendor exists: " . (is_dir($root . '/vendor') ? 'Yes' : 'No') . "<br>";

require_once $root . '/vendor/autoload.php';
try {
    Dotenv\Dotenv::createImmutable($root)->load();
    echo "Dotenv loaded successfully.<br>";
    echo "DB_HOST: " . ($_SERVER['DB_HOST'] ?? $_ENV['DB_HOST'] ?? 'MISSING') . "<br>";
    echo "TIENDANUBE_APP_ID: " . ($_SERVER['TIENDANUBE_APP_ID'] ?? $_ENV['TIENDANUBE_APP_ID'] ?? 'MISSING') . "<br>";
    
    echo "<br>All loaded keys in \$_ENV:<br>";
    print_r(array_keys($_ENV));
} catch (Exception $e) {
    echo "Dotenv error: " . $e->getMessage() . "<br>";
}
