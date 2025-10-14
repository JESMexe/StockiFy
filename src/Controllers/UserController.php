<?php
namespace App\Controllers;

use App\Models\UserModel;

class UserController
{
    /**
     * Obtiene y devuelve el perfil del usuario actualmente logueado.
     */
    public function getProfile(): void
    {
        header('Content-Type: application/json');

        $user = getCurrentUser();

        if (!$user) {
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
            return;
        }

        // Aquí es donde también consultarías las bases de datos del usuario
        // Por ahora, lo dejamos pendiente para el siguiente paso.
        $databases = []; // Simulación

        // Devolvemos una respuesta exitosa con los datos del usuario
        echo json_encode([
            'success' => true,
            'user' => $user,
            'databases' => $databases
        ]);
    }
}