<?php
// src/config/mail_config.php

if (empty($_ENV['SMTP_USER']) || empty($_ENV['SMTP_PASS'])) {
    $envRoot = __DIR__ . '/../../';
    if (!class_exists('Dotenv\\Dotenv')) {
        require_once $envRoot . 'vendor/autoload.php';
    }
    try {
        $dotenv = \Dotenv\Dotenv::createImmutable($envRoot);
        $dotenv->load();
    } catch (\Throwable $e) {
        error_log('[mail_config] No se pudo cargar .env: ' . $e->getMessage());
    }
}

define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_USER', $_ENV['SMTP_USER'] ?? '');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
define('SMTP_PORT', (int)($_ENV['SMTP_PORT'] ?? 587));
define('SMTP_SECURE', $_ENV['SMTP_SECURE'] ?? 'tls');

define('MAIL_FROM_SECURITY', $_ENV['MAIL_FROM_SECURITY'] ?? SMTP_USER);
define('MAIL_NAME_SECURITY', $_ENV['MAIL_NAME_SECURITY'] ?? 'Seguridad StockiFy');