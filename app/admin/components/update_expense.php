<?php
include '../../../database/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['status' => 'error', 'message' => 'Phương thức không được hỗ trợ']);
  exit;
}

$tenCpOld = $_POST['ten_cp_old'] ?? null;
$tenCpNew = $_POST['ten_cp_new'] ?? null;
$giaTriNew = $_POST['gia_tri'] ?? null;
$motaCp = $_POST['mota_cp'] ?? null;
$branchId = $_POST['branch_id'] ?? null;

if (!$tenCpOld || !$tenCpNew || $giaTriNew === null || !$branchId) {
  echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin']);
  exit;
}

$branchId = (int)$branchId;
$giaTriNew = (float)$giaTriNew;
$tenCpOld = trim($tenCpOld);
$tenCpNew = trim($tenCpNew);
$motaCp = trim($motaCp) ?: null;

$conn->begin_transaction();

try {
  // Cập nhật chi_phi_phat_sinh
  $updateStmt = $conn->prepare("UPDATE chi_phi_phat_sinh SET TEN_CP = ?, GIA_TRI = ?, MOTA_CP = ? WHERE TEN_CP = ? AND ID_CN = ?");
  $updateStmt->bind_param('sdisi', $tenCpNew, $giaTriNew, $motaCp, $tenCpOld, $branchId);
  $updateStmt->execute();
  $updatedRows = $updateStmt->affected_rows;
  $updateStmt->close();

  // Cập nhật tai_chinh (nếu không phải thuế)
  if ($tenCpOld !== 'Thuế VAT') {
    $updateFinanceStmt = $conn->prepare("UPDATE tai_chinh SET LOAI_CHI_TIET = ?, SO_TIEN = ? WHERE ID_CN = ? AND LOAI_CHI_TIET = ? LIMIT 1");
    $updateFinanceStmt->bind_param('sdis', $tenCpNew, $giaTriNew, $branchId, $tenCpOld);
    $updateFinanceStmt->execute();
    $updateFinanceStmt->close();
  }

  $conn->commit();

  if ($updatedRows > 0) {
    echo json_encode(['status' => 'success', 'message' => 'Cập nhật chi phí thành công']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy chi phí để cập nhật']);
  }
} catch (Throwable $throwable) {
  $conn->rollback();
  echo json_encode(['status' => 'error', 'message' => 'Lỗi cập nhật chi phí: ' . $throwable->getMessage()]);
}
