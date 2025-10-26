<?php
include '../../database/config.php';

$limit = 6;
$page = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';


function getEmployeesSearch($search = "", $limit = 6, $offset = 0)
{
    global $conn;
    $query = "
    SELECT nv.ID_TK,
           nv.ID_CN,
           nv.CHUYEN_MON,
           nv.LOAI_NV,      
           tk.HO_TEN,
           tk.NGAY_SINH,
           tk.DIA_CHI,
           tk.EMAIL,
           tk.SDT,
           cn.TEN_CN
    FROM nhan_vien nv
    INNER JOIN tai_khoan tk ON nv.ID_TK = tk.ID_TK
    INNER JOIN chi_nhanh cn ON nv.ID_CN = cn.ID_CN
";

    if (!empty($search)) {
        $search = mysqli_real_escape_string($conn, $search);
        $query .= " WHERE tk.ID_TK LIKE '%$search%'
                    OR tk.HO_TEN LIKE '%$search%' 
                    OR tk.EMAIL LIKE '%$search%'
                    OR cn.TEN_CN LIKE '%$search%'";
    }
    $query .= " LIMIT $limit OFFSET $offset";
    return mysqli_query($conn, $query);
}

function countEmployees($search = "")
{
    global $conn;
    $query = "SELECT COUNT(*) as total FROM nhan_vien nv 
              INNER JOIN tai_khoan tk ON nv.ID_TK = tk.ID_TK 
              INNER JOIN chi_nhanh cn ON nv.ID_CN = cn.ID_CN";
    if (!empty($search)) {
        $search = mysqli_real_escape_string($conn, $search);
        $query .= " WHERE tk.ID_TK LIKE '%$search%'
                    OR tk.HO_TEN LIKE '%$search%' 
                    OR tk.EMAIL LIKE '%$search%' 
                    OR cn.TEN_CN LIKE '%$search%'";
    }

    $result = mysqli_query($conn, $query);
    return mysqli_fetch_assoc($result)['total'] ?? 0;
}


// Fetch branches for dropdown
function getBranches()
{
    global $conn;
    $query = "SELECT ID_CN, TEN_CN FROM chi_nhanh";
    return mysqli_query($conn, $query);
}

// Handle edit employee form submission
if (isset($_POST['edit_employee'])) {
    $id_tk = $_POST['ID_TK'];
    $ho_ten = $_POST['HO_TEN'];
    $ngay_sinh = $_POST['NGAY_SINH'];
    $dia_chi = $_POST['DIA_CHI'];
    $email = $_POST['EMAIL'];
    $sdt = $_POST['SDT'];
    $id_cn = $_POST['ID_CN'];
    $chuyen_mon = $_POST['CHUYEN_MON'];
    $mat_khau = trim($_POST['MAT_KHAU']); // Mật khẩu mới (có thể để trống)
    $loai_nv = $_POST['LOAI_NV'] ?? 'chuyen_trach'; // 'chuyen_trach' mặc định


    if (empty($ho_ten) || empty($ngay_sinh) || empty($dia_chi) || empty($email) || empty($sdt) || empty($id_cn) || empty($chuyen_mon)) {
        $errorMessage = "Vui lòng điền đầy đủ thông tin.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Địa chỉ email không hợp lệ.";
    } elseif (!preg_match("/^[0-9]{10}$/", $sdt)) {
        $errorMessage = "Số điện thoại phải gồm 10 chữ số.";
    } else {
        $updateTaiKhoanQuery = "
            UPDATE tai_khoan 
            SET HO_TEN = '$ho_ten', NGAY_SINH = '$ngay_sinh', DIA_CHI = '$dia_chi', 
                EMAIL = '$email', SDT = '$sdt'
        ";

        if ($mat_khau !== '') {
            if (strlen($mat_khau) < 6) {
                $errorMessage = "Mật khẩu phải ít nhất 6 ký tự.";
            } else {
                $hashedPassword = password_hash($mat_khau, PASSWORD_DEFAULT);
                $updateTaiKhoanQuery .= ", MAT_KHAU = '$hashedPassword'";
            }
        }

        $updateTaiKhoanQuery .= " WHERE ID_TK = '$id_tk'";

        if (!isset($errorMessage) && mysqli_query($conn, $updateTaiKhoanQuery)) {
            $updateNhanVienQuery = "
    UPDATE nhan_vien
    SET ID_CN = '$id_cn',
        CHUYEN_MON = '$chuyen_mon',
        LOAI_NV = '$loai_nv'
    WHERE ID_TK = '$id_tk'
";
            if (mysqli_query($conn, $updateNhanVienQuery)) {
                $successMessage = "Cập nhật thông tin nhân viên thành công!";
            } else {
                $errorMessage = "Không thể cập nhật bảng nhân viên. Lỗi: " . mysqli_error($conn);
            }
        } else if (!isset($errorMessage)) {
            $errorMessage = "Không thể cập nhật bảng tài khoản. Lỗi: " . mysqli_error($conn);
        }
    }
}


