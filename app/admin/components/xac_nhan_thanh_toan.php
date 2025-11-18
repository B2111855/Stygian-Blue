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

$stmtInsert = $conn->prepare("
    INSERT INTO tai_chinh (ID_HD, NGAY_GIAO_DICH, SO_TIEN, LOAI_GIAO_DICH, ID_CN)
    VALUES (?, NOW(), ?, 'doanh thu', ?)
");
if (!$stmtInsert) {
    respondAndExit(false, 'Lỗi khi ghi nhận doanh thu: ' . $conn->error);
}
$stmtInsert->bind_param('idi', $id_hd, $tongTien, $id_cn);
if (!$stmtInsert->execute()) {
    respondAndExit(false, 'Không thể ghi nhận giao dịch tài chính.');
}
$stmtInsert->close();

respondAndExit(true, 'Xác nhận thanh toán thành công!', [
    'invoiceId' => $id_hd,
    'amount' => $tongTien
]);
?>
``
