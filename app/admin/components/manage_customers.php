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
function deleteCustomer($idTk)
{
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
function checkIfCustomerHasAppointments($idTk)
{
    global $conn; // Assuming $conn is your MySQL connection

    // Query to check if there are any appointments related to this customer in lich_hen
    $checkAppointmentsQuery = "SELECT * FROM lich_hen WHERE ID_TK = ?"; // Check in lich_hen table for the customer's ID_TK
    $stmt = mysqli_prepare($conn, $checkAppointmentsQuery);
    mysqli_stmt_bind_param($stmt, "s", $idTk);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_num_rows($result) > 0; // Return true if customer has appointments, false otherwise
}


// Ham lay danh sach khach hang co phan trang va tim kiem
function getPaginatedCustomers($search, $page, $limit = 5)
{
    global $conn;
    $offset = ($page - 1) * $limit;
    $search = mysqli_real_escape_string($conn, $search);
    $where = $search ? "kh.HO_TEN LIKE '%$search%' OR kh.EMAIL LIKE '%$search%' OR kh.SDT LIKE '%$search%'" : "1";

    $countQuery = "SELECT COUNT(*) as total FROM khach_hang kh INNER JOIN tai_khoan tk ON kh.ID_TK = tk.ID_TK WHERE $where";
    $countResult = mysqli_query($conn, $countQuery);
    $total = mysqli_fetch_assoc($countResult)['total'];
    $totalPages = ceil($total / $limit);

    $query = "SELECT kh.ID_TK, kh.HO_TEN, kh.NGAY_SINH, kh.DIA_CHI, kh.EMAIL, kh.SDT
              FROM khach_hang kh
              INNER JOIN tai_khoan tk ON kh.ID_TK = tk.ID_TK
              WHERE $where
              LIMIT $offset, $limit";
    $result = mysqli_query($conn, $query);

    return [$result, $totalPages];
}

// Lay tu form GET
$search = $_GET['search'] ?? '';
$pageNumber = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
list($customers, $totalPages) = getPaginatedCustomers($search, $pageNumber);
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

        <?php if (isset($successMessage)) : ?>
            <div class="bg-green-100 text-green-800 p-4 rounded-lg shadow mb-4">
                <?= $successMessage ?>
            </div>
        <?php elseif (isset($errorMessage)) : ?>
            <div class="bg-red-100 text-red-700 p-4 rounded-lg shadow mb-4">
                <?= $errorMessage ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['edit'])): ?>
            <!-- Form cập nhật -->
            <form method="POST" class="bg-white p-6 rounded-lg shadow mb-6">
                <h2 class="text-xl font-semibold text-indigo-600 mb-4">✏️ Cập nhật khách hàng</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="text" name="ID_TK" value="<?= $editCustomer['ID_TK'] ?>" readonly class="p-2 border rounded" />
                    <input type="text" name="HO_TEN" value="<?= $editCustomer['HO_TEN'] ?>" required class="p-2 border rounded" />
                    <input type="date" name="NGAY_SINH" value="<?= $editCustomer['NGAY_SINH'] ?>" required class="p-2 border rounded" />
                    <input type="text" name="DIA_CHI" value="<?= $editCustomer['DIA_CHI'] ?>" required class="p-2 border rounded" />
                    <input type="email" name="EMAIL" value="<?= $editCustomer['EMAIL'] ?>" required class="p-2 border rounded" />
                    <input type="text" name="SDT" value="<?= $editCustomer['SDT'] ?>" required class="p-2 border rounded" />
                    <input type="password" name="MAT_KHAU" placeholder="Mật khẩu mới" required class="p-2 border rounded" />
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="submit" name="edit_customer" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow">💾 Lưu</button>
                    <a href="?page=customers" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded shadow">🔙 Quay lại</a>
                </div>
            </form>

        <?php elseif (isset($_GET['add'])): ?>
            <!-- Form thêm -->
            <form method="POST" class="bg-white p-6 rounded-lg shadow mb-6">
                <h2 class="text-xl font-semibold text-indigo-600 mb-4">➕ Thêm khách hàng mới</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="text" name="ID_TK" placeholder="ID tài khoản" required class="p-2 border rounded" />
                    <input type="text" name="HO_TEN" placeholder="Họ tên" required class="p-2 border rounded" />
                    <input type="date" name="NGAY_SINH" required class="p-2 border rounded" />
                    <input type="text" name="DIA_CHI" placeholder="Địa chỉ" required class="p-2 border rounded" />
                    <input type="email" name="EMAIL" placeholder="Email" required class="p-2 border rounded" />
                    <input type="text" name="SDT" placeholder="Số điện thoại" required class="p-2 border rounded" />
                    <input type="password" name="MAT_KHAU" placeholder="Mật khẩu" required class="p-2 border rounded" />
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="submit" name="add_customer" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow">✅ Thêm</button>
                    <a href="?page=customers" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded shadow">🔙 Quay lại</a>
                </div>
            </form>

        <?php else: ?>
            <!-- Tìm kiếm + Thêm mới -->
            <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
                <form method="GET" class="flex gap-2 flex-wrap items-center">
                    <input type="hidden" name="page" value="customers">
                    <input type="text" name="search" placeholder="🔍 Tìm theo tên, email, SDT..." value="<?= htmlspecialchars($search ?? '') ?>"
                        class="p-2 border rounded w-64 shadow-sm">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded shadow">Tìm kiếm</button>
                </form>
                <a href="?page=customers&add=true" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow">➕ Thêm khách hàng</a>
            </div>

            <!-- Danh sách khách hàng -->
            <div class="overflow-x-auto bg-white rounded-lg shadow">
                <table class="min-w-full table-auto text-sm">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="p-4">ID Tài Khoản</th>
                            <th class="p-4">Họ Tên</th>
                            <th class="p-4">Ngày Sinh</th>
                            <th class="p-4">Địa Chỉ</th>
                            <th class="p-4">Email</th>
                            <th class="p-4">SĐT</th>
                            <th class="p-4">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($customer = mysqli_fetch_assoc($customers)) : ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-4"><?= $customer['ID_TK']; ?></td>
                                <td class="p-4"><?= $customer['HO_TEN']; ?></td>
                                <td class="p-4"><?= $customer['NGAY_SINH']; ?></td>
                                <td class="p-4"><?= $customer['DIA_CHI']; ?></td>
                                <td class="p-4"><?= $customer['EMAIL']; ?></td>
                                <td class="p-4"><?= $customer['SDT']; ?></td>
                                <td class="p-4 flex gap-2">
                                    <a href="?page=customers&edit=<?= $customer['ID_TK']; ?>"
                                        class="bg-yellow-500 hover:bg-yellow-600 text-white px-2 py-1 rounded shadow text-sm">Sửa</a>
                                    <a href="?page=customers&delete=<?= $customer['ID_TK']; ?>"
                                        onclick="return confirm('Bạn có chắc chắn muốn xóa khách hàng này?');"
                                        class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded shadow text-sm">Xóa</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Phân trang -->
            <?php if ($totalPages > 1): ?>
                <div class="mt-6 flex justify-center gap-2">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=customers&search=<?= urlencode($search) ?>&p=<?= $i ?>"
                           class="px-3 py-1 rounded border <?= ($i == $pageNumber) ? 'bg-indigo-600 text-white' : 'bg-white hover:bg-gray-100' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
