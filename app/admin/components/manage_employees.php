<?php
include '../../database/config.php';

$defaultLimit = 6;
$allowedPageSizes = [6, 10, 15, 25];
$perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : $defaultLimit;
$perPage = in_array($perPage, $allowedPageSizes, true) ? $perPage : $defaultLimit;
$limit = $perPage;
$page = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$branchFilter = isset($_GET['branch']) ? trim($_GET['branch']) : '';
$roleFilter = isset($_GET['role']) ? trim($_GET['role']) : '';


function getEmployeesSearch($search = "", $branch = "", $role = "", $limit = 6, $offset = 0)
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

    $conditions = [];

    if (!empty($search)) {
        $search = mysqli_real_escape_string($conn, $search);
        $conditions[] = "(tk.ID_TK LIKE '%$search%' 
                    OR tk.HO_TEN LIKE '%$search%' 
                    OR tk.EMAIL LIKE '%$search%'
                    OR cn.TEN_CN LIKE '%$search%')";
    }

    if (!empty($branch)) {
        $branch = mysqli_real_escape_string($conn, $branch);
        $conditions[] = "nv.ID_CN = '$branch'";
    }

    if (!empty($role)) {
        $role = mysqli_real_escape_string($conn, $role);
        $conditions[] = "nv.LOAI_NV = '$role'";
    }

    if (!empty($conditions)) {
        $query .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $query .= " LIMIT $limit OFFSET $offset";
    return mysqli_query($conn, $query);
}

function countEmployees($search = "", $branch = "", $role = "")
{
    global $conn;
    $query = "SELECT COUNT(*) as total FROM nhan_vien nv 
              INNER JOIN tai_khoan tk ON nv.ID_TK = tk.ID_TK 
              INNER JOIN chi_nhanh cn ON nv.ID_CN = cn.ID_CN";
    $conditions = [];

    if (!empty($search)) {
        $search = mysqli_real_escape_string($conn, $search);
        $conditions[] = "(tk.ID_TK LIKE '%$search%'
                    OR tk.HO_TEN LIKE '%$search%' 
                    OR tk.EMAIL LIKE '%$search%' 
                    OR cn.TEN_CN LIKE '%$search%')";
    }

    if (!empty($branch)) {
        $branch = mysqli_real_escape_string($conn, $branch);
        $conditions[] = "nv.ID_CN = '$branch'";
    }

    if (!empty($role)) {
        $role = mysqli_real_escape_string($conn, $role);
        $conditions[] = "nv.LOAI_NV = '$role'";
    }

    if (!empty($conditions)) {
        $query .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $result = mysqli_query($conn, $query);
    return mysqli_fetch_assoc($result)['total'] ?? 0;
}


// Fetch branches for dropdown
function getBranches()
{
    global $conn;
    $query = "SELECT ID_CN, TEN_CN FROM chi_nhanh ORDER BY TEN_CN";
    $result = mysqli_query($conn, $query);
    $branches = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $branches[] = $row;
        }
    }

    return $branches;
}

function getEmployeeSummary()
{
    global $conn;
    $summary = [
        'total' => 0,
        'manager' => 0,
        'staff' => 0,
    ];

    $query = "SELECT LOAI_NV, COUNT(*) AS total FROM nhan_vien GROUP BY LOAI_NV";
    $result = mysqli_query($conn, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $summary['total'] += (int)$row['total'];
            if ($row['LOAI_NV'] === 'quan_ly') {
                $summary['manager'] = (int)$row['total'];
            } else {
                $summary['staff'] += (int)$row['total'];
            }
        }
    }

    return $summary;
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

$employees = getEmployeesSearch($search, $branchFilter, $roleFilter, $limit, $offset);
$totalRecords = countEmployees($search, $branchFilter, $roleFilter);
$totalPages = max(1, ceil($totalRecords / $limit));
$showingStart = $totalRecords ? $offset + 1 : 0;
$showingEnd = min($offset + $limit, $totalRecords);

$selectedBranch = (string)$branchFilter;
$selectedRole = (string)$roleFilter;
$employeeSummary = getEmployeeSummary();
$branchTotal = count($branches);
$activeBranchLabel = '';

if ($selectedBranch !== '') {
    foreach ($branches as $branch) {
        if ((string)$branch['ID_CN'] === $selectedBranch) {
            $activeBranchLabel = $branch['TEN_CN'];
            break;
        }
    }
}

?>


<!--  -->

