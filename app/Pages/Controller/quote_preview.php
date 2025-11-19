<?php
// Quote preview for single service bookings
// Input: JSON { branch_id, date, time, service_id, device_ids: [] }
// Output: JSON { total: number, items?: array }

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '1');

try {
    // Load DB config
    include $_SERVER['DOCUMENT_ROOT'] . '/stygianblue/database/config.php';

    // Parse input (JSON preferred)
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST; // fallback
    }

    $serviceId = isset($data['service_id']) ? (int)$data['service_id'] : 0;

    if ($serviceId <= 0) {
        echo json_encode([ 'total' => 0, 'error' => 'Thiếu service_id' ]);
        exit;
    }

    // Get latest unit price for service
    $sql = "SELECT DON_GIA FROM DON_GIA_DICH_VU WHERE ID_DV = ? ORDER BY NGAY_GIO DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode([ 'total' => 0, 'error' => 'Không thể chuẩn bị truy vấn' ]);
        exit;
    }
    $stmt->bind_param('i', $serviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $servicePrice = $row && isset($row['DON_GIA']) ? (int)$row['DON_GIA'] : 0;
    $stmt->close();

    // For now, devices have no pricing table => sum to 0
    // Keep the structure to extend later if needed
    $deviceTotal = 0;

    $total = max(0, (int)$servicePrice + (int)$deviceTotal);

    echo json_encode([
        'total' => $total,
        'items' => [
            [ 'label' => 'Dịch vụ', 'price' => (int)$servicePrice ],
            [ 'label' => 'Thiết bị', 'price' => (int)$deviceTotal ],
        ],
        'currency' => 'VND',
        'ok' => true,
    ]);
} catch (Throwable $e) {
    echo json_encode([ 'total' => 0, 'error' => 'Lỗi hệ thống' ]);
}
