<?php
require_once __DIR__ . '/../../../database/config.php';
require_once __DIR__ . '/report_helpers.php';

$filterParam = $_GET['filter'] ?? 'year';
$branchParam = $_GET['branch'] ?? 'all';

[$filter, $start, $end] = report_resolve_relative_range($filterParam);
$branchId = report_parse_branch($branchParam);

$sql = "
    SELECT HOUR(lh.THOI_GIAN_BAT_DAU) AS hour_slot, COUNT(*) AS total
    FROM lich_hen lh
    WHERE lh.THOI_GIAN_BAT_DAU BETWEEN ? AND ?";

$types = 'ss';
$params = [
    $start->format('Y-m-d H:i:s'),
    $end->format('Y-m-d H:i:s')
];

if ($branchId) {
    $sql .= ' AND lh.ID_CHINHANH = ?';
    $types .= 'i';
    $params[] = $branchId;
}

$sql .= ' GROUP BY hour_slot ORDER BY hour_slot';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    report_json(['success' => false, 'message' => 'Không thể tải dữ liệu khung giờ đặt lịch.'], 500);
}

report_stmt_bind_params($stmt, $types, $params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $label = $row['hour_slot'] . 'h';
    $data[] = [
        'label' => $label,
        'value' => (int)$row['total']
    ];
}

$stmt->close();

header('Content-Type: application/json');
echo json_encode($data);
?>
