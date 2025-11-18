<?php
require_once __DIR__ . '/../../../database/config.php';
require_once __DIR__ . '/report_helpers.php';

$branchParam = $_GET['branch'] ?? null;
$modeParam = $_GET['mode'] ?? 'month';
$monthParam = $_GET['month'] ?? date('Y-m');

[$mode, $rangeStart, $rangeEnd] = report_resolve_range($modeParam, $monthParam);
$branchId = report_parse_branch($branchParam);

$startStr = $rangeStart->format('Y-m-d H:i:s');
$endStr = $rangeEnd->format('Y-m-d H:i:s');

$heatmapSql = "SELECT DATE(THOI_GIAN_BAT_DAU) AS booking_day, COUNT(*) AS total
               FROM lich_hen
               WHERE THOI_GIAN_BAT_DAU >= ? AND THOI_GIAN_BAT_DAU < ?";
$params = [$startStr, $endStr];
$types = 'ss';

if ($branchId) {
    $heatmapSql .= ' AND ID_CHINHANH = ?';
    $params[] = $branchId;
    $types .= 'i';
}

$heatmapSql .= ' GROUP BY booking_day ORDER BY booking_day ASC';
$stmt = $conn->prepare($heatmapSql);
if (!$stmt) {
    report_json(['error' => 'Không thể chuẩn bị truy vấn heatmap.'], 500);
}
report_stmt_bind_params($stmt, $types, $params);
$stmt->execute();
$result = $stmt->get_result();
$countMap = [];
while ($row = $result->fetch_assoc()) {
    $countMap[$row['booking_day']] = (int)$row['total'];
}
$stmt->close();

$period = new DatePeriod(clone $rangeStart, new DateInterval('P1D'), clone $rangeEnd);
$totalDays = 0;
$busyDays = 0;
$cells = [];
foreach ($period as $day) {
    $totalDays++;
    $key = $day->format('Y-m-d');
    $count = $countMap[$key] ?? 0;
    if ($count > 0) {
        $busyDays++;
    }

    $intensity = 'bg-gray-50 border-gray-100 text-gray-500';
    if ($count >= 8) {
        $intensity = 'bg-emerald-300 border-emerald-400 text-emerald-900';
    } elseif ($count >= 5) {
        $intensity = 'bg-emerald-200 border-emerald-300 text-emerald-900';
    } elseif ($count >= 2) {
        $intensity = 'bg-emerald-100 border-emerald-200 text-emerald-800';
    } elseif ($count === 1) {
        $intensity = 'bg-emerald-50 border-emerald-100 text-emerald-700';
    }

    $cells[] = [
        'label' => $day->format('d/m'),
        'count' => $count,
        'intensity' => $intensity,
    ];
}

$coverageRate = $totalDays > 0 ? round(($busyDays / $totalDays) * 100) : 0;

// Average assignment duration based on staff schedule
$durationSql = "SELECT AVG(
                    GREATEST(0, TIMESTAMPDIFF(MINUTE, pcnv.THOI_GIAN_BAT_DAU, COALESCE(pcnv.THOI_GIAN_KET_THUC, pcnv.THOI_GIAN_BAT_DAU)))
                 ) AS avg_minutes
                 FROM phan_cong_nhan_vien pcnv
                 JOIN lich_hen lh ON lh.ID_LICHHEN = pcnv.ID_LICHHEN
                 WHERE lh.THOI_GIAN_BAT_DAU >= ? AND lh.THOI_GIAN_BAT_DAU < ?";
$durationParams = [$startStr, $endStr];
$durationTypes = 'ss';
if ($branchId) {
    $durationSql .= ' AND lh.ID_CHINHANH = ?';
    $durationParams[] = $branchId;
    $durationTypes .= 'i';
}
$stmt = $conn->prepare($durationSql);
if (!$stmt) {
    report_json(['error' => 'Không thể chuẩn bị truy vấn thời lượng.'], 500);
}
report_stmt_bind_params($stmt, $durationTypes, $durationParams);
$stmt->execute();
$durationResult = $stmt->get_result()->fetch_assoc();
$avgMinutes = $durationResult ? (float)$durationResult['avg_minutes'] : null;
$stmt->close();

// Return rate (repeat customers)
$returnSql = "SELECT COUNT(*) AS total_customers,
                     SUM(CASE WHEN booking_count > 1 THEN 1 ELSE 0 END) AS repeat_customers
              FROM (
                  SELECT ID_TK, COUNT(*) AS booking_count
                  FROM lich_hen
                  WHERE THOI_GIAN_BAT_DAU >= ? AND THOI_GIAN_BAT_DAU < ?";
$returnParams = [$startStr, $endStr];
$returnTypes = 'ss';
if ($branchId) {
    $returnSql .= ' AND ID_CHINHANH = ?';
    $returnParams[] = $branchId;
    $returnTypes .= 'i';
}
$returnSql .= ' GROUP BY ID_TK
              ) AS tmp';
$stmt = $conn->prepare($returnSql);
if (!$stmt) {
    report_json(['error' => 'Không thể chuẩn bị truy vấn tái đặt.'], 500);
}
report_stmt_bind_params($stmt, $returnTypes, $returnParams);
$stmt->execute();
$returnRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalCustomers = $returnRow['total_customers'] ?? 0;
$repeatCustomers = $returnRow['repeat_customers'] ?? 0;
$returnRate = $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100) : 0;

report_json([
    'mode' => $mode,
    'cells' => $cells,
    'coverage' => $coverageRate . '% ngày có lịch',
    'avgDuration' => report_format_duration($avgMinutes),
    'returnRate' => $returnRate . '% khách quay lại',
]);
