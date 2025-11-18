<?php
require_once __DIR__ . '/../../../database/config.php';
require_once __DIR__ . '/report_helpers.php';

$branchParam = $_GET['branch'] ?? null;
$filterParam = $_GET['filter'] ?? 'month';

[$filter, $start, $end] = report_resolve_relative_range($filterParam);
$branchId = report_parse_branch($branchParam);

$startStr = $start->format('Y-m-d H:i:s');
$endStr = $end->format('Y-m-d H:i:s');

$financialSql = "SELECT
        SUM(CASE WHEN LOAI_GIAO_DICH = 'doanh thu' THEN SO_TIEN ELSE 0 END) AS revenue,
        SUM(CASE WHEN LOAI_GIAO_DICH = 'chi phí' THEN SO_TIEN ELSE 0 END) AS expense
    FROM tai_chinh
    WHERE NGAY_GIAO_DICH >= ? AND NGAY_GIAO_DICH <= ?";
$params = [$startStr, $endStr];
$types = 'ss';
if ($branchId) {
    $financialSql .= ' AND ID_CN = ?';
    $params[] = $branchId;
    $types .= 'i';
}
$stmt = $conn->prepare($financialSql);
if (!$stmt) {
    report_json(['error' => 'Không thể tải dữ liệu tài chính.'], 500);
}
report_stmt_bind_params($stmt, $types, $params);
$stmt->execute();
$financialRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$revenue = (float)($financialRow['revenue'] ?? 0);
$expense = (float)($financialRow['expense'] ?? 0);
$profit = $revenue - $expense;

// Schedule stats
$scheduleSql = "SELECT COUNT(*) AS total, COUNT(DISTINCT DATE(THOI_GIAN_BAT_DAU)) AS busy_days,
                       SUM(CASE WHEN TRANGTHAI = 'Đã hủy' THEN 1 ELSE 0 END) AS cancelled
                FROM lich_hen
                WHERE THOI_GIAN_BAT_DAU >= ? AND THOI_GIAN_BAT_DAU <= ?";
$sParams = [$startStr, $endStr];
$sTypes = 'ss';
if ($branchId) {
    $scheduleSql .= ' AND ID_CHINHANH = ?';
    $sParams[] = $branchId;
    $sTypes .= 'i';
}
$stmt = $conn->prepare($scheduleSql);
if (!$stmt) {
    report_json(['error' => 'Không thể tải dữ liệu lịch hẹn.'], 500);
}
report_stmt_bind_params($stmt, $sTypes, $sParams);
$stmt->execute();
$scheduleRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalAppointments = (int)($scheduleRow['total'] ?? 0);
$busyDays = (int)($scheduleRow['busy_days'] ?? 0);
$cancelled = (int)($scheduleRow['cancelled'] ?? 0);

$totalDays = max(1, (int)$start->diff($end)->format('%a'));
$coverage = $totalDays > 0 ? round(($busyDays / $totalDays) * 100) : 0;
$cancelRate = $totalAppointments > 0 ? round(($cancelled / $totalAppointments) * 100) : 0;

$alerts = [];

if ($profit < 0) {
    $alerts[] = [
        'level' => 'rose',
        'category' => 'Tài chính',
        'title' => 'Lợi nhuận âm',
        'description' => 'Chi phí vượt doanh thu ' . report_format_currency(abs($profit)) . '.',
        'action' => 'Kiểm soát chi phí cố định và đàm phán lại với nhà cung cấp.',
    ];
} elseif ($profit < $revenue * 0.1) {
    $alerts[] = [
        'level' => 'amber',
        'category' => 'Tài chính',
        'title' => 'Biên lợi nhuận thấp',
        'description' => 'Biên lợi nhuận < 10%, hiện đạt ' . ($revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0) . '%.',
        'action' => 'Xem xét tăng giá bán hoặc tối ưu chi phí marketing.',
    ];
} else {
    $alerts[] = [
        'level' => 'emerald',
        'category' => 'Tài chính',
        'title' => 'Dòng tiền khỏe mạnh',
        'description' => 'Lợi nhuận đạt ' . report_format_currency($profit) . '.',
        'action' => 'Tiếp tục duy trì kế hoạch chi tiêu hiện tại.',
    ];
}

if ($coverage < 50) {
    $alerts[] = [
        'level' => 'amber',
        'category' => 'Lịch hẹn',
        'title' => 'Độ phủ lịch thấp',
        'description' => 'Chỉ ' . $coverage . '% ngày trong kỳ có lịch hẹn.',
        'action' => 'Đẩy mạnh các chiến dịch quảng cáo cuối tuần.',
    ];
} else {
    $alerts[] = [
        'level' => 'emerald',
        'category' => 'Lịch hẹn',
        'title' => 'Lịch hẹn ổn định',
        'description' => $coverage . '% ngày trong kỳ có khách đặt lịch.',
        'action' => 'Ưu tiên giữ chất lượng dịch vụ và upsell.',
    ];
}

if ($cancelRate >= 20) {
    $alerts[] = [
        'level' => 'rose',
        'category' => 'Vận hành',
        'title' => 'Tỷ lệ hủy cao',
        'description' => 'Có ' . $cancelRate . '% lịch bị hủy.',
        'action' => 'Liên hệ khách để xác nhận và áp dụng đặt cọc bắt buộc.',
    ];
} elseif ($cancelRate >= 10) {
    $alerts[] = [
        'level' => 'amber',
        'category' => 'Vận hành',
        'title' => 'Cần theo dõi hủy lịch',
        'description' => 'Có ' . $cancelRate . '% lịch bị hủy.',
        'action' => 'Nhắc nhân viên xác nhận lịch trước 24h.',
    ];
}

report_json([
    'filter' => $filter,
    'alerts' => $alerts,
]);
