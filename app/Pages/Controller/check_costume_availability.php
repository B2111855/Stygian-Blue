<?php
// Simple availability check stub
// Expected query params: id (costume id), from (ISO datetime), to (ISO datetime)
// Returns JSON: { available: true/false }

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require_once '../../../database/config.php';

function respond($available, $extra = []) {
    echo json_encode(array_merge(['available' => (bool)$available], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$id   = isset($_GET['id']) ? trim($_GET['id']) : '';
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to   = isset($_GET['to']) ? trim($_GET['to']) : '';

// Basic validation
if (!ctype_digit($id) || $from === '' || $to === '') {
    respond(false, ['error' => 'Thiếu tham số hoặc không hợp lệ']);
}

// Parse datetimes
$fromTs = strtotime(str_replace('T', ' ', $from));
$toTs   = strtotime(str_replace('T', ' ', $to));
if (!$fromTs || !$toTs || $toTs <= $fromTs) {
    respond(false, ['error' => 'Khoảng thời gian không hợp lệ']);
}

// NOTE: Stub logic — always available unless costume not found or inactive
$available = true;

// Optional: verify costume exists & active
if ($stmt = $conn->prepare('SELECT TRANG_THAI FROM trang_phuc WHERE ID_TRANG_PHUC = ? LIMIT 1')) {
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            // If status indicates not rentable (customize according to schema)
            if (!in_array($row['TRANG_THAI'], ['AVAILABLE', 'CHO_THUE', 'ACTIVE'])) { // adjust values
                $available = false;
            }
        } else {
            $available = false; // Not found
        }
    }
    $stmt->close();
}

// Future: check overlapping bookings table
// Example pseudo:
// SELECT 1 FROM dat_thue_trang_phuc WHERE ID_TRANG_PHUC = ? AND (? < THOI_GIAN_TRA AND ? > THOI_GIAN_NHAN) LIMIT 1
// If found -> available = false

respond($available);
