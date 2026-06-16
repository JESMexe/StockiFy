<?php
// src/config/whatsapp_config.php

// Cargar el .env de forma segura y autónoma, sin depender de bootstrap.php
// (bootstrap.php llama a session_start() lo que puede fallar en contextos CLI/cron).
if (empty($_ENV['WHATSAPP_PHONE_NUMBER_ID']) || empty($_ENV['WHATSAPP_ACCESS_TOKEN'])) {
    $envRoot = __DIR__ . '/../../';
    if (!class_exists('Dotenv\\Dotenv')) {
        require_once $envRoot . 'vendor/autoload.php';
    }
    try {
        $dotenv = \Dotenv\Dotenv::createImmutable($envRoot);
        $dotenv->load();
    } catch (\Throwable $e) {
        error_log('[whatsapp_config] No se pudo cargar .env: ' . $e->getMessage());
    }
}

if (!defined('WHATSAPP_PHONE_NUMBER_ID')) {
    define('WHATSAPP_PHONE_NUMBER_ID',     $_ENV['WHATSAPP_PHONE_NUMBER_ID']     ?? '');
}
if (!defined('WHATSAPP_BUSINESS_ACCOUNT_ID')) {
    define('WHATSAPP_BUSINESS_ACCOUNT_ID', $_ENV['WHATSAPP_BUSINESS_ACCOUNT_ID'] ?? '');
}
if (!defined('WHATSAPP_ACCESS_TOKEN')) {
    define('WHATSAPP_ACCESS_TOKEN',        $_ENV['WHATSAPP_ACCESS_TOKEN']        ?? '');
}
