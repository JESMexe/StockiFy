<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\AuthController;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$authController = new AuthController();
$authController->register($data);