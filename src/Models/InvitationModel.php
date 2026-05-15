<?php
namespace App\Models;

use App\core\Database;
use PDO;

class InvitationModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function createInvitation(int $inventoryId, string $email, int $roleId, int $invitedBy): ?string
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare("INSERT INTO invitations (inventory_id, email, role_id, token, invited_by) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$inventoryId, $email, $roleId, $token, $invitedBy])) {
            return $token;
        }
        return null;
    }

    public function findByToken(string $token)
    {
        $stmt = $this->db->prepare("SELECT * FROM invitations WHERE token = ? AND status = 'pending'");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

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

            // Insertar en inventory_collaborators
            $stmtCollab = $this->db->prepare("INSERT INTO inventory_collaborators (inventory_id, user_id, role_id, status, invited_by, accepted_at) VALUES (?, ?, ?, 'active', ?, NOW()) ON DUPLICATE KEY UPDATE status='active', role_id=VALUES(role_id)");
            $stmtCollab->execute([
                $invitation['inventory_id'],
                $userId,
                $invitation['role_id'],
                $invitation['invited_by']
            ]);

            // Marcar como accepted
            $stmtUpdate = $this->db->prepare("UPDATE invitations SET status = 'accepted' WHERE id = ?");
            $stmtUpdate->execute([$invitationId]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
}
