<?php
// File: get_financial_data.php
// 
// GHI CHÚ: Chi phí phát sinh đang đóng vai trò chi phí vận hành (điện, nước, mặt bằng...)
// ==========================================================================
// EXPENSE (Chi phí) trong biểu đồ = Chi phí phát sinh (LOAI_CHI_TIET='Chi phí phát sinh').
//   - Dữ liệu nguồn: tai_chinh (LOAI_GIAO_DICH='chi phí' AND LOAI_CHI_TIET='Chi phí phát sinh')
//   - Bao gồm: điện, nước, bảo trì, mặt bằng... đã được đồng bộ vào tai_chinh.
//
// SALARY_EXPENSE: LOAI_CHI_TIET='Lương nhân viên' (tính riêng)
// TAX_EXPENSE:    LOAI_CHI_TIET='Thuế VAT' (VAT); Thuế DN sẽ tính nơi khác nếu cần
// INCIDENTAL_EXPENSE: Tổng hợp từ chi_phi_phat_sinh (không bao gồm thuế) để hiển thị thêm
//
// LỢI NHUẬN = Doanh thu thực tế - (Chi phí phát sinh + Lương + Thuế)
//             = revenue_actual - (expense_incidental + salary_expense + tax)

header('Content-Type: application/json');

include __DIR__ . '/../../../database/config.php';
require_once __DIR__ . '/../../helpers/format_helper.php';

