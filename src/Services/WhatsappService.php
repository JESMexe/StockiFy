<?php
declare(strict_types=1);

namespace App\Services;

require_once __DIR__ . '/../config/whatsapp_config.php';

class WhatsappService
{
    private string $graphApiVersion = 'v20.0';
    private string $baseUrl         = 'https://graph.facebook.com';
    public  string $lastError       = '';

    /**
     * Envía una plantilla (Template) de WhatsApp.
     *
     * Soporta dos formatos de $parameters:
     *   - Array asociativo ['nombre_var' => 'valor'] → variables NOMBRADAS (plantillas modernas de Meta)
     *   - Array numérico  ['valor1', 'valor2']       → variables POSICIONALES {{1}}, {{2}} (plantillas antiguas)
     *
     * Meta exige `parameter_name` cuando la plantilla usa variables con nombre (ej: {{nombre_usuario}}).
     * Si se omite, devuelve HTTP 400 "Parameter name is missing or empty".
     *
     * @param string $toPhoneNumber  Número destino con código de país (ej: 5491112345678)
     * @param string $templateName   Nombre exacto de la plantilla aprobada en Meta
     * @param array  $parameters     Asociativo (nombrado) o numérico (posicional)
     * @param string $languageCode   Código de idioma de la plantilla (default: es_AR)
     * @return bool
     */
    public function sendTemplateMessage(
        string $toPhoneNumber,
        string $templateName,
        array $parameters = [],
        string $languageCode = 'es_AR'
    ): bool {
        if (empty(WHATSAPP_PHONE_NUMBER_ID) || empty(WHATSAPP_ACCESS_TOKEN)) {
            $this->lastError = 'Faltan credenciales. ID: ' . (WHATSAPP_PHONE_NUMBER_ID ?: 'VACÍO')
                             . ' | TOKEN: ' . (WHATSAPP_ACCESS_TOKEN ? 'CARGADO' : 'VACÍO');
            error_log('WhatsappService: credenciales faltantes en whatsapp_config.php');
            return false;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $toPhoneNumber);

        $url     = "{$this->baseUrl}/{$this->graphApiVersion}/" . WHATSAPP_PHONE_NUMBER_ID . '/messages';
        $headers = [
            'Authorization: Bearer ' . WHATSAPP_ACCESS_TOKEN,
            'Content-Type: application/json',
        ];

        // ── Construir componentes ──────────────────────────────────────────────
        $components = [];
        if (!empty($parameters)) {
            $bodyParams = [];

            // Detectar si el array es asociativo (keys string) → variables nombradas
            $isNamed = array_keys($parameters) !== range(0, count($parameters) - 1);

            foreach ($parameters as $key => $value) {
                $param = [
                    'type' => 'text',
                    'text' => (string) $value,
                ];
                if ($isNamed) {
                    // Meta requiere este campo para plantillas con variables nombradas
                    $param['parameter_name'] = (string) $key;
                }
                $bodyParams[] = $param;
            }

            $components = [[
                'type'       => 'body',
                'parameters' => $bodyParams,
            ]];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $cleanPhone,
            'type'              => 'template',
            'template'          => [
                'name'     => $templateName,
                'language' => ['code' => $languageCode],
            ],
        ];
        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        // ── Llamada HTTP ───────────────────────────────────────────────────────
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST,          true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,    json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER,    $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
            curl_setopt($ch, CURLOPT_TIMEOUT,       15);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                $this->lastError = "CURL Error: {$curlErr}";
                error_log("WhatsappService CURL Error: {$curlErr}");
                return false;
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                return true;
            }

