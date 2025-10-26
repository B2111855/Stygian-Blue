<?php
include '../../../database/config.php'; // Kết nối CSDL
include '../Controller/taikhoan.php'; // Hàm getUserInfo

// Lấy ID_TK từ session
$userId = $_SESSION['ID_TK'];

// Lấy thông tin người dùng hiện tại
$userInfo = getUserInfo($conn);

$stmt = $conn->prepare("SELECT ID_TK, HO_TEN, NGAY_SINH, DIA_CHI, EMAIL, SDT, MAT_KHAU FROM TAI_KHOAN WHERE ID_TK = ?");
if ($stmt === false) {
    die("Lỗi câu truy vấn SQL: " . $conn->error);  // Kiểm tra lỗi câu truy vấn
}
$stmt->bind_param("s", $userId); // Gắn ID_TK vào câu truy vấn
$stmt->execute();
$stmt->bind_result($idTk, $hoTen, $ngaySinh, $diaChi, $email, $sdt, $hashedPassword);
$stmt->fetch(); // Lấy thông tin người dùng
$stmt->close();

// Xử lý cập nhật thông tin khi người dùng gửi form
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['current_password'])) {
    // Lấy thông tin từ form
    $inputFullName = trim($_POST['full_name']);
    $inputBirthDate = trim($_POST['birth_date']);
    $inputAddress = trim($_POST['address']);
    $inputEmail = trim($_POST['email']);
    $inputPhone = trim($_POST['phone']);

    // Kiểm tra thông tin hợp lệ
    if (empty($inputFullName) || empty($inputEmail) || empty($inputPhone)) {
        $error = "Vui lòng điền đầy đủ thông tin.";
    } elseif (!filter_var($inputEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Địa chỉ email không hợp lệ.";
    } elseif (!preg_match('/^[0-9]{10,12}$/', $inputPhone)) {
        $error = "Số điện thoại không hợp lệ.";
    } else {
        // Kiểm tra xem ID_TK từ session có hợp lệ không
        if (empty($userId)) {
            die("Lỗi: Không tìm thấy ID_TK người dùng.");
        }

        // Cập nhật thông tin người dùng (không thay đổi ID_TK)
        $updateStmt = $conn->prepare("UPDATE TAI_KHOAN SET HO_TEN = ?, NGAY_SINH = ?, DIA_CHI = ?, EMAIL = ?, SDT = ? WHERE ID_TK = ?");
        $updateStmt->bind_param("ssssss", $inputFullName, $inputBirthDate, $inputAddress, $inputEmail, $inputPhone, $userId);
        if ($updateStmt->execute()) {
            $success = "Cập nhật thông tin thành công.";
        
            // Gọi lại thông tin mới từ database để hiển thị lên form
            $stmt = $conn->prepare("SELECT ID_TK, HO_TEN, NGAY_SINH, DIA_CHI, EMAIL, SDT FROM TAI_KHOAN WHERE ID_TK = ?");
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $stmt->bind_result($idTk, $hoTen, $ngaySinh, $diaChi, $email, $sdt);
            $stmt->fetch();
            $stmt->close();
        } else {
            $error = "Lỗi khi cập nhật thông tin: " . $updateStmt->error;
        }
        $updateStmt->close();
    }
}

// Lấy mật khẩu từ database (sử dụng sau khi người dùng xác nhận muốn thay đổi mật khẩu)
$stmt = $conn->prepare("SELECT MAT_KHAU FROM TAI_KHOAN WHERE ID_TK = ?");
$stmt->bind_param("s", $userId);  // ID_TK là kiểu varchar
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $hashedPassword = $row['MAT_KHAU']; // Lấy mật khẩu mã hóa từ DB
} else {
    $error = "Không tìm thấy tài khoản.";
}
$stmt->close();

