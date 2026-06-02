<?php
// public/api/user/start-trial.php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/Models/UserModel.php';

use App\Models\UserModel;

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = getCurrentUser();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Inicia sesión para activar la prueba gratuita.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

try {
    // Verificar si ya usó la prueba
    if ((int)$user['trial_used'] === 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ya has utilizado la prueba gratuita anteriormente.']);
        exit;
    }

    $userModel = new UserModel();
    $success = $userModel->activateFreeTrial((int)$user['id']);

    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => '¡Prueba gratuita de 30 días activada con éxito! Acceso elevado nivel 4 habilitado.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No se pudo activar la prueba gratuita.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