            // Extraer mensaje de error legible de Meta
            $decoded    = json_decode($response, true);
            $metaMsg    = $decoded['error']['message']         ?? '';
            $metaDetail = $decoded['error']['error_data']['details'] ?? '';
            $this->lastError = "HTTP {$httpCode} | {$metaMsg}" . ($metaDetail ? " | {$metaDetail}" : '');
            error_log("WhatsappService HTTP {$httpCode}: {$response}");
            return false;

        } catch (\Throwable $e) {
            $this->lastError = 'Exception: ' . $e->getMessage();
            error_log('WhatsappService excepción: ' . $e->getMessage());
            return false;
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PLANTILLAS ESPECÍFICAS
    // Los nombres de las variables deben coincidir EXACTAMENTE con los definidos
    // en Meta Business Manager → Administrador de WhatsApp → Plantillas.
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Alerta de Stock Crítico
     *
     * Plantilla: alerta_stock_critico (es_AR)
     * Variables: {{nombre_usuario}}, {{nombre_inventario}}, {{nombre_producto}},
     *            {{producto_id}}, {{stock_actual}}, {{stock_minimo}}
     */
    public function sendLowStockAlert(
        string $toPhoneNumber,
        string $userName,
        string $productName,
        float  $currentStock,
        float  $minStock,
        string $inventoryName = 'Principal',
               $productId     = '-'
    ): bool {
        $params = [
            'nombre_usuario'    => $userName,
            'nombre_inventario' => $inventoryName,
            'nombre_producto'   => $productName,
            'producto_id'       => (string) $productId,
            'stock_actual'      => (string) $currentStock,
            'stock_minimo'      => (string) $minStock,
        ];
        return $this->sendTemplateMessage($toPhoneNumber, 'alerta_stock_critico', $params);
    }

    /**
     * Reporte de Cierre de Caja Diario
     *
     * Plantilla: reporte_cierre_caja (es_AR)
     * Variables: {{inventario_nombre}}, {{nombre_usuario}}, {{fecha}},
     *            {{ventas_totales}}, {{gastos_totales}}, {{balance}}
     */
    public function sendDailyBalance(
        string $toPhoneNumber,
        string $userName,
        string $date,
        float  $totalSales,
        float  $totalPurchases,
        float  $balance,
        string $inventoryName = 'General'
    ): bool {
        $params = [
            'inventario_nombre' => $inventoryName,
            'nombre_usuario'    => $userName,
            'fecha'             => $date,
            'ventas_totales'    => number_format($totalSales,     2, ',', '.'),
            'gastos_totales'    => number_format($totalPurchases, 2, ',', '.'),
            'balance'           => number_format($balance,        2, ',', '.'),
        ];
        return $this->sendTemplateMessage($toPhoneNumber, 'reporte_cierre_caja', $params);
    }

    /**
     * Reporte masivo de reposición de stock
     *
     * Plantilla: reporte_reposicion (es_AR)
     * Variables: ajustar nombres según la plantilla creada en Meta.
     */
    public function sendRestockReport(
        string $toPhoneNumber,
        string $userName,
        string $inventoryName,
        array  $productsList
    ): bool {
        $lines = [];
        foreach ($productsList as $product) {
            $name     = $product['name']    ?? 'Producto';
            $faltante = $product['faltante'] ?? 0;
            $lines[]  = "• {$name}: faltan {$faltante}";
        }
        $productsString = implode("\n", $lines);

        $params = [
            'nombre'            => $userName,
            'inventario_nombre' => $inventoryName,
            'productos'         => $productsString,
        ];
        return $this->sendTemplateMessage($toPhoneNumber, 'reporte_reposicion', $params);
    }

    /**
     * Alerta de Producto Agotado (Stock 0)
     *
     * Plantilla: producto_agotado (es_AR)
     * Variables: {{nombre_usuario}}, {{nombre_inventario}}, {{nombre_producto}}, {{producto_id}}
     */
    public function sendOutOfStockAlert(
        string $toPhoneNumber,
        string $userName,
        string $productName,
        string $inventoryName = 'Principal',
        string $productId = '-'
    ): bool {
        $params = [
            'nombre_usuario'    => $userName,
            'nombre_inventario' => $inventoryName,
            'nombre_producto'   => $productName,
            'producto_id'       => $productId,
        ];
        return $this->sendTemplateMessage($toPhoneNumber, 'producto_agotado', $params);
    }

    /**
     * Alerta de Nuevo Colaborador Agregado / Invitación Aceptada
     *
     * Plantilla: invitacion_aceptada (es_AR)
     * Variables: {{nombre_usuario}}, {{nombre_invitado}}, {{email_invitado}}, {{nombre_inventario}}, {{rol_invitado}}
     */
    public function sendNewCollaboratorAlert(
        string $toPhoneNumber,
        string $userName,
        string $collaboratorName,
        string $collaboratorEmail,
        string $inventoryName,
        string $collaboratorRole
    ): bool {
        $params = [
            'nombre_usuario'    => $userName,
            'nombre_invitado'   => $collaboratorName,
            'email_invitado'    => $collaboratorEmail,
            'nombre_inventario' => $inventoryName,
            'rol_invitado'      => $collaboratorRole,
        ];
        return $this->sendTemplateMessage($toPhoneNumber, 'invitacion_aceptada', $params);
    }

    /**
     * Reporte de Cierre de Caja Semanal
     *
     * Plantilla: cierre_semanal (es_AR)
     * Variables: {{inventario_nombre}}, {{nombre_usuario}}, {{ventas_totales}}, {{gastos_totales}}, {{balance}}
     */
    public function sendWeeklyBalance(
        string $toPhoneNumber,
        string $userName,
        float  $totalSales,
        float  $totalPurchases,
        float  $balance,
        string $inventoryName = 'General'
    ): bool {
        $params = [
            'inventario_nombre' => $inventoryName,
            'nombre_usuario'    => $userName,
            'ventas_totales'    => number_format($totalSales,     2, ',', '.'),
            'gastos_totales'    => number_format($totalPurchases, 2, ',', '.'),
            'balance'           => number_format($balance,        2, ',', '.'),
        ];
        return $this->sendTemplateMessage($toPhoneNumber, 'cierre_semanal', $params);
    }
}
