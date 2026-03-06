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

    private function clearOtpState(int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET
                otp_hash = NULL,
                otp_expires_at = NULL,
                otp_attempts = 0,
                otp_action_type = NULL
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $userId]);
    }

    public function clearPasswordOtp(int $userId): bool
    {
        return $this->clearOtpState($userId);
    }

    public function canRequestPasswordOtp(int $userId, int $cooldownSeconds = 60): bool
    {
        $stmt = $this->db->prepare("
            SELECT otp_last_sent_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['otp_last_sent_at'])) {
            return true;
        }

        $lastSentTimestamp = strtotime((string)$row['otp_last_sent_at']);
        if ($lastSentTimestamp === false) {
            return true;
        }

        return (time() - $lastSentTimestamp) >= $cooldownSeconds;
    }

    public function storePasswordChangeOtp(int $userId, string $otpHash, string $expiresAt): bool
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET
                otp_hash = :otp_hash,
                otp_expires_at = :otp_expires_at,
                otp_attempts = 0,
                otp_last_sent_at = NOW(),
                otp_action_type = 'password_change'
            WHERE id = :id
        ");

        return $stmt->execute([
            ':otp_hash' => $otpHash,
            ':otp_expires_at' => $expiresAt,
            ':id' => $userId
        ]);
    }

    public function verifyPasswordChangeOtp(int $userId, string $otp): bool
    {
        $stmt = $this->db->prepare("
            SELECT otp_hash, otp_expires_at, otp_attempts, otp_action_type
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $userId]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        if (
            empty($user['otp_hash']) ||
            empty($user['otp_expires_at']) ||
            ($user['otp_action_type'] ?? null) !== 'password_change'
        ) {
            return false;
        }

        $attempts = (int)($user['otp_attempts'] ?? 0);

        // Máximo 5 intentos fallidos
        if ($attempts >= 5) {
            $this->clearOtpState($userId);
            return false;
        }

        // Expirado
        if (strtotime((string)$user['otp_expires_at']) < time()) {
            $this->clearOtpState($userId);
            return false;
        }

        // Verificación del hash
        if (!password_verify($otp, (string)$user['otp_hash'])) {
            $this->incrementOtpAttempts($userId);
            return false;
        }

        // Si es válido, lo consumimos
        $this->clearOtpState($userId);
        return true;
    }

    private function incrementOtpAttempts(int $userId): void
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET otp_attempts = otp_attempts + 1
            WHERE id = :id
        ");

        $stmt->execute([':id' => $userId]);
    }
}