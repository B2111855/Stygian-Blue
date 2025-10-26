<?php
session_start(); // Bắt đầu phiên làm việc

// Xóa tất cả các biến session
$_SESSION = array();

// Nếu muốn xóa cookie phiên, thiết lập thời gian hết hạn trong quá khứ
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Cuối cùng, hủy phiên
session_destroy();

// Điều hướng đến trang đăng nhập
header("Location: ../../../login.php");
exit();
?>