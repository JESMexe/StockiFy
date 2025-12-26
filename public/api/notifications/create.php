<?php
// public/api/notifications/create.php

require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/Models/NotificationModel.php';

use App\Models\NotificationModel;

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? null;
$title = $data['title'] ?? null;
$message = $data['message'] ?? null;

if (!$type || !$title) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tipo y título son requeridos']);
    exit;
}

try {
    $model = new NotificationModel();
    $model->create($user['id'], $type, $title, $message);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}