<body class="bg-gray-100 p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-extrabold text-indigo-700 mb-6 text-center">Quản lý nhân viên</h1>

        <!-- Thông báo -->
        <?php if (isset($successMessage)): ?>
            <div class="bg-green-100 text-green-800 px-4 py-3 rounded mb-6 shadow"><?= $successMessage ?></div>
        <?php endif; ?>
        <?php if (isset($errorMessage)): ?>
            <div class="bg-red-100 text-red-700 px-4 py-3 rounded mb-6 shadow"><?= $errorMessage ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Tổng nhân viên</p>
                <p class="text-3xl font-bold text-indigo-700 mt-2"><?= number_format($employeeSummary['total']) ?></p>
                <p class="text-sm text-gray-500 mt-1">Bao gồm cả nhân sự thường và quản lý.</p>
            </div>
            <div class="bg-white rounded-xl shadow p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Chi nhánh</p>
                <p class="text-3xl font-bold text-emerald-600 mt-2"><?= number_format($branchTotal) ?></p>
                <p class="text-sm text-gray-500 mt-1">
                    <?= $activeBranchLabel ? 'Đang lọc: ' . htmlspecialchars($activeBranchLabel) : 'Tất cả chi nhánh đang được hiển thị.' ?>
                </p>
            </div>
            <div class="bg-white rounded-xl shadow p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Cơ cấu vai trò</p>
                <div class="flex items-end gap-6 mt-2">
                    <div>
                        <p class="text-2xl font-bold text-gray-800"><?= number_format($employeeSummary['manager']) ?></p>
                        <p class="text-sm text-gray-500">Quản lý</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-800"><?= number_format($employeeSummary['staff']) ?></p>
                        <p class="text-sm text-gray-500">Nhân sự thường</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bộ lọc + Thêm -->
        <div class="flex flex-wrap gap-4 mb-6">
            <form action="admin_dashboard.php" method="GET" class="w-full md:flex-1 bg-white rounded-xl shadow-lg p-4 space-y-6">
                <input type="hidden" name="page" value="employees">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="space-y-3">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Tìm kiếm</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex flex-col">
                                <label for="search" class="text-sm font-medium text-gray-700 mb-1">Từ khóa</label>
                                <input type="text" id="search" name="search" placeholder="Nhập tên, email hoặc chi nhánh"
                                    value="<?= htmlspecialchars($search) ?>"
                                    class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500 shadow-sm">
                            </div>
                            <div class="flex flex-col">
                                <label for="branch" class="text-sm font-medium text-gray-700 mb-1">Chi nhánh</label>
                                <select id="branch" name="branch" class="border border-gray-300 rounded-lg px-4 py-2 shadow-sm">
                                    <option value="">Tất cả chi nhánh</option>
                                    <?php if (!empty($branches)): ?>
                                        <?php foreach ($branches as $branch): ?>
                                            <option value="<?= htmlspecialchars($branch['ID_CN']) ?>" <?= $selectedBranch === (string)$branch['ID_CN'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($branch['TEN_CN']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>Chưa có dữ liệu chi nhánh</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Phân loại</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex flex-col">
                                <label for="role" class="text-sm font-medium text-gray-700 mb-1">Vai trò nội bộ</label>
                                <select id="role" name="role" class="border border-gray-300 rounded-lg px-4 py-2 shadow-sm">
                                    <option value="">Tất cả vai trò</option>
                                    <option value="chuyen_trach" <?= $selectedRole === 'chuyen_trach' ? 'selected' : '' ?>>Nhân sự thường</option>
                                    <option value="quan_ly" <?= $selectedRole === 'quan_ly' ? 'selected' : '' ?>>Quản lý chi nhánh</option>
                                </select>
                            </div>
                            <div class="flex flex-col">
                                <label for="per_page" class="text-sm font-medium text-gray-700 mb-1">Số bản ghi/trang</label>
                                <select id="per_page" name="per_page" class="border border-gray-300 rounded-lg px-4 py-2 shadow-sm">
                                    <?php foreach ($allowedPageSizes as $size): ?>
                                        <option value="<?= $size ?>" <?= $size === $perPage ? 'selected' : '' ?>><?= $size ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3 justify-end pt-4 border-t border-gray-100">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg shadow">Áp dụng bộ lọc</button>
                    <a href="?page=employees" class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg shadow hover:bg-gray-50">Đặt lại</a>
                </div>
            </form>
            <div class="w-full md:w-64">
                <div class="bg-white rounded-xl shadow-lg p-4 h-full flex flex-col gap-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 mb-1">Tùy chọn nhanh</p>
                        <p class="text-lg font-bold text-gray-800">Thêm nhân viên mới</p>
                    </div>
                    <a href="?page=employees&add"
                        class="inline-flex items-center justify-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold shadow">
                        Thêm nhân viên
                    </a>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap justify-between items-center text-sm text-gray-600 bg-white px-4 py-3 rounded-xl shadow mb-6">
            <p>Hiển thị <?= $showingStart ?> - <?= $showingEnd ?> trên tổng <?= $totalRecords ?> nhân viên</p>
            <p>Trang <?= $page ?> / <?= $totalPages ?></p>
        </div>

        <!-- Danh sách nhân viên -->
        <div class="overflow-x-auto bg-white rounded-xl shadow-lg">
            <table class="min-w-full table-auto text-sm">
                <thead class="bg-indigo-100 text-indigo-700">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Họ tên &amp; chuyên môn</th>
                        <th class="px-4 py-3">Chi nhánh</th>
                        <th class="px-4 py-3">Liên hệ</th>
                        <th class="px-4 py-3">Vai trò</th>
                        <th class="px-4 py-3">Hành động</th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    <?php if (mysqli_num_rows($employees) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($employees)): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-left md:text-center font-semibold text-gray-800"><?= htmlspecialchars($row['ID_TK']) ?></td>
                                <td class="px-4 py-3 text-left">
                                    <p class="font-semibold text-gray-900"><?= htmlspecialchars($row['HO_TEN']) ?></p>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($row['CHUYEN_MON']) ?></p>
                                </td>
                                <td class="px-4 py-3 text-left md:text-center"><?= htmlspecialchars($row['TEN_CN']) ?></td>
                                <td class="px-4 py-3 text-left">
                                    <p><?= htmlspecialchars($row['EMAIL']) ?></p>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($row['SDT']) ?></p>
                                </td>

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
                                    <div class="flex flex-wrap justify-center gap-2">
                                        <a href="?page=employees&edit=<?= urlencode($row['ID_TK']) ?>"
                                            class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-indigo-700 border border-indigo-200 rounded-lg hover:bg-indigo-50">
                                            Sửa
                                        </a>
                                        <a href="?page=employees&delete=<?= urlencode($row['ID_TK']) ?>"
                                            class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-red-600 border border-red-200 rounded-lg hover:bg-red-50">
                                            Xóa
                                        </a>
                                    </div>
                                </td>

                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-red-600 font-bold py-6">Không tìm thấy nhân viên nào.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        echo '<div class="mt-6 flex flex-wrap justify-center gap-2">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $queryString = http_build_query([
                'page' => 'employees',
                'page_num' => $i,
                'search' => $search,
                'branch' => $branchFilter,
                'role' => $roleFilter,
                'per_page' => $perPage,
            ]);
            $active = ($i == $page) ? 'bg-indigo-600 text-white' : 'bg-gray-200 hover:bg-gray-300';
            echo "<a href='?$queryString' class='px-3 py-1 rounded $active'>$i</a>";
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
                    <?= isset($_GET['add']) ? 'Thêm nhân viên mới' : 'Sửa thông tin nhân viên' ?>
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
                        <input type="hidden" name="ID_TK" value="<?= htmlspecialchars($editEmployee['ID_TK']) ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Họ tên</label>
                        <input type="text" name="HO_TEN" value="<?= htmlspecialchars($editEmployee['HO_TEN'] ?? '') ?>" required
                            class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Ngày sinh</label>
                        <input type="date" name="NGAY_SINH" value="<?= htmlspecialchars($editEmployee['NGAY_SINH'] ?? '') ?>"
                            class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Địa chỉ</label>
                        <input type="text" name="DIA_CHI" value="<?= htmlspecialchars($editEmployee['DIA_CHI'] ?? '') ?>" required
                            class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Số điện thoại</label>
                        <input type="text" name="SDT" value="<?= htmlspecialchars($editEmployee['SDT'] ?? '') ?>" required
                            class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="EMAIL" value="<?= htmlspecialchars($editEmployee['EMAIL'] ?? '') ?>" required
                            class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Chi nhánh</label>
                        <select name="ID_CN" class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" required>
                            <?php if (!empty($branches)): ?>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?= htmlspecialchars($branch['ID_CN']) ?>"
                                        <?= isset($editEmployee) && (string)$editEmployee['ID_CN'] === (string)$branch['ID_CN'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($branch['TEN_CN']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>Chưa có dữ liệu chi nhánh</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Chuyên môn</label>
                        <input type="text" name="CHUYEN_MON" value="<?= htmlspecialchars($editEmployee['CHUYEN_MON'] ?? '') ?>"
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
                        Đóng form
                    </button>

                    <!-- Nút lưu -->
                    <button type="submit" name="<?= isset($_GET['add']) ? 'add_employee' : 'edit_employee' ?>"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2 rounded-lg shadow transition">
                        <?= isset($_GET['add']) ? 'Thêm mới' : 'Cập nhật' ?>
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