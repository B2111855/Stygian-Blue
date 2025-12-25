<?php

use App\Payments\VNPayService;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/** @var VNPayService $service */
$service = require './config.php';
$config = $service->config();
$result = $service->interpretResponse($_GET);

$payload = $result->payload;
$amount = isset($payload['vnp_Amount']) ? number_format(((float) $payload['vnp_Amount']) / 100, 0, '.', ',') : '0';
$orderInfo = $payload['vnp_OrderInfo'] ?? '';
$transactionNo = $payload['vnp_TransactionNo'] ?? '';
$bankCode = $payload['vnp_BankCode'] ?? '';
$payDate = $payload['vnp_PayDate'] ?? '';

// AUTO-UPDATE DATABASE AND SEND EMAIL (replaces IPN)
$emailStatus = '';
$updateStatus = '';
$debugInfo = [];

if (!$result->isSuccessful()) {
    $updateStatus = '❌ Thanh toán không thành công - Không cập nhật dữ liệu';
} elseif (!$result->isValidSignature) {
    $updateStatus = '⚠️ Chữ ký không hợp lệ - Giao dịch bị từ chối';
} elseif ($result->isSuccessful() && $result->isValidSignature) {
    $debugInfo[] = 'Bắt đầu xử lý...';
    
    require_once __DIR__ . '/../database/config.php';
    require_once __DIR__ . '/../app/helpers/mail_config.php';
    
    $debugInfo[] = 'Đã load config files';
    
    // Check database connection
    if (!isset($conn) || !$conn) {
        $updateStatus = '⚠️ Không thể kết nối database';
        $debugInfo[] = 'FAILED: $conn không tồn tại';
    } else {
        $debugInfo[] = 'Database connected';
        $txnRef = $payload['vnp_TxnRef'] ?? '';
        $debugInfo[] = 'TxnRef: ' . $txnRef;
        $vnpAmount = isset($payload['vnp_Amount']) ? ((float) $payload['vnp_Amount']) / 100 : 0;
        $vnpBankCode = $payload['vnp_BankCode'] ?? '';
        $vnpPayDate = $payload['vnp_PayDate'] ?? '';
        
        if ($txnRef) {
            // Convert vnp_PayDate (YmdHis) to MySQL datetime
            $payDateTime = null;
            if ($vnpPayDate && strlen($vnpPayDate) === 14) {
                $y = substr($vnpPayDate, 0, 4);
                $m = substr($vnpPayDate, 4, 2);
                $d = substr($vnpPayDate, 6, 2);
                $h = substr($vnpPayDate, 8, 2);
                $i = substr($vnpPayDate, 10, 2);
                $s = substr($vnpPayDate, 12, 2);
                $payDateTime = "$y-$m-$d $h:$i:$s";
            }
            
            // 1. Update thanh_toan_truc_tuyen
            $updateSql = "UPDATE thanh_toan_truc_tuyen SET 
                          TRANG_THAI = 'success',
                          RAW_CALLBACK = ?,
                          UPDATED_AT = NOW()
                          WHERE MA_THAM_CHIEU = ? AND TRANG_THAI != 'success'";
            $rawCallback = json_encode($payload);
            $stmt = $conn->prepare($updateSql);
            
            if (!$stmt) {
                $updateStatus = '⚠️ Lỗi SQL: ' . $conn->error;
                $debugInfo[] = 'FAILED prepare: ' . $conn->error;
            } else {
                $debugInfo[] = 'Prepare statement OK';
                $stmt->bind_param('ss', $rawCallback, $txnRef);
                $stmt->execute();
                $updated = $stmt->affected_rows > 0;
                $debugInfo[] = 'Execute OK, affected: ' . $stmt->affected_rows;
                
                if ($updated) {
                    $updateStatus = '✅ Đã cập nhật trạng thái thanh toán thành công';
                } else {
                    $updateStatus = 'ℹ️ Giao dịch đã được cập nhật trước đó';
                }
                $debugInfo[] = 'Update status set';
                
                // 2. Get invoice and customer info
                  $sql = "SELECT hd.ID_HD, hd.TONG_TIEN, hd.ID_LICHHEN, hd.ID_TTP,
                           tk.EMAIL, tk.HO_TEN, tt.SO_TIEN
                       FROM thanh_toan_truc_tuyen tt
                       JOIN hoa_don hd ON hd.ID_HD = tt.ID_HD
                       LEFT JOIN lich_hen lh ON lh.ID_LICHHEN = hd.ID_LICHHEN
                       LEFT JOIN don_thue_trang_phuc dt ON dt.ID_TTP = hd.ID_TTP
                       LEFT JOIN tai_khoan tk ON tk.ID_TK = COALESCE(lh.ID_TK, dt.ID_TK)
                       WHERE tt.MA_THAM_CHIEU = ?";
                $stmt2 = $conn->prepare($sql);
                
                if ($stmt2) {
                    $debugInfo[] = 'Invoice query prepared';
                    $stmt2->bind_param('s', $txnRef);
                    $stmt2->execute();
                    $row = $stmt2->get_result()->fetch_assoc();
                    $debugInfo[] = $row ? 'Invoice found: #' . $row['ID_HD'] : 'Invoice not found';
                    
                    if ($row) {
                        $idHD = $row['ID_HD'];
                        $email = $row['EMAIL'];
                        $hoTen = $row['HO_TEN'];
                        
                        // 3. Check if invoice fully paid and send receipt
                        $debugInfo[] = 'Checking if invoice fully paid...';
                        $sumSql = "SELECT COALESCE(SUM(SO_TIEN), 0) as total_paid FROM thanh_toan_truc_tuyen 
                                   WHERE ID_HD = ? AND TRANG_THAI = 'success'";
                        $sumStmt = $conn->prepare($sumSql);
                        if ($sumStmt) {
                            $sumStmt->bind_param('i', $idHD);
                            $sumStmt->execute();
                            $paidSum = $sumStmt->get_result()->fetch_assoc()['total_paid'];
                            $debugInfo[] = "Paid: $paidSum / Total: {$row['TONG_TIEN']}";
                            
                            if ($paidSum >= $row['TONG_TIEN']) {
                                $debugInfo[] = 'Fully paid - updating invoice...';
                                // Update invoice status
                                $updateHdSql = "UPDATE hoa_don SET TRANGTHAI_THANHTOAN = 'Đã thanh toán' 
                                                WHERE ID_HD = ? AND TRANGTHAI_THANHTOAN != 'Đã thanh toán'";
                                $updateHdStmt = $conn->prepare($updateHdSql);
                                if ($updateHdStmt) {
                                    $updateHdStmt->bind_param('i', $idHD);
                                    $updateHdStmt->execute();
                                    $statusChanged = $updateHdStmt->affected_rows > 0;
                                    $debugInfo[] = "Invoice updated, affected: " . $updateHdStmt->affected_rows;
                                    
                                    // Send receipt for appointment booking
                                    if ($statusChanged && $row['ID_LICHHEN'] && $email) {
                                        $debugInfo[] = 'Sending receipt email...';
                                        try {
                                            $mail = new PHPMailer(true);
                                            tp_configure_mail($mail);
                                            $mail->addAddress($email, $hoTen);
                                            $mail->Subject = 'Biên nhận thanh toán - Stygian Blue Studio';
                                            $mail->Body = "<h2>Xin chào $hoTen,</h2>
                                                <p>Cảm ơn bạn đã hoàn tất thanh toán!</p>
                                                <ul>
                                                    <li><strong>Hóa đơn:</strong> #$idHD</li>
                                                    <li><strong>Mã giao dịch:</strong> $txnRef</li>
                                                    <li><strong>Số tiền:</strong> " . number_format($vnpAmount) . " VNĐ</li>
                                                </ul>
                                                <p>Chúng tôi sẽ liên hệ bạn sớm nhất để xác nhận lịch hẹn.</p>";
                                            
                                            if ($mail->send()) {
                                                $emailStatus = empty($emailStatus) 
                                                    ? "✅ Đã gửi biên nhận đến $email" 
                                                    : $emailStatus . " và biên nhận";
                                                $debugInfo[] = 'Receipt email sent';
                                            }
                                        } catch (Exception $e) {
                                            $debugInfo[] = 'Email error: ' . $e->getMessage();
                                        }
                                    } else {
                                        $debugInfo[] = 'No email: statusChanged=' . ($statusChanged?'Y':'N') . ', hasLichHen=' . ($row['ID_LICHHEN']?'Y':'N') . ', hasEmail=' . ($email?'Y':'N');
                                    }
                                } else {
                                    $debugInfo[] = 'FAILED to prepare invoice update: ' . $conn->error;
                                }
                            } else {
                                $debugInfo[] = 'Not fully paid yet';
                            }
                        } else {
                            $debugInfo[] = 'FAILED to prepare sum query';
                        }
                    } else {
                        $debugInfo[] = 'No row found for txnRef';
                    }
                } else {
                    $debugInfo[] = 'FAILED to prepare invoice query: ' . $conn->error;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Kết quả thanh toán VNPAY">
        <title>Kết quả thanh toán</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    </head>
    <body>
        <div class="container">
            <div class="header clearfix">
                <h3 class="text-muted">Kết quả thanh toán VNPAY</h3>
            </div>
            <div class="table-responsive">
                <div class="form-group">
                    <label>Kết quả:</label>
                    <span class="font-weight-bold <?php echo $result->isSuccessful() ? 'text-success' : 'text-danger'; ?>"><?php echo htmlspecialchars($result->message(), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php if (!empty($updateStatus)): ?>
                <div class="form-group">
                    <div class="alert <?php echo $result->isSuccessful() ? 'alert-success' : 'alert-warning'; ?>">
                        <?php echo htmlspecialchars($updateStatus, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($emailStatus)): ?>
                <div class="form-group">
                    <div class="alert alert-info">
                        <?php echo htmlspecialchars($emailStatus, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
                    <label>Kết quả:</label>
                    <span class="font-weight-bold <?php echo $result->isSuccessful() ? 'text-success' : 'text-danger'; ?>"><?php echo htmlspecialchars($result->message(), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php if (!empty($updateStatus)): ?>
                <div class="form-group">
                    <div class="alert <?php echo $result->isSuccessful() ? 'alert-success' : 'alert-warning'; ?>">
                        <strong>Trạng thái xử lý:</strong> <?php echo htmlspecialchars($updateStatus, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($emailStatus)): ?>
                <div class="form-group">
                    <div class="alert alert-info">
                        <strong>Email:</strong> <?php echo htmlspecialchars($emailStatus, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($debugInfo)): ?>
                <div class="form-group">
                    <div class="alert alert-secondary">
                        <strong>Debug:</strong><br>
                        <?php foreach ($debugInfo as $msg): ?>
                            • <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?><br>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <footer class="footer">
                <p>&copy; VNPay Sandbox <?php echo $config->now()->format('Y'); ?></p>
            </footer>
        </div>
    </body>
</html>