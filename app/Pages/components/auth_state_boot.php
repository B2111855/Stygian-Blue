<?php
// auth_state_boot.php
// Gọi file này TRƯỚC KHI echo bất kỳ HTML nào

// 1. Khởi tạo session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Kết nối DB (nếu trang trước đó chưa include)
require_once '../../../database/config.php';

if (!function_exists('sbSyncLoginState')) {
    function sbSyncLoginState(): bool
    {
        $loggedIn = !empty($_SESSION['ID_TK']);

        if (!headers_sent()) {
            if ($loggedIn) {
                setcookie('sb_logged_in', '1', 0, '/');
            } else {
                setcookie('sb_logged_in', '', time() - 3600, '/');
            }
        }

        return $loggedIn;
    }
}

// 3. Xác định trạng thái đăng nhập
$isLoggedIn = sbSyncLoginState();
$redirect   = urlencode($_SERVER['REQUEST_URI'] ?? '/');

// 4. Dữ liệu header cần (trước đây header tự làm -> bây giờ boot làm, header chỉ render)
$unpaidCount = 0;
$scheduleUpdateCount = 0;
$scheduleLatestNote = '';
if ($isLoggedIn) {
    $userId = mysqli_real_escape_string($conn, $_SESSION['ID_TK']);
    $query = "
        SELECT COUNT(*) AS count
        FROM hoa_don h
        JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
        WHERE h.TRANGTHAI_THANHTOAN = 'Chưa thanh toán'
          AND l.ID_TK = '$userId'
    ";
    if ($result = mysqli_query($conn, $query)) {
        if ($row = mysqli_fetch_assoc($result)) {
            $unpaidCount = (int)($row['count'] ?? 0);
        }
        mysqli_free_result($result);
    }

    $scheduleLogQuery = "
        SELECT COUNT(*) AS count
        FROM trang_thai_lich_hen_log log
        JOIN lich_hen l ON log.ID_LICHHEN = l.ID_LICHHEN
        WHERE l.ID_TK = '$userId'
          AND log.THOI_DIEM >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    "
    ;

    if ($logResult = mysqli_query($conn, $scheduleLogQuery)) {
        if ($logRow = mysqli_fetch_assoc($logResult)) {
            $scheduleUpdateCount = (int)($logRow['count'] ?? 0);
        }
        mysqli_free_result($logResult);
    }

    if ($scheduleUpdateCount > 0) {
        $latestLogQuery = "
            SELECT log.ID_LICHHEN, log.TRANG_THAI_MOI, log.THOI_DIEM, log.GHI_CHU,
                   dv.TEN_DV, gdv.TEN_GOI
            FROM trang_thai_lich_hen_log log
            JOIN lich_hen l ON log.ID_LICHHEN = l.ID_LICHHEN
            LEFT JOIN dich_vu dv ON dv.ID_DV = l.ID_DV
            LEFT JOIN goi_dich_vu gdv ON gdv.ID_GOI = l.ID_GOI
            WHERE l.ID_TK = '$userId'
              AND log.THOI_DIEM >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY log.THOI_DIEM DESC
            LIMIT 1
        "
        ;

        if ($latestResult = mysqli_query($conn, $latestLogQuery)) {
            if ($latest = mysqli_fetch_assoc($latestResult)) {
                $label = $latest['TEN_GOI'] ?? '';
                if ($label === '') {
                    $label = $latest['TEN_DV'] ?? '';
                }
                if ($label === '' && !empty($latest['ID_LICHHEN'])) {
                    $label = 'Lịch hẹn #' . (int)$latest['ID_LICHHEN'];
                }

                $status = $latest['TRANG_THAI_MOI'] ?? 'Đã cập nhật';
                $timeStr = '';
                if (!empty($latest['THOI_DIEM'])) {
                    $time = date_create($latest['THOI_DIEM']);
                    if ($time) {
                        $timeStr = date_format($time, 'd/m H:i');
                    }
                }

                $noteParts = array_filter([$label, $status, $timeStr], static function ($part) {
                    return is_string($part) ? trim($part) !== '' : !empty($part);
                });

                if (!empty($latest['GHI_CHU'])) {
                    $noteParts[] = trim($latest['GHI_CHU']);
                }

                $scheduleLatestNote = implode(' • ', array_map('trim', $noteParts));
            }
            mysqli_free_result($latestResult);
        }
    }
}

// Lấy danh sách dịch vụ / combo cho menu
$menuServices = $conn->query("
    SELECT ID_DV, TEN_DV
    FROM dich_vu
    ORDER BY TEN_DV ASC
    LIMIT 12
");

$menuCombos = $conn->query("
    SELECT ID_GOI, TEN_GOI
    FROM goi_dich_vu
    WHERE TRANG_THAI = 'ban'
      AND (HIEU_LUC_TU IS NULL OR HIEU_LUC_TU <= NOW())
      AND (HIEU_LUC_DEN IS NULL OR HIEU_LUC_DEN >= NOW())
    ORDER BY TEN_GOI ASC
    LIMIT 12
");

// Bây giờ các biến sau đây đã tồn tại cho header.php dùng:
// $isLoggedIn, $redirect, $unpaidCount, $scheduleUpdateCount, $scheduleLatestNote, $menuServices, $menuCombos
