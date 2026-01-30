<?php
namespace App\Services;

class ExchangeService {

    private string $apiUrl = 'https://dolarapi.com/v1/dolares/blue';
    private string $cacheFile;
    private int $cacheTime = 3600; // 1 hora en segundos

    public function __construct() {
        // Guardamos el caché en la carpeta temporal del sistema o en la misma carpeta
        $this->cacheFile = __DIR__ . '/exchange_cache.json';
    }

    public function getRates() {
        // 1. INTENTO LEER DE CACHÉ
        if (file_exists($this->cacheFile)) {
            $cacheContent = @file_get_contents($this->cacheFile);
            if ($cacheContent) {
                $cacheData = json_decode($cacheContent, true);

                // Verificamos si el caché sigue siendo válido (menor a 1 hora)
                if (isset($cacheData['timestamp']) && (time() - $cacheData['timestamp'] < $this->cacheTime)) {
                    // Retornamos caché y evitamos la llamada externa lenta
                    return $cacheData['data'];
                }
            }
        }

        // 2. SI NO HAY CACHÉ (O EXPIRÓ), CONSULTAMOS API EXTERNA
        $defaultRates = [
            'buy' => 1200,
            'sell' => 1240,
            'avg' => 1220,
            'updated' => date('Y-m-d H:i:s')
        ];

        try {
            $arrContextOptions = [
                "ssl" => ["verify_peer" => false, "verify_peer_name" => false],
                "http" => ["timeout" => 3] // Bajamos timeout a 3s para no colgar
            ];

            $json = @file_get_contents($this->apiUrl, false, stream_context_create($arrContextOptions));

            if ($json) {
                $data = json_decode($json, true);
                if(isset($data['compra']) && isset($data['venta'])) {

                    $rates = [
                        'buy' => $data['compra'],
                        'sell' => $data['venta'],
                        'avg' => ($data['compra'] + $data['venta']) / 2,
                        'updated' => $data['fechaActualizacion'] ?? date('Y-m-d H:i:s')
                    ];

                    // 3. GUARDAMOS EN CACHÉ PARA LA PRÓXIMA
                    @file_put_contents($this->cacheFile, json_encode([
                        'timestamp' => time(),
                        'data' => $rates
                    ]));

                    return $rates;
                }
            }
        } catch (\Exception $e) {
            error_log("ExchangeService Error: " . $e->getMessage());
        }

        // Si falló API y teníamos un caché viejo, mejor devolvemos el viejo que el default
        if (isset($cacheData) && isset($cacheData['data'])) {
            return $cacheData['data'];
        }

        return $defaultRates;
    }

    public function convert($amount, $from, $to, $rates) {
        if ($from === $to) return $amount;
        if ($from === 'ARS' && $to === 'USD') return ($rates['sell'] > 0) ? $amount / $rates['sell'] : $amount;
        if ($from === 'USD' && $to === 'ARS') return $amount * $rates['buy']; // O 'sell' si prefieres conservador
        return $amount;
    }
}