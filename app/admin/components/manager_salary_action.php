<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../middlewares/require_staff_manager.php';
require_once __DIR__ . '/../../../database/config.php';
require_once __DIR__ . '/../../helpers/branch_salary.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../manager_dashboard.php?page=salaries');
    exit;
}

$managerId = $_SESSION['ID_TK'] ?? null;
if (!$managerId) {
    header('Location: ../manager_dashboard.php?page=salaries&notice=Phiên+làm+việc+không+hợp+lệ&noticeType=error');
    exit;
}

try {
    ensureBranchSalaryMetaTable($conn);
} catch (Throwable $e) {
    header('Location: ../manager_dashboard.php?page=salaries&notice=' . urlencode($e->getMessage()) . '&noticeType=error');
    exit;
}

$employeeId = trim($_POST['employee_id'] ?? '');
$month = isset($_POST['month']) ? max(1, min(12, (int)$_POST['month'])) : (int)date('n');
$year = isset($_POST['year']) ? max(2000, (int)$_POST['year']) : (int)date('Y');
$allowance = max(0, (int)($_POST['allowance'] ?? 0));
$deduction = max(0, (int)($_POST['deduction'] ?? 0));
$status = $_POST['status'] ?? 'cho_duyet';
$note = trim($_POST['note'] ?? '');
$returnUrl = $_POST['return_url'] ?? '../manager_dashboard.php?page=salaries';

$allowedStatuses = ['cho_duyet', 'da_duyet', 'da_chi_tra'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'cho_duyet';
}

$branchId = getBranchIdForUser($conn, $managerId);
if (!$branchId) {
    header('Location: ../manager_dashboard.php?page=salaries&notice=Không+thể+nhận+chi+nhánh&noticeType=error');
    exit;
}

if ($employeeId === '') {
    header('Location: ../manager_dashboard.php?page=salaries&notice=Thiếu+thông+tin+nhân+viên&noticeType=error');
    exit;
}

// Bảo đảm nhân viên thuộc chi nhánh đang quản lý
$checkEmployee = $conn->prepare('SELECT 1 FROM nhan_vien WHERE ID_TK = ? AND ID_CN = ? LIMIT 1');
$checkEmployee->bind_param('si', $employeeId, $branchId);
$checkEmployee->execute();
$belongs = $checkEmployee->get_result()->fetch_assoc();
$checkEmployee->close();

if (!$belongs) {
    header('Location: ../manager_dashboard.php?page=salaries&notice=Nhân+viên+không+thuộc+chi+nhánh&noticeType=error');
    exit;
}

// Bảo đảm có bản ghi lương đầu kỳ
$salaryStmt = $conn->prepare('SELECT TONG_LUONG FROM luong_nhan_vien WHERE ID_TK = ? AND THANG = ? AND NAM = ? LIMIT 1');
$salaryStmt->bind_param('sii', $employeeId, $month, $year);
$salaryStmt->execute();
$salaryRow = $salaryStmt->get_result()->fetch_assoc();
$salaryStmt->close();

if (!$salaryRow) {
    try {
        recalculateBranchSalaries($conn, $branchId, $month, $year, $managerId);
    } catch (Throwable $e) {
        header('Location: ../manager_dashboard.php?page=salaries&notice=' . urlencode($e->getMessage()) . '&noticeType=error');
        exit;
    }
    $salaryStmt = $conn->prepare('SELECT TONG_LUONG FROM luong_nhan_vien WHERE ID_TK = ? AND THANG = ? AND NAM = ? LIMIT 1');
    $salaryStmt->bind_param('sii', $employeeId, $month, $year);
    $salaryStmt->execute();
    $salaryRow = $salaryStmt->get_result()->fetch_assoc();
    $salaryStmt->close();
}

$metaStmt = $conn->prepare('SELECT BASE_TONG_LUONG, PHU_CAP, KHOAN_TRU FROM quanly_luong_chinhanh WHERE ID_TK_NV = ? AND THANG = ? AND NAM = ? LIMIT 1');
$metaStmt->bind_param('sii', $employeeId, $month, $year);
$metaStmt->execute();
$metaRow = $metaStmt->get_result()->fetch_assoc();
$metaStmt->close();

