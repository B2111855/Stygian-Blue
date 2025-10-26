<?php
include '../../../database/config.php';

$branch = $_GET['branch'] ?? 'all';
$branchCondition = '';

if ($branch !== 'all') {
    $branchId = intval(substr($branch, 2));
    $branchCondition = "AND tc.ID_CN = $branchId";
}

$sql = "
    SELECT HOUR(l.THOI_GIAN_BAT_DAU) AS hour_slot, COUNT(*) AS total
    FROM lich_hen l
    JOIN hoa_don h ON l.ID_LICHHEN = h.ID_LICHHEN
    JOIN tai_chinh tc ON h.ID_HD = tc.ID_HD
    WHERE 1 $branchCondition
    GROUP BY hour_slot
    ORDER BY hour_slot
";

$result = mysqli_query($conn, $sql);
$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $label = $row['hour_slot'] . 'h';
    $data[] = [
        'label' => $label,
        'value' => (int)$row['total']
    ];
}

header('Content-Type: application/json');
echo json_encode($data);
?>
