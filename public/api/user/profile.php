<?php
// public/api/user/profile.php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\UserController;

// Verificamos que sea una petición GET, ya que solo estamos pidiendo datos
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

$userController = new UserController();
$userController->getProfile();