// Trạng thái cũ để tránh chèn trùng vào tai_chinh khi đã ở trạng thái 'da_chi_tra'
$prevStatus = null;
$prevStmt = $conn->prepare('SELECT TRANG_THAI FROM quanly_luong_chinhanh WHERE ID_TK_NV = ? AND THANG = ? AND NAM = ? LIMIT 1');
$prevStmt->bind_param('sii', $employeeId, $month, $year);
$prevStmt->execute();
$prevRow = $prevStmt->get_result()->fetch_assoc();
$prevStatus = $prevRow['TRANG_THAI'] ?? null;
$prevStmt->close();

$baseAmount = $metaRow ? (int)$metaRow['BASE_TONG_LUONG'] : (int)($salaryRow['TONG_LUONG'] ?? 0);
$baseAmount = max(0, $baseAmount);
$net = max(0, $baseAmount + $allowance - $deduction);

$noteParam = $note !== '' ? $note : null;
$payoutDate = $status === 'da_chi_tra' ? date('Y-m-d H:i:s') : null;

$upsert = $conn->prepare(
    'INSERT INTO quanly_luong_chinhanh (ID_TK_NV, ID_TK_MANAGER, ID_CN, THANG, NAM, BASE_TONG_LUONG, PHU_CAP, KHOAN_TRU, THUC_LINH, TRANG_THAI, GHI_CHU, NGAY_CHI_TRA)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        ID_TK_MANAGER = VALUES(ID_TK_MANAGER),
        ID_CN = VALUES(ID_CN),
        BASE_TONG_LUONG = VALUES(BASE_TONG_LUONG),
        PHU_CAP = VALUES(PHU_CAP),
        KHOAN_TRU = VALUES(KHOAN_TRU),
        THUC_LINH = VALUES(THUC_LINH),
        TRANG_THAI = VALUES(TRANG_THAI),
        GHI_CHU = VALUES(GHI_CHU),
        NGAY_CHI_TRA = VALUES(NGAY_CHI_TRA),
        NGAY_CAP_NHAT = CURRENT_TIMESTAMP'
);
$upsert->bind_param('ssiiiiiiisss', $employeeId, $managerId, $branchId, $month, $year, $baseAmount, $allowance, $deduction, $net, $status, $noteParam, $payoutDate);
$upsert->execute();
$upsert->close();

$updateSalary = $conn->prepare('UPDATE luong_nhan_vien SET TONG_LUONG = ?, NGAY_TINH = CURDATE() WHERE ID_TK = ? AND THANG = ? AND NAM = ?');
$updateSalary->bind_param('isii', $net, $employeeId, $month, $year);
$updateSalary->execute();
$updateSalary->close();

// Đồng bộ vào tai_chinh khi chuyển trạng thái sang 'da_chi_tra' (idempotent)
if ($status === 'da_chi_tra' && $prevStatus !== 'da_chi_tra') {
    $insertFinanceQuery = "INSERT INTO tai_chinh (LOAI_GIAO_DICH, SO_TIEN, LOAI_CHI_TIET, NGAY_GIAO_DICH, ID_CN, TRANG_THAI)
                          VALUES ('chi phí', ?, 'Lương nhân viên', NOW(), ?, 'đã thanh toán')";
    $financeStmt = $conn->prepare($insertFinanceQuery);
    $financeStmt->bind_param('ii', $net, $branchId);
    $financeStmt->execute();
    $financeStmt->close();
}

$redirect = $returnUrl ?: '../manager_dashboard.php?page=salaries';
if (preg_match('/^https?:\/\//i', $redirect)) {
    $redirect = '../manager_dashboard.php?page=salaries';
}
if (strpos($redirect, 'manager_dashboard.php') === false) {
    $redirect = '../manager_dashboard.php?page=salaries';
}

$notice = $status === 'da_chi_tra' ? 'Đã chốt chi trả cho nhân viên.' : 'Đã cập nhật điều chỉnh lương.';
$separator = strpos($redirect, '?') !== false ? '&' : '?';

header('Location: ' . $redirect . $separator . 'notice=' . urlencode($notice) . '&noticeType=success');
exit;
