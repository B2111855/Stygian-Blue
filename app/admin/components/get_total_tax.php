<?php
include '../../../database/config.php';

$branch = $_GET['branch'] ?? 'all';
$month = date('Y-m');

if (!preg_match('/^cn(\d+)$/', $branch, $matches)) {
  // Tổng thuế toàn hệ thống
  $sql = "SELECT SUM(GIA_TRI) AS total_tax FROM chi_phi_phat_sinh WHERE TEN_CP = 'Thuế VAT' AND DATE_FORMAT(NGAY_GIO, '%Y-%m') = '$month'";
} else {
  $branchId = (int)$matches[1];
  $sql = "SELECT SUM(GIA_TRI) AS total_tax FROM chi_phi_phat_sinh WHERE TEN_CP = 'Thuế VAT' AND ID_CN = $branchId AND DATE_FORMAT(NGAY_GIO, '%Y-%m') = '$month'";
}

$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
echo json_encode(['tax' => (float)($row['total_tax'] ?? 0)]);
