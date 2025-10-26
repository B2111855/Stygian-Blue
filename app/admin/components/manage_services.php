<?php
include '../../database/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Xử lý tìm kiếm
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

$searchCondition = $search ? "WHERE dv.TEN_DV LIKE '%$search%' OR dv.MOTA_DV LIKE '%$search%'" : '';

// Tính tổng số dòng
$countQuery = "SELECT COUNT(*) as total FROM dich_vu dv $searchCondition";
$countResult = mysqli_query($conn, $countQuery);
$totalRows = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRows / $limit);

// Lấy dữ liệu có phân trang và đơn giá mới nhất
$getServicesQuery = "
    SELECT dv.*, dgdv.DON_GIA
    FROM dich_vu dv
    LEFT JOIN (
        SELECT d1.ID_DV, d1.DON_GIA
        FROM don_gia_dich_vu d1
        INNER JOIN (
            SELECT ID_DV, MAX(NGAY_GIO) AS MAX_DATE
            FROM don_gia_dich_vu
            GROUP BY ID_DV
        ) d2 ON d1.ID_DV = d2.ID_DV AND d1.NGAY_GIO = d2.MAX_DATE
    ) dgdv ON dv.ID_DV = dgdv.ID_DV
    $searchCondition
    LIMIT $offset, $limit
";
$services = mysqli_query($conn, $getServicesQuery);


