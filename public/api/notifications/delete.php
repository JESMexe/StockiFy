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
$notificationId = $data['id'] ?? null;

if (!$notificationId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de notificación requerido']);
    exit;
}

try {
    $model = new NotificationModel();
    if ($model->deleteById($notificationId, $user['id'])) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('No se pudo eliminar la notificación o no tenés permiso.');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}