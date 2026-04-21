<?php
declare(strict_types=1);

namespace App\Services;

require_once __DIR__ . '/../config/whatsapp_config.php';

class WhatsappService
{
    private string $graphApiVersion = 'v20.0'; // Updated to Meta's v20.0
    private string $baseUrl = 'https://graph.facebook.com';
    public string $lastError = '';

    /**
     * Envía una plantilla (Template) de WhatsApp.
     * 
     * @param string $toPhoneNumber Número destino (con código de país ej: 549112345678)
     * @param string $templateName Nombre exacto de la plantilla aprobada
     * @param array $parameters Array numérico de strings con los valores para {{1}}, {{2}}, etc.
     * @return bool True si la llamada a la API responde con éxito
     */
    public function sendTemplateMessage(string $toPhoneNumber, string $templateName, array $parameters = []): bool
    {
        if (empty(WHATSAPP_PHONE_NUMBER_ID) || empty(WHATSAPP_ACCESS_TOKEN)) {
            $this->lastError = "Faltan credenciales empresariales. Verifica el archivo .env, actualmente ID: " . (WHATSAPP_PHONE_NUMBER_ID ?: 'VACIO') . " TOKEN: " . (WHATSAPP_ACCESS_TOKEN ? 'CARGADO' : 'VACIO');
            error_log("WhatsappService: Faltan credenciales en el archivo config/whatsapp_config.php");
            return false;
        }

        // Limpiar formato de teléfono (sólo números)
        $cleanPhone = preg_replace('/[^0-9]/', '', $toPhoneNumber);

        $url = "{$this->baseUrl}/{$this->graphApiVersion}/" . WHATSAPP_PHONE_NUMBER_ID . "/messages";

        // Mapear los parámetros simples de PHP a la estructura compleja de componentes que exige JSON de Meta
        $components = [];
        if (!empty($parameters)) {
            $bodyParams = [];
            foreach ($parameters as $param) {
                $bodyParams[] = [
                    'type' => 'text',
                    'text' => (string)$param
                ];
            }
            $components = [
                [
                    'type' => 'body',
                    'parameters' => $bodyParams
                ]
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $cleanPhone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => 'es_AR'],
            ]
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        $headers = [
            'Authorization: Bearer ' . WHATSAPP_ACCESS_TOKEN,
            'Content-Type: application/json'
        ];

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $this->lastError = "CURL Error: " . $error;
                error_log("WhatsappService CURL Error: " . $error);
                return false;
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                return true;
            }

            $this->lastError = "HTTP {$httpCode}: " . $response;
            error_log("WhatsappService Respuesta fallida HTTP {$httpCode}: " . $response);
            return false;

        } catch (\Throwable $e) {
            $this->lastError = "Exception: " . $e->getMessage();
            error_log("WhatsappService error de ejecución: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envia alerta de stock critico vía WhatsApp 
     * Nota: Ahora incluye la variable para el Inventario como 5to parametro
     */
    public function sendLowStockAlert(string $toPhoneNumber, string $userName, string $productName, float $currentStock, float $minStock, string $inventoryName = 'Principal'): bool
    {
        // En base a comentarios del User: {{1}} Usuario, {{2}} Producto, {{3}} Stock Actual, {{4}} Min Stock, {{5}} Inventario
        $params = [
            $userName,
            $productName,
            (string)$currentStock,
            (string)$minStock,
            $inventoryName
        ];
        return $this->sendTemplateMessage($toPhoneNumber, 'alerta_stock_critico', $params);
    }

    /**
     * Envia alerta balance diario de caja vía WhatsApp
     * Nota: Ahora incluye el inventario o similar si aplica (asumo inventario como 6to parametro o ajustamos)
     * Como el usuario dijo que ambas plantillas tienen esta info de inventario, sumaremos 1 variable extra
     */
    public function sendDailyBalance(string $toPhoneNumber, string $userName, string $date, float $totalSales, float $totalPurchases, float $balance, string $inventoryName = 'General'): bool
    {
        // En base a la plantilla original: {{1}} Usuario, {{2}} Fecha, {{3}} Ventas, {{4}} Compras, {{5}} Balance, {{6}} Inventario
        $params = [
            $userName,
            $date,
            number_format($totalSales, 2, ',', '.'),
            number_format($totalPurchases, 2, ',', '.'),
            number_format($balance, 2, ',', '.'),
            $inventoryName
        ];
        return $this->sendTemplateMessage($toPhoneNumber, 'reporte_cierre_caja', $params);
    }

    /**
     * Envía reporte masivo de reposición vía WhatsApp 
     */
    public function sendRestockReport(string $toPhoneNumber, string $userName, string $inventoryName, array $productsList): bool
    {
        $productsString = "";
        foreach ($productsList as $product) {
            $name = $product['name'] ?? 'Producto';
            $faltante = $product['faltante'] ?? 0;
            $productsString .= "• {$name}: faltan {$faltante}\n";
        }
        $productsString = trim($productsString);

        $params = [
            $userName,
            $inventoryName,
            $productsString
        ];
        return $this->sendTemplateMessage($toPhoneNumber, 'reporte_reposicion', $params);
    }
}
