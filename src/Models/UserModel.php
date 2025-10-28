<?php
namespace App\Models;

use App\Core\Database;
use PDO;
use PDOException;

class UserModel
{
    private $db;

    public function __construct()
    {
        // Obtengo la conexion por el Singleton
        $this->db = Database::getInstance();
    }

    /**
     * Busca un usuario por su dirección de correo electrónico.
     */
    public function findByEmail(string $email)
    {
        $stmt = $this->db->prepare("SELECT id, email, username, password_hash FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
    }

    /**
     * Crea un nuevo usuario en la base de datos.
     *
     * @param array $data Datos del usuario (username, email, password, etc.).
     * @return bool Devuelve true si el usuario fue creado, false si ya existe.
     */
    public function createUser(array $data): bool
    {
        // Hashear la contraseña de forma segura
        // el estandar de PHP.
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        // Preparar la consulta SQL
        $sql = "INSERT INTO users (username, email, password_hash, full_name, cell, dni, is_admin)
                VALUES (:username, :email, :password_hash, :full_name, :cell, :dni, :is_admin)";

        $stmt = $this->db->prepare($sql);

        try {
            // Ejecutar la insercion
            $stmt->execute([
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':password_hash' => $passwordHash,
                ':full_name' => $data['full_name'] ?? null,
                ':cell' => $data['cell'] ?? null,
                ':dni' => $data['dni'] ?? null,
                ':is_admin' => $data['is_admin'] ?? 0
            ]);
            return true;
        } catch (PDOException $e) {
            // El código '23000' es un error de violacion de integridad, ej: email o username duplicado
            if ($e->getCode() == '23000') {
                return false;
            }
            // Para otros errores, lo relanzamos
            throw $e;
        }
    }

    /**
     * Busca un usuario por su ID.
     *
     * @param int $id El ID del usuario.
     * @return array|false Devuelve los datos del usuario o false si no lo encuentra.
     */
    public function findById(int $id)
    {
        $stmt = $this->db->prepare("SELECT id, username, email, full_name FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
}