<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\UserController;

$userController = new UserController();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userController->getProfile();
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $userController->updateProfile($data);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}


