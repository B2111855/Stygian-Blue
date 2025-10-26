<?php
// auth_state_boot.php
// Gọi file này TRƯỚC KHI echo bất kỳ HTML nào

// 1. Khởi tạo session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Kết nối DB (nếu trang trước đó chưa include)
require_once '../../../database/config.php';

// 3. Xác định trạng thái đăng nhập
$isLoggedIn = !empty($_SESSION['ID_TK']); // bạn đang dùng ID_TK trong session
$redirect   = urlencode($_SERVER['REQUEST_URI'] ?? '/');

// 4. Đồng bộ cookie để JS có thể biết trạng thái login "thời điểm hiện tại"
if ($isLoggedIn) {
    setcookie('sb_logged_in', '1', 0, '/');
} else {
    setcookie('sb_logged_in', '', time() - 3600, '/');
}

// 5. Dữ liệu header cần (trước đây header tự làm -> bây giờ boot làm, header chỉ render)
$unpaidCount = 0;
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
// $isLoggedIn, $redirect, $unpaidCount, $menuServices, $menuCombos
