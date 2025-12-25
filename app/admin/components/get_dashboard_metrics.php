<?php
// File: app/admin/components/get_dashboard_metrics.php
// API để lấy KPI cho Tab 0 (Dashboard Nhanh)

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

include '../../../database/config.php';

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);
    exit;
}

function fetchScalar(string $sql, float $default = 0): float
{
    global $conn;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        error_log('[dashboard_metrics] SQL error: ' . mysqli_error($conn));
        return $default;
    }
    $row = mysqli_fetch_row($result);
    mysqli_free_result($result);
    return ($row && $row[0] !== null) ? (float)$row[0] : $default;
}

function fetchRows(string $sql): array
{
    global $conn;
    $rows = [];
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        error_log('[dashboard_metrics] SQL rows error: ' . mysqli_error($conn));
        return $rows;
    }
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_free_result($result);
    return $rows;
}

function calcPercentChange(float $current, float $previous): ?float
{
    if ($previous <= 0) {
        return null;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

try {
    // KPI: Revenue tháng này vs tháng trước
    $currentMonthRevenue = fetchScalar(
        "SELECT COALESCE(SUM(TONG_TIEN), 0) FROM hoa_don 
        WHERE TRANGTHAI_THANHTOAN = 'Đã thanh toán' 
        AND YEAR(NGAY_GIO) = YEAR(CURDATE()) 
        AND MONTH(NGAY_GIO) = MONTH(CURDATE())"
    );
    
    $previousMonthRevenue = fetchScalar(
        "SELECT COALESCE(SUM(TONG_TIEN), 0) FROM hoa_don 
        WHERE TRANGTHAI_THANHTOAN = 'Đã thanh toán' 
        AND YEAR(NGAY_GIO) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
        AND MONTH(NGAY_GIO) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))"
    );
    
    $revenueGrowth = calcPercentChange($currentMonthRevenue, $previousMonthRevenue) ?? 0;

    // KPI: Bookings tuần này vs tuần trước
    $bookingsThisWeek = fetchScalar(
        "SELECT COUNT(*) FROM lich_hen 
        WHERE YEARWEEK(THOI_GIAN_BAT_DAU, 1) = YEARWEEK(CURDATE(), 1)"
    );
    
    $bookingsLastWeek = fetchScalar(
        "SELECT COUNT(*) FROM lich_hen 
        WHERE YEARWEEK(THOI_GIAN_BAT_DAU, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)"
    );
    
    $bookingGrowth = calcPercentChange($bookingsThisWeek, $bookingsLastWeek) ?? 0;

    // KPI: Active counts
    $activeCustomers = (int) fetchScalar('SELECT COUNT(*) FROM khach_hang');
    $activeEmployees = (int) fetchScalar('SELECT COUNT(*) FROM nhan_vien');
    $uniqueCustomersMonth = (int) fetchScalar(
        'SELECT COUNT(DISTINCT ID_TK) FROM lich_hen 
        WHERE YEAR(THOI_GIAN_BAT_DAU) = YEAR(CURDATE()) 
        AND MONTH(THOI_GIAN_BAT_DAU) = MONTH(CURDATE())'
    );

    // KPI: Payment & Satisfaction
    $totalInvoices = (int) fetchScalar('SELECT COUNT(*) FROM hoa_don');
    $paidInvoices = (int) fetchScalar("SELECT COUNT(*) FROM hoa_don WHERE TRANGTHAI_THANHTOAN = 'Đã thanh toán'");
    $collectionRate = $totalInvoices > 0 ? round(($paidInvoices / $totalInvoices) * 100, 1) : 0;
    
    $avgRating = fetchScalar('SELECT COALESCE(AVG(XEP_HANG_DV), 0) FROM phan_hoi_cua_khach_hang');
    $satisfactionRate = round(($avgRating / 5) * 100, 1);

    // KPI: Appointments
    $appointmentsToday = (int) fetchScalar("SELECT COUNT(*) FROM lich_hen WHERE DATE(THOI_GIAN_BAT_DAU) = CURDATE()");
    $appointmentsNeedConfirm = (int) fetchScalar(
        "SELECT COUNT(*) FROM lich_hen 
        WHERE TRANGTHAI = 'Đang chờ' 
        AND THOI_GIAN_BAT_DAU BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)"
    );

    // KPI: Invoices
    $pendingInvoices = (int) fetchScalar(
        "SELECT COUNT(DISTINCT hd.ID_HD) FROM hoa_don hd 
        JOIN thanh_toan_truc_tuyen tt ON tt.ID_HD = hd.ID_HD 
        WHERE hd.TRANGTHAI_THANHTOAN <> 'Đã thanh toán' AND tt.TRANG_THAI = 'pending'"
    );
    
    $overdueInvoices = (int) fetchScalar(
        "SELECT COUNT(*) FROM hoa_don hd 
        JOIN lich_hen lh ON lh.ID_LICHHEN = hd.ID_LICHHEN 
        WHERE hd.TRANGTHAI_THANHTOAN <> 'Đã thanh toán' AND lh.THOI_GIAN_BAT_DAU < NOW()"
    );

    // Timeline 6 tháng gần nhất (Revenue vs Expense vs Profit)
    $timeline = [
        'labels' => [],
        'revenue' => [],
        'expense' => [],
        'profit' => []
    ];
    
    for ($i = 5; $i >= 0; $i--) {
        $dateKey = date('Y-m', strtotime("-$i month"));
        $monthNum = (int)substr($dateKey, 5, 2);
        $year = (int)substr($dateKey, 0, 4);
        
        $label = 'T' . $monthNum;
        $timeline['labels'][] = $label;
        
        // Revenue
        $revenue = fetchScalar(
            "SELECT COALESCE(SUM(TONG_TIEN), 0) FROM hoa_don 
            WHERE TRANGTHAI_THANHTOAN = 'Đã thanh toán' 
            AND YEAR(NGAY_GIO) = $year AND MONTH(NGAY_GIO) = $monthNum"
        );
        $timeline['revenue'][] = round($revenue / 1000000, 1); // Tính theo triệu VND
        
        // Expense (Chi phí phát sinh + Lương + Thuế từ tai_chinh)
        $expense = fetchScalar(
            "SELECT COALESCE(SUM(SO_TIEN), 0) FROM tai_chinh 
            WHERE LOAI_GIAO_DICH = 'chi phí' 
            AND YEAR(NGAY_GIAO_DICH) = $year AND MONTH(NGAY_GIAO_DICH) = $monthNum"
        );
        
        $salary = fetchScalar(
            "SELECT COALESCE(SUM(SO_TIEN), 0) FROM tai_chinh 
            WHERE LOAI_CHI_TIET = 'Lương nhân viên' 
            AND YEAR(NGAY_GIAO_DICH) = $year AND MONTH(NGAY_GIAO_DICH) = $monthNum"
        );
        
        $totalExpense = $expense + $salary;
        $timeline['expense'][] = round($totalExpense / 1000000, 1);
        
        // Profit
        $profit = $revenue - $totalExpense;
        $timeline['profit'][] = round($profit / 1000000, 1);
    }

    // Top 5 Customers by Revenue
    $topCustomers = fetchRows(
        "SELECT kh.TEN_KH, COALESCE(SUM(hd.TONG_TIEN), 0) as total_revenue, COUNT(DISTINCT hd.ID_HD) as booking_count
        FROM khach_hang kh
        LEFT JOIN lich_hen lh ON lh.ID_TK = kh.ID_TK
        LEFT JOIN hoa_don hd ON hd.ID_LICHHEN = lh.ID_LICHHEN AND hd.TRANGTHAI_THANHTOAN = 'Đã thanh toán'
        GROUP BY kh.ID_TK
        ORDER BY total_revenue DESC
        LIMIT 5"
    );

    // System Logs (10 gần nhất)
    $systemLogs = fetchRows(
        "SELECT HANH_DONG, TAIKHOAN, THOI_GIAN
        FROM nhat_ky_he_thong
        ORDER BY THOI_GIAN DESC
        LIMIT 10"
    );

    // Prepare response
    $response = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'kpi' => [
            'currentMonthRevenue' => (int)$currentMonthRevenue,
            'revenueGrowth' => $revenueGrowth,
            'bookingsThisWeek' => (int)$bookingsThisWeek,
            'bookingGrowth' => $bookingGrowth,
            'activeCustomers' => $activeCustomers,
            'uniqueCustomersMonth' => $uniqueCustomersMonth,
            'activeEmployees' => $activeEmployees,
            'collectionRate' => $collectionRate,
            'satisfactionRate' => $satisfactionRate,
            'appointmentsToday' => $appointmentsToday,
            'appointmentsNeedConfirm' => $appointmentsNeedConfirm,
            'pendingInvoices' => $pendingInvoices,
            'overdueInvoices' => $overdueInvoices
        ],
        'timeline' => $timeline,
        'topCustomers' => $topCustomers,
        'systemLogs' => $systemLogs
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
} catch (Throwable $t) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $t->getMessage()
    ]);
    exit;
}
?>
