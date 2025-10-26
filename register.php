<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'C:/xampp/htdocs/StygianBlue/database/config.php'; // Đường dẫn chính xác đến config.php

// Caching & Security Headers
header("Cache-Control: private, no-store, no-cache, must-revalidate");
header("X-Content-Type-Options: nosniff");

$error = "";   // Initialize error variable
$success = ""; // Initialize success variable

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lấy dữ liệu từ biểu mẫu
    $inputUsername = $_POST['username'];
    $inputPassword = trim($_POST['password']);
    $inputFullName = $_POST['full_name'];
    $inputBirthDate = $_POST['birth_date'];
    $inputAddress = $_POST['address'];
    $inputEmail = $_POST['email'];
    $inputPhone = $_POST['phone'];
    $inputRole = 3; // Giả sử role khách hàng là 3

    // Kiểm tra các trường bắt buộc
    if (empty($inputUsername) || empty($inputPassword) || empty($inputFullName) || empty($inputEmail) || empty($inputPhone)) {
        $error = "Vui lòng điền đầy đủ thông tin.";
    } elseif (strlen($inputUsername) < 8 || strlen($inputUsername) > 16) {
        $error = "Mã đăng nhập phải từ 8 đến 16 ký tự.";
    } elseif (strlen($inputPassword) < 6 || strlen($inputPassword) > 22) {
        $error = "Mật khẩu phải từ 6 đến 22 ký tự."; 
    } elseif (!preg_match("/^0[0-9]{9,11}$/", $inputPhone)) {
        $error = "Số điện thoại không hợp lệ. Vui lòng nhập lại.";         
    } elseif (!filter_var($inputEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Địa chỉ email không hợp lệ.";
    } else {
        // Mã hóa mật khẩu
        $hashedPassword = password_hash($inputPassword, PASSWORD_DEFAULT);

        // Kiểm tra xem ID_TK đã tồn tại hay chưa
        $checkStmt = $conn->prepare("SELECT ID_TK FROM tai_khoan WHERE ID_TK = ?");
        $checkStmt->bind_param("s", $inputUsername);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult && $checkResult->num_rows > 0) {
            $error = "Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác.";
        } else {
            // Thêm tài khoản mới vào bảng TAI_KHOAN
            $stmt = $conn->prepare("INSERT INTO tai_khoan (ID_TK, ID_QUYEN, HO_TEN, NGAY_SINH, DIA_CHI, EMAIL, SDT, MAT_KHAU) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssssssss", $inputUsername, $inputRole, $inputFullName, $inputBirthDate, $inputAddress, $inputEmail, $inputPhone, $hashedPassword);
                if ($stmt->execute()) {
                    // Thêm vào bảng KHACH_HANG
                    $stmt2 = $conn->prepare("INSERT INTO khach_hang (ID_TK, ID_QUYEN, HO_TEN, NGAY_SINH, DIA_CHI, EMAIL, SDT, MAT_KHAU) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt2) {
                        $stmt2->bind_param("ssssssss", $inputUsername, $inputRole, $inputFullName, $inputBirthDate, $inputAddress, $inputEmail, $inputPhone, $hashedPassword);
                        if ($stmt2->execute()) {
                            $_SESSION['message'] = "Tạo tài khoản thành công!";
                            $_SESSION['message_type'] = "success";
                            // Chuyển hướng đến trang đăng nhập                           
                            if (empty($error)) {
                                header("Location: login.php");
                                exit();
                            }
                        } else {
                            $error = "Lỗi khi thêm vào bảng khách hàng: " . $stmt2->error;
                        }
                    } else {
                        $error = "Lỗi chuẩn bị câu lệnh thêm vào bảng khách hàng: " . $conn->error;
                    }
                } else {
                    $error = "Lỗi khi thêm vào bảng tài khoản: " . $stmt->error;
                }
            } else {
                $error = "Lỗi chuẩn bị câu lệnh thêm tài khoản: " . $conn->error;
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Đăng ký - Stygian Blue Studio</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet"/>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 0;
      font-family: 'Roboto', sans-serif;
      background: linear-gradient(135deg, #0B1C2C, #1e2d3f, #2c3e50);
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .register-container {
      background: rgba(255, 255, 255, 0.08);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 16px;
      padding: 40px;
      width: 380px;
      max-width: 90%;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
      color: #fff;
      text-align: center;
    }

    .register-container h1 {
      margin-bottom: 24px;
      font-size: 28px;
      font-weight: 700;
      color: #00b4d8;
    }

    form input {
      width: 100%;
      padding: 12px 14px;
      margin: 8px 0;
      border: 1px solid #ccc;
      border-radius: 8px;
      background-color: #0f2027;
      color: #fff;
      font-size: 15px;
      transition: border-color 0.3s, box-shadow 0.3s;
    }

    form input:focus {
      border-color: #00b4d8;
      box-shadow: 0 0 8px rgba(0, 180, 216, 0.4);
      outline: none;
    }

    form button {
      width: 100%;
      padding: 12px;
      margin-top: 16px;
      background-color: #00a8e8;
      border: none;
      border-radius: 8px;
      color: #fff;
      font-size: 16px;
      font-weight: bold;
      cursor: pointer;
      transition: background-color 0.3s, box-shadow 0.3s;
    }

    form button:hover {
      background-color: #0077b6;
      box-shadow: 0 0 10px #00b4d8;
    }

    .error {
      color: #ff6b6b;
      margin-top: 12px;
    }

    .success {
      color: #32cd99;
      margin-top: 12px;
    }

    .login-link {
      display: block;
      margin-top: 20px;
      color: #ccc;
      font-size: 14px;
      text-decoration: none;
    }

    .login-link:hover {
      text-decoration: underline;
      color: #fff;
    }
  </style>
</head>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Đăng ký - Stygian Blue Studio</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    body {
      background: linear-gradient(to bottom right, #e6f0ff, #cfd9e8);
      font-family: 'Segoe UI', sans-serif;
    }
    .glass {
      backdrop-filter: blur(12px);
      background-color: rgba(255, 255, 255, 0.7);
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    }
    .input-style {
      background-color: #f4f6f9;
    }
    .input-style:focus {
      border-color: #2563eb;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
    }
  </style>
</head>
<body class="flex items-center justify-center min-h-screen px-4">

  <div class="w-full max-w-md glass p-8 rounded-xl border border-blue-100">
    <div class="flex justify-center mb-6">
      <img src="public/images/logo5.png" alt="Logo" class="h-20 w-20 rounded-lg shadow-md">
    </div>
    <h2 class="text-2xl font-bold text-center text-blue-800 mb-6">Đăng ký tài khoản</h2>

    <form method="POST" action="" class="space-y-4">
      <input type="text" name="full_name" placeholder="Họ tên" required
        class="w-full px-4 py-2 rounded-lg border input-style text-gray-800 focus:outline-none" />
      <input type="text" name="username" placeholder="Mã đăng nhập" required
        class="w-full px-4 py-2 rounded-lg border input-style text-gray-800 focus:outline-none" />
      <input type="email" name="email" placeholder="Email" required
        class="w-full px-4 py-2 rounded-lg border input-style text-gray-800 focus:outline-none" />
      <input type="text" name="address" placeholder="Địa chỉ" required
        class="w-full px-4 py-2 rounded-lg border input-style text-gray-800 focus:outline-none" />
      <input type="tel" name="phone" placeholder="Số điện thoại" required
        class="w-full px-4 py-2 rounded-lg border input-style text-gray-800 focus:outline-none" />
      <input type="password" name="password" placeholder="Mật khẩu" required
        class="w-full px-4 py-2 rounded-lg border input-style text-gray-800 focus:outline-none" />
      <input type="date" name="birth_date" required
        class="w-full px-4 py-2 rounded-lg border input-style text-gray-800 focus:outline-none" />

      <button type="submit"
        class="w-full py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold transition">
        Đăng ký
      </button>

      <?php if (!empty($error)) : ?>
        <div class="text-sm text-red-600 font-medium mt-2"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (!empty($success)) : ?>
        <div class="text-sm text-green-600 font-medium mt-2"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
    </form>

    <div class="mt-6 text-center text-sm text-gray-600">
      Đã có tài khoản?
      <a href="login.php" class="text-blue-700 hover:underline font-semibold">Đăng nhập</a>
    </div>
  </div>
</body>
</html>