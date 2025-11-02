<?php
include '../../database/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

$statusOptions = [
    'san_sang'  => 'Sẵn sàng',
    'dang_thue' => 'Đang thuê',
    'bao_tri'   => 'Bảo trì',
    'ngung'     => 'Ngưng hoạt động',
];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirect_costume(string $type, string $message): void
{
    $_SESSION[$type] = $message;
    header('Location: ?page=costumes');
    exit;
}

// Helper: ensure category exists or create new
function resolve_category(mysqli $conn): ?int
{
    $idLoai = isset($_POST['ID_LOAI']) && $_POST['ID_LOAI'] !== '' ? (int) $_POST['ID_LOAI'] : null;
    $newLoai = trim($_POST['NEW_TEN_LOAI'] ?? '');
    $newMoTa = trim($_POST['NEW_MOTA_LOAI'] ?? '');

    if ($newLoai !== '') {
        $stmt = $conn->prepare('SELECT ID_LOAI FROM trang_phuc_loai WHERE LOWER(TEN_LOAI) = LOWER(?) LIMIT 1');
        $stmt->bind_param('s', $newLoai);
        $stmt->execute();
        $stmt->bind_result($existingId);
        if ($stmt->fetch()) {
            $idLoai = (int) $existingId;
        }
        $stmt->close();

        if (!$idLoai) {
            $now = date('Y-m-d H:i:s');
            $stmt = $conn->prepare('INSERT INTO trang_phuc_loai (TEN_LOAI, MOTA, IS_ACTIVE, CREATED_AT, UPDATED_AT) VALUES (?, ?, 1, ?, ?)');
            $stmt->bind_param('ssss', $newLoai, $newMoTa, $now, $now);
            if ($stmt->execute()) {
                $idLoai = $stmt->insert_id;
            }
            $stmt->close();
        }
    }

    return $idLoai;
}

