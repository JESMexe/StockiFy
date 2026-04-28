<?php
namespace App\Services;

use Exception;

class TiendaNubeService {
    private $accessToken;
    private $storeId;
    private $userAgent;

    public function __construct($accessToken, $storeId) {
        $this->accessToken = $accessToken;
        $this->storeId = $storeId;
        $this->userAgent = "StockiFy (no_reply@stockify.com.ar)";
    }

    public function getProducts() {
        $url = "https://api.tiendanube.com/v1/{$this->storeId}/products";
        $allProducts = [];
        $page = 1;
        $perPage = 200;

        try {
            do {
                $ch = curl_init("$url?page=$page&per_page=$perPage");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authentication: bearer {$this->accessToken}",
                    "User-Agent: {$this->userAgent}"
                ]);

                $response = curl_exec($ch);
                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($status !== 200) {
                    throw new Exception("TiendaNube API error: Status $status. Response: $response");
                }

                $products = json_decode($response, true);
                if (empty($products)) break;

                $allProducts = array_merge($allProducts, $products);
                $page++;

            } while (count($products) == $perPage);

            return $allProducts;
        } catch (Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }
}