// Hàm thêm nhân viên mới
function handleAddEmployee($data)
{
    global $conn;
    $id_tk = trim($data['ID_TK']);
    $ho_ten = trim($data['HO_TEN']);
    $ngay_sinh = trim($data['NGAY_SINH']);
    $dia_chi = trim($data['DIA_CHI']);
    $email = trim($data['EMAIL']);
    $sdt = trim($data['SDT']);
    $mat_khau = trim($data['MAT_KHAU']);
    $id_cn = trim($data['ID_CN']);
    $chuyen_mon = trim($data['CHUYEN_MON']);
    $id_quyen = 2;
    $loai_nv = isset($data['LOAI_NV']) ? trim($data['LOAI_NV']) : 'chuyen_trach';
    if ($loai_nv !== 'chuyen_trach' && $loai_nv !== 'quan_ly') {
        $loai_nv = 'chuyen_trach';
    }


    if (empty($id_tk) || empty($ho_ten) || empty($ngay_sinh) || empty($dia_chi) || empty($email) || empty($sdt) || empty($mat_khau) || empty($id_cn) || empty($chuyen_mon)) {
        return ['error' => "Vui lòng điền đầy đủ thông tin."];
    } elseif (!preg_match("/^[a-zA-Z0-9]+$/", $id_tk)) {
        return ['error' => "ID tài khoản không hợp lệ."];
    } elseif (!preg_match("/^[0-9]{10}$/", $sdt)) {
        return ['error' => "Số điện thoại phải bao gồm 10 chữ số."];
    } elseif (strlen($mat_khau) < 6 || strlen($mat_khau) > 22) {
        return ['error' => "Mật khẩu phải có độ dài từ 6 đến 22 ký tự."];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['error' => "Địa chỉ email không hợp lệ."];
    }

    $checkIdQuery = "SELECT ID_TK FROM tai_khoan WHERE ID_TK = ?";
    $stmt = $conn->prepare($checkIdQuery);
    $stmt->bind_param("s", $id_tk);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return ['error' => "ID tài khoản đã tồn tại."];
    }

    $checkEmailQuery = "SELECT 1 FROM tai_khoan WHERE EMAIL = ?";
    $stmtEmail = $conn->prepare($checkEmailQuery);
    $stmtEmail->bind_param("s", $email);
    $stmtEmail->execute();
    $resultEmail = $stmtEmail->get_result();
    if ($resultEmail->num_rows > 0) {
        return ['error' => "Email đã được sử dụng."];
    }

    $mat_khau_encrypted = password_hash($mat_khau, PASSWORD_DEFAULT);
    $insertTaiKhoan = $conn->prepare("INSERT INTO tai_khoan (ID_TK, ID_QUYEN, HO_TEN, NGAY_SINH, DIA_CHI, EMAIL, SDT, MAT_KHAU) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $insertTaiKhoan->bind_param("sissssss", $id_tk, $id_quyen, $ho_ten, $ngay_sinh, $dia_chi, $email, $sdt, $mat_khau_encrypted);

    if (!$insertTaiKhoan->execute()) {
        return ['error' => "Không thể thêm vào bảng tài khoản. Lỗi: " . $conn->error];
    }

    $insertNhanVien = $conn->prepare("
    INSERT INTO nhan_vien (ID_TK, ID_CN, CHUYEN_MON, LOAI_NV)
    VALUES (?, ?, ?, ?)
");
    $insertNhanVien->bind_param("ssss", $id_tk, $id_cn, $chuyen_mon, $loai_nv);

    if (!$insertNhanVien->execute()) {
        return ['error' => "Không thể thêm vào bảng nhân viên. Lỗi: " . $conn->error];
    }

    return ['success' => "Thêm nhân viên mới thành công!"];
}

// Gọi hàm khi submit thêm nhân viên
if (isset($_POST['add_employee'])) {
    $result = handleAddEmployee($_POST);
    if (isset($result['error'])) {
        $errorMessage = $result['error'];
    } elseif (isset($result['success'])) {
        $successMessage = $result['success'];
    }
}




// Xử lý chỉnh sửa nhân viên
$editEmployee = null;
if (isset($_GET['edit'])) {
    $id_tk = $_GET['edit'];
    $editQuery = "SELECT nv.ID_TK,
                     nv.ID_CN,
                     nv.CHUYEN_MON,
                     nv.LOAI_NV,      
                     tk.HO_TEN,
                     tk.NGAY_SINH,
                     tk.DIA_CHI,
                     tk.EMAIL,
                     tk.SDT,
                     tk.MAT_KHAU
              FROM nhan_vien nv
              INNER JOIN tai_khoan tk ON nv.ID_TK = tk.ID_TK
              WHERE nv.ID_TK = '$id_tk'";
    $result = mysqli_query($conn, $editQuery);
    if ($result && mysqli_num_rows($result) > 0) {
        $editEmployee = mysqli_fetch_assoc($result);
    }
}

// Handle delete employee
if (isset($_GET['delete']) && !isset($_GET['confirmed'])) {
    $id_tk = $_GET['delete'];
    echo "<script>
        if (confirm('Bạn có chắc chắn muốn xóa nhân viên này không?')) {
            window.location.href = '?page=employees&delete=$id_tk&confirmed=true';
        } else {
            window.location.href = '?page=employees';
        }
    </script>";
    exit;
}

if (isset($_GET['delete']) && isset($_GET['confirmed']) && $_GET['confirmed'] === 'true') {
    $id_tk = $_GET['delete'];

    if (checkIfEmployeeHasAssignments($id_tk)) {
        $message = "Không thể xóa nhân viên vì nhân viên này đang được phân công cho một lịch hẹn.";
    } else {
        $message = deleteEmployee($id_tk);
    }

    echo "<script>alert('$message'); window.location.href = '?page=employees';</script>";
}





// Delete employee function
function deleteEmployee($idTk)
{
    global $conn; // Assuming $conn is your MySQL connection

    // Start a transaction to ensure data consistency
    mysqli_begin_transaction($conn);

    try {
        // Delete from phan_cong_nhan_vien (assignments table)
        $deleteAssignmentsQuery = "DELETE FROM phan_cong_nhan_vien WHERE ID_TK = ?";
        $stmt1 = mysqli_prepare($conn, $deleteAssignmentsQuery);
        mysqli_stmt_bind_param($stmt1, "s", $idTk);
        mysqli_stmt_execute($stmt1);

        // Delete from nhan_vien (employee table)
        $deleteNhanVienQuery = "DELETE FROM nhan_vien WHERE ID_TK = ?";
        $stmt2 = mysqli_prepare($conn, $deleteNhanVienQuery);
        mysqli_stmt_bind_param($stmt2, "s", $idTk);
        mysqli_stmt_execute($stmt2);

        // Delete from tai_khoan (account table)
        $deleteTaiKhoanQuery = "DELETE FROM tai_khoan WHERE ID_TK = ?";
        $stmt3 = mysqli_prepare($conn, $deleteTaiKhoanQuery);
        mysqli_stmt_bind_param($stmt3, "s", $idTk);
        mysqli_stmt_execute($stmt3);

        // Commit transaction if all queries executed successfully
        mysqli_commit($conn);

        return "Nhân viên đã được xóa thành công.";
    } catch (Exception $e) {
        // Rollback transaction if any query fails
        mysqli_rollback($conn);
        return "Xóa nhân viên thất bại. Vui lòng thử lại.";
    }
}

// Check if employee has any assignments in phan_cong_nhan_vien
function checkIfEmployeeHasAssignments($idTk)
{
    global $conn; // Assuming $conn is your MySQL connection

    // Query to check if the employee is assigned to any appointments
    $checkAssignmentsQuery = "SELECT * FROM phan_cong_nhan_vien WHERE ID_TK = ?"; // Check in phan_cong_nhan_vien table for the employee's ID_TK
    $stmt = mysqli_prepare($conn, $checkAssignmentsQuery);
    mysqli_stmt_bind_param($stmt, "s", $idTk);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_num_rows($result) > 0; // Return true if employee has assignments, false otherwise
}

$branches = getBranches();

$employees = getEmployeesSearch($search, $limit, $offset);

?>


<!--  -->

<body class="bg-gray-100 p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-extrabold text-indigo-700 mb-6 text-center">👔 Quản lý nhân viên</h1>

        <!-- Thông báo -->
        <?php if (isset($successMessage)): ?>
            <div class="bg-green-100 text-green-800 px-4 py-3 rounded mb-6 shadow"><?= $successMessage ?></div>
        <?php endif; ?>
        <?php if (isset($errorMessage)): ?>
            <div class="bg-red-100 text-red-700 px-4 py-3 rounded mb-6 shadow"><?= $errorMessage ?></div>
        <?php endif; ?>

        <!-- Tìm kiếm + Thêm -->
        <div class="flex flex-wrap items-center justify-between mb-6 gap-4">
            <form action="admin_dashboard.php" method="GET" class="flex gap-2 items-center">
                <input type="hidden" name="page" value="employees">
                <input type="text" name="search" placeholder="🔍 Tìm kiếm nhân viên..."
                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                    class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500 shadow-sm">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg shadow">Tìm</button>
            </form>
            <a href="?page=employees&add"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow transition">
                ➕ Thêm nhân viên
            </a>
        </div>

        <!-- Danh sách nhân viên -->
        <div class="overflow-x-auto bg-white rounded-xl shadow-lg">
            <table class="min-w-full table-auto text-sm">
                <thead class="bg-indigo-100 text-indigo-700">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Họ tên</th>
                        <th class="px-4 py-3">Chuyên môn</th>
                        <th class="px-4 py-3">Chi nhánh</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">SĐT</th>

                        <th class="px-4 py-3">Vai trò</th> <!-- 👈 mới -->

                        <th class="px-4 py-3">Hành động</th>
                    </tr>
                </thead>

                <tbody class="text-center divide-y">
                    <?php if (mysqli_num_rows($employees) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($employees)): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3"><?= $row['ID_TK'] ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($row['HO_TEN']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($row['CHUYEN_MON']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($row['TEN_CN']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($row['EMAIL']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($row['SDT']) ?></td>

                                <td class="px-4 py-3">
                                    <?php
                                    $currentRole = $row['LOAI_NV'] ?? 'chuyen_trach';
                                    $label = ($currentRole === 'quan_ly') ? 'Quản lý chi nhánh' : 'Nhân sự thường';
                                    $badgeClass = ($currentRole === 'quan_ly')
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-blue-100 text-blue-700';
                                    ?>
                                    <span class="inline-block text-xs font-semibold px-2 py-1 rounded <?= $badgeClass ?>">
                                        <?= htmlspecialchars($label) ?>
                                    </span>
                                </td>


                                <td class="px-4 py-3">
                                    <a href="?page=employees&edit=<?= $row['ID_TK'] ?>" class="text-blue-600 font-semibold hover:underline mr-3">Sửa</a>
                                    <a href="?page=employees&delete=<?= $row['ID_TK'] ?>" class="text-red-600 font-semibold hover:underline">Xóa</a>
                                </td>

                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-red-600 font-bold py-6">Không tìm thấy nhân viên nào.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        $totalRecords = countEmployees($search);
        $totalPages = ceil($totalRecords / $limit);
        echo '<div class="mt-6 flex justify-center gap-2">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i == $page) ? 'bg-indigo-600 text-white' : 'bg-gray-200 hover:bg-gray-300';
            echo "<a href='?page=employees&page_num=$i&search=" . urlencode($search) . "' class='px-3 py-1 rounded $active'>$i</a>";
        }
        echo '</div>';
        ?>


        <!--  -->
        <div id="form-container" class="<?= (isset($_GET['add']) || isset($_GET['edit'])) ? '' : 'hidden' ?>">
            <form action="" method="POST" class="bg-white p-6 rounded-xl shadow-lg mt-8">
                <div class="flex justify-end">
                    <button type="button" onclick="closeForm()"
                        class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                </div>
                <h2 class="text-2xl font-bold text-indigo-700 mb-6">
                    <?= isset($_GET['add']) ? '🆕 Thêm nhân viên mới' : '✏️ Sửa thông tin nhân viên' ?>
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- ID Tài khoản (chỉ hiển thị khi thêm) -->
                    <?php if (isset($_GET['add'])): ?>
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">ID Tài khoản</label>
                            <input type="text" name="ID_TK" required
                                class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="ID_TK" value="<?= $editEmployee['ID_TK'] ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Họ tên</label>
                        <input type="text" name="HO_TEN" value="<?= $editEmployee['HO_TEN'] ?? '' ?>" required
                            class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Ngày sinh</label>
                        <input type="date" name="NGAY_SINH" value="<?= $editEmployee['NGAY_SINH'] ?? '' ?>"
                            class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Địa chỉ</label>
                        <input type="text" name="DIA_CHI" value="<?= $editEmployee['DIA_CHI'] ?? '' ?>" required
                            class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Số điện thoại</label>
                        <input type="text" name="SDT" value="<?= $editEmployee['SDT'] ?? '' ?>" required
                            class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="EMAIL" value="<?= $editEmployee['EMAIL'] ?? '' ?>" required
                            class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Chi nhánh</label>
                        <select name="ID_CN" class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" required>
                            <?php
                            $branchesList = isset($_GET['add']) ? getBranches() : $branches;
                            while ($branch = mysqli_fetch_assoc($branchesList)): ?>
                                <option value="<?= $branch['ID_CN'] ?>"
                                    <?= isset($editEmployee) && $editEmployee['ID_CN'] == $branch['ID_CN'] ? 'selected' : '' ?>>
                                    <?= $branch['TEN_CN'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Chuyên môn</label>
                        <input type="text" name="CHUYEN_MON" value="<?= $editEmployee['CHUYEN_MON'] ?? '' ?>"
                            class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Vai trò nội bộ</label>
                        <select name="LOAI_NV"
                            class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500">
                            <?php
                            $currentRoleEdit = $editEmployee['LOAI_NV'] ?? 'chuyen_trach';
                            ?>
                            <option value="chuyen_trach" <?= $currentRoleEdit === 'chuyen_trach' ? 'selected' : '' ?>>
                                Nhân sự thường
                            </option>
                            <option value="quan_ly" <?= $currentRoleEdit === 'quan_ly' ? 'selected' : '' ?>>
                                Quản lý chi nhánh
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">
                            <?= isset($_GET['add']) ? 'Mật khẩu' : 'Mật khẩu mới (bỏ trống nếu không đổi)' ?>
                        </label>
                        <input type="<?= isset($_GET['add']) ? 'password' : 'text' ?>" name="MAT_KHAU"
                            class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500"
                            <?= isset($_GET['add']) ? 'required' : '' ?> />
                    </div>

                </div>

                <div class="mt-6 flex justify-between items-center">
                    <!-- Nút đóng form -->
                    <button type="button"
                        onclick="closeForm()"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold px-6 py-2 rounded-lg shadow transition">
                        ✖ Đóng form
                    </button>

                    <!-- Nút lưu -->
                    <button type="submit" name="<?= isset($_GET['add']) ? 'add_employee' : 'edit_employee' ?>"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2 rounded-lg shadow transition">
                        <?= isset($_GET['add']) ? '➕ Thêm mới' : '💾 Cập nhật' ?>
                    </button>
                </div>

            </form>
        </div>

</body>

<script>
    function closeForm() {
        const formContainer = document.getElementById('form-container');
        if (formContainer) formContainer.classList.add('hidden');

        // Xóa các tham số ?add hoặc ?edit trên URL để không mở lại form khi reload
        const url = new URL(window.location.href);
        url.searchParams.delete('add');
        url.searchParams.delete('edit');
        window.history.replaceState({}, document.title, url);
    }
</script>