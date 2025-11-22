<?php
/**
 * File: get_notification_counts.php
 * Mục đích: Lấy số lượng thông báo cho các menu trong sidebar
 * - Lịch hẹn cần xác nhận (TRANGTHAI = 'Đang chờ')
 * - Lịch hẹn cần phân công (đã xác nhận nhưng chưa có phân công)
 * - Yêu cầu đổi lịch làm việc (TRANGTHAI = 'Chờ duyệt')
 * - Lịch hẹn mới được phân công cho nhân viên (trong 7 ngày gần đây)
 */

if (!isset($conn)) {
    require_once __DIR__ . '/../../../database/config.php';
}

/**
 * Lấy số lượng thông báo cho Manager (theo chi nhánh)
 * @param mysqli $conn Database connection
 * @param int $branchId ID chi nhánh
 * @return array Array chứa các số lượng thông báo
 */
function getManagerNotificationCounts($conn, $branchId) {
    $counts = [
        'appointments_pending' => 0,      // Lịch hẹn cần xác nhận
        'appointments_need_assignment' => 0, // Lịch hẹn cần phân công
        'schedule_change_requests' => 0   // Yêu cầu đổi lịch làm việc
    ];
    
    // 1. Đếm lịch hẹn cần xác nhận (trạng thái "Đang chờ")
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM lich_hen
        WHERE ID_CHINHANH = ? AND TRANGTHAI = 'Đang chờ'
    ");
    if ($stmt) {
        $stmt->bind_param('i', $branchId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $counts['appointments_pending'] = (int)$row['total'];
        }
        $stmt->close();
    }
    
    // 2. Đếm lịch hẹn đã xác nhận nhưng chưa có phân công
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM lich_hen lh
        WHERE lh.ID_CHINHANH = ? 
          AND lh.TRANGTHAI = 'Đã xác nhận'
          AND NOT EXISTS (
              SELECT 1 FROM phan_cong_nhan_vien pc 
              WHERE pc.ID_LICHHEN = lh.ID_LICHHEN
          )
    ");
    if ($stmt) {
        $stmt->bind_param('i', $branchId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $counts['appointments_need_assignment'] = (int)$row['total'];
        }
        $stmt->close();
    }
    
    // 3. Đếm yêu cầu đổi lịch làm việc đang chờ duyệt
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM yeu_cau_thay_doi_lich yc
        JOIN lich_hen lh ON yc.ID_LICHHEN = lh.ID_LICHHEN
        WHERE lh.ID_CHINHANH = ? AND yc.TRANGTHAI = 'Chờ duyệt'
    ");
    if ($stmt) {
        $stmt->bind_param('i', $branchId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $counts['schedule_change_requests'] = (int)$row['total'];
        }
        $stmt->close();
    }
    
    return $counts;
}

/**
 * Lấy số lượng thông báo cho Admin (toàn hệ thống)
 * @param mysqli $conn Database connection
 * @return array Array chứa các số lượng thông báo
 */
function getAdminNotificationCounts($conn) {
    $counts = [
        'appointments_pending' => 0,      // Lịch hẹn cần xác nhận
        'appointments_need_assignment' => 0, // Lịch hẹn cần phân công
        'schedule_change_requests' => 0   // Yêu cầu đổi lịch làm việc (tùy chọn cho admin)
    ];
    
    // 1. Đếm lịch hẹn cần xác nhận (trạng thái "Đang chờ")
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM lich_hen
        WHERE TRANGTHAI = 'Đang chờ'
    ");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $counts['appointments_pending'] = (int)$row['total'];
        }
        $stmt->close();
    }
    
    // 2. Đếm lịch hẹn đã xác nhận nhưng chưa có phân công
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM lich_hen lh
        WHERE lh.TRANGTHAI = 'Đã xác nhận'
          AND NOT EXISTS (
              SELECT 1 FROM phan_cong_nhan_vien pc 
              WHERE pc.ID_LICHHEN = lh.ID_LICHHEN
          )
    ");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $counts['appointments_need_assignment'] = (int)$row['total'];
        }
        $stmt->close();
    }
    
    // 3. Đếm yêu cầu đổi lịch làm việc đang chờ duyệt (admin có thể xem tất cả)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM yeu_cau_thay_doi_lich
        WHERE TRANGTHAI = 'Chờ duyệt'
    ");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $counts['schedule_change_requests'] = (int)$row['total'];
        }
        $stmt->close();
    }
    
    return $counts;
}

/**
 * Lấy số lượng thông báo cho Staff (nhân viên)
 * @param mysqli $conn Database connection
 * @param string $staffAccountId ID tài khoản nhân viên
 * @return array Array chứa các số lượng thông báo
 */
function getStaffNotificationCounts($conn, $staffAccountId) {
    $counts = [
        'new_assignments' => 0  // Lịch hẹn mới được phân công (trong 7 ngày gần đây)
    ];
    
    // Đếm lịch hẹn mới được phân công trong 7 ngày gần đây
    // và thời gian bắt đầu phân công chưa tới (trong tương lai)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM phan_cong_nhan_vien pc
        JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
        WHERE pc.ID_TK = ?
          AND pc.THOI_GIAN_BAT_DAU >= NOW()
          AND lh.TRANGTHAI IN ('Đã xác nhận', 'Đang thực hiện')
          AND pc.THOI_GIAN_BAT_DAU >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    if ($stmt) {
        $stmt->bind_param('s', $staffAccountId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $counts['new_assignments'] = (int)$row['total'];
        }
        $stmt->close();
    }
    
    return $counts;
}

/**
 * Render badge thông báo HTML
 * @param int $count Số lượng thông báo
 * @param string $colorClass Class màu của badge (mặc định: bg-red-500)
 * @return string HTML của badge
 */
function renderNotificationBadge($count, $colorClass = 'bg-red-500') {
    if ($count <= 0) {
        return '';
    }
    
    $displayCount = $count > 99 ? '99+' : $count;
    
    return sprintf(
        '<span class="ml-auto inline-flex items-center justify-center min-w-[1.5rem] h-6 px-2 text-xs font-bold text-white %s rounded-full ring-2 ring-white/30">%s</span>',
        htmlspecialchars($colorClass, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($displayCount, ENT_QUOTES, 'UTF-8')
    );
}
