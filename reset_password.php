<?php
session_start();
require './database/config.php';
require_once __DIR__ . '/app/helpers/auth_background.php';

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

// Cấu hình nền cho trang đặt lại mật khẩu (cập nhật đường dẫn/overlay nếu cần)
$authBodyAttributes = buildAuthBodyAttributes([
    'image'   => '',
    'overlay' => '',
    'blur'    => '',
]);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Đặt lại mật khẩu</title>
  <link rel="stylesheet" href="public/css/auth.css">
</head>
<body <?= $authBodyAttributes ?>>
  <!-- Để hiển thị ảnh nền, thiết lập giá trị cho --auth-background-image trong thuộc tính style của thẻ body -->
  <div class="auth-card">
    <div class="auth-card__logo">
      <a href="./app/Pages/Views/home.php" title="Về trang chủ">
        <img src="public/images/logo5.png" alt="Logo Stygian Blue">
      </a>
    </div>

    <h2 class="auth-heading">Đặt lại mật khẩu</h2>

    <form method="POST" class="auth-form">
      <input type="password" name="new_password" placeholder="Mật khẩu mới" class="auth-form__field" required>
      <input type="password" name="confirm_password" placeholder="Xác nhận mật khẩu" class="auth-form__field" required>

      <button type="submit" class="auth-form__button">
        Xác nhận
      </button>
    </form>

    <?php if (!empty($message)): ?>
      <p class="auth-form__message auth-form__message--error"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <div class="auth-helper">
      <a href="login.php" class="auth-helper__link">Quay lại đăng nhập</a>
    </div>
  </div>
</body>
</html>