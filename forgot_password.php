<?php
session_start();
require './database/config.php';
require './vendor/autoload.php';

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
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Quên mật khẩu</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-blue-100 to-indigo-200">
  <div class="bg-white/80 backdrop-blur-lg shadow-xl rounded-xl p-8 w-full max-w-md border border-indigo-200 relative">
    
    <!-- Logo -->
    <div class="flex justify-center mb-6">
      <img src="public/images/logo5.png" alt="Logo Stygian Blue" class="w-16 h-16 rounded-lg shadow-md">
    </div>

    <h2 class="text-2xl font-bold text-center text-indigo-700 mb-4">🔐 Quên mật khẩu</h2>

    <form method="POST" class="space-y-4">
      <input type="email" name="email" placeholder="Nhập email đăng ký"
             class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400" required>

      <button type="submit"
              class="w-full bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700 transition font-semibold shadow">
        Gửi mã xác nhận
      </button>
    </form>

    <?php if (!empty($message)) : ?>
      <p class="mt-4 text-red-600 text-center font-medium"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <!-- Link quay về -->
    <div class="text-center mt-6">
      <a href="login.php" class="text-indigo-600 hover:underline hover:text-indigo-800 transition text-sm">
        ⬅ Quay lại trang đăng nhập
      </a>
    </div>
  </div>
</body>
</html>
