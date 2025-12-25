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
$transactionNo = $payload['vnp_TransactionNo'] ?? '';
$bankCode = $payload['vnp_BankCode'] ?? '';
$payDate = $payload['vnp_PayDate'] ?? '';

// AUTO-UPDATE DATABASE AND SEND EMAIL
$emailStatus = '';
$updateStatus = '';

if ($result->isSuccessful() && $result->isValidSignature) {
    require_once __DIR__ . '/../database/config.php';
    require_once __DIR__ . '/../app/helpers/mail_config.php';
    
    if (isset($conn) && $conn) {
        $txnRef = $payload['vnp_TxnRef'] ?? '';
        $vnpAmount = isset($payload['vnp_Amount']) ? ((float) $payload['vnp_Amount']) / 100 : 0;
        
        if ($txnRef) {
            // Update thanh_toan_truc_tuyen
            $updateSql = "UPDATE thanh_toan_truc_tuyen SET 
                          TRANG_THAI = 'success',
                          RAW_CALLBACK = ?,
                          UPDATED_AT = NOW()
                          WHERE MA_THAM_CHIEU = ? AND TRANG_THAI != 'success'";
            $rawCallback = json_encode($payload);
            $stmt = $conn->prepare($updateSql);
            
            if ($stmt) {
                $stmt->bind_param('ss', $rawCallback, $txnRef);
                $stmt->execute();
                
                // Get invoice info
                $sql = "SELECT hd.ID_HD, hd.TONG_TIEN, hd.ID_LICHHEN, tk.EMAIL, tk.HO_TEN
                        FROM thanh_toan_truc_tuyen tt
                        JOIN hoa_don hd ON hd.ID_HD = tt.ID_HD
                        LEFT JOIN lich_hen lh ON lh.ID_LICHHEN = hd.ID_LICHHEN
                        LEFT JOIN don_thue_trang_phuc dt ON dt.ID_TTP = hd.ID_TTP
                        LEFT JOIN tai_khoan tk ON tk.ID_TK = COALESCE(lh.ID_TK, dt.ID_TK)
                        WHERE tt.MA_THAM_CHIEU = ?";
                $stmt2 = $conn->prepare($sql);
                
                if ($stmt2) {
                    $stmt2->bind_param('s', $txnRef);
                    $stmt2->execute();
                    $row = $stmt2->get_result()->fetch_assoc();
                    
                    if ($row) {
                        $idHD = $row['ID_HD'];
                        $email = $row['EMAIL'];
                        $hoTen = $row['HO_TEN'];
                        
                        // Check if fully paid
                        $sumSql = "SELECT COALESCE(SUM(SO_TIEN), 0) as total_paid FROM thanh_toan_truc_tuyen 
                                   WHERE ID_HD = ? AND TRANG_THAI = 'success'";
                        $sumStmt = $conn->prepare($sumSql);
                        if ($sumStmt) {
                            $sumStmt->bind_param('i', $idHD);
                            $sumStmt->execute();
                            $paidSum = $sumStmt->get_result()->fetch_assoc()['total_paid'];
                            
                            if ($paidSum >= $row['TONG_TIEN']) {
                                // Update invoice
                                $updateHdSql = "UPDATE hoa_don SET TRANGTHAI_THANHTOAN = 'Đã thanh toán' 
                                                WHERE ID_HD = ? AND TRANGTHAI_THANHTOAN != 'Đã thanh toán'";
                                $updateHdStmt = $conn->prepare($updateHdSql);
                                if ($updateHdStmt) {
                                    $updateHdStmt->bind_param('i', $idHD);
                                    $updateHdStmt->execute();
                                    
                                    // Send receipt email
                                    if ($updateHdStmt->affected_rows > 0 && $row['ID_LICHHEN'] && $email) {
                                        try {
                                                    $mail = new PHPMailer(true);
                                                    tp_configure_mail($mail);
                                                    $mail->addAddress($email, $hoTen);
                                                    $mail->Subject = 'Biên nhận thanh toán - Stygian Blue Studio';
                                                    $mail->isHTML(true);
                                                    $mail->Body = '
                                                    <div style="background: linear-gradient(90deg, #6a5af9 0%, #38bdf8 100%); padding: 32px 0 0 0; border-radius: 12px 12px 0 0; text-align: center; color: #fff;">
                                                        <img src="https://i.imgur.com/8Km9tLL.png" alt="Stygian Blue Studio" style="width: 56px; height: 56px; border-radius: 50%; margin-bottom: 8px;" />
                                                        <h2 style="margin: 0; font-size: 28px; font-weight: 700; letter-spacing: 1px;">Xác nhận thanh toán</h2>
                                                        <div style="font-size: 16px; margin-bottom: 0;">Stygian Blue Studio</div>
                                                    </div>
                                                    <div style="background: #fff; border-radius: 0 0 12px 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); padding: 32px; font-family: \'Segoe UI\', Arial, sans-serif; color: #222;">
                                                        <p style="font-size: 18px; margin-top: 0;">Xin chào <b>' . htmlspecialchars($hoTen, ENT_QUOTES, 'UTF-8') . '</b>,</p>
                                                        <p>Cảm ơn bạn đã hoàn tất thanh toán! Dưới đây là thông tin giao dịch của bạn:</p>
                                                        <div style="border: 1px solid #e0e7ef; border-radius: 8px; padding: 20px 24px; margin: 24px 0; background: #f8fafc;">
                                                            <table style="width: 100%; font-size: 16px; border-collapse: collapse;">
                                                                <tr>
                                                                    <td style="padding: 8px 0; color: #64748b;">Hóa đơn</td>
                                                                    <td style="padding: 8px 0; font-weight: 600; color: #222;">#' . htmlspecialchars($idHD, ENT_QUOTES, 'UTF-8') . '</td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="padding: 8px 0; color: #64748b;">Mã giao dịch</td>
                                                                    <td style="padding: 8px 0; font-weight: 600; color: #222;">' . htmlspecialchars($txnRef, ENT_QUOTES, 'UTF-8') . '</td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="padding: 8px 0; color: #64748b;">Số tiền</td>
                                                                    <td style="padding: 8px 0; font-weight: 700; color: #2563eb; font-size: 20px;">' . number_format($vnpAmount) . ' VNĐ</td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                        <p style="margin-bottom: 24px;">Nếu bạn có bất kỳ thắc mắc nào, vui lòng liên hệ với chúng tôi để được hỗ trợ nhanh nhất.</p>
                                                        <div style="font-size: 14px; color: #64748b; border-top: 1px solid #e0e7ef; padding-top: 16px;">
                                                            <b>Stygian Blue Studio</b><br>
                                                            Địa chỉ: 0123 Đường ABC, Quận XYZ, TP. Cần Thơ<br>
                                                            Hotline: 0123 456 789<br>
                                                            Email: stygianblue.studio@gmail.com
                                                        </div>
                                                    </div>';
                                            
                                            if ($mail->send()) {
                                                $emailStatus = "Đã gửi biên nhận đến $email";
                                            }
                                        } catch (Exception $e) {
                                            // Silent fail
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// Extract invoice ID for redirect
$invoiceId = null;
if ($result->orderId()) {
    preg_match('/^HD(\d+)_/', $result->orderId(), $matches);
    if (isset($matches[1])) {
        $invoiceId = (int)$matches[1];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $result->isSuccessful() ? 'Thanh toán thành công' : 'Thanh toán thất bại'; ?> - Stygian Blue</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes checkmark {
            0% { stroke-dashoffset: 100; }
            100% { stroke-dashoffset: 0; }
        }
        @keyframes circle {
            0% { stroke-dashoffset: 166; }
            100% { stroke-dashoffset: 0; }
        }
        .success-checkmark circle { animation: circle 0.6s ease-in-out; }
        .success-checkmark .check { animation: checkmark 0.6s 0.3s ease-in-out forwards; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-2xl w-full bg-white shadow-2xl rounded-2xl overflow-hidden">
        <!-- Header -->
        <div class="<?php echo $result->isSuccessful() ? 'bg-gradient-to-r from-green-500 to-emerald-600' : 'bg-gradient-to-r from-red-500 to-rose-600'; ?> p-8 text-white text-center">
            <?php if ($result->isSuccessful()): ?>
                <svg class="success-checkmark mx-auto mb-4" width="80" height="80" viewBox="0 0 52 52">
                    <circle cx="26" cy="26" r="25" fill="none" stroke="#fff" stroke-width="2" 
                            stroke-dasharray="166" stroke-dashoffset="166" stroke-linecap="round"/>
                    <path class="check" fill="none" stroke="#fff" stroke-width="3" 
                          stroke-linecap="round" stroke-linejoin="round" 
                          stroke-dasharray="48" stroke-dashoffset="48"
                          d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                </svg>
                <h1 class="text-3xl font-bold mb-2">Thanh toán thành công!</h1>
                <p class="text-green-100">Cảm ơn bạn đã thanh toán qua VNPAY</p>
            <?php else: ?>
                <svg class="mx-auto mb-4" width="80" height="80" viewBox="0 0 52 52" fill="none">
                    <circle cx="26" cy="26" r="25" stroke="#fff" stroke-width="2"/>
                    <path d="M16 16L36 36M36 16L16 36" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
                </svg>
                <h1 class="text-3xl font-bold mb-2">Thanh toán thất bại</h1>
                <p class="text-red-100"><?php echo htmlspecialchars($result->message(), ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>

        <!-- Transaction details -->
        <div class="p-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-6 pb-3 border-b">Thông tin giao dịch</h2>
            
            <div class="space-y-4">
                <?php if ($result->orderId()): ?>
                <div class="flex justify-between items-center py-3 border-b border-gray-100">
                    <span class="text-gray-600 font-medium">Mã đơn hàng</span>
                    <span class="text-gray-900 font-semibold"><?php echo htmlspecialchars($result->orderId(), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php endif; ?>

                <div class="flex justify-between items-center py-3 border-b border-gray-100">
                    <span class="text-gray-600 font-medium">Số tiền</span>
                    <span class="text-2xl font-bold text-indigo-600"><?php echo htmlspecialchars($amount, ENT_QUOTES, 'UTF-8'); ?> VNĐ</span>
                </div>

                <?php if ($transactionNo): ?>
                <div class="flex justify-between items-center py-3 border-b border-gray-100">
                    <span class="text-gray-600 font-medium">Mã giao dịch VNPAY</span>
                    <span class="text-gray-900"><?php echo htmlspecialchars($transactionNo, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php endif; ?>

                <?php if ($bankCode): ?>
                <div class="flex justify-between items-center py-3 border-b border-gray-100">
                    <span class="text-gray-600 font-medium">Ngân hàng</span>
                    <span class="text-gray-900 uppercase"><?php echo htmlspecialchars($bankCode, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php endif; ?>

                <?php if ($payDate): ?>
                <div class="flex justify-between items-center py-3">
                    <span class="text-gray-600 font-medium">Thời gian thanh toán</span>
                    <span class="text-gray-900">
                        <?php 
                        if (strlen($payDate) === 14) {
                            echo date('d/m/Y H:i:s', strtotime(
                                substr($payDate, 0, 4).'-'.
                                substr($payDate, 4, 2).'-'.
                                substr($payDate, 6, 2).' '.
                                substr($payDate, 8, 2).':'.
                                substr($payDate, 10, 2).':'.
                                substr($payDate, 12, 2)
                            ));
                        } else {
                            echo htmlspecialchars($payDate, ENT_QUOTES, 'UTF-8');
                        }
                        ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($result->isSuccessful() && !empty($emailStatus)): ?>
            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                    </svg>
                    <p class="text-sm text-blue-800"><?php echo htmlspecialchars($emailStatus, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action buttons -->
            <div class="mt-8 flex flex-col sm:flex-row gap-3">
                <a href="../app/Pages/Views/hoa_don.php" 
                   class="flex-1 text-center px-6 py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-md">
                    Quay về danh sách hóa đơn
                </a>
                <a href="../app/Pages/Views/hoa_don.php" 
                   class="flex-1 text-center px-6 py-3 bg-gray-100 text-gray-700 font-semibold rounded-lg hover:bg-gray-200 transition-colors">
                    Danh sách hóa đơn
                </a>
            </div>

            <?php if ($result->isSuccessful()): ?>
            <p class="mt-4 text-center text-sm text-gray-500">
                Tự động chuyển về danh sách hóa đơn trong <span id="countdown">10</span> giây...
            </p>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-8 py-4 text-center text-sm text-gray-500 border-t">
            <p>Giao dịch được bảo mật bởi VNPAY • © Stygian Blue Studio <?php echo date('Y'); ?></p>
        </div>
    </div>

    <?php if ($result->isSuccessful()): ?>
    <script>
        let countdown = 10;
        const countdownEl = document.getElementById('countdown');
        const redirectUrl = '../app/Pages/Views/hoa_don.php';
        
        const interval = setInterval(() => {
            countdown--;
            if (countdownEl) countdownEl.textContent = countdown;
            if (countdown <= 0) {
                clearInterval(interval);
                window.location.href = redirectUrl;
            }
        }, 1000);
    </script>
    <?php endif; ?>
</body>
</html>
