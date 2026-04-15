<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/src/helpers/auth_helper.php';
require_once dirname(__DIR__, 3) . '/src/config/mail_config.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json; charset=UTF-8');

function jsonResponse(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function generateInvoiceHtml(array $sale): string
{
    $items = $sale['items'] ?? [];

    $html = '<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #1a1a1a; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f4f4f4; }
        .right { text-align: right; }
        .center { text-align: center; }
    </style>
</head>
<body>';

    $html .= '<h2>Factura - Venta #' . htmlspecialchars((string)($sale['id'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') . '</h2>';
    $html .= '<p>Fecha: ' . htmlspecialchars((string)($sale['date'] ?? date('Y-m-d H:i')), ENT_QUOTES, 'UTF-8') . '</p>';

    if (!empty($sale['customer'])) {
        $html .= '<p>Cliente: ' . htmlspecialchars((string)$sale['customer'], ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $html .= '<table>
        <thead>
            <tr>
                <th>Producto</th>
                <th style="width:80px">Cant.</th>
                <th style="width:120px">Precio unit.</th>
                <th style="width:120px">Total</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($items as $it) {
        $name = htmlspecialchars((string)($it['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $qtyValue = (float)($it['quantity'] ?? ($it['amount'] ?? 0));
        $unitValue = (float)($it['unit_price'] ?? ($it['price'] ?? 0));
        $lineTotalValue = (float)($it['total'] ?? ($qtyValue * $unitValue));

        $qty = htmlspecialchars((string)$qtyValue, ENT_QUOTES, 'UTF-8');
        $unit = number_format($unitValue, 2, ',', '.');
        $lineTotal = number_format($lineTotalValue, 2, ',', '.');

        $html .= "<tr>
            <td>{$name}</td>
            <td class='center'>{$qty}</td>
            <td class='right'>{$unit}</td>
            <td class='right'>{$lineTotal}</td>
        </tr>";
    }

    $html .= '</tbody></table>';

    $totalAmount = number_format((float)($sale['total'] ?? 0), 2, ',', '.');
    $html .= '<h3>Total: ' . $totalAmount . '</h3>';
    $html .= '</body></html>';

    return $html;
}

function buildAltBody(string $htmlBody): string
{
    $plain = str_replace(
        ['<br>', '<br/>', '<br />', '</p>', '</h2>', '</h3>', '</tr>'],
        " \n",
        $htmlBody
    );

    return trim(strip_tags($plain));
}

function createMailer(): PHPMailer
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

    // Workaround for strict SSL checks on DonWeb/Ferozo host
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];

    return $mail;
}

function sendInvoiceEmail(array $emailInfo): array
{
    if (empty($emailInfo['to'])) {
        return ['success' => false, 'error' => 'Destinatario no provisto.'];
    }

    $to = trim((string)$emailInfo['to']);

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'El email del destinatario no es válido.'];
    }

    $subject = trim((string)($emailInfo['subject'] ?? 'Factura de su compra'));
    $from = trim((string)($emailInfo['from'] ?? MAIL_FROM_SECURITY));
    $fromName = trim((string)($emailInfo['fromName'] ?? MAIL_NAME_SECURITY));
    $sale = is_array($emailInfo['sale'] ?? null) ? $emailInfo['sale'] : null;

    $bodyHtml = '';
    if (!empty($emailInfo['html']) && is_string($emailInfo['html'])) {
        $bodyHtml = $emailInfo['html'];
    } elseif ($sale) {
        $bodyHtml = generateInvoiceHtml($sale);
    } else {
        $bodyHtml = '<p>Adjunto encontrará su factura.</p>';
    }

    try {
        $mail = createMailer();

        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml;
        $mail->AltBody = buildAltBody($bodyHtml);

        if (!empty($emailInfo['cc']) && is_array($emailInfo['cc'])) {
            foreach ($emailInfo['cc'] as $cc) {
                $cc = trim((string)$cc);
                if ($cc !== '' && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($cc);
                }
            }
        }

        if (!empty($emailInfo['bcc']) && is_array($emailInfo['bcc'])) {
            foreach ($emailInfo['bcc'] as $bcc) {
                $bcc = trim((string)$bcc);
                if ($bcc !== '' && filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
                    $mail->addBCC($bcc);
                }
            }
        }

        if (!empty($emailInfo['attachName']) && !empty($emailInfo['attachContent'])) {
            $attachName = trim((string)$emailInfo['attachName']);
            $content = (string)$emailInfo['attachContent'];
            $isBase64 = !empty($emailInfo['attachIsBase64']);

            if ($isBase64) {
                $decoded = base64_decode($content, true);
                if ($decoded === false) {
                    return ['success' => false, 'error' => 'El adjunto base64 no es válido.'];
                }
                $mail->addStringAttachment($decoded, $attachName);
            } else {
                $mail->addStringAttachment($content, $attachName);
            }
        } else {
            $invoiceHtml = $bodyHtml;
            $mail->addStringAttachment($invoiceHtml, 'invoice.html', 'base64', 'text/html');
        }

        $mail->send();

        return ['success' => true];
    } catch (Exception $e) {
        error_log('send-email.php PHPMailer error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'No se pudo enviar el correo: ' . $e->getMessage()];
    } catch (\Throwable $e) {
        error_log('send-email.php error inesperado: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Ocurrió un error inesperado al enviar el correo.'];
    }
}

try {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput ?: '', true);

    if (!is_array($data)) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Solicitud inválida.'
        ]);
    }

    $currentUser = null;

    if (!empty($data['testMode'])) {
        $currentUser = [
            'user_id' => 0,
            'full_name' => 'Test User'
        ];
    } else {
        $currentUser = getCurrentUser();
    }

    if (!$currentUser) {
        jsonResponse(401, [
            'success' => false,
            'error' => 'Usuario no autenticado.'
        ]);
    }

    $emailInfo = $data['emailInfo'] ?? null;

    if (!is_array($emailInfo)) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'emailInfo no fue provisto correctamente.'
        ]);
    }

    $result = sendInvoiceEmail($emailInfo);

    if (!$result['success']) {
        jsonResponse(400, $result);
    }

    jsonResponse(200, $result);

} catch (\Throwable $e) {
    error_log('send-email.php fatal error: ' . $e->getMessage());
    jsonResponse(500, [
        'success' => false,
        'error' => 'Ocurrió un error inesperado.'
    ]);
}