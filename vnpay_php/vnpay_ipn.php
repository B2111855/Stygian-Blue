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
$scheduleReceiptSent = false;

// Debug logging
error_log('[VNPAY_IPN] Called with txnRef=' . ($txnRef ?? 'null') . ' isSuccess=' . ($isSuccess ? 'true' : 'false') . ' amount=' . $amount);

// Configure PHPMailer from existing helper if available; else fall back to env config
if (!function_exists('tp_try_configure_mail')) {
    function tp_try_configure_mail(PHPMailer $mail): void
    {
        if (function_exists('tp_configure_mail')) {
            tp_configure_mail($mail);
            return;
        }
        // Build minimal config from env (mail_config.php already loaded at top)
        $host       = function_exists('env_value') ? env_value('SMTP_HOST', 'smtp.gmail.com') : 'smtp.gmail.com';
        $port       = (int)(function_exists('env_value') ? env_value('SMTP_PORT', 587) : 587);
        $username   = function_exists('env_value') ? env_value('SMTP_USERNAME', '') : '';
        if ($username === '') { $username = function_exists('env_value') ? env_value('SMTP_USER', 'example@gmail.com') : 'example@gmail.com'; }
        $password   = function_exists('env_value') ? env_value('SMTP_PASSWORD', '') : '';
        if ($password === '') { $password = function_exists('env_value') ? env_value('SMTP_PASS', '') : ''; }
        $encRaw     = function_exists('env_value') ? strtolower(env_value('SMTP_ENCRYPTION', 'tls')) : 'tls';
        $fromEmail  = function_exists('env_value') ? env_value('MAIL_FROM', '') : '';
        if ($fromEmail === '') { $fromEmail = function_exists('env_value') ? env_value('SMTP_FROM', '') : ''; }
        if ($fromEmail === '') { $fromEmail = $username ?: 'no-reply@example.com'; }
        $fromName   = function_exists('env_value') ? env_value('MAIL_FROM_NAME', 'Stygian Blue Studio') : 'Stygian Blue Studio';
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->SMTPSecure = ($encRaw === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $port;
        $mail->CharSet    = 'UTF-8';
        try { $mail->setFrom($fromEmail, $fromName); } catch (\Throwable $e) { /* ignore */ }
    }
}

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
    // NOTE: Lấy email từ tai_khoan (qua đơn thuê) thay vì khach_hang để đảm bảo có dữ liệu
    $infoStmt = $conn->prepare('SELECT hd.ID_HD, hd.ID_TTP, ttp.TIEN_COC, ttp.GHI_CHU, tk.EMAIL, tk.HO_TEN, ttp.TRANG_THAI FROM thanh_toan_truc_tuyen tt 
        JOIN hoa_don hd ON hd.ID_HD = tt.ID_HD 
        LEFT JOIN don_thue_trang_phuc ttp ON ttp.ID_TTP = hd.ID_TTP 
        LEFT JOIN tai_khoan tk ON tk.ID_TK = ttp.ID_TK 
        WHERE tt.MA_THAM_CHIEU = ? LIMIT 1');
    if ($infoStmt) {
        $infoStmt->bind_param('s', $txnRef);
        $infoStmt->execute();
        $infoStmt->bind_result($invoiceId, $rentalId, $depositRequired, $rentalNote, $email, $hoTen, $rentalStatus);
        if ($infoStmt->fetch() && $rentalId) {
            $infoStmt->close();
            // Gửi email xác nhận cọc nếu có yêu cầu cọc, tránh gửi trùng theo marker
            $shouldSend = ((int)$depositRequired > 0) && (!($rentalNote && str_contains($rentalNote, '[DEPOSIT_CONF_SENT]')));
            error_log('[VNPAY_IPN] Rental deposit email check: shouldSend=' . ($shouldSend ? 'true' : 'false') . ' email=' . ($email ?? 'null') . ' depositRequired=' . $depositRequired . ' rentalId=' . $rentalId);
            if ($shouldSend && $email) {
                $mail = new PHPMailer(true);
                try {
                    tp_try_configure_mail($mail);
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
                    error_log('[VNPAY_IPN] Deposit email sent successfully to ' . $email . ' for rental #' . $rentalId);
                } catch (Exception $e) {
                    error_log('[VNPAY_IPN] Deposit email FAILED to ' . ($email ?? 'unknown') . ': '.substr($mail->ErrorInfo ?? $e->getMessage(),0,200));
                }
            }
        } else {
            $infoStmt->close();
        }
    }

    // Reconcile invoice status by total successful payments vs invoice total
    // Tránh gắn "Đã thanh toán" nếu mới thanh toán tiền cọc/1 phần
    $invStmt = $conn->prepare('SELECT hd.ID_HD, hd.TONG_TIEN FROM thanh_toan_truc_tuyen tt JOIN hoa_don hd ON hd.ID_HD = tt.ID_HD WHERE tt.MA_THAM_CHIEU = ? LIMIT 1');
    if ($invStmt) {
        $invStmt->bind_param('s', $txnRef);
        $invStmt->execute();
        $invStmt->bind_result($ipnInvoiceId, $invoiceTotal);
        if ($invStmt->fetch()) {
            $invStmt->close();
            // Sum all successful payments for this invoice
            $sumStmt = $conn->prepare('SELECT COALESCE(SUM(SO_TIEN),0) FROM thanh_toan_truc_tuyen WHERE ID_HD = ? AND TRANG_THAI = "thanh_cong"');
            if ($sumStmt) {
                $sumStmt->bind_param('i', $ipnInvoiceId);
                $sumStmt->execute();
                $sumStmt->bind_result($paidSum);
                if ($sumStmt->fetch()) {
                    $sumStmt->close();
                    $isCovered = ((int)$paidSum >= (int)$invoiceTotal);
                    if ($isCovered) {
                        // Check current status for idempotent notification
                        $curStmt = $conn->prepare('SELECT TRANGTHAI_THANHTOAN, ID_LICHHEN, ID_TTP FROM hoa_don WHERE ID_HD = ? LIMIT 1');
                        $prevStatus = null; $curApptId = null; $curRentalId = null;
                        if ($curStmt) {
                            $curStmt->bind_param('i', $ipnInvoiceId);
                            $curStmt->execute();
                            $curStmt->bind_result($prevStatus, $curApptId, $curRentalId);
                            $curStmt->fetch();
                            $curStmt->close();
                        }

                        $needUpdate = ($prevStatus !== 'Đã thanh toán');
                        error_log('[VNPAY_IPN] Invoice #' . $ipnInvoiceId . ' coverage check: paidSum=' . $paidSum . ' total=' . $invoiceTotal . ' prevStatus=' . ($prevStatus ?? 'null') . ' needUpdate=' . ($needUpdate ? 'true' : 'false') . ' isSchedule=' . (empty($curRentalId) && !empty($curApptId) ? 'true' : 'false'));
                        if ($needUpdate) {
                            $updPaid = $conn->prepare('UPDATE hoa_don SET TRANGTHAI_THANHTOAN = "Đã thanh toán", PHUONGTHUC_THANHTOAN = "VNPay" WHERE ID_HD = ?');
                            if ($updPaid) { $updPaid->bind_param('i', $ipnInvoiceId); $updPaid->execute(); $updPaid->close(); }
                            $tcPaid = $conn->prepare("UPDATE tai_chinh SET TRANG_THAI = 'đã thanh toán' WHERE ID_HD = ? AND LOAI_GIAO_DICH = 'doanh thu'");
                            if ($tcPaid) { $tcPaid->bind_param('i', $ipnInvoiceId); $tcPaid->execute(); $tcPaid->close(); }

                            // Send receipt email for appointment invoices (non-rental)
                            if (empty($curRentalId) && !empty($curApptId)) {
                                $info2 = $conn->prepare('SELECT tk.EMAIL, tk.HO_TEN, lh.THOI_GIAN_BAT_DAU, cn.TEN_CN, dv.TEN_DV, gdv.TEN_GOI FROM hoa_don hd LEFT JOIN lich_hen lh ON lh.ID_LICHHEN = hd.ID_LICHHEN LEFT JOIN tai_khoan tk ON tk.ID_TK = lh.ID_TK LEFT JOIN chi_nhanh cn ON cn.ID_CN = lh.ID_CHINHANH LEFT JOIN dich_vu dv ON dv.ID_DV = lh.ID_DV LEFT JOIN goi_dich_vu gdv ON gdv.ID_GOI = lh.ID_GOI WHERE hd.ID_HD = ? LIMIT 1');
                                if ($info2) {
                                    $info2->bind_param('i', $ipnInvoiceId);
                                    $info2->execute();
                                    $info2->bind_result($email2, $name2, $startAt, $branchName, $serviceName, $packageName);
                                    if ($info2->fetch() && $email2) {
                                        $info2->close();
                                        $mail = new PHPMailer(true);
                                        try {
                                            tp_try_configure_mail($mail);
                                            $mail->addAddress($email2, $name2 ?: $email2);
                                            $mail->isHTML(true);
                                            $mail->Subject = 'Biên nhận thanh toán hóa đơn #' . $ipnInvoiceId;
                                            $svc = $serviceName ?: $packageName ?: 'Dịch vụ';
                                            $amt = number_format((int)$invoiceTotal, 0, ',', '.');
                                            $when = $startAt ? date('d/m/Y H:i', strtotime($startAt)) : '';
                                            $body = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222">'
                                                .'<h2 style="color:#2563eb;margin:0 0 12px">Biên nhận thanh toán</h2>'
                                                .'<p>Chúng tôi xác nhận hóa đơn <strong>#'.$ipnInvoiceId.'</strong> đã được thanh toán đầy đủ với số tiền <strong>'.$amt.' ₫</strong> qua <strong>VNPay</strong>.</p>'
                                                .'<p><strong>Dịch vụ:</strong> '.htmlspecialchars($svc, ENT_QUOTES, 'UTF-8').'</p>'
                                                .($when !== '' ? '<p><strong>Thời gian chụp:</strong> '.htmlspecialchars($when, ENT_QUOTES, 'UTF-8').'</p>' : '')
                                                .($branchName ? '<p><strong>Chi nhánh:</strong> '.htmlspecialchars($branchName, ENT_QUOTES, 'UTF-8').'</p>' : '')
                                                .'<p>Mã hóa đơn: <strong>#'.$ipnInvoiceId.'</strong>. Xin cảm ơn quý khách!</p>'
                                                .'<hr style="margin:20px 0"><p style="font-size:12px;color:#555">Email tự động – vui lòng không trả lời trực tiếp.</p></div>';
                                            $mail->Body = $body;
                                            $mail->send();
                                            $scheduleReceiptSent = true;
                                            error_log('[VNPAY_IPN] Schedule receipt email sent successfully to ' . $email2 . ' for invoice #' . $ipnInvoiceId);
                                        } catch (Exception $e) {
                                            error_log('[VNPAY_IPN] Schedule receipt email FAILED to ' . ($email2 ?? 'unknown') . ' for invoice #' . $ipnInvoiceId . ': '.substr($mail->ErrorInfo ?? $e->getMessage(),0,200));
                                        }
                                    } else {
                                        $info2->close();
                                    }
                                }
                            }
                        }
                    } else {
                        // Ensure not marked as fully paid if not covered
                        $updUnpaid = $conn->prepare('UPDATE hoa_don SET TRANGTHAI_THANHTOAN = "Chưa thanh toán" WHERE ID_HD = ? AND TRANGTHAI_THANHTOAN <> "Chưa thanh toán"');
                        if ($updUnpaid) { $updUnpaid->bind_param('i', $ipnInvoiceId); $updUnpaid->execute(); $updUnpaid->close(); }
                        // Keep tai_chinh in pending
                        $tcPending = $conn->prepare("UPDATE tai_chinh SET TRANG_THAI = 'chờ thanh toán' WHERE ID_HD = ? AND LOAI_GIAO_DICH = 'doanh thu'");
                        if ($tcPending) { $tcPending->bind_param('i', $ipnInvoiceId); $tcPending->execute(); $tcPending->close(); }
                    }
                } else {
                    $sumStmt->close();
                }
            }
        } else {
            $invStmt->close();
        }
    }
}

header('Content-Type: application/json');
echo json_encode([
    'RspCode' => $verify['RspCode'] ?? '00',
    'Message' => $verify['Message'] ?? 'OK',
    'isSuccess' => $isSuccess,
    'depositEmailSent' => $depositEmailSent,
    'scheduleReceiptSent' => $scheduleReceiptSent
]);
