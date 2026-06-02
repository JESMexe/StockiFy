<?php
/**
 * /api/test-whatsapp-debug.php
 *
 * Endpoint de DIAGNÓSTICO para WhatsApp Business API.
 * Muestra la respuesta RAW de Meta para detectar exactamente
 * por qué una plantilla falla (variables incorrectas, estado, etc.)
 *
 * Uso: GET /api/test-whatsapp-debug.php
 * Solo accesible para usuarios autenticados.
 */

header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    $root = dirname(__DIR__, 2);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/config/whatsapp_config.php';
    require_once $root . '/src/core/Database.php';

    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado.']);
        exit;
    }

    // Obtener datos del usuario
    $db = \App\core\Database::getInstance();
    $stmt = $db->prepare("SELECT cell, full_name FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    $targetPhone = $_GET['phone'] ?? $u['cell'] ?? null;
    if (empty($targetPhone)) {
        echo json_encode(['success' => false, 'message' => 'Sin número. Pasá ?phone=549112345678']);
        exit;
    }

    $cleanPhone = preg_replace('/[^0-9]/', '', $targetPhone);
    $userName   = $u['full_name'] ?? 'Usuario Test';

    $graphVersion = 'v20.0';
    $baseUrl      = 'https://graph.facebook.com';
    $url          = "{$baseUrl}/{$graphVersion}/" . WHATSAPP_PHONE_NUMBER_ID . "/messages";

    $headers = [
        'Authorization: Bearer ' . WHATSAPP_ACCESS_TOKEN,
        'Content-Type: application/json',
    ];

    $results = [];

    // ---- Test 1: alerta_stock_critico ----
    // Variables nombradas: nombre_usuario, nombre_inventario, nombre_producto, producto_id, stock_actual, stock_minimo
    $payload1 = [
        'messaging_product' => 'whatsapp',
        'to'   => $cleanPhone,
        'type' => 'template',
        'template' => [
            'name'     => 'alerta_stock_critico',
            'language' => ['code' => 'es_AR'],
            'components' => [[
                'type'       => 'body',
                'parameters' => [
                    ['type' => 'text', 'parameter_name' => 'nombre_usuario',    'text' => $userName],
                    ['type' => 'text', 'parameter_name' => 'nombre_inventario', 'text' => 'Inventario Demo'],
                    ['type' => 'text', 'parameter_name' => 'nombre_producto',   'text' => 'Producto de Prueba'],
                    ['type' => 'text', 'parameter_name' => 'producto_id',       'text' => '42'],
                    ['type' => 'text', 'parameter_name' => 'stock_actual',      'text' => '3'],
                    ['type' => 'text', 'parameter_name' => 'stock_minimo',      'text' => '10'],
                ],
            ]],
        ],
    ];
    $results['alerta_stock_critico'] = _callWhatsAppRaw($url, $headers, $payload1);

    // ---- Test 2: reporte_cierre_caja ----
    // Variables: {{inventario_nombre}}, {{nombre_usuario}}, {{fecha}}, {{ventas_totales}}, {{gastos_totales}}, {{balance}}
    $payload2 = [
        'messaging_product' => 'whatsapp',
        'to'   => $cleanPhone,
        'type' => 'template',
        'template' => [
            'name'     => 'reporte_cierre_caja',
            'language' => ['code' => 'es_AR'],
            'components' => [[
                'type'       => 'body',
                'parameters' => [
                    ['type' => 'text', 'parameter_name' => 'inventario_nombre', 'text' => 'Inventario Demo'],
                    ['type' => 'text', 'parameter_name' => 'nombre_usuario',    'text' => $userName],
                    ['type' => 'text', 'parameter_name' => 'fecha',             'text' => date('d/m/Y')],
                    ['type' => 'text', 'parameter_name' => 'ventas_totales',    'text' => '$50.000,00'],
                    ['type' => 'text', 'parameter_name' => 'gastos_totales',    'text' => '$15.000,00'],
                    ['type' => 'text', 'parameter_name' => 'balance',           'text' => '$35.000,00'],
                ],
            ]],
        ],
    ];
    $results['reporte_cierre_caja'] = _callWhatsAppRaw($url, $headers, $payload2);

    // ---- Test 3: producto_agotado ----
    // Variables: {{nombre_usuario}}, {{nombre_inventario}}, {{nombre_producto}}, {{producto_id}}
    $payload3 = [
        'messaging_product' => 'whatsapp',
        'to'   => $cleanPhone,
        'type' => 'template',
        'template' => [
            'name'     => 'producto_agotado',
            'language' => ['code' => 'es_AR'],
            'components' => [[
                'type'       => 'body',
                'parameters' => [
                    ['type' => 'text', 'parameter_name' => 'nombre_usuario',    'text' => $userName],
                    ['type' => 'text', 'parameter_name' => 'nombre_inventario', 'text' => 'Inventario Demo'],
                    ['type' => 'text', 'parameter_name' => 'nombre_producto',   'text' => 'Producto Agotado Test'],
                    ['type' => 'text', 'parameter_name' => 'producto_id',       'text' => '99'],
                ],
            ]],
        ],
    ];
    $results['producto_agotado'] = _callWhatsAppRaw($url, $headers, $payload3);

    // ---- Test 4: invitacion_aceptada ----
    // Variables: {{nombre_usuario}}, {{nombre_invitado}}, {{email_invitado}}, {{nombre_inventario}}, {{rol_invitado}}
    $payload4 = [
        'messaging_product' => 'whatsapp',
        'to'   => $cleanPhone,
        'type' => 'template',
        'template' => [
            'name'     => 'invitacion_aceptada',
            'language' => ['code' => 'es_AR'],
            'components' => [[
                'type'       => 'body',
                'parameters' => [
                    ['type' => 'text', 'parameter_name' => 'nombre_usuario',    'text' => $userName],
                    ['type' => 'text', 'parameter_name' => 'nombre_invitado',   'text' => 'Juan Invitado'],
                    ['type' => 'text', 'parameter_name' => 'email_invitado',    'text' => 'juan@test.com'],
                    ['type' => 'text', 'parameter_name' => 'nombre_inventario', 'text' => 'Inventario Demo'],
                    ['type' => 'text', 'parameter_name' => 'rol_invitado',      'text' => 'Empleado'],
                ],
            ]],
        ],
    ];
    $results['invitacion_aceptada'] = _callWhatsAppRaw($url, $headers, $payload4);

    // ---- Test 5: cierre_semanal ----
    // Variables: {{inventario_nombre}}, {{nombre_usuario}}, {{ventas_totales}}, {{gastos_totales}}, {{balance}}
    $payload5 = [
        'messaging_product' => 'whatsapp',
        'to'   => $cleanPhone,
        'type' => 'template',
        'template' => [
            'name'     => 'cierre_semanal',
            'language' => ['code' => 'es_AR'],
            'components' => [[
                'type'       => 'body',
                'parameters' => [
                    ['type' => 'text', 'parameter_name' => 'inventario_nombre', 'text' => 'Inventario Demo'],
                    ['type' => 'text', 'parameter_name' => 'nombre_usuario',    'text' => $userName],
                    ['type' => 'text', 'parameter_name' => 'ventas_totales',    'text' => '$250.000,00'],
                    ['type' => 'text', 'parameter_name' => 'gastos_totales',    'text' => '$90.000,00'],
                    ['type' => 'text', 'parameter_name' => 'balance',           'text' => '$160.000,00'],
                ],
            ]],
        ],
    ];
    $results['cierre_semanal'] = _callWhatsAppRaw($url, $headers, $payload5);

    echo json_encode([
        'success'      => true,
        'phone_used'   => $cleanPhone,
        'phone_number_id' => WHATSAPP_PHONE_NUMBER_ID,
        'token_loaded' => !empty(WHATSAPP_ACCESS_TOKEN) ? 'SÍ (' . strlen(WHATSAPP_ACCESS_TOKEN) . ' chars)' : 'NO',
        'results'      => $results,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Realiza una llamada directa a la API de WhatsApp y devuelve la respuesta RAW.
 */
function _callWhatsAppRaw(string $url, array $headers, array $payload): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    return [
        'http_code'      => $httpCode,
        'curl_error'     => $curlErr ?: null,
        'raw_response'   => json_decode($response, true) ?? $response,
        'payload_sent'   => $payload,
    ];
}
