<?php
include '../../../database/config.php';

$branch = $_GET['branch'] ?? 'all';
$filter = $_GET['filter'] ?? 'year';

// Xác định điều kiện thời gian theo THOI_GIAN_BAT_DAU
$timeCondition = "";
switch ($filter) {
    case 'month':
        $timeCondition = "AND MONTH(pcnv.THOI_GIAN_BAT_DAU) = MONTH(CURDATE()) AND YEAR(pcnv.THOI_GIAN_BAT_DAU) = YEAR(CURDATE())";
        break;
    case 'quarter':
        $timeCondition = "AND QUARTER(pcnv.THOI_GIAN_BAT_DAU) = QUARTER(CURDATE()) AND YEAR(pcnv.THOI_GIAN_BAT_DAU) = YEAR(CURDATE())";
        break;
    case 'year':
    default:
        $timeCondition = "AND YEAR(pcnv.THOI_GIAN_BAT_DAU) = YEAR(CURDATE())";
        break;
}

// Xác định chi nhánh nếu có
$branchCondition = "";
if (preg_match('/^cn(\\d+)$/', $branch, $matches)) {
    $branchId = (int)$matches[1];
    $branchCondition = "AND nv.ID_CN = $branchId";
}

// Truy vấn
$query = "
    SELECT tk.HO_TEN AS name, COUNT(*) AS count
    FROM phan_cong_nhan_vien pcnv
    JOIN tai_khoan tk ON pcnv.ID_TK = tk.ID_TK
    JOIN nhan_vien nv ON tk.ID_TK = nv.ID_TK
    WHERE 1 $timeCondition $branchCondition
    GROUP BY tk.HO_TEN
    ORDER BY count DESC
";

$result = mysqli_query($conn, $query);
$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $data[] = [
        'name' => $row['name'],
        'count' => (int)$row['count']
    ];
}

header('Content-Type: application/json');
echo json_encode($data);