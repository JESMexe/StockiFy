<?php
namespace App\Services;

use App\core\Database;
use App\helpers\ActivityLogger;
use PDO;

class CollaboratorDebtService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Procesa las deudas pendientes que ya superaron las 48 horas de plazo.
     * Marca como expiradas y elimina los colaboradores excedentes del inventario correspondiente.
     */
    public function processExpiredDebts(): void
    {
        try {
            // 1. Obtener deudas pendientes con más de 48 horas de antigüedad
            $stmt = $this->db->prepare("
                SELECT * FROM collaborator_slots_debts 
                WHERE status = 'pending' 
                  AND created_at < NOW() - INTERVAL 48 HOUR
            ");
            $stmt->execute();
            $expiredDebts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($expiredDebts)) {
                return;
            }

            foreach ($expiredDebts as $debt) {
                $this->db->beginTransaction();

                // Lock for update to avoid race conditions
                $stmtLock = $this->db->prepare("SELECT status FROM collaborator_slots_debts WHERE id = ? FOR UPDATE");
                $stmtLock->execute([$debt['id']]);
                $currentStatus = $stmtLock->fetchColumn();

                if ($currentStatus !== 'pending') {
                    $this->db->rollBack();
                    continue;
                }

                // 2. Cambiar estado a 'expired'
                $stmtUpdate = $this->db->prepare("UPDATE collaborator_slots_debts SET status = 'expired' WHERE id = ?");
                $stmtUpdate->execute([$debt['id']]);

                // 3. Obtener los colaboradores más recientes de este inventario para eliminarlos
                // Excluimos al owner (role_id = 1)
                $slotsToRemove = (int)$debt['slots_added'];
                $inventoryId = (int)$debt['inventory_id'];

                $stmtCollabs = $this->db->prepare("
                    SELECT ic.id, ic.user_id, u.username, u.email 
                    FROM inventory_collaborators ic
                    JOIN users u ON ic.user_id = u.id
                    WHERE ic.inventory_id = ? AND ic.role_id != 1
                    ORDER BY ic.id DESC
                    LIMIT :limit
                ");
                // PDO needs bindValue for integer limit
                $stmtCollabs->bindValue(':limit', $slotsToRemove, PDO::PARAM_INT);
                $stmtCollabs->execute();
                $collabsToRemove = $stmtCollabs->fetchAll(PDO::FETCH_ASSOC);

                foreach ($collabsToRemove as $collab) {
                    // Eliminar colaborador
                    $stmtDelete = $this->db->prepare("DELETE FROM inventory_collaborators WHERE id = ?");
                    $stmtDelete->execute([$collab['id']]);

                    // Registrar actividad
                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Colaboradores',
                        'expire_removal',
                        'collaborator',
                        (string)$collab['user_id'],
                        "Eliminado automáticamente por falta de pago de slots adicionales: " . ($collab['username'] ?: $collab['email']),
                        "El Owner no saldó la deuda de slots adicionales en 48 horas.",
                        $inventoryId,
                        $debt['owner_id'],
                        'System'
                    );
                }

                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error processing expired debts: " . $e->getMessage());
        }
    }

    /**
     * Obtiene el total de slots adicionales activos (paid o pending < 48h) de un inventario.
     */
    public function getActiveExtraSlots(int $inventoryId): int
    {
        $stmt = $this->db->prepare("
            SELECT SUM(slots_added) 
            FROM collaborator_slots_debts 
            WHERE inventory_id = ? 
              AND (status = 'paid' OR (status = 'pending' AND created_at >= NOW() - INTERVAL 48 HOUR))
        ");
        $stmt->execute([$inventoryId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Agrega slots adicionales pendientes para un inventario.
     */
    public function addPendingSlots(int $ownerId, int $inventoryId, int $slotsCount): bool
    {
        if ($slotsCount <= 0) return false;

        $pricePerSlot = 20000.00;
        $totalAmount = $slotsCount * $pricePerSlot;

        // Fetch owner email
        $stmtEmail = $this->db->prepare("SELECT email FROM users WHERE id = ?");
        $stmtEmail->execute([$ownerId]);
        $ownerEmail = $stmtEmail->fetchColumn();

        $stmt = $this->db->prepare("
            INSERT INTO collaborator_slots_debts (owner_id, owner_email, inventory_id, slots_added, price_per_slot, total_amount, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $success = $stmt->execute([$ownerId, $ownerEmail, $inventoryId, $slotsCount, $pricePerSlot, $totalAmount]);

        if ($success) {
            // Registrar actividad
            require_once __DIR__ . '/../helpers/ActivityLogger.php';
            \App\helpers\ActivityLogger::log(
                'Colaboradores',
                'add_slots',
                'collaborator_debt',
                (string)$this->db->lastInsertId(),
                "Agregó {$slotsCount} slots de colaboradores (deuda de $" . number_format($totalAmount, 0, ',', '.') . " pendiente)",
                "Debe saldarse en un plazo de 48 horas.",
                $inventoryId,
                $ownerId
            );
        }

        return $success;
    }

    /**
     * Obtiene las deudas pendientes para el banner de advertencia.
     */
    public function getPendingDebtsForInventory(int $inventoryId): array
    {
        $stmt = $this->db->prepare("
            SELECT *, 
                   (created_at + INTERVAL 48 HOUR) as deadline,
                   TIMESTAMPDIFF(SECOND, NOW(), created_at + INTERVAL 48 HOUR) as seconds_left
            FROM collaborator_slots_debts
            WHERE inventory_id = ? AND status = 'pending'
            ORDER BY created_at ASC
        ");
        $stmt->execute([$inventoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Renueva las deudas pagadas mensualmente.
     * Busca deudas con status 'paid' donde created_at tiene más de 1 mes,
     * reinicia su estado a 'pending' y actualiza created_at a NOW().
     */
    public function renewMonthlyDebts(): void
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM collaborator_slots_debts 
                WHERE status = 'paid' 
                  AND created_at <= NOW() - INTERVAL 1 MONTH
            ");
            $stmt->execute();
            $debtsToRenew = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($debtsToRenew)) {
                return;
            }

            foreach ($debtsToRenew as $debt) {
                $this->db->beginTransaction();

                $stmtLock = $this->db->prepare("SELECT status FROM collaborator_slots_debts WHERE id = ? FOR UPDATE");
                $stmtLock->execute([$debt['id']]);
                $currentStatus = $stmtLock->fetchColumn();

                if ($currentStatus !== 'paid') {
                    $this->db->rollBack();
                    continue;
                }

                // Reiniciar a pending y actualizar fecha
                $stmtUpdate = $this->db->prepare("
                    UPDATE collaborator_slots_debts 
                    SET status = 'pending', created_at = NOW() 
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$debt['id']]);

                // Registrar actividad de renovación
                require_once __DIR__ . '/../helpers/ActivityLogger.php';
                \App\helpers\ActivityLogger::log(
                    'Colaboradores',
                    'monthly_renewal',
                    'collaborator_debt',
                    (string)$debt['id'],
                    "Se generó la renovación mensual por {$debt['slots_added']} slots adicionales (deuda de $" . number_format($debt['total_amount'] ?? ($debt['slots_added'] * $debt['price_per_slot']), 0, ',', '.') . " pendiente).",
                    "El ciclo mensual se ha reiniciado. Debe saldarse en 48 horas.",
                    $debt['inventory_id'],
                    $debt['owner_id'],
                    'System'
                );

                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error renewing monthly debts: " . $e->getMessage());
        }
    }
}
