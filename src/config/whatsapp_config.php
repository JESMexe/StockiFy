<?php
// src/config/whatsapp_config.php

if (!isset($_ENV['WHATSAPP_PHONE_NUMBER_ID']) || empty($_ENV['WHATSAPP_PHONE_NUMBER_ID'])) {
    require_once __DIR__ . '/../bootstrap.php';
}

define('WHATSAPP_PHONE_NUMBER_ID', $_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? '');
define('WHATSAPP_BUSINESS_ACCOUNT_ID', $_ENV['WHATSAPP_BUSINESS_ACCOUNT_ID'] ?? '');
define('WHATSAPP_ACCESS_TOKEN', $_ENV['WHATSAPP_ACCESS_TOKEN'] ?? '');
