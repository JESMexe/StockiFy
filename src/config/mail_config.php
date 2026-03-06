<?php
// src/config/mail_config.php

if (!isset($_ENV) || empty($_ENV)) {
    require_once __DIR__ . '/../bootstrap.php';
}

define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_USER', $_ENV['SMTP_USER'] ?? '');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
define('SMTP_PORT', (int)($_ENV['SMTP_PORT'] ?? 587));
define('SMTP_SECURE', $_ENV['SMTP_SECURE'] ?? 'tls');

define('MAIL_FROM_SECURITY', $_ENV['MAIL_FROM_SECURITY'] ?? SMTP_USER);
define('MAIL_NAME_SECURITY', $_ENV['MAIL_NAME_SECURITY'] ?? 'Seguridad StockiFy');