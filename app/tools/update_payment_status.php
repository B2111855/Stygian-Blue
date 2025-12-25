<?php
/**
 * Manual Payment Status Update
 * Use when IPN is not called - updates DB for successful VNPay payments
 */

require_once __DIR__ . '/../../database/config.php';

$txnRef = isset($_GET['txn']) ? $_GET['txn'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $txnRef = $_POST['txn_ref'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Update thanh_toan_truc_tuyen to 'thanh_cong'
        $stmt1 = $conn->prepare("
            UPDATE thanh_toan_truc_tuyen 
            SET TRANG_THAI = 'thanh_cong',
                THOI_GIAN_THANH_TOAN = COALESCE(THOI_GIAN_THANH_TOAN, NOW())
            WHERE MA_THAM_CHIEU = ?
        ");
        $stmt1->bind_param('s', $txnRef);
        $stmt1->execute();
        $updated1 = $stmt1->affected_rows;
        
        // 2. Get invoice details
        $stmt2 = $conn->prepare("
            SELECT tt.ID_HD, tt.SO_TIEN
            FROM thanh_toan_truc_tuyen tt
            WHERE tt.MA_THAM_CHIEU = ?
        ");
        $stmt2->bind_param('s', $txnRef);
        $stmt2->execute();
        $result = $stmt2->get_result();
        $payment = $result->fetch_assoc();
        
        if ($payment) {
            $idHD = $payment['ID_HD'];
            
            // 3. Calculate total paid for this invoice
            $stmt3 = $conn->prepare("
                SELECT COALESCE(SUM(SO_TIEN), 0) as total_paid
                FROM thanh_toan_truc_tuyen
                WHERE ID_HD = ? AND TRANG_THAI = 'thanh_cong'
            ");
            $stmt3->bind_param('i', $idHD);
            $stmt3->execute();
            $paid = $stmt3->get_result()->fetch_assoc()['total_paid'];
            
            // 4. Get invoice total
            $stmt4 = $conn->prepare("SELECT TONG_TIEN FROM hoa_don WHERE ID_HD = ?");
            $stmt4->bind_param('i', $idHD);
            $stmt4->execute();
            $total = $stmt4->get_result()->fetch_assoc()['TONG_TIEN'];
            
            // 5. Update invoice status if fully paid
            if ($paid >= $total) {
                $stmt5 = $conn->prepare("
                    UPDATE hoa_don 
                    SET TRANGTHAI_THANHTOAN = 'Đã thanh toán'
                    WHERE ID_HD = ?
                ");
                $stmt5->bind_param('i', $idHD);
                $stmt5->execute();
                $updated2 = $stmt5->affected_rows;
            }
        }
        
        $conn->commit();
        $success = true;
        $message = "✅ Đã cập nhật thành công: $updated1 giao dịch, " . ($updated2 ?? 0) . " hóa đơn";
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "❌ Lỗi: " . $e->getMessage();
    }
}

// Display current status
if ($txnRef) {
    $stmt = $conn->prepare("
        SELECT 
            tt.ID_TT,
            tt.MA_THAM_CHIEU,
            tt.TRANG_THAI as tt_status,
            tt.SO_TIEN,
            tt.THOI_GIAN_THANH_TOAN,
            hd.ID_HD,
            hd.TRANGTHAI_THANHTOAN as hd_status,
            hd.TONG_TIEN,
            hd.ID_LICHHEN,
            tk.EMAIL,
            tk.HO_TEN
        FROM thanh_toan_truc_tuyen tt
        LEFT JOIN hoa_don hd ON hd.ID_HD = tt.ID_HD
        LEFT JOIN lich_hen lh ON lh.ID_LICHHEN = hd.ID_LICHHEN
        LEFT JOIN tai_khoan tk ON tk.ID_TK = lh.ID_TK
        WHERE tt.MA_THAM_CHIEU = ?
    ");
    $stmt->bind_param('s', $txnRef);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập Nhật Trạng Thái Thanh Toán</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; max-width: 800px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .success { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .error { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .info { background-color: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .status-box { border: 1px solid #ddd; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .status-pending { border-left: 4px solid #ffc107; }
        .status-success { border-left: 4px solid #28a745; }
        .data-row { display: flex; margin-bottom: 10px; }
        .data-label { width: 200px; font-weight: bold; }
        .data-value { flex: 1; }
    </style>
</head>
<body>
    <h1>🔧 Cập Nhật Trạng Thái Thanh Toán Thủ Công</h1>
    
    <div class="info">
        <strong>ℹ️ Công cụ này dùng khi:</strong><br>
        - VNPay IPN không được gọi (ngrok không nhận request)<br>
        - Khách hàng đã thanh toán thành công nhưng DB chưa cập nhật<br>
        - Cần cập nhật thủ công để gửi email xác nhận
    </div>
    
    <?php if (isset($success)): ?>
        <div class="success"><?= $message ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>
    
    <form method="GET">
        <div class="form-group">
            <label for="txn">Mã giao dịch (vnp_TxnRef):</label>
            <input type="text" id="txn" name="txn" value="<?= htmlspecialchars($txnRef) ?>" 
                   placeholder="Ví dụ: HD48_20251221165448">
        </div>
        <button type="submit">Kiểm tra</button>
    </form>
    
    <?php if ($txnRef && $data): ?>
        <h2>Trạng Thái Hiện Tại</h2>
        <div class="status-box <?= $data['tt_status'] === 'thanh_cong' ? 'status-success' : 'status-pending' ?>">
            <div class="data-row">
                <div class="data-label">Mã giao dịch:</div>
                <div class="data-value"><?= htmlspecialchars($data['MA_THAM_CHIEU']) ?></div>
            </div>
            <div class="data-row">
                <div class="data-label">Trạng thái giao dịch:</div>
                <div class="data-value">
                    <strong style="color: <?= $data['tt_status'] === 'thanh_cong' ? 'green' : 'orange' ?>">
                        <?= $data['tt_status'] ?>
                    </strong>
                </div>
            </div>
            <div class="data-row">
                <div class="data-label">Số tiền:</div>
                <div class="data-value"><?= number_format($data['SO_TIEN']) ?> VNĐ</div>
            </div>
            <div class="data-row">
                <div class="data-label">Thời gian:</div>
                <div class="data-value"><?= $data['THOI_GIAN_THANH_TOAN'] ?? 'Chưa có' ?></div>
            </div>
            <div class="data-row">
                <div class="data-label">Hóa đơn:</div>
                <div class="data-value">#<?= $data['ID_HD'] ?></div>
            </div>
            <div class="data-row">
                <div class="data-label">Trạng thái hóa đơn:</div>
                <div class="data-value">
                    <strong style="color: <?= $data['hd_status'] === 'Đã thanh toán' ? 'green' : 'orange' ?>">
                        <?= $data['hd_status'] ?>
                    </strong>
                </div>
            </div>
            <div class="data-row">
                <div class="data-label">Tổng tiền hóa đơn:</div>
                <div class="data-value"><?= number_format($data['TONG_TIEN']) ?> VNĐ</div>
            </div>
            <div class="data-row">
                <div class="data-label">Khách hàng:</div>
                <div class="data-value"><?= htmlspecialchars($data['HO_TEN']) ?></div>
            </div>
            <div class="data-row">
                <div class="data-label">Email:</div>
                <div class="data-value"><?= htmlspecialchars($data['EMAIL']) ?></div>
            </div>
        </div>
        
        <?php if ($data['tt_status'] !== 'thanh_cong' || $data['hd_status'] !== 'Đã thanh toán'): ?>
            <form method="POST">
                <input type="hidden" name="txn_ref" value="<?= htmlspecialchars($txnRef) ?>">
                <button type="submit" name="update" value="1" 
                        onclick="return confirm('Xác nhận cập nhật trạng thái thành công?')">
                    🔄 Cập nhật thành "thanh_cong"
                </button>
            </form>
        <?php else: ?>
            <div class="success">
                ✅ Giao dịch và hóa đơn đã ở trạng thái thành công.<br>
                Bạn có thể <a href="manual_email_sender.php">gửi email xác nhận</a> cho khách hàng.
            </div>
        <?php endif; ?>
        
    <?php elseif ($txnRef): ?>
        <div class="error">❌ Không tìm thấy giao dịch với mã: <?= htmlspecialchars($txnRef) ?></div>
    <?php endif; ?>
    
</body>
</html>
