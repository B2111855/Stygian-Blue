<?php
/**
 * calculate_taxes.php
 * Tính thuế VAT và Thuế Doanh nghiệp tự động
 * Được gọi hàng tháng hoặc khi cập nhật chi phí/doanh thu
 */

error_reporting(0);
ini_set('display_errors', '0');
include '../../../database/config.php';

header('Content-Type: application/json');

try {
    $branch_id = $_GET['branch_id'] ?? null;
    $month = $_GET['month'] ?? date('Y-m');
    
    if (!$branch_id) {
        throw new Exception('Thiếu branch_id');
    }
    
    // Validate month format YYYY-MM
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        throw new Exception('Định dạng tháng không hợp lệ (phải là YYYY-MM)');
    }
    
    // Xác định danh sách branch cần tính
    if ($branch_id === 'all') {
        $branchesQuery = "SELECT ID_CN FROM chi_nhanh";
        $branchesResult = mysqli_query($conn, $branchesQuery);
        $branches = [];
        while ($row = mysqli_fetch_assoc($branchesResult)) {
            $branches[] = $row['ID_CN'];
        }
    } else {
        $branches = [(int)$branch_id];
    }
    
    // Xóa dữ liệu cũ của tháng này
    if ($branch_id === 'all') {
        $deleteQuery = "DELETE FROM thue_chi_tra WHERE THANG = '$month'";
    } else {
        $deleteQuery = "DELETE FROM thue_chi_tra WHERE ID_CN = {$branches[0]} AND THANG = '$month'";
    }
    mysqli_query($conn, $deleteQuery);
    
    // Tính thuế cho từng branch
    $allTaxes = [];
    $financialSummary = [
        'totalRevenue' => 0,
        'totalExpense' => 0,
        'profit' => 0
    ];
    
    foreach ($branches as $branchNum) {
        // 1. Doanh thu thực tế (đã thanh toán) trong tháng
        $revenueQuery = "
            SELECT COALESCE(SUM(SO_TIEN), 0) as total_revenue 
            FROM tai_chinh 
            WHERE LOAI_GIAO_DICH = 'doanh thu' 
              AND TRANG_THAI = 'đã thanh toán'
              AND DATE_FORMAT(NGAY_GIAO_DICH, '%Y-%m') = '$month'
              AND ID_CN = $branchNum
        ";
        $revenueResult = mysqli_query($conn, $revenueQuery);
        $revenueRow = $revenueResult ? mysqli_fetch_assoc($revenueResult) : ['total_revenue' => 0];
        $totalRevenuePaid = (float)($revenueRow['total_revenue'] ?? 0);

        // 2. Chi phí vận hành (không gồm lương) trong tháng từ tai_chinh
                $opExpenseQuery = "
                        SELECT COALESCE(SUM(SO_TIEN), 0) as total_expense
                        FROM tai_chinh
                        WHERE LOAI_GIAO_DICH = 'chi phí'
                            AND (LOAI_CHI_TIET IS NULL OR LOAI_CHI_TIET NOT IN ('Lương nhân viên','Thuế VAT','Thuế DN'))
                            AND DATE_FORMAT(NGAY_GIAO_DICH, '%Y-%m') = '$month'
                            AND ID_CN = $branchNum
                ";
        $opExpenseResult = mysqli_query($conn, $opExpenseQuery);
        $opExpenseRow = $opExpenseResult ? mysqli_fetch_assoc($opExpenseResult) : ['total_expense' => 0];
        $opExpense = (float)($opExpenseRow['total_expense'] ?? 0);

        // 3. Chi phí lương tháng này từ tai_chinh
        $salaryExpenseQuery = "
            SELECT COALESCE(SUM(SO_TIEN), 0) as salary_expense
            FROM tai_chinh
            WHERE LOAI_GIAO_DICH = 'chi phí'
              AND LOAI_CHI_TIET = 'Lương nhân viên'
              AND DATE_FORMAT(NGAY_GIAO_DICH, '%Y-%m') = '$month'
              AND ID_CN = $branchNum
        ";
        $salaryExpenseResult = mysqli_query($conn, $salaryExpenseQuery);
        $salaryExpenseRow = $salaryExpenseResult ? mysqli_fetch_assoc($salaryExpenseResult) : ['salary_expense' => 0];
        $salaryExpense = (float)($salaryExpenseRow['salary_expense'] ?? 0);

        // 4. Chi phí phát sinh (loại trừ các bản ghi thuế) trong tháng
        $incidentalExpenseQuery = "
            SELECT COALESCE(SUM(GIA_TRI), 0) as incidental_expense
            FROM chi_phi_phat_sinh
            WHERE TEN_CP NOT IN ('Thuế VAT', 'Thuế DN')
              AND DATE_FORMAT(NGAY_GIO, '%Y-%m') = '$month'
              AND ID_CN = $branchNum
        ";
        $incidentalExpenseResult = mysqli_query($conn, $incidentalExpenseQuery);
        $incidentalExpenseRow = $incidentalExpenseResult ? mysqli_fetch_assoc($incidentalExpenseResult) : ['incidental_expense' => 0];
        $incidentalExpense = (float)($incidentalExpenseRow['incidental_expense'] ?? 0);

        // 5. Lợi nhuận dựa trên doanh thu thực tế đã thanh toán
        $totalExpense = $opExpense + $salaryExpense + $incidentalExpense;
        $profit = $totalRevenuePaid - $totalExpense;

        // 6. Thuế VAT = 10% × Doanh thu thực tế đã thanh toán
        $vatRate = 10; // %
        $vatAmount = ($totalRevenuePaid * $vatRate) / 100;

        // 7. Thuế DN = 20% × Lợi nhuận (nếu > 0)
        $corporateTaxRate = 20; // %
        $corporateTaxAmount = ($profit > 0) ? ($profit * $corporateTaxRate) / 100 : 0;
        
        // 6. Lưu vào bảng thue_chi_tra
                // Idempotent: Xóa bản ghi thuế VAT/DN của tháng này trước khi chèn để tránh nhân đôi
                $cleanupTaxDetail = "DELETE FROM thue_chi_tra WHERE ID_CN = $branchNum AND THANG = '$month' AND LOAI_THUE IN ('Thuế VAT','Thuế DN')";
                mysqli_query($conn, $cleanupTaxDetail);

                $cleanupTaxFinance = "DELETE FROM tai_chinh 
                                                            WHERE ID_CN = $branchNum 
                                                                AND LOAI_GIAO_DICH = 'chi phí' 
                                                                AND LOAI_CHI_TIET IN ('Thuế VAT','Thuế DN') 
                                                                AND DATE_FORMAT(NGAY_GIAO_DICH, '%Y-%m') = '$month'";
                mysqli_query($conn, $cleanupTaxFinance);

                // Thêm bản ghi thuế VAT
        $insertVatQuery = "
            INSERT INTO thue_chi_tra (ID_CN, LOAI_THUE, SO_TIEN, TY_LE, CO_SO_TINH, THANG, GHI_CHU)
            VALUES ($branchNum, 'Thuế VAT', $vatAmount, $vatRate, 'Doanh thu', '$month', 'Tính tự động: $vatRate% × Doanh thu')
        ";
        mysqli_query($conn, $insertVatQuery);
        
        // Đồng bộ thuế VAT vào tai_chinh
        $syncVatQuery = "INSERT INTO tai_chinh (LOAI_GIAO_DICH, SO_TIEN, LOAI_CHI_TIET, NGAY_GIAO_DICH, ID_CN, TRANG_THAI)
                VALUES ('chi phí', $vatAmount, 'Thuế VAT', NOW(), $branchNum, 'đã thanh toán')";
        mysqli_query($conn, $syncVatQuery);
        
        // Thêm bản ghi thuế DN
        $insertCorpTaxQuery = "
            INSERT INTO thue_chi_tra (ID_CN, LOAI_THUE, SO_TIEN, TY_LE, CO_SO_TINH, THANG, GHI_CHU)
            VALUES ($branchNum, 'Thuế DN', $corporateTaxAmount, $corporateTaxRate, 'Lợi nhuận', '$month', 'Tính tự động: $corporateTaxRate% × Lợi nhuận')
        ";
        mysqli_query($conn, $insertCorpTaxQuery);
        
        // Đồng bộ thuế DN vào tai_chinh (chỉ khi có lợi nhuận > 0)
        if ($corporateTaxAmount > 0) {
            $syncCorpTaxQuery = "INSERT INTO tai_chinh (LOAI_GIAO_DICH, SO_TIEN, LOAI_CHI_TIET, NGAY_GIAO_DICH, ID_CN, TRANG_THAI)
                                VALUES ('chi phí', $corporateTaxAmount, 'Thuế DN', NOW(), $branchNum, 'đã thanh toán')";
            mysqli_query($conn, $syncCorpTaxQuery);
        }
        
        // Collect data for response
        $allTaxes[] = [
            'branch' => $branchNum,
            'vat' => round($vatAmount, 0),
            'corporateTax' => round($corporateTaxAmount, 0),
            'revenue' => $totalRevenuePaid,
            'expense' => $totalExpense,
            'profit' => $profit
        ];

        $financialSummary['totalRevenue'] += $totalRevenuePaid;
        $financialSummary['totalExpense'] += $totalExpense;
        $financialSummary['profit'] += $profit;
    }
    
    // 7. Trả về kết quả
    echo json_encode([
        'status' => 'success',
        'month' => $month,
        'branch_id' => $branch_id,
        'branchDetails' => $allTaxes,
        'financialData' => [
            'totalRevenue' => $financialSummary['totalRevenue'],
            'totalExpense' => $financialSummary['totalExpense'],
            'profit' => $financialSummary['profit']
        ],
        'taxes' => [
            'vat' => [
                'name' => 'Thuế VAT',
                'rate' => 10,
                'amount' => round(array_sum(array_column($allTaxes, 'vat')), 0),
                'baseOn' => 'Doanh thu'
            ],
            'corporateTax' => [
                'name' => 'Thuế DN',
                'rate' => 20,
                'amount' => round(array_sum(array_column($allTaxes, 'corporateTax')), 0),
                'baseOn' => 'Lợi nhuận'
            ],
            'totalTax' => round(array_sum(array_column($allTaxes, 'vat')) + array_sum(array_column($allTaxes, 'corporateTax')), 0)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
