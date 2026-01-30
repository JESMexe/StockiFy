<?php
// public/api/user/update.php

require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/Models/UserModel.php';

use App\Models\UserModel;

header('Content-Type: application/json');

// 1. Verificación de Seguridad
if (session_status() === PHP_SESSION_NONE) session_start();
$user = getCurrentUser();

if (!$user || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

// 2. Obtención de Datos
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    $userModel = new UserModel();

    // 3. Ejecución
    // Nota: El frontend mandará 'full_name', no 'name' y 'surname' separados,
    // para respetar la estructura de tu DB.
    $success = $userModel->updateProfile($user['id'], $input);

    if ($success) {
        // Si cambió el nombre completo, actualizamos la sesión
        if (isset($input['full_name'])) {
            $_SESSION['user_name'] = $input['full_name'];
        }
        // Si cambió el username, actualizamos la sesión
        if (isset($input['username'])) {
            $_SESSION['username'] = $input['username'];
        }

        echo json_encode(['success' => true, 'message' => 'Perfil actualizado']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}