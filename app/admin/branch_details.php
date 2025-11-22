<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../database/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID không hợp lệ']);
    exit;
}

$stmt = $conn->prepare('SELECT ID_CN, TEN_CN, SDT_CN, DIA_CHI_CN, LATITUDE, LONGITUDE FROM chi_nhanh WHERE ID_CN = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$branch = $stmt->get_result()->fetch_assoc();
if (!$branch) {
    http_response_code(404);
    echo json_encode(['error' => 'Không tìm thấy chi nhánh']);
    exit;
}

$stmtEmp = $conn->prepare('SELECT COUNT(*) AS total_employees FROM nhan_vien WHERE ID_CN = ?');
$stmtEmp->bind_param('i', $id);
$stmtEmp->execute();
$totalEmployees = (int)($stmtEmp->get_result()->fetch_assoc()['total_employees'] ?? 0);

$stmtManagers = $conn->prepare("SELECT HO_TEN, EMAIL, SDT FROM nhan_vien WHERE ID_CN = ? AND LOAI_NV = 'quan_ly'");
$stmtManagers->bind_param('i', $id);
$stmtManagers->execute();
$managers = [];
$resManagers = $stmtManagers->get_result();
while ($row = $resManagers->fetch_assoc()) { $managers[] = $row; }

$stmtRevenue = $conn->prepare("SELECT COALESCE(SUM(h.TONG_TIEN),0) AS revenue FROM hoa_don h JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN WHERE l.ID_CHINHANH = ? AND h.TRANGTHAI_THANHTOAN = 'Đã thanh toán'");
$stmtRevenue->bind_param('i', $id);
$stmtRevenue->execute();
$revenue = (float)($stmtRevenue->get_result()->fetch_assoc()['revenue'] ?? 0);

$stmtExpense = $conn->prepare('SELECT COALESCE(SUM(GIA_TRI),0) AS expenses FROM chi_phi_phat_sinh WHERE ID_CN = ?');
$stmtExpense->bind_param('i', $id);
$stmtExpense->execute();
$expenses = (float)($stmtExpense->get_result()->fetch_assoc()['expenses'] ?? 0);

$stmtUpcoming = $conn->prepare("SELECT COUNT(*) AS upcoming FROM lich_hen WHERE ID_CHINHANH = ? AND TRANGTHAI = 'Đã xác nhận' AND THOI_GIAN_BAT_DAU >= NOW()");
$stmtUpcoming->bind_param('i', $id);
$stmtUpcoming->execute();
$upcoming = (int)($stmtUpcoming->get_result()->fetch_assoc()['upcoming'] ?? 0);

$data = [
    'branch' => $branch,
    'stats' => [
        'total_employees' => $totalEmployees,
        'managers' => $managers,
        'revenue' => $revenue,
        'expenses' => $expenses,
        'net' => $revenue - $expenses,
        'upcoming_appointments' => $upcoming
    ]
];

echo json_encode($data);
