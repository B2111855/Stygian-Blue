<?php
include '../../database/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$statusCatalog = [
    'san_sang' => [
        'label'       => 'Sẵn sàng',
        'badge_class' => 'bg-emerald-100 text-emerald-700',
        'dot_class'   => 'bg-emerald-500',
        'description' => 'Trang phục đã sẵn sàng phục vụ khách hàng.',
    ],
    'dang_thue' => [
        'label'       => 'Đang thuê',
        'badge_class' => 'bg-blue-100 text-blue-700',
        'dot_class'   => 'bg-blue-500',
        'description' => 'Trang phục đang ở cùng khách hoặc chuẩn bị giao.',
    ],
    'bao_tri' => [
        'label'       => 'Bảo trì',
        'badge_class' => 'bg-amber-100 text-amber-700',
        'dot_class'   => 'bg-amber-500',
        'description' => 'Cần giặt ủi, sửa chữa hoặc kiểm tra chất lượng.',
    ],
    'ngung' => [
        'label'       => 'Ngưng hoạt động',
        'badge_class' => 'bg-gray-200 text-gray-600',
        'dot_class'   => 'bg-gray-500',
        'description' => 'Tạm dừng khai thác hoặc đã loại bỏ khỏi kho.',
    ],
];

function status_meta(array $catalog, string $value): array
{
    if (isset($catalog[$value])) {
        return $catalog[$value];
    }

    return [
        'label'       => ucfirst(str_replace('_', ' ', $value)),
        'badge_class' => 'bg-gray-100 text-gray-600',
        'dot_class'   => 'bg-gray-400',
        'description' => 'Trạng thái khác.',
    ];
}

function redirect_costume(string $type, string $message): void
{
    $_SESSION[$type] = $message;
    if (!headers_sent()) {
        header('Location: ?page=costumes');
        exit;
    }

    echo '<script>window.location.href = "?page=costumes";</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=?page=costumes"></noscript>';
    exit;
}

function determine_scope(?array $customScope): array
{
    $mode     = 'global';
    $branchId = 0;

    if ($customScope) {
        $mode     = $customScope['mode'] ?? 'global';
        $branchId = (int) ($customScope['branchId'] ?? 0);
    } elseif (($_SESSION['role'] ?? '') === 'branch_manager') {
        $mode     = 'branch';
        $branchId = (int) ($_SESSION['branch_id'] ?? 0);
    }

    return [
        'mode'      => $mode === 'branch' && $branchId > 0 ? 'branch' : 'global',
        'branch_id' => $branchId,
    ];
}

function fetch_scope_branches(mysqli $conn, array $scope): array
{
    $branches      = [];
    $displayBranch = null;

    if ($scope['mode'] === 'branch' && $scope['branch_id'] > 0) {
        $result = $conn->query('SELECT ID_CN, TEN_CN FROM chi_nhanh WHERE ID_CN = ' . (int) $scope['branch_id'] . ' LIMIT 1');
        if ($result && ($row = $result->fetch_assoc())) {
            $branches[(int) $row['ID_CN']] = $row['TEN_CN'];
            $displayBranch                 = $row['TEN_CN'];
        }

        return [
            'list'    => $branches,
            'allowed' => array_keys($branches),
            'name'    => $displayBranch,
            'valid'   => $displayBranch !== null,
        ];
    }

    $result = $conn->query('SELECT ID_CN, TEN_CN FROM chi_nhanh ORDER BY TEN_CN');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $branches[(int) $row['ID_CN']] = $row['TEN_CN'];
        }
    }

    return [
        'list'    => $branches,
        'allowed' => array_keys($branches),
        'name'    => null,
        'valid'   => true,
    ];
}

function resolve_category(mysqli $conn): ?int
{
    if (isset($_POST['ID_LOAI']) && $_POST['ID_LOAI'] !== '') {
        return (int) $_POST['ID_LOAI'];
    }

    return null;
}

function locate_project_root(): ?string
{
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if ($documentRoot !== '') {
        $normalized = rtrim(str_replace('\\\\', '/', $documentRoot), '/');
        if (substr($normalized, -13) !== 'Stygian-Blue' && is_dir($normalized . '/Stygian-Blue/public')) {
            return $normalized . '/Stygian-Blue';
        }
        if (is_dir($normalized . '/public')) {
            return $normalized;
        }
    }

    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    if ($script !== '') {
        $current = str_replace('\\\\', '/', dirname($script));
        while ($current && !is_dir($current . '/public')) {
            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }
            $current = $parent;
        }
        if ($current && is_dir($current . '/public')) {
            return $current;
        }
    }

    $fallback = realpath('../../..');
    if ($fallback && is_dir($fallback . '/public')) {
        return str_replace('\\\\', '/', $fallback);
    }

    $cwd = getcwd();
    if ($cwd && is_dir($cwd . '/public')) {
        return str_replace('\\\\', '/', $cwd);
    }

    return null;
}

function upload_costume_cover(array $file, string $fallbackName): ?string
{
    if (!isset($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $originalName = basename($file['name']);
    $extension    = pathinfo($originalName, PATHINFO_EXTENSION);
    $baseName     = pathinfo($originalName, PATHINFO_FILENAME);
    $safeName     = preg_replace('/[^A-Za-z0-9_\\-\.]+/', '_', $baseName);

    if ($safeName === '' && $fallbackName !== '') {
        $safeName = preg_replace('/[^A-Za-z0-9_\\-\.]+/', '_', $fallbackName);
    }

    $filename = ($safeName !== '' ? $safeName : 'cover') . '_' . time();
    if ($extension) {
        $filename .= '.' . $extension;
    }

    $rootPath = locate_project_root();
    if ($rootPath === null) {
        return null;
    }

    $targetDir = rtrim($rootPath, '/') . '/public/images/trangphuc/';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true)) {
        return null;
    }

    $targetPath = $targetDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return null;
    }

    return 'public/images/trangphuc/' . $filename;
}