// Helper: upload cover image and return relative path
function upload_costume_cover(array $file, string $fallbackName): ?string
{
    if (!isset($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $originalName = basename($file['name']);
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $safeName = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    $filename = $safeName !== '' ? $safeName : 'cover';
    $filename .= '_' . time();
    if ($extension) {
        $filename .= '.' . $extension;
    }

    $targetDir = dirname(__DIR__, 3) . '/public/images/trangphuc/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $targetPath = $targetDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return null;
    }

    return 'public/images/trangphuc/' . $filename;
}

// Handle add / edit actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $tenTp = trim($_POST['TEN_TP'] ?? '');
        $idCn = (int) ($_POST['ID_CN'] ?? 0);
        $size = trim($_POST['SIZE'] ?? '');
        $mau = trim($_POST['MAU'] ?? '');
        $tinhTrang = $_POST['TINH_TRANG'] ?? 'san_sang';
        $donGia = (int) ($_POST['DON_GIA'] ?? 0);
        $ngayGiat = trim($_POST['NGAY_GIAT_CUOI'] ?? '');
        $ghiChu = trim($_POST['GHI_CHU'] ?? '');

        if ($tenTp === '' || $idCn <= 0 || $donGia <= 0) {
            redirect_costume('error', 'Vui lòng nhập đầy đủ tên, chi nhánh và đơn giá hợp lệ.');
        }

        global $statusOptions;
        if (!array_key_exists($tinhTrang, $statusOptions)) {
            $tinhTrang = 'san_sang';
        }

        $idLoai = resolve_category($conn);
        $tenEsc = mysqli_real_escape_string($conn, $tenTp);
        $sizeEsc = mysqli_real_escape_string($conn, $size);
        $mauEsc = mysqli_real_escape_string($conn, $mau);
        $tinhEsc = mysqli_real_escape_string($conn, $tinhTrang);
        $ghiChuEsc = $ghiChu !== '' ? "'" . mysqli_real_escape_string($conn, $ghiChu) . "'" : 'NULL';
        $ngayGiatSql = $ngayGiat !== '' ? "'" . mysqli_real_escape_string($conn, $ngayGiat) . "'" : 'NULL';
        $idLoaiSql = $idLoai ? $idLoai : 'NULL';

        $insert = "INSERT INTO trang_phuc (ID_CN, ID_LOAI, TEN_TP, SIZE, MAU, TINH_TRANG, NGAY_GIAT_CUOI, GHI_CHU, IS_ACTIVE, CREATED_AT, UPDATED_AT) VALUES ($idCn, $idLoaiSql, '$tenEsc', '$sizeEsc', '$mauEsc', '$tinhEsc', $ngayGiatSql, $ghiChuEsc, 1, NOW(), NOW())";
        if (!mysqli_query($conn, $insert)) {
            redirect_costume('error', 'Không thể thêm trang phục mới: ' . mysqli_error($conn));
        }

        $idTp = mysqli_insert_id($conn);
        $now = date('Y-m-d H:i:s');
        mysqli_query($conn, "INSERT INTO don_gia_trang_phuc (ID_TP, NGAY_GIO, DON_GIA) VALUES ($idTp, '$now', $donGia)");

        $coverPath = upload_costume_cover($_FILES['COVER_IMAGE'] ?? [], $tenTp);
        if ($coverPath) {
            $coverEsc = mysqli_real_escape_string($conn, $coverPath);
            $alt = 'Ảnh bìa trang phục ' . $tenTp;
            $altEsc = mysqli_real_escape_string($conn, $alt);
            mysqli_query($conn, "UPDATE trang_phuc_hinh_anh SET IS_COVER = 0 WHERE ID_TP = $idTp");
            mysqli_query($conn, "INSERT INTO trang_phuc_hinh_anh (ID_TP, URL, ALT_TEXT, THU_TU, IS_COVER, IS_ACTIVE, CREATED_AT, UPDATED_AT) VALUES ($idTp, '$coverEsc', '$altEsc', 1, 1, 1, NOW(), NOW())");
        }

        redirect_costume('success', 'Đã thêm trang phục mới thành công.');
    }

    if ($action === 'edit') {
        $idTp = (int) ($_POST['ID_TP'] ?? 0);
        if ($idTp <= 0) {
            redirect_costume('error', 'Trang phục không hợp lệ.');
        }

        $tenTp = trim($_POST['TEN_TP'] ?? '');
        $idCn = (int) ($_POST['ID_CN'] ?? 0);
        $size = trim($_POST['SIZE'] ?? '');
        $mau = trim($_POST['MAU'] ?? '');
        $tinhTrang = $_POST['TINH_TRANG'] ?? 'san_sang';
        $donGia = (int) ($_POST['DON_GIA'] ?? 0);
        $ngayGiat = trim($_POST['NGAY_GIAT_CUOI'] ?? '');
        $ghiChu = trim($_POST['GHI_CHU'] ?? '');
        $isActive = isset($_POST['IS_ACTIVE']) ? 1 : 0;

        if ($tenTp === '' || $idCn <= 0) {
            redirect_costume('error', 'Vui lòng nhập đầy đủ tên trang phục và chọn chi nhánh.');
        }

        global $statusOptions;
        if (!array_key_exists($tinhTrang, $statusOptions)) {
            $tinhTrang = 'san_sang';
        }

        $idLoai = resolve_category($conn);
        $tenEsc = mysqli_real_escape_string($conn, $tenTp);
        $sizeEsc = mysqli_real_escape_string($conn, $size);
        $mauEsc = mysqli_real_escape_string($conn, $mau);
        $tinhEsc = mysqli_real_escape_string($conn, $tinhTrang);
        $ghiChuEsc = $ghiChu !== '' ? "'" . mysqli_real_escape_string($conn, $ghiChu) . "'" : 'NULL';
        $ngayGiatSql = $ngayGiat !== '' ? "'" . mysqli_real_escape_string($conn, $ngayGiat) . "'" : 'NULL';
        $idLoaiSql = $idLoai ? $idLoai : 'NULL';

        $update = "UPDATE trang_phuc SET ID_CN = $idCn, ID_LOAI = $idLoaiSql, TEN_TP = '$tenEsc', SIZE = '$sizeEsc', MAU = '$mauEsc', TINH_TRANG = '$tinhEsc', NGAY_GIAT_CUOI = $ngayGiatSql, GHI_CHU = $ghiChuEsc, IS_ACTIVE = $isActive, UPDATED_AT = NOW() WHERE ID_TP = $idTp";
        if (!mysqli_query($conn, $update)) {
            redirect_costume('error', 'Không thể cập nhật trang phục: ' . mysqli_error($conn));
        }

        if ($donGia > 0) {
            $latest = mysqli_query($conn, "SELECT DON_GIA FROM don_gia_trang_phuc WHERE ID_TP = $idTp ORDER BY NGAY_GIO DESC LIMIT 1");
            $latestVal = $latest && mysqli_num_rows($latest) ? (int) mysqli_fetch_assoc($latest)['DON_GIA'] : 0;
            if ($latestVal !== $donGia) {
                $now = date('Y-m-d H:i:s');
                mysqli_query($conn, "INSERT INTO don_gia_trang_phuc (ID_TP, NGAY_GIO, DON_GIA) VALUES ($idTp, '$now', $donGia)");
            }
        }

        $coverPath = upload_costume_cover($_FILES['COVER_IMAGE'] ?? [], $tenTp);
        if ($coverPath) {
            $coverEsc = mysqli_real_escape_string($conn, $coverPath);
            $alt = 'Ảnh bìa trang phục ' . $tenTp;
            $altEsc = mysqli_real_escape_string($conn, $alt);
            mysqli_query($conn, "UPDATE trang_phuc_hinh_anh SET IS_COVER = 0 WHERE ID_TP = $idTp");
            mysqli_query($conn, "INSERT INTO trang_phuc_hinh_anh (ID_TP, URL, ALT_TEXT, THU_TU, IS_COVER, IS_ACTIVE, CREATED_AT, UPDATED_AT) VALUES ($idTp, '$coverEsc', '$altEsc', 1, 1, 1, NOW(), NOW())");
        }

        redirect_costume('success', 'Đã cập nhật trang phục thành công.');
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];
    if ($deleteId > 0) {
        $check = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM don_thue_trang_phuc_ct ct JOIN don_thue_trang_phuc ttp ON ct.ID_TTP = ttp.ID_TTP WHERE ct.ID_TP = $deleteId AND ttp.TRANG_THAI IN ('cho_duyet','da_duyet','dang_thue')");
        $row = $check ? mysqli_fetch_assoc($check) : ['cnt' => 0];
        if ((int) $row['cnt'] > 0) {
            redirect_costume('error', 'Không thể xóa: trang phục đang được đặt/thuê.');
        }

        mysqli_query($conn, "DELETE FROM trang_phuc_hinh_anh WHERE ID_TP = $deleteId");
        mysqli_query($conn, "DELETE FROM don_gia_trang_phuc WHERE ID_TP = $deleteId");
        mysqli_query($conn, "DELETE FROM trang_phuc WHERE ID_TP = $deleteId");
        redirect_costume('success', 'Đã xóa trang phục khỏi hệ thống.');
    }
}

