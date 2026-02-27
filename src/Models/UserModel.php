<?php
namespace App\Models;

use App\core\Database;
use PDO;
use Exception;

class UserModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByEmail(string $email)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findById(int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // --- MÉTODOS DE GOOGLE AUTH ---
    public function findByGoogleId(string $googleId) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE google_id = :gid");
        $stmt->execute([':gid' => $googleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function linkGoogleAccount(int $userId, string $googleId): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET google_id = :gid WHERE id = :id");
        return $stmt->execute([':gid' => $googleId, ':id' => $userId]);
    }

    public function createFromGoogle(array $data): bool|string
    {
        // Crea usuario con datos de Google y contraseña aleatoria (ya que entra por Google)
        $tempPass = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (username, email, password_hash, full_name, google_id, created_at) 
                VALUES (:username, :email, :pass, :name, :gid, NOW())";

        $stmt = $this->db->prepare($sql);
        try {
            $stmt->execute([
                ':username' => explode('@', $data['email'])[0] . rand(100,999), // Username temporal único
                ':email' => $data['email'],
                ':pass' => $tempPass,
                ':name' => $data['name'],
                ':gid' => $data['google_id']
            ]);
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            return false;
        }
    }

    private function consumeOtp($userId) {
        $this->db->prepare("UPDATE users SET otp_code = NULL, otp_expiry = NULL WHERE id = ?")->execute([$userId]);
    }

    /**
     * Guarda un código OTP y su expiración para un usuario.
     */
    public function setOtp($userId, $otp): bool
    {
        $expiry = date("Y-m-d H:i:s", strtotime('+15 minutes'));
        $sql = "UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$otp, $expiry, $userId]);
    }

    /**
     * Verifica si el código es válido y no ha expirado.
     */
    public function verifyOtp($userId, $otp): bool
    {
        $sql = "SELECT id FROM users 
            WHERE id = ? AND otp_code = ? AND otp_expiry > NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $otp]);
        $user = $stmt->fetch();

        if ($user) {
            // Si es válido, lo borramos para que no se pueda reusar
            $this->db->prepare("UPDATE users SET otp_code = NULL, otp_expiry = NULL WHERE id = ?")
                ->execute([$userId]);
            return true;
        }
        return false;
    }

    // Métodos para actualizar los datos finales
    public function updateEmail($userId, $newEmail): bool
    {
        return $this->db->prepare("UPDATE users SET email = ? WHERE id = ?")
            ->execute([$newEmail, $userId]);
    }

    public function updatePassword($userId, $newHash): bool
    {
        return $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
            ->execute([$newHash, $userId]);
    }
}