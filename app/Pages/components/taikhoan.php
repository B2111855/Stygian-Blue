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

// Tạo dữ liệu hiển thị bổ sung cho phần giao diện
$displayName = !empty($hoTen) ? htmlspecialchars($hoTen) : 'Người dùng';
$fallbackInitials = 'TK';
$initials = $fallbackInitials;

if (!empty($hoTen)) {
    $parts = preg_split('/\s+/u', trim($hoTen));
    if ($parts !== false && count($parts) > 0) {
        $first = $parts[0];
        $last = $parts[count($parts) - 1];
        if (function_exists('mb_substr')) {
            $firstInitial = mb_substr($first, 0, 1, 'UTF-8');
            $lastInitial = mb_substr($last, 0, 1, 'UTF-8');
        } else {
            $firstInitial = substr($first, 0, 1);
            $lastInitial = substr($last, 0, 1);
        }
        $initials = strtoupper($firstInitial . $lastInitial);
    }
}

$maskedEmail = !empty($email) ? htmlspecialchars($email) : 'Chưa cập nhật';
$maskedPhone = !empty($sdt) ? htmlspecialchars($sdt) : 'Chưa cập nhật';
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông tin người dùng</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function togglePasswordModal() {
            const modal = document.getElementById('password-modal');
            const overlay = document.getElementById('password-overlay');
            if (!modal || !overlay) {
                return;
            }
            modal.classList.toggle('hidden');
            overlay.classList.toggle('hidden');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const overlay = document.getElementById('password-overlay');
            if (overlay) {
                overlay.addEventListener('click', togglePasswordModal);
            }
        });
    </script>
</head>