function remove_uploaded_costume_files(array $paths): void
{
    if (empty($paths)) {
        return;
    }

    $rootPath = locate_project_root();
    if ($rootPath === null) {
        return;
    }

    $normalizedRoot = rtrim(str_replace('\\', '/', $rootPath), '/');
    foreach ($paths as $path) {
        if (!$path) {
            continue;
        }

        $relative = ltrim(str_replace('\\', '/', $path), '/');
        $fullPath = $normalizedRoot . '/' . $relative;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}

function upload_costume_gallery(array $files, string $fallbackName): array
{
    $uploadedPaths = [];

    if (!isset($files['name']) || !is_array($files['name'])) {
        return ['paths' => [], 'error' => null];
    }

    $total = count($files['name']);
    for ($index = 0; $index < $total; $index++) {
        $name    = $files['name'][$index] ?? '';
        $error   = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;
        $tmpName = $files['tmp_name'][$index] ?? '';

        if ($name === '' || $error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($error !== UPLOAD_ERR_OK || $tmpName === '') {
            remove_uploaded_costume_files($uploadedPaths);
            return ['paths' => [], 'error' => 'Không thể tải ảnh bổ sung lên, vui lòng thử lại.'];
        }

        $singleFile = [
            'name'     => $name,
            'type'     => $files['type'][$index] ?? '',
            'tmp_name' => $tmpName,
            'error'    => $error,
            'size'     => $files['size'][$index] ?? 0,
        ];

        $path = upload_costume_cover($singleFile, $fallbackName . '-' . ($index + 1));
        if ($path === null) {
            remove_uploaded_costume_files($uploadedPaths);
            return ['paths' => [], 'error' => 'Không thể tải ảnh bổ sung lên, vui lòng thử lại.'];
        }

        $uploadedPaths[] = $path;
    }

    return ['paths' => $uploadedPaths, 'error' => null];
}

function fetch_categories(mysqli $conn): array
{
    $categories = [];
    $result     = $conn->query('SELECT ID_LOAI, TEN_LOAI FROM trang_phuc_loai WHERE IS_ACTIVE = 1 ORDER BY TEN_LOAI');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[(int) $row['ID_LOAI']] = $row['TEN_LOAI'];
        }
    }

    return $categories;
}

function build_filter_state(mysqli $conn, array $scope, array $branches, array $catalog): array
{
    $search         = isset($_GET['search']) ? trim($_GET['search']) : '';
    $filterBranch   = isset($_GET['filter_branch']) ? (int) $_GET['filter_branch'] : 0;
    $filterStatus   = $_GET['filter_status'] ?? '';
    $filterActive   = $_GET['filter_active'] ?? '';
    $filterCategory = isset($_GET['filter_category']) ? (int) $_GET['filter_category'] : 0;

    $conditions = [];
    if ($search !== '') {
        $escaped = mysqli_real_escape_string($conn, $search);
        $conditions[] = "(tp.TEN_TP LIKE '%$escaped%' OR tp.MAU LIKE '%$escaped%' OR tp.SIZE LIKE '%$escaped%' OR tp.GHI_CHU LIKE '%$escaped%')";
    }

    if ($scope['mode'] === 'branch' && $scope['branch_id'] > 0) {
        $conditions[] = 'tp.ID_CN = ' . (int) $scope['branch_id'];
        $filterBranch = $scope['branch_id'];
    } elseif ($filterBranch > 0 && isset($branches[$filterBranch])) {
        $conditions[] = 'tp.ID_CN = ' . $filterBranch;
    } else {
        $filterBranch = 0;
    }

    if ($filterCategory > 0) {
        $conditions[] = 'tp.ID_LOAI = ' . $filterCategory;
    }

    if ($filterStatus !== '' && isset($catalog[$filterStatus])) {
        $escaped = mysqli_real_escape_string($conn, $filterStatus);
        $conditions[] = "tp.TINH_TRANG = '$escaped'";
    } else {
        $filterStatus = '';
    }

    if ($filterActive !== '') {
        $conditions[] = $filterActive === '1' ? 'tp.IS_ACTIVE = 1' : 'tp.IS_ACTIVE = 0';
    }

    return [
        'search'     => $search,
        'branch'     => $filterBranch,
        'status'     => $filterStatus,
        'active'     => $filterActive,
        'category'   => $filterCategory,
        'conditions' => $conditions,
    ];
}

function fetch_edit_context(mysqli $conn, int $id, bool $restrictBranch, int $branchId): array
{
    $data    = null;
    $history = [];

    $editSql = "
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
        WHERE tp.ID_TP = $id";

    if ($restrictBranch) {
        $editSql .= ' AND tp.ID_CN = ' . $branchId;
    }
    $editSql .= ' LIMIT 1';

    $result = $conn->query($editSql);
    if ($result && $result->num_rows) {
        $data = $result->fetch_assoc();
        $hist = $conn->query('SELECT DON_GIA, NGAY_GIO FROM don_gia_trang_phuc WHERE ID_TP = ' . $id . ' ORDER BY NGAY_GIO DESC');
        if ($hist) {
            while ($row = $hist->fetch_assoc()) {
                $history[] = $row;
            }
        }
    }

    return [$data, $history];
}

