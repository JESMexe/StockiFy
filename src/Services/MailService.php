<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../config/mail_config.php';

class MailService
{
    private function getMailer(): PHPMailer
    {
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

    public function sendSecurityOTP(
        string $toEmail,
        string $otpCode,
        string $actionType,
        string $userName
    ): bool {
        try {
            $mail = $this->getMailer();
            $mail->setFrom(MAIL_FROM_SECURITY, MAIL_NAME_SECURITY);
            $mail->addAddress($toEmail);
            $mail->isHTML(true);

            switch ($actionType) {
                case 'password_change':
                    $mail->Subject = 'ALERTA DE SEGURIDAD: Solicitud de cambio de contraseña';
                    $mail->Body = $this->generateSecurityCodeEmailHtml($otpCode, $userName);
                    $mail->AltBody = "Hola {$userName}, tu código de verificación para cambiar la contraseña es: {$otpCode}. Este código es temporal. Si no solicitaste este cambio, ignorá este correo.";
                    break;

                default:
                    throw new \InvalidArgumentException('Tipo de acción OTP no soportado.');
            }

            return $mail->send();
        } catch (Exception|\Throwable $e) {
            error_log("MailService::sendSecurityOTP error: " . $e->getMessage());
            return false;
        }
    }

    private function generateSecurityCodeEmailHtml(string $code, string $userName = 'Usuario'): string
    {
        return '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Código de seguridad | StockiFy</title>
    </head>
    <body style="margin:0; padding:0; background-color:#A3BE8C22; font-family:Arial, Helvetica, sans-serif; color:#1a1a1a;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f3f4f6; margin:0; padding:32px 0;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px; background:#ffffff; border:2px solid #1a1a1a; border-radius:14px; overflow:hidden;">
                        
                        <tr>
                            <td style="padding:28px 32px; border-bottom:1px solid #e5e7eb;">
                                <div style="font-size:28px; font-weight:bold; color:#1a1a1a;">StockiFy</div>
                                <!--<img src="...AunNoEstaOnlineEsto/LogoE2.png" alt="StockiFy" width="150" style="display:block; border:0;">-->
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:36px 32px 16px 32px;">
                                <p style="margin:0 0 10px 0; font-size:14px; color:#A3BE8C; font-weight:bold; text-transform:uppercase; letter-spacing:1px; font-family: Satoshi, sans-serif;;">
                                    Seguridad StockiFy
                                </p>

                                <h1 style="margin:0 0 18px 0; font-size:30px; line-height:1.2; color:#1a1a1a;">
                                    Solicitud de cambio de contraseña
                                </h1>

                                <p style="margin:0; font-size:16px; line-height:1.7; color:#475569;">
                                    Hola <strong>' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '</strong>, recibimos una solicitud para cambiar la contraseña de tu cuenta.
                                    Ingresá este código en la aplicación para continuar con el proceso.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:10px 32px 8px 32px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#A3BE8C33; border:2px dashed #A3BE8C; border-radius:12px;">
                                    <tr>
                                        <td align="center" style="padding:28px 16px;">
                                            <p style="margin:0 0 12px 0; font-size:13px; color:#A3BE8C; font-weight:bold; text-transform:uppercase; letter-spacing:1px; font-family: Satoshi, sans-serif;;">
                                                Código de verificación
                                            </p>
                                            <p style="margin:0; font-size:40px; line-height:1; font-weight:bold; letter-spacing:10px; color:#A3BE8C;">
                                                ' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:20px 32px 0 32px;">
                                <p style="margin:0; font-size:15px; line-height:1.7; color:#334155;">
                                    Este código es temporal y fue generado para proteger tu cuenta. No lo compartas con nadie.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:18px 32px 0 32px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#EBCB8B33; border-left:5px solid #EBCB8B; border-radius:8px;">
                                    <tr>
                                        <td style="padding:16px 18px; font-size:14px; line-height:1.6; color:#EBCB8B;">
                                            Si no solicitaste este cambio, ignorá este correo. Para mayor seguridad, te recomendamos revisar el acceso a tu cuenta.
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:28px 32px 10px 32px;">
                                <p style="margin:0; font-size:14px; line-height:1.6; color:#64748b;">
                                    Este mensaje fue enviado automáticamente por el sistema de seguridad de StockiFy.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:0 32px 32px 32px;">
                                <p style="margin:0; font-size:14px; line-height:1.6; color:#64748b;">
                                    ¿Necesitás ayuda? Escribinos a
                                    <a href="mailto:soporte@stockify.app" style="color:#A3BE8C; text-decoration:none; font-weight:bold;">
                                        soporte@stockify.app
                                    </a>
                                </p>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
    }
}