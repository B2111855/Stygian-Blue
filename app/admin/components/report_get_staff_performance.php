<?php
require_once __DIR__ . '/../../../database/config.php';
require_once __DIR__ . '/report_helpers.php';

$branchParam = $_GET['branch'] ?? null;
$metricParam = $_GET['metric'] ?? 'assignments';
$filterParam = $_GET['filter'] ?? 'month';

$metric = in_array($metricParam, ['hours', 'assignments'], true) ? $metricParam : 'assignments';
[$filter, $start, $end] = report_resolve_relative_range($filterParam);
$branchId = report_parse_branch($branchParam);

$selectValue = $metric === 'hours'
    ? "SUM(GREATEST(0, TIMESTAMPDIFF(MINUTE, pcnv.THOI_GIAN_BAT_DAU, COALESCE(pcnv.THOI_GIAN_KET_THUC, pcnv.THOI_GIAN_BAT_DAU)))) AS metric_value"
    : 'COUNT(*) AS metric_value';

$sql = "SELECT COALESCE(nv.HO_TEN, pcnv.ID_TK) AS staff_name, $selectValue
        FROM phan_cong_nhan_vien pcnv
        JOIN lich_hen lh ON lh.ID_LICHHEN = pcnv.ID_LICHHEN
        LEFT JOIN nhan_vien nv ON nv.ID_TK = pcnv.ID_TK
        WHERE pcnv.THOI_GIAN_BAT_DAU >= ? AND pcnv.THOI_GIAN_BAT_DAU <= ?";
$params = [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
$types = 'ss';

if ($branchId) {
    $sql .= ' AND lh.ID_CHINHANH = ?';
    $params[] = $branchId;
    $types .= 'i';
}

$sql .= ' GROUP BY staff_name ORDER BY metric_value DESC LIMIT 8';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    report_json(['error' => 'Không thể tải hiệu suất nhân sự.'], 500);
}
report_stmt_bind_params($stmt, $types, $params);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $value = (float)$row['metric_value'];
    if ($metric === 'hours') {
        $value = round($value / 60, 2); // đổi sang giờ
    }
    $items[] = [
        'name' => $row['staff_name'],
        'value' => $value,
    ];
}
$stmt->close();

$label = $metric === 'hours' ? 'Giờ công' : 'Số nhiệm vụ';
report_json([
    'filter' => $filter,
    'metric' => $metric,
    'label' => $label,
    'items' => $items,
]);
