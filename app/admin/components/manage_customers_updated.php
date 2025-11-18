<?php
include '../../database/config.php';
require_once __DIR__ . '/../../helpers/assets.php';

// Fetch all customers
function getCustomers()
{
    global $conn;
    $query = "
        SELECT kh.ID_TK, kh.HO_TEN, kh.NGAY_SINH, kh.DIA_CHI, kh.EMAIL, kh.SDT, tk.HO_TEN as 'taikhoan_ho_ten'
        FROM khach_hang kh
        INNER JOIN tai_khoan tk ON kh.ID_TK = tk.ID_TK
    ";
    return mysqli_query($conn, $query);
}

// Handle add/edit customer
if (isset($_POST['edit_customer'])) {
    $id_tk = $_POST['ID_TK'];
    $ho_ten = $_POST['HO_TEN'];
    $ngay_sinh = $_POST['NGAY_SINH'];
    $dia_chi = $_POST['DIA_CHI'];
    $email = $_POST['EMAIL'];
    $sdt = $_POST['SDT'];
    $mat_khau = $_POST['MAT_KHAU']; // Trường mật khẩu

    // Check if required fields are not empty
    if (empty($ho_ten) || empty($ngay_sinh) || empty($dia_chi) || empty($email) || empty($sdt)) {
        $errorMessage = "Vui lòng điền đầy đủ thông tin.";
    } else {
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Địa chỉ email không hợp lệ.";
        }
        // Validate phone number format (must start with 0 and have 10 digits)
        elseif (!preg_match("/^0[0-9]{9}$/", $sdt)) {
            $errorMessage = "Số điện thoại phải bắt đầu bằng số 0 và có 10 chữ số.";
        }
        // Check if the customer is 18 years or older
        elseif (strtotime($ngay_sinh) > strtotime('-18 years')) {
            $errorMessage = "Khách hàng phải đủ 18 tuổi.";
        } elseif (strlen($mat_khau) < 8) {
            $errorMessage = "Mật khẩu phải có ít nhất 8 ký tự.";
        } else {
            // If password is provided, hash it, otherwise keep the old password
            if (!empty($mat_khau)) {
                $hashedPassword = password_hash($mat_khau, PASSWORD_DEFAULT); // Encrypt password
                // Update password in tai_khoan table
                $updateTaiKhoanQuery = "UPDATE tai_khoan SET HO_TEN = ?, NGAY_SINH = ?, DIA_CHI = ?, EMAIL = ?, SDT = ?, MAT_KHAU = ? WHERE ID_TK = ?";
                $stmt = mysqli_prepare($conn, $updateTaiKhoanQuery);
                mysqli_stmt_bind_param($stmt, 'sssssss', $ho_ten, $ngay_sinh, $dia_chi, $email, $sdt, $hashedPassword, $id_tk);
            } else {
                // Update without changing the password
                $updateTaiKhoanQuery = "UPDATE tai_khoan SET HO_TEN = ?, NGAY_SINH = ?, DIA_CHI = ?, EMAIL = ?, SDT = ? WHERE ID_TK = ?";
                $stmt = mysqli_prepare($conn, $updateTaiKhoanQuery);
                mysqli_stmt_bind_param($stmt, 'ssssss', $ho_ten, $ngay_sinh, $dia_chi, $email, $sdt, $id_tk);
            }

            if (mysqli_stmt_execute($stmt)) {
                // Update customer data in khach_hang
                $updateKhachHangQuery = "UPDATE khach_hang SET HO_TEN = ?, NGAY_SINH = ?, DIA_CHI = ?, EMAIL = ?, SDT = ? WHERE ID_TK = ?";
                $stmt2 = mysqli_prepare($conn, $updateKhachHangQuery);
                mysqli_stmt_bind_param($stmt2, 'ssssss', $ho_ten, $ngay_sinh, $dia_chi, $email, $sdt, $id_tk);

                if (mysqli_stmt_execute($stmt2)) {
                    $successMessage = "Cập nhật khách hàng thành công!";
                } else {
                    $errorMessage = "Không thể cập nhật bảng khách hàng. Lỗi: " . mysqli_error($conn);
                }
            } else {
                $errorMessage = "Không thể cập nhật bảng tài khoản. Lỗi: " . mysqli_error($conn);
            }
        }
    }
}

