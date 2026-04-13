<?php

require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/Models/UserModel.php';

use App\Models\UserModel;

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
$user = getCurrentUser();

if (!$user || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    $userModel = new UserModel();

    $success = $userModel->updateProfile($user['id'], $input);

    if ($success) {
        if (isset($input['full_name'])) {
            $_SESSION['user_name'] = $input['full_name'];
        }
        if (isset($input['username'])) {
            $_SESSION['username'] = $input['username'];
        }

        echo json_encode(['success' => true, 'message' => 'Perfil actualizado']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}