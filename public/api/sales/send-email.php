<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../auth_helper.php';

$data = json_decode(file_get_contents('php://input'), true);

$emailInfo = $data['emailInfo'];

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


function generateInvoiceHtml($sale)
{
	$items = $sale['items'] ?? [];
	$html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:Arial,Helvetica,sans-serif}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f4f4f4}</style></head><body>';
	$html .= '<h2>Factura - Venta #' . htmlspecialchars($sale['id'] ?? 'N/A') . '</h2>';
	$html .= '<p>Fecha: ' . htmlspecialchars($sale['date'] ?? date('Y-m-d H:i')) . '</p>';
	if (!empty($sale['customer'])) {
		$html .= '<p>Cliente: ' . htmlspecialchars($sale['customer']) . '</p>';
	}

	$html .= '<table><thead><tr><th>Producto</th><th style="width:80px">Cant.</th><th style="width:120px">Precio unit.</th><th style="width:120px">Total</th></tr></thead><tbody>';
	foreach ($items as $it) {
		$name = htmlspecialchars($it['name'] ?? '');
		$qty = htmlspecialchars($it['quantity'] ?? ($it['amount'] ?? 0));
		$unit = number_format((float)($it['unit_price'] ?? ($it['price'] ?? 0)), 2, ',', '.');
		$total = number_format((float)($it['total'] ?? (($it['quantity'] ?? ($it['amount'] ?? 0)) * ($it['unit_price'] ?? ($it['price'] ?? 0)))), 2, ',', '.');
		$html .= "<tr><td>$name</td><td style='text-align:center'>$qty</td><td style='text-align:right'>$unit</td><td style='text-align:right'>$total</td></tr>";
	}
	$html .= '</tbody></table>';
	$totalAmount = number_format((float)($sale['total'] ?? 0), 2, ',', '.');
	$html .= '<h3>Total: ' . $totalAmount . '</h3>';
	$html .= '</body></html>';
	return $html;
}

function sendInvoiceEmail($emailInfo)
{
	if (!$emailInfo || empty($emailInfo['to'])) {
		return ['success' => false, 'error' => 'Destinatario (to) no provisto en emailInfo'];
	}

	$to = $emailInfo['to'];
	$subject = $emailInfo['subject'] ?? 'Factura de su compra';
	$from = $emailInfo['from'] ?? 'no-reply@stockify.local';
	$fromName = $emailInfo['fromName'] ?? 'StockiFy';
	$sale = $emailInfo['sale'] ?? null;
	$bodyHtml = $emailInfo['html'] ?? ($sale ? generateInvoiceHtml($sale) : '<p>Adjunto encontrará su factura.</p>');

	try {
		$mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'no.reply.stockify02@gmail.com';
            $mail->Password   = 'hcgu fjli kali vbcw';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

		$mail->setFrom($from, $fromName);
		$mail->addAddress($to);

		if (!empty($emailInfo['cc'])) {
			foreach ((array)$emailInfo['cc'] as $cc) {
				$mail->addCC($cc);
			}
		}
		if (!empty($emailInfo['bcc'])) {
			foreach ((array)$emailInfo['bcc'] as $bcc) {
				$mail->addBCC($bcc);
			}
		}

		$mail->isHTML(true);
		$mail->Subject = $subject;
		$mail->Body = $bodyHtml;
		$mail->AltBody = strip_tags(str_replace(['<br>','<br/>','</p>','</h2>','</h3>'],' ',$bodyHtml));

		// Adjuntos: si el frontend envía un adjunto en base64 (por ejemplo PDF), se puede usar attachContent + attachName
		if (!empty($emailInfo['attachName']) && !empty($emailInfo['attachContent'])) {
			$content = $emailInfo['attachContent'];
			if (!empty($emailInfo['attachIsBase64'])) {
				$decoded = base64_decode($content);
				if ($decoded === false) throw new Exception('Falló la decodificación base64 del adjunto');
				$mail->addStringAttachment($decoded, $emailInfo['attachName']);
			} else {
				$mail->addStringAttachment($content, $emailInfo['attachName']);
			}
		} else {
			// Adjuntar factura HTML generada
			$invoiceHtml = $bodyHtml;
			$mail->addStringAttachment($invoiceHtml, 'invoice.html', 'base64', 'text/html');
		}

		$mail->send();
		return ['success' => true];
	} catch (Exception $e) {
		return ['success' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
	}
}

$currentUser = null;
// Permitir modo de prueba para facilitar tests locales: enviar { "testMode": true } en el body.
if (!empty($data['testMode'])) {
	$currentUser = ['user_id' => 0, 'full_name' => 'Test User'];
} else {
	$currentUser = getCurrentUser();
}

if (!$currentUser) {
	echo json_encode(['success' => false, 'error' => 'Usuario no autenticado']);
	exit;
}

$result = sendInvoiceEmail($emailInfo);
echo json_encode($result);

