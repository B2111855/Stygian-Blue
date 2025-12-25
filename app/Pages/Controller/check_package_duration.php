<?php
require '../../../database/config.php';

echo "=== KIỂM TRA THỜI LƯỢNG GÓI DỊCH VỤ ===" . PHP_EOL . PHP_EOL;

// Lấy thông tin gói
$stmt = $conn->prepare("SELECT ID_GOI, TEN_GOI FROM goi_dich_vu WHERE ID_GOI = 1");
$stmt->execute();
$res = $stmt->get_result();
$package = $res->fetch_assoc();
echo "Gói: {$package['TEN_GOI']}" . PHP_EOL . PHP_EOL;

// Lấy các dịch vụ trong gói
echo "Các dịch vụ trong gói:" . PHP_EOL;
$stmt = $conn->prepare("
    SELECT d.ID_DV, d.TEN_DV, d.THOI_GIAN 
    FROM goi_dich_vu_chi_tiet gdt 
    JOIN dich_vu d ON d.ID_DV = gdt.ID_DV 
    WHERE gdt.ID_GOI = 1
");
$stmt->execute();
$res = $stmt->get_result();
$totalMinutes = 0;
while($row = $res->fetch_assoc()) {
    echo "  - {$row['TEN_DV']}: {$row['THOI_GIAN']} phút" . PHP_EOL;
    $totalMinutes += $row['THOI_GIAN'];
}

echo PHP_EOL;
echo "TỔNG THỜI LƯỢNG: {$totalMinutes} phút = " . floor($totalMinutes/60) . " giờ " . ($totalMinutes%60) . " phút" . PHP_EOL;
echo PHP_EOL;

// Kiểm tra API get_package_detail.php có trả về duration không
echo "Kiểm tra API get_package_detail.php:" . PHP_EOL;
$_GET['package_id'] = 1;
ob_start();
include 'get_package_detail.php';
$apiResponse = ob_get_clean();
$data = json_decode($apiResponse, true);

if (isset($data['duration_min']) || isset($data['THOI_GIAN'])) {
    echo "  ✓ API có trả duration: " . json_encode($data['duration_min'] ?? $data['THOI_GIAN'] ?? 'N/A') . PHP_EOL;
} else {
    echo "  ✗ API KHÔNG trả duration field" . PHP_EOL;
    echo "  Response keys: " . implode(', ', array_keys($data)) . PHP_EOL;
}
