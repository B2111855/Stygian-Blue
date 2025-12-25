<?php
$_GET['package_id'] = 1;
ob_start();
include 'get_package_detail.php';
$res = ob_get_clean();
$data = json_decode($res, true);

if ($data && isset($data['package']['duration_min'])) {
    $duration = $data['package']['duration_min'];
    echo "✓ API ĐÃ CÓ FIELD duration_min" . PHP_EOL;
    echo "Duration: {$duration} phút = " . floor($duration/60) . " giờ " . ($duration % 60) . " phút" . PHP_EOL;
} else {
    echo "✗ API vẫn chưa có duration_min" . PHP_EOL;
}

echo PHP_EOL . "Full response:" . PHP_EOL;
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
