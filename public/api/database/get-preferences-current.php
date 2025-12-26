<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';

use App\core\Database;

$user = getCurrentUser();
if (!$user) { echo json_encode(['success'=>false]); exit; }

// Obtener inventario activo
// (Asumimos lógica de "último activo" o "único")
// Aquí simplifico buscando el último creado o el seleccionado en sesión si existiera
$db = Database::getInstance();
$stmt = $db->prepare("SELECT id, preferences, min_stock, hard_gain FROM inventories WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user['id']]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);

if ($inv) {
    // Decodificar JSON
    $prefs = json_decode($inv['preferences'], true) ?? [];

    // Estructura por defecto si está vacío
    $response = [
        'success' => true,
        'mapping' => $prefs['mapping'] ?? [
                'name' => null,
                'stock' => null,
                'sale_price' => null,
                'buy_price' => null
            ],
        'features' => $prefs['features'] ?? [
                'min_stock' => (bool)$inv['min_stock'], // Mantener compatibilidad con dato viejo
                'gain' => (bool)$inv['hard_gain'],      // Mantener compatibilidad
                'min_stock_val' => 5,
                'gain_type' => 'percent'
            ]
    ];
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => 'No hay inventario']);
}
?>