<?php
include '../../../database/config.php';

// Lấy danh sách chi nhánh
$branchQuery = mysqli_query($conn, "SELECT ID_CN, TEN_CN FROM chi_nhanh");
$branches = [];
while ($row = mysqli_fetch_assoc($branchQuery)) {
  $branches[] = $row;
}

// Xử lý lưu chi phí khi có request POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $branchParam = $_POST['branch'] ?? '';
  $rent = floatval($_POST['rent'] ?? 0);
  $utilities = floatval($_POST['utilities'] ?? 0);

  if (!$branchParam || !preg_match('/^cn(\d+)$/', $branchParam, $matches)) {
    echo json_encode(["status" => "error", "message" => "Chi nhánh không hợp lệ."]);
    exit;
  }

  $branchId = (int)$matches[1];
  $now = new DateTime();
  $firstOfMonth = $now->format('Y-m-01 00:00:00');
  $month = $now->format('Y-m');

  // Xóa các khoản phát sinh tháng hiện tại
  mysqli_query($conn, "DELETE FROM chi_phi_phat_sinh WHERE ID_CN = $branchId AND DATE_FORMAT(NGAY_GIO, '%Y-%m') = '$month'");

  // Xóa luôn các bản ghi tài chính tương ứng để tránh trùng
  mysqli_query($conn, "DELETE FROM tai_chinh WHERE ID_CN = $branchId AND DATE_FORMAT(NGAY_GIAO_DICH, '%Y-%m') = '$month' AND LOAI_GIAO_DICH IN ('chi phí', 'thuế')");

  // Thêm chi phí mặt bằng
  if ($rent > 0) {
    $stmt = mysqli_prepare($conn, "INSERT INTO chi_phi_phat_sinh (TEN_CP, MOTA_CP, GIA_TRI, NGAY_GIO, ID_CN) VALUES (?, ?, ?, ?, ?)");
    $type = 'Chi phí mặt bằng';
    $desc = "Chi phí thuê mặt bằng tháng $month";
    mysqli_stmt_bind_param($stmt, 'ssdsi', $type, $desc, $rent, $firstOfMonth, $branchId);
    mysqli_stmt_execute($stmt);

    mysqli_query($conn, "INSERT INTO tai_chinh (LOAI_GIAO_DICH, SO_TIEN, NGAY_GIAO_DICH, ID_CN) VALUES ('chi phí', $rent, '$firstOfMonth', $branchId)");
  }

  // Thêm chi phí điện nước
  if ($utilities > 0) {
    $stmt = mysqli_prepare($conn, "INSERT INTO chi_phi_phat_sinh (TEN_CP, MOTA_CP, GIA_TRI, NGAY_GIO, ID_CN) VALUES (?, ?, ?, ?, ?)");
    $type = 'Chi phí điện nước';
    $desc = "Chi phí điện nước tháng $month";
    mysqli_stmt_bind_param($stmt, 'ssdsi', $type, $desc, $utilities, $firstOfMonth, $branchId);
    mysqli_stmt_execute($stmt);

    mysqli_query($conn, "INSERT INTO tai_chinh (LOAI_GIAO_DICH, SO_TIEN, NGAY_GIAO_DICH, ID_CN) VALUES ('chi phí', $utilities, '$firstOfMonth', $branchId)");
  }

  // Tự động tính và lưu thuế (10% doanh thu)
  $result = mysqli_query($conn, "SELECT SUM(SO_TIEN) AS doanh_thu FROM tai_chinh WHERE ID_CN = $branchId AND LOAI_GIAO_DICH = 'doanh thu' AND DATE_FORMAT(NGAY_GIAO_DICH, '%Y-%m') = '$month'");
  $row = mysqli_fetch_assoc($result);
  $doanhThu = (float)($row['doanh_thu'] ?? 0);
  $tax = $doanhThu * 0.1;

  if ($tax > 0) {
    $desc = "Thuế 10% trên doanh thu tháng $month";
    $stmt = mysqli_prepare($conn, "INSERT INTO chi_phi_phat_sinh (TEN_CP, MOTA_CP, GIA_TRI, NGAY_GIO, ID_CN) VALUES (?, ?, ?, ?, ?)");
    $type = 'Thuế VAT';
    mysqli_stmt_bind_param($stmt, 'ssdsi', $type, $desc, $tax, $firstOfMonth, $branchId);
    mysqli_stmt_execute($stmt);

    // Lưu thuế vào tài chính dưới loại giao dịch là "chi phí"
    mysqli_query($conn, "INSERT INTO tai_chinh (LOAI_GIAO_DICH, SO_TIEN, NGAY_GIAO_DICH, ID_CN) VALUES ('chi phí', $tax, '$firstOfMonth', $branchId)");
  }

  // Kiểm tra xem có phải từ trang nhân viên hay không
  if (isset($_SERVER['HTTP_REFERER']) && str_contains($_SERVER['HTTP_REFERER'], 'staff_dashboard.php')) {
    // Gọi từ giao diện nhân viên → chuyển hướng lại với thông báo
    header("Location: ../staff_dashboard.php?page=staff_expense&success=1");
    exit;
  }

  // Mặc định: trả về JSON để các giao diện khác xử lý qua JavaScript
  echo json_encode(["status" => "success", "message" => "Lưu chi phí thành công."]);
  exit;

}
?>

<!-- HTML giữ nguyên phía dưới -->
