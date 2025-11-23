<?php
// Endpoint: check_costume_type_availability.php
// Purpose: Return availability count for a costume type in a branch over a date range.
// Params (GET): id_loai (int, required), branch (int, required), from (datetime-local ISO), to (datetime-local ISO), qty (int optional)
// Response JSON: { id_loai, branch_id, total_instances, available, requested_qty, can_fulfill, max_requestable, range: {from,to} }

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=UTF-8');

require_once '../../../database/config.php';

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$idLoai   = isset($_GET['id_loai']) ? trim($_GET['id_loai']) : '';
$branchId = isset($_GET['branch']) ? trim($_GET['branch']) : '';
$fromRaw  = isset($_GET['from']) ? trim($_GET['from']) : '';
$toRaw    = isset($_GET['to']) ? trim($_GET['to']) : '';
$qtyReq   = isset($_GET['qty']) ? (int)$_GET['qty'] : 1;
if ($qtyReq < 1) { $qtyReq = 1; }

// Basic validation
if (!ctype_digit($idLoai) || !ctype_digit($branchId)) {
    respond(['error' => 'Tham số id_loai hoặc branch không hợp lệ'], 400);
}

if ($fromRaw === '' || $toRaw === '') {
    respond(['error' => 'Thiếu thời gian from/to'], 400);
}

// Normalize datetime (accept both "YYYY-MM-DDTHH:MM" or "YYYY-MM-DD HH:MM")
$fromNorm = str_replace('T', ' ', $fromRaw);
$toNorm   = str_replace('T', ' ', $toRaw);

$fromTs = strtotime($fromNorm);
$toTs   = strtotime($toNorm);
if ($fromTs === false || $toTs === false) {
    respond(['error' => 'Định dạng thời gian không hợp lệ'], 400);
}
if ($toTs <= $fromTs) {
    respond(['error' => 'Thời gian trả phải sau thời gian nhận'], 400);
}

// Prepared query computing totals & available count
$sql = "SELECT COUNT(tp.ID_TRANG_PHUC) AS total_instances,
        SUM(CASE WHEN EXISTS (
              SELECT 1
              FROM don_thue_trang_phuc_ct ct
              JOIN don_thue_trang_phuc d ON d.ID_TTP = ct.ID_TTP
              WHERE ct.ID_TP = tp.ID_TRANG_PHUC
                AND d.TRANG_THAI IN ('cho_duyet','da_duyet','dang_thue')
                AND d.NGAY_NHAN <= ?
                AND d.NGAY_TRA_DK >= ?
            ) THEN 0 ELSE 1 END) AS available_count
        FROM trang_phuc tp
        WHERE tp.ID_LOAI = ?
          AND tp.ID_CN = ?
          AND tp.TRANG_THAI = 'available'";

$totalInstances = 0; $availableCount = 0;
if ($stmt = $conn->prepare($sql)) {
    // Overlap condition: existing.NGAY_NHAN <= to AND existing.NGAY_TRA_DK >= from
    $stmt->bind_param('ssii', $toNorm, $fromNorm, $idLoai, $branchId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($res) {
            $row = $res->fetch_assoc();
            $totalInstances = (int)($row['total_instances'] ?? 0);
            $availableCount = (int)($row['available_count'] ?? 0);
        }
    }
    $stmt->close();
}

$data = [
    'id_loai'         => (int)$idLoai,
    'branch_id'       => (int)$branchId,
    'total_instances' => $totalInstances,
    'available'       => $availableCount,
    'requested_qty'   => $qtyReq,
    'can_fulfill'     => $availableCount >= $qtyReq && $qtyReq > 0,
    'max_requestable' => $availableCount,
    'range'           => [ 'from' => date('Y-m-d H:i', $fromTs), 'to' => date('Y-m-d H:i', $toTs) ],
];

respond($data);