// Handle adding new customer
if (isset($_POST['add_customer'])) {
    $id_tk = $_POST['ID_TK'];
    $ho_ten = $_POST['HO_TEN'];
    $ngay_sinh = $_POST['NGAY_SINH'];
    $dia_chi = $_POST['DIA_CHI'];
    $email = $_POST['EMAIL'];
    $sdt = $_POST['SDT'];
    $mat_khau = $_POST['MAT_KHAU']; // Mật khẩu chưa mã hóa
    $id_quyen = 3; // Default role for customers

    // Ràng buộc kiểm tra các trường
    if (!preg_match("/^[a-zA-Z0-9]+$/", $id_tk)) {
        $errorMessage = "ID tài khoản không hợp lệ. Vui lòng chỉ nhập ký tự không dấu và không có dấu cách.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Địa chỉ email không hợp lệ.";
    } elseif (!preg_match("/^[0-9]{10}$/", $sdt)) {
        $errorMessage = "Số điện thoại phải là 10 chữ số.";
    } elseif (!preg_match("/^0[0-9]{9}$/", $sdt)) {
        $errorMessage = "Số điện thoại phải bắt đầu bằng số 0 và có 10 chữ số.";
    } elseif (strlen($mat_khau) < 6) {
        $errorMessage = "Mật khẩu phải có ít nhất 6 ký tự.";
    } else {
        // Sanitize inputs to prevent SQL injection
        $id_tk = mysqli_real_escape_string($conn, $id_tk);
        $ho_ten = mysqli_real_escape_string($conn, $ho_ten);
        $ngay_sinh = mysqli_real_escape_string($conn, $ngay_sinh);
        $dia_chi = mysqli_real_escape_string($conn, $dia_chi);
        $email = mysqli_real_escape_string($conn, $email);
        $sdt = mysqli_real_escape_string($conn, $sdt);
        $mat_khau = password_hash($mat_khau, PASSWORD_DEFAULT); // Encrypt password

        // Check if ID_TK already exists
        $checkQuery = "SELECT ID_TK FROM tai_khoan WHERE ID_TK = '$id_tk'";
        $checkResult = mysqli_query($conn, $checkQuery);
        if (mysqli_num_rows($checkResult) > 0) {
            $errorMessage = "ID tài khoản đã tồn tại. Vui lòng chọn ID khác.";
        } else {
            // Insert into tai_khoan
            $insertTaiKhoanQuery = "INSERT INTO tai_khoan (ID_TK, ID_QUYEN, HO_TEN, NGAY_SINH, DIA_CHI, EMAIL, SDT, MAT_KHAU)
                                    VALUES ('$id_tk', $id_quyen, '$ho_ten', '$ngay_sinh', '$dia_chi', '$email', '$sdt', '$mat_khau')";
            if (mysqli_query($conn, $insertTaiKhoanQuery)) {
                // Insert into khach_hang
                $insertKhachHangQuery = "INSERT INTO khach_hang (ID_TK, HO_TEN, NGAY_SINH, DIA_CHI, EMAIL, SDT)
                                        VALUES ('$id_tk', '$ho_ten', '$ngay_sinh', '$dia_chi', '$email', '$sdt')";
                if (mysqli_query($conn, $insertKhachHangQuery)) {
                    $successMessage = "Thêm khách hàng mới thành công!";
                } else {
                    $errorMessage = "Không thể thêm vào bảng khách hàng. Lỗi: " . mysqli_error($conn);
                }
            } else {
                $errorMessage = "Không thể thêm vào bảng tài khoản. Lỗi: " . mysqli_error($conn);
            }
        }
    }
}

// Handle editing customer
$editCustomer = null;
if (isset($_GET['edit'])) {
    $id_tk = $_GET['edit'];
    $editQuery = "SELECT kh.ID_TK, kh.HO_TEN, kh.NGAY_SINH, kh.DIA_CHI, kh.EMAIL, kh.SDT
                  FROM khach_hang kh
                  WHERE kh.ID_TK = '$id_tk'";
    $result = mysqli_query($conn, $editQuery);
    if ($result && mysqli_num_rows($result) > 0) {
        $editCustomer = mysqli_fetch_assoc($result);
    }
}

