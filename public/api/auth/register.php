<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\controllers\AuthController;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// Lee el cuerpo de la petición raw y lo decodifica de JSON a un array de PHP
$data = json_decode(file_get_contents('php://input'), true);

$authController = new AuthController();
$authController->register($data);