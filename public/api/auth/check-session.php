<?php

// public/api/auth/check-session.php

session_start();

// Le digo al navegador que la respuesta sera JSON
header('Content-Type: application/json');

// Verifico si la variable "user_id" existe dentro de la sesion.
// isset() es la forma segura de comprobar si una variable está definida.
if (isset($_SESSION['user_id'])) {
    echo json_encode(['isLoggedIn' => true]);
} else {
    echo json_encode(['isLoggedIn' => false]);
}