// Filters & pagination
$limit = 6;
$page = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterBranch = isset($_GET['filter_branch']) ? (int) $_GET['filter_branch'] : 0;
$filterStatus = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filterCategory = isset($_GET['filter_category']) ? (int) $_GET['filter_category'] : 0;
$filterActive = isset($_GET['filter_active']) ? $_GET['filter_active'] : '';

$conditions = [];
if ($search !== '') {
    $searchEsc = mysqli_real_escape_string($conn, $search);
    $conditions[] = "(tp.TEN_TP LIKE '%$searchEsc%' OR tp.MAU LIKE '%$searchEsc%' OR tp.SIZE LIKE '%$searchEsc%' OR tp.GHI_CHU LIKE '%$searchEsc%')";
}
if ($filterBranch > 0) {
    $conditions[] = "tp.ID_CN = $filterBranch";
}
if ($filterCategory > 0) {
    $conditions[] = "tp.ID_LOAI = $filterCategory";
}
if ($filterStatus !== '' && array_key_exists($filterStatus, $statusOptions)) {
    $statusEsc = mysqli_real_escape_string($conn, $filterStatus);
    $conditions[] = "tp.TINH_TRANG = '$statusEsc'";
}
if ($filterActive !== '') {
    $conditions[] = $filterActive === '1' ? 'tp.IS_ACTIVE = 1' : 'tp.IS_ACTIVE = 0';
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Data sources for select boxes
$branchesRes = mysqli_query($conn, 'SELECT ID_CN, TEN_CN FROM chi_nhanh ORDER BY TEN_CN');
$branches = [];
if ($branchesRes) {
    while ($row = mysqli_fetch_assoc($branchesRes)) {
        $branches[(int) $row['ID_CN']] = $row['TEN_CN'];
    }
}

$categoriesRes = mysqli_query($conn, 'SELECT ID_LOAI, TEN_LOAI FROM trang_phuc_loai WHERE IS_ACTIVE = 1 ORDER BY TEN_LOAI');
$categories = [];
if ($categoriesRes) {
    while ($row = mysqli_fetch_assoc($categoriesRes)) {
        $categories[(int) $row['ID_LOAI']] = $row['TEN_LOAI'];
    }
}

// Fetch edit data if requested
$editData = null;
$priceHistory = [];
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $editQuery = "
        SELECT tp.*, cn.TEN_CN, tl.TEN_LOAI,
               price.DON_GIA AS DON_GIA_HIEN_TAI,
               price.NGAY_GIO AS GIA_CAP_NHAT,
               cover.URL AS COVER_URL
        FROM trang_phuc tp
        LEFT JOIN chi_nhanh cn ON cn.ID_CN = tp.ID_CN
        LEFT JOIN trang_phuc_loai tl ON tl.ID_LOAI = tp.ID_LOAI
        LEFT JOIN (
            SELECT d1.ID_TP, d1.DON_GIA, d1.NGAY_GIO
            FROM don_gia_trang_phuc d1
            INNER JOIN (
                SELECT ID_TP, MAX(NGAY_GIO) AS NGAY_GIO
                FROM don_gia_trang_phuc
                GROUP BY ID_TP
            ) latest ON latest.ID_TP = d1.ID_TP AND latest.NGAY_GIO = d1.NGAY_GIO
        ) price ON price.ID_TP = tp.ID_TP
        LEFT JOIN (
            SELECT ID_TP, SUBSTRING_INDEX(GROUP_CONCAT(URL ORDER BY UPDATED_AT DESC), ',', 1) AS URL
            FROM trang_phuc_hinh_anh
            WHERE IS_ACTIVE = 1 AND IS_COVER = 1
            GROUP BY ID_TP
        ) cover ON cover.ID_TP = tp.ID_TP
        WHERE tp.ID_TP = $editId
        LIMIT 1
    ";
    $editResult = mysqli_query($conn, $editQuery);
    if ($editResult && mysqli_num_rows($editResult)) {
        $editData = mysqli_fetch_assoc($editResult);
        $histRes = mysqli_query($conn, "SELECT DON_GIA, NGAY_GIO FROM don_gia_trang_phuc WHERE ID_TP = $editId ORDER BY NGAY_GIO DESC");
        if ($histRes) {
            while ($row = mysqli_fetch_assoc($histRes)) {
                $priceHistory[] = $row;
            }
        }
    }
}

