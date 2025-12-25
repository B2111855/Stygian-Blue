<?php
/**
 * get_taxes.php
 * Lấy thông tin thuế VAT và Thuế DN của một chi nhánh/tháng
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
    
    // Lấy dữ liệu thuế
    if ($branch_id === 'all') {
        // Lấy tất cả chi nhánh
        $query = "
            SELECT 
                LOAI_THUE,
                SUM(SO_TIEN) as SO_TIEN,
                TY_LE,
                CO_SO_TINH,
                GHI_CHU,
                MAX(NGAY_CAP_NHAT) as NGAY_CAP_NHAT
            FROM thue_chi_tra
            WHERE THANG = '$month'
            GROUP BY LOAI_THUE
            ORDER BY LOAI_THUE
        ";
    } else {
        // Lấy chi nhánh cụ thể
        $branch_id = (int)$branch_id;
        $query = "
            SELECT 
                LOAI_THUE,
                SO_TIEN,
                TY_LE,
                CO_SO_TINH,
                GHI_CHU,
                NGAY_CAP_NHAT
            FROM thue_chi_tra
            WHERE ID_CN = $branch_id 
            AND THANG = '$month'
            ORDER BY LOAI_THUE
        ";
    }
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        throw new Exception('Query error: ' . mysqli_error($conn));
    }
    
    $taxes = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $taxes[] = [
            'type' => $row['LOAI_THUE'],
            'amount' => (float)$row['SO_TIEN'],
            'rate' => (float)$row['TY_LE'],
            'baseOn' => $row['CO_SO_TINH'],
            'note' => $row['GHI_CHU'],
            'updatedAt' => $row['NGAY_CAP_NHAT']
        ];
    }
    
    // Nếu không có dữ liệu thuế, tự động tính từ dữ liệu tháng này hoặc tháng gần nhất
    if (empty($taxes)) {
        // Trước tiên kiểm tra tháng yêu cầu có dữ liệu không
        $checkDataQuery = "
            SELECT COUNT(*) as cnt
            FROM tai_chinh
            WHERE DATE_FORMAT(NGAY_GIAO_DICH, '%Y-%m') = '$month'
        ";
        $checkResult = mysqli_query($conn, $checkDataQuery);
        $checkRow = mysqli_fetch_assoc($checkResult);
        
        $targetMonth = $month;
        // Nếu tháng hiện tại không có dữ liệu, dùng tháng gần nhất có dữ liệu
        if ($checkRow['cnt'] == 0) {
            $latestQuery = "
                SELECT DATE_FORMAT(NGAY_GIAO_DICH, '%Y-%m') as latest_month
                FROM tai_chinh
                WHERE DATE_FORMAT(NGAY_GIAO_DICH, '%Y-%m') <= '$month'
                ORDER BY NGAY_GIAO_DICH DESC
                LIMIT 1
            ";
            $latestResult = mysqli_query($conn, $latestQuery);
            if ($latestResult && $latestRow = mysqli_fetch_assoc($latestResult)) {
                $targetMonth = $latestRow['latest_month'];
            }
        }
        
        // Lấy doanh thu đã thanh toán
        $revQuery = "
            SELECT COALESCE(SUM(SO_TIEN), 0) as total
            FROM tai_chinh
            WHERE LOAI_GIAO_DICH = 'doanh thu'
              AND TRANG_THAI = 'đã thanh toán'
              AND DATE_FORMAT(NGAY_GIAO_DICH, '%Y-%m') = '$targetMonth'
        ";
        
        $revResult = mysqli_query($conn, $revQuery);
        $revRow = mysqli_fetch_assoc($revResult);
        $revenue = (float)($revRow['total'] ?? 0);
        
        // Lấy chi phí
        $expQuery = "
            SELECT COALESCE(SUM(SO_TIEN), 0) as total
            FROM tai_chinh
            WHERE LOAI_GIAO_DICH = 'chi phí'
              AND DATE_FORMAT(NGAY_GIAO_DICH, '%Y-%m') = '$targetMonth'
        ";
        
        $expResult = mysqli_query($conn, $expQuery);
        $expRow = mysqli_fetch_assoc($expResult);
        $expenses = (float)($expRow['total'] ?? 0);
        
        // Tính lợi nhuận và thuế
        $profit = $revenue - $expenses;
        $vatTax = round($revenue * 0.1);
        $corpTax = $profit > 0 ? round($profit * 0.2) : 0;
        
        // Thêm vào array taxes
        if ($revenue > 0) {
            $taxes[] = [
                'type' => 'Thuế VAT',
                'amount' => $vatTax,
                'rate' => '10%',
                'baseOn' => "Doanh thu: " . number_format($revenue, 0),
                'note' => 'VAT trên doanh thu (tự động tính)',
                'updatedAt' => date('Y-m-d H:i:s')
            ];
        }
        
        if ($profit > 0) {
            $taxes[] = [
                'type' => 'Thuế DN',
                'amount' => $corpTax,
                'rate' => '20%',
                'baseOn' => "Lợi nhuận: " . number_format($profit, 0),
                'note' => 'Thuế doanh nghiệp (tự động tính)',
                'updatedAt' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    // Tính tổng thuế
    $totalTax = array_sum(array_column($taxes, 'amount'));
    
    echo json_encode([
        'status' => 'success',
        'month' => $month,
        'branch_id' => $branch_id,
        'taxes' => $taxes,
        'totalTax' => $totalTax
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