// Handle delete customer
if (isset($_GET['delete'])) {
    $id_tk = $_GET['delete'];

    // Check if the customer has any associated appointments (lich_hen)
    if (checkIfCustomerHasAppointments($id_tk)) {
        $message = "Không thể xóa khách hàng vì khách hàng này có lịch hẹn.";
    } else {
        // If no associated appointments, proceed with deletion
        $message = deleteCustomer($id_tk);
    }

    // Show message and redirect
    echo "<script>alert('$message'); window.location.href = '?page=customers';</script>";
}

// Delete customer function
function deleteCustomer($idTk) {
    global $conn; // Assuming $conn is your MySQL connection

    // Proceed with deletion if no appointments exist
    // Delete from khach_hang (child table)
    $deleteKhachHangQuery = "DELETE FROM khach_hang WHERE ID_TK = ?";
    $stmt = mysqli_prepare($conn, $deleteKhachHangQuery);
    mysqli_stmt_bind_param($stmt, "s", $idTk);
    $result1 = mysqli_stmt_execute($stmt);

    // Delete from tai_khoan (parent table)
    $deleteTaiKhoanQuery = "DELETE FROM tai_khoan WHERE ID_TK = ?";
    $stmt2 = mysqli_prepare($conn, $deleteTaiKhoanQuery);
    mysqli_stmt_bind_param($stmt2, "s", $idTk);
    $result2 = mysqli_stmt_execute($stmt2);

    if ($result1 && $result2) {
        return "Khách hàng đã được xóa thành công.";
    } else {
        return "Xóa khách hàng thất bại. Vui lòng thử lại.";
    }
}

// Check if customer has any appointments
function checkIfCustomerHasAppointments($idTk) {
    global $conn; // Assuming $conn is your MySQL connection

    // Query to check if there are any appointments related to this customer in lich_hen
    $checkAppointmentsQuery = "SELECT * FROM lich_hen WHERE ID_TK = ?"; // Check in lich_hen table for the customer's ID_TK
    $stmt = mysqli_prepare($conn, $checkAppointmentsQuery);
    mysqli_stmt_bind_param($stmt, "s", $idTk);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_num_rows($result) > 0; // Return true if customer has appointments, false otherwise
}

