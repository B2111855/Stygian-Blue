<?php
// Quick SMTP send test using PHPMailer and project config
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../helpers/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$to = isset($_GET['to']) ? trim($_GET['to']) : getenv('SMTP_TEST_TO');
if (!$to) {
    echo "Missing recipient. Append ?to=you@example.com to URL.";
    exit;
}
$name = isset($_GET['name']) ? trim($_GET['name']) : $to;

$mail = new PHPMailer(true);
try {
    if (function_exists('tp_configure_mail')) {
        tp_configure_mail($mail);
    } else {
        $cfg = @include __DIR__ . '/../helpers/mail_config.php';
        $mail->isSMTP();
        $mail->Host       = $cfg['host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['username'] ?? '';
        $mail->Password   = $cfg['password'] ?? '';
        $mail->SMTPSecure = ($cfg['encryption'] ?? 'tls') === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($cfg['port'] ?? 587);
        $mail->CharSet    = $cfg['charset'] ?? 'UTF-8';
        $fromEmail        = $cfg['from_email'] ?? ($cfg['username'] ?? 'no-reply@example.com');
        $fromName         = $cfg['from_name'] ?? 'Stygian Blue Studio';
        $mail->setFrom($fromEmail, $fromName);
    }

    $mail->addAddress($to, $name);
    $mail->isHTML(true);
    $mail->Subject = 'SMTP test – Stygian Blue';
    $mail->Body = '<p>Nếu bạn nhận được email này, cấu hình SMTP đang hoạt động.</p>'
        .'<p>Thời gian: '.date('d/m/Y H:i').'</p>'
        .'<p>Máy chủ: '.htmlspecialchars($mail->Host, ENT_QUOTES, 'UTF-8').'</p>';
    $mail->send();
    echo json_encode(['ok' => true, 'message' => 'Sent to '.$to]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => substr($mail->ErrorInfo ?? $e->getMessage(),0,200)]);
}
