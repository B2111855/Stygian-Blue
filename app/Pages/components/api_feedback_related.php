<?php
/**
 * API Endpoint: Advanced Feedback Data Loading
 * GET /api/feedback/related/:feedback_id
 * 
 * Trả về: Lịch hẹn, phân công, thông tin nhân viên, khách hàng
 */

header('Content-Type: application/json');
include '../../../database/config.php';

// Validate feedback ID
$feedbackId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($feedbackId <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid feedback ID']);
  exit;
}

// Get feedback basic info
$fbQuery = "
  SELECT 
    ph.ID_PHAN_HOI, ph.ID_TK, ph.ID_DV, ph.NOI_DUNG, ph.XEP_HANG_DV, ph.NGAY_GUI,
    tk.HO_TEN, tk.SDT, tk.EMAIL,
    dv.TEN_DV, dv.GIA_DV
  FROM phan_hoi_cua_khach_hang ph
  LEFT JOIN tai_khoan tk ON ph.ID_TK = tk.ID_TK
  LEFT JOIN dich_vu dv ON ph.ID_DV = dv.ID_DV
  WHERE ph.ID_PHAN_HOI = $feedbackId
";
$fbResult = mysqli_fetch_assoc(mysqli_query($conn, $fbQuery));

if (!$fbResult) {
  http_response_code(404);
  echo json_encode(['error' => 'Feedback not found']);
  exit;
}

$customerId = $fbResult['ID_TK'];
$serviceId = $fbResult['ID_DV'];

// ============ 1. GET APPOINTMENTS ============
$appointmentsQuery = "
  SELECT 
    lh.ID_LICHHEN,
    lh.THOI_GIAN_BAT_DAU,
    lh.THOI_GIAN_KET_THUC,
    lh.DIA_CHI_HEN,
    lh.TRANGTHAI,
    lh.ID_CHINHANH,
    cn.TEN_CHINHANH
  FROM lich_hen lh
  LEFT JOIN chi_nhanh cn ON lh.ID_CHINHANH = cn.ID_CHINHANH
  WHERE lh.ID_TK = '$customerId' AND lh.ID_DV = '$serviceId'
  ORDER BY lh.THOI_GIAN_BAT_DAU DESC
  LIMIT 10
";
$appointments = [];
$appointmentsResult = mysqli_query($conn, $appointmentsQuery);
while ($row = mysqli_fetch_assoc($appointmentsResult)) {
  $appointments[] = $row;
}

// ============ 2. GET ASSIGNMENTS (Phân công) ============
$assignmentsQuery = "
  SELECT 
    pc.ID_TK as staff_id,
    tk.HO_TEN as staff_name,
    nv.CHUYEN_MON as specialization,
    pc.THOI_GIAN_BAT_DAU,
    pc.THOI_GIAN_KET_THUC,
    lh.DIA_CHI_HEN,
    lh.ID_LICHHEN,
    (SELECT AVG(XEP_HANG_DV) FROM phan_hoi_cua_khach_hang WHERE ID_TK = pc.ID_TK) as avg_rating,
    (SELECT COUNT(*) FROM phan_hoi_cua_khach_hang WHERE ID_TK = pc.ID_TK) as total_feedback
  FROM phan_cong_nhan_vien pc
  JOIN tai_khoan tk ON pc.ID_TK = tk.ID_TK
  JOIN nhan_vien nv ON tk.ID_TK = nv.ID_TK
  JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
  WHERE lh.ID_TK = '$customerId' AND lh.ID_DV = '$serviceId'
  ORDER BY pc.THOI_GIAN_BAT_DAU DESC
  LIMIT 20
";
$assignments = [];
$assignmentsResult = mysqli_query($conn, $assignmentsQuery);
while ($row = mysqli_fetch_assoc($assignmentsResult)) {
  $assignments[] = $row;
}

// ============ 3. GET CUSTOMER DETAILS ============
$customerQuery = "
  SELECT 
    tk.ID_TK,
    tk.HO_TEN,
    tk.SDT,
    tk.EMAIL,
    kh.DIA_CHI,
    (SELECT COUNT(*) FROM lich_hen WHERE ID_TK = '$customerId') as total_appointments,
    (SELECT AVG(XEP_HANG_DV) FROM phan_hoi_cua_khach_hang WHERE ID_TK = '$customerId') as avg_rating,
    (SELECT COUNT(*) FROM phan_hoi_cua_khach_hang WHERE ID_TK = '$customerId') as total_feedback
  FROM tai_khoan tk
  LEFT JOIN khach_hang kh ON tk.ID_TK = kh.ID_TK
  WHERE tk.ID_TK = '$customerId'
";
$customerDetail = mysqli_fetch_assoc(mysqli_query($conn, $customerQuery));

// ============ 4. GET STAFF PERFORMANCE (cho feedback của khách hàng này) ============
$staffPerformanceQuery = "
  SELECT 
    tk.ID_TK as staff_id,
    tk.HO_TEN as staff_name,
    nv.CHUYEN_MON as specialization,
    nv.LOAI_NV as staff_type,
    cn.TEN_CHINHANH as branch_name,
    (SELECT AVG(XEP_HANG_DV) FROM phan_hoi_cua_khach_hang 
     WHERE ID_TK IN (
       SELECT lh.ID_TK FROM lich_hen lh
       JOIN phan_cong_nhan_vien pc ON lh.ID_LICHHEN = pc.ID_LICHHEN
       WHERE pc.ID_TK = tk.ID_TK
     )) as staff_rating,
    COUNT(DISTINCT pc.ID_LICHHEN) as assignments_count
  FROM tai_khoan tk
  JOIN nhan_vien nv ON tk.ID_TK = nv.ID_TK
  JOIN chi_nhanh cn ON nv.ID_CN = cn.ID_CHINHANH
  LEFT JOIN phan_cong_nhan_vien pc ON tk.ID_TK = pc.ID_TK
  LEFT JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN AND lh.ID_DV = '$serviceId'
  GROUP BY tk.ID_TK
  LIMIT 10
";
$staffPerformance = [];
$staffResult = mysqli_query($conn, $staffPerformanceQuery);
while ($row = mysqli_fetch_assoc($staffResult)) {
  $staffPerformance[] = $row;
}

// ============ 5. GET TIMELINE (Lịch sử phản hồi & hẹn) ============
$timelineQuery = "
  (
    SELECT 
      'feedback' as type,
      ph.NGAY_GUI as date,
      CONCAT('Phản hồi: ', ph.NOI_DUNG) as description,
      ph.XEP_HANG_DV as rating
    FROM phan_hoi_cua_khach_hang ph
    WHERE ph.ID_TK = '$customerId' AND ph.ID_DV = '$serviceId'
  )
  UNION ALL
  (
    SELECT 
      'appointment' as type,
      lh.THOI_GIAN_BAT_DAU as date,
      CONCAT('Lịch hẹn: ', lh.DIA_CHI_HEN) as description,
      NULL as rating
    FROM lich_hen lh
    WHERE lh.ID_TK = '$customerId' AND lh.ID_DV = '$serviceId'
  )
  ORDER BY date DESC
  LIMIT 20
";
$timeline = [];
$timelineResult = mysqli_query($conn, $timelineQuery);
while ($row = mysqli_fetch_assoc($timelineResult)) {
  $timeline[] = $row;
}

// ============ RESPONSE ============
$response = [
  'feedback' => $fbResult,
  'appointments' => $appointments,
  'assignments' => $assignments,
  'customer_detail' => $customerDetail,
  'staff_performance' => $staffPerformance,
  'timeline' => $timeline,
  'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
mysqli_close($conn);
?>
