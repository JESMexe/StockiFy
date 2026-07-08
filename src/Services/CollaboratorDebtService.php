<?php
namespace App\Services;

use App\core\Database;
use App\helpers\ActivityLogger;
use App\Services\Payments\PricingService;
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

                // 3. Obtener la cantidad de colaboradores únicos y remover los excedentes en base a base_collaborator_count
                $ownerId = (int)$debt['owner_id'];
                $baseCount = (int)$debt['base_collaborator_count'];

                $stmtCount = $this->db->prepare("
                    SELECT COUNT(DISTINCT ic.user_id) 
                    FROM inventory_collaborators ic
                    INNER JOIN inventories inv ON ic.inventory_id = inv.id
                    WHERE inv.user_id = ? 
                      AND ic.status = 'active'
                      AND ic.user_id != ?
                ");
                $stmtCount->execute([$ownerId, $ownerId]);
                $currentCount = (int)$stmtCount->fetchColumn();

                $slotsToRemove = $currentCount - $baseCount;

                if ($slotsToRemove > 0) {
                    // Obtener los colaboradores únicos más recientes del Owner
                    $stmtCollabs = $this->db->prepare("
                        SELECT ic.user_id, MAX(u.username) as username, MAX(u.email) as email, MAX(ic.id) as max_ic_id
                        FROM inventory_collaborators ic
                        INNER JOIN users u ON ic.user_id = u.id
                        INNER JOIN inventories inv ON ic.inventory_id = inv.id
                        WHERE inv.user_id = :owner_id 
                          AND ic.status = 'active'
                          AND ic.user_id != :owner_id2
                        GROUP BY ic.user_id
                        ORDER BY max_ic_id DESC
                        LIMIT :limit
                    ");
                    $stmtCollabs->bindValue(':owner_id', $ownerId, PDO::PARAM_INT);
                    $stmtCollabs->bindValue(':owner_id2', $ownerId, PDO::PARAM_INT);
                    $stmtCollabs->bindValue(':limit', $slotsToRemove, PDO::PARAM_INT);
                    $stmtCollabs->execute();
                    $collabsToRemove = $stmtCollabs->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($collabsToRemove as $collab) {
                        // Obtener todos los inventarios de este Owner donde este colaborador tiene acceso activo
                        $stmtCollabInvs = $this->db->prepare("
                            SELECT ic.inventory_id 
                            FROM inventory_collaborators ic
                            INNER JOIN inventories inv ON ic.inventory_id = inv.id
                            WHERE inv.user_id = ? AND ic.user_id = ? AND ic.status = 'active'
                        ");
                        $stmtCollabInvs->execute([$ownerId, $collab['user_id']]);
                        $invs = $stmtCollabInvs->fetchAll(PDO::FETCH_COLUMN);

                        foreach ($invs as $invId) {
                            // Actualizar estado en la tabla de empleados
                            $stmtEmp = $this->db->prepare("
                                UPDATE employees 
                                SET is_collaborator = 0 
                                WHERE email = ? AND inventory_id = ?
                            ");
                            $stmtEmp->execute([$collab['email'], $invId]);

                            // Eliminar colaborador
                            $stmtDelete = $this->db->prepare("
                                DELETE FROM inventory_collaborators 
                                WHERE user_id = ? AND inventory_id = ?
                            ");
                            $stmtDelete->execute([$collab['user_id'], $invId]);

                            // Registrar actividad
                            require_once __DIR__ . '/../helpers/ActivityLogger.php';
                            \App\helpers\ActivityLogger::log(
                                'Colaboradores',
                                'expire_removal',
                                'collaborator',
                                (string)$collab['user_id'],
                                "Eliminado automáticamente por falta de pago de slots adicionales: " . ($collab['username'] ?: $collab['email']),
                                "El Owner no saldó la deuda de slots adicionales en 48 horas.",
                                (int)$invId,
                                $ownerId,
                                'System'
                            );
                        }
                    }
                }

                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error processing expired debts: " . $e->getMessage());
            if (php_sapi_name() === 'cli') {
                echo "ERROR in processExpiredDebts: " . $e->getMessage() . "\n";
            }
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

        // 1. Validar deudas pendientes o expiradas
        $stmtCheck = $this->db->prepare("
            SELECT status FROM collaborator_slots_debts 
            WHERE owner_id = ? AND status IN ('pending', 'expired')
            LIMIT 1
        ");
        $stmtCheck->execute([$ownerId]);
        $debtStatus = $stmtCheck->fetchColumn();

        if ($debtStatus === 'expired') {
            throw new \Exception("Tu deuda anterior de slots de colaboradores expiró sin pagarse. Tu cuenta se encuentra restringida. Contactate con soporte para habilitar esta opción.");
        } elseif ($debtStatus === 'pending') {
            throw new \Exception("Ya tenés una deuda de slots de colaboradores pendiente de pago. Por favor, saldala antes de agregar más slots.");
        }

        // 2. Calcular base_collaborator_count (colaboradores únicos activos)
        $stmtUsed = $this->db->prepare("
            SELECT COUNT(DISTINCT ic.user_id)
            FROM inventory_collaborators ic
            INNER JOIN inventories inv ON ic.inventory_id = inv.id
            WHERE inv.user_id = ?
              AND ic.status   = 'active'
              AND ic.user_id != ?
        ");
        $stmtUsed->execute([$ownerId, $ownerId]);
        $baseCollaboratorCount = (int)$stmtUsed->fetchColumn();

        $pricePerSlot = (new PricingService())->getSlotUnitPrice();
        $totalAmount = $slotsCount * $pricePerSlot;

        // Fetch owner email
        $stmtEmail = $this->db->prepare("SELECT email FROM users WHERE id = ?");
        $stmtEmail->execute([$ownerId]);
        $ownerEmail = $stmtEmail->fetchColumn();

        $stmt = $this->db->prepare("
            INSERT INTO collaborator_slots_debts (owner_id, owner_email, inventory_id, slots_added, base_collaborator_count, price_per_slot, total_amount, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $success = $stmt->execute([$ownerId, $ownerEmail, $inventoryId, $slotsCount, $baseCollaboratorCount, $pricePerSlot, $totalAmount]);

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

    /**
     * Revisa si hay deudas de slots de colaboradores que estén a menos de 12 horas de expirar
     * y envía una notificación de advertencia por WhatsApp.
     */
    public function checkAndSendDebtWarnings(): void
    {
        try {
            $stmt = $this->db->prepare("
                SELECT d.*, 
                       u.cell as owner_cell,
                       u.full_name as owner_name,
                       u.username as owner_username,
                       inv.name as inventory_name,
                       TIMESTAMPDIFF(SECOND, NOW(), d.created_at + INTERVAL 48 HOUR) as seconds_left
                FROM collaborator_slots_debts d
                INNER JOIN users u ON d.owner_id = u.id
                INNER JOIN inventories inv ON d.inventory_id = inv.id
                WHERE d.status = 'pending'
                  AND d.warning_sent = 0
                  AND d.created_at + INTERVAL 48 HOUR <= NOW() + INTERVAL 12 HOUR
                  AND d.created_at + INTERVAL 48 HOUR > NOW()
            ");
            $stmt->execute();
            $debtsToWarn = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($debtsToWarn)) {
                return;
            }

            $whatsappSvc = new WhatsappService();

            foreach ($debtsToWarn as $debt) {
                if (empty($debt['owner_cell'])) {
                    continue;
                }

                $hoursLeft = (int)ceil((float)$debt['seconds_left'] / 3600.0);
                if ($hoursLeft <= 0) {
                    $hoursLeft = 1;
                }

                $userName = $debt['owner_name'] ?: $debt['owner_username'] ?: 'Usuario';
                $pendingAmount = (float)$debt['total_amount'];
                $slotsCount = (int)$debt['slots_added'];
                $inventoryName = $debt['inventory_name'] ?: 'Principal';

                $success = $whatsappSvc->sendCollaboratorSlotsExpiryAlert(
                    $debt['owner_cell'],
                    $userName,
                    $pendingAmount,
                    $slotsCount,
                    $inventoryName,
                    $hoursLeft
                );

                if ($success) {
                    $stmtUpdate = $this->db->prepare("UPDATE collaborator_slots_debts SET warning_sent = 1 WHERE id = ?");
                    $stmtUpdate->execute([$debt['id']]);

                    // Registrar actividad
                    require_once __DIR__ . '/../helpers/ActivityLogger.php';
                    \App\helpers\ActivityLogger::log(
                        'Colaboradores',
                        'send_slots_warning',
                        'collaborator_debt',
                        (string)$debt['id'],
                        "Se envió advertencia de vencimiento por WhatsApp a {$userName} por deudas de slots adicionales.",
                        "Horas restantes: {$hoursLeft}h.",
                        (int)$debt['inventory_id'],
                        (int)$debt['owner_id'],
                        'System'
                    );
                } else {
                    error_log("Failed to send WhatsApp slots warning for debt ID {$debt['id']}: " . $whatsappSvc->lastError);
                }
            }
        } catch (\Throwable $e) {
            error_log("Error in checkAndSendDebtWarnings: " . $e->getMessage());
        }
    }
}
