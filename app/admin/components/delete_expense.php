<?php
include '../../../database/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['status' => 'error', 'message' => 'Phương thức không được hỗ trợ']);
  exit;
}

$expenseId = $_POST['id'] ?? null;
$tenCp = $_POST['ten_cp'] ?? null;
$branchId = $_POST['branch_id'] ?? null;

if (!$expenseId || !$tenCp || !$branchId) {
  echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin']);
  exit;
}

$expenseId = (int)$expenseId;
$branchId = (int)$branchId;
$tenCp = trim($tenCp);

$conn->begin_transaction();

try {
  // Xóa từ bảng chi_phi_phat_sinh
  $deleteStmt = $conn->prepare("DELETE FROM chi_phi_phat_sinh WHERE TEN_CP = ? AND ID_CN = ?");
  $deleteStmt->bind_param('si', $tenCp, $branchId);
  $deleteStmt->execute();
  $deletedExpense = $deleteStmt->affected_rows;
  $deleteStmt->close();

  // Xóa chi phí tương ứng từ bảng tai_chinh (nếu đó không phải là thuế)
  if ($tenCp !== 'Thuế VAT') {
    $deleteFinanceStmt = $conn->prepare("DELETE FROM tai_chinh WHERE ID_CN = ? AND LOAI_CHI_TIET = ? LIMIT 1");
    $deleteFinanceStmt->bind_param('is', $branchId, $tenCp);
    $deleteFinanceStmt->execute();
    $deleteFinanceStmt->close();
  }

  $conn->commit();

  if ($deletedExpense > 0) {
    echo json_encode(['status' => 'success', 'message' => 'Xóa chi phí thành công']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy chi phí để xóa']);
  }
} catch (Throwable $throwable) {
  $conn->rollback();
  echo json_encode(['status' => 'error', 'message' => 'Lỗi xóa chi phí: ' . $throwable->getMessage()]);
}
