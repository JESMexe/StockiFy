<?php
// public/api/user/profile.php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\UserController;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

$userController = new UserController();
$userController->getProfile();