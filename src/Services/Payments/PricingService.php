<?php
/**
 * PricingService.php
 *
 * Fuente única de verdad para la resolución dinámica de precios en StockiFy.
 * Calcula el monto exacto a cobrar según:
 *   - El plan al que el usuario quiere acceder
 *   - La cantidad de slots de colaboradores adicionales
 *
 * Cualquier cambio de precios se realiza ÚNICAMENTE aquí y en payment_config.php.
 */

declare(strict_types=1);

namespace App\Services\Payments;

require_once __DIR__ . '/../../config/payment_config.php';
require_once __DIR__ . '/../../core/Database.php';

use App\core\Database;
use PDO;

class PricingService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obtiene el precio unitario del plan desde la base de datos.
     * Soporta precios personalizados para el Plan Empresarial (3).
     *
     * @param  int   $planId  ID del plan (1=Básico, 2=Profesional, 3=Empresarial, 4=Vitalicio)
     * @param  int|null $userId ID del usuario (opcional, requerido para Plan Empresarial 3)
     * @return float Precio en ARS
     * @throws \InvalidArgumentException si el plan no es válido
     */
    public function getPlanPrice(int $planId, ?int $userId = null): float
    {
        // Caso Especial: Plan Empresarial (3)
        if ($planId === 3) {
            if ($userId === null) {
                throw new \InvalidArgumentException("Para calcular el precio del Plan Empresarial, se requiere el ID del usuario.");
            }
            $stmt = $this->db->prepare("SELECT custom_enterprise_price FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $customPrice = $stmt->fetchColumn();
            
            if ($customPrice !== false && $customPrice !== null) {
                return (float)$customPrice;
            }
            
            // Si es nulo y no hay cotización, por seguridad retornamos un precio base muy alto o lanzamos excepción
            // Retornamos 150000.00 por defecto según el caso de ejemplo del usuario
            return 150000.00;
        }

        // Obtener el precio desde la tabla system_pricing
        $key = "plan_{$planId}_price";
        $stmt = $this->db->prepare("SELECT price_value FROM system_pricing WHERE price_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $price = $stmt->fetchColumn();

        if ($price === false || $price === null) {
            // Fallback de seguridad al archivo config en caso de que la tabla no esté poblada
            $configPrices = [
                1 => 5000.00,
                2 => 15000.00,
                4 => 80000.00,
            ];
            if (!isset($configPrices[$planId])) {
                throw new \InvalidArgumentException("Plan ID '$planId' no reconocido en base de datos ni fallback.");
            }
            return $configPrices[$planId];
        }

        return (float)$price;
    }

    /**
     * Obtiene el nombre descriptivo de un plan para mostrarlo al usuario.
     *
     * @param  int    $planId
     * @return string
     */
    public function getPlanName(int $planId): string
    {
        return PAYMENT_PLAN_NAMES[$planId] ?? "Plan #$planId";
    }

    /**
     * Obtiene el costo unitario de un slot de colaborador desde la base de datos.
     *
     * @return float
     */
    public function getSlotUnitPrice(): float
    {
        $stmt = $this->db->prepare("SELECT price_value FROM system_pricing WHERE price_key = 'slot_price' LIMIT 1");
        $stmt->execute();
        $price = $stmt->fetchColumn();

        if ($price === false || $price === null) {
            return 20000.00; // Fallback
        }

        return (float)$price;
    }

    /**
     * Calcula el costo total de N slots adicionales de colaborador.
     *
     * @param  int   $slots  Cantidad de slots (> 0)
     * @return float Precio total en ARS
     * @throws \InvalidArgumentException si la cantidad es inválida
     */
    public function getSlotsTotalPrice(int $slots): float
    {
        if ($slots <= 0) {
            throw new \InvalidArgumentException("La cantidad de slots debe ser mayor a 0.");
        }

        return $slots * $this->getSlotUnitPrice();
    }

    /**
     * Construye el título descriptivo del ítem para mostrar en el checkout.
     *
     * @param string $nature  'plan_activation' | 'collaborator_slots'
     * @param array  $meta    Metadata adicional (plan_id, slots_count, etc.)
     * @return string
     */
    public function buildItemTitle(string $nature, array $meta = []): string
    {
        return match ($nature) {
            'plan_activation'   => PAYMENT_APP_NAME . ' — ' . $this->getPlanName((int)($meta['plan_id'] ?? 0)),
            'collaborator_slots' => PAYMENT_APP_NAME . ' — ' . ($meta['slots_count'] ?? 1) . ' Slot(s) de Colaborador',
            default             => PAYMENT_APP_NAME . ' — Pago',
        };
    }

    /**
     * Calcula y valida el monto total para una intención de pago.
     * Retorna el monto en ARS según la naturaleza del cobro.
     *
     * @param string $nature  'plan_activation' | 'collaborator_slots'
     * @param array  $meta    { plan_id?: int, slots_count?: int, user_id?: int, debt_id?: int }
     * @return float
     */
    public function resolveAmount(string $nature, array $meta): float
    {
        return match ($nature) {
            'plan_activation'    => $this->getPlanPrice((int)($meta['plan_id'] ?? 0), isset($meta['user_id']) ? (int)$meta['user_id'] : null),
            'collaborator_slots' => $this->resolveSlotsPrice($meta),
            default              => throw new \InvalidArgumentException("Naturaleza de cobro '$nature' no reconocida."),
        };
    }

    /**
     * Resuelve el precio de los slots, priorizando el monto guardado en la deuda
     * si se proporciona un debt_id. Si no, calcula según el precio unitario actual.
     */
    private function resolveSlotsPrice(array $meta): float
    {
        $debtId = (int)($meta['debt_id'] ?? 0);
        if ($debtId > 0) {
            $stmt = $this->db->prepare("SELECT total_amount FROM collaborator_slots_debts WHERE id = ? LIMIT 1");
            $stmt->execute([$debtId]);
            $debtAmount = $stmt->fetchColumn();
            if ($debtAmount !== false && $debtAmount !== null) {
                return (float)$debtAmount;
            }
        }
        return $this->getSlotsTotalPrice((int)($meta['slots_count'] ?? 0));
    }
}
