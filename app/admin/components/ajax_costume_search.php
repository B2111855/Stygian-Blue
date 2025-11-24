<?php
// Simple AJAX endpoint to search costumes by branch + term
require_once '../../database/config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

// Basic auth gate: require login
if (!isset($_SESSION['ID_QUYEN'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$branchId = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
$term     = trim($_GET['term'] ?? '');
if ($branchId <= 0) {
    echo json_encode([]); // no branch context
    exit;
}

// Limit length
if (strlen($term) > 100) { $term = substr($term, 0, 100); }

$params = [];
$types  = '';
$sql = "SELECT ID_TRANG_PHUC, TEN, MAU_SAC, SIZE, GIA_THUE, TRANG_THAI FROM trang_phuc WHERE ID_CN = ?";
$types .= 'i';
$params[] = $branchId;

if ($term !== '') {
    $sql .= " AND (TEN LIKE ? OR MAU_SAC LIKE ?)";
    $types .= 'ss';
    $like = '%' . $term . '%';
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY TEN ASC LIMIT 15";
$stmt = $conn->prepare($sql);
if ($types) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$res = $stmt->get_result();
$out = [];
while ($row = $res->fetch_assoc()) {
    $out[] = [
        'id' => (int)$row['ID_TRANG_PHUC'],
        'ten' => $row['TEN'],
        'mau' => $row['MAU_SAC'],
        'size' => $row['SIZE'],
        'gia_thue' => (int)$row['GIA_THUE'],
        'trang_thai' => $row['TRANG_THAI'],
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
