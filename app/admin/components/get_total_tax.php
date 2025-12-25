<?php
include '../../../database/config.php';

$branch = $_GET['branch'] ?? 'all';
$month = date('Y-m');

if (!preg_match('/^cn(\d+)$/', $branch, $matches)) {
  // Tổng thuế toàn hệ thống (VAT + DN)
  $sql = "SELECT COALESCE(SUM(SO_TIEN), 0) AS total_tax FROM thue_chi_tra WHERE THANG = '$month'";
} else {
  $branchId = (int)$matches[1];
  // Tổng thuế của chi nhánh cụ thể (VAT + DN)
  $sql = "SELECT COALESCE(SUM(SO_TIEN), 0) AS total_tax FROM thue_chi_tra WHERE ID_CN = $branchId AND THANG = '$month'";
}

$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
echo json_encode(['tax' => (float)($row['total_tax'] ?? 0)]);
?>