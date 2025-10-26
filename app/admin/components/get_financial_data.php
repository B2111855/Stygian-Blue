<?php
// File: get_financial_data.php

header('Content-Type: application/json');

include __DIR__ . '/../../../database/config.php';

function getRealFinancialData($filter = 'year', $branch = 'all') {
    global $conn;

    $branchCondition = "";
    if ($branch !== 'all') {
        $branchID = intval(substr($branch, 2)); // cn1 => 1
        $branchCondition = "AND cn.ID_CN = $branchID";
    }

    $data = ['labels' => [], 'revenue' => [], 'expense' => [], 'salary_expense' => []];

    if ($filter === 'month') {
        $query = "
            SELECT 
                MONTH(NGAY_GIAO_DICH) AS label,
                SUM(CASE WHEN LOAI_GIAO_DICH = 'doanh thu' THEN SO_TIEN ELSE 0 END) AS revenue,
                SUM(CASE WHEN LOAI_GIAO_DICH = 'chi phí' AND LOAI_CHI_TIET <> 'Lương nhân viên' THEN SO_TIEN ELSE 0 END) AS expense,
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
                SUM(CASE WHEN LOAI_GIAO_DICH = 'doanh thu' THEN SO_TIEN ELSE 0 END) AS revenue,
                SUM(CASE WHEN LOAI_GIAO_DICH = 'chi phí' AND LOAI_CHI_TIET <> 'Lương nhân viên' THEN SO_TIEN ELSE 0 END) AS expense,
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
                SUM(CASE WHEN LOAI_GIAO_DICH = 'doanh thu' THEN SO_TIEN ELSE 0 END) AS revenue,
                SUM(CASE WHEN LOAI_GIAO_DICH = 'chi phí' AND LOAI_CHI_TIET <> 'Lương nhân viên' THEN SO_TIEN ELSE 0 END) AS expense,
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
        $label = $row['label'];
        if ($filter === 'month') {
            $label = "Tháng $label";
        } elseif ($filter === 'quarter') {
            $label = "Quý $label";
        }

        $data['labels'][] = $label;
        $data['revenue'][] = (int)$row['revenue'];
        $data['expense'][] = (int)$row['expense'];
        $data['salary_expense'][] = (int)$row['salary_expense'];
    }

    // Thêm thuế 10% vào chi phí vận hành (không tính lương)
    foreach ($data['revenue'] as $index => $doanhThu) {
        $tax = $doanhThu * 0.1;
        $data['expense'][$index] += $tax;
    }

    // Thêm chi phí lương vào phản hồi
    $salaryQuery = "
    SELECT SUM(SO_TIEN) as salary_expense
    FROM tai_chinh tc
    JOIN chi_nhanh cn ON tc.ID_CN = cn.ID_CN
    WHERE LOAI_CHI_TIET = 'Lương nhân viên' $branchCondition
    ";

    if ($salaryResult = mysqli_query($conn, $salaryQuery)) {
    $salaryRow = mysqli_fetch_assoc($salaryResult);
    $data['salary_expense'] = (int)($salaryRow['salary_expense'] ?? 0);
    } else {
    $data['salary_expense'] = 0;
    }


    return $data;
}

$initialBranch = $_GET['branch'] ?? 'all';
$initialFilter = $_GET['filter'] ?? 'year';
$initialData = getRealFinancialData($initialFilter, $initialBranch);

echo json_encode($initialData);
