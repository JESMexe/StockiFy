<?php
namespace App\Controllers;

use App\Models\UserModel;

class AuthController
{
    public function login(array $postData): void {
        header('Content-Type: application/json');
        try {
            if (empty($postData['email']) || empty($postData['password'])) {
                echo json_encode(['success' => false, 'message' => 'El correo y la contraseña son obligatorios.']);
                return;
            }

            $userModel = new UserModel();
            $user = $userModel->findByEmail($postData['email']);

            if ($user && password_verify($postData['password'], $user['password_hash'])) {
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['full_name']
                    ?? $user['username']
                    ?? explode('@', $user['email'])[0]
                    ?? 'Usuario';
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Credenciales incorrectas.']);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
        }
    }


    /**
     * Maneja la lógica del registro de usuarios.
     * @param array $data Los datos decodificados del JSON enviado.
     */
    public function register(array $data): void
    {
        header('Content-Type: application/json');

        // Validacion simple
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Usuario, email y contraseña son obligatorios.']);
            return;
        }

        // Llamp al modelo para crear el usuario
        $userModel = new UserModel();
        $success = $userModel->createUser($data);

        // Devuelvo la respuesta
        if ($success) {
            echo json_encode(['success' => true, 'message' => '¡Usuario registrado con éxito!']);
        } else {
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => 'El email o nombre de usuario ya existe.']);
        }
    }
}