<body class="bg-gradient-to-br from-sky-100 via-white to-indigo-50 min-h-screen py-10 text-slate-700">
    <div class="max-w-6xl mx-auto px-6">
        <div class="bg-gradient-to-br from-sky-300 via-indigo-300 to-purple-300 rounded-3xl shadow-xl p-px">
            <div class="bg-white/90 backdrop-blur-sm rounded-[calc(1.5rem-4px)] p-8 md:p-12">
                <div class="flex flex-col gap-8 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center gap-5">
                        <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-white text-3xl font-semibold text-indigo-500 shadow-inner">
                            <?php echo htmlspecialchars($initials); ?>
                        </div>
                        <div>
                            <p class="text-sm uppercase tracking-widest text-indigo-500/70">Tài khoản của bạn</p>
                            <h1 class="text-3xl font-bold mt-1 text-slate-800"><?php echo $displayName; ?></h1>
                            <div class="mt-2 flex flex-wrap gap-4 text-sm text-slate-500">
                                <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-emerald-400"></span><?php echo $maskedEmail; ?></span>
                                <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-sky-400"></span><?php echo $maskedPhone; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="togglePasswordModal()"
                            class="inline-flex items-center gap-2 rounded-xl border border-indigo-300 bg-white/80 px-5 py-3 text-sm font-semibold text-indigo-600 shadow-sm transition hover:-translate-y-0.5 hover:bg-indigo-100">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                <path d="M12 1.5a5.25 5.25 0 00-5.25 5.25v2.25H6a3 3 0 00-3 3V18a4.5 4.5 0 004.5 4.5h9A4.5 4.5 0 0021 18v-6a3 3 0 00-3-3h-.75V6.75A5.25 5.25 0 0012 1.5zm-3.75 5.25a3.75 3.75 0 117.5 0v2.25h-7.5V6.75z" />
                            </svg>
                            Đổi mật khẩu
                        </button>
                        <a href="/logout.php" class="inline-flex items-center gap-2 rounded-xl bg-white/80 px-5 py-3 text-sm font-semibold text-rose-500 shadow-sm transition hover:-translate-y-0.5 hover:bg-rose-100 hover:text-rose-600">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                <path fill-rule="evenodd" d="M4.5 3A1.5 1.5 0 003 4.5v15A1.5 1.5 0 004.5 21h9a1.5 1.5 0 001.5-1.5V18a.75.75 0 00-1.5 0v1.5h-9V4.5h9V6a.75.75 0 001.5 0V4.5A1.5 1.5 0 0013.5 3h-9zm13.28 5.72a.75.75 0 10-1.06 1.06l1.97 1.97H10.5a.75.75 0 000 1.5h8.19l-1.97 1.97a.75.75 0 101.06 1.06l3.25-3.25a.75.75 0 000-1.06l-3.25-3.25z" clip-rule="evenodd" />
                            </svg>
                            Đăng xuất
                        </a>
                    </div>
                </div>

                <?php if (isset($error) || isset($success)): ?>
                    <div class="mt-8 grid gap-3">
                        <?php if (isset($error)): ?>
                            <div class="rounded-2xl border border-red-200 bg-rose-50 px-5 py-4 text-sm text-rose-600 shadow-inner">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($success)): ?>
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-600 shadow-inner">
                                <?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="mt-10">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div class="space-y-2">
                            <label for="id_tk" class="text-sm font-medium text-slate-500">Mã Tài Khoản</label>
                            <input type="text" id="id_tk" name="id_tk" value="<?php echo htmlspecialchars($idTk); ?>"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-200" readonly>
                        </div>
                        <div class="space-y-2">
                            <label for="ho_ten" class="text-sm font-medium text-slate-500">Họ tên</label>
                            <input type="text" id="ho_ten" name="full_name" value="<?php echo htmlspecialchars($hoTen); ?>"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        </div>
                        <div class="space-y-2">
                            <label for="ngay_sinh" class="text-sm font-medium text-slate-500">Ngày sinh</label>
                            <input type="date" id="ngay_sinh" name="birth_date" value="<?php echo htmlspecialchars($ngaySinh); ?>"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        </div>
                        <div class="space-y-2">
                            <label for="dia_chi" class="text-sm font-medium text-slate-500">Địa chỉ</label>
                            <input type="text" id="dia_chi" name="address" value="<?php echo htmlspecialchars($diaChi); ?>"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        </div>
                        <div class="space-y-2">
                            <label for="email" class="text-sm font-medium text-slate-500">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        </div>
                        <div class="space-y-2">
                            <label for="sdt" class="text-sm font-medium text-slate-500">Số điện thoại</label>
                            <input type="text" id="sdt" name="phone" value="<?php echo htmlspecialchars($sdt); ?>"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        </div>
                        <div class="lg:col-span-2 flex flex-wrap gap-4 pt-2">
                            <button type="submit"
                                class="inline-flex items-center gap-2 rounded-2xl bg-emerald-400 px-6 py-3 text-sm font-semibold text-emerald-900 shadow-sm transition hover:-translate-y-0.5 hover:bg-emerald-300">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                    <path d="M11.47 3.72a.75.75 0 011.06 0l7.5 7.5a.75.75 0 01-1.06 1.06L12 5.31 5.03 12.28a.75.75 0 01-1.06-1.06l7.5-7.5z" />
                                    <path d="M12 5.31l7.5 7.5c.29.3.08.81-.35.81H15a.75.75 0 00-.75.75v4.69a.75.75 0 01-.75.75h-3a.75.75 0 01-.75-.75V14.37A.75.75 0 008.25 13.5H4.85c-.43 0-.64-.52-.35-.81L12 5.31z" />
                                </svg>
                                Cập nhật thông tin
                            </button>
                            <button type="button" onclick="togglePasswordModal()"
                                class="inline-flex items-center gap-2 rounded-2xl border border-indigo-200 bg-white px-6 py-3 text-sm font-semibold text-indigo-500 shadow-sm transition hover:-translate-y-0.5 hover:bg-indigo-50">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                    <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v2.25H6a3 3 0 00-3 3V18a4.5 4.5 0 004.5 4.5h9A4.5 4.5 0 0021 18v-6a3 3 0 00-3-3h-.75V6.75A5.25 5.25 0 0012 1.5zm-3.75 5.25a3.75 3.75 0 117.5 0v2.25h-7.5V6.75zm4.5 7.5a.75.75 0 10-1.5 0v2.25a.75.75 0 001.5 0V14.25z" clip-rule="evenodd" />
                                </svg>
                                Đổi mật khẩu
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="password-overlay" class="fixed inset-0 hidden bg-slate-900/20 backdrop-blur-[2px]"></div>
    <div id="password-modal" class="fixed inset-0 hidden z-20 flex items-center justify-center px-4">
        <div class="w-full max-w-md rounded-3xl border border-indigo-100 bg-white p-6 text-slate-700 shadow-2xl">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-slate-800">Đổi mật khẩu</h2>
                <button type="button" onclick="togglePasswordModal()" class="rounded-full p-1 text-slate-400 transition hover:bg-indigo-50 hover:text-indigo-500">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                        <path fill-rule="evenodd" d="M5.47 5.47a.75.75 0 011.06 0L12 10.94l5.47-5.47a.75.75 0 111.06 1.06L13.06 12l5.47 5.47a.75.75 0 11-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 01-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 010-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
            <p class="mt-2 text-sm text-slate-500">Vui lòng nhập mật khẩu hiện tại và mật khẩu mới để cập nhật bảo mật tài khoản.</p>
            <form method="POST" class="mt-6 space-y-5">
                <div class="space-y-2">
                    <label for="current_password" class="text-sm font-medium text-slate-500">Mật khẩu hiện tại</label>
                    <input type="password" id="current_password" name="current_password"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-200" required>
                </div>
                <div class="space-y-2">
                    <label for="new_password" class="text-sm font-medium text-slate-500">Mật khẩu mới</label>
                    <input type="password" id="new_password" name="new_password"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-200" required>
                </div>
                <div class="space-y-2">
                    <label for="confirm_new_password" class="text-sm font-medium text-slate-500">Xác nhận mật khẩu mới</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-200" required>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-2xl bg-indigo-400 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-indigo-300">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                            <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v2.25H6a3 3 0 00-3 3V18a4.5 4.5 0 004.5 4.5h9A4.5 4.5 0 0021 18v-6a3 3 0 00-3-3h-.75V6.75A5.25 5.25 0 0012 1.5zm-3.75 5.25a3.75 3.75 0 117.5 0v2.25h-7.5V6.75zm3.75 4.5a1.5 1.5 0 00-1.5 1.5v2.25a1.5 1.5 0 003 0V12a1.5 1.5 0 00-1.5-1.5z" clip-rule="evenodd" />
                        </svg>
                        Xác nhận đổi mật khẩu
                    </button>
                    <button type="button" onclick="togglePasswordModal()"
                        class="inline-flex items-center gap-2 rounded-2xl border border-rose-200 bg-white px-6 py-3 text-sm font-semibold text-rose-500 shadow-sm transition hover:-translate-y-0.5 hover:bg-rose-50">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                            <path fill-rule="evenodd" d="M5.47 5.47a.75.75 0 011.06 0L12 10.94l5.47-5.47a.75.75 0 111.06 1.06L13.06 12l5.47 5.47a.75.75 0 11-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 01-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 010-1.06z" clip-rule="evenodd" />
                        </svg>
                        Hủy
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>
