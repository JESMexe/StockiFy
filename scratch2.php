<?php
require 'src/core/Database.php';
require 'vendor/autoload.php';
$db = App\core\Database::getInstance();
$stmt = $db->query("SELECT id, email, full_name, cell FROM users WHERE subscription_active >= 2");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($users);
