<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class InvitationModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Busca un usuario registrado por su email.
     * Usado para validar que el invitado tenga cuenta antes de generar la invitación.
     * @return array|null Los datos del usuario o null si no existe.
     */
    public function findUserByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT id, email, full_name, username FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([strtolower(trim($email))]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Marca como expiradas las invitaciones previas (pendientes) para el mismo email+inventario.
     * Evita acumular invitaciones duplicadas en la tabla.
     */
    public function markPreviousExpired(int $inventoryId, string $email): void
    {
        $stmt = $this->db->prepare(
            "UPDATE invitations SET status = 'expired' 
             WHERE inventory_id = ? AND email = ? AND status = 'pending'"
        );
        $stmt->execute([$inventoryId, strtolower(trim($email))]);
    }

    /**
     * Crea un registro histórico de invitación.
     * Con la nueva política (solo usuarios registrados), la invitación se acepta
     * automáticamente y este registro queda como auditoría.
     */
    public function createInvitation(int $inventoryId, string $email, int $roleId, int $invitedBy): ?string
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare(
            "INSERT INTO invitations (inventory_id, email, role_id, token, invited_by) 
             VALUES (?, ?, ?, ?, ?)"
        );
        if ($stmt->execute([$inventoryId, strtolower(trim($email)), $roleId, $token, $invitedBy])) {
            return $token;
        }
        return null;
    }

    /**
     * Busca una invitación pendiente por su token.
     */
    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM invitations WHERE token = ? AND status = 'pending'");
        $stmt->execute([$token]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Acepta una invitación: activa al colaborador en inventory_collaborators
     * y marca la invitación como aceptada.
     */
    public function acceptInvitation(int $invitationId, int $userId): bool
    {
        try {
            $this->db->beginTransaction();

            $stmtInv = $this->db->prepare("SELECT * FROM invitations WHERE id = ? FOR UPDATE");
            $stmtInv->execute([$invitationId]);
            $invitation = $stmtInv->fetch(PDO::FETCH_ASSOC);

            if (!$invitation || $invitation['status'] !== 'pending') {
                $this->db->rollBack();
                return false;
            }

            // Insertar o actualizar en inventory_collaborators
            $stmtCollab = $this->db->prepare(
                "INSERT INTO inventory_collaborators (inventory_id, user_id, role_id, status, invited_by, accepted_at) 
                 VALUES (?, ?, ?, 'active', ?, NOW()) 
                 ON DUPLICATE KEY UPDATE status='active', role_id=VALUES(role_id), accepted_at=NOW()"
            );
            $stmtCollab->execute([
                $invitation['inventory_id'],
                $userId,
                $invitation['role_id'],
                $invitation['invited_by']
            ]);

            // Marcar invitación como aceptada
            $stmtUpdate = $this->db->prepare("UPDATE invitations SET status = 'accepted' WHERE id = ?");
            $stmtUpdate->execute([$invitationId]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("InvitationModel::acceptInvitation error: " . $e->getMessage());
            return false;
        }
    }
}
