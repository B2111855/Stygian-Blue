<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../../database/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID không hợp lệ']);
    exit;
}

// Lấy thông tin chi nhánh
$stmt = $conn->prepare('SELECT ID_CN, TEN_CN, SDT_CN, DIA_CHI_CN, LATITUDE, LONGITUDE FROM chi_nhanh WHERE ID_CN = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$branch = $stmt->get_result()->fetch_assoc();
if (!$branch) {
    http_response_code(404);
    echo json_encode(['error' => 'Không tìm thấy chi nhánh']);
    exit;
}

// Tổng số nhân viên
$stmtEmp = $conn->prepare('SELECT COUNT(*) AS total_employees FROM nhan_vien WHERE ID_CN = ?');
$stmtEmp->bind_param('i', $id);
$stmtEmp->execute();
$totalEmployees = $stmtEmp->get_result()->fetch_assoc()['total_employees'] ?? 0;

// Danh sách quản lý
$stmtManagers = $conn->prepare("SELECT HO_TEN, EMAIL, SDT FROM nhan_vien WHERE ID_CN = ? AND LOAI_NV = 'quan_ly'");
$stmtManagers->bind_param('i', $id);
$stmtManagers->execute();
$managers = [];
$resManagers = $stmtManagers->get_result();
while ($row = $resManagers->fetch_assoc()) { $managers[] = $row; }

// Doanh thu (hoa_don đã thanh toán gắn với lịch hẹn của chi nhánh)
$stmtRevenue = $conn->prepare("SELECT COALESCE(SUM(h.TONG_TIEN),0) AS revenue FROM hoa_don h JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN WHERE l.ID_CHINHANH = ? AND h.TRANGTHAI_THANHTOAN = 'Đã thanh toán'");
$stmtRevenue->bind_param('i', $id);
$stmtRevenue->execute();
$revenue = $stmtRevenue->get_result()->fetch_assoc()['revenue'] ?? 0;

// Chi phí phát sinh
$stmtExpense = $conn->prepare('SELECT COALESCE(SUM(GIA_TRI),0) AS expenses FROM chi_phi_phat_sinh WHERE ID_CN = ?');
$stmtExpense->bind_param('i', $id);
$stmtExpense->execute();
$expenses = $stmtExpense->get_result()->fetch_assoc()['expenses'] ?? 0;

// Số lịch hẹn đã xác nhận còn hiệu lực (tương lai)
$stmtUpcoming = $conn->prepare("SELECT COUNT(*) AS upcoming FROM lich_hen WHERE ID_CHINHANH = ? AND TRANGTHAI = 'Đã xác nhận' AND THOI_GIAN_BAT_DAU >= NOW()");
$stmtUpcoming->bind_param('i', $id);
$stmtUpcoming->execute();
$upcoming = $stmtUpcoming->get_result()->fetch_assoc()['upcoming'] ?? 0;

$data = [
    'branch' => $branch,
    'stats' => [
        'total_employees' => (int)$totalEmployees,
        'managers' => $managers,
        'revenue' => (float)$revenue,
        'expenses' => (float)$expenses,
        'net' => (float)$revenue - (float)$expenses,
        'upcoming_appointments' => (int)$upcoming
    ]
];

echo json_encode($data);
