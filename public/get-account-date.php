<?php
$json = file_get_contents('php://input');
$data = json_decode($json, true);

    //CONSULTA A LA BASE DE DATOS UTILIZANDO EL ID

    $databaseDate = "2024-1-12 12:30:00";

    $timestamp = strtotime($databaseDate);
    $date = date("Y-m-d", $timestamp);
    $response = ['creationDate' => $date,'success' => true];

header('Content-Type: application/json');
echo json_encode($response);
