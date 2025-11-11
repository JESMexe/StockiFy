<?php
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/Models/NotificationModel.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../../vendor/phpmailer/Exception.php';
require_once __DIR__ . '/../../../vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../../../vendor/phpmailer/SMTP.php';

use App\Models\NotificationModel;

header('Content-Type: application/json');

session_start();
$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? null;
$title = $data['title'] ?? null;
$message = $data['message'] ?? null;

if (!$type || !$title) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tipo y título son requeridos']);
    exit;
}

try {
    $model = new NotificationModel();
    $model->create($user['id'], $type, $title, $message);

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('no-reply@stockify.com', 'StockiFy Notificaciones');
        $mail->addAddress($user['email']); // ¡Asume que $user['email'] existe!

        $mail->isHTML(true);
        $mail->Subject = "[$type] - $title";
        $mail->Body    = "Hola,<br><br>Recibiste una nueva notificación en StockiFy:<br><b>$message</b>";

        $mail->send();

    } catch (Exception $mailException) {
        error_log("Error al enviar mail: " . $mailException->getMessage());
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}