<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $root = dirname(__DIR__, 2);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Services/WhatsappService.php';

    if (!function_exists('getCurrentUser')) {
        throw new \Exception('Error de autenticación');
    }
    
    $user = getCurrentUser();
    if (!$user) { 
        echo json_encode(['success' => false, 'message' => 'No autorizado. Iniciá sesión primero.']); 
        exit; 
    }

    require_once $root . '/src/core/Database.php';
    $db = \App\core\Database::getInstance();
    $stmtUser = $db->prepare("SELECT cell, full_name FROM users WHERE id = :id");
    $stmtUser->execute([':id' => $user['id']]);
    $u = $stmtUser->fetch(PDO::FETCH_ASSOC);

    $targetPhone = $_GET['phone'] ?? $u['cell'] ?? null;

    if (empty($targetPhone)) {
        echo json_encode([
            'success' => false, 
            'message' => 'No se encontró un número de celular asociado al usuario. Por favor ingresá un número de prueba con ?phone=54911...'
        ]);
        exit;
    }

    $waSvc = new \App\Services\WhatsappService();
    $userName = $u['full_name'] ?? 'Usuario Prueba';
    
    // Test 1: Alerta de stock crítico
    $testStock = $waSvc->sendLowStockAlert($targetPhone, $userName, 'Producto de Prueba', 3, 10, 'Inventario Demo');
    
    // Test 2: Balance diario
    $testBalance = $waSvc->sendDailyBalance($targetPhone, $userName, date('d/m/Y'), 50000.50, 15000.00, 35000.50, 'Inventario Demo');

    echo json_encode([
        'success' => true,
        'message' => 'Pruebas disparadas.',
        'results' => [
            'alerta_stock_critico' => $testStock,
            'reporte_cierre_caja' => $testBalance
        ]
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
