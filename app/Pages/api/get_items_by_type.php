<?php
// API endpoint để lấy danh sách items theo loại trang phục
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../database/config.php';

// Simple asset helper for API
function api_asset_href($path) {
    if (empty($path)) return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    
    // Get base path (e.g., /StygianBlue/app)
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $parts = explode('/app/', $scriptPath);
    $base = $parts[0];
    
    return $base . '/' . ltrim($path, '/');
}

$typeId = $_GET['loai'] ?? '';

if (!ctype_digit((string)$typeId)) {
    echo json_encode(['success' => false, 'message' => 'ID loại không hợp lệ: ' . $typeId]);
    exit;
}

$defaultImagePath = 'public/images/bg01.png';
$defaultImageUrl = api_asset_href($defaultImagePath);

// Lấy thông tin loại
$typeInfo = null;
if ($typeStmt = $conn->prepare("SELECT ID_LOAI, TEN_LOAI, MO_TA FROM trang_phuc_loai WHERE ID_LOAI = ?")) {
    $typeStmt->bind_param('i', $typeId);
    if ($typeStmt->execute()) {
        $result = $typeStmt->get_result();
        $typeInfo = $result->fetch_assoc();
    }
    $typeStmt->close();
}

if (!$typeInfo) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy loại trang phục với ID: ' . $typeId]);
    exit;
}

// Lấy danh sách items
$items = [];
$sql = "SELECT 
    tp.ID_TRANG_PHUC, tp.TEN, tp.SIZE, tp.MAU_SAC, tp.GIA_THUE, tp.TRANG_THAI,
    COALESCE(cn.TEN_CN, 'Tất cả chi nhánh') AS TEN_CN, tp.ID_CN,
    ha.URL AS HINH_ANH
FROM trang_phuc tp
LEFT JOIN chi_nhanh cn ON cn.ID_CN = tp.ID_CN
LEFT JOIN (
    SELECT ID_TP, SUBSTRING_INDEX(GROUP_CONCAT(URL ORDER BY IS_COVER DESC, THU_TU ASC, ID_HA ASC SEPARATOR '||'), '||', 1) AS URL
    FROM trang_phuc_hinh_anh
    WHERE IS_ACTIVE = 1
    GROUP BY ID_TP
) ha ON ha.ID_TP = tp.ID_TRANG_PHUC
WHERE tp.ID_LOAI = ? AND tp.DELETED_AT IS NULL
ORDER BY tp.TRANG_THAI = 'available' DESC, tp.TEN ASC";

if ($itemStmt = $conn->prepare($sql)) {
    $itemStmt->bind_param('i', $typeId);
    if ($itemStmt->execute()) {
        $result = $itemStmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['HINH_ANH'] = api_asset_href($row['HINH_ANH'] ?: $defaultImagePath);
            $items[] = $row;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi thực thi query: ' . $itemStmt->error]);
        exit;
    }
    $itemStmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi khi chuẩn bị query: ' . $conn->error]);
    exit;
}

echo json_encode([
    'success' => true,
    'type' => $typeInfo,
    'items' => $items,
    'count' => count($items),
    'defaultImage' => $defaultImageUrl
]);
?>
