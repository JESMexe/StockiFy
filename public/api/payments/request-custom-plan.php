<?php
/**
 * POST /api/payments/request-custom-plan.php
 *
 * Endpoint para solicitar el Plan Vitalicio o el Plan Empresarial mediante formulario.
 * Envía un correo electrónico al administrador con los detalles del interesado.
 */

header('Content-Type: application/json');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Services/MailService.php';

use App\Services\MailService;

try {
    // --- Autenticación ---
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado.']);
        exit;
    }

    // --- Validar método HTTP ---
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
        exit;
    }

    // --- Parsear y validar el body JSON ---
    $rawBody = file_get_contents('php://input');
    $data    = json_decode($rawBody, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Body JSON inválido.']);
        exit;
    }

    $planName = trim($data['plan_name'] ?? '');
    $userName = trim($data['name'] ?? '');
    $userEmail = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $comments = trim($data['comments'] ?? '');

    if (empty($planName) || empty($userName) || empty($userEmail) || empty($phone)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Todos los campos obligatorios (nombre del plan, nombre, email, teléfono) deben completarse.']);
        exit;
    }

    // --- Validar que sea Vitalicio o Empresarial ---
    if (!in_array($planName, ['Plan Vitalicio', 'Plan Empresarial'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Plan no válido para esta solicitud.']);
        exit;
    }

    // --- Enviar Correo ---
    $mailService = new MailService();
    $subject = "Solicitud de " . $planName;
    
    $sent = $mailService->sendCustomPlanRequest($subject, $userName, $userEmail, $phone, $comments);

    if ($sent) {
        echo json_encode(['success' => true, 'message' => 'Solicitud enviada correctamente. Te contactaremos pronto.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al enviar el correo de solicitud. Por favor intenta de nuevo o contáctanos por WhatsApp.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Excepción interna: ' . $e->getMessage()]);
}
