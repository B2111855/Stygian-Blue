<?php
// Costume availability check for rental bookings
// Query params: id (costume ID), from (ISO datetime), to (ISO datetime)
// Returns JSON: { available: true/false, message: string, conflicts: array }

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
// Bật bộ đệm đầu ra để thu gom mọi nội dung phát sinh từ include/echo và dọn sạch trước khi trả JSON
if (!ob_get_level()) {
    ob_start();
}

require_once '../../../database/config.php';

function respond($available, $extra = [], $statusCode = 200) {
    http_response_code((int)$statusCode);
    // Dọn sạch mọi nội dung có thể đã bị in ra trước đó (HTML/Warning/BOM)
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode(array_merge(['available' => (bool)$available], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$id   = isset($_GET['id']) ? trim($_GET['id']) : '';
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to   = isset($_GET['to']) ? trim($_GET['to']) : '';

// Basic validation
if (!ctype_digit($id) || $from === '' || $to === '') {
    respond(false, ['error' => 'Thiếu tham số hoặc không hợp lệ'], 200);
}

// Parse datetimes
$fromTs = strtotime(str_replace('T', ' ', $from));
$toTs   = strtotime(str_replace('T', ' ', $to));
if (!$fromTs || !$toTs || $toTs <= $fromTs) {
    respond(false, ['error' => 'Khoảng thời gian không hợp lệ'], 200);
}

$fromDt = date('Y-m-d H:i:s', $fromTs);
$toDt   = date('Y-m-d H:i:s', $toTs);

// Rule 1: Không cho chọn ngày quá khứ
$now = time();
if ($fromTs < $now) {
    respond(false, ['error' => 'Không thể đặt thuê vào thời gian quá khứ'], 200);
}

// Rule 1.5: Giờ nhận phải trong giờ làm việc (8h-21h)
$fromHour = (int)date('G', $fromTs);
if ($fromHour < 8 || $fromHour >= 21) {
    respond(false, ['error' => 'Giờ nhận phải trong khoảng 8:00 - 21:00 (giờ làm việc)'], 200);
}

// Rule 2: Verify costume exists & available
$costume = null;
if ($stmt = $conn->prepare('SELECT ID_TRANG_PHUC, TEN, TRANG_THAI, DELETED_AT FROM trang_phuc WHERE ID_TRANG_PHUC = ? LIMIT 1')) {
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        $costume = $res->fetch_assoc();
    }
    $stmt->close();
}

if (!$costume) {
    respond(false, ['error' => 'Trang phục không tồn tại'], 200);
}

if ($costume['DELETED_AT'] !== null) {
    respond(false, ['error' => 'Trang phục đã bị xóa'], 200);
}

if (!in_array($costume['TRANG_THAI'], ['available', 'san_sang', 'AVAILABLE'])) {
    respond(false, ['error' => 'Trang phục không khả dụng'], 200);
}

// Rule 3: Check overlapping with approved/active rentals + cleaning buffer
// TRANG_THAI cần kiểm tra: 'cho_duyet', 'da_duyet', 'dang_thue'
// Không kiểm tra: 'da_tra', 'huy'
// Buffer vệ sinh: 1 ngày sau khi trả

$cleaningBufferDays = 1; // Thời gian vệ sinh sau khi trả

$sql = "SELECT 
    dtt.ID_TTP, 
    dtt.NGAY_NHAN, 
    dtt.NGAY_TRA_DK,
    DATE_ADD(dtt.NGAY_TRA_DK, INTERVAL ? DAY) AS NGAY_KHA_DUNG,
    dtt.TRANG_THAI
FROM don_thue_trang_phuc dtt
JOIN don_thue_trang_phuc_ct ct ON ct.ID_TTP = dtt.ID_TTP
WHERE ct.ID_TP = ?
  AND dtt.TRANG_THAI IN ('cho_duyet', 'da_duyet', 'dang_thue')
  AND (
    -- Khoảng thời gian mới trùng với khoảng đang thuê (bao gồm buffer vệ sinh)
    (? < DATE_ADD(dtt.NGAY_TRA_DK, INTERVAL ? DAY) AND ? > dtt.NGAY_NHAN)
  )
ORDER BY dtt.NGAY_NHAN";

$conflicts = [];
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('iisis', $cleaningBufferDays, $id, $fromDt, $cleaningBufferDays, $toDt);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $conflicts[] = [
                'id' => $row['ID_TTP'],
                'from' => $row['NGAY_NHAN'],
                'to' => $row['NGAY_TRA_DK'],
                'available_after' => $row['NGAY_KHA_DUNG'],
                'status' => $row['TRANG_THAI']
            ];
        }
    }
    $stmt->close();
}

if (!empty($conflicts)) {
    $nextAvailable = $conflicts[0]['available_after'];
    respond(false, [
        'message' => 'Trang phục đã được đặt trong khoảng thời gian này (bao gồm thời gian vệ sinh)',
        'conflicts' => $conflicts,
        'next_available' => $nextAvailable
    ], 200);
}

// All checks passed
respond(true, ['message' => 'Trang phục khả dụng trong khoảng thời gian này'], 200);

