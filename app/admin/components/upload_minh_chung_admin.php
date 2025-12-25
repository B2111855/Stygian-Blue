<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../database/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function respondAndExit($success, $message, $extra = [])
{
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    } else {
        $_SESSION['upload_notice'] = $message;
        $_SESSION['upload_notice_type'] = $success ? 'success' : 'error';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'admin_dashboard.php?page=payments'));
    }
    exit;
}

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_hd'])) {
    respondAndExit(false, 'Yêu cầu không hợp lệ.');
}

$id_hd = (int)$_POST['id_hd'];

// Verify invoice exists and not yet paid
$checkStmt = mysqli_prepare($conn, "
    SELECT ID_HD, TRANGTHAI_THANHTOAN, TONG_TIEN
    FROM hoa_don
    WHERE ID_HD = ?
");

if (!$checkStmt) {
    respondAndExit(false, 'Lỗi hệ thống: ' . mysqli_error($conn));
}

mysqli_stmt_bind_param($checkStmt, 'i', $id_hd);
mysqli_stmt_execute($checkStmt);
$invoice = mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt));
mysqli_stmt_close($checkStmt);

if (!$invoice) {
    respondAndExit(false, 'Không tìm thấy hóa đơn.');
}

if ($invoice['TRANGTHAI_THANHTOAN'] === 'Đã thanh toán') {
    respondAndExit(false, 'Hóa đơn này đã được thanh toán rồi.');
}

// Validate file upload
if (!isset($_FILES['minh_chung']) || $_FILES['minh_chung']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = match($_FILES['minh_chung']['error'] ?? null) {
        UPLOAD_ERR_NO_FILE => 'Vui lòng chọn tệp.',
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Tệp quá lớn (tối đa 5MB).',
        default => 'Lỗi tải lên tệp.'
    };
    respondAndExit(false, $errorMsg);
}

$file = $_FILES['minh_chung'];
$maxSize = 5 * 1024 * 1024; // 5MB

if ($file['size'] > $maxSize) {
    respondAndExit(false, 'Tệp quá lớn. Tối đa 5MB.');
}

// Validate file type
$allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimes)) {
    respondAndExit(false, 'Định dạng tệp không hợp lệ. Chỉ chấp nhận JPG, PNG, PDF.');
}

// Create upload directory
$uploadDir = '../../public/images/uploads/minh_chung/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$ext = match($mimeType) {
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'application/pdf' => 'pdf',
};
$filename = 'minh_chung_' . $id_hd . '_' . time() . '.' . $ext;
$filepath = $uploadDir . $filename;

// Save file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    respondAndExit(false, 'Không thể lưu tệp. Vui lòng thử lại.');
}

// Get additional data
$maThamChieu = trim($_POST['ma_tham_chieu'] ?? '');
$ghiChu = trim($_POST['ghi_chu'] ?? '');

// Insert into bang_chung_thanh_toan
$insertStmt = mysqli_prepare($conn, "
    INSERT INTO bang_chung_thanh_toan (ID_HD, TEP_MINH_CHUNG, GHI_CHU, NGUOI_XAC_NHAN, THOI_GIAN_XN, KET_QUA)
    VALUES (?, ?, ?, ?, NOW(), 'Đã xác nhận')
");

if (!$insertStmt) {
    @unlink($filepath);
    respondAndExit(false, 'Lỗi hệ thống: ' . mysqli_error($conn));
}

$adminName = $_SESSION['HO_TEN'] ?? 'Nhân viên quản lý';
$proofData = json_encode([
    'file' => $filename,
    'ma_tham_chieu' => $maThamChieu,
    'note' => $ghiChu
]);

mysqli_stmt_bind_param($insertStmt, 'isss', $id_hd, $proofData, $ghiChu, $adminName);

if (!mysqli_stmt_execute($insertStmt)) {
    @unlink($filepath);
    mysqli_stmt_close($insertStmt);
    respondAndExit(false, 'Lỗi lưu minh chứng: ' . mysqli_error($conn));
}

mysqli_stmt_close($insertStmt);

// Update invoice status to "Đã thanh toán"
$updateStmt = mysqli_prepare($conn, "
    UPDATE hoa_don 
    SET TRANGTHAI_THANHTOAN = 'Đã thanh toán', PHUONGTHUC_THANHTOAN = 'Chuyển khoản ngân hàng'
    WHERE ID_HD = ?
");

if (!$updateStmt) {
    respondAndExit(false, 'Lỗi cập nhật hóa đơn: ' . mysqli_error($conn));
}

mysqli_stmt_bind_param($updateStmt, 'i', $id_hd);

if (!mysqli_stmt_execute($updateStmt)) {
    mysqli_stmt_close($updateStmt);
    respondAndExit(false, 'Lỗi cập nhật hóa đơn: ' . mysqli_error($conn));
}

mysqli_stmt_close($updateStmt);

// Success response
respondAndExit(true, 'Minh chứng đã được lưu và hóa đơn được đánh dấu là đã thanh toán.', [
    'invoice_id' => $id_hd,
    'status' => 'Đã thanh toán',
    'redirect' => 'admin_dashboard.php?page=payments'
]);