$baseQuery = "
    FROM trang_phuc tp
    LEFT JOIN chi_nhanh cn ON cn.ID_CN = tp.ID_CN
    LEFT JOIN trang_phuc_loai tl ON tl.ID_LOAI = tp.ID_LOAI
    LEFT JOIN (
        SELECT d1.ID_TP, d1.DON_GIA, d1.NGAY_GIO
        FROM don_gia_trang_phuc d1
        INNER JOIN (
            SELECT ID_TP, MAX(NGAY_GIO) AS NGAY_GIO
            FROM don_gia_trang_phuc
            GROUP BY ID_TP
        ) latest ON latest.ID_TP = d1.ID_TP AND latest.NGAY_GIO = d1.NGAY_GIO
    ) price ON price.ID_TP = tp.ID_TP
    LEFT JOIN (
        SELECT ID_TP, SUBSTRING_INDEX(GROUP_CONCAT(URL ORDER BY UPDATED_AT DESC), ',', 1) AS URL
        FROM trang_phuc_hinh_anh
        WHERE IS_ACTIVE = 1 AND IS_COVER = 1
        GROUP BY ID_TP
    ) cover ON cover.ID_TP = tp.ID_TP
    $where
";

$totalResult = mysqli_query($conn, "SELECT COUNT(*) AS total $baseQuery");
$totalRows = $totalResult ? (int) mysqli_fetch_assoc($totalResult)['total'] : 0;
$totalPages = max(1, (int) ceil($totalRows / $limit));

$listQuery = "
    SELECT tp.ID_TP, tp.TEN_TP, tp.SIZE, tp.MAU, tp.TINH_TRANG, tp.IS_ACTIVE,
           tp.NGAY_GIAT_CUOI, tp.GHI_CHU, tp.UPDATED_AT,
           cn.TEN_CN, tl.TEN_LOAI,
           price.DON_GIA, price.NGAY_GIO,
           cover.URL AS COVER_URL
    $baseQuery
    ORDER BY tp.UPDATED_AT DESC
    LIMIT $limit OFFSET $offset
