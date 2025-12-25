<?php
// Test gửi email thanh toán KHÔNG CẦN VNPAY
require_once '../../vendor/autoload.php';
require_once '../../database/config.php';
require_once '../helpers/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: text/html; charset=utf-8');
echo '<h2>Test Email Thanh Toán</h2>';

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if (!$type || !$id) {
    echo '<p>Sử dụng:</p>';
    echo '<ul>';
    echo '<li><a href="?type=deposit&id=1">?type=deposit&id=1</a> - Test email cọc cho đơn thuê #1</li>';
    echo '<li><a href="?type=receipt&id=1">?type=receipt&id=1</a> - Test email biên nhận cho hóa đơn #1</li>';
    echo '</ul>';
    exit;
}

// Test email xác nhận cọc (thuê trang phục)
if ($type === 'deposit') {
    $stmt = $conn->prepare('SELECT ttp.ID_TTP, ttp.TIEN_COC, tk.EMAIL, tk.HO_TEN, hd.ID_HD 
        FROM don_thue_trang_phuc ttp 
        LEFT JOIN tai_khoan tk ON tk.ID_TK = ttp.ID_TK 
        LEFT JOIN hoa_don hd ON hd.ID_TTP = ttp.ID_TTP 
        WHERE ttp.ID_TTP = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($rentalId, $deposit, $email, $name, $invoiceId);
    
    if ($stmt->fetch()) {
        $stmt->close();
        echo "<p><strong>Đơn thuê #$rentalId</strong></p>";
        echo "<p>Email: " . ($email ?: '<span style="color:red">KHÔNG CÓ EMAIL</span>') . "</p>";
        echo "<p>Tên khách: " . ($name ?: 'Không có') . "</p>";
        echo "<p>Tiền cọc: " . number_format($deposit, 0) . " VND</p>";
        echo "<p>Hóa đơn: #" . ($invoiceId ?: 'Chưa có') . "</p>";
        
        if (!$email) {
            echo '<p style="color:red;font-weight:bold">❌ Đơn thuê này không có email trong tài khoản!</p>';
            echo '<p>Vui lòng kiểm tra bảng <code>tai_khoan</code> và đảm bảo có email cho ID_TK của đơn thuê này.</p>';
            exit;
        }
        
        $mail = new PHPMailer(true);
        try {
            tp_configure_mail($mail);
            $mail->addAddress($email, $name ?: $email);
            $mail->isHTML(true);
            $mail->Subject = 'Xác nhận đã nhận tiền cọc – Đơn thuê #' . $rentalId;
            $mail->Body = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222">'
                .'<h2 style="color:#16a34a;margin:0 0 12px">Đã nhận tiền cọc</h2>'
                .'<p>Chúng tôi xác nhận đã nhận được tiền cọc cho đơn thuê <strong>#'.$rentalId.'</strong> với số tiền <strong>'.number_format((int)$deposit,0,',','.').' ₫</strong>.</p>'
                .'<p>Email này là bằng chứng thanh toán tiền cọc. Vui lòng lưu lại và xuất trình (nếu được yêu cầu) khi đến nhận trang phục.</p>'
                .'<p>Mã hóa đơn liên quan: <strong>#'.$invoiceId.'</strong>. Hóa đơn cuối sẽ được cập nhật sau khi trả trang phục (bao gồm điều chỉnh phát sinh và hoàn trả cọc nếu có).</p>'
                .'<hr style="margin:20px 0"><p style="font-size:12px;color:#555">Email tự động – vui lòng không trả lời trực tiếp.</p></div>';
            $mail->send();
            echo '<p style="color:green;font-weight:bold">✅ Email đã gửi thành công!</p>';
            echo '<p>Kiểm tra hộp thư: ' . htmlspecialchars($email) . '</p>';
        } catch (Exception $e) {
            echo '<p style="color:red;font-weight:bold">❌ Lỗi gửi email:</p>';
            echo '<pre>' . htmlspecialchars($mail->ErrorInfo ?? $e->getMessage()) . '</pre>';
        }
    } else {
        $stmt->close();
        echo '<p style="color:red">Không tìm thấy đơn thuê #' . $id . ' hoặc thiếu email</p>';
    }
}

// Test email biên nhận thanh toán (đặt lịch)
elseif ($type === 'receipt') {
    $stmt = $conn->prepare('SELECT hd.ID_HD, hd.TONG_TIEN, tk.EMAIL, tk.HO_TEN, lh.THOI_GIAN_BAT_DAU, cn.TEN_CN, dv.TEN_DV, gdv.TEN_GOI 
        FROM hoa_don hd 
        LEFT JOIN lich_hen lh ON lh.ID_LICHHEN = hd.ID_LICHHEN 
        LEFT JOIN tai_khoan tk ON tk.ID_TK = lh.ID_TK 
        LEFT JOIN chi_nhanh cn ON cn.ID_CN = lh.ID_CHINHANH 
        LEFT JOIN dich_vu dv ON dv.ID_DV = lh.ID_DV 
        LEFT JOIN goi_dich_vu gdv ON gdv.ID_GOI = lh.ID_GOI 
        WHERE hd.ID_HD = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($invoiceId, $total, $email, $name, $startAt, $branchName, $serviceName, $packageName);
    
    if ($stmt->fetch()) {
        $stmt->close();
        echo "<p><strong>Hóa đơn #$invoiceId</strong></p>";
        echo "<p>Email: " . ($email ?: '<span style="color:red">KHÔNG CÓ EMAIL</span>') . "</p>";
        echo "<p>Tên khách: " . ($name ?: 'Không có') . "</p>";
        echo "<p>Tổng tiền: " . number_format($total, 0) . " VND</p>";
        echo "<p>Dịch vụ: " . ($serviceName ?: $packageName ?: 'Không có') . "</p>";
        
        if (!$email) {
            echo '<p style="color:red;font-weight:bold">❌ Hóa đơn này không có email trong tài khoản!</p>';
            echo '<p>Vui lòng kiểm tra bảng <code>tai_khoan</code> và đảm bảo có email cho ID_TK của lịch hẹn này.</p>';
            exit;
        }
        
        $mail = new PHPMailer(true);
        try {
            tp_configure_mail($mail);
            $mail->addAddress($email, $name ?: $email);
            $mail->isHTML(true);
            $mail->Subject = 'Biên nhận thanh toán hóa đơn #' . $invoiceId;
            $svc = $serviceName ?: $packageName ?: 'Dịch vụ';
            $amt = number_format((int)$total, 0, ',', '.');
            $when = $startAt ? date('d/m/Y H:i', strtotime($startAt)) : '';
            $mail->Body = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222">'
                .'<h2 style="color:#2563eb;margin:0 0 12px">Biên nhận thanh toán</h2>'
                .'<p>Chúng tôi xác nhận hóa đơn <strong>#'.$invoiceId.'</strong> đã được thanh toán đầy đủ với số tiền <strong>'.$amt.' ₫</strong> qua <strong>VNPay</strong>.</p>'
                .'<p><strong>Dịch vụ:</strong> '.htmlspecialchars($svc, ENT_QUOTES, 'UTF-8').'</p>'
                .($when !== '' ? '<p><strong>Thời gian chụp:</strong> '.htmlspecialchars($when, ENT_QUOTES, 'UTF-8').'</p>' : '')
                .($branchName ? '<p><strong>Chi nhánh:</strong> '.htmlspecialchars($branchName, ENT_QUOTES, 'UTF-8').'</p>' : '')
                .'<p>Mã hóa đơn: <strong>#'.$invoiceId.'</strong>. Xin cảm ơn quý khách!</p>'
                .'<hr style="margin:20px 0"><p style="font-size:12px;color:#555">Email tự động – vui lòng không trả lời trực tiếp.</p></div>';
            $mail->send();
            echo '<p style="color:green;font-weight:bold">✅ Email đã gửi thành công!</p>';
            echo '<p>Kiểm tra hộp thư: ' . htmlspecialchars($email) . '</p>';
        } catch (Exception $e) {
            echo '<p style="color:red;font-weight:bold">❌ Lỗi gửi email:</p>';
            echo '<pre>' . htmlspecialchars($mail->ErrorInfo ?? $e->getMessage()) . '</pre>';
        }
    } else {
        $stmt->close();
        echo '<p style="color:red">Không tìm thấy hóa đơn #' . $id . ' hoặc thiếu email</p>';
    }
}
