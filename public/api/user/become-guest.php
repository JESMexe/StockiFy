<?php
// public/api/user/become-guest.php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = getCurrentUser();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Inicia sesión para realizar esta acción.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

try {
    $currentPlan = (int)($user['subscription_active'] ?? 0);

    if ($currentPlan === 5) {
        echo json_encode([
            'success' => true,
            'message' => 'Ya eres un usuario Invitado.'
        ]);
        exit;
    }

    if ($currentPlan !== 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No puedes cambiar a Invitado si tienes un plan activo.'
        ]);
        exit;
    }

    $db = \App\core\Database::getInstance();
    $stmt = $db->prepare("UPDATE users SET subscription_active = 5 WHERE id = ? AND subscription_active = 0");
    $success = $stmt->execute([(int)$user['id']]);

    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => '¡Has sido registrado como Invitado con éxito!'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No se pudo actualizar tu plan.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