// Xử lý đổi mật khẩu
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['current_password'])) {
    $currentPassword = trim($_POST['current_password']);
    $newPassword = trim($_POST['new_password']);
    $confirmNewPassword = trim($_POST['confirm_new_password']);

    // Kiểm tra mật khẩu
    if (empty($currentPassword) || empty($newPassword) || empty($confirmNewPassword)) {
        $error = "Vui lòng điền đầy đủ thông tin.";
    } elseif ($newPassword !== $confirmNewPassword) {
        $error = "Mật khẩu mới không khớp.";
    } elseif (strlen($newPassword) < 6 || strlen($newPassword) > 22) {
        $error = "Mật khẩu mới phải từ 6 đến 22 ký tự.";
    } else {
        // Kiểm tra mật khẩu hiện tại
        if (password_verify($currentPassword, $hashedPassword)) {
            // Mã hóa mật khẩu mới
            $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            // Cập nhật mật khẩu mới
            $updateStmt = $conn->prepare("UPDATE TAI_KHOAN SET MAT_KHAU = ? WHERE ID_TK = ?");
            $updateStmt->bind_param("ss", $hashedNewPassword, $userId);
            if ($updateStmt->execute()) {
                $success = "Đổi mật khẩu thành công.";
            } else {
                $error = "Lỗi khi đổi mật khẩu: " . $updateStmt->error;
            }
            $updateStmt->close();
        } else {
            $error = "Mật khẩu hiện tại không đúng.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông tin người dùng</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function togglePasswordModal() {
            document.getElementById('password-modal').classList.toggle('hidden');
        }
    </script>
</head>

<body class="bg-gradient-to-r from-indigo-600 via-blue-500 to-indigo-400 min-h-screen flex items-center justify-center">
    <div class="max-w-4xl mx-auto mt-6 bg-gray-800 text-white p-8 rounded-xl shadow-lg transform transition-all hover:scale-105 hover:shadow-lg">
        <h1 class="text-3xl font-extrabold mb-6 text-center">Thông tin người dùng</h1>

        <!-- Thông báo -->
        <?php if (isset($error)): ?>
            <div class="bg-red-500 text-white p-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="bg-green-500 text-white p-3 rounded-lg mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Form thông tin -->
        <form method="POST" action="">
            <div class="space-y-4">
                <div class="flex items-center space-x-4">
                    <div class="w-1/3">
                        <label for="id_tk" class="block font-medium text-gray-300">Mã Tài Khoản</label>
                        <input type="text" id="id_tk" name="id_tk" value="<?php echo htmlspecialchars($idTk); ?>" 
                            class="mt-1 block w-full bg-gray-600 border border-gray-500 rounded-md py-2 px-3" readonly>
                    </div>
                    <div class="w-2/3">
                        <label for="ho_ten" class="block font-medium text-gray-300">Họ tên</label>
                        <input type="text" id="ho_ten" name="full_name" value="<?php echo htmlspecialchars($hoTen); ?>" 
                            class="mt-1 block w-full bg-gray-700 border border-gray-500 rounded-md py-2 px-3 focus:ring focus:ring-blue-300 transition-all duration-300 ease-in-out">
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="w-1/3">
                        <label for="ngay_sinh" class="block font-medium text-gray-300">Ngày sinh</label>
                        <input type="date" id="ngay_sinh" name="birth_date" value="<?php echo htmlspecialchars($ngaySinh); ?>" 
                            class="mt-1 block w-full bg-gray-700 border border-gray-500 rounded-md py-2 px-3 focus:ring focus:ring-blue-300 transition-all duration-300 ease-in-out">
                    </div>
                    <div class="w-2/3">
                        <label for="dia_chi" class="block font-medium text-gray-300">Địa chỉ</label>
                        <input type="text" id="dia_chi" name="address" value="<?php echo htmlspecialchars($diaChi); ?>" 
                            class="mt-1 block w-full bg-gray-700 border border-gray-500 rounded-md py-2 px-3 focus:ring focus:ring-blue-300 transition-all duration-300 ease-in-out">
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="w-1/3">
                        <label for="email" class="block font-medium text-gray-300">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" 
                            class="mt-1 block w-full bg-gray-700 border border-gray-500 rounded-md py-2 px-3 focus:ring focus:ring-blue-300 transition-all duration-300 ease-in-out">
                    </div>
                    <div class="w-2/3">
                        <label for="sdt" class="block font-medium text-gray-300">Số điện thoại</label>
                        <input type="text" id="sdt" name="phone" value="<?php echo htmlspecialchars($sdt); ?>" 
                            class="mt-1 block w-full bg-gray-700 border border-gray-500 rounded-md py-2 px-3 focus:ring focus:ring-blue-300 transition-all duration-300 ease-in-out">
                    </div>
                </div>
            </div>

            <!-- Các nút hành động -->
            <div class="flex justify-between mt-6">
                <button type="button" onclick="togglePasswordModal()" 
                    class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-md shadow-lg transform transition-all hover:scale-105 focus:outline-none">Đổi mật khẩu</button>
                <button type="submit" 
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-lg transform transition-all hover:scale-105 focus:outline-none">Cập nhật thông tin</button>
            </div>
        </form>

        <!-- Modal đổi mật khẩu -->
        <div id="password-modal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center">
            <div class="bg-gray-800 text-white p-8 rounded-xl w-96">
                <h2 class="text-2xl font-semibold mb-4">Đổi mật khẩu</h2>
                <form method="POST">
                    <div class="mb-4">
                        <label for="current_password" class="block text-sm">Mật khẩu hiện tại</label>
                        <input type="password" id="current_password" name="current_password" 
                            class="mt-1 block w-full bg-gray-600 border border-gray-500 rounded-md py-2 px-3">
                    </div>
                    <div class="mb-4">
                        <label for="new_password" class="block text-sm">Mật khẩu mới</label>
                        <input type="password" id="new_password" name="new_password" 
                            class="mt-1 block w-full bg-gray-600 border border-gray-500 rounded-md py-2 px-3">
                    </div>
                    <div class="mb-4">
                        <label for="confirm_new_password" class="block text-sm">Xác nhận mật khẩu mới</label>
                        <input type="password" id="confirm_new_password" name="confirm_new_password" 
                            class="mt-1 block w-full bg-gray-600 border border-gray-500 rounded-md py-2 px-3">
                    </div>
                    <div class="flex justify-between">
                        <button type="submit" 
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md focus:outline-none">Đổi mật khẩu</button>
                        <button type="button" onclick="togglePasswordModal()" 
                            class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md focus:outline-none">Đóng</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>
