<?php
include '../../../database/config.php';

$filter = $_GET['filter'] ?? 'year';
$branch = $_GET['branch'] ?? 'all';

$branchCondition = '';
if ($branch !== 'all') {
    $branchId = intval(substr($branch, 2));
    $branchCondition = "AND tc.ID_CN = $branchId";
}

$sql = "
    SELECT dv.TEN_DV, COUNT(*) AS total
    FROM hoa_don h
    JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
    JOIN dich_vu dv ON l.ID_DV = dv.ID_DV
    JOIN tai_chinh tc ON h.ID_HD = tc.ID_HD
    WHERE 1 $branchCondition
    GROUP BY dv.ID_DV
    ORDER BY total DESC
";

$result = mysqli_query($conn, $sql);
$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $data[] = [
        'label' => $row['TEN_DV'],
        'value' => (int)$row['total']
    ];
}

header('Content-Type: application/json');
echo json_encode($data);
?>
