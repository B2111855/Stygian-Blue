<?php
session_start();
require_once '../../../database/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['invoice_id'])) {
    $_SESSION['payment_notice'] = 'Yêu cầu hủy không hợp lệ.';
    $_SESSION['payment_notice_type'] = 'error';
    header('Location: ../Views/hoa_don.php');
    exit;
}

if (empty($_SESSION['ID_TK'])) {
    $_SESSION['payment_notice'] = 'Vui lòng đăng nhập.';
    $_SESSION['payment_notice_type'] = 'error';
    header('Location: ../Views/hoa_don.php');
    exit;
}

$invoiceId = (int)$_POST['invoice_id'];
$userId = $_SESSION['ID_TK'];

// Xác thực hóa đơn thuộc về người dùng
$verifySql = "SELECT h.ID_HD FROM hoa_don h LEFT JOIN lich_hen l ON h.ID_LICHHEN=l.ID_LICHHEN LEFT JOIN don_thue_trang_phuc ttp ON h.ID_TTP=ttp.ID_TTP WHERE h.ID_HD=? AND (l.ID_TK=? OR ttp.ID_TK=?) LIMIT 1";
$verifyStmt = $conn->prepare($verifySql);
if (!$verifyStmt) {
    $_SESSION['payment_notice'] = 'Không thể xác thực hóa đơn.';
    $_SESSION['payment_notice_type'] = 'error';
    header('Location: ../Views/hoa_don.php');
    exit;
}
$verifyStmt->bind_param('iss', $invoiceId, $userId, $userId);
$verifyStmt->execute();
$res = $verifyStmt->get_result();
$valid = $res && $res->fetch_assoc();
$verifyStmt->close();

if (!$valid) {
    $_SESSION['payment_notice'] = 'Hóa đơn không thuộc quyền truy cập.';
    $_SESSION['payment_notice_type'] = 'error';
    header('Location: ../Views/hoa_don.php');
    exit;
}

$upd = $conn->prepare("UPDATE thanh_toan_truc_tuyen SET TRANG_THAI='expired' WHERE ID_HD=? AND GATEWAY='vnpay' AND TRANG_THAI='pending'");
if ($upd) { $upd->bind_param('i', $invoiceId); $upd->execute(); $affected = $upd->affected_rows; $upd->close(); }
else { $affected = 0; }

$_SESSION['payment_notice'] = $affected > 0 ? 'Đã hủy phiên VNPay đang chờ.' : 'Không có phiên VNPay đang chờ để hủy.';
$_SESSION['payment_notice_type'] = $affected > 0 ? 'success' : 'error';

$conn->close();
header('Location: ../Views/hoa_don.php');
exit;
