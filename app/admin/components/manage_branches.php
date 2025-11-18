<?php
// Kết nối đến cơ sở dữ liệu
include '../../database/config.php';
require_once __DIR__ . '/../../helpers/assets.php';

// Thiết lập phân trang
$limit = 5; // Số bản ghi mỗi trang
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_sql = !empty($search) ? "WHERE TEN_CN LIKE ?" : "";


// Lấy danh sách chi nhánh
function getBranches($conn, $search, $limit, $offset) {
    $search_sql = !empty($search) ? "WHERE TEN_CN LIKE ?" : "";
    $query = "SELECT * FROM chi_nhanh $search_sql LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);

    if (!empty($search)) {
        $like = "%$search%";
        $stmt->bind_param("sii", $like, $limit, $offset);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }

    $stmt->execute();
    return $stmt->get_result();
}

$total_query = "SELECT COUNT(*) AS total FROM chi_nhanh " . (!empty($search) ? "WHERE TEN_CN LIKE ?" : "");
$stmt_total = $conn->prepare($total_query);
if (!empty($search)) {
    $like = "%$search%";
    $stmt_total->bind_param("s", $like);
}
$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_rows = $result_total->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);


// Thêm chi nhánh
if (isset($_POST['add_branch'])) {
    $branch_name = $_POST['branch_name'];
    $branch_phone = $_POST['branch_phone'];
    $branch_address = $_POST['branch_address'];

    if (empty($branch_name) || strlen($branch_name) > 100) {
        echo "<script>alert('❌ Tên chi nhánh không được bỏ trống và tối đa 100 ký tự!'); window.location.replace(window.location.href);</script>";
        exit;
    }
    if (!preg_match('/^[0-9]{9,12}$/', $branch_phone)) {
        echo "<script>alert('❌ Số điện thoại không hợp lệ!'); window.location.replace(window.location.href);</script>";
        exit;
    }
    if (empty($branch_address)) {
        echo "<script>alert('❌ Địa chỉ chi nhánh không được bỏ trống!'); window.location.replace(window.location.href);</script>";
        exit;
    }

    $check_query = "SELECT * FROM chi_nhanh WHERE TEN_CN = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("s", $branch_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<script>alert('❌ Tên chi nhánh đã tồn tại!'); window.location.replace(window.location.href);</script>";
        exit;
    } else {
        $query = "INSERT INTO chi_nhanh (TEN_CN, SDT_CN, DIA_CHI_CN) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $branch_name, $branch_phone, $branch_address);
        if ($stmt->execute()) {
            echo "<script>alert('✅ Thêm chi nhánh thành công!'); window.location.replace(window.location.href);</script>";
            exit;
        } else {
            echo "<script>alert('❌ Lỗi khi thêm chi nhánh: " . $stmt->error . "'); window.location.replace(window.location.href);</script>";
            exit;
        }
    }
}

// Xóa chi nhánh
if (isset($_POST['delete_branch'])) {
    $branch_id = $_POST['branch_id'];

    $res1 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM nhan_vien WHERE ID_CN = $branch_id"))['total'];
    $res2 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM trang_thiet_bi WHERE ID_CN = $branch_id"))['total'];
    $res3 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM lich_hen WHERE ID_CHINHANH = $branch_id AND TRANGTHAI = 'Đã xác nhận'"))['total'];

    if ($res1 > 0 || $res2 > 0 || $res3 > 0) {
        echo "<script>alert('❌ Không thể xóa chi nhánh vì còn liên kết với nhân viên, thiết bị hoặc lịch hẹn đã xác nhận.'); window.location.replace(window.location.href);</script>";
        exit;
    } else {
        $query = "DELETE FROM chi_nhanh WHERE ID_CN = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $branch_id);
        if ($stmt->execute()) {
            echo "<script>alert('✅ Xóa chi nhánh thành công!'); window.location.replace(window.location.href);</script>";
            exit;
        } else {
            echo "<script>alert('❌ Lỗi khi xóa chi nhánh: " . $stmt->error . "'); window.location.replace(window.location.href);</script>";
            exit;
        }
    }
}

// Cập nhật chi nhánh
if (isset($_POST['edit_branch'])) {
    $branch_id = $_POST['branch_id'];
    $branch_name = $_POST['branch_name'];
    $branch_phone = $_POST['branch_phone'];
    $branch_address = $_POST['branch_address'];

    if (empty($branch_name) || strlen($branch_name) > 100) {
        echo "<script>alert('❌ Tên chi nhánh không được bỏ trống và tối đa 100 ký tự!'); window.location.replace(window.location.href);</script>";
        exit;
    }
    if (!preg_match('/^[0-9]{9,12}$/', $branch_phone)) {
        echo "<script>alert('❌ Số điện thoại không hợp lệ!'); window.location.replace(window.location.href);</script>";
        exit;
    }
    if (empty($branch_address)) {
        echo "<script>alert('❌ Địa chỉ chi nhánh không được bỏ trống!'); window.location.replace(window.location.href);</script>";
        exit;
    }

    $query = "UPDATE chi_nhanh SET TEN_CN = ?, SDT_CN = ?, DIA_CHI_CN = ? WHERE ID_CN = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssi", $branch_name, $branch_phone, $branch_address, $branch_id);
    if ($stmt->execute()) {
        echo "<script>alert('✅ Cập nhật chi nhánh thành công!'); window.location.replace(window.location.href);</script>";
        exit;
    } else {
        echo "<script>alert('❌ Lỗi khi cập nhật chi nhánh: " . $stmt->error . "'); window.location.replace(window.location.href);</script>";
        exit;
    }
}

