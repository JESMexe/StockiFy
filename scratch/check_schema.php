<?php
require 'vendor/autoload.php';
$root = dirname(__DIR__);
Dotenv\Dotenv::createImmutable($root)->load();
$db = App\core\Database::getInstance();
foreach($db->query('SHOW COLUMNS FROM inventories') as $c) {
    echo $c['Field'] . ' (' . $c['Type'] . ")\n";
}
