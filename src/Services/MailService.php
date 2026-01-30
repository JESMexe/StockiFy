<?php
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../config/mail_config.php';

class MailService {

    private function getMailer(): PHPMailer {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        return $mail;
    }

    public function sendSecurityOTP($toEmail, $otpCode, $actionType): bool {
        try {
            $mail = $this->getMailer();
            $mail->setFrom(MAIL_FROM_SECURITY, MAIL_NAME_SECURITY);
            $mail->addAddress($toEmail);

            if ($actionType === 'password_change') {
                $mail->Subject = 'ALERTA DE SEGURIDAD: Solicitud de cambio de contraseña';
                $mail->Body = "<h2>Solicitud de Cambio de Contraseña</h2>
                           <p>Introduce este código de seguridad en la aplicación para continuar:</p>
                           <h1 style='color:red; letter-spacing:5px'>$otpCode</h1>
                           <p>Si no solicitaste este cambio, ignora este correo y asegura tu cuenta.</p>";
            }

            $mail->isHTML(true);
            return $mail->send();

        } catch (Exception $e) {
            error_log("Error de PHPMailer: " . $e->getMessage());
            return false;
        }
    }
}