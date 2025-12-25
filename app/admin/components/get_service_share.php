<?php
require_once __DIR__ . '/../../../database/config.php';
require_once __DIR__ . '/report_helpers.php';

$filterParam = $_GET['filter'] ?? 'year';
$branchParam = $_GET['branch'] ?? 'all';

[$filter, $start, $end] = report_resolve_relative_range($filterParam);
$branchId = report_parse_branch($branchParam);

$sql = "
    SELECT dv.TEN_DV AS label, COUNT(*) AS total
    FROM BOOKING_ITEM bi
    JOIN dich_vu dv ON dv.ID_DV = bi.REF_ID
    JOIN lich_hen lh ON lh.ID_LICHHEN = bi.ID_LICHHEN
    WHERE bi.ITEM_TYPE = 'service'
      AND lh.THOI_GIAN_BAT_DAU BETWEEN ? AND ?";

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

$sql .= ' GROUP BY dv.ID_DV ORDER BY total DESC';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    report_json(['success' => false, 'message' => 'Không thể tải dữ liệu tỉ lệ dịch vụ.'], 500);
}

report_stmt_bind_params($stmt, $types, $params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'label' => $row['label'],
        'value' => (int)$row['total']
    ];
}

$stmt->close();

header('Content-Type: application/json');
echo json_encode($data);
?>