// Handle adding or editing a service
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ten_dv = $_POST['TEN_DV'];
    $mota_dv = $_POST['MOTA_DV'];
    $thoi_gian = intval($_POST['THOI_GIAN']);
    $imagePath = null;

    // Handle image upload
    if (isset($_FILES['IMAGE']) && $_FILES['IMAGE']['error'] === UPLOAD_ERR_OK) {
        $image = $_FILES['IMAGE']['name'];
        $imageTmp = $_FILES['IMAGE']['tmp_name'];
        $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/public/images/dichvu/';
        $imagePath = 'public/images/dichvu/' . basename($image);

        // Create directory if it does not exist
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // Move file to target directory
        if (!move_uploaded_file($imageTmp, $targetDir . basename($image))) {
            $errorMessage = "Lỗi tải lên hình ảnh!";
        }
    }

    // Thêm dịch vụ vào cơ sở dữ liệu
    if (isset($_POST['add_service'])) {
        $gia = $_POST['GIA'];
        $thoi_gian = $_POST['THOI_GIAN'];
    
        // Kiểm tra hợp lệ
        if (!is_numeric($gia) || $gia <= 0) {
            $errorMessage = "Giá dịch vụ phải là một số dương hợp lệ.";
        } elseif (!is_numeric($thoi_gian) || $thoi_gian <= 0) {
            $errorMessage = "Thời lượng phải là số dương.";
        } else {
            // Bước 1: thêm dịch vụ mới
            $insertQuery = "INSERT INTO dich_vu (TEN_DV, MOTA_DV, IMAGE, THOI_GIAN) 
                            VALUES ('$ten_dv', '$mota_dv', '$imagePath', '$thoi_gian')";
    
            if (mysqli_query($conn, $insertQuery)) {
                $id_dv_new = mysqli_insert_id($conn);
    
                // Bước 2: Lấy hoặc thêm thời điểm hiện tại
                $now = date('Y-m-d H:i:s');
                $checkTime = mysqli_query($conn, "SELECT NGAY_GIO FROM thoi_diem WHERE NGAY_GIO = '$now'");
                if (mysqli_num_rows($checkTime) === 0) {
                    mysqli_query($conn, "INSERT INTO thoi_diem (NGAY_GIO) VALUES ('$now')");
                }
    
                // Bước 3: Thêm đơn giá
                $insertGia = "INSERT INTO don_gia_dich_vu (ID_DV, DON_GIA, NGAY_GIO) 
                              VALUES ('$id_dv_new', '$gia', '$now')";
                if (mysqli_query($conn, $insertGia)) {
                    $successMessage = "Dịch vụ đã được thêm thành công!";
                } else {
                    $errorMessage = "Dịch vụ được thêm nhưng lỗi khi thêm đơn giá: " . mysqli_error($conn);
                }
            } else {
                $errorMessage = "Lỗi khi thêm dịch vụ: " . mysqli_error($conn);
            }
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_service'])) {
    $id_dv = $_POST['ID_DV'];
    $ten_dv = $_POST['TEN_DV'];
    $mota_dv = $_POST['MOTA_DV'];
    $thoi_gian = intval($_POST['THOI_GIAN']);
    $gia_moi = $_POST['GIA'];
    $imagePath = null;

    if (isset($_FILES['IMAGE']) && $_FILES['IMAGE']['error'] === UPLOAD_ERR_OK) {
        $image = $_FILES['IMAGE']['name'];
        $imageTmp = $_FILES['IMAGE']['tmp_name'];
        $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/public/images/dichvu/';
        $imagePath = 'public/images/dichvu/' . basename($image);

        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        if (!move_uploaded_file($imageTmp, $targetDir . basename($image))) {
            $errorMessage = "Lỗi tải lên hình ảnh!";
        }
    }

    $updateQuery = "UPDATE dich_vu SET TEN_DV = '$ten_dv', MOTA_DV = '$mota_dv', THOI_GIAN = $thoi_gian";
    if ($imagePath) {
        $updateQuery .= ", IMAGE = '$imagePath'";
    }
    $updateQuery .= " WHERE ID_DV = '$id_dv'";

    if (mysqli_query($conn, $updateQuery)) {
        // Thêm đơn giá mới nếu có
        if (is_numeric($gia_moi) && $gia_moi > 0) {
            $now = date('Y-m-d H:i:s');
            $checkTime = mysqli_query($conn, "SELECT NGAY_GIO FROM thoi_diem WHERE NGAY_GIO = '$now'");
            if (mysqli_num_rows($checkTime) === 0) {
                mysqli_query($conn, "INSERT INTO thoi_diem (NGAY_GIO) VALUES ('$now')");
            }
            mysqli_query($conn, "INSERT INTO don_gia_dich_vu (ID_DV, DON_GIA, NGAY_GIO) VALUES ('$id_dv', '$gia_moi', '$now')");
        }
        $successMessage = "Dịch vụ đã được cập nhật thành công!";
    } else {
        $errorMessage = "Lỗi khi cập nhật dịch vụ!";
    }
}

// Handle deleting a service
if (isset($_GET['delete'])) {
    $id_dv = $_GET['delete'];

    // Chỉ kiểm tra những lịch hẹn chưa hoàn thành hoặc chưa hủy
    $checkQuery = "SELECT COUNT(*) as count 
                   FROM LICH_HEN 
                   WHERE ID_DV = '$id_dv' 
                     AND TRANGTHAI NOT IN ('Đã hoàn thành', 'Đã hủy')";

    $result = mysqli_query($conn, $checkQuery);
    $row = mysqli_fetch_assoc($result);

    if ($row['count'] > 0) {
        $errorMessage = "Không thể xóa dịch vụ vì còn lịch hẹn đang xử lý!";
    } else {
        $deleteQuery = "DELETE FROM dich_vu WHERE ID_DV = '$id_dv'";
        if (mysqli_query($conn, $deleteQuery)) {
            $successMessage = "Dịch vụ đã được xóa!";
        } else {
            $errorMessage = "Lỗi khi xóa dịch vụ!";
        }
    }
}


// Fetch service data for editing if the 'edit' parameter is set
$isEditing = isset($_GET['edit']);
$editService = null;

if ($isEditing) {
    $id_dv = $_GET['edit'];
    $editServiceQuery = "SELECT * FROM dich_vu WHERE ID_DV = $id_dv";
    $editServiceResult = mysqli_query($conn, $editServiceQuery);
    $editService = mysqli_fetch_assoc($editServiceResult);
}
?>
<body class="bg-gray-100 p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold text-indigo-700 mb-6 text-center">📸 Quản lý dịch vụ</h1>

        <!-- Thông báo -->
        <?php if (isset($successMessage)) : ?>
            <div class="bg-green-100 text-green-800 p-4 rounded shadow mb-4"><?= $successMessage; ?></div>
        <?php endif; ?>
        <?php if (isset($errorMessage)) : ?>
            <div class="bg-red-100 text-red-700 p-4 rounded shadow mb-4"><?= $errorMessage; ?></div>
        <?php endif; ?>

        <!-- Thanh tìm kiếm và nút thêm -->
        <div class="flex flex-wrap justify-between items-center gap-2 mb-6">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="page" value="services">
                <input type="text" name="search" placeholder="🔍 Tìm theo tên hoặc mô tả" value="<?= htmlspecialchars($search) ?>"
                    class="p-2 border rounded w-64 shadow-sm">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded shadow">Tìm</button>
            </form>
            <a href="?page=services&add=true" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow">➕ Thêm dịch vụ</a>
        </div>

        <!-- Form Thêm Dịch vụ -->
        <?php if (isset($_GET['add']) && $_GET['add'] === 'true' && !$isEditing) : ?>
            <form method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded shadow mb-6">
                <h2 class="text-xl font-semibold text-indigo-600 mb-4">🆕 Thêm dịch vụ</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="text" name="TEN_DV" placeholder="Tên dịch vụ" class="p-2 border rounded" required>
                    <input type="number" name="THOI_GIAN" placeholder="Thời lượng (phút)" min="1" class="p-2 border rounded" required>
                    <textarea name="MOTA_DV" placeholder="Mô tả" class="p-2 border rounded md:col-span-2" required></textarea>
                    <input type="file" name="IMAGE" class="p-2 border rounded" required>
                    <input type="number" name="GIA" placeholder="Giá dịch vụ (VNĐ)" class="p-2 border rounded" required>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="submit" name="add_service" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">✅ Lưu</button>
                    <a href="?page=services" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">❌ Hủy</a>
                </div>
            </form>
        <?php endif; ?>

        <!-- Form Cập nhật Dịch vụ -->
        <?php if ($isEditing && $editService) : ?>
            <form method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded shadow mb-6">
                <h2 class="text-xl font-semibold text-indigo-600 mb-4">✏️ Cập nhật dịch vụ</h2>
                <input type="hidden" name="ID_DV" value="<?= $editService['ID_DV']; ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="text" name="TEN_DV" value="<?= $editService['TEN_DV']; ?>" class="p-2 border rounded" required>
                    <input type="number" name="THOI_GIAN" value="<?= $editService['THOI_GIAN']; ?>" min="1" class="p-2 border rounded" required>
                    <textarea name="MOTA_DV" class="p-2 border rounded md:col-span-2" required><?= $editService['MOTA_DV']; ?></textarea>
                    <input type="file" name="IMAGE" class="p-2 border rounded">
                    <input type="number" name="GIA" value="<?= $editService['DON_GIA'] ?? ''; ?>" class="p-2 border rounded" required>
                </div>
                <?php if ($editService['IMAGE']) : ?>
                    <div class="mt-4">
                        <img src="/<?= $editService['IMAGE']; ?>" class="w-32 h-32 object-cover rounded shadow" />
                    </div>
                <?php endif; ?>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="submit" name="edit_service" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">💾 Lưu</button>
                    <a href="?page=services" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">❌ Hủy</a>
                </div>
            </form>
        <?php endif; ?>

        <!-- Danh sách dịch vụ -->
        <div class="overflow-x-auto bg-white rounded shadow">
            <table class="min-w-full table-auto text-sm">
                <thead class="bg-indigo-100 text-indigo-700">
                    <tr>
                        <th class="p-3">ID</th>
                        <th class="p-3">Tên Dịch Vụ</th>
                        <th class="p-3">Mô Tả</th>
                        <th class="p-3">Thời lượng</th>
                        <th class="p-3">Giá</th>
                        <th class="p-3">Ảnh</th>
                        <th class="p-3">Hành động</th>
                    </tr>
                </thead>
                <tbody class="text-center divide-y">
                    <?php while ($service = mysqli_fetch_assoc($services)) : ?>
                        <?php
                        $phut = (int)$service['THOI_GIAN'];
                        $gio = floor($phut / 60);
                        $du_phut = $phut % 60;
                        $thoi_gian_dang_dep = "$gio giờ $du_phut phút";
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="p-3"><?= $service['ID_DV']; ?></td>
                            <td class="p-3"><?= htmlspecialchars($service['TEN_DV']); ?></td>
                            <td class="p-3"><?= mb_strimwidth($service['MOTA_DV'], 0, 30, "..."); ?></td>
                            <td class="p-3"><?= $thoi_gian_dang_dep ?></td>
                            <td class="p-3"><?= isset($service['DON_GIA']) ? number_format($service['DON_GIA'], 0, ',', '.') . ' VND' : 'Chưa có' ?></td>
                            <td class="p-3">
                                <img src="/<?= htmlspecialchars($service['IMAGE']); ?>" alt="Service Image" class="w-12 h-12 object-cover rounded">
                            </td>
                            <td class="p-3 flex justify-center gap-2">
                                <a href="?page=services&edit=<?= $service['ID_DV']; ?>"
                                   class="bg-yellow-500 hover:bg-yellow-600 text-white px-2 py-1 rounded text-xs">Sửa</a>
                                <a href="?page=services&delete=<?= $service['ID_DV']; ?>"
                                   onclick="return confirm('Bạn chắc chắn muốn xóa?');"
                                   class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs">Xóa</a>
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
                    <a href="?page=services&search=<?= urlencode($search) ?>&p=<?= $i ?>"
                       class="px-3 py-1 rounded border <?= ($i == $page) ? 'bg-indigo-600 text-white' : 'bg-white hover:bg-gray-100' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
