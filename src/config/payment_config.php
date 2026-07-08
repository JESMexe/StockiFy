<?php
/**
 * payment_config.php
 *
 * Centraliza la configuración del motor de pagos de StockiFy.
 * Lee credenciales del .env para aislar la lógica de negocio de los secretos.
 *
 * PRECIOS DINÁMICOS:
 *   Los precios de los planes y de los slots de colaboradores se definen aquí
 *   como fuente única de verdad. Son en ARS (pesos argentinos).
 */

declare(strict_types=1);

// Cargar .env si las variables de MP aún no están en $_ENV (mismo patrón que mail_config.php)
if (empty($_ENV['MP_ACCESS_TOKEN'])) {
    $envRoot = __DIR__ . '/../../';
    if (!class_exists('Dotenv\\Dotenv')) {
        require_once $envRoot . 'vendor/autoload.php';
    }
    try {
        $dotenv = \Dotenv\Dotenv::createImmutable($envRoot);
        $dotenv->load();
    } catch (\Throwable $e) {
        error_log('[payment_config] No se pudo cargar .env: ' . $e->getMessage());
    }
}

// --- Precios dinámicos de planes (en ARS) ---
define('PAYMENT_PLAN_PRICES', [
    1 => 5000.00,   // Básico
    2 => 15000.00,  // Profesional
    3 => 35000.00,  // Empresarial
    4 => 80000.00,  // Vitalicio (pago único)
]);

define('PAYMENT_PLAN_NAMES', [
    1 => 'Plan Básico',
    2 => 'Plan Profesional',
    3 => 'Plan Empresarial',
    4 => 'Plan Vitalicio',
]);

// Precio por slot adicional de colaborador (en ARS)
define('PAYMENT_SLOT_PRICE', 20000.00);

// Créditos de días para cada plan al renovar (activación mensual)
define('PAYMENT_PLAN_DURATION_DAYS', [
    1 => 30,
    2 => 30,
    3 => 30,
    4 => 36500, // Vitalicio: 100 años
]);

// --- Credenciales de Mercado Pago (leídas del .env) ---
define('MP_ACCESS_TOKEN',       $_ENV['MP_ACCESS_TOKEN']       ?? '');
define('MP_PUBLIC_KEY',         $_ENV['MP_PUBLIC_KEY']         ?? '');
define('MP_WEBHOOK_SECRET',     $_ENV['MP_WEBHOOK_SECRET']     ?? '');
define('MP_ENV',                $_ENV['MP_ENV']                ?? 'sandbox');

// --- Configuración general de pagos ---
define('PAYMENT_CURRENCY', 'ARS');
define('PAYMENT_APP_NAME', 'StockiFy');
define('PAYMENT_BASE_URL', $_ENV['PAYMENT_BASE_URL'] ?? 'https://stockify.com.ar');
