<?php
// Script de prueba que hace POST a api/sales/send-email.php con JSON de ejemplo.
// Ajusta $url y el destinatario antes de usar.

$url = 'http://localhost/api/sales/send-email.php'; // Cambia si tu app está en otra ruta

$sample = [
    'emailInfo' => [
        'to' => 'tu.email@ejemplo.com',
        'subject' => 'Factura de prueba - StockiFy',
        'from' => 'no-reply@stockify.local',
        'fromName' => 'StockiFy - Test',
        'sale' => [
            'id' => 999,
            'date' => date('Y-m-d H:i:s'),
            'customer' => 'Cliente de Prueba',
            'items' => [
                ['name' => 'Producto A', 'quantity' => 2, 'unit_price' => 10, 'total' => 20],
                ['name' => 'Producto B', 'quantity' => 1, 'unit_price' => 15.5, 'total' => 15.5]
            ],
            'total' => 35.5
        ]
    ],
    'testMode' => true
];

$payload = json_encode($sample);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

$response = curl_exec($ch);
$err = curl_error($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    echo "cURL error: $err\n";
    exit(1);
}

echo "HTTP: $http\n";
echo "Response:\n";
echo $response . "\n";
