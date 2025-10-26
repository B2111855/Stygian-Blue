<?php
include '../../database/config.php';

// Phân trang & tìm kiếm
$limit = 5;
$page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_cn = isset($_GET['filter_cn']) ? (int)$_GET['filter_cn'] : '';
$filter_status = isset($_GET['filter_status']) ? mysqli_real_escape_string($conn, $_GET['filter_status']) : '';

$conditions = [];
if ($search) {
    $conditions[] = "(tb.TEN_TB LIKE '%$search%' OR tb.TINH_TRANG LIKE '%$search%')";
}
if ($filter_cn) {
    $conditions[] = "tb.ID_CN = $filter_cn";
}
if ($filter_status) {
    $conditions[] = "tb.TINH_TRANG = '$filter_status'";
}

$searchCondition = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Thêm thiết bị
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $ten_tb = $_POST['TEN_TB'];
    $id_cn = (int)$_POST['ID_CN'];
    $don_gia = (int)$_POST['DON_GIA'];
    $tinh_trang = 'Đang hoạt động';
    $ngay_bao_tri = date('Y-m-d');

    $image_path = '';
    if (isset($_FILES['IMAGE']) && $_FILES['IMAGE']['error'] === UPLOAD_ERR_OK) {
        $image = $_FILES['IMAGE']['name'];
        $imageTmp = $_FILES['IMAGE']['tmp_name'];
        $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/public/images/trang_thietbi/';
        $imagePath = 'public/images/trang_thietbi/' . basename($image);

        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        if (!move_uploaded_file($imageTmp, $targetDir . basename($image))) {
            $errorMessage = "Lỗi tải lên hình ảnh!";
        }
    }

    mysqli_query($conn, "INSERT INTO trang_thiet_bi (TEN_TB, ID_CN, TINH_TRANG, NGAY_BAO_TRI, IMAGE) VALUES ('$ten_tb', $id_cn, '$tinh_trang', '$ngay_bao_tri', '$image_path')");
    $id_tb = mysqli_insert_id($conn);
    mysqli_query($conn, "INSERT INTO don_gia_trang_thiet_bi (ID_TB, DON_GIA) VALUES ($id_tb, $don_gia)");

    $_SESSION['success'] = "Đã thêm thiết bị thành công.";
}

// Cập nhật thiết bị
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id_tb = (int)$_POST['ID_TB'];
    $ten_tb = $_POST['TEN_TB'];
    $id_cn = (int)$_POST['ID_CN'];
    $don_gia = (int)$_POST['DON_GIA'];
    $tinh_trang = $_POST['TINH_TRANG'];
    $ngay_bao_tri = $_POST['NGAY_BAO_TRI'];

    $update_image = '';
    if (!empty($_FILES['IMAGE']['name'])) {
        $filename = basename($_FILES['IMAGE']['name']);
        $target_path = '../../public/uploads/equipment/' . $filename;
        move_uploaded_file($_FILES['IMAGE']['tmp_name'], $target_path);
        $update_image = ", IMAGE = '$filename'";
    }

    mysqli_query($conn, "UPDATE trang_thiet_bi SET TEN_TB = '$ten_tb', ID_CN = $id_cn, TINH_TRANG = '$tinh_trang', NGAY_BAO_TRI = '$ngay_bao_tri' $update_image WHERE ID_TB = $id_tb");
    mysqli_query($conn, "UPDATE don_gia_trang_thiet_bi SET DON_GIA = $don_gia WHERE ID_TB = $id_tb");

    $_SESSION['success'] = "Đã cập nhật thiết bị thành công.";
}

