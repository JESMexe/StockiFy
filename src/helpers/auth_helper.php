<?php
// src/helpers/auth_helper.php

use App\Models\UserModel;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Obtiene los datos del usuario que tiene la sesión activa.
 *
 * @return array|null Devuelve un array con los datos del usuario si está logueado, o null si no.
 */

function getCurrentUser(): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $userModel = new UserModel();
    $user = $userModel->findById($_SESSION['user_id']);

    return $user ?: null;
}