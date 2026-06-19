<?php
/**
 * quota_helper.php
 *
 * Centraliza la lógica de cupos de colaboradores por suscripción.
 * Esta es la ÚNICA fuente de verdad para determinar si un Owner puede
 * agregar más colaboradores a sus inventarios.
 *
 * Reglas de negocio:
 *   Básico      (1) → 0 colaboradores  → botón bloqueado con candado
 *   Profesional (2) → 2 colaboradores  → hardcoded
 *   Empresarial (3) → users.collaborator_slots (default 5 si NULL)
 *   Vitalicio   (4) → ilimitado        → null = sin tope
 *
 * El conteo usa DISTINCT user_id a través de TODOS los inventarios
 * del Owner, para no penalizar por tener múltiples inventarios.
 * (Miguel en 2 inventarios = 1 slot usado, no 2.)
 */

declare(strict_types=1);

use App\core\Database;

if (!function_exists('getCollaboratorQuota')) {

    /**
     * Retorna la información de cupo de colaboradores del Owner.
     *
     * @param  int   $ownerId  ID del usuario propietario del inventario
     * @return array {
     *   plan:       int,        // subscription_active
     *   plan_name:  string,
     *   max:        int|null,   // null = ilimitado
     *   used:       int,        // colaboradores únicos activos
     *   remaining:  int|null,   // null = ilimitado; 0 = sin cupos
     *   allowed:    bool,       // puede invitar más?
     *   locked:     bool,       // plan que no permite ningún colaborador
     * }
     */
    function getCollaboratorQuota(int $ownerId): array
    {
        $db = Database::getInstance();

        // Ejecutar autocorrección on-demand para deudas expiradas
        require_once __DIR__ . '/../Services/CollaboratorDebtService.php';
        $debtSvc = new \App\Services\CollaboratorDebtService();
        $debtSvc->processExpiredDebts();

        // Calcular slots adicionales activos (del owner en todos sus inventarios)
        $stmtExtra = $db->prepare(
            "SELECT SUM(slots_added) 
             FROM collaborator_slots_debts 
             WHERE owner_id = ? 
               AND (status = 'paid' OR (status = 'pending' AND created_at >= NOW() - INTERVAL 48 HOUR))"
        );
        $stmtExtra->execute([$ownerId]);
        $extraSlots = (int)$stmtExtra->fetchColumn();

        // 1. Leer plan del Owner
        $stmt = $db->prepare(
            'SELECT subscription_active FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$ownerId]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$owner) {
            // Owner no encontrado → bloquear por seguridad
            return _buildQuotaResult(plan: 0, planName: 'Sin Plan', max: 0, used: 0);
        }

        $plan  = (int)$owner['subscription_active'];

        // 2. Determinar el tope máximo según el plan
        $max = match($plan) {
            1 => 0,                                                           // Básico: sin colaboradores
            2 => 2 + $extraSlots,                                             // Profesional: hasta 2 + extras
            3 => 5,                                                           // Empresarial: default 5
            4 => 4 + $extraSlots,                                             // Vitalicio: hasta 4 + extras (5 cupos base including owner)
            default => 0,                                                     // Plan desconocido: bloqueado
        };

        $planName = match($plan) {
            1 => 'Básico',
            2 => 'Profesional',
            3 => 'Empresarial',
            4 => 'Vitalicio',
            default => 'Sin Plan',
        };

        // 3. Contar colaboradores únicos activos en todos los inventarios del Owner
        //    (excluye al propio Owner por user_id != ownerId)
        $stmtUsed = $db->prepare(
            'SELECT COUNT(DISTINCT ic.user_id)
             FROM inventory_collaborators ic
             INNER JOIN inventories inv ON ic.inventory_id = inv.id
             WHERE inv.user_id = ?
               AND ic.status   = \'active\'
               AND ic.user_id != ?'
        );
        $stmtUsed->execute([$ownerId, $ownerId]);
        $used = (int)$stmtUsed->fetchColumn();

        return _buildQuotaResult($plan, $planName, $max, $used);
    }

    /**
     * Construye el array de resultado de cuota de forma consistente.
     * @internal
     */
    function _buildQuotaResult(int $plan, string $planName, ?int $max, int $used): array
    {
        $locked    = ($plan === 1 || ($plan === 0));
        $remaining = ($max === null) ? null : max(0, $max - $used);
        $allowed   = !$locked && ($max === null || $remaining > 0);

        return [
            'plan'      => $plan,
            'plan_name' => $planName,
            'max'       => $max,       // null = ilimitado
            'used'      => $used,
            'remaining' => $remaining, // null = ilimitado, int = cupos restantes
            'allowed'   => $allowed,
            'locked'    => $locked,
        ];
    }

}
