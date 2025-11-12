<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../auth_helper.php';

use App\core\Database;

$data = json_decode(file_get_contents('php://input'), true);

//ESTAS 4 ESTADISTICAS SON INDEPENDIENTES DE LA TABLA SELECCIONADA YA QUE NO INVOLUCRAN TABLAS
try{
    $pdo = Database::getInstance();
    $user = getCurrentUser();
    $user_id = $_SESSION['user_id'];

    $data = json_decode(file_get_contents('php://input'), true);
    date_default_timezone_set("America/Argentina/Buenos_Aires");

    $tableID = $data['tableID'];
    $hour = date('H');

    $ventasRealizadas = [];
    $comprasRealizadas = [];
    $nuevosClientes = [];
    $nuevosProveedores = [];
    $stockIngresado = [];
    $stockVendido = [];
    $gastos = [];
    $ingresos = [];
    $ganancias = [];

    for ($counter = 0; $counter <= $hour; $counter++) {
        $ventasRealizadasSQL = $pdo->prepare("SELECT COUNT(*) AS total FROM sales WHERE user_id = ? AND DATE(sale_date) = CURDATE() AND HOUR(sale_date) = ?");
        $ventasRealizadasSQL->execute([$user_id,$counter]);
        $ventasRealizadasSQL = $ventasRealizadasSQL->fetchColumn();
        $ventasRealizadas[] = (int)$ventasRealizadasSQL;
        $comprasRealizadasSQL = $pdo->prepare("SELECT COUNT(*) AS total FROM receipts WHERE user_id = ? AND DATE(receipt_date) = CURDATE() AND HOUR(receipt_date) = ?");
        $comprasRealizadasSQL->execute([$user_id,$counter]);
        $comprasRealizadasSQL = $comprasRealizadasSQL->fetchColumn();
        $comprasRealizadas[] = (int)$comprasRealizadasSQL;
        $nuevosClientesSQL = $pdo->prepare("SELECT COUNT(*) AS total FROM customers WHERE user_id = ? AND DATE(created_at) = CURDATE() AND HOUR(created_at) = ?");
        $nuevosClientesSQL->execute([$user_id,$counter]);
        $nuevosClientesSQL = $nuevosClientesSQL->fetchColumn();
        $nuevosClientes[] = (int)$nuevosClientesSQL;
        $nuevosProveedoresSQL = $pdo->prepare("SELECT COUNT(*) AS total FROM providers WHERE user_id = ? AND DATE(created_at) = CURDATE() AND HOUR(created_at) = ?");
        $nuevosProveedoresSQL->execute([$user_id,$counter]);
        $nuevosProveedoresSQL = $nuevosProveedoresSQL->fetchColumn();
        $nuevosProveedores[] = (int)$nuevosProveedoresSQL;

        //ESTAS ESTADISTICAS DEBEN SER FILTRADAS POR LA TABLA SELECCIONADA (O TODAS)
        if ($tableID === 'all'){
            //CONSULTAS PARA TODAS LAS TABLAS
            $stockIngresadoSQL = $pdo->prepare("SELECT COALESCE(SUM(ri.quantity)) AS total FROM receipt_items ri JOIN receipts r ON ri.receipt_id = r.id WHERE r.user_id = ? AND DATE(r.receipt_date) = CURDATE() AND HOUR(r.receipt_date) = ?");
            $stockIngresadoSQL->execute([$user_id,$counter]);
            $stockIngresadoSQL = $stockIngresadoSQL->fetchColumn();
            $stockIngresado[] = (int)$stockIngresadoSQL;
            $stockVendidoSQL = $pdo->prepare("SELECT COALESCE(SUM(si.quantity)) AS total FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE s.user_id = ? AND DATE(s.sale_date) = CURDATE() AND HOUR(s.sale_date) = ?");
            $stockVendidoSQL->execute([$user_id,$counter]);
            $stockVendidoSQL = $stockVendidoSQL->fetchColumn();
            $stockVendido[] = (int)$stockVendidoSQL;
            $gastosSQL = $pdo->prepare("SELECT COALESCE(SUM(r.total_amount)) AS total FROM receipts r WHERE r.user_id = ? AND DATE(r.receipt_date) = CURDATE() AND HOUR(r.receipt_date) = ?");
            $gastosSQL->execute([$user_id,$counter]);
            $gastosSQL = $gastosSQL->fetchColumn();
            $gastos[] = (float)$gastosSQL;
            $ingresosSQL = $pdo->prepare("SELECT COALESCE(SUM(s.total_amount)) AS total FROM sales s WHERE s.user_id = ? AND DATE(s.sale_date) = CURDATE() AND HOUR(s.sale_date) = ?");
            $ingresosSQL->execute([$user_id,$counter]);
            $ingresosSQL = $ingresosSQL->fetchColumn();
            $ingresos[] = (float)$ingresosSQL;
            $ganancias[] = (float)$ingresosSQL - (float)$gastosSQL;
        }
        else{
            //CONSULTAS PARA UNA TABLA ESPECIFICA
            $stockIngresadoSQL = $pdo->prepare("SELECT COALESCE(SUM(ri.quantity)) AS total FROM receipt_items ri JOIN receipts r ON ri.receipt_id = r.id WHERE ri.inventory_id = ? AND DATE(r.receipt_date) = CURDATE() AND HOUR(r.receipt_date) = ?");
            $stockIngresadoSQL->execute([$tableID,$counter]);
            $stockIngresadoSQL = $stockIngresadoSQL->fetchColumn();
            $stockIngresado[] = (int)$stockIngresadoSQL;
            $stockVendidoSQL = $pdo->prepare("SELECT COALESCE(SUM(si.quantity)) AS total FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE si.inventory_id = ? AND DATE(s.sale_date) = CURDATE() AND HOUR(s.sale_date) = ?");
            $stockVendidoSQL->execute([$tableID,$counter]);
            $stockVendidoSQL = $stockVendidoSQL->fetchColumn();
            $stockVendido[] = (int)$stockVendidoSQL;
            $gastosSQL = $pdo->prepare("SELECT COALESCE(SUM(ri.total_price)) AS total FROM receipt_items ri JOIN receipts r ON ri.receipt_id = r.id WHERE ri.inventory_id = ? AND DATE(r.receipt_date) = CURDATE() AND HOUR(r.receipt_date) = ?");
            $gastosSQL->execute([$tableID,$counter]);
            $gastosSQL = $gastosSQL->fetchColumn();
            $gastos[] = (float)$gastosSQL;
            $ingresosSQL = $pdo->prepare("SELECT COALESCE(SUM(si.total_price)) AS total FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE si.inventory_id = ? AND DATE(s.sale_date) = CURDATE() AND HOUR(s.sale_date) = ?");
            $ingresosSQL->execute([$tableID,$counter]);
            $ingresosSQL = $ingresosSQL->fetchColumn();
            $ingresos[] = (float)$ingresosSQL;
            $ganancias[] = (float)$ingresosSQL - (float)$gastosSQL;
        }
    }

    $transacciones = [
        'ventasRealizadas' => $ventasRealizadas,
        'comprasRealizadas' => $comprasRealizadas];

    $conexiones= [
        'nuevosClientes' => $nuevosClientes,
        'nuevosProveedores' => $nuevosProveedores];

    $stock = [
        'stockIngresado' => $stockIngresado,
        'stockVendido' => $stockVendido];

    $monetarias = [
        'gastos' => $gastos,
        'ingresos' => $ingresos,
        'ganancias' => $ganancias
    ];

    $response = ['transacciones' => $transacciones, 'conexiones' => $conexiones, 'stock' => $stock, 'monetarias' => $monetarias];

    header('Content-Type: application/json');
    echo json_encode($response, JSON_NUMERIC_CHECK);
}
catch (Exception $e) {
    echo json_encode(['error' => 'An error occurred: ' . $e->getMessage()]);
}

