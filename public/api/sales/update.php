<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../auth_helper.php';

$user = getCurrentUser();
$user_id = $_SESSION['user_id'];

use App\core\Database;

function execConsult($query, $params = []) {
    $conn = Database::getInstance();
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
$data = json_decode(file_get_contents('php://input'), true);

$listaFechas = $data['dates'] ?? [];
$inv = $data['tableID'];

$stockVendidoGeneral = [];
$stockIngresadoGeneral = [];
$ingresosBrutosGeneral = [];
$promedioVentaGeneral = [];
$gastosGeneral = [];
$gananciaGeneral = [];

$stockVendidoTabla = [];
$stockIngresadoTabla = [];
$ingresosBrutosTabla = [];
$promedioVentaTabla = [];
$gastosTabla = [];
$gananciaTabla = [];

foreach ($listaFechas as $fechaSQL) {
    $stockVendido = execConsult(
        "SELECT COALESCE(SUM(si.quantity), 0) AS total
             FROM sale_items si
             JOIN sales s ON si.sale_id = s.id
             WHERE s.user_id = ? AND DATE(s.sale_date) = ?",
        [$user_id, $fechaSQL]
    );

    $stockIngresado = execConsult(
        "SELECT COALESCE(SUM(ri.quantity), 0) AS total
             FROM receipt_items ri
             JOIN receipts r ON ri.receipt_id = r.id
             WHERE r.user_id = ? AND DATE(r.sale_date) = ?",
        [$user_id, $fechaSQL]
    );

    $ingresosBrutos = execConsult(
        "SELECT COALESCE(SUM(s.total_amount), 0) AS total
             FROM sales s
             WHERE s.user_id = ? AND DATE(s.sale_date) = ?",
        [$user_id, $fechaSQL]
    );

    $promedioVenta = execConsult(
        "SELECT COALESCE(AVG(s.total_amount), 0) AS total
             FROM sales s
             WHERE s.user_id = ? AND DATE(s.sale_date) = ?",
        [$user_id, $fechaSQL]
    );

    $gastos = execConsult(
        "SELECT COALESCE(SUM(r.total_ammount), 0) AS total
             FROM receipts r
             WHERE r.user_id = ? AND DATE(r.sale_date) = ?",
        [$user_id, $fechaSQL]
    );

    $stockVendidoVal = (int)($stockVendido[0]['total'] ?? 0);
    $stockIngresadoVal = (int)($stockIngresado[0]['total'] ?? 0);
    $ingresosBrutosVal = (float)($ingresosBrutos[0]['total'] ?? 0.0);
    $promedioVentaVal = (float)($promedioVenta[0]['total'] ?? 0.0);
    $gastosVal = (float)($gastos[0]['total'] ?? 0.0);
    $gananciaVal = $ingresosBrutosVal - $gastosVal;

    $stockVendidoGeneral[] = $stockVendidoVal;
    $stockIngresadoGeneral[] = $stockIngresadoVal;
    $ingresosBrutosGeneral[] = $ingresosBrutosVal;
    $promedioVentaGeneral[] = $promedioVentaVal;
    $gastosGeneral[] = $gastosVal;
    $gananciaGeneral[] = $gananciaVal;

    // ventas por inventario
    $stockVendidoT = execConsult(
        "SELECT COALESCE(SUM(si.quantity), 0) AS total
             FROM sale_items si
             JOIN sales s ON si.sale_id = s.id
             WHERE si.inventory_id = ? AND DATE(s.sale_date) = ?",
        [$inv, $fechaSQL]
    );

    $ingresosBrutosT = execConsult(
        "SELECT COALESCE(SUM(si.quantity * COALESCE(si.unit_price,0)), 0) AS total
             FROM sale_items si
             JOIN sales s ON si.sale_id = s.id
             WHERE si.inventory_id = ? AND DATE(s.sale_date) = ?",
        [$inv, $fechaSQL]
    );

    $promedioVentaT = execConsult(
        "SELECT COALESCE(AVG(si.quantity * COALESCE(si.unit_price,0)), 0) AS total
             FROM sale_items si
             JOIN sales s ON si.sale_id = s.id
             WHERE si.inventory_id = ? AND DATE(s.sale_date) = ?",
        [$inv, $fechaSQL]
    );

    $stockIngresadoT = execConsult(
        "SELECT COALESCE(SUM(ri.quantity), 0) AS total
             FROM receipt_items ri
             JOIN receipts r ON ri.receipt_id = r.id
             WHERE ri.inventory_id = ? AND DATE(r.sale_date) = ?",
        [$inv, $fechaSQL]
    );

    $gastosT = execConsult(
        "SELECT COALESCE(SUM(ri.quantity * COALESCE(ri.unit_price,0)), 0) AS total
             FROM receipt_items ri
             JOIN receipts r ON ri.receipt_id = r.id
             WHERE ri.inventory_id = ? AND DATE(r.sale_date) = ?",
        [$inv, $fechaSQL]
    );

    $stockVendidoValT = (int)($stockVendidoT[0]['total'] ?? 0);
    $stockIngresadoValT = (int)($stockIngresadoT[0]['total'] ?? 0);
    $ingresosBrutosValT = (float)($ingresosBrutosT[0]['total'] ?? 0.0);
    $promedioVentaValT = (float)($promedioVentaT[0]['total'] ?? 0.0);
    $gastosValT = (float)($gastosT[0]['total'] ?? 0.0);
    $gananciaValT = $ingresosBrutosValT - $gastosValT;

    $stockVendidoTabla[] = $stockVendidoValT;
    $stockIngresadoTabla[] = $stockIngresadoValT;
    $ingresosBrutosTabla[] = $ingresosBrutosValT;
    $promedioVentaTabla[] = $promedioVentaValT;
    $gastosTabla[] = $gastosValT;
    $gananciaTabla[] = $gananciaValT;
}

$response = [
    'stockVendidoGeneral' => $stockVendidoGeneral,
    'stockIngresadoGeneral' => $stockIngresadoGeneral,
    'ingresosBrutosGeneral' => $ingresosBrutosGeneral,
    'gastosGeneral' => $gastosGeneral,
    'gananciaGeneral' => $gananciaGeneral,
    'promedioVentaGeneral' => $promedioVentaGeneral,

    'stockVendidoTable' => $stockVendidoTabla,
    'stockIngresadoTable' => $stockIngresadoTabla,
    'ingresosBrutosTable' => $ingresosBrutosTabla,
    'gastosTable' => $gastosTabla,
    'gananciaTable' => $gananciaTabla,
    'promedioVentaTable' => $promedioVentaTabla,
];

header('Content-Type: application/json');
echo json_encode($response, JSON_NUMERIC_CHECK);

} catch (\PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
}