// Fetch customers
$customers = getCustomers();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý khách hàng</title>
    <?= sb_tailwind_link_tag(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen p-6">
    <div class="max-w-5xl mx-auto">
        <h1 class="text-3xl font-bold text-indigo-700 mb-6 text-center">👥 Quản lý khách hàng</h1>

    <!-- Display success or error message -->
    <?php if (isset($successMessage)) : ?>
        <div class="bg-green-500 text-white p-3 rounded mb-4">
            <?php echo $successMessage; ?>
        </div>
    <?php elseif (isset($errorMessage)) : ?>
        <div class="bg-red-500 text-white p-3 rounded mb-4">
            <?php echo $errorMessage; ?>
        </div>
    <?php endif; ?>

    <?php
    // Check if we are in "edit" mode or "add" mode
    if (isset($_GET['edit'])) {
        // Fetch the customer details for editing
        $id_tk = $_GET['edit'];
        $getCustomerQuery = "SELECT * FROM khach_hang WHERE ID_TK = '$id_tk'";
        $result = mysqli_query($conn, $getCustomerQuery);
        $editCustomer = mysqli_fetch_assoc($result);

        // If no customer found with this ID, redirect to the customer list
        if (!$editCustomer) {
            header("Location: ?page=customers");
            exit();
        }
    ?>
        <!-- Edit customer form -->
        <form method="POST" class="bg-white p-6 shadow-md rounded mb-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Customer Information Form -->
                <input type="text" name="ID_TK" value="<?= $editCustomer['ID_TK'] ?? ''; ?>" class="p-2 border" readonly>
                <input type="text" name="HO_TEN" placeholder="Họ tên" value="<?= $editCustomer['HO_TEN'] ?? ''; ?>" class="p-2 border" required>
                <input type="date" name="NGAY_SINH" value="<?= $editCustomer['NGAY_SINH'] ?? ''; ?>" class="p-2 border" required>
                <input type="text" name="DIA_CHI" placeholder="Địa chỉ" value="<?= $editCustomer['DIA_CHI'] ?? ''; ?>" class="p-2 border" required>
                <input type="email" name="EMAIL" placeholder="Email" value="<?= $editCustomer['EMAIL'] ?? ''; ?>" class="p-2 border" required>
                <input type="text" name="SDT" placeholder="Số điện thoại" value="<?= $editCustomer['SDT'] ?? ''; ?>" class="p-2 border" required>
                <input type="password" name="MAT_KHAU" placeholder="Mật khẩu" class="p-2 border" required>
            </div>
            <div class="flex justify-between mt-4">
                <button type="submit" name="edit_customer" class="bg-blue-500 text-white p-2 rounded">
                    Cập nhật khách hàng
                </button>
                <button type="button" onclick="window.location.href='?page=customers'" class="bg-gray-500 text-white p-2 rounded">
                    Quay lại
                </button>
            </div>
        </form>
    <?php
    } else if (isset($_GET['add'])) {
        // Show form for adding new customer
    ?>
        <!-- Add new customer form -->
        <form method="POST" class="bg-white p-6 shadow-md rounded mb-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Customer Information Form -->
                <input type="text" name="ID_TK" placeholder="ID tài khoản" class="p-2 border" required>
                <input type="text" name="HO_TEN" placeholder="Họ tên" class="p-2 border" required>
                <input type="date" name="NGAY_SINH" class="p-2 border" required>
                <input type="text" name="DIA_CHI" placeholder="Địa chỉ" class="p-2 border" required>
                <input type="email" name="EMAIL" placeholder="Email" class="p-2 border" required>
                <input type="text" name="SDT" placeholder="Số điện thoại" class="p-2 border" required>
                <input type="password" name="MAT_KHAU" placeholder="Mật khẩu" class="p-2 border" required>
            </div>
            <div class="flex justify-between mt-4">
                <button type="submit" name="add_customer" class="bg-blue-500 text-white p-2 rounded">
                    Thêm khách hàng
                </button>
                <button type="button" onclick="window.location.href='?page=customers'" class="bg-gray-500 text-white p-2 rounded">
                    Quay lại
                </button>
            </div>
        </form>
    <?php
    } else {
        // Default: Show customer list
    ?>
        <!-- Customer List -->
        <table class="w-full bg-white rounded shadow-md overflow-hidden">
            <thead class="bg-gray-200">
                <tr>
                    <th class="p-4">ID Tài Khoản</th>
                    <th class="p-4">Họ Tên</th>
                    <th class="p-4">Ngày Sinh</th>
                    <th class="p-4">Địa Chỉ</th>
                    <th class="p-4">Email</th>
                    <th class="p-4">Số Điện Thoại</th>
                    <th class="p-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($customer = mysqli_fetch_assoc($customers)) : ?>
                    <tr>
                        <td class="p-4"><?= $customer['ID_TK']; ?></td>
                        <td class="p-4"><?= $customer['HO_TEN']; ?></td>
                        <td class="p-4"><?= $customer['NGAY_SINH']; ?></td>
                        <td class="p-4"><?= $customer['DIA_CHI']; ?></td>
                        <td class="p-4"><?= $customer['EMAIL']; ?></td>
                        <td class="p-4"><?= $customer['SDT']; ?></td>
                        <td class="p-4">
                            <a href="?page=customers&edit=<?= $customer['ID_TK'] ?>" class="bg-yellow-500 text-white p-2 rounded">Edit</a>
                            <a href="?page=customers&delete=<?= $customer['ID_TK']; ?>" class="bg-red-500 text-white p-2 rounded"
                                onclick="return confirm('Are you sure you want to delete this customer?');">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <!-- Add new customer button -->
        <a href="?page=customers&add=true" class="bg-green-500 text-white p-2 rounded mt-4 inline-block">Thêm khách hàng mới</a>
    <?php } ?>
</body>

</html>
