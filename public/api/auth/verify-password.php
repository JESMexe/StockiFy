<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';


try {

    $data = json_decode(file_get_contents('php://input'), true);
    $newPassword = $data['newPass'];
    $passwordHash = $data['passwordHash'];

    $correctPassword = password_verify($newPassword, $passwordHash);

    $response = ['success' => true, 'correctPassword' => $correctPassword];

    header('Content-Type: application/json');
} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}
echo json_encode($response, JSON_NUMERIC_CHECK);


