<?php
require_once __DIR__ . '/../../../database/config.php';
require_once __DIR__ . '/report_helpers.php';

$branchParam = $_GET['branch'] ?? 'all';
$filterParam = $_GET['filter'] ?? 'year';

[$filter, $start, $end] = report_resolve_relative_range($filterParam);
$branchId = report_parse_branch($branchParam);

$sql = "
    SELECT COALESCE(tk.HO_TEN, CONCAT('NV ', tk.ID_TK)) AS name,
           COUNT(*) AS count
    FROM phan_cong_nhan_vien pcnv
    JOIN tai_khoan tk ON pcnv.ID_TK = tk.ID_TK
    JOIN nhan_vien nv ON tk.ID_TK = nv.ID_TK
    WHERE pcnv.THOI_GIAN_BAT_DAU BETWEEN ? AND ?";

$types = 'ss';
$params = [
    $start->format('Y-m-d H:i:s'),
    $end->format('Y-m-d H:i:s')
];

if ($branchId) {
    $sql .= ' AND nv.ID_CN = ?';
    $types .= 'i';
    $params[] = $branchId;
}

$sql .= ' GROUP BY tk.HO_TEN ORDER BY count DESC LIMIT 12';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Không thể tải dữ liệu phân công nhân sự.']);
    exit;
}

report_stmt_bind_params($stmt, $types, $params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'name' => $row['name'],
        'count' => (int)$row['count']
    ];
}

$stmt->close();

header('Content-Type: application/json');
echo json_encode($data);