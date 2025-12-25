<?php
session_start();
require './database/config.php';
require './vendor/autoload.php';
require_once __DIR__ . '/app/helpers/auth_background.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8');
    
    // Kiểm tra xem email có hợp lệ hay không
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email không hợp lệ. Vui lòng nhập đúng định dạng email.";
    } else {
        $stmt = $conn->prepare("SELECT ID_TK FROM tai_khoan WHERE EMAIL = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $code = rand(100000, 999999);
            $_SESSION['reset_code'] = $code;
            $_SESSION['reset_email'] = $email;

            // Gửi mail
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'trongnghiann4911@gmail.com'; // Thay bằng Gmail thật
                $mail->Password   = 'boyw rfke ahjp trlx';    // Mật khẩu ứng dụng
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('trongnghiann4911@gmail.com', 'Stygian Blue Studio');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';  // Đảm bảo mã hóa UTF-8 cho tiêu đề và nội dung
                $mail->Subject = 'Yêu cầu đặt lại mật khẩu';
                $mail->Body    = "<p>Xin chào,</p>
                                  <p>Mã xác nhận của bạn là: <strong style='font-size: 20px;'>$code</strong></p>
                                  <p>Vui lòng nhập mã này để xác thực và tiếp tục quá trình đặt lại mật khẩu.</p>
                                  <p>Thân mến,<br>Stygian Blue Studio</p>";

                $mail->send();
                header("Location: verify_code.php");
                exit();
            } catch (Exception $e) {
                $message = "Không thể gửi email. Lỗi: {$mail->ErrorInfo}";
            }
        } else {
            $message = "Không tìm thấy email này trong hệ thống.";
        }
    }
}

// Cấu hình nền cho trang quên mật khẩu (cập nhật đường dẫn/overlay nếu cần)
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
  <title>Quên mật khẩu</title>
  <link rel="stylesheet" href="public/css/auth.css">
</head>
<body <?= $authBodyAttributes ?>>
  <!-- Để hiển thị ảnh nền, thiết lập giá trị cho --auth-background-image trong thuộc tính style của thẻ body -->
  <div class="auth-card">
    <div class="auth-card__logo">
      <a href="./app/Pages/Views/home.php" title="Về trang chủ">
        <img src="public/images/StygianBlueLogo.png" alt="Logo Stygian Blue">
      </a>
    </div>

    <h2 class="auth-heading">Quên mật khẩu</h2>

    <form method="POST" class="auth-form">
      <input type="email" name="email" placeholder="Nhập email đăng ký" class="auth-form__field" required>

      <button type="submit" class="auth-form__button">
        Gửi mã xác nhận
      </button>
    </form>

    <?php if (!empty($message)) : ?>
      <p class="auth-form__message auth-form__message--error"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <div class="auth-helper">
      <a href="login.php" class="auth-helper__link">Quay lại trang đăng nhập</a>
    </div>
  </div>
</body>
</html>