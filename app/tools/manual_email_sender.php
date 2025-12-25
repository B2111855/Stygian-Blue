<?php
/**
 * Manual Email Sender for Successful VNPay Payments
 * Use this when IPN is not configured - sends emails for completed payments
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../helpers/mail_config.php';
require_once __DIR__ . '/../../database/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Get recent successful payments without emails sent
$sql = "
SELECT 
    tt.ID_TT,
    tt.MA_THAM_CHIEU,
    tt.SO_TIEN,
    tt.THOI_GIAN_THANH_TOAN,
    hd.ID_HD,
    hd.TIEN_COC,
    hd.TONG_TIEN,
    hd.ID_LICHHEN,
    hd.ID_TTP,
    hd.GHI_CHU,
    tk.EMAIL,
    tk.HO_TEN,
    CASE 
        WHEN hd.ID_LICHHEN IS NOT NULL THEN 'Đặt lịch chụp ảnh'
        WHEN hd.ID_TTP IS NOT NULL THEN 'Thuê trang phục'
        ELSE 'Khác'
    END AS LOAI_HOA_DON
FROM thanh_toan_truc_tuyen tt
LEFT JOIN hoa_don hd ON hd.ID_HD = tt.ID_HD
LEFT JOIN lich_hen lh ON lh.ID_LICHHEN = hd.ID_LICHHEN
LEFT JOIN don_thue_trang_phuc dt ON dt.ID_TTP = hd.ID_TTP
LEFT JOIN tai_khoan tk ON tk.ID_TK = COALESCE(lh.ID_TK, dt.ID_TK)
WHERE tt.TRANG_THAI = 'thanh_cong'
  AND tt.THOI_GIAN_THANH_TOAN >= DATE_SUB(NOW(), INTERVAL 7 DAY)
  AND tk.EMAIL IS NOT NULL
ORDER BY tt.THOI_GIAN_THANH_TOAN DESC
";

$result = $conn->query($sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $id_tt = (int)$_POST['id_tt'];
    $email_type = $_POST['email_type']; // 'deposit' or 'receipt'
    
    // Get transaction details
    $stmt = $conn->prepare("
        SELECT tt.*, hd.*, tk.EMAIL, tk.HO_TEN
        FROM thanh_toan_truc_tuyen tt
        LEFT JOIN hoa_don hd ON hd.ID_HD = tt.ID_HD
        LEFT JOIN lich_hen lh ON lh.ID_LICHHEN = hd.ID_LICHHEN
        LEFT JOIN don_thue_trang_phuc dt ON dt.ID_TTP = hd.ID_TTP
        LEFT JOIN tai_khoan tk ON tk.ID_TK = COALESCE(lh.ID_TK, dt.ID_TK)
        WHERE tt.ID_TT = ?
    ");
    $stmt->bind_param('i', $id_tt);
    $stmt->execute();
    $txn = $stmt->get_result()->fetch_assoc();
    
    if ($txn && $txn['EMAIL']) {
        $mail = new PHPMailer(true);
        tp_configure_mail($mail);
        
        try {
            $mail->addAddress($txn['EMAIL'], $txn['HO_TEN']);
            
            if ($email_type === 'deposit') {
                // Deposit confirmation email
                $mail->Subject = 'Xác nhận đã nhận tiền cọc - Stygian Blue Studio';
                $mail->Body = "
                    <h2>Xin chào {$txn['HO_TEN']},</h2>
                    <p>Chúng tôi đã nhận được khoản thanh toán tiền cọc của bạn.</p>
                    <ul>
                        <li><strong>Mã giao dịch:</strong> {$txn['MA_THAM_CHIEU']}</li>
                        <li><strong>Số tiền:</strong> " . number_format($txn['SO_TIEN']) . " VNĐ</li>
                        <li><strong>Thời gian:</strong> {$txn['THOI_GIAN_THANH_TOAN']}</li>
                    </ul>
                    <p>Cảm ơn bạn đã tin tưởng sử dụng dịch vụ của Stygian Blue Studio!</p>
                ";
                
                // Mark as sent
                $updateSql = "UPDATE hoa_don SET GHI_CHU = CONCAT(COALESCE(GHI_CHU, ''), '[DEPOSIT_CONF_SENT]') WHERE ID_HD = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param('i', $txn['ID_HD']);
                $updateStmt->execute();
                
            } else {
                // Payment receipt email
                $mail->Subject = 'Biên nhận thanh toán - Stygian Blue Studio';
                $mail->Body = "
                    <h2>Xin chào {$txn['HO_TEN']},</h2>
                    <p>Cảm ơn bạn đã hoàn tất thanh toán!</p>
                    <ul>
                        <li><strong>Hóa đơn:</strong> #{$txn['ID_HD']}</li>
                        <li><strong>Mã giao dịch:</strong> {$txn['MA_THAM_CHIEU']}</li>
                        <li><strong>Số tiền:</strong> " . number_format($txn['SO_TIEN']) . " VNĐ</li>
                        <li><strong>Thời gian:</strong> {$txn['THOI_GIAN_THANH_TOAN']}</li>
                    </ul>
                    <p>Chúng tôi sẽ liên hệ bạn sớm nhất để xác nhận lịch hẹn.</p>
                ";
            }
            
            $mail->send();
            $success_msg = "✅ Đã gửi email đến {$txn['EMAIL']}";
        } catch (Exception $e) {
            $error_msg = "❌ Lỗi gửi email: {$mail->ErrorInfo}";
        }
    } else {
        $error_msg = "❌ Không tìm thấy giao dịch hoặc thiếu email";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gửi Email Thủ Công - VNPay</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        .success { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; }
        .error { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; }
        .btn { padding: 5px 10px; margin: 2px; border: none; cursor: pointer; border-radius: 3px; }
        .btn-deposit { background-color: #ffc107; color: #000; }
        .btn-receipt { background-color: #28a745; color: #fff; }
        .info { background-color: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>📧 Gửi Email Thủ Công cho Thanh Toán VNPay</h1>
    
    <div class="info">
        <strong>ℹ️ Hướng dẫn:</strong> Trang này hiển thị các giao dịch VNPay thành công trong 7 ngày qua. 
        Click nút tương ứng để gửi email xác nhận cho khách hàng.
    </div>
    
    <?php if (isset($success_msg)): ?>
        <div class="success"><?= $success_msg ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_msg)): ?>
        <div class="error"><?= $error_msg ?></div>
    <?php endif; ?>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Mã giao dịch</th>
                <th>Loại hóa đơn</th>
                <th>Số tiền</th>
                <th>Thời gian</th>
                <th>Khách hàng</th>
                <th>Email</th>
                <th>Đã gửi?</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['ID_TT'] ?></td>
                <td><?= htmlspecialchars($row['MA_THAM_CHIEU']) ?></td>
                <td><?= $row['LOAI_HOA_DON'] ?></td>
                <td><?= number_format($row['SO_TIEN']) ?> VNĐ</td>
                <td><?= $row['THOI_GIAN_THANH_TOAN'] ?></td>
                <td><?= htmlspecialchars($row['HO_TEN']) ?></td>
                <td><?= htmlspecialchars($row['EMAIL']) ?></td>
                <td>
                    <?php if ($row['TIEN_COC'] > 0 && strpos($row['GHI_CHU'], '[DEPOSIT_CONF_SENT]') !== false): ?>
                        ✅ Cọc
                    <?php endif; ?>
                </td>
                <td>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="id_tt" value="<?= $row['ID_TT'] ?>">
                        
                        <?php if ($row['TIEN_COC'] > 0 && strpos($row['GHI_CHU'], '[DEPOSIT_CONF_SENT]') === false): ?>
                        <button type="submit" name="send_email" value="1" 
                                onclick="this.form.email_type.value='deposit'" 
                                class="btn btn-deposit">Gửi xác nhận cọc</button>
                        <?php endif; ?>
                        
                        <?php if ($row['ID_LICHHEN']): ?>
                        <button type="submit" name="send_email" value="1" 
                                onclick="this.form.email_type.value='receipt'" 
                                class="btn btn-receipt">Gửi biên nhận</button>
                        <?php endif; ?>
                        
                        <input type="hidden" name="email_type" value="">
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
    <p style="margin-top: 20px; color: #666;">
        <strong>Lưu ý:</strong> Để email tự động gửi sau mỗi thanh toán, cần cấu hình IPN URL trong VNPay Dashboard.
    </p>
</body>
</html>
