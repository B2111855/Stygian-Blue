<?php
require_once __DIR__ . '/../../../database/config.php';
require_once __DIR__ . '/report_helpers.php';

$branchParam = $_GET['branch'] ?? null;
$filterParam = $_GET['filter'] ?? 'month';

[$filter, $start, $end] = report_resolve_relative_range($filterParam);
$branchId = report_parse_branch($branchParam);

$groupSelect = 'DATE_FORMAT(ph.NGAY_GUI, "%m/%Y")';
$labelPrefix = 'Tháng ';

if ($filter === 'week') {
    $groupSelect = 'CONCAT("Tuần ", LPAD(WEEK(ph.NGAY_GUI, 1), 2, "0"), "/", YEAR(ph.NGAY_GUI))';
    $labelPrefix = '';
} elseif ($filter === 'quarter') {
    $groupSelect = 'CONCAT("Q", QUARTER(ph.NGAY_GUI), "/", YEAR(ph.NGAY_GUI))';
    $labelPrefix = '';
}

$sql = "SELECT $groupSelect AS label, AVG(ph.XEP_HANG_DV) AS avg_score, COUNT(*) AS total
        FROM phan_hoi_cua_khach_hang ph
        JOIN lich_hen lh ON lh.ID_TK = ph.ID_TK AND lh.ID_DV = ph.ID_DV
        WHERE ph.NGAY_GUI >= ? AND ph.NGAY_GUI <= ?";
$params = [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
$types = 'ss';

if ($branchId) {
    $sql .= ' AND lh.ID_CHINHANH = ?';
    $params[] = $branchId;
    $types .= 'i';
}

$sql .= ' GROUP BY label ORDER BY MIN(ph.NGAY_GUI) ASC';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    report_json(['error' => 'Không thể tải đánh giá khách hàng.'], 500);
}
report_stmt_bind_params($stmt, $types, $params);
$stmt->execute();
$result = $stmt->get_result();

$points = [];
$totalScore = 0;
$totalFeedback = 0;
while ($row = $result->fetch_assoc()) {
    $avgScore = (float)$row['avg_score'];
    $points[] = [
        'label' => $labelPrefix . $row['label'],
        'score' => round($avgScore, 2),
    ];
    $totalScore += $avgScore * (int)$row['total'];
    $totalFeedback += (int)$row['total'];
}
$stmt->close();

$overall = $totalFeedback > 0 ? round($totalScore / $totalFeedback, 2) : null;
$alerts = [];

if ($totalFeedback === 0) {
    $alerts[] = [
        'level' => 'amber',
        'title' => 'Thiếu dữ liệu đánh giá',
        'message' => 'Chưa có phản hồi trong giai đoạn này, hãy khuyến khích khách hàng để lại đánh giá.',
    ];
} else {
    if ($overall !== null && $overall < 3.5) {
        $alerts[] = [
            'level' => 'rose',
            'title' => 'Điểm hài lòng thấp',
            'message' => 'Điểm trung bình chỉ đạt ' . $overall . '/5. Hãy rà soát lại chất lượng dịch vụ.',
        ];
    }
    if ($totalFeedback < 3) {
        $alerts[] = [
            'level' => 'amber',
            'title' => 'Số lượng phản hồi hạn chế',
            'message' => 'Chỉ có ' . $totalFeedback . ' đánh giá, khó phản ánh chính xác cảm nhận khách hàng.',
        ];
    }
    if (empty($alerts)) {
        $alerts[] = [
            'level' => 'emerald',
            'title' => 'Khách hàng hài lòng',
            'message' => 'Điểm trung bình đạt ' . $overall . '/5 với ' . $totalFeedback . ' phản hồi.',
        ];
    }
}

report_json([
    'filter' => $filter,
    'overallScore' => $overall !== null ? $overall . '/5' : '--',
    'totalFeedback' => $totalFeedback,
    'points' => $points,
    'alerts' => $alerts,
]);