function getRealFinancialData($filter = 'year', $branch = 'all') {
    global $conn;

    $branchCondition = "";
    if ($branch !== 'all') {
        $branchID = intval(substr($branch, 2)); // cn1 => 1
        $branchCondition = "AND cn.ID_CN = $branchID";
    }

    // Initialize data array - expense sẽ chính là chi phí phát sinh
    $data = [
        'labels' => [], 
        'revenue' => [], 
        'revenue_predicted' => [], 
        'revenue_actual' => [], 
        'expense' => [],                  // Chi phí phát sinh (vận hành)
        'expense_operational' => [],      // Alias (giữ tương thích)
        'salary_expense' => []            // Chi phí lương
    ];

    if ($filter === 'month') {
        $query = "
            SELECT 
                MONTH(NGAY_GIAO_DICH) AS label,
                SUM(CASE WHEN LOAI_GIAO_DICH = 'doanh thu' AND TRANG_THAI = 'đã thanh toán' THEN SO_TIEN ELSE 0 END) AS revenue,
                SUM(CASE WHEN LOAI_GIAO_DICH = 'doanh thu' AND TRANG_THAI = 'chờ thanh toán' THEN SO_TIEN ELSE 0 END) AS revenue_predicted,
                SUM(CASE WHEN LOAI_GIAO_DICH = 'doanh thu' AND TRANG_THAI = 'đã thanh toán' THEN SO_TIEN ELSE 0 END) AS revenue_actual,
                SUM(CASE WHEN LOAI_GIAO_DICH = 'chi phí' AND LOAI_CHI_TIET = 'Chi phí phát sinh' THEN SO_TIEN ELSE 0 END) AS expense_operational,
                SUM(CASE WHEN LOAI_CHI_TIET = 'Lương nhân viên' THEN SO_TIEN ELSE 0 END) AS salary_expense
            FROM tai_chinh tc
            JOIN chi_nhanh cn ON tc.ID_CN = cn.ID_CN
            WHERE YEAR(NGAY_GIAO_DICH) = YEAR(CURDATE()) $branchCondition
            GROUP BY MONTH(NGAY_GIAO_DICH)
            ORDER BY MONTH(NGAY_GIAO_DICH)
        ";
    } elseif ($filter === 'quarter') {
        $query = "
            SELECT 
                QUARTER(NGAY_GIAO_DICH) AS label,
                SUM(CASE WHEN LOAI_GIAO_DICH = 'doanh thu' AND TRANG_THAI = 'đã thanh toán' THEN SO_TIEN ELSE 0 END) AS revenue,
                SUM(CASE WHEN LOAI_GIAO_DICH = 'doanh thu' AND TRANG_THAI = 'chờ thanh toán' THEN SO_TIEN ELSE 0 END) AS revenue_predicted,
                SUM(CASE WHEN LOAI_GIAO_DICH = 'doanh thu' AND TRANG_THAI = 'đã thanh toán' THEN SO_TIEN ELSE 0 END) AS revenue_actual,
                SUM(CASE WHEN LOAI_GIAO_DICH = 'chi phí' AND LOAI_CHI_TIET = 'Chi phí phát sinh' THEN SO_TIEN ELSE 0 END) AS expense_operational,
                SUM(CASE WHEN LOAI_CHI_TIET = 'Lương nhân viên' THEN SO_TIEN ELSE 0 END) AS salary_expense
            FROM tai_chinh tc
            JOIN chi_nhanh cn ON tc.ID_CN = cn.ID_CN
            WHERE YEAR(NGAY_GIAO_DICH) = YEAR(CURDATE()) $branchCondition
            GROUP BY QUARTER(NGAY_GIAO_DICH)
            ORDER BY QUARTER(NGAY_GIAO_DICH)
        ";
    } else {
        $query = "
            SELECT 
                YEAR(NGAY_GIAO_DICH) AS label,
                SUM(CASE WHEN LOAI_GIAO_DICH = 'doanh thu' AND TRANG_THAI = 'đã thanh toán' THEN SO_TIEN ELSE 0 END) AS revenue,
                SUM(CASE WHEN LOAI_GIAO_DICH = 'doanh thu' AND TRANG_THAI = 'chờ thanh toán' THEN SO_TIEN ELSE 0 END) AS revenue_predicted,
                SUM(CASE WHEN LOAI_GIAO_DICH = 'doanh thu' AND TRANG_THAI = 'đã thanh toán' THEN SO_TIEN ELSE 0 END) AS revenue_actual,
                SUM(CASE WHEN LOAI_GIAO_DICH = 'chi phí' AND LOAI_CHI_TIET = 'Chi phí phát sinh' THEN SO_TIEN ELSE 0 END) AS expense_operational,
                SUM(CASE WHEN LOAI_CHI_TIET = 'Lương nhân viên' THEN SO_TIEN ELSE 0 END) AS salary_expense
            FROM tai_chinh tc
            JOIN chi_nhanh cn ON tc.ID_CN = cn.ID_CN
            WHERE 1=1 $branchCondition
            GROUP BY YEAR(NGAY_GIAO_DICH)
            ORDER BY YEAR(NGAY_GIAO_DICH)
        ";
    }

    $result = mysqli_query($conn, $query);
    if (!$result) {
        echo json_encode(['success' => false, 'message' => $conn->error]);
        exit;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        // FIXED: Use consistent label formatting with helper function
        $label = formatPeriodLabel($row['label'], $filter);
        
        // FIXED: Validate and sanitize values (must be >= 0)
        $revenue = max(0, (int)$row['revenue']);
        $revenuePredicted = max(0, (int)($row['revenue_predicted'] ?? 0));
        $revenueActual = max(0, (int)($row['revenue_actual'] ?? 0));
        $expenseOperational = max(0, (int)($row['expense_operational'] ?? 0)); // Chi phí phát sinh (vận hành)
        $salaryExpense = max(0, (int)($row['salary_expense'] ?? 0)); // Chi phí lương
        $totalExpense = $expenseOperational; // Tổng chi phí vận hành = chi phí phát sinh
        
        // FIXED: Log warning if negative values encountered (data integrity check)
        if ($row['expense_operational'] < 0) {
            error_log("WARNING: Negative incidental expense value detected in period $label: {$row['expense_operational']}");
        }
        if ($row['salary_expense'] < 0) {
            error_log("WARNING: Negative salary expense in period $label: {$row['salary_expense']}");
        }

        $data['labels'][] = $label;
        $data['revenue'][] = $revenue;
        $data['revenue_predicted'][] = $revenuePredicted;
        $data['revenue_actual'][] = $revenueActual;
        $data['expense'][] = $totalExpense; // Chi phí phát sinh (vận hành)
        $data['expense_operational'][] = $expenseOperational; // Alias
        $data['salary_expense'][] = $salaryExpense; // Lương tách riêng
    }

    // Lấy chi phí phát sinh từ bảng chi_phi_phat_sinh (không bao gồm các bản ghi thuế)
    // Lọc theo kỳ thời gian tương ứng với $filter (tháng/quý/năm)
    $expenseIncidentalQuery = "
        SELECT COALESCE(SUM(cpp.GIA_TRI), 0) as total_expense
        FROM chi_phi_phat_sinh cpp
        JOIN chi_nhanh cn ON cpp.ID_CN = cn.ID_CN
        WHERE 1=1
          AND (cpp.TEN_CP NOT IN ('Thuế VAT', 'Thuế DN'))
    ";

    // Lọc theo filter (tháng/quý/năm)
    $currentYear = date('Y');
    $currentMonth = date('m');
    $currentQuarter = ceil(date('n') / 3);
    
    if ($filter === 'month') {
        // Lọc theo tháng hiện tại
        $expenseIncidentalQuery .= " AND YEAR(cpp.NGAY_GIO) = $currentYear AND MONTH(cpp.NGAY_GIO) = $currentMonth";
    } elseif ($filter === 'quarter') {
        // Lọc theo quý hiện tại
        $expenseIncidentalQuery .= " AND YEAR(cpp.NGAY_GIO) = $currentYear AND QUARTER(cpp.NGAY_GIO) = $currentQuarter";
    } else {
        // filter === 'year' - lọc theo năm hiện tại
        $expenseIncidentalQuery .= " AND YEAR(cpp.NGAY_GIO) = $currentYear";
    }

    if ($branch !== 'all') {
        $branchID = intval(substr($branch, 2)); // cn1 => 1
        $expenseIncidentalQuery .= " AND cn.ID_CN = $branchID";
    }

    $expenseIncidentalResult = mysqli_query($conn, $expenseIncidentalQuery);
    $expenseIncidentalRow = $expenseIncidentalResult ? mysqli_fetch_assoc($expenseIncidentalResult) : ['total_expense' => 0];
    $totalExpenseIncidental = (int)($expenseIncidentalRow['total_expense'] ?? 0);

    // Không cộng VAT vào mảng chi phí vận hành ở đây; thuế sẽ được tính/hiển thị riêng

    // Lưu tổng chi phí phát sinh từ chi_phi_phat_sinh
    $data['incidental_expense'] = $totalExpenseIncidental;
    
    // QUAN TRỌNG: Không ghi đè $data['salary_expense'][] vì nó đã được xây dựng trong vòng while!
    // salary_expense[] là mảng theo kỳ (tháng/quý/năm), không phải scalar.
    // Nếu cần tính tổng lương toàn năm, dùng key khác như 'salary_expense_total'
    
    $salaryQuery = "
    SELECT SUM(SO_TIEN) as salary_expense_total
    FROM tai_chinh tc
    JOIN chi_nhanh cn ON tc.ID_CN = cn.ID_CN
    WHERE LOAI_CHI_TIET = 'Lương nhân viên' $branchCondition
    ";

    if ($salaryResult = mysqli_query($conn, $salaryQuery)) {
    $salaryRow = mysqli_fetch_assoc($salaryResult);
    $data['salary_expense_total'] = (int)($salaryRow['salary_expense_total'] ?? 0);
    } else {
    $data['salary_expense_total'] = 0;
    }
    
    // Lấy tổng thuế toàn năm/kỳ
    $taxQuery = "
    SELECT SUM(SO_TIEN) as total_tax
    FROM tai_chinh tc
    JOIN chi_nhanh cn ON tc.ID_CN = cn.ID_CN
    WHERE LOAI_CHI_TIET = 'Thuế VAT' $branchCondition
    ";
    
    if ($taxResult = mysqli_query($conn, $taxQuery)) {
    $taxRow = mysqli_fetch_assoc($taxResult);
    $data['tax'] = (int)($taxRow['total_tax'] ?? 0);
    } else {
    $data['tax'] = 0;
    }

    return $data;
}

$initialBranch = $_GET['branch'] ?? 'all';
$initialFilter = $_GET['filter'] ?? 'year';
$initialData = getRealFinancialData($initialFilter, $initialBranch);

echo json_encode($initialData);
