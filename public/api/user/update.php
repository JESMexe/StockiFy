<?php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $userData = $data;

    $pdo = Database::getInstance();

    $passType = $data['passwordType'];

    if ($passType == 'number') {
        $newPassword = password_hash($data['password'],PASSWORD_DEFAULT);
    }
    else{
        $newPassword = $data['password'];
    }

    $user = getCurrentUser();
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, password_hash = ?, username = ? WHERE id = ?");
    $stmt->execute([$data['full_name'], $data['email'], $newPassword, $data['username'], $user_id]);
    $response = ['success' => true];
} catch (Exception $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response, JSON_NUMERIC_CHECK);

