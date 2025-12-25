<?php
include 'C:\xampp\htdocs\StygianBlue\database\config.php';

$redirectUrl = '/stygianblue/app/admin/admin_dashboard.php?page=payments';
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function respondAndExit($success, $message, $extra = [])
{
    global $isAjax, $redirectUrl;

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    } else {
        $safeMessage = addslashes($message);
        echo "<script>alert('{$safeMessage}'); window.location.href='{$redirectUrl}';</script>";
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_hd'])) {
    respondAndExit(false, 'Yêu cầu không hợp lệ.');
}

$id_hd = (int)$_POST['id_hd'];

$stmt = $conn->prepare("UPDATE hoa_don SET TRANGTHAI_THANHTOAN = 'Đã thanh toán' WHERE ID_HD = ?");
if (!$stmt) {
    respondAndExit(false, 'Lỗi khi cập nhật hóa đơn: ' . $conn->error);
}
$stmt->bind_param('i', $id_hd);
if (!$stmt->execute()) {
    respondAndExit(false, 'Không thể cập nhật trạng thái hóa đơn.');
}
if ($stmt->affected_rows === 0) {
    respondAndExit(false, 'Hóa đơn không tồn tại hoặc đã được xác nhận trước đó.');
}
$stmt->close();

$stmtInfo = $conn->prepare("
    SELECT hd.TONG_TIEN, cn.ID_CN
    FROM hoa_don hd
    JOIN lich_hen lh ON hd.ID_LICHHEN = lh.ID_LICHHEN
    JOIN chi_nhanh cn ON lh.ID_CHINHANH = cn.ID_CN
    WHERE hd.ID_HD = ?
");
if (!$stmtInfo) {
    respondAndExit(false, 'Lỗi khi lấy thông tin chi nhánh: ' . $conn->error);
}
$stmtInfo->bind_param('i', $id_hd);
$stmtInfo->execute();
$result = $stmtInfo->get_result();
$row = $result->fetch_assoc();
$stmtInfo->close();

if (!$row) {
    respondAndExit(false, 'Không tìm thấy dữ liệu tài chính cho hóa đơn này.');
}

$tongTien = $row['TONG_TIEN'];
$id_cn = $row['ID_CN'];

// Cập nhật trạng thái doanh thu từ "chờ thanh toán" -> "đã thanh toán"
// (Doanh thu đã được ghi nhận khi hoàn thành công việc, bây giờ chỉ cập nhật trạng thái)
$stmtUpdateRevenue = $conn->prepare("
    UPDATE tai_chinh 
    SET TRANG_THAI = 'đã thanh toán' 
    WHERE ID_HD = ? AND LOAI_GIAO_DICH = 'doanh thu'
");

if (!$stmtUpdateRevenue) {
    respondAndExit(false, 'Lỗi khi cập nhật trạng thái doanh thu: ' . $conn->error);
}

$stmtUpdateRevenue->bind_param('i', $id_hd);
if (!$stmtUpdateRevenue->execute()) {
    respondAndExit(false, 'Không thể cập nhật trạng thái doanh thu.');
}
$stmtUpdateRevenue->close();

// Nếu doanh thu chưa được ghi (legacy data), ghi vào bây giờ
$stmtCheckRevenue = $conn->prepare("
    SELECT ID_TC FROM tai_chinh 
    WHERE ID_HD = ? AND LOAI_GIAO_DICH = 'doanh thu'
    LIMIT 1
");
if ($stmtCheckRevenue) {
    $stmtCheckRevenue->bind_param('i', $id_hd);
    $stmtCheckRevenue->execute();
    $checkResult = $stmtCheckRevenue->get_result();
    
    if ($checkResult->num_rows === 0) {
        // Chưa có doanh thu, ghi vào ngay lập tức
        $stmtInsertRevenue = $conn->prepare("
            INSERT INTO tai_chinh (ID_HD, NGAY_GIAO_DICH, SO_TIEN, LOAI_GIAO_DICH, LOAI_CHI_TIET, ID_CN, TRANG_THAI)
            VALUES (?, NOW(), ?, 'doanh thu', 'Dịch vụ chụp ảnh', ?, 'đã thanh toán')
        ");
        if ($stmtInsertRevenue) {
            $stmtInsertRevenue->bind_param('idi', $id_hd, $tongTien, $id_cn);
            $stmtInsertRevenue->execute();
            $stmtInsertRevenue->close();
        }
    }
    $stmtCheckRevenue->close();
}

respondAndExit(true, 'Xác nhận thanh toán thành công!', [
    'invoiceId' => $id_hd,
    'amount' => $tongTien
]);
?>
``
