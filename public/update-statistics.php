<?php
$json = file_get_contents('php://input');
$data = json_decode($json, true);


$listaFechas = $data['fechas'];

$stockVendidoGeneral = [];
$stockIngresadoGeneral = [];
$ingresosBrutosGeneral = [];
$promedioVentaGeneral = [];
$gastosGeneral = [];
$gananciaGeneral = [];
$stockIngresadoTabla = [];
$ingresosBrutosTabla = [];
$stockVendidoTabla = [];
$promedioVentaTabla= [];
$gastosTabla = [];
$gananciaTabla = [];

$count = 0;

foreach ($listaFechas as $fecha) {
    $promedioVentaGeneral[$count] = 1;
    $ingresosBrutosGeneral[$count] = 1;
    $stockVendidoGeneral[$count] = 1;
    $stockIngresadoGeneral[$count] = 1;
    $gastosGeneral[$count] = 1;
    $gananciaGeneral[$count] = 1;
    $stockIngresadoTabla[$count] = 1;
    $ingresosBrutosTabla[$count] = 1;
    $stockVendidoTabla[$count] = 1;
    $promedioVentaTabla[$count] = 1;
    $gastosTabla[$count] = 1;
    $gananciaTabla[$count] = 1;
    $count++;
}
$response = ['stockVendidoGeneral' => $stockVendidoGeneral, 'stockIngresadoGeneral' => $stockIngresadoGeneral, 'ingresosBrutosGeneral' => $ingresosBrutosGeneral,
    'gastosGeneral' => $gastosGeneral, 'gananciaGeneral' => $gananciaGeneral, 'promedioVentaGeneral' => $promedioVentaGeneral,
    'stockVendidoTable' => $stockVendidoGeneral, 'stockIngresadoTable' => $stockIngresadoGeneral, 'ingresosBrutosTable' => $ingresosBrutosGeneral,
    'gastosTable' => $gastosGeneral, 'gananciaTable' => $gananciaGeneral, 'promedioVentaTable' => $promedioVentaGeneral,
    'stockVendidoProduct' => $stockVendidoGeneral, 'stockIngresadoProduct' => $stockIngresadoGeneral, 'ingresosBrutosProduct' => $ingresosBrutosGeneral,
    'gastosProduct' => $gastosGeneral, 'gananciaProduct' => $gananciaGeneral, 'promedioVentaProduct' => $promedioVentaGeneral];


header('Content-Type: application/json');
echo json_encode($response);