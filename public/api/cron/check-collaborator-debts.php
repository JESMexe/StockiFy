<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/core/Database.php';
    require_once $root . '/src/Services/CollaboratorDebtService.php';

    // Validar token de seguridad
    $secretToken = $_ENV['CRON_SECRET_TOKEN'] ?? null;
    if (!$secretToken || !isset($_GET['token']) || $_GET['token'] !== $secretToken) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado. Token inválido o no configurado.']);
        exit;
    }

    date_default_timezone_set('America/Argentina/Buenos_Aires');

    $debtSvc = new \App\Services\CollaboratorDebtService();
    $debtSvc->checkAndSendDebtWarnings();
    $debtSvc->processExpiredDebts();

    echo json_encode([
        'success' => true,
        'message' => 'Cron de vencimiento de deudas de colaboradores ejecutado con éxito.'
    ]);

} catch (Throwable $e) {
    error_log("Error Cron Check Collaborator Debts: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocurrió un error interno. Revisa los logs.']);
}
