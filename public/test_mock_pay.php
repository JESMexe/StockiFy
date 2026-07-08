<?php
/**
 * test_mock_pay.php - Script para simular pagos y activar planes localmente.
 * 
 * USO:
 *   Abrir en el navegador: http://localhost/test_mock_pay.php?plan=2
 *   (Donde 'plan' puede ser: 1 = Básico, 2 = Profesional, 4 = Vitalicio)
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers/auth_helper.php';
require_once __DIR__ . '/../src/Services/Payments/PaymentService.php';
require_once __DIR__ . '/../src/Services/Payments/MercadoPagoGateway.php';

use App\Services\Payments\MercadoPagoGateway;
use App\Services\Payments\PaymentService;

$currentUser = getCurrentUser();
if (!$currentUser) {
    die("Debés iniciar sesión en StockiFy primero para poder probar este simulador.");
}

$planId = isset($_GET['plan']) ? (int)$_GET['plan'] : 2; // Profesional por defecto
if (!in_array($planId, [1, 2, 4, 5])) {
    die("Plan inválido. Usá: ?plan=1 (Básico), ?plan=2 (Profesional) o ?plan=4 (Vitalicio).");
}

try {
    $gateway = new MercadoPagoGateway();
    $paymentService = new PaymentService($gateway);
    
    // Usamos reflexión para poder invocar la función privada activateUserPlan para tests locales
    $reflection = new ReflectionClass($paymentService);
    $method = $reflection->getMethod('activateUserPlan');
    $method->setAccessible(true);
    $method->invoke($paymentService, (int)$currentUser['id'], $planId);
    
    // Forzar actualización de la sesión local si se almacena allí
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['user'])) {
        $_SESSION['user']['subscription_active'] = $planId;
    }
    
    echo "<h1>¡Simulación Exitosa! 🎉</h1>";
    echo "<p>Se ha activado el <strong>Plan ID: {$planId}</strong> para tu usuario <strong>{$currentUser['email']}</strong> (ID: {$currentUser['id']}).</p>";
    echo "<p><a href='/settings.php?tab=suscripcion' style='font-weight:bold; color:#10b981; text-decoration:none;'>👉 Ir a 'Mi Suscripción' en Configuración para comprobarlo</a></p>";
    
} catch (\Throwable $e) {
    echo "<h1>Error en la simulación</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
