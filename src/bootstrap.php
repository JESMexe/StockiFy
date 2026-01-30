<?php
// src/bootstrap.php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
} catch (Exception $e) {
    error_log("Error: No se pudo cargar el archivo .env - " . $e->getMessage());
}