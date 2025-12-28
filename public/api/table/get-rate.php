<?php
// public/api/table/get-rate.php
header('Content-Type: application/json');
ini_set('display_errors', 0); // IMPORTANTE: Ocultar warnings de PHP en la respuesta
error_reporting(E_ALL);

use App\Services\ExchangeService;

try {
    // Ajusta las rutas relativos si tu estructura es diferente
    $root = dirname(__DIR__, 3); // Sube 3 niveles hasta la raiz
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/Services/ExchangeService.php';


    $service = new ExchangeService();
    $rates = $service->getRates();

    echo json_encode([
        'success' => true,
        'buy' => $rates['buy'],
        'sell' => $rates['sell'],
        'avg' => $rates['avg'],
        'updated' => $rates['updated']
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}