$branches = getBranches($conn, $search, $limit, $offset);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Chi nhánh</title>
    <?= sb_tailwind_link_tag(); ?>
    <script>
        function confirmDelete() {
            return confirm('Bạn chắc chắn muốn xóa chi nhánh này?');
        }

        function toggleForm(formId) {
            const form = document.getElementById(formId);
            form.classList.toggle('hidden');
        }

        function editBranch(id, name, phone, address) {
            toggleForm('edit_branch_form');
            document.getElementById('edit_branch_id').value = id;
            document.getElementById('edit_branch_name').value = name;
            document.getElementById('edit_branch_phone').value = phone;
            document.getElementById('edit_branch_address').value = address;
        }
    </script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-6xl mx-auto bg-white p-8 rounded-xl shadow-lg space-y-6">
        <h1 class="text-3xl font-bold text-indigo-700 mb-4 text-center">📍 Quản lý chi nhánh</h1>

        <!-- Thanh tìm kiếm và nút thêm -->
        <form method="GET" class="flex flex-wrap justify-between items-center gap-2 mb-4">
    <!-- Ô tìm kiếm và nút tìm -->
    <div class="flex items-center gap-2">
        <input type="text" name="search" placeholder="🔍 Tìm theo tên chi nhánh"
               value="<?= htmlspecialchars($search) ?>"
               class="border rounded px-4 py-2 w-64">
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded shadow">
            Tìm
        </button>
    </div>

    <!-- Nút thêm chi nhánh -->
    <button type="button" onclick="toggleForm('add_branch_form')"
            class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded shadow">
        ➕ Thêm chi nhánh
    </button>
</form>


        <!-- Bảng dữ liệu -->
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white rounded-lg shadow-md mt-4">
                <thead class="bg-indigo-500 text-white">
                    <tr>
                        <th class="py-2 px-4 border-b text-left">ID</th>
                        <th class="py-2 px-4 border-b text-left">Tên chi nhánh</th>
                        <th class="py-2 px-4 border-b text-left">Số điện thoại</th>
                        <th class="py-2 px-4 border-b text-left">Địa chỉ</th>
                        <th class="py-2 px-4 border-b text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($branch = mysqli_fetch_assoc($branches)): ?>
                    <tr class="hover:bg-gray-100">
                        <td class="py-2 px-4 border-b"><?= $branch['ID_CN'] ?></td>
                        <td class="py-2 px-4 border-b"><?= htmlspecialchars($branch['TEN_CN']) ?></td>
                        <td class="py-2 px-4 border-b"><?= htmlspecialchars($branch['SDT_CN']) ?></td>
                        <td class="py-2 px-4 border-b"><?= htmlspecialchars($branch['DIA_CHI_CN']) ?></td>
                        <td class="py-2 px-4 border-b text-center space-x-2">
                            <button onclick="editBranch('<?= $branch['ID_CN'] ?>','<?= htmlspecialchars($branch['TEN_CN']) ?>','<?= htmlspecialchars($branch['SDT_CN']) ?>','<?= htmlspecialchars($branch['DIA_CHI_CN']) ?>')"
                                    class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded">✏️ Sửa</button>
                            <form method="POST" onsubmit="return confirmDelete();" class="inline">
                                <input type="hidden" name="branch_id" value="<?= $branch['ID_CN'] ?>">
                                <button type="submit" name="delete_branch" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">🗑️ Xóa</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Phân trang -->
        <div class="flex justify-center items-center gap-2">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=branches&search=<?= urlencode($search) ?>&p=<?= $i ?>"
                   class="px-3 py-1 border rounded <?= $i == $page ? 'bg-indigo-600 text-white' : 'bg-gray-100' ?>">
                   <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>

        <!-- Form thêm -->
        <div id="add_branch_form" class="hidden mt-6 bg-gray-50 p-4 rounded-lg shadow space-y-2">
            <h2 class="text-xl font-semibold mb-2">➕ Thêm chi nhánh</h2>
            <form method="POST">
                <input type="text" name="branch_name" placeholder="Tên chi nhánh" required class="border px-4 py-2 rounded w-full">
                <input type="text" name="branch_phone" placeholder="Số điện thoại" required class="border px-4 py-2 rounded w-full">
                <input type="text" name="branch_address" placeholder="Địa chỉ" required class="border px-4 py-2 rounded w-full">
                <div class="flex justify-end gap-2 mt-2">
                    <button type="submit" name="add_branch" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Lưu</button>
                    <button type="button" onclick="toggleForm('add_branch_form')" class="bg-gray-400 text-white px-4 py-2 rounded">Hủy</button>
                </div>
            </form>
        </div>

        <!-- Form sửa -->
        <div id="edit_branch_form" class="hidden mt-6 bg-gray-50 p-4 rounded-lg shadow space-y-2">
            <h2 class="text-xl font-semibold mb-2">✏️ Sửa chi nhánh</h2>
            <form method="POST">
                <input type="hidden" id="edit_branch_id" name="branch_id">
                <input type="text" id="edit_branch_name" name="branch_name" required class="border px-4 py-2 rounded w-full">
                <input type="text" id="edit_branch_phone" name="branch_phone" required class="border px-4 py-2 rounded w-full">
                <input type="text" id="edit_branch_address" name="branch_address" required class="border px-4 py-2 rounded w-full">
                <div class="flex justify-end gap-2 mt-2">
                    <button type="submit" name="edit_branch" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded">Cập nhật</button>
                    <button type="button" onclick="toggleForm('edit_branch_form')" class="bg-gray-400 text-white px-4 py-2 rounded">Hủy</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>


