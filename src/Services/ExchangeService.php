<?php
namespace App\Services;

class ExchangeService {

    private $cacheTime = 3600; // 1 hora en segundos

    public function __construct() {
    }

    /**
     * Devuelve la cotización según la configuración del usuario actual.
     * Si falla la API externa y no hay manual, lanza excepción "API_DOWN" para forzar fallback en UI.
     *
     * @param array|null $config Configuración 'exchange_config' de DB
     */
    public function getContextualRate(?array $config = null, bool $forceRefresh = false) {
        $type = $config['type'] ?? 'api';
        $apiSource = $config['api_source'] ?? 'blue';
        $manualRate = floatval($config['manual_rate'] ?? 1200);

        // 1. MODO MANUAL: Prioridad absoluta
        if ($type === 'manual' && $manualRate > 0) {
            return [
                'buy' => $manualRate,
                'sell' => $manualRate,
                'avg' => $manualRate,
                'updated' => date('Y-m-d H:i:s'),
                'source' => 'manual'
            ];
        }

        // 2. MODO API: Buscar desde API Externa parametrizada
        $allowedSources = ['blue', 'oficial', 'bolsa', 'cripto', 'mayorista'];
        if (!in_array($apiSource, $allowedSources)) {
            $apiSource = 'blue';
        }

        $apiUrl = "https://dolarapi.com/v1/dolares/{$apiSource}";
        $cacheFile = __DIR__ . "/exchange_cache_{$apiSource}.json";

        // INTENTO LEER CACHÉ (Si no se fuerza recarga)
        if (!$forceRefresh && file_exists($cacheFile)) {
            $cacheContent = @file_get_contents($cacheFile);
            if ($cacheContent) {
                $cacheData = json_decode($cacheContent, true);
                if (isset($cacheData['timestamp']) && (time() - $cacheData['timestamp'] < $this->cacheTime)) {
                    $rate = $cacheData['data'];
                    // Removemos metadata extra que rompa algo
                    return [
                        'buy' => $rate['buy'],
                        'sell' => $rate['sell'],
                        'avg' => $rate['avg'],
                        'updated' => $rate['updated'],
                        'source' => 'api_cache'
                    ];
                }
            }
        }

        // INTENTO CONSULTAR API VIVA
        try {
            $arrContextOptions = [
                "ssl" => ["verify_peer" => false, "verify_peer_name" => false],
                "http" => ["timeout" => 4] // Bajamos timeout para no colgar
            ];

            $json = @file_get_contents($apiUrl, false, stream_context_create($arrContextOptions));

            if ($json) {
                $data = json_decode($json, true);
                if(isset($data['compra']) && isset($data['venta'])) {

                    $rates = [
                        'buy' => floatval($data['compra']),
                        'sell' => floatval($data['venta']),
                        'avg' => (floatval($data['compra']) + floatval($data['venta'])) / 2,
                        'updated' => $data['fechaActualizacion'] ?? date('Y-m-d H:i:s')
                    ];

                    // GUARDAMOS EN CACHÉ
                    @file_put_contents($cacheFile, json_encode([
                        'timestamp' => time(),
                        'data' => $rates
                    ]));
                    
                    $rates['source'] = 'api_live';

                    return $rates;
                }
            }
            throw new \Exception("La API no devolvió valores compra y venta.");
        } catch (\Exception $e) {
            error_log("ExchangeService Error: " . $e->getMessage());
            
            // Opción 3 de contingencia: Si falló API, no hay caché, y no hay manual rate escrito...
            // Rompemos a propósito con "API_DOWN" para que el frontend ataje esto y pida al usuario digitar.
            throw new \Exception("API_DOWN");
        }
    }

    public function convert($amount, $from, $to, $rates) {
        if ($from === $to) return $amount;
        if ($from === 'ARS' && $to === 'USD') return ($rates['sell'] > 0) ? $amount / $rates['sell'] : $amount;
        if ($from === 'USD' && $to === 'ARS') return $amount * $rates['buy'];
        return $amount;
    }
}