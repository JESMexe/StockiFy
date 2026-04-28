<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

echo json_encode([
    'success' => true,
    'fields' => [
        ['id' => 'name', 'label' => 'Nombre del Producto'],
        ['id' => 'sku', 'label' => 'SKU / Código'],
        ['id' => 'price', 'label' => 'Precio'],
        ['id' => 'stock', 'label' => 'Stock Actual'],
        ['id' => 'barcode', 'label' => 'Código de Barras'],
        ['id' => 'description', 'label' => 'Descripción'],
        ['id' => 'categories', 'label' => 'Categorías'],
        ['id' => 'variant_name', 'label' => 'Nombre de Variantes (Talle, Color, etc.)']
    ]
]);
