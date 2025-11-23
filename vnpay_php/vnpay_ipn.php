<?php
// VNPay IPN handler: validate signature, update transaction, trigger deposit confirmation email for rental invoices
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../app/helpers/mail_config.php';
require_once __DIR__ . '/config.php'; // returns VNPayService instance

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Service provided by config.php (already required)
$service = $service ?? (require __DIR__ . '/config.php');
$data = $_GET; // VNPay sends IPN data via query string
$verify = $service->validateIpn($data);

// Early abort on invalid signature / failure
if (($verify['RspCode'] ?? '') !== '00') {
    header('Content-Type: application/json');
    echo json_encode([
        'RspCode' => $verify['RspCode'] ?? '99',
        'Message' => $verify['Message'] ?? 'Error',
        'isSuccess' => false
    ]);
    exit;
}

$isSuccess = $verify['isSuccess'] ?? false; // true only if payment success
$txnRef = isset($data['vnp_TxnRef']) ? trim($data['vnp_TxnRef']) : null; // MA_THAM_CHIEU
$amount = isset($data['vnp_Amount']) ? ((int)$data['vnp_Amount'] / 100) : 0; // VNPay sends *100
$bankCode = $data['vnp_BankCode'] ?? null;
$payDate = $data['vnp_PayDate'] ?? null;
$depositEmailSent = false;

if ($txnRef && $isSuccess) {
    // Update transaction record
    $updateStmt = $conn->prepare('UPDATE thanh_toan_truc_tuyen SET TRANG_THAI = "thanh_cong", NGAN_HANG = ?, THOI_GIAN_THANH_TOAN = ?, SO_TIEN = ?, DU_LIEU_IPN = ? WHERE MA_THAM_CHIEU = ?');
    $rawJson = json_encode($data);
    if ($updateStmt) {
        $updateStmt->bind_param('ssiss', $bankCode, $payDate, $amount, $rawJson, $txnRef);
        $updateStmt->execute();
        $updateStmt->close();
    }

    // Locate related invoice & rental order
    $infoStmt = $conn->prepare('SELECT hd.ID_HD, hd.ID_TTP, ttp.TIEN_COC, ttp.GHI_CHU, kh.EMAIL, kh.HO_TEN, ttp.TRANG_THAI FROM thanh_toan_truc_tuyen tt 
        JOIN hoa_don hd ON hd.ID_HD = tt.ID_HD 
        LEFT JOIN don_thue_trang_phuc ttp ON ttp.ID_TTP = hd.ID_TTP 
        LEFT JOIN khach_hang kh ON kh.ID_KH = hd.ID_KH 
        WHERE tt.MA_THAM_CHIEU = ? LIMIT 1');
    if ($infoStmt) {
        $infoStmt->bind_param('s', $txnRef);
        $infoStmt->execute();
        $infoStmt->bind_result($invoiceId, $rentalId, $depositRequired, $rentalNote, $email, $hoTen, $rentalStatus);
        if ($infoStmt->fetch() && $rentalId) {
            $infoStmt->close();
            $shouldSend = ((int)$depositRequired > 0) && $rentalStatus === 'da_duyet' && (!($rentalNote && str_contains($rentalNote, '[DEPOSIT_CONF_SENT]')));
            if ($shouldSend && $email) {
                $mail = new PHPMailer(true);
                try {
                    tp_configure_mail($mail);
                    $mail->addAddress($email, $hoTen ?: $email);
                    $mail->isHTML(true);
                    $mail->Subject = 'Xác nhận đã nhận tiền cọc – Đơn thuê #' . $rentalId;
                    $mail->Body = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222">'
                        .'<h2 style="color:#16a34a;margin:0 0 12px">Đã nhận tiền cọc</h2>'
                        .'<p>Chúng tôi xác nhận đã nhận được tiền cọc cho đơn thuê <strong>#'.$rentalId.'</strong> với số tiền <strong>'.number_format((int)$depositRequired,0,',','.').' ₫</strong>.</p>'
                        .'<p>Email này là bằng chứng thanh toán tiền cọc. Vui lòng lưu lại và xuất trình (nếu được yêu cầu) khi đến nhận trang phục.</p>'
                        .'<p>Mã hóa đơn liên quan: <strong>#'.$invoiceId.'</strong>. Hóa đơn cuối sẽ được cập nhật sau khi trả trang phục (bao gồm điều chỉnh phát sinh và hoàn trả cọc nếu có).</p>'
                        .'<hr style="margin:20px 0"><p style="font-size:12px;color:#555">Email tự động – vui lòng không trả lời trực tiếp.</p></div>';
                    $mail->send();
                    $mkStmt = $conn->prepare("UPDATE don_thue_trang_phuc SET GHI_CHU = CONCAT(IFNULL(GHI_CHU,''), '\n[DEPOSIT_CONF_SENT]') WHERE ID_TTP = ?");
                    if ($mkStmt) { $mkStmt->bind_param('i',$rentalId); $mkStmt->execute(); $mkStmt->close(); }
                    $depositEmailSent = true;
                } catch (Exception $e) {
                    error_log('Deposit confirmation email failed: '.substr($mail->ErrorInfo ?? $e->getMessage(),0,150));
                }
            }
        } else {
            $infoStmt->close();
        }
    }
}

header('Content-Type: application/json');
echo json_encode([
    'RspCode' => $verify['RspCode'] ?? '00',
    'Message' => $verify['Message'] ?? 'OK',
    'isSuccess' => $isSuccess,
    'depositEmailSent' => $depositEmailSent
]);