// Xóa thiết bị
if (isset($_GET['delete'])) {
    $id_tb = (int)$_GET['delete'];
    $check = mysqli_query($conn, "
        SELECT 1 FROM lich_hen_thiet_bi lhtb
        JOIN lich_hen lh ON lhtb.ID_LICHHEN = lh.ID_LICHHEN
        WHERE lhtb.ID_TB = $id_tb AND lh.TRANGTHAI = 'Đã xác nhận'
    ");

    if (mysqli_num_rows($check) > 0) {
        $_SESSION['error'] = "Không thể xóa: thiết bị đang được sử dụng trong lịch hẹn đã xác nhận.";
    } else {
        mysqli_query($conn, "DELETE FROM don_gia_trang_thiet_bi WHERE ID_TB = $id_tb");
        mysqli_query($conn, "DELETE FROM trang_thiet_bi WHERE ID_TB = $id_tb");
        $_SESSION['success'] = "Đã xóa thiết bị thành công.";
    }
}

// Danh sách chi nhánh
$branches = mysqli_query($conn, "SELECT ID_CN, TEN_CN FROM chi_nhanh");
$branchMap = [];
while ($b = mysqli_fetch_assoc($branches)) {
    $branchMap[$b['ID_CN']] = $b['TEN_CN'];
}

// Lấy dữ liệu cần sửa nếu có
$editData = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $editResult = mysqli_query($conn, "
        SELECT tb.*, dgtb.DON_GIA
        FROM trang_thiet_bi tb
        JOIN don_gia_trang_thiet_bi dgtb ON tb.ID_TB = dgtb.ID_TB
        WHERE tb.ID_TB = $edit_id
    ");
    $editData = mysqli_fetch_assoc($editResult);
}

// Danh sách thiết bị
$totalResult = mysqli_query($conn, "SELECT COUNT(*) as total FROM trang_thiet_bi tb $searchCondition");
$totalRows = mysqli_fetch_assoc($totalResult)['total'];
$totalPages = ceil($totalRows / $limit);

$query = "SELECT tb.*, dgtb.DON_GIA FROM trang_thiet_bi tb JOIN don_gia_trang_thiet_bi dgtb ON tb.ID_TB = dgtb.ID_TB $searchCondition LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $query);
?>

<body class="bg-gray-100 p-6">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded mb-4">
            ✅ <?= $_SESSION['success'] ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4">
            ❌ <?= $_SESSION['error'] ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <div class="max-w-6xl mx-auto bg-white p-6 rounded-lg shadow-xl space-y-6">
        <h1 class="text-3xl font-bold text-indigo-700 text-center">📦 Quản Lý Thiết Bị</h1>


        <!-- Thanh tìm kiếm và nút thêm -->
        <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
            <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="page" value="equipment">
                <input type="text" name="search" placeholder="🔍 Tìm theo tên hoặc mô tả" value="<?= htmlspecialchars($search) ?>"
                    class="px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-72">
                <select name="filter_cn" class="border border-gray-300 rounded-lg px-3 py-2 shadow-sm">
                    <option value="">Tất cả chi nhánh</option>
                    <?php foreach ($branchMap as $id => $name): ?>
                        <option value="<?= $id ?>" <?= $filter_cn == $id ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="filter_status" class="border border-gray-300 rounded-lg px-3 py-2 shadow-sm">
                    <option value="">Tất cả tình trạng</option>
                    <option value="Đang hoạt động" <?= $filter_status === 'Đang hoạt động' ? 'selected' : '' ?>>Đang hoạt động</option>
                    <option value="Bảo trì" <?= $filter_status === 'Bảo trì' ? 'selected' : '' ?>>Bảo trì</option>
                    <option value="Ngưng sử dụng" <?= $filter_status === 'Ngưng sử dụng' ? 'selected' : '' ?>>Ngưng sử dụng</option>
                </select>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg shadow">
                    🔍 Tìm
                </button>
            </form>



            <button onclick="toggleForm()" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg shadow-lg font-semibold transition">
                ➕ Thêm Thiết Bị
            </button>
        </div>

        <!-- Form thêm -->
        <div id="addForm" class="hidden border border-gray-200 p-6 rounded-lg bg-gray-50">
            <form method="POST" enctype="multipart/form-data" class="grid md:grid-cols-2 gap-4">
                <input type="hidden" name="action" value="add">

                <div>
                    <label class="block font-medium mb-1">Tên thiết bị</label>
                    <input name="TEN_TB" required class="w-full border px-3 py-2 rounded focus:ring focus:ring-indigo-200">
                </div>

                <div>
                    <label class="block font-medium mb-1">Chi nhánh</label>
                    <select name="ID_CN" class="w-full border px-3 py-2 rounded focus:ring focus:ring-indigo-200">
                        <?php foreach ($branchMap as $id => $name): ?>
                            <option value="<?= $id ?>"><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-medium mb-1">Đơn giá (VND)</label>
                    <input type="number" name="DON_GIA" required class="w-full border px-3 py-2 rounded focus:ring focus:ring-indigo-200">
                </div>

                <div>
                    <label class="block font-medium mb-1">Ảnh thiết bị</label>
                    <input type="file" name="IMAGE" accept="image/*" class="w-full">
                </div>

                <div class="md:col-span-2 flex justify-between mt-4">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded shadow">
                        ✅ Thêm
                    </button>
                    <button type="button" onclick="toggleForm()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded shadow">
                        ❌ Đóng
                    </button>
                </div>
            </form>
        </div>

        <!-- Form sửa -->
        <?php if ($editData): ?>
            <div class="border p-6 rounded-lg bg-yellow-50">
                <h2 class="text-xl font-bold text-yellow-800 mb-4">✏️ Chỉnh sửa thiết bị #<?= $editData['ID_TB'] ?></h2>
                <form method="POST" enctype="multipart/form-data" class="grid md:grid-cols-2 gap-4">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="ID_TB" value="<?= $editData['ID_TB'] ?>">

                    <div>
                        <label class="block font-medium mb-1">Tên thiết bị</label>
                        <input name="TEN_TB" value="<?= htmlspecialchars($editData['TEN_TB']) ?>" class="w-full border px-3 py-2 rounded">
                    </div>

                    <div>
                        <label class="block font-medium mb-1">Chi nhánh</label>
                        <select name="ID_CN" class="w-full border px-3 py-2 rounded">
                            <?php foreach ($branchMap as $id => $name): ?>
                                <option value="<?= $id ?>" <?= $editData['ID_CN'] == $id ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium mb-1">Tình trạng</label>
                        <select name="TINH_TRANG" class="w-full border px-3 py-2 rounded">
                            <option <?= $editData['TINH_TRANG'] === 'Đang hoạt động' ? 'selected' : '' ?>>Đang hoạt động</option>
                            <option <?= $editData['TINH_TRANG'] === 'Bảo trì' ? 'selected' : '' ?>>Bảo trì</option>
                            <option <?= $editData['TINH_TRANG'] === 'Ngưng sử dụng' ? 'selected' : '' ?>>Ngưng sử dụng</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium mb-1">Ngày bảo trì</label>
                        <input type="date" name="NGAY_BAO_TRI" value="<?= $editData['NGAY_BAO_TRI'] ?>" class="w-full border px-3 py-2 rounded">
                    </div>

                    <div>
                        <label class="block font-medium mb-1">Đơn giá</label>
                        <input type="number" name="DON_GIA" value="<?= $editData['DON_GIA'] ?>" class="w-full border px-3 py-2 rounded">
                    </div>

                    <div>
                        <label class="block font-medium mb-1">Ảnh mới (nếu thay)</label>
                        <input type="file" name="IMAGE" accept="image/*" class="w-full">
                    </div>

                    <div class="md:col-span-2 flex justify-end gap-2 mt-4">
                        <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded">💾 Lưu</button>
                        <a href="?page=equipment" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">❌ Hủy</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Bảng danh sách thiết bị -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm border border-gray-300">
                <thead class="bg-indigo-600 text-white">
                    <tr>
                        <th class="px-4 py-2">ID</th>
                        <th class="px-4 py-2">Tên thiết bị</th>
                        <th class="px-4 py-2">Chi nhánh</th>
                        <th class="px-4 py-2">Tình trạng</th>
                        <th class="px-4 py-2">Ngày bảo trì</th>
                        <th class="px-4 py-2">Đơn giá</th>
                        <th class="px-4 py-2">Ảnh</th>
                        <th class="px-4 py-2">Hành động</th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="px-4 py-2 font-semibold">#<?= $row['ID_TB'] ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($row['TEN_TB']) ?></td>
                            <td class="px-4 py-2"><?= $branchMap[$row['ID_CN']] ?? 'Không xác định' ?></td>
                            <td class="px-4 py-2"><?= $row['TINH_TRANG'] ?></td>
                            <td class="px-4 py-2"><?= $row['NGAY_BAO_TRI'] ?></td>
                            <td class="px-4 py-2"><?= number_format($row['DON_GIA'], 0, ',', '.') ?> VND</td>
                            <td class="px-4 py-2">
                                <?php if ($row['IMAGE']): ?>
                                    <img src="../../public/uploads/equipment/<?= $row['IMAGE'] ?>" alt="Ảnh thiết bị" class="h-12 w-12 object-cover rounded-full mx-auto">
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 space-x-2 text-center">
                                <a href="?page=equipment&edit=<?= $row['ID_TB'] ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded">✏</a>
                                <a href="?page=equipment&delete=<?= $row['ID_TB'] ?>" onclick="return confirm('Xóa thiết bị này?')" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">🗑</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="mt-4 flex justify-center space-x-2">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=equipment&p=<?= $i ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 border rounded <?= ($i == $page) ? 'bg-indigo-600 text-white' : 'bg-white hover:bg-gray-100' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    <script>
        function toggleForm() {
            document.getElementById('addForm').classList.toggle('hidden');
        }
    </script>
</body>