<?php
session_start();
require_once __DIR__ . '/app/helpers/auth_background.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'];

    if ($code == $_SESSION['reset_code']) {
        header("Location: reset_password.php");
        exit();
    } else {
        $message = "Mã xác nhận không đúng.";
    }
}
// Cấu hình nền cho trang xác nhận mã (cập nhật đường dẫn/overlay nếu cần)
$authBodyAttributes = buildAuthBodyAttributes([
    'image'   => '',
    'overlay' => '',
    'blur'    => '',
]);
?>

<!-- ... phần xử lý PHP giữ nguyên ... -->

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Xác nhận mã</title>
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

    <h2 class="auth-heading">Nhập mã xác nhận</h2>

    <form method="POST" class="auth-form">
      <input type="text" name="code" placeholder="Nhập mã xác nhận" class="auth-form__field" required>

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