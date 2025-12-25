<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../database/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // Confirm Invoice Payment (Mark as paid)
    if ($action === 'confirm_payment') {
        $id_hd = isset($_POST['id_hd']) ? (int)$_POST['id_hd'] : 0;
        
        if ($id_hd <= 0) {
            throw new Exception('Mã hóa đơn không hợp lệ.');
        }
        
        // Verify invoice exists
        $stmt = $conn->prepare("SELECT ID_HD, TRANGTHAI_THANHTOAN FROM hoa_don WHERE ID_HD = ? LIMIT 1");
        if (!$stmt) {
            throw new Exception('Lỗi cơ sở dữ liệu: ' . $conn->error);
        }
        
        $stmt->bind_param('i', $id_hd);
        $stmt->execute();
        $result = $stmt->get_result();
        $invoice = $result->fetch_assoc();
        $stmt->close();
        
        if (!$invoice) {
            throw new Exception('Không tìm thấy hóa đơn.');
        }
        
        if ($invoice['TRANGTHAI_THANHTOAN'] === 'Đã thanh toán') {
            throw new Exception('Hóa đơn đã được thanh toán rồi.');
        }
        
        // Update invoice status
        $stmt = $conn->prepare("UPDATE hoa_don SET TRANGTHAI_THANHTOAN = 'Đã thanh toán' WHERE ID_HD = ?");
        if (!$stmt) {
            throw new Exception('Lỗi cập nhật: ' . $conn->error);
        }
        
        $stmt->bind_param('i', $id_hd);
        if (!$stmt->execute()) {
            throw new Exception('Không thể cập nhật trạng thái hóa đơn.');
        }
        $stmt->close();
        
        $response['success'] = true;
        $response['message'] = 'Hóa đơn đã được xác nhận thanh toán.';
        $response['data'] = ['id_hd' => $id_hd];
    }
    
    // Refund Invoice
    else if ($action === 'refund_invoice') {
        $id_hd = isset($_POST['id_hd']) ? (int)$_POST['id_hd'] : 0;
        $refund_amount = isset($_POST['refund_amount']) ? (int)$_POST['refund_amount'] : 0;
        $refund_reason = trim($_POST['refund_reason'] ?? '');
        
        if ($id_hd <= 0) {
            throw new Exception('Mã hóa đơn không hợp lệ.');
        }
        
        if ($refund_amount <= 0) {
            throw new Exception('Số tiền hoàn phải lớn hơn 0.');
        }
        
        // Verify invoice exists and is paid
        $stmt = $conn->prepare("SELECT ID_HD, TRANGTHAI_THANHTOAN, TONG_TIEN FROM hoa_don WHERE ID_HD = ? LIMIT 1");
        if (!$stmt) {
            throw new Exception('Lỗi cơ sở dữ liệu: ' . $conn->error);
        }
        
        $stmt->bind_param('i', $id_hd);
        $stmt->execute();
        $result = $stmt->get_result();
        $invoice = $result->fetch_assoc();
        $stmt->close();
        
        if (!$invoice) {
            throw new Exception('Không tìm thấy hóa đơn.');
        }
        
        if ($invoice['TRANGTHAI_THANHTOAN'] !== 'Đã thanh toán') {
            throw new Exception('Chỉ có thể hoàn tiền cho hóa đơn đã thanh toán.');
        }
        
        if ($refund_amount > (int)$invoice['TONG_TIEN']) {
            throw new Exception('Số tiền hoàn không được vượt quá tổng tiền hóa đơn.');
        }
        
        // Record refund in hoa_don_hoan_tien table
        $stmt = $conn->prepare("INSERT INTO hoa_don_hoan_tien (ID_HD, SO_TIEN_HOAN, LY_DO, NGAY_HOAN) VALUES (?, ?, ?, NOW())");
        if (!$stmt) {
            throw new Exception('Lỗi ghi nhận hoàn tiền: ' . $conn->error);
        }
        
        $stmt->bind_param('iis', $id_hd, $refund_amount, $refund_reason);
        if (!$stmt->execute()) {
            throw new Exception('Không thể ghi nhận hoàn tiền.');
        }
        $stmt->close();
        
        // Update invoice status to refunded
        $stmt = $conn->prepare("UPDATE hoa_don SET TRANGTHAI_THANHTOAN = 'Đã hoàn tiền' WHERE ID_HD = ?");
        if (!$stmt) {
            throw new Exception('Lỗi cập nhật trạng thái: ' . $conn->error);
        }
        
        $stmt->bind_param('i', $id_hd);
        if (!$stmt->execute()) {
            throw new Exception('Không thể cập nhật trạng thái hóa đơn.');
        }
        $stmt->close();
        
        $response['success'] = true;
        $response['message'] = "Đã ghi nhận hoàn tiền " . number_format($refund_amount, 0, ',', '.') . " VND cho hóa đơn #$id_hd.";
        $response['data'] = ['id_hd' => $id_hd, 'refund_amount' => $refund_amount];
    }
    
    // Get Invoice Detail (for modal/inspection)
    else if ($action === 'get_invoice_detail') {
        $id_hd = isset($_POST['id_hd']) ? (int)$_POST['id_hd'] : 0;
        
        if ($id_hd <= 0) {
            throw new Exception('Mã hóa đơn không hợp lệ.');
        }
        
        $stmt = $conn->prepare("
            SELECT 
                hd.ID_HD, hd.NGAY_GIO, hd.TRANGTHAI_THANHTOAN, hd.TONG_TIEN,
                kh.HO_TEN, kh.EMAIL, kh.SDT,
                lh.ID_LICHHEN, dv.TEN_DV, dv.thoi_gian,
                ttp.ID_TTP
            FROM hoa_don hd
            LEFT JOIN lich_hen lh ON hd.ID_LICHHEN = lh.ID_LICHHEN
            LEFT JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
            LEFT JOIN don_thue_trang_phuc ttp ON hd.ID_TTP = ttp.ID_TTP
            LEFT JOIN tai_khoan kh ON COALESCE(lh.ID_TK, ttp.ID_TK) = kh.ID_TK
            WHERE hd.ID_HD = ?
            LIMIT 1
        ");
        
        if (!$stmt) {
            throw new Exception('Lỗi truy vấn: ' . $conn->error);
        }
        
        $stmt->bind_param('i', $id_hd);
        $stmt->execute();
        $result = $stmt->get_result();
        $invoice = $result->fetch_assoc();
        $stmt->close();
        
        if (!$invoice) {
            throw new Exception('Không tìm thấy hóa đơn.');
        }
        
        $response['success'] = true;
        $response['data'] = $invoice;
    }
    
    // Bulk Confirm Multiple Invoices
    else if ($action === 'bulk_confirm') {
        $ids = isset($_POST['ids']) ? (array)$_POST['ids'] : [];
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, fn($id) => $id > 0);
        
        if (empty($ids)) {
            throw new Exception('Chưa chọn hóa đơn nào.');
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        
        $stmt = $conn->prepare("UPDATE hoa_don SET TRANGTHAI_THANHTOAN = 'Đã thanh toán' WHERE ID_HD IN ($placeholders) AND TRANGTHAI_THANHTOAN != 'Đã thanh toán'");
        if (!$stmt) {
            throw new Exception('Lỗi cơ sở dữ liệu: ' . $conn->error);
        }
        
        $stmt->bind_param($types, ...$ids);
        if (!$stmt->execute()) {
            throw new Exception('Không thể cập nhật hóa đơn.');
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        $response['success'] = true;
        $response['message'] = "Đã xác nhận thanh toán cho $affected hóa đơn.";
        $response['data'] = ['count' => $affected];
    }
    
    else {
        throw new Exception('Hành động không hợp lệ.');
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    http_response_code(400);
}

echo json_encode($response);
mysqli_close($conn);
?>