";
$listResult = mysqli_query($conn, $listQuery);
$listCount = $listResult ? mysqli_num_rows($listResult) : 0;
?>

<div class="max-w-7xl mx-auto bg-white p-6 rounded-xl shadow-lg space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-3xl font-bold text-indigo-700">🎭 Quản Lý Trang Phục</h1>
            <p class="text-gray-500">Theo dõi tồn kho, tình trạng và đơn giá trang phục tại các chi nhánh.</p>
        </div>
        <?php if ($editData): ?>
            <a href="?page=costumes" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                <i class="fas fa-arrow-left"></i> Hủy chỉnh sửa
            </a>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-2 rounded">
            ✅ <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-2 rounded">
            ❌ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3 bg-gray-50 p-4 rounded-lg">
        <input type="hidden" name="page" value="costumes">
        <div class="lg:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Tìm kiếm</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tên, màu sắc, kích thước..." class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Chi nhánh</label>
            <select name="filter_branch" class="w-full px-3 py-2 border rounded-lg">
                <option value="0">Tất cả</option>
                <?php foreach ($branches as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $filterBranch === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Loại</label>
            <select name="filter_category" class="w-full px-3 py-2 border rounded-lg">
                <option value="0">Tất cả</option>
                <?php foreach ($categories as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $filterCategory === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Tình trạng</label>
            <select name="filter_status" class="w-full px-3 py-2 border rounded-lg">
                <option value="">Tất cả</option>
                <?php foreach ($statusOptions as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $filterStatus === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Hoạt động</label>
            <select name="filter_active" class="w-full px-3 py-2 border rounded-lg">
                <option value="">Tất cả</option>
                <option value="1" <?= $filterActive === '1' ? 'selected' : '' ?>>Đang sử dụng</option>
                <option value="0" <?= $filterActive === '0' ? 'selected' : '' ?>>Ngưng dùng</option>
            </select>
        </div>
        <div class="lg:col-span-6 flex justify-end gap-2">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition"><i class="fas fa-search mr-2"></i>Lọc</button>
            <a href="?page=costumes" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Đặt lại</a>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Trang phục</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Chi nhánh</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Loại</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Kích thước</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Đơn giá</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tình trạng</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Ngày giặt cuối</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Thao tác</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($listCount > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($listResult)): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($row['COVER_URL'])): ?>
                                        <img src="/<?= htmlspecialchars($row['COVER_URL']) ?>" alt="<?= htmlspecialchars($row['TEN_TP']) ?>" class="w-12 h-12 rounded object-cover border" />
                                    <?php else: ?>
                                        <div class="w-12 h-12 flex items-center justify-center bg-gray-200 rounded text-gray-500"><i class="fas fa-tshirt"></i></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="font-semibold text-gray-900"><?= htmlspecialchars($row['TEN_TP']) ?></div>
                                        <?php if ($row['GHI_CHU']): ?>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($row['GHI_CHU']) ?></div>
                                        <?php endif; ?>
                                        <div class="text-xs text-gray-400">Cập nhật: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['UPDATED_AT']))) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($row['TEN_CN'] ?? '—') ?></td>
                            <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($row['TEN_LOAI'] ?? 'Chưa phân loại') ?></td>
                            <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($row['SIZE'] ?: '—') ?> / <?= htmlspecialchars($row['MAU'] ?: '—') ?></td>
                            <td class="px-4 py-3 text-sm font-semibold text-emerald-600">
                                <?php if ($row['DON_GIA'] !== null): ?>
                                    <?= number_format((float) $row['DON_GIA'], 0, ',', '.') ?> ₫
                                <?php else: ?>
                                    <span class="text-gray-400">Chưa có</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium
                                    <?= $row['TINH_TRANG'] === 'san_sang' ? 'bg-emerald-100 text-emerald-700' : '' ?>
                                    <?= $row['TINH_TRANG'] === 'dang_thue' ? 'bg-blue-100 text-blue-700' : '' ?>
                                    <?= $row['TINH_TRANG'] === 'bao_tri' ? 'bg-yellow-100 text-yellow-700' : '' ?>
                                    <?= $row['TINH_TRANG'] === 'ngung' ? 'bg-gray-200 text-gray-600' : '' ?>">
                                    <span class="w-2 h-2 rounded-full bg-current"></span>
                                    <?= htmlspecialchars($statusOptions[$row['TINH_TRANG']] ?? $row['TINH_TRANG']) ?>
                                </span>
                                <?php if (!(int) $row['IS_ACTIVE']): ?>
                                    <div class="text-xs text-red-500">Đã ngưng hoạt động</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <?php if ($row['NGAY_GIAT_CUOI'] && $row['NGAY_GIAT_CUOI'] !== '0000-00-00'): ?>
                                    <?= htmlspecialchars(date('d/m/Y', strtotime($row['NGAY_GIAT_CUOI']))) ?>
                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-right space-x-2 whitespace-nowrap">
                                <a href="?page=costumes&edit=<?= $row['ID_TP'] ?>" class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded hover:bg-indigo-100"><i class="fas fa-edit"></i>Sửa</a>
                                <a href="?page=costumes&delete=<?= $row['ID_TP'] ?>" onclick="return confirm('Xóa trang phục này? Hành động không thể hoàn tác.');" class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-50 text-red-600 rounded hover:bg-red-100"><i class="fas fa-trash"></i>Xóa</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">Chưa có dữ liệu trang phục phù hợp.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex justify-between items-center text-sm text-gray-600">
        <div>
            Hiển thị <?= $listCount ?> / <?= $totalRows ?> trang phục
        </div>
        <div class="flex items-center gap-1">
            <?php if ($page > 1): ?>
                <a href="?page=costumes&p=<?= $page - 1 ?>" class="px-3 py-1 border rounded-lg hover:bg-gray-100">« Trước</a>
            <?php else: ?>
                <span class="px-3 py-1 border rounded-lg text-gray-400">« Trước</span>
            <?php endif; ?>
            <span class="px-3 py-1">Trang <?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?page=costumes&p=<?= $page + 1 ?>" class="px-3 py-1 border rounded-lg hover:bg-gray-100">Tiếp »</a>
            <?php else: ?>
                <span class="px-3 py-1 border rounded-lg text-gray-400">Tiếp »</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-gray-50 p-5 rounded-lg border border-gray-200">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">➕ Thêm trang phục mới</h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="add">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Tên trang phục *</label>
                    <input type="text" name="TEN_TP" required class="w-full px-3 py-2 border rounded-lg" placeholder="Ví dụ: Áo cưới ren dáng A">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Chi nhánh *</label>
                        <select name="ID_CN" required class="w-full px-3 py-2 border rounded-lg">
                            <option value="">-- Chọn chi nhánh --</option>
                            <?php foreach ($branches as $id => $name): ?>
                                <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Tình trạng</label>
                        <select name="TINH_TRANG" class="w-full px-3 py-2 border rounded-lg">
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $value === 'san_sang' ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Kích thước</label>
                        <input type="text" name="SIZE" class="w-full px-3 py-2 border rounded-lg" placeholder="S, M, L...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Màu sắc</label>
                        <input type="text" name="MAU" class="w-full px-3 py-2 border rounded-lg" placeholder="Trắng, đỏ...">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Loại trang phục</label>
                    <select name="ID_LOAI" class="w-full px-3 py-2 border rounded-lg">
                        <option value="">-- Chọn loại sẵn có --</option>
                        <?php foreach ($categories as $id => $name): ?>
                            <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Hoặc thêm loại mới bên dưới:</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Tên loại mới</label>
                        <input type="text" name="NEW_TEN_LOAI" class="w-full px-3 py-2 border rounded-lg" placeholder="Ví dụ: Áo cưới">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Mô tả loại</label>
                        <input type="text" name="NEW_MOTA_LOAI" class="w-full px-3 py-2 border rounded-lg" placeholder="Mô tả ngắn">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Đơn giá thuê (₫) *</label>
                        <input type="number" name="DON_GIA" min="0" required class="w-full px-3 py-2 border rounded-lg" placeholder="Ví dụ: 500000">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Ngày giặt gần nhất</label>
                        <input type="date" name="NGAY_GIAT_CUOI" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Ghi chú</label>
                    <textarea name="GHI_CHU" rows="2" class="w-full px-3 py-2 border rounded-lg" placeholder="Thông tin bảo quản, phụ kiện kèm theo..."></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Ảnh bìa (tùy chọn)</label>
                    <input type="file" name="COVER_IMAGE" accept="image/*" class="w-full text-sm text-gray-600">
                </div>
                <div class="pt-2">
                    <button type="submit" class="w-full inline-flex justify-center items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-plus-circle"></i> Thêm trang phục
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-gray-50 p-5 rounded-lg border border-gray-200">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">✏️ Chỉnh sửa trang phục</h2>
            <?php if ($editData): ?>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="ID_TP" value="<?= $editData['ID_TP'] ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Tên trang phục *</label>
                        <input type="text" name="TEN_TP" value="<?= htmlspecialchars($editData['TEN_TP']) ?>" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Chi nhánh *</label>
                            <select name="ID_CN" required class="w-full px-3 py-2 border rounded-lg">
                                <?php foreach ($branches as $id => $name): ?>
                                    <option value="<?= $id ?>" <?= (int) $editData['ID_CN'] === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Tình trạng</label>
                            <select name="TINH_TRANG" class="w-full px-3 py-2 border rounded-lg">
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= $editData['TINH_TRANG'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Kích thước</label>
                            <input type="text" name="SIZE" value="<?= htmlspecialchars($editData['SIZE']) ?>" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Màu sắc</label>
                            <input type="text" name="MAU" value="<?= htmlspecialchars($editData['MAU']) ?>" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Loại trang phục</label>
                        <select name="ID_LOAI" class="w-full px-3 py-2 border rounded-lg">
                            <option value="">-- Không phân loại --</option>
                            <?php foreach ($categories as $id => $name): ?>
                                <option value="<?= $id ?>" <?= (int) $editData['ID_LOAI'] === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Hoặc nhập loại mới:</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Tên loại mới</label>
                            <input type="text" name="NEW_TEN_LOAI" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Mô tả loại</label>
                            <input type="text" name="NEW_MOTA_LOAI" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Đơn giá mới (₫)</label>
                            <input type="number" name="DON_GIA" min="0" class="w-full px-3 py-2 border rounded-lg" placeholder="Giữ nguyên nếu bỏ trống">
                            <?php if (!empty($editData['DON_GIA_HIEN_TAI'])): ?>
                                <p class="text-xs text-gray-500 mt-1">Giá hiện tại: <?= number_format((float) $editData['DON_GIA_HIEN_TAI'], 0, ',', '.') ?> ₫</p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Ngày giặt gần nhất</label>
                            <input type="date" name="NGAY_GIAT_CUOI" value="<?= ($editData['NGAY_GIAT_CUOI'] && $editData['NGAY_GIAT_CUOI'] !== '0000-00-00') ? htmlspecialchars($editData['NGAY_GIAT_CUOI']) : '' ?>" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Ghi chú</label>
                        <textarea name="GHI_CHU" rows="2" class="w-full px-3 py-2 border rounded-lg" placeholder="Thông tin thêm..."><?= htmlspecialchars($editData['GHI_CHU']) ?></textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="IS_ACTIVE" name="IS_ACTIVE" value="1" <?= (int) $editData['IS_ACTIVE'] === 1 ? 'checked' : '' ?> class="rounded border-gray-300">
                        <label for="IS_ACTIVE" class="text-sm text-gray-600">Đang hoạt động</label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Ảnh bìa mới</label>
                        <input type="file" name="COVER_IMAGE" accept="image/*" class="w-full text-sm text-gray-600">
                        <?php if (!empty($editData['COVER_URL'])): ?>
                            <img src="/<?= htmlspecialchars($editData['COVER_URL']) ?>" alt="Ảnh hiện tại" class="mt-3 w-32 h-32 object-cover rounded border">
                        <?php endif; ?>
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="w-full inline-flex justify-center items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                            <i class="fas fa-save"></i> Lưu thay đổi
                        </button>
                    </div>
                </form>
                <?php if (!empty($priceHistory)): ?>
                    <div class="mt-6">
                        <h3 class="text-lg font-semibold text-gray-700 mb-3">📈 Lịch sử đơn giá</h3>
                        <ul class="space-y-2 max-h-48 overflow-auto pr-2">
                            <?php foreach ($priceHistory as $entry): ?>
                                <li class="flex justify-between text-sm bg-white border border-gray-200 px-3 py-2 rounded">
                                    <span><?= number_format((float) $entry['DON_GIA'], 0, ',', '.') ?> ₫</span>
                                    <span class="text-gray-500"><?= date('d/m/Y H:i', strtotime($entry['NGAY_GIO'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-gray-500 text-sm">
                    Chọn một trang phục trong danh sách để chỉnh sửa thông tin, cập nhật giá thuê hoặc thay đổi trạng thái hoạt động.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>