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
                    $mail->Subject = 'StockiFy Seguridad: Solicitud de cambio de contrase\u00f1a';
                    $mail->AltBody = "Hola {$userName}, tu código de verificación para cambiar la contraseña es: {$otpCode}. Este código es temporal. Si no solicitaste este cambio, ignorá este correo.";
                    break;

                case 'delete_inventory':
                    $mail->Subject = 'ALERTA CRÍTICA: Solicitud de eliminación de inventario';
                    $mail->Subject = 'StockiFy Seguridad: Solicitud de eliminaci\u00f3n de inventario';
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

            $mail->Subject = 'StockiFy Alertas: Stock crítico de ' . $productName;
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

            $mail->Subject = 'StockiFy Alertas: Margen negativo en ' . $productName;
            $mail->Body = $this->generateNegativeProfitEmailHtml($userName, $productName, $salePrice, $costPrice);
            $mail->AltBody = "Hola {$userName}, detectamos una venta de '{$productName}' a $ {$salePrice}, pero tu costo de compra es $ {$costPrice}. Estas perdiendo dinero.";

            return $mail->send();
        } catch (Exception | \Throwable $e) {
            error_log("MailService::sendNegativeProfitAlert error: " . $e->getMessage());
            return false;
        }
    }

    public function sendOutOfStockAlert(
        string $toEmail,
        string $userName,
        string $productName,
        string $inventoryName = 'Principal'
    ): bool {
        try {
            $mail = $this->getMailer();
            $mail->setFrom(MAIL_FROM_SECURITY, 'StockiFy Alertas');
            $mail->addAddress($toEmail);
            $mail->isHTML(true);

            $mail->Subject = 'StockiFy Alertas: Producto AGOTADO - ' . $productName;
            $mail->Body = $this->generateOutOfStockEmailHtml($userName, $productName, $inventoryName);
            $mail->AltBody = "Hola {$userName}, el producto '{$productName}' se ha quedado sin stock (0 unidades disponibles) en el inventario {$inventoryName}.";

            return $mail->send();
        } catch (Exception | \Throwable $e) {
            error_log("MailService::sendOutOfStockAlert error: " . $e->getMessage());
            return false;
        }
    }

    public function sendWeeklyBalance(
        string $toEmail,
        string $userName,
        string $dateRange,
        float $totalSales,
        float $totalPurchases,
        float $balance,
        string $inventoryName = ''
    ): bool {
        try {
            $mail = $this->getMailer();
            $mail->setFrom(MAIL_FROM_SECURITY, 'StockiFy Reportes');
            $mail->addAddress($toEmail);
            $mail->isHTML(true);

            $subjectInv = $inventoryName ? " - $inventoryName" : "";
            $mail->Subject = 'StockiFy Reportes: Resumen Semanal ' . $dateRange . $subjectInv;
            $mail->Body = $this->generateWeeklyBalanceEmailHtml($userName, $dateRange, $totalSales, $totalPurchases, $balance, $inventoryName);
            $mail->AltBody = "Hola {$userName}, tu resumen semanal del {$dateRange} para {$inventoryName}: Ingresos $ {$totalSales} | Egresos $ {$totalPurchases} | Balance Final: $ {$balance}.";

            return $mail->send();
        } catch (Exception | \Throwable $e) {
            error_log("MailService::sendWeeklyBalance error: " . $e->getMessage());
            return false;
        }
    }

    public function sendDailyBalance(
        string $toEmail,
        string $userName,
        string $date,
        float $totalSales,
        float $totalPurchases,
        float $balance,
        string $inventoryName = ''
    ): bool {
        try {
            $mail = $this->getMailer();
            $mail->setFrom(MAIL_FROM_SECURITY, 'StockiFy Reportes');
            $mail->addAddress($toEmail);
            $mail->isHTML(true);

            $subjectInv = $inventoryName ? " - $inventoryName" : "";
            $mail->Subject = 'StockiFy Reportes: Cierre de caja del ' . $date . $subjectInv;
            $mail->Body = $this->generateDailyBalanceEmailHtml($userName, $date, $totalSales, $totalPurchases, $balance, $inventoryName);
            $mail->AltBody = "Hola {$userName}, tu balance del {$date} para {$inventoryName}: Ingresos $ {$totalSales} | Egresos $ {$totalPurchases} | Balance Final: $ {$balance}.";

            return $mail->send();
        } catch (Exception | \Throwable $e) {
            error_log("MailService::sendDailyBalance error: " . $e->getMessage());
            return false;
        }
    }

    private function generateDeleteInventoryOtpEmailHtml(string $code, string $userName = 'Usuario'): string
    {
        $c = '#BF616A';
        $safe = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $sc = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $x = "<h2 style='font-family:Outfit,Arial,sans-serif;color:{$c};margin:0 0 4px;font-size:26px;font-weight:700;'>Eliminaci&oacute;n de inventario</h2><p style='font-family:Outfit,Arial,sans-serif;color:#999;font-size:12px;text-transform:uppercase;margin:0 0 24px;'>Alerta de seguridad &mdash; acci&oacute;n irreversible</p><p style='font-family:Outfit,Arial,sans-serif;color:#333;font-size:15px;line-height:1.7;margin:0 0 12px;'>Hola <strong>{$safe}</strong>,</p><p style='font-family:Outfit,Arial,sans-serif;color:#555;font-size:15px;line-height:1.7;margin:0 0 24px;'>Recibimos una solicitud para <strong style='color:{$c};'>eliminar permanentemente</strong> un inventario de tu cuenta. Esta acci&oacute;n es irreversible.</p><table role='presentation' width='100%' cellspacing='0' cellpadding='0' border='0' style='margin:0 0 20px;'><tr><td align='center'><div style='background:{$c}22;border:2px dashed {$c};border-radius:12px;padding:20px;display:inline-block;'><p style='font-family:Outfit,Arial,sans-serif;margin:0 0 8px;font-size:11px;color:{$c};text-transform:uppercase;letter-spacing:1.5px;font-weight:600;'>C&oacute;digo &mdash; v&aacute;lido 10 minutos</p><p style='font-family:Outfit,Arial,sans-serif;margin:0;font-size:42px;font-weight:700;letter-spacing:12px;color:{$c};'>{$sc}</p></div></td></tr></table><table role='presentation' width='100%' cellspacing='0' cellpadding='0' border='0'><tr><td style='background:{$c}18;border-left:4px solid {$c};border-radius:0 6px 6px 0;padding:12px 16px;'><p style='font-family:Outfit,Arial,sans-serif;margin:0;font-size:14px;color:#7a2a2a;line-height:1.6;'>Si no fuiste vos quien inici&oacute; esta acci&oacute;n, cambi&aacute; tu contrase&ntilde;a de inmediato y contact&aacute; a soporte.</p></td></tr></table>";
        return str_replace('{{content}}', $x, $this->getBaseTemplate($c));
    }

    private function generateSecurityCodeEmailHtml(string $code, string $userName = 'Usuario'): string
    {
        $c = '#88C0D0';
        $safe = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $sc = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $x = "<h2 style='font-family:Outfit,Arial,sans-serif;color:{$c};margin:0 0 4px;font-size:26px;font-weight:700;'>Cambio de contrase&ntilde;a</h2><p style='font-family:Outfit,Arial,sans-serif;color:#999;font-size:12px;text-transform:uppercase;margin:0 0 24px;'>Verificaci&oacute;n de seguridad</p><p style='font-family:Outfit,Arial,sans-serif;color:#333;font-size:15px;line-height:1.7;margin:0 0 12px;'>Hola <strong>{$safe}</strong>,</p><p style='font-family:Outfit,Arial,sans-serif;color:#555;font-size:15px;line-height:1.7;margin:0 0 24px;'>Recibimos una solicitud para cambiar la contrase&ntilde;a de tu cuenta. Ingres&aacute; el siguiente c&oacute;digo en la aplicaci&oacute;n para continuar:</p><table role='presentation' width='100%' cellspacing='0' cellpadding='0' border='0' style='margin:0 0 20px;'><tr><td align='center'><div style='background:{$c}22;border:2px dashed {$c};border-radius:12px;padding:20px;display:inline-block;'><p style='font-family:Outfit,Arial,sans-serif;margin:0 0 8px;font-size:11px;color:{$c};text-transform:uppercase;letter-spacing:1.5px;font-weight:600;'>C&oacute;digo de verificaci&oacute;n</p><p style='font-family:Outfit,Arial,sans-serif;margin:0;font-size:42px;font-weight:700;letter-spacing:12px;color:{$c};'>{$sc}</p></div></td></tr></table><table role='presentation' width='100%' cellspacing='0' cellpadding='0' border='0'><tr><td style='background:#EBCB8B22;border-left:4px solid #EBCB8B;border-radius:0 6px 6px 0;padding:12px 16px;'><p style='font-family:Outfit,Arial,sans-serif;margin:0;font-size:14px;color:#7a6a00;line-height:1.6;'>Si no realizaste esta solicitud, ignor&aacute; este correo. Tu contrase&ntilde;a no va a cambiar a menos que ingres&eacute;s el c&oacute;digo.</p></td></tr></table>";
        return str_replace('{{content}}', $x, $this->getBaseTemplate($c));
    }

    private function generateLowStockEmailHtml(string $userName, string $productName, float $currentStock, float $minStock): string
    {
        $c = '#EBCB8B';
        $safe = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $sp = htmlspecialchars($productName, ENT_QUOTES, 'UTF-8');
        $x = "<h2 style='font-family:Outfit,Arial,sans-serif;color:{$c};margin:0 0 4px;font-size:26px;font-weight:700;'>Stock cr&iacute;tico detectado</h2><p style='font-family:Outfit,Arial,sans-serif;color:#999;font-size:12px;text-transform:uppercase;margin:0 0 24px;'>Alerta de inventario</p><p style='font-family:Outfit,Arial,sans-serif;color:#333;font-size:15px;line-height:1.7;margin:0 0 12px;'>Hola <strong>{$safe}</strong>,</p><p style='font-family:Outfit,Arial,sans-serif;color:#555;font-size:15px;line-height:1.7;margin:0 0 24px;'>El sistema detect&oacute; que <strong>{$sp}</strong> alcanz&oacute; su l&iacute;mite de stock cr&iacute;tico. Es momento de contactar a tu proveedor.</p><div style='background:{$c}22;border:2px dashed {$c};border-radius:12px;padding:20px;margin-bottom:20px;'><p style='font-family:Outfit,Arial,sans-serif;margin:0 0 14px;font-size:16px;font-weight:700;color:#1a1a1a;text-align:center;'>{$sp}</p><table role='presentation' width='100%' cellspacing='0' cellpadding='0' border='0'><tr><td style='text-align:center;width:50%;'><p style='font-family:Outfit,Arial,sans-serif;margin:0 0 4px;font-size:11px;color:{$c};text-transform:uppercase;font-weight:600;'>Stock actual</p><p style='font-family:Outfit,Arial,sans-serif;margin:0;font-size:36px;font-weight:700;color:{$c};'>{$currentStock}</p></td><td style='text-align:center;width:50%;border-left:1px solid {$c}55;'><p style='font-family:Outfit,Arial,sans-serif;margin:0 0 4px;font-size:11px;color:#888;text-transform:uppercase;font-weight:600;'>M&iacute;nimo ideal</p><p style='font-family:Outfit,Arial,sans-serif;margin:0;font-size:36px;font-weight:700;color:#888;'>{$minStock}</p></td></tr></table></div><p style='font-family:Outfit,Arial,sans-serif;color:#888;font-size:13px;line-height:1.7;margin:0;font-style:italic;'>Mantener el stock en niveles adecuados es clave para no perder ventas.</p>";
        return str_replace('{{content}}', $x, $this->getBaseTemplate($c));
    }

    private function generateNegativeProfitEmailHtml(string $userName, string $productName, float $salePrice, float $costPrice): string
    {
        $c = '#D08770';
        $loss = $costPrice - $salePrice;
        $safe = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $sp = htmlspecialchars($productName, ENT_QUOTES, 'UTF-8');
        $sf = '$ ' . number_format($salePrice, 2, ',', '.');
        $cf = '$ ' . number_format($costPrice, 2, ',', '.');
        $lf = '$ ' . number_format($loss, 2, ',', '.');
        $x = "<h2 style='font-family:Outfit,Arial,sans-serif;color:{$c};margin:0 0 4px;font-size:26px;font-weight:700;'>Margen negativo detectado</h2><p style='font-family:Outfit,Arial,sans-serif;color:#999;font-size:12px;text-transform:uppercase;margin:0 0 24px;'>Alerta de rentabilidad</p><p style='font-family:Outfit,Arial,sans-serif;color:#333;font-size:15px;line-height:1.7;margin:0 0 12px;'>Hola <strong>{$safe}</strong>,</p><p style='font-family:Outfit,Arial,sans-serif;color:#555;font-size:15px;line-height:1.7;margin:0 0 24px;'>Procesamos una venta de <strong>{$sp}</strong> donde el precio cobrado es <strong style='color:{$c};'>inferior al costo de compra registrado</strong>.</p><div style='background:{$c}22;border:2px dashed {$c};border-radius:12px;padding:20px;margin-bottom:20px;'><table role='presentation' width='100%' cellspacing='0' cellpadding='0' border='0'><tr><td style='text-align:center;width:50%;'><p style='font-family:Outfit,Arial,sans-serif;margin:0 0 4px;font-size:11px;color:{$c};text-transform:uppercase;font-weight:600;'>Precio de venta</p><p style='font-family:Outfit,Arial,sans-serif;margin:0;font-size:28px;font-weight:700;color:{$c};'>{$sf}</p></td><td style='text-align:center;width:50%;border-left:1px solid {$c}55;'><p style='font-family:Outfit,Arial,sans-serif;margin:0 0 4px;font-size:11px;color:#888;text-transform:uppercase;font-weight:600;'>Costo de compra</p><p style='font-family:Outfit,Arial,sans-serif;margin:0;font-size:28px;font-weight:700;color:#888;'>{$cf}</p></td></tr></table><p style='font-family:Outfit,Arial,sans-serif;margin:12px 0 0;font-size:13px;color:{$c};text-align:center;font-weight:600;'>P&eacute;rdida: {$lf} por unidad</p></div><p style='font-family:Outfit,Arial,sans-serif;color:#888;font-size:13px;line-height:1.7;margin:0;font-style:italic;'>Verific&aacute; si fue un error de tipeo, un descuento excesivo, o si los precios necesitan actualizarse.</p>";
        return str_replace('{{content}}', $x, $this->getBaseTemplate($c));
    }

    private function generateDailyBalanceEmailHtml(string $userName, string $date, float $totalSales, float $totalPurchases, float $balance, string $inventoryName = ''): string
    {
        $c = $balance >= 0 ? '#A3BE8C' : '#BF616A';
        $safe = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $sd = htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
        $si = htmlspecialchars($inventoryName, ENT_QUOTES, 'UTF-8');
        $bf = '$ ' . number_format($balance, 2, ',', '.');
        $sf = '$ ' . number_format($totalSales, 2, ',', '.');
        $pf = '$ ' . number_format($totalPurchases, 2, ',', '.');
        $invHtml = $si ? "<p style='font-family:Outfit,Arial,sans-serif;color:{$c};font-size:16px;font-weight:700;margin:0 0 24px;text-transform:uppercase;'>Inventario: {$si}</p>" : "";
        $x = "<h2 style='font-family:Outfit,Arial,sans-serif;color:{$c};margin:0 0 4px;font-size:26px;font-weight:700;'>Cierre de caja</h2><p style='font-family:Outfit,Arial,sans-serif;color:#999;font-size:12px;text-transform:uppercase;margin:0 0 24px;'>Resumen diario &mdash; {$sd}</p>{$invHtml}<p style='font-family:Outfit,Arial,sans-serif;color:#333;font-size:15px;line-height:1.7;margin:0 0 12px;'>Hola <strong>{$safe}</strong>,</p><p style='font-family:Outfit,Arial,sans-serif;color:#555;font-size:15px;line-height:1.7;margin:0 0 24px;'>Ac&aacute; ten&eacute;s el balance general de tus movimientos de caja de hoy.</p><div style='background:{$c}22;border:2px dashed {$c};border-radius:12px;padding:20px;margin-bottom:20px;'><table role='presentation' width='100%' cellspacing='0' cellpadding='0' border='0' style='margin-bottom:14px;'><tr><td style='text-align:center;width:50%;'><p style='font-family:Outfit,Arial,sans-serif;margin:0 0 4px;font-size:11px;color:#888;text-transform:uppercase;font-weight:600;'>Ingresos (Ventas)</p><p style='font-family:Outfit,Arial,sans-serif;margin:0;font-size:22px;font-weight:700;color:#555;'>{$sf}</p></td><td style='text-align:center;width:50%;border-left:1px solid #ddd;'><p style='font-family:Outfit,Arial,sans-serif;margin:0 0 4px;font-size:11px;color:#888;text-transform:uppercase;font-weight:600;'>Egresos (Compras)</p><p style='font-family:Outfit,Arial,sans-serif;margin:0;font-size:22px;font-weight:700;color:#555;'>{$pf}</p></td></tr></table><div style='background:#fff;border:2px solid {$c};border-radius:8px;padding:14px;text-align:center;'><p style='font-family:Outfit,Arial,sans-serif;margin:0 0 4px;font-size:11px;color:{$c};text-transform:uppercase;font-weight:600;'>Balance neto</p><p style='font-family:Outfit,Arial,sans-serif;margin:0;font-size:36px;font-weight:900;color:{$c};'>{$bf}</p></div></div><p style='font-family:Outfit,Arial,sans-serif;color:#888;font-size:13px;line-height:1.7;margin:0;font-style:italic;'>Ten&eacute; en claro tu balance diario. Eso te ayuda a prever gastos y calcular la ganancia real de tu negocio.</p>";
        return str_replace('{{content}}', $x, $this->getBaseTemplate($c));
    }

    private function generateOutOfStockEmailHtml(string $userName, string $productName, string $inventoryName): string
    {
        $c = '#BF616A'; // Red color for completely out of stock
        $safe = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $sp = htmlspecialchars($productName, ENT_QUOTES, 'UTF-8');
        $si = htmlspecialchars($inventoryName, ENT_QUOTES, 'UTF-8');
        $x = "<h2 style='font-family:Outfit,Arial,sans-serif;color:{$c};margin:0 0 4px;font-size:26px;font-weight:700;'>Producto Agotado</h2><p style='font-family:Outfit,Arial,sans-serif;color:#999;font-size:12px;text-transform:uppercase;margin:0 0 24px;'>Alerta crítica de inventario</p><p style='font-family:Outfit,Arial,sans-serif;color:#333;font-size:15px;line-height:1.7;margin:0 0 12px;'>Hola <strong>{$safe}</strong>,</p><p style='font-family:Outfit,Arial,sans-serif;color:#555;font-size:15px;line-height:1.7;margin:0 0 24px;'>Te informamos que el producto <strong>{$sp}</strong> se ha quedado completamente sin stock (<strong>0 unidades disponibles</strong>) en el inventario <strong>{$si}</strong>. Te sugerimos reponerlo a la brevedad para no perder ventas.</p><div style='background:{$c}22;border:2px dashed {$c};border-radius:12px;padding:20px;margin-bottom:20px;text-align:center;'><p style='font-family:Outfit,Arial,sans-serif;margin:0;font-size:18px;font-weight:700;color:{$c};'>{$sp}</p><p style='font-family:Outfit,Arial,sans-serif;margin:8px 0 0;font-size:14px;color:#555;'>Stock actual: <strong>0 unidades</strong></p></div>";
        return str_replace('{{content}}', $x, $this->getBaseTemplate($c));
    }

    private function generateWeeklyBalanceEmailHtml(string $userName, string $dateRange, float $totalSales, float $totalPurchases, float $balance, string $inventoryName = ''): string
    {
        $c = $balance >= 0 ? '#A3BE8C' : '#BF616A';
        $safe = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $sdr = htmlspecialchars($dateRange, ENT_QUOTES, 'UTF-8');
        $si = htmlspecialchars($inventoryName, ENT_QUOTES, 'UTF-8');
        $bf = '$ ' . number_format($balance, 2, ',', '.');
        $sf = '$ ' . number_format($totalSales, 2, ',', '.');
        $pf = '$ ' . number_format($totalPurchases, 2, ',', '.');
        $invHtml = $si ? "<p style='font-family:Outfit,Arial,sans-serif;color:{$c};font-size:16px;font-weight:700;margin:0 0 24px;text-transform:uppercase;'>Inventario: {$si}</p>" : "";
        $x = "<h2 style='font-family:Outfit,Arial,sans-serif;color:{$c};margin:0 0 4px;font-size:26px;font-weight:700;'>Cierre semanal</h2><p style='font-family:Outfit,Arial,sans-serif;color:#999;font-size:12px;text-transform:uppercase;margin:0 0 24px;'>Resumen Semanal &mdash; {$sdr}</p>{$invHtml}<p style='font-family:Outfit,Arial,sans-serif;color:#333;font-size:15px;line-height:1.7;margin:0 0 12px;'>Hola <strong>{$safe}</strong>,</p><p style='font-family:Outfit,Arial,sans-serif;color:#555;font-size:15px;line-height:1.7;margin:0 0 24px;'>Ac&aacute; ten&eacute;s el balance general de tus movimientos de caja de la última semana.</p><div style='background:{$c}22;border:2px dashed {$c};border-radius:12px;padding:20px;margin-bottom:20px;'><table role='presentation' width='100%' cellspacing='0' cellpadding='0' border='0' style='margin-bottom:14px;'><tr><td style='text-align:center;width:50%;'><p style='font-family:Outfit,Arial,sans-serif;margin:0 0 4px;font-size:11px;color:#888;text-transform:uppercase;font-weight:600;'>Ingresos (Ventas)</p><p style='font-family:Outfit,Arial,sans-serif;margin:0;font-size:22px;font-weight:700;color:#555;'>{$sf}</p></td><td style='text-align:center;width:50%;border-left:1px solid #ddd;'><p style='font-family:Outfit,Arial,sans-serif;margin:0 0 4px;font-size:11px;color:#888;text-transform:uppercase;font-weight:600;'>Egresos (Compras)</p><p style='font-family:Outfit,Arial,sans-serif;margin:0;font-size:22px;font-weight:700;color:#555;'>{$pf}</p></td></tr></table><div style='background:#fff;border:2px solid {$c};border-radius:8px;padding:14px;text-align:center;'><p style='font-family:Outfit,Arial,sans-serif;margin:0 0 4px;font-size:11px;color:{$c};text-transform:uppercase;font-weight:600;'>Balance neto</p><p style='font-family:Outfit,Arial,sans-serif;margin:0;font-size:36px;font-weight:900;color:{$c};'>{$bf}</p></div></div><p style='font-family:Outfit,Arial,sans-serif;color:#888;font-size:13px;line-height:1.7;margin:0;font-style:italic;'>Monitorear tu rendimiento semanal es vital para la salud financiera a mediano plazo de tu negocio.</p>";
        return str_replace('{{content}}', $x, $this->getBaseTemplate($c));
    }

    public function sendRestockReport(
        string $toEmail,
        string $userName,
        string $inventoryName,
        array $productsList
    ): bool {
        try {
            $mail = $this->getMailer();
            $mail->setFrom(MAIL_FROM_SECURITY, 'StockiFy Reportes');
            $mail->addAddress($toEmail);
            $mail->isHTML(true);

            $mail->Subject = 'Reporte Masivo de Reposición - ' . $inventoryName;
            $mail->Body = $this->generateRestockReportEmailHtml($userName, $inventoryName, $productsList);
            $mail->AltBody = "Hola {$userName}, adjuntamos el reporte de productos con stock critico en tu inventario {$inventoryName} que requieren reposicion inmediata.";

            return $mail->send();
        } catch (Exception | \Throwable $e) {
            error_log("MailService::sendRestockReport error: " . $e->getMessage());
            return false;
        }
    }


    private function generateRestockReportEmailHtml(string $userName, string $inventoryName, array $productsList): string
    {
        $c = '#B48EAD';
        $safe = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $si = htmlspecialchars($inventoryName, ENT_QUOTES, 'UTF-8');
        $count = count($productsList);
        $rows = '';
        foreach ($productsList as $p) {
            $n = htmlspecialchars($p['name'] ?? 'Producto Desconocido', ENT_QUOTES, 'UTF-8');
            $cur = htmlspecialchars((string) ($p['current'] ?? 0), ENT_QUOTES, 'UTF-8');
            $fal = htmlspecialchars((string) ($p['faltante'] ?? 0), ENT_QUOTES, 'UTF-8');
            $rows .= "<tr><td style='font-family:Outfit,Arial,sans-serif;padding:10px 14px;border-bottom:1px solid #f0e4ef;color:#2d1a2d;'>{$n}</td><td style='font-family:Outfit,Arial,sans-serif;padding:10px 14px;border-bottom:1px solid #f0e4ef;color:#7a6478;text-align:center;'>{$cur}</td><td style='font-family:Outfit,Arial,sans-serif;padding:10px 14px;border-bottom:1px solid #f0e4ef;color:#BF616A;text-align:center;font-weight:700;'>{$fal}</td></tr>";
        }
        $x = "<h2 style='font-family:Outfit,Arial,sans-serif;color:{$c};margin:0 0 4px;font-size:26px;font-weight:700;'>Reporte de Reposici&oacute;n</h2><p style='font-family:Outfit,Arial,sans-serif;color:#999;font-size:12px;text-transform:uppercase;margin:0 0 24px;'>{$count} producto(s) en estado cr&iacute;tico</p><p style='font-family:Outfit,Arial,sans-serif;color:#333;font-size:15px;line-height:1.7;margin:0 0 12px;'>Hola <strong>{$safe}</strong>,</p><p style='font-family:Outfit,Arial,sans-serif;color:#555;font-size:15px;line-height:1.7;margin:0 0 24px;'>Solicitaste un reporte de productos con stock cr&iacute;tico para tu inventario <strong style='color:{$c};'>{$si}</strong>. A continuaci&oacute;n, las cantidades a reponer:</p><table width='100%' cellspacing='0' cellpadding='0' border='0' style='border-collapse:collapse;margin-bottom:20px;'><thead><tr style='background:#f5eef5;'><th style='font-family:Outfit,Arial,sans-serif;padding:10px 14px;border-bottom:2px solid #e8d4e8;color:{$c};font-size:12px;text-transform:uppercase;text-align:left;'>Producto</th><th style='font-family:Outfit,Arial,sans-serif;padding:10px 14px;border-bottom:2px solid #e8d4e8;color:{$c};font-size:12px;text-transform:uppercase;text-align:center;'>Stock actual</th><th style='font-family:Outfit,Arial,sans-serif;padding:10px 14px;border-bottom:2px solid #e8d4e8;color:#BF616A;font-size:12px;text-transform:uppercase;text-align:center;'>A reponer</th></tr></thead><tbody>{$rows}</tbody></table><p style='font-family:Outfit,Arial,sans-serif;color:#9c8498;font-size:13px;line-height:1.7;margin:0;font-style:italic;'>Pod&eacute;s reenviar este correo directamente a tu proveedor para gestionar el pedido.</p>";
        return str_replace('{{content}}', $x, $this->getBaseTemplate($c));
    }

    private function generateInvitationEmailHtml(string $inventoryName, string $roleName, string $inviteLink, string $senderName): string
    {
        $c = '#B48EAD'; // Violet requested by user
        $safeInv = htmlspecialchars($inventoryName, ENT_QUOTES, 'UTF-8');
        $safeSender = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');
        $safeRole = htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8');
        
        $x = "<h2 style='font-family:Outfit,Arial,sans-serif;color:{$c};margin:0 0 4px;font-size:26px;font-weight:700;'>Invitaci&oacute;n de Colaboraci&oacute;n</h2><p style='font-family:Outfit,Arial,sans-serif;color:#999;font-size:12px;text-transform:uppercase;margin:0 0 24px;'>Sistema de Usuarios &mdash; StockiFy</p><p style='font-family:Outfit,Arial,sans-serif;color:#333;font-size:15px;line-height:1.7;margin:0 0 12px;'>Hola,</p><p style='font-family:Outfit,Arial,sans-serif;color:#555;font-size:15px;line-height:1.7;margin:0 0 24px;'><strong>{$safeSender}</strong> te ha invitado a unirte al inventario <strong style='color:{$c};'>{$safeInv}</strong> en StockiFy con el rol de <strong>{$safeRole}</strong>.</p><table role='presentation' width='100%' cellspacing='0' cellpadding='0' border='0' style='margin:0 0 20px;'><tr><td align='center'><a href='{$inviteLink}' style='display:inline-block;background:{$c};color:#fff;font-family:Outfit,Arial,sans-serif;font-size:16px;font-weight:700;text-decoration:none;padding:14px 28px;border-radius:8px;'>Aceptar Invitaci&oacute;n</a></td></tr></table><table role='presentation' width='100%' cellspacing='0' cellpadding='0' border='0'><tr><td style='background:{$c}18;border-left:4px solid {$c};border-radius:0 6px 6px 0;padding:12px 16px;'><p style='font-family:Outfit,Arial,sans-serif;margin:0;font-size:14px;color:#5c3a56;line-height:1.6;'>Si no esperabas esta invitaci&oacute;n o no conoces al remitente, puedes ignorar este correo de forma segura.</p></td></tr></table>";
        return str_replace('{{content}}', $x, $this->getBaseTemplate($c));
    }

    /**
     * Envía un email de notificación a un usuario que fue agregado como colaborador.
     * Con la nueva política, la invitación ya está aceptada — este email es solo notificación.
     */
    public function sendInvitationEmail(
        string $toEmail,
        string $inventoryName,
        string $roleName,
        string $dashboardLink,
        string $senderName
    ): bool {
        try {
            $mail = $this->getMailer();
            $mail->setFrom(MAIL_FROM_SECURITY, MAIL_NAME_SECURITY);
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject = "¡Fuiste invitado a colaborar en StockiFy!";
            $mail->AltBody = "{$senderName} te agregó al inventario \"{$inventoryName}\" como {$roleName}. Ingresá a: {$dashboardLink}";
            $mail->Body    = $this->generateInvitationEmailHtml($inventoryName, $roleName, $dashboardLink, $senderName);
            return $mail->send();
        } catch (\Exception $e) {
            error_log("MailService::sendInvitationEmail error: " . $e->getMessage());
            return false;
        }
    }

    private function getBaseTemplate(string $color = '#B48EAD'): string
    {
        $logo = 'https://stockify.com.ar/assets/img/LogoE3.png';
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>StockiFy</title><link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700" rel="stylesheet"></head><body style="margin:0;padding:0;background:#f4f4f6;font-family:Outfit,Arial,sans-serif;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f4f4f6;padding:36px 0;"><tr><td align="center" style="padding:0 16px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:620px;background:#fff;border:2px solid ' . $color . ';border-radius:16px;overflow:hidden;"><tr><td style="padding:18px 28px;background:' . $color . ';"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td><img src="' . $logo . '" alt="StockiFy" height="42" style="display:block;border:0;"></td><td align="right"><span style="font-family:Outfit,Arial,sans-serif;font-size:11px;color:rgba(255,255,255,0.75);letter-spacing:1px;text-transform:uppercase;">Notificaci&oacute;n autom&aacute;tica</span></td></tr></table></td></tr><tr><td style="padding:32px 32px 24px;font-family:Outfit,Arial,sans-serif;">{{content}}</td></tr><tr><td style="padding:0 32px;"><div style="height:1px;background:' . $color . ';opacity:0.15;"></div></td></tr><tr><td style="padding:16px 32px 22px;background:#fafafa;"><p style="margin:0;font-family:Outfit,Arial,sans-serif;font-size:12px;color:#999;line-height:1.6;">Mensaje generado autom&aacute;ticamente por <strong style="color:' . $color . ';">StockiFy</strong>. No respond&aacute;s este correo. &mdash; <a href="mailto:soporte@stockify.com.ar" style="color:' . $color . ';text-decoration:none;font-weight:600;">soporte@stockify.com.ar</a></p></td></tr></table></td></tr></table></body></html>';
    }
}

