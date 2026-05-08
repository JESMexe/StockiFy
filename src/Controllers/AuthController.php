<?php
namespace App\Controllers;

use App\Models\UserModel;

class AuthController
{
    public function login(array $postData): void {
        header('Content-Type: application/json');
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $emailKey = $postData['email'] ?? 'unknown';
            $failKey = md5($ip . '_' . $emailKey);
            $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stockify_login_fails_' . $failKey . '.json';
            
            $fails = 0;
            if (file_exists($cacheFile)) {
                $cacheData = json_decode(file_get_contents($cacheFile), true);
                if ($cacheData && time() - $cacheData['time'] < 900) { // 15 minutos
                    $fails = $cacheData['count'];
                    if ($fails >= 5) {
                        echo json_encode(['success' => false, 'message' => 'Cuenta bloqueada temporalmente. Intente en 15 minutos.']);
                        return;
                    }
                }
            }

            if (empty($postData['email']) || empty($postData['password'])) {
                echo json_encode(['success' => false, 'message' => 'El correo y la contraseña son obligatorios.']);
                return;
            }

            $userModel = new UserModel();
            $user = $userModel->findByEmail($postData['email']);

            if ($user && password_verify($postData['password'], $user['password_hash'])) {
                if (file_exists($cacheFile)) @unlink($cacheFile); // Limpiar intentos fallidos

                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['full_name']
                    ?? $user['username']
                    ?? explode('@', $user['email'])[0]
                    ?? 'Usuario';
                echo json_encode(['success' => true]);
            } else {
                $fails++;
                file_put_contents($cacheFile, json_encode(['count' => $fails, 'time' => time()]));
                echo json_encode(['success' => false, 'message' => 'Credenciales incorrectas.']);
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
        }
    }

    public function logout(): void {
        header('Content-Type: application/json');
        session_start();
        session_unset();
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Sesión cerrada correctamente.']);
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

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'El formato del email es inválido.']);
            return;
        }

        if (strlen($data['password']) < 8) {
            echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.']);
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