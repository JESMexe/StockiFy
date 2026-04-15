<?php

declare(strict_types=1)
;

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
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Evitar el error de certificado de Ferozo (*.ferozo.com vs mail.stockify.com.ar)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

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

                case 'delete_inventory':
                    $mail->Subject = '⚠️ ALERTA CRÍTICA: Solicitud de eliminación de inventario';
                    $mail->Body = $this->generateDeleteInventoryOtpEmailHtml($otpCode, $userName);
                    $mail->AltBody = "Hola {$userName}, tu código de verificación para ELIMINAR PERMANENTEMENTE un inventario es: {$otpCode}. Expira en 10 minutos. Si no iniciaste esta acción, cambiá tu contraseña inmediatamente.";
                    break;

                default:
                    throw new \InvalidArgumentException('Tipo de acción OTP no soportado.');
            }

            return $mail->send();
        } catch (Exception | \Throwable $e) {
            error_log("MailService::sendSecurityOTP error: " . $e->getMessage());
            return false;
        }
    }

    public function sendLowStockAlert(
        string $toEmail,
        string $userName,
        string $productName,
        float $currentStock,
        float $minStock
    ): bool {
        try {
            $mail = $this->getMailer();
            $mail->setFrom(MAIL_FROM_SECURITY, 'StockiFy Alertas');
            $mail->addAddress($toEmail);
            $mail->isHTML(true);

            $mail->Subject = 'ALERTA: Stock critico de ' . $productName;
            $mail->Body = $this->generateLowStockEmailHtml($userName, $productName, $currentStock, $minStock);
            $mail->AltBody = "Hola {$userName}, el producto '{$productName}' ha alcanzado un stock critico de {$currentStock} (Min: {$minStock}).";

            return $mail->send();
        } catch (Exception | \Throwable $e) {
            error_log("MailService::sendLowStockAlert error: " . $e->getMessage());
            return false;
        }
    }

    public function sendNegativeProfitAlert(
        string $toEmail,
        string $userName,
        string $productName,
        float $salePrice,
        float $costPrice
    ): bool {
        try {
            $mail = $this->getMailer();
            $mail->setFrom(MAIL_FROM_SECURITY, 'StockiFy Alertas');
            $mail->addAddress($toEmail);
            $mail->isHTML(true);

            $mail->Subject = 'PELIGRO: Ganancia Negativa en ' . $productName;
            $mail->Body = $this->generateNegativeProfitEmailHtml($userName, $productName, $salePrice, $costPrice);
            $mail->AltBody = "Hola {$userName}, detectamos una venta de '{$productName}' a $ {$salePrice}, pero tu costo de compra es $ {$costPrice}. Estas perdiendo dinero.";

            return $mail->send();
        } catch (Exception | \Throwable $e) {
            error_log("MailService::sendNegativeProfitAlert error: " . $e->getMessage());
            return false;
        }
    }

    public function sendDailyBalance(
        string $toEmail,
        string $userName,
        string $date,
        float $totalSales,
        float $totalPurchases,
        float $balance
    ): bool {
        try {
            $mail = $this->getMailer();
            $mail->setFrom(MAIL_FROM_SECURITY, 'StockiFy Reportes');
            $mail->addAddress($toEmail);
            $mail->isHTML(true);

            $mail->Subject = '📄 Tu Cierre de Caja Diario (' . $date . ')';
            $mail->Body = $this->generateDailyBalanceEmailHtml($userName, $date, $totalSales, $totalPurchases, $balance);
            $mail->AltBody = "Hola {$userName}, tu balance del {$date}: Ingresos $ {$totalSales} | Egresos $ {$totalPurchases} | Balance Final: $ {$balance}.";

            return $mail->send();
        } catch (Exception | \Throwable $e) {
            error_log("MailService::sendDailyBalance error: " . $e->getMessage());
            return false;
        }
    }

    private function generateDeleteInventoryOtpEmailHtml(string $code, string $userName = 'Usuario'): string
    {
        return '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Alerta Crítica de Seguridad | StockiFy</title>
    </head>
    <body style="margin:0; padding:0; background-color:#BF616A22; font-family:Arial, Helvetica, sans-serif; color:#1a1a1a;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f3f4f6; margin:0; padding:32px 0;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px; background:#ffffff; border:3px solid #BF616A; border-radius:14px; overflow:hidden;">
                        
                        <tr>
                            <td style="padding:28px 32px; border-bottom:3px solid #BF616A; background:#BF616A11;">
                                <div style="font-size:28px; font-weight:bold; color:#1a1a1a;">StockiFy</div>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:36px 32px 16px 32px;">
                                <p style="margin:0 0 10px 0; font-size:14px; color:#BF616A; font-weight:bold; text-transform:uppercase; letter-spacing:1px;">
                                    ⚠️ Alerta Crítica de Seguridad
                                </p>
                                <h1 style="margin:0 0 18px 0; font-size:28px; line-height:1.2; color:#1a1a1a;">
                                    Solicitud de Eliminación de Inventario
                                </h1>
                                <p style="margin:0; font-size:16px; line-height:1.7; color:#475569;">
                                    Hola <strong>' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '</strong>, alguien solicitó eliminar <strong>permanentemente</strong> un inventario en tu cuenta de StockiFy. Usá el siguiente código para confirmar esta acción.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:10px 32px 8px 32px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#BF616A22; border:2px dashed #BF616A; border-radius:12px;">
                                    <tr>
                                        <td align="center" style="padding:28px 16px;">
                                            <p style="margin:0 0 12px 0; font-size:13px; color:#BF616A; font-weight:bold; text-transform:uppercase; letter-spacing:1px;">
                                                Código de Confirmación — Válido por 10 minutos
                                            </p>
                                            <p style="margin:0; font-size:42px; line-height:1; font-weight:bold; letter-spacing:12px; color:#BF616A;">
                                                ' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:18px 32px 0 32px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#BF616A33; border-left:5px solid #BF616A; border-radius:8px;">
                                    <tr>
                                        <td style="padding:16px 18px; font-size:14px; line-height:1.6; color:#7d2a2a; font-weight:bold;">
                                            🔴 Si no sos vos quien realizó esta solicitud, NO compartas este código. Esta acción es irreversible. Cambiá tu contraseña inmediatamente y contactá soporte.
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:20px 32px 0 32px;">
                                <p style="margin:0; font-size:15px; line-height:1.7; color:#334155;">
                                    Este código expira automáticamente a los 10 minutos de haber sido generado. No lo compartas con nadie.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:28px 32px 32px 32px;">
                                <p style="margin:0; font-size:14px; line-height:1.6; color:#64748b;">
                                    ¿Necesitás ayuda? Escribinos a
                                    <a href="mailto:soporte@stockify.com.ar" style="color:#BF616A; text-decoration:none; font-weight:bold;">
                                        soporte@stockify.com.ar
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
                                    <a href="mailto:soporte@stockify.com.ar" style="color:#A3BE8C; text-decoration:none; font-weight:bold;">
                                        soporte@stockify.com.ar
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

    private function generateLowStockEmailHtml(string $userName, string $productName, float $currentStock, float $minStock): string
    {
        return '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Alerta de Stock | StockiFy</title>
    </head>
    <body style="margin:0; padding:0; background-color:#BF616A22; font-family:Arial, Helvetica, sans-serif; color:#1a1a1a;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f3f4f6; margin:0; padding:32px 0;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px; background:#ffffff; border:2px solid #1a1a1a; border-radius:14px; overflow:hidden;">
                        
                        <tr>
                            <td style="padding:28px 32px; border-bottom:1px solid #e5e7eb;">
                                <div style="font-size:28px; font-weight:bold; color:#1a1a1a;">StockiFy</div>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:36px 32px 16px 32px;">
                                <p style="margin:0 0 10px 0; font-size:14px; color:#BF616A; font-weight:bold; text-transform:uppercase; letter-spacing:1px; font-family: Satoshi, sans-serif;">
                                    Reporte de Inventario
                                </p>
                                <h1 style="margin:0 0 18px 0; font-size:30px; line-height:1.2; color:#1a1a1a;">
                                    Alerta de Stock Crítico
                                </h1>
                                <p style="margin:0; font-size:16px; line-height:1.7; color:#475569;">
                                    Hola <strong>' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '</strong>, tu sistema informático ha detectado automáticamente que un producto de tu inventario alcanzó la línea roja.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:10px 32px 8px 32px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#BF616A33; border:2px dashed #BF616A; border-radius:12px;">
                                    <tr>
                                        <td align="center" style="padding:28px 16px;">
                                            <p style="margin:0 0 12px 0; font-size:18px; color:#1a1a1a; font-weight:bold; letter-spacing:0px;">
                                                ' . htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') . '
                                            </p>
                                            <div style="display:flex; justify-content:center; gap:20px;">
                                                <div>
                                                    <p style="margin:0; font-size:12px; color:#BF616A; font-weight:bold; text-transform:uppercase;">Stock Actual</p>
                                                    <p style="margin:0; font-size:30px; font-weight:bold; color:#BF616A;">' . $currentStock . '</p>
                                                </div>
                                                <div style="border-left: 2px solid #BF616A; margin: 0 15px;"></div>
                                                <div>
                                                    <p style="margin:0; font-size:12px; color:#64748b; font-weight:bold; text-transform:uppercase;">Mínimo Ideal</p>
                                                    <p style="margin:0; font-size:30px; font-weight:bold; color:#64748b;">' . $minStock . '</p>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:20px 32px 0 32px;">
                                <p style="margin:0; font-size:15px; line-height:1.7; color:#334155;">
                                    Es momento de contactar a tus proveedores. Recordá que mantener el stock saludable es clave para no perder ventas.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:28px 32px 32px 32px;">
                                <p style="margin:0; font-size:14px; line-height:1.6; color:#64748b;">
                                    Notificación automatizada por tu Asistente Empresarial StockiFy SaaS.
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

    private function generateNegativeProfitEmailHtml(string $userName, string $productName, float $salePrice, float $costPrice): string
    {
        $loss = $costPrice - $salePrice;
        return '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ganancia Negativa | StockiFy</title>
    </head>
    <body style="margin:0; padding:0; background-color:#D0877022; font-family:Arial, Helvetica, sans-serif; color:#1a1a1a;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f3f4f6; margin:0; padding:32px 0;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px; background:#ffffff; border:2px solid #1a1a1a; border-radius:14px; overflow:hidden;">
                        
                        <tr>
                            <td style="padding:28px 32px; border-bottom:1px solid #e5e7eb;">
                                <div style="font-size:28px; font-weight:bold; color:#1a1a1a;">StockiFy</div>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:36px 32px 16px 32px;">
                                <p style="margin:0 0 10px 0; font-size:14px; color:#D08770; font-weight:bold; text-transform:uppercase; letter-spacing:1px; font-family: Satoshi, sans-serif;">
                                    Reporte de Rentabilidad
                                </p>
                                <h1 style="margin:0 0 18px 0; font-size:30px; line-height:1.2; color:#1a1a1a;">
                                    Alerta de Márgen Negativo
                                </h1>
                                <p style="margin:0; font-size:16px; line-height:1.7; color:#475569;">
                                    Hola <strong>' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '</strong>, acabamos de procesar una venta donde el precio final cobrado es <strong>inferior</strong> al costo de mercadería registrado.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:10px 32px 8px 32px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#D0877033; border:2px dashed #D08770; border-radius:12px;">
                                    <tr>
                                        <td align="center" style="padding:28px 16px;">
                                            <p style="margin:0 0 12px 0; font-size:18px; color:#1a1a1a; font-weight:bold; letter-spacing:0px;">
                                                ' . htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') . '
                                            </p>
                                            <div style="display:flex; justify-content:center; gap:20px;">
                                                <div>
                                                    <p style="margin:0; font-size:12px; color:#D08770; font-weight:bold; text-transform:uppercase;">Precio de Venta</p>
                                                    <p style="margin:0; font-size:24px; font-weight:bold; color:#D08770;">$ ' . number_format($salePrice, 2, ',', '.') . '</p>
                                                </div>
                                                <div style="border-left: 2px solid #D08770; margin: 0 15px;"></div>
                                                <div>
                                                    <p style="margin:0; font-size:12px; color:#64748b; font-weight:bold; text-transform:uppercase;">Costo de Compra</p>
                                                    <p style="margin:0; font-size:24px; font-weight:bold; color:#64748b;">$ ' . number_format($costPrice, 2, ',', '.') . '</p>
                                                </div>
                                            </div>
                                            <div style="margin-top: 15px; font-size: 14px; color: #D08770; font-weight: bold;">
                                                Pérdida neta estimada: $ ' . number_format($loss, 2, ',', '.') . ' por unidad.
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:20px 32px 0 32px;">
                                <p style="margin:0; font-size:15px; line-height:1.7; color:#334155;">
                                    Verifica si esto fue un error de tipeo en mostrador, un descuento manual excesivo, o si necesitas actualizar tus precios de góndola ante la inflación.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:28px 32px 32px 32px;">
                                <p style="margin:0; font-size:14px; line-height:1.6; color:#64748b;">
                                    Notificación automatizada por tu Asistente Empresarial StockiFy SaaS.
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

    private function generateDailyBalanceEmailHtml(string $userName, string $date, float $totalSales, float $totalPurchases, float $balance): string
    {
        $balanceColor = $balance >= 0 ? '#A3BE8C' : '#BF616A'; // Verde si hay profit, Rojo si es pérdida
        return '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Cierre de Caja | StockiFy</title>
    </head>
    <body style="margin:0; padding:0; background-color:#5E81AC22; font-family:Arial, Helvetica, sans-serif; color:#1a1a1a;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f3f4f6; margin:0; padding:32px 0;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px; background:#ffffff; border:2px solid #1a1a1a; border-radius:14px; overflow:hidden;">
                        
                        <tr>
                            <td style="padding:28px 32px; border-bottom:1px solid #e5e7eb;">
                                <div style="font-size:28px; font-weight:bold; color:#1a1a1a;">StockiFy</div>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:36px 32px 16px 32px;">
                                <p style="margin:0 0 10px 0; font-size:14px; color:#5E81AC; font-weight:bold; text-transform:uppercase; letter-spacing:1px; font-family: Satoshi, sans-serif;">
                                    Resumen Automático
                                </p>
                                <h1 style="margin:0 0 18px 0; font-size:30px; line-height:1.2; color:#1a1a1a;">
                                    Cierre de Caja del ' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '
                                </h1>
                                <p style="margin:0; font-size:16px; line-height:1.7; color:#475569;">
                                    Hola <strong>' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '</strong>, el día cerró y aquí tienes el balance general de tus movimientos de caja registrados hoy.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:10px 32px 8px 32px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#5E81AC33; border:2px dashed #5E81AC; border-radius:12px;">
                                    <tr>
                                        <td align="center" style="padding:28px 16px;">
                                            <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom: 25px;">
                                                <div style="flex: 1; min-width: 120px;">
                                                    <p style="margin:0; font-size:12px; color:#475569; font-weight:bold; text-transform:uppercase;">Ingresos (Ventas)</p>
                                                    <p style="margin:0; font-size:20px; font-weight:bold; color:#475569;">$ ' . number_format($totalSales, 2, ',', '.') . '</p>
                                                </div>
                                                <div style="flex: 1; min-width: 120px;">
                                                    <p style="margin:0; font-size:12px; color:#475569; font-weight:bold; text-transform:uppercase;">Egresos (Compras)</p>
                                                    <p style="margin:0; font-size:20px; font-weight:bold; color:#475569;">$ ' . number_format($totalPurchases, 2, ',', '.') . '</p>
                                                </div>
                                            </div>
                                            
                                            <div style="background: #ffffff; border: 2px solid ' . $balanceColor . '; border-radius: 8px; padding: 15px; display: inline-block;">
                                                <p style="margin:0; font-size:14px; color:' . $balanceColor . '; font-weight:bold; text-transform:uppercase;">Balance Neto</p>
                                                <p style="margin:0; font-size:34px; font-weight:900; color:' . $balanceColor . ';">$ ' . number_format($balance, 2, ',', '.') . '</p>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:20px 32px 0 32px;">
                                <p style="margin:0; font-size:15px; line-height:1.7; color:#334155;">
                                    ¡Excelente trabajo! Tener claro tu balance diario ayuda a prever gastos y calcular la ganancia real de tu negocio a fin de mes.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:28px 32px 32px 32px;">
                                <p style="margin:0; font-size:14px; line-height:1.6; color:#64748b;">
                                    Notificación automatizada por tu Asistente Empresarial StockiFy SaaS.
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