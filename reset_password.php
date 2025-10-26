<?php
session_start();
require './database/config.php';

$message = '';

// Kiểm tra xem biến session 'reset_email' đã được thiết lập chưa
if (!isset($_SESSION['reset_email'])) {
    // Nếu chưa, chuyển hướng người dùng đến trang yêu cầu đặt lại mật khẩu
    header('Location: reset_request.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // Kiểm tra độ dài mật khẩu
    if (strlen($new_pass) < 8) {
        $message = "Mật khẩu mới phải có ít nhất 8 ký tự.";
    } 
    // Kiểm tra mật khẩu mới và mật khẩu xác nhận có giống nhau không
    elseif ($new_pass !== $confirm_pass) {
        $message = "Mật khẩu xác nhận không khớp.";
    } 
    else {
        // Mã hóa mật khẩu và cập nhật vào cơ sở dữ liệu
        $new_pass_hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $email = $_SESSION['reset_email'];

        $stmt = $conn->prepare("UPDATE tai_khoan SET MAT_KHAU = ? WHERE EMAIL = ?");
        $stmt->bind_param("ss", $new_pass_hashed, $email);

        if ($stmt->execute()) {
            // Đặt lại mật khẩu thành công, lưu thông báo vào session
            $_SESSION['message'] = "Mật khẩu của bạn đã được thay đổi thành công. Vui lòng đăng nhập!";
            $_SESSION['message_type'] = "success";
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_code']);
            header("Location: login.php");  // Chuyển hướng tới trang đăng nhập
            exit();
        } else {
            $message = "Có lỗi xảy ra, vui lòng thử lại.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đặt lại mật khẩu</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-purple-100 via-indigo-100 to-blue-100">

    <div class="bg-white/90 backdrop-blur-lg shadow-xl rounded-xl p-8 w-full max-w-md border border-purple-200">
        <!-- Logo -->
        <div class="flex justify-center mb-6">
            <img src="public/images/logo5.png" alt="Logo Stygian Blue" class="w-16 h-16 rounded-lg shadow-md">
        </div>

        <h2 class="text-2xl font-bold text-center text-purple-700 mb-6">🔒 Đặt lại mật khẩu</h2>

        <form method="POST" class="space-y-4">
            <input type="password" name="new_password" placeholder="Mật khẩu mới"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" required>

            <input type="password" name="confirm_password" placeholder="Xác nhận mật khẩu"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" required>

            <button type="submit"
                    class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg transition font-semibold shadow">
                Xác nhận
            </button>
        </form>

        <?php if (!empty($message)): ?>
            <p class="mt-4 text-red-600 text-center font-medium"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <div class="text-center mt-6">
            <a href="login.php" class="text-indigo-600 hover:underline text-sm">⬅ Quay lại đăng nhập</a>
        </div>
    </div>
</body>
</html>