function fetch_costume_dataset(mysqli $conn, string $where, int $limit, int $offset): array
{
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
        $where";

    $totalResult = $conn->query('SELECT COUNT(*) AS total ' . $baseQuery);
    $totalRows   = $totalResult ? (int) $totalResult->fetch_assoc()['total'] : 0;
    $totalPages  = max(1, (int) ceil($totalRows / $limit));

    $listSql = 'SELECT tp.ID_TP, tp.TEN_TP, tp.SIZE, tp.MAU, tp.TINH_TRANG, tp.IS_ACTIVE,'
        . ' tp.NGAY_GIAT_CUOI, tp.GHI_CHU, tp.UPDATED_AT,'
        . ' cn.TEN_CN, tl.TEN_LOAI,'
        . ' price.DON_GIA, price.NGAY_GIO,'
        . ' cover.URL AS COVER_URL '
        . $baseQuery
        . ' ORDER BY tp.UPDATED_AT DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

    $listResult = $conn->query($listSql);
    $rows       = [];
    if ($listResult) {
        while ($row = $listResult->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return [$rows, $totalRows, $totalPages];
}

function fetch_insights(mysqli $conn, string $where): array
{
    $insights = [
        'by_status' => [],
        'active'    => ['1' => 0, '0' => 0],
    ];

    $statusRes = $conn->query('SELECT tp.TINH_TRANG, COUNT(*) AS total FROM trang_phuc tp ' . ($where !== '' ? $where : '') . ' GROUP BY tp.TINH_TRANG');
    if ($statusRes) {
        while ($row = $statusRes->fetch_assoc()) {
            $insights['by_status'][$row['TINH_TRANG']] = (int) $row['total'];
        }
    }

    $activeRes = $conn->query('SELECT tp.IS_ACTIVE, COUNT(*) AS total FROM trang_phuc tp ' . ($where !== '' ? $where : '') . ' GROUP BY tp.IS_ACTIVE');
    if ($activeRes) {
        while ($row = $activeRes->fetch_assoc()) {
            $insights['active'][(string) $row['IS_ACTIVE']] = (int) $row['total'];
        }
    }

    return $insights;
}

$scope         = determine_scope($costumeScope ?? null);
$isBranchScope = $scope['mode'] === 'branch' && $scope['branch_id'] > 0;
$branchesModel = fetch_scope_branches($conn, $scope);

if (!$branchesModel['valid']) {
    echo "<div class='p-6 bg-red-50 border border-red-200 text-red-700 rounded-lg'>Không tìm thấy thông tin chi nhánh được phân quyền.</div>";
    return;
}

$allowedBranches = $branchesModel['allowed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_category') {
        $tenLoai  = trim($_POST['TEN_LOAI'] ?? '');
        $moTaLoai = trim($_POST['MOTA'] ?? '');

        if ($tenLoai === '') {
            redirect_costume('error', 'Vui lòng nhập tên loại trang phục.');
        }

        $stmt = $conn->prepare('SELECT ID_LOAI, IS_ACTIVE FROM trang_phuc_loai WHERE LOWER(TEN_LOAI) = LOWER(?) LIMIT 1');
        if (!$stmt) {
            redirect_costume('error', 'Không thể kiểm tra loại trang phục: ' . $conn->error);
        }

        $stmt->bind_param('s', $tenLoai);
        $stmt->execute();
        $stmt->bind_result($existingId, $existingActive);
        $hasExisting = $stmt->fetch();
        $stmt->close();

        if ($hasExisting) {
            $idLoaiExisting = (int) $existingId;
            $wasInactive    = (int) $existingActive === 0;

            if ($wasInactive || $moTaLoai !== '') {
                $updateSql = 'UPDATE trang_phuc_loai SET UPDATED_AT = NOW(), IS_ACTIVE = 1';
                if ($moTaLoai !== '') {
                    $updateSql .= ', MOTA = ?';
                }
                $updateSql .= ' WHERE ID_LOAI = ?';

                $updateStmt = $conn->prepare($updateSql);
                if (!$updateStmt) {
                    redirect_costume('error', 'Không thể cập nhật loại trang phục: ' . $conn->error);
                }

                if ($moTaLoai !== '') {
                    $updateStmt->bind_param('si', $moTaLoai, $idLoaiExisting);
                } else {
                    $updateStmt->bind_param('i', $idLoaiExisting);
                }

                $updateStmt->execute();
                $updateStmt->close();
            }

            redirect_costume('success', 'Loại trang phục đã tồn tại được kích hoạt sử dụng.');
        }

        $insertStmt = $conn->prepare('INSERT INTO trang_phuc_loai (TEN_LOAI, MOTA, IS_ACTIVE, CREATED_AT, UPDATED_AT) VALUES (?, ?, 1, NOW(), NOW())');
        if (!$insertStmt) {
            redirect_costume('error', 'Không thể thêm loại trang phục mới: ' . $conn->error);
        }

        $insertStmt->bind_param('ss', $tenLoai, $moTaLoai);
        $insertStmt->execute();
        $insertStmt->close();

        redirect_costume('success', 'Đã thêm loại trang phục mới thành công.');
    }

    if ($action === 'add_costume') {
        $tenTp     = trim($_POST['TEN_TP'] ?? '');
        $size      = trim($_POST['SIZE'] ?? '');
        $mau       = trim($_POST['MAU'] ?? '');
        $tinhTrang = $_POST['TINH_TRANG'] ?? 'san_sang';
        $donGia    = (int) ($_POST['DON_GIA'] ?? 0);
        $ngayGiat  = trim($_POST['NGAY_GIAT_CUOI'] ?? '');
        $ghiChu    = trim($_POST['GHI_CHU'] ?? '');

        if ($tenTp === '') {
            redirect_costume('error', 'Vui lòng nhập tên trang phục.');
        }

        $rawBranch        = $_POST['ID_CN'] ?? '';
        $applyAllBranches = !$isBranchScope && $rawBranch === 'all';
        $targetBranches   = [];

        if ($applyAllBranches) {
            $targetBranches = array_values(array_filter(array_map('intval', $allowedBranches), static function ($branchId) {
                return (int) $branchId > 0;
            }));

            if (empty($targetBranches)) {
                redirect_costume('error', 'Không tìm thấy danh sách chi nhánh hợp lệ để thêm trang phục.');
            }
        } else {
            $idCn = $isBranchScope ? $scope['branch_id'] : (int) $rawBranch;
            if ($idCn <= 0 || (!in_array($idCn, $allowedBranches, true) && !$isBranchScope)) {
                redirect_costume('error', 'Vui lòng chọn chi nhánh hợp lệ.');
            }
            $targetBranches = [(int) $idCn];
        }

        if ($donGia <= 0) {
            redirect_costume('error', 'Đơn giá phải lớn hơn 0.');
        }

        if (!isset($statusCatalog[$tinhTrang])) {
            $tinhTrang = 'san_sang';
        }

        $idLoai      = resolve_category($conn);
        $tenEsc      = mysqli_real_escape_string($conn, $tenTp);
        $sizeEsc     = mysqli_real_escape_string($conn, $size);
        $mauEsc      = mysqli_real_escape_string($conn, $mau);
        $tinhEsc     = mysqli_real_escape_string($conn, $tinhTrang);
        $ghiChuSql   = $ghiChu !== '' ? "'" . mysqli_real_escape_string($conn, $ghiChu) . "'" : 'NULL';
        $ngayGiatSql = $ngayGiat !== '' ? "'" . mysqli_real_escape_string($conn, $ngayGiat) . "'" : 'NULL';
        $idLoaiSql   = $idLoai ? $idLoai : 'NULL';

        $coverFile = $_FILES['COVER_IMAGE'] ?? [];
        $coverPath = null;
        if (!empty($coverFile) && isset($coverFile['tmp_name']) && ($coverFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $coverPath = upload_costume_cover($coverFile, $tenTp);
            if ($coverPath === null) {
                redirect_costume('error', 'Không thể tải ảnh bìa lên, vui lòng thử lại.');
            }
        }

        $galleryResult = upload_costume_gallery($_FILES['GALLERY_IMAGES'] ?? [], $tenTp);
        if ($galleryResult['error'] !== null) {
            if ($coverPath) {
                remove_uploaded_costume_files([$coverPath]);
            }
            redirect_costume('error', $galleryResult['error']);
        }

        $galleryPaths = $galleryResult['paths'];

        $coverEsc = $coverPath ? mysqli_real_escape_string($conn, $coverPath) : null;
        $altEsc   = $coverPath ? mysqli_real_escape_string($conn, 'Ảnh bìa trang phục ' . $tenTp) : null;
        $galleryEscaped = [];
        foreach ($galleryPaths as $index => $path) {
            $galleryEscaped[] = [
                'url'  => mysqli_real_escape_string($conn, $path),
                'alt'  => mysqli_real_escape_string($conn, 'Ảnh trang phục ' . $tenTp . ' #' . ($index + 1)),
            ];
        }

        foreach ($targetBranches as $branchId) {
            $branchId = (int) $branchId;
            $insert   = "INSERT INTO trang_phuc (ID_CN, ID_LOAI, TEN_TP, SIZE, MAU, TINH_TRANG, NGAY_GIAT_CUOI, GHI_CHU, IS_ACTIVE, CREATED_AT, UPDATED_AT)"
                . " VALUES ($branchId, $idLoaiSql, '$tenEsc', '$sizeEsc', '$mauEsc', '$tinhEsc', $ngayGiatSql, $ghiChuSql, 1, NOW(), NOW())";

            if (!$conn->query($insert)) {
                redirect_costume('error', 'Không thể thêm trang phục mới: ' . $conn->error);
            }

            $idTp = (int) $conn->insert_id;
            $now  = date('Y-m-d H:i:s');
            $conn->query("INSERT INTO don_gia_trang_phuc (ID_TP, NGAY_GIO, DON_GIA) VALUES ($idTp, '$now', $donGia)");

            $orderStart = 1;
            if ($coverPath) {
                $conn->query("UPDATE trang_phuc_hinh_anh SET IS_COVER = 0 WHERE ID_TP = $idTp");
                $conn->query("INSERT INTO trang_phuc_hinh_anh (ID_TP, URL, ALT_TEXT, THU_TU, IS_COVER, IS_ACTIVE, CREATED_AT, UPDATED_AT) VALUES ($idTp, '$coverEsc', '$altEsc', $orderStart, 1, 1, NOW(), NOW())");
                $orderStart++;
            }

            foreach ($galleryEscaped as $offset => $entry) {
                $position = $orderStart + $offset;
                $conn->query("INSERT INTO trang_phuc_hinh_anh (ID_TP, URL, ALT_TEXT, THU_TU, IS_COVER, IS_ACTIVE, CREATED_AT, UPDATED_AT) VALUES ($idTp, '{$entry['url']}', '{$entry['alt']}', $position, 0, 1, NOW(), NOW())");
            }

        }

        $successMessage = $applyAllBranches
            ? 'Đã thêm trang phục mới cho tất cả chi nhánh.'
            : 'Đã thêm trang phục mới thành công.';

        redirect_costume('success', $successMessage);
    }

    if ($action === 'edit_costume') {
        $idTp = (int) ($_POST['ID_TP'] ?? 0);
        if ($idTp <= 0) {
            redirect_costume('error', 'Trang phục không hợp lệ.');
        }

        if ($isBranchScope) {
            $check = $conn->query('SELECT ID_TP FROM trang_phuc WHERE ID_TP = ' . $idTp . ' AND ID_CN = ' . (int) $scope['branch_id'] . ' LIMIT 1');
            if (!$check || !$check->num_rows) {
                redirect_costume('error', 'Bạn không có quyền chỉnh sửa trang phục thuộc chi nhánh khác.');
            }
        }

        $tenTp     = trim($_POST['TEN_TP'] ?? '');
        $size      = trim($_POST['SIZE'] ?? '');
        $mau       = trim($_POST['MAU'] ?? '');
        $tinhTrang = $_POST['TINH_TRANG'] ?? 'san_sang';
        $donGia    = isset($_POST['DON_GIA']) && $_POST['DON_GIA'] !== '' ? (int) $_POST['DON_GIA'] : 0;
        $ngayGiat  = trim($_POST['NGAY_GIAT_CUOI'] ?? '');
        $ghiChu    = trim($_POST['GHI_CHU'] ?? '');
        $isActive  = isset($_POST['IS_ACTIVE']) ? 1 : 0;

        $idCn = $isBranchScope ? $scope['branch_id'] : (int) ($_POST['ID_CN'] ?? 0);
        if ($tenTp === '' || $idCn <= 0 || (!in_array($idCn, $allowedBranches, true) && !$isBranchScope)) {
            redirect_costume('error', 'Vui lòng nhập đầy đủ tên trang phục và chọn chi nhánh hợp lệ.');
        }

        if (!isset($statusCatalog[$tinhTrang])) {
            $tinhTrang = 'san_sang';
        }

        $idLoai      = resolve_category($conn);
        $tenEsc      = mysqli_real_escape_string($conn, $tenTp);
        $sizeEsc     = mysqli_real_escape_string($conn, $size);
        $mauEsc      = mysqli_real_escape_string($conn, $mau);
        $tinhEsc     = mysqli_real_escape_string($conn, $tinhTrang);
        $ghiChuSql   = $ghiChu !== '' ? "'" . mysqli_real_escape_string($conn, $ghiChu) . "'" : 'NULL';
        $ngayGiatSql = $ngayGiat !== '' ? "'" . mysqli_real_escape_string($conn, $ngayGiat) . "'" : 'NULL';
        $idLoaiSql   = $idLoai ? $idLoai : 'NULL';

        $update = "UPDATE trang_phuc SET ID_CN = $idCn, ID_LOAI = $idLoaiSql, TEN_TP = '$tenEsc', SIZE = '$sizeEsc', MAU = '$mauEsc',"
            . " TINH_TRANG = '$tinhEsc', NGAY_GIAT_CUOI = $ngayGiatSql, GHI_CHU = $ghiChuSql, IS_ACTIVE = $isActive, UPDATED_AT = NOW()"
            . " WHERE ID_TP = $idTp";
        if (!$conn->query($update)) {
            redirect_costume('error', 'Không thể cập nhật trang phục: ' . $conn->error);
        }

        if ($donGia > 0) {
            $latest    = $conn->query('SELECT DON_GIA FROM don_gia_trang_phuc WHERE ID_TP = ' . $idTp . ' ORDER BY NGAY_GIO DESC LIMIT 1');
            $latestVal = $latest && $latest->num_rows ? (int) $latest->fetch_assoc()['DON_GIA'] : 0;
            if ($latestVal !== $donGia) {
                $now = date('Y-m-d H:i:s');
                $conn->query("INSERT INTO don_gia_trang_phuc (ID_TP, NGAY_GIO, DON_GIA) VALUES ($idTp, '$now', $donGia)");
            }
        }

        $coverPath = upload_costume_cover($_FILES['COVER_IMAGE'] ?? [], $tenTp);
        if ($coverPath) {
            $coverEsc = mysqli_real_escape_string($conn, $coverPath);
            $alt      = 'Ảnh bìa trang phục ' . $tenTp;
            $altEsc   = mysqli_real_escape_string($conn, $alt);
            $conn->query("UPDATE trang_phuc_hinh_anh SET IS_COVER = 0 WHERE ID_TP = $idTp");
            $conn->query("INSERT INTO trang_phuc_hinh_anh (ID_TP, URL, ALT_TEXT, THU_TU, IS_COVER, IS_ACTIVE, CREATED_AT, UPDATED_AT) VALUES ($idTp, '$coverEsc', '$altEsc', 1, 1, 1, NOW(), NOW())");
        }

        redirect_costume('success', 'Đã cập nhật trang phục thành công.');
    }
}
if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];
    if ($deleteId > 0) {
        if ($isBranchScope) {
            $check = $conn->query('SELECT ID_TP FROM trang_phuc WHERE ID_TP = ' . $deleteId . ' AND ID_CN = ' . (int) $scope['branch_id'] . ' LIMIT 1');
            if (!$check || !$check->num_rows) {
                redirect_costume('error', 'Bạn không có quyền xóa trang phục thuộc chi nhánh khác.');
            }
        }

        $check = $conn->query("SELECT COUNT(*) AS cnt
            FROM don_thue_trang_phuc_ct ct
            JOIN don_thue_trang_phuc ttp ON ct.ID_TTP = ttp.ID_TTP
            WHERE ct.ID_TP = $deleteId AND ttp.TRANG_THAI IN ('cho_duyet','da_duyet','dang_thue')");
        $row = $check ? $check->fetch_assoc() : ['cnt' => 0];
        if ((int) $row['cnt'] > 0) {
            redirect_costume('error', 'Không thể xóa: trang phục đang được đặt/thuê.');
        }

        $conn->query('DELETE FROM trang_phuc_hinh_anh WHERE ID_TP = ' . $deleteId);
        $conn->query('DELETE FROM don_gia_trang_phuc WHERE ID_TP = ' . $deleteId);
        $conn->query('DELETE FROM trang_phuc WHERE ID_TP = ' . $deleteId);
        redirect_costume('success', 'Đã xóa trang phục khỏi hệ thống.');
    }
}

$limit  = 8;
$page   = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
$offset = ($page - 1) * $limit;

$categories = fetch_categories($conn);
$filters    = build_filter_state($conn, $scope, $branchesModel['list'], $statusCatalog);
$where      = $filters['conditions'] ? 'WHERE ' . implode(' AND ', $filters['conditions']) : '';

$editData     = null;
$priceHistory = [];
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        [$editData, $priceHistory] = fetch_edit_context($conn, $editId, $isBranchScope, (int) $scope['branch_id']);
    }
}

