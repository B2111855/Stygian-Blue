<?php
require_once __DIR__ . '/../../../database/config.php';
require_once __DIR__ . '/report_helpers.php';

$branchParam = $_GET['branch'] ?? null;
$filterParam = $_GET['filter'] ?? 'month';

[$filter, $start, $end] = report_resolve_relative_range($filterParam);
$branchId = report_parse_branch($branchParam);

$sql = "SELECT dv.TEN_DV, COALESCE(SUM(hd.TONG_TIEN), 0) AS revenue
        FROM hoa_don hd
        JOIN lich_hen lh ON lh.ID_LICHHEN = hd.ID_LICHHEN
        JOIN dich_vu dv ON dv.ID_DV = lh.ID_DV
        WHERE hd.NGAY_GIO >= ? AND hd.NGAY_GIO <= ?
          AND hd.TRANGTHAI_THANHTOAN = 'Đã thanh toán'";
$params = [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
$types = 'ss';

if ($branchId) {
    $sql .= ' AND lh.ID_CHINHANH = ?';
    $params[] = $branchId;
    $types .= 'i';
}

$sql .= ' GROUP BY dv.ID_DV, dv.TEN_DV ORDER BY revenue DESC LIMIT 6';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    report_json(['error' => 'Không thể tải doanh thu dịch vụ.'], 500);
}
report_stmt_bind_params($stmt, $types, $params);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
$totalRevenue = 0;
while ($row = $result->fetch_assoc()) {
    $numeric = (float)$row['revenue'];
    $totalRevenue += $numeric;
    $items[] = [
        'label' => $row['TEN_DV'],
        'numeric' => $numeric,
        'revenue' => report_format_currency($numeric),
    ];
}
$stmt->close();

report_json([
    'filter' => $filter,
    'from' => $start->format(DateTime::ISO8601),
    'to' => $end->format(DateTime::ISO8601),
    'items' => $items,
    'total' => report_format_currency($totalRevenue),
]);
