<?php
/**
 * calculate_duration.php
 * 
 * Helper endpoint để tính tổng thời lượng của dịch vụ/gói được chọn.
 * Dùng bởi lienhe.php updateServiceSummary() để hiển thị thời lượng chính xác.
 * 
 * Input: POST JSON
 * - booking_type: 'service' | 'package' | 'costume'
 * - service_ids: [int, ...] (nếu service)
 * - package_id: int (nếu package)
 * 
 * Output: JSON
 * {
 *   "duration_min": int
 * }
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../../database/config.php';

// Lấy input từ POST
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$bookingType = $input['booking_type'] ?? 'service';
$serviceIdsRaw = $input['service_ids'] ?? [];
$packageId = $input['package_id'] ?? null;

// Normalize service IDs
$serviceIds = [];
foreach ((array)$serviceIdsRaw as $sid) {
    if (ctype_digit((string)$sid)) {
        $serviceIds[] = (int)$sid;
    }
}

$packageId = $packageId ? (int)$packageId : null;

// Connect to database
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection unavailable', 'duration_min' => 60]);
    exit;
}

$durationMin = 60; // default

if ($bookingType === 'service' && !empty($serviceIds)) {
    // Calculate total duration for all services
    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
    $stmt = $conn->prepare("SELECT SUM(THOI_GIAN) as TOTAL_TIME FROM dich_vu WHERE ID_DV IN ($placeholders)");
    
    if ($stmt) {
        $types = str_repeat('i', count($serviceIds));
        $stmt->bind_param($types, ...$serviceIds);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        
        if ($row && $row['TOTAL_TIME']) {
            $durationMin = (int)$row['TOTAL_TIME'];
        }
    }
} elseif ($bookingType === 'package' && $packageId) {
    // Calculate total duration for package services
    $stmt = $conn->prepare(
        "SELECT SUM(d.THOI_GIAN) as TOTAL_TIME FROM goi_dich_vu_chi_tiet gdt 
         JOIN dich_vu d ON d.ID_DV = gdt.ID_DV 
         WHERE gdt.ID_GOI = ?"
    );
    
    if ($stmt) {
        $stmt->bind_param('i', $packageId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        
        if ($row && $row['TOTAL_TIME']) {
            $durationMin = (int)$row['TOTAL_TIME'];
        }
    }
} elseif ($bookingType === 'costume') {
    $durationMin = (int)getenv('DEFAULT_DURATION_COSTUME') ?: 30;
}

$conn->close();

echo json_encode(['duration_min' => $durationMin]);
?>
