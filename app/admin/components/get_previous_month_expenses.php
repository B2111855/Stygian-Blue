<?php
include '../../../database/config.php';

header('Content-Type: application/json');

$branch = $_GET['branch'] ?? '';
$currentMonth = $_GET['month'] ?? date('Y-m');

// Validate branch
if (!preg_match('/^cn(\d+)$/', $branch, $matches)) {
  echo json_encode(['status' => 'error', 'message' => 'Chi nhánh không hợp lệ']);
  exit;
}

$branchId = (int)$matches[1];

// Tính tháng trước
$currentDate = DateTime::createFromFormat('Y-m', $currentMonth) ?: new DateTime('first day of this month');
$prevDate = clone $currentDate;
$prevDate->modify('-1 month');
$previousMonth = $prevDate->format('Y-m');

// Lấy chi phí tháng trước
$query = "SELECT TEN_CP, GIA_TRI, MOTA_CP 
          FROM chi_phi_phat_sinh 
          WHERE ID_CN = ? AND DATE_FORMAT(NGAY_GIO, '%Y-%m') = ?
          ORDER BY TEN_CP";

$stmt = $conn->prepare($query);
if (!$stmt) {
  echo json_encode(['status' => 'error', 'message' => 'Lỗi truy vấn: ' . $conn->error]);
  exit;
}

$stmt->bind_param('is', $branchId, $previousMonth);
$stmt->execute();
$result = $stmt->get_result();

$expenses = [];
while ($row = $result->fetch_assoc()) {
  $expenses[] = [
    'name' => $row['TEN_CP'],
    'amount' => (float)$row['GIA_TRI'],
    'note' => $row['MOTA_CP'] ?: ''
  ];
}

$stmt->close();

if (empty($expenses)) {
  echo json_encode([
    'status' => 'empty',
    'message' => "Tháng $previousMonth chưa có chi phí. Vui lòng nhập chi phí mới.",
    'month' => $previousMonth
  ]);
} else {
  echo json_encode([
    'status' => 'success',
    'month' => $previousMonth,
    'expenses' => $expenses
  ]);
}
