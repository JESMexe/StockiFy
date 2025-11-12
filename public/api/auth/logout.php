<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\UserController;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$userController = new UserController();
$userController->logout();