[$costumeRows, $totalRows, $totalPages] = fetch_costume_dataset($conn, $where, $limit, $offset);
$insights = fetch_insights($conn, $where);

?>
<div class="max-w-7xl mx-auto bg-white p-6 rounded-xl shadow-lg space-y-6">
    <div class="flex flex-col gap-2">
        <h1 class="text-3xl font-bold text-indigo-700">Quản Lý Trang Phục</h1>
        <p class="text-gray-500">
            Theo dõi tồn kho, tình trạng và đơn giá trang phục
            <?= $isBranchScope ? 'tại chi nhánh ' . htmlspecialchars($branchesModel['name']) : 'tại toàn bộ chi nhánh' ?>.
        </p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2 rounded-lg">
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-2 rounded-lg">
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="border border-gray-200 rounded-xl p-4 bg-gray-50">
            <div class="text-sm text-gray-500">Tổng số trang phục</div>
            <div class="text-2xl font-semibold text-gray-800 mt-2"><?= $totalRows ?></div>
            <div class="text-xs text-gray-400 mt-1">Bao gồm tất cả bộ lọc hiện tại.</div>
        </div>
        <?php foreach ($statusCatalog as $code => $meta): ?>
            <div class="border border-gray-200 rounded-xl p-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-600"><?= htmlspecialchars($meta['label']) ?></span>
                    <span class="text-xs text-gray-400 text-right"><?= htmlspecialchars($meta['description']) ?></span>
                </div>
                <div class="text-2xl font-semibold text-gray-800 mt-2"><?= $insights['by_status'][$code] ?? 0 ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="GET" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3 bg-gray-50 p-4 rounded-lg">
        <input type="hidden" name="page" value="costumes">
        <div class="xl:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Tìm kiếm</label>
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Tên, màu sắc, kích thước..." class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <?php if (!$isBranchScope): ?>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Chi nhánh</label>
                <select name="filter_branch" class="w-full px-3 py-2 border rounded-lg">
                    <option value="0">Tất cả</option>
                    <?php foreach ($branchesModel['list'] as $id => $name): ?>
                        <option value="<?= $id ?>" <?= $filters['branch'] === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Chi nhánh</label>
                <div class="px-3 py-2 border rounded-lg bg-gray-100 text-gray-700"><?= htmlspecialchars($branchesModel['name']) ?></div>
            </div>
        <?php endif; ?>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Loại</label>
            <select name="filter_category" class="w-full px-3 py-2 border rounded-lg">
                <option value="0">Tất cả</option>
                <?php foreach ($categories as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $filters['category'] === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Tình trạng</label>
            <select name="filter_status" class="w-full px-3 py-2 border rounded-lg">
                <option value="">Tất cả</option>
                <?php foreach ($statusCatalog as $value => $meta): ?>
                    <option value="<?= $value ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($meta['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Hoạt động</label>
            <select name="filter_active" class="w-full px-3 py-2 border rounded-lg">
                <option value="">Tất cả</option>
                <option value="1" <?= $filters['active'] === '1' ? 'selected' : '' ?>>Đang sử dụng</option>
                <option value="0" <?= $filters['active'] === '0' ? 'selected' : '' ?>>Ngưng dùng</option>
            </select>
        </div>
        <div class="xl:col-span-6 flex justify-end gap-2">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">Áp dụng bộ lọc</button>
            <a href="?page=costumes" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Đặt lại</a>
        </div>
    </form>

    <div class="flex flex-col lg:flex-row gap-6">
        <div class="flex-1 space-y-4">
            <div class="overflow-x-auto border border-gray-200 rounded-xl">
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
                        <?php if (count($costumeRows) > 0): ?>
                            <?php foreach ($costumeRows as $row): ?>
                                <?php $meta = status_meta($statusCatalog, $row['TINH_TRANG']); ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <?php if (!empty($row['COVER_URL'])): ?>
                                                <img src="/<?= htmlspecialchars($row['COVER_URL']) ?>" alt="<?= htmlspecialchars($row['TEN_TP']) ?>" class="w-12 h-12 rounded object-cover border" />
                                            <?php else: ?>
                                                <div class="w-12 h-12 flex items-center justify-center bg-gray-200 rounded text-gray-500 text-xs font-semibold">Ảnh</div>
                                            <?php endif; ?>
                                            <div class="space-y-1">
                                                <div class="font-semibold text-gray-900"><?= htmlspecialchars($row['TEN_TP']) ?></div>
                                                <?php if ($row['GHI_CHU']): ?>
                                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($row['GHI_CHU']) ?></div>
                                                <?php endif; ?>
                                                <div class="text-xs text-gray-400">Cập nhật <?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['UPDATED_AT']))) ?></div>
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
                                        <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium <?= $meta['badge_class'] ?>">
                                            <span class="w-2 h-2 rounded-full <?= $meta['dot_class'] ?>"></span>
                                            <?= htmlspecialchars($meta['label']) ?>
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
                                        <a href="?page=costumes&edit=<?= $row['ID_TP'] ?>" class="inline-flex items-center px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded hover:bg-indigo-100">Sửa</a>
                                        <a href="?page=costumes&delete=<?= $row['ID_TP'] ?>" onclick="return confirm('Xóa trang phục này? Hành động không thể hoàn tác.');" class="inline-flex items-center px-3 py-1.5 bg-red-50 text-red-600 rounded hover:bg-red-100">Xóa</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-gray-500">Chưa có dữ liệu trang phục phù hợp.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col md:flex-row md:items-center md:justify-between text-sm text-gray-600 gap-2">
                <div>Hiển thị <?= count($costumeRows) ?> / <?= $totalRows ?> trang phục</div>
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
</div>

        <div class="w-full lg:w-96 space-y-6">
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-700">Thêm trang phục mới</h2>
                        <p class="text-xs text-gray-500">Nhấn nút để mở form tạo mới theo phạm vi được phân quyền.</p>
                    </div>
                    <button type="button" class="text-sm px-3 py-1.5 rounded-lg border border-indigo-200 text-indigo-600 hover:bg-indigo-50"
                        data-toggle-form="addCostumeForm" data-close-label="Mở form" data-open-label="Đóng form">
                        Mở form
                    </button>
                </div>
                <div id="addCostumeForm" class="hidden space-y-4" data-form-section>
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="action" value="add_costume">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Tên trang phục *</label>
                            <input type="text" name="TEN_TP" required class="w-full px-3 py-2 border rounded-lg" placeholder="Ví dụ: Váy dạ hội đỏ">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Chi nhánh *</label>
                            <?php if ($isBranchScope): ?>
                                <input type="hidden" name="ID_CN" value="<?= $scope['branch_id'] ?>">
                                <div class="px-3 py-2 border rounded-lg bg-gray-100 text-gray-700"><?= htmlspecialchars($branchesModel['name']) ?></div>
                            <?php else: ?>
                                <select name="ID_CN" required class="w-full px-3 py-2 border rounded-lg">
                                    <option value="">-- Chọn chi nhánh --</option>
                                    <option value="all">Tất cả chi nhánh</option>
                                    <?php foreach ($branchesModel['list'] as $id => $name): ?>
                                        <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-gray-400 mt-1">Chọn "Tất cả chi nhánh" để nhân bản trang phục đến toàn hệ thống.</p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Tình trạng</label>
                            <select name="TINH_TRANG" class="w-full px-3 py-2 border rounded-lg">
                                <?php foreach ($statusCatalog as $value => $meta): ?>
                                    <option value="<?= $value ?>"><?= htmlspecialchars($meta['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Kích thước</label>
                                <input type="text" name="SIZE" class="w-full px-3 py-2 border rounded-lg" placeholder="M, L...">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Màu sắc</label>
                                <input type="text" name="MAU" class="w-full px-3 py-2 border rounded-lg" placeholder="Đỏ, đen...">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Loại trang phục</label>
                            <select name="ID_LOAI" class="w-full px-3 py-2 border rounded-lg">
                                <option value="">-- Không phân loại --</option>
                                <?php foreach ($categories as $id => $name): ?>
                                    <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Cần thêm loại mới? Hãy mở form bên dưới.</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Đơn giá (₫)</label>
                                <input type="number" name="DON_GIA" min="0" required class="w-full px-3 py-2 border rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Ngày giặt gần nhất</label>
                                <input type="date" name="NGAY_GIAT_CUOI" class="w-full px-3 py-2 border rounded-lg">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Ghi chú</label>
                            <textarea name="GHI_CHU" rows="2" class="w-full px-3 py-2 border rounded-lg" placeholder="Thông tin thêm..."></textarea>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Ảnh bìa</label>
                                <input type="file" name="COVER_IMAGE" accept="image/*" class="w-full text-sm text-gray-600">
                                <p class="text-xs text-gray-400 mt-1">Ảnh bìa sẽ hiển thị trong danh sách và là ảnh đại diện chính.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Ảnh bổ sung</label>
                                <input type="file" name="GALLERY_IMAGES[]" accept="image/*" multiple class="w-full text-sm text-gray-600">
                                <p class="text-xs text-gray-400 mt-1">Có thể chọn nhiều ảnh để sử dụng cho trang chi tiết trang phục.</p>
                            </div>
                        </div>
                        <div class="pt-2">
                            <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">Thêm mới</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-700">Chi tiết trang phục</h2>
                    <div class="flex items-center gap-2">
                        <?php if ($editData): ?>
                            <a href="?page=costumes" class="text-sm text-indigo-600 hover:underline">Hủy chỉnh sửa</a>
                        <?php endif; ?>
                        <button type="button" class="text-sm px-3 py-1.5 rounded-lg border border-indigo-200 text-indigo-600 hover:bg-indigo-50"
                            data-toggle-form="editCostumeForm" data-close-label="Mở form" data-open-label="Đóng form">
                            Mở form
                        </button>
                    </div>
                </div>
                <div id="editCostumeForm" class="hidden" data-form-section<?= $editData ? ' data-open-default="true"' : '' ?>>
                    <?php if ($editData): ?>
                        <form method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="action" value="edit_costume">
                            <input type="hidden" name="ID_TP" value="<?= $editData['ID_TP'] ?>">
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-600 mb-1">Tên trang phục *</label>
                                    <input type="text" name="TEN_TP" value="<?= htmlspecialchars($editData['TEN_TP']) ?>" required class="w-full px-3 py-2 border rounded-lg">
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Chi nhánh *</label>
                                        <?php if ($isBranchScope): ?>
                                            <input type="hidden" name="ID_CN" value="<?= $scope['branch_id'] ?>">
                                            <div class="px-3 py-2 border rounded-lg bg-gray-100 text-gray-700"><?= htmlspecialchars($branchesModel['name']) ?></div>
                                        <?php else: ?>
                                            <select name="ID_CN" required class="w-full px-3 py-2 border rounded-lg">
                                                <?php foreach ($branchesModel['list'] as $id => $name): ?>
                                                    <option value="<?= $id ?>" <?= (int) $editData['ID_CN'] === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Tình trạng</label>
                                        <select name="TINH_TRANG" class="w-full px-3 py-2 border rounded-lg">
                                            <?php foreach ($statusCatalog as $value => $meta): ?>
                                                <option value="<?= $value ?>" <?= $editData['TINH_TRANG'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($meta['label']) ?></option>
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
                            </div>
                            <div class="pt-2">
                                <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">Lưu thay đổi</button>
                            </div>
                        </form>
                        <?php if (!empty($priceHistory)): ?>
                            <div class="mt-6">
                                <h3 class="text-lg font-semibold text-gray-700 mb-3">Lịch sử đơn giá</h3>
                                <ul class="space-y-2 max-h-48 overflow-auto pr-2">
                                    <?php foreach ($priceHistory as $entry): ?>
                                        <li class="flex justify-between text-sm bg-gray-50 border border-gray-200 px-3 py-2 rounded">
                                            <span><?= number_format((float) $entry['DON_GIA'], 0, ',', '.') ?> ₫</span>
                                            <span class="text-gray-500"><?= date('d/m/Y H:i', strtotime($entry['NGAY_GIO'])) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-gray-500 text-sm leading-relaxed">
                            Chọn một trang phục trong danh sách để mở form chỉnh sửa, cập nhật giá thuê hoặc thay đổi trạng thái hoạt động.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-700">Thêm loại trang phục</h2>
                    <button type="button" class="text-sm px-3 py-1.5 rounded-lg border border-indigo-200 text-indigo-600 hover:bg-indigo-50"
                        data-toggle-form="addCategoryForm" data-close-label="Mở form" data-open-label="Đóng form">
                        Mở form
                    </button>
                </div>
                <p class="text-xs text-gray-500">Tạo mới hoặc kích hoạt lại loại trang phục để dùng chung cho các chi nhánh.</p>
                <div id="addCategoryForm" class="hidden space-y-4" data-form-section>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add_category">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Tên loại trang phục *</label>
                            <input type="text" name="TEN_LOAI" required class="w-full px-3 py-2 border rounded-lg" placeholder="Ví dụ: Váy cưới">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Mô tả</label>
                            <textarea name="MOTA" rows="2" class="w-full px-3 py-2 border rounded-lg" placeholder="Ghi chú giúp phân biệt loại trang phục"></textarea>
                        </div>
                        <div class="text-xs text-gray-500">
                            Hệ thống sẽ tự động kích hoạt lại loại đã tồn tại nếu bạn nhập trùng tên.
                        </div>
                        <div class="pt-2">
                            <button type="submit" class="w-full px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition">Lưu loại trang phục</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 space-y-4">
                <h2 class="text-lg font-semibold text-gray-700">Tình trạng hoạt động</h2>
                <div class="grid grid-cols-2 gap-4 text-center">
                    <div class="rounded-lg bg-white border border-gray-200 p-4">
                        <div class="text-sm text-gray-500">Đang sử dụng</div>
                        <div class="text-xl font-semibold text-emerald-600 mt-1"><?= $insights['active']['1'] ?? 0 ?></div>
                    </div>
                    <div class="rounded-lg bg-white border border-gray-200 p-4">
                        <div class="text-sm text-gray-500">Ngưng dùng</div>
                        <div class="text-xl font-semibold text-red-500 mt-1"><?= $insights['active']['0'] ?? 0 ?></div>
                    </div>
                </div>
                <p class="text-xs text-gray-500">Các số liệu phản ánh dữ liệu sau khi áp dụng bộ lọc hiện tại.</p>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggles = document.querySelectorAll('[data-toggle-form]');
    var focusSelector = 'input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([type=button]):not([disabled])';

    toggles.forEach(function (button) {
        var targetId = button.getAttribute('data-toggle-form');
        var target = document.getElementById(targetId);
        if (!target) {
            return;
        }

        var closeLabel = button.getAttribute('data-close-label') || button.textContent.trim();
        var openLabel = button.getAttribute('data-open-label') || closeLabel;

        var setButtonState = function (isOpen) {
            button.textContent = isOpen ? openLabel : closeLabel;
        };

        var toggleSection = function () {
            if (target.classList.contains('hidden')) {
                target.classList.remove('hidden');
                setButtonState(true);
                var focusable = target.querySelector(focusSelector);
                if (focusable) {
                    try {
                        focusable.focus({ preventScroll: true });
                    } catch (err) {
                        focusable.focus();
                    }
                }
                window.setTimeout(function () {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 50);
            } else {
                target.classList.add('hidden');
                setButtonState(false);
            }
        };

        setButtonState(!target.classList.contains('hidden'));
        button.addEventListener('click', toggleSection);

        if (target.hasAttribute('data-open-default')) {
            target.classList.remove('hidden');
            setButtonState(true);
            window.setTimeout(function () {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 50);
        }
    });
});
</script>