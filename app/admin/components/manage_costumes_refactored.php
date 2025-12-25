<?php
/**
 * MANAGE_COSTUMES_REFACTORED.php
 * 
 * Tab Interface + Dual-panel/Single-panel flexible layout
 * Style: edit_costume_package.php (rounded-2xl, shadow, badges, no icons)
 * 
 * Tabs: 
 * 1. Danh sách (fullwidth table)
 * 2. Thêm mới (center form)
 * 3. Chi tiết (edit form - shows when edit=id in URL)
 * 4. Loại (categories - 2-panel layout)
 * 5. Nhóm (groups - 2-panel layout)
 */

use App\Repositories\CostumeRepository;

include '../../database/config.php';
require_once __DIR__ . '/../../helpers/assets.php';
require_once __DIR__ . '/../../repositories/CostumeRepository.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// SETUP & AUTHENTICATION
// ============================================================================

if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = md5(uniqid((string)mt_rand(), true));
    }
}

$roleId = (string)($_SESSION['ID_QUYEN'] ?? '');
$staffType = (string)($_SESSION['STAFF_TYPE'] ?? '');
$userBranchId = isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : 0;
$isAdmin = ($roleId === '1');
$isBranchManager = ($roleId === '2' && $staffType === 'quan_ly' && $userBranchId > 0);
$costumeRepo = new CostumeRepository($conn);

// Status catalog (unchanged from original)
$statusCatalog = [
    'available' => [
        'label'       => 'Sẵn sàng',
        'badge_class' => 'bg-emerald-100 text-emerald-700',
        'dot_class'   => 'bg-emerald-500',
        'description' => 'Trang phục đã sẵn sàng phục vụ khách hàng.',
    ],
    'rented' => [
        'label'       => 'Đang thuê',
        'badge_class' => 'bg-blue-100 text-blue-700',
        'dot_class'   => 'bg-blue-500',
        'description' => 'Trang phục đang ở cùng khách hoặc chuẩn bị giao.',
    ],
    'maintenance' => [
        'label'       => 'Bảo trì',
        'badge_class' => 'bg-amber-100 text-amber-700',
        'dot_class'   => 'bg-amber-500',
        'description' => 'Cần giặt ủi, sửa chữa hoặc kiểm tra chất lượng.',
    ],
    'retired' => [
        'label'       => 'Ngưng hoạt động',
        'badge_class' => 'bg-gray-200 text-gray-600',
        'dot_class'   => 'bg-gray-500',
        'description' => 'Tạm dừng khai thác hoặc đã loại bỏ khỏi kho.',
    ],
];

$statusCatalog['san_sang']  = $statusCatalog['available'];
$statusCatalog['dang_thue'] = $statusCatalog['rented'];
$statusCatalog['bao_tri']   = $statusCatalog['maintenance'];
$statusCatalog['ngung']     = $statusCatalog['retired'];

$statusSelectableKeys = ['available', 'rented', 'maintenance', 'retired'];

function status_meta(array $catalog, string $value): array {
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

// ============================================================================
// CSRF & VALIDATION
// ============================================================================

function csrf_token(): string {
    return $_SESSION['csrf_token'] ?? '';
}

function require_valid_csrf_action(string $action): void {
    $protected = ['add_category','add_costume','edit_costume','delete_costume','add_group','edit_group','delete_group'];
    if (!in_array($action, $protected, true)) return;
    
    $posted = $_POST['csrf'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    if (!is_string($posted) || $posted === '' || $stored === '' || !hash_equals($stored, $posted)) {
        die('CSRF token không hợp lệ');
    }
}

// ============================================================================
// POST HANDLERS
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    require_valid_csrf_action($action);
    
    // Add Costume
    if ($action === 'add_costume') {
        $tenTp = trim($_POST['TEN_TP'] ?? '');
        $donGia = isset($_POST['DON_GIA']) ? (int)$_POST['DON_GIA'] : 0;
        $idLoai = isset($_POST['ID_LOAI']) ? (int)$_POST['ID_LOAI'] : 0;
        $size = trim($_POST['SIZE'] ?? '');
        $mau = trim($_POST['MAU'] ?? '');
        $ghiChu = trim($_POST['GHI_CHU'] ?? '');
        
        if (empty($tenTp) || $donGia <= 0) {
            $_SESSION['error'] = 'Tên trang phục và đơn giá không được trống';
            header('Location: ?page=costumes&tab=add');
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO trang_phuc (TEN_TP, DON_GIA, ID_LOAI, SIZE, MAU, GHI_CHU, TINH_TRANG) 
                              VALUES (?, ?, ?, ?, ?, ?, 'available')");
        $stmt->bind_param('sidiss', $tenTp, $donGia, $idLoai, $size, $mau, $ghiChu);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Thêm trang phục thành công';
            header('Location: ?page=costumes&tab=list');
        } else {
            $_SESSION['error'] = 'Thêm trang phục thất bại';
            header('Location: ?page=costumes&tab=add');
        }
        exit;
    }
    
    // Edit Costume
    if ($action === 'edit_costume') {
        $idTp = (int)($_POST['ID_TP'] ?? 0);
        $tenTp = trim($_POST['TEN_TP'] ?? '');
        $donGia = isset($_POST['DON_GIA']) && $_POST['DON_GIA'] !== '' ? (int)$_POST['DON_GIA'] : null;
        $idLoai = isset($_POST['ID_LOAI']) ? (int)$_POST['ID_LOAI'] : 0;
        $tinhTrang = $_POST['TINH_TRANG'] ?? 'available';
        $size = trim($_POST['SIZE'] ?? '');
        $mau = trim($_POST['MAU'] ?? '');
        $ghiChu = trim($_POST['GHI_CHU'] ?? '');
        
        if (empty($tenTp) || $idTp <= 0) {
            $_SESSION['error'] = 'Dữ liệu không hợp lệ';
            header('Location: ?page=costumes&tab=detail&edit=' . $idTp);
            exit;
        }
        
        if ($donGia !== null) {
            $stmt = $conn->prepare("UPDATE trang_phuc SET TEN_TP=?, DON_GIA=?, ID_LOAI=?, TINH_TRANG=?, SIZE=?, MAU=?, GHI_CHU=? WHERE ID_TP=?");
            $stmt->bind_param('sidissi', $tenTp, $donGia, $idLoai, $tinhTrang, $size, $mau, $ghiChu, $idTp);
        } else {
            $stmt = $conn->prepare("UPDATE trang_phuc SET TEN_TP=?, ID_LOAI=?, TINH_TRANG=?, SIZE=?, MAU=?, GHI_CHU=? WHERE ID_TP=?");
            $stmt->bind_param('sisisi', $tenTp, $idLoai, $tinhTrang, $size, $mau, $ghiChu, $idTp);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Cập nhật trang phục thành công';
            header('Location: ?page=costumes&tab=list');
        } else {
            $_SESSION['error'] = 'Cập nhật thất bại';
            header('Location: ?page=costumes&tab=detail&edit=' . $idTp);
        }
        exit;
    }
    
    // Add Category
    if ($action === 'add_category') {
        $tenLoai = trim($_POST['TEN_LOAI'] ?? '');
        $mota = trim($_POST['MOTA'] ?? '');
        
        if (empty($tenLoai)) {
            $_SESSION['error'] = 'Tên loại không được trống';
            header('Location: ?page=costumes&tab=categories');
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO trang_phuc_loai (TEN_LOAI, MOTA, TRANG_THAI) VALUES (?, ?, 'active')");
        $stmt->bind_param('ss', $tenLoai, $mota);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Thêm loại thành công';
        }
        header('Location: ?page=costumes&tab=categories');
        exit;
    }
    
    // Add Group
    if ($action === 'add_group') {
        $tenNhom = trim($_POST['TEN_NHOM'] ?? '');
        $mota = trim($_POST['MOTA'] ?? '');
        
        if (empty($tenNhom)) {
            $_SESSION['error'] = 'Tên nhóm không được trống';
            header('Location: ?page=costumes&tab=groups');
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO trang_phuc_nhom (TEN_NHOM, MOTA, TRANG_THAI) VALUES (?, ?, 'active')");
        $stmt->bind_param('ss', $tenNhom, $mota);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Thêm nhóm thành công';
        }
        header('Location: ?page=costumes&tab=groups');
        exit;
    }
}

// ============================================================================
// TAB NAVIGATION & URL STATE
// ============================================================================

$activeTab = $_GET['tab'] ?? 'list';
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editCategoryId = isset($_GET['cat_edit']) ? (int)$_GET['cat_edit'] : 0;
$editGroupId = isset($_GET['grp_edit']) ? (int)$_GET['grp_edit'] : 0;

// Valid tabs
$validTabs = ['list', 'add', 'detail', 'categories', 'groups'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'list';
}

// ============================================================================
// FETCH DATA: COSTUMES, CATEGORIES, GROUPS
// ============================================================================

// Costumes list
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$filterBranch = isset($_GET['filter_branch']) ? (int)$_GET['filter_branch'] : 0;
$filterCategory = isset($_GET['filter_category']) ? (int)$_GET['filter_category'] : 0;
$filterStatus = $_GET['filter_status'] ?? '';
$filterActive = $_GET['filter_active'] ?? '';

// Build query
$where = "WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $where .= " AND (tp.TEN_TP LIKE ? OR tp.SIZE LIKE ? OR tp.MAU LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= 'sss';
}

if ($filterCategory > 0) {
    $where .= " AND tp.ID_LOAI = ?";
    $params[] = $filterCategory;
    $types .= 'i';
}

if ($filterStatus !== '') {
    $where .= " AND tp.TINH_TRANG = ?";
    $params[] = $filterStatus;
    $types .= 's';
}

if ($filterActive === '1') {
    $where .= " AND tp.TINH_TRANG != 'retired'";
} elseif ($filterActive === '0') {
    $where .= " AND tp.TINH_TRANG = 'retired'";
}

// Costumes query
$sql = "SELECT tp.ID_TP, tp.TEN_TP, tp.SIZE, tp.MAU, tp.DON_GIA, tp.TINH_TRANG, 
               tp.ID_LOAI, tp.GHI_CHU, tp.UPDATED_AT, tp.COVER_URL,
               tl.TEN_LOAI, cn.TEN_CN
        FROM trang_phuc tp
        LEFT JOIN trang_phuc_loai tl ON tp.ID_LOAI = tl.ID_LOAI
        LEFT JOIN chi_nhanh cn ON tp.ID_CN = cn.ID_CN
        $where
        ORDER BY tp.TEN_TP ASC
        LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$costumes = [];
while ($row = $result->fetch_assoc()) {
    $costumes[] = $row;
}

// Total count
$countSql = "SELECT COUNT(*) as total FROM trang_phuc tp $where";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRows = (int)($countResult->fetch_assoc()['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $limit));

// Categories
$categories = [];
$catResult = $conn->query("SELECT ID_LOAI, TEN_LOAI, MOTA FROM trang_phuc_loai WHERE TRANG_THAI = 'active' ORDER BY TEN_LOAI");
while ($row = $catResult->fetch_assoc()) {
    $categories[$row['ID_LOAI']] = $row;
}

// Groups
$groups = [];
$grpResult = $conn->query("SELECT ID_NHOM, TEN_NHOM, MOTA FROM trang_phuc_nhom WHERE TRANG_THAI = 'active' ORDER BY TEN_NHOM");
while ($row = $grpResult->fetch_assoc()) {
    $groups[$row['ID_NHOM']] = $row;
}

// Edit data
$editData = null;
if ($editId > 0) {
    $editStmt = $conn->prepare("SELECT * FROM trang_phuc WHERE ID_TP = ?");
    $editStmt->bind_param('i', $editId);
    $editStmt->execute();
    $editResult = $editStmt->get_result();
    $editData = $editResult->fetch_assoc();
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý Trang Phục</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">

<div class="max-w-7xl mx-auto p-6 space-y-6">
    
    <!-- MESSAGES -->
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm font-medium">
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm font-medium">
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <!-- HEADER -->
    <header class="space-y-3">
        <h1 class="text-3xl font-bold text-indigo-700">Quản lý Trang Phục</h1>
        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
            <span>Tổng: <strong class="text-indigo-700"><?= $totalRows ?></strong> trang phục</span>
            <span class="hidden md:inline">Trang: <strong><?= $page ?></strong> / <strong><?= $totalPages ?></strong></span>
        </div>
    </header>

    <!-- TAB NAVIGATION -->
    <div class="bg-white rounded-2xl shadow-md border border-gray-200 overflow-hidden">
        <div class="flex items-center overflow-x-auto border-b border-gray-200">
            <button class="tab-btn px-6 py-4 text-sm font-medium whitespace-nowrap transition-colors
                    <?= $activeTab === 'list' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'border-b-2 border-transparent text-gray-600 hover:text-gray-900' ?>"
                    onclick="switchTab('list')">
                Danh sách
            </button>
            <button class="tab-btn px-6 py-4 text-sm font-medium whitespace-nowrap transition-colors
                    <?= $activeTab === 'add' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'border-b-2 border-transparent text-gray-600 hover:text-gray-900' ?>"
                    onclick="switchTab('add')">
                Thêm mới
            </button>
            <button class="tab-btn px-6 py-4 text-sm font-medium whitespace-nowrap transition-colors
                    <?= $activeTab === 'detail' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'border-b-2 border-transparent text-gray-600 hover:text-gray-900' ?>"
                    onclick="switchTab('detail')">
                Chi tiết
            </button>
            <button class="tab-btn px-6 py-4 text-sm font-medium whitespace-nowrap transition-colors
                    <?= $activeTab === 'categories' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'border-b-2 border-transparent text-gray-600 hover:text-gray-900' ?>"
                    onclick="switchTab('categories')">
                Loại
            </button>
            <button class="tab-btn px-6 py-4 text-sm font-medium whitespace-nowrap transition-colors
                    <?= $activeTab === 'groups' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'border-b-2 border-transparent text-gray-600 hover:text-gray-900' ?>"
                    onclick="switchTab('groups')">
                Nhóm
            </button>
        </div>

        <!-- CONTENT AREA -->
        <div class="p-6">

            <!-- TAB 1: DANH SÁCH -->
            <?php if ($activeTab === 'list'): ?>
                <div class="space-y-4">
                    
                    <!-- Filter Bar (Sticky) -->
                    <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 space-y-3">
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-3">
                            <input type="hidden" name="page" value="costumes">
                            <input type="hidden" name="tab" value="list">
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Tìm kiếm</label>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                    placeholder="Tên, size, màu..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Loại</label>
                                <select name="filter_category" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                    <option value="">Tất cả</option>
                                    <?php foreach ($categories as $id => $cat): ?>
                                        <option value="<?= $id ?>" <?= $filterCategory === $id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['TEN_LOAI']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Tình trạng</label>
                                <select name="filter_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                    <option value="">Tất cả</option>
                                    <?php foreach ($statusSelectableKeys as $key): ?>
                                        <option value="<?= $key ?>" <?= $filterStatus === $key ? 'selected' : '' ?>>
                                            <?= $statusCatalog[$key]['label'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Hoạt động</label>
                                <select name="filter_active" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                    <option value="">Tất cả</option>
                                    <option value="1" <?= $filterActive === '1' ? 'selected' : '' ?>>Đang sử dụng</option>
                                    <option value="0" <?= $filterActive === '0' ? 'selected' : '' ?>>Ngưng dùng</option>
                                </select>
                            </div>
                            
                            <div class="flex items-end gap-2">
                                <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium transition">
                                    Áp dụng
                                </button>
                                <a href="?page=costumes&tab=list" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium transition text-center">
                                    Đặt lại
                                </a>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Table fullwidth -->
                    <div class="bg-white rounded-2xl shadow-md border border-gray-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-indigo-50 border-b border-gray-200">
                                    <tr class="text-gray-700 font-semibold">
                                        <th class="px-4 py-3 text-left">Tên Trang Phục</th>
                                        <th class="px-4 py-3 text-left">Loại</th>
                                        <th class="px-4 py-3 text-left">Kích thước / Màu</th>
                                        <th class="px-4 py-3 text-right">Đơn Giá</th>
                                        <th class="px-4 py-3 text-center">Tình Trạng</th>
                                        <th class="px-4 py-3 text-right">Thao Tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($costumes)): ?>
                                        <?php foreach ($costumes as $costume): ?>
                                            <?php $meta = status_meta($statusCatalog, $costume['TINH_TRANG']); ?>
                                            <tr class="border-t border-gray-100 hover:bg-gray-50 transition">
                                                <td class="px-4 py-3">
                                                    <p class="font-semibold text-gray-900"><?= htmlspecialchars($costume['TEN_TP']) ?></p>
                                                    <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($costume['GHI_CHU'] ?? '') ?></p>
                                                </td>
                                                <td class="px-4 py-3 text-sm">
                                                    <?= htmlspecialchars($costume['TEN_LOAI'] ?? 'Chưa phân loại') ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600">
                                                    <?= htmlspecialchars($costume['SIZE'] ?: '—') ?> / <?= htmlspecialchars($costume['MAU'] ?: '—') ?>
                                                </td>
                                                <td class="px-4 py-3 text-right font-semibold text-emerald-600">
                                                    <?= number_format((int)$costume['DON_GIA'], 0, ',', '.') ?> ₫
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $meta['badge_class'] ?>">
                                                        <?= $meta['label'] ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-right space-x-2">
                                                    <button onclick="switchTab('detail', <?= $costume['ID_TP'] ?>)" 
                                                        class="px-3 py-1 rounded text-xs font-medium border border-indigo-200 text-indigo-600 hover:bg-indigo-50 transition">
                                                        Sửa
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-4 py-8 text-center text-gray-500 text-sm">
                                                Không có trang phục nào phù hợp
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="flex items-center justify-between text-sm text-gray-600">
                            <div>Hiển thị <?= count($costumes) ?> / <?= $totalRows ?> bản ghi</div>
                            <div class="flex items-center gap-2">
                                <?php if ($page > 1): ?>
                                    <a href="?page=costumes&tab=list&p=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                                        class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-100">
                                        Trước
                                    </a>
                                <?php endif; ?>
                                <span>Trang <?= $page ?> / <?= $totalPages ?></span>
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=costumes&tab=list&p=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                                        class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-100">
                                        Sau
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                </div>
            <?php endif; ?>

            <!-- TAB 2: THÊM MỚI -->
            <?php if ($activeTab === 'add'): ?>
                <div class="max-w-2xl mx-auto">
                    <h2 class="text-xl font-bold text-indigo-700 mb-4">Thêm Trang Phục Mới</h2>
                    <form method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-md border border-gray-200 p-6 space-y-4">
                        <input type="hidden" name="action" value="add_costume">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Tên Trang Phục *</label>
                            <input type="text" name="TEN_TP" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none" 
                                placeholder="Ví dụ: Váy cưới trắng">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Loại</label>
                                <select name="ID_LOAI" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                    <option value="">Chưa phân loại</option>
                                    <?php foreach ($categories as $id => $cat): ?>
                                        <option value="<?= $id ?>"><?= htmlspecialchars($cat['TEN_LOAI']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Nhóm</label>
                                <select name="ID_NHOM" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                    <option value="">Chưa phân nhóm</option>
                                    <?php foreach ($groups as $id => $grp): ?>
                                        <option value="<?= $id ?>"><?= htmlspecialchars($grp['TEN_NHOM']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Kích Thước</label>
                                <input type="text" name="SIZE" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="M, L, XL">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Màu Sắc</label>
                                <input type="text" name="MAU" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Đỏ, đen, trắng">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Đơn Giá (₫) *</label>
                                <input type="number" name="DON_GIA" required min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="500000">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Ghi Chú</label>
                            <textarea name="GHI_CHU" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Thông tin thêm..."></textarea>
                        </div>
                        
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                            <button type="reset" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                Đặt lại
                            </button>
                            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-medium">
                                Thêm Mới
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- TAB 3: CHI TIẾT -->
            <?php if ($activeTab === 'detail'): ?>
                <div class="max-w-2xl mx-auto">
                    <?php if ($editData): ?>
                        <h2 class="text-xl font-bold text-indigo-700 mb-1">Chỉnh Sửa: <?= htmlspecialchars($editData['TEN_TP']) ?></h2>
                        <p class="text-xs text-gray-500 mb-4">ID: <?= $editData['ID_TP'] ?> | Cập nhật: <?= date('d/m/Y H:i', strtotime($editData['UPDATED_AT'])) ?></p>
                        
                        <form method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-md border border-gray-200 p-6 space-y-4">
                            <input type="hidden" name="action" value="edit_costume">
                            <input type="hidden" name="ID_TP" value="<?= $editData['ID_TP'] ?>">
                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Tên Trang Phục *</label>
                                <input type="text" name="TEN_TP" required value="<?= htmlspecialchars($editData['TEN_TP']) ?>" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Loại</label>
                                    <select name="ID_LOAI" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                        <option value="">Chưa phân loại</option>
                                        <?php foreach ($categories as $id => $cat): ?>
                                            <option value="<?= $id ?>" <?= (int)$editData['ID_LOAI'] === $id ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['TEN_LOAI']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tình Trạng</label>
                                    <select name="TINH_TRANG" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                        <?php foreach ($statusSelectableKeys as $key): ?>
                                            <option value="<?= $key ?>" <?= $editData['TINH_TRANG'] === $key ? 'selected' : '' ?>>
                                                <?= $statusCatalog[$key]['label'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Kích Thước</label>
                                    <input type="text" name="SIZE" value="<?= htmlspecialchars($editData['SIZE']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Màu Sắc</label>
                                    <input type="text" name="MAU" value="<?= htmlspecialchars($editData['MAU']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Đơn Giá Mới (₫)</label>
                                    <input type="number" name="DON_GIA" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Giữ nguyên nếu bỏ trống">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Ghi Chú</label>
                                <textarea name="GHI_CHU" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg"><?= htmlspecialchars($editData['GHI_CHU']) ?></textarea>
                            </div>
                            
                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                                <button type="button" onclick="switchTab('list')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                    Hủy
                                </button>
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                                    Lưu Thay Đổi
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-12 text-gray-500">
                            <p>Chọn trang phục trong danh sách để chỉnh sửa</p>
                            <button type="button" onclick="switchTab('list')" class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                                Quay lại Danh Sách
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 4: LOẠI (CATEGORIES) - 2 Panel -->
            <?php if ($activeTab === 'categories'): ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Left: List -->
                    <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-6">
                        <h3 class="text-lg font-bold text-indigo-700 mb-4">Danh Sách Loại Trang Phục</h3>
                        <div class="space-y-2 max-h-96 overflow-auto">
                            <?php if (!empty($categories)): ?>
                                <?php foreach ($categories as $id => $cat): ?>
                                    <div class="flex items-center justify-between bg-gray-50 p-3 rounded-lg hover:bg-indigo-50 transition">
                                        <div class="flex-1">
                                            <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($cat['TEN_LOAI']) ?></p>
                                            <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($cat['MOTA'] ?? '—') ?></p>
                                        </div>
                                        <button type="button" onclick="switchTab('categories', 0, <?= $id ?>)" class="ml-2 px-2 py-1 text-xs text-indigo-600 hover:bg-indigo-100 rounded">
                                            Sửa
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 py-8 text-center">Chưa có loại trang phục nào</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Right: Form -->
                    <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-6">
                        <h3 class="text-lg font-bold text-indigo-700 mb-4">Thêm Loại Trang Phục</h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_category">
                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Tên Loại *</label>
                                <input type="text" name="TEN_LOAI" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Váy, Áo, Mũ...">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Mô Tả</label>
                                <textarea name="MOTA" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Ghi chú..."></textarea>
                            </div>
                            
                            <div class="flex gap-3 pt-4 border-t border-gray-200">
                                <button type="submit" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-medium text-sm">
                                    Lưu
                                </button>
                                <button type="reset" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm">
                                    Đặt lại
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 5: NHÓM (GROUPS) - 2 Panel -->
            <?php if ($activeTab === 'groups'): ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Left: List -->
                    <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-6">
                        <h3 class="text-lg font-bold text-indigo-700 mb-4">Danh Sách Nhóm Trang Phục</h3>
                        <div class="space-y-2 max-h-96 overflow-auto">
                            <?php if (!empty($groups)): ?>
                                <?php foreach ($groups as $id => $grp): ?>
                                    <div class="flex items-center justify-between bg-gray-50 p-3 rounded-lg hover:bg-indigo-50 transition">
                                        <div class="flex-1">
                                            <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($grp['TEN_NHOM']) ?></p>
                                            <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($grp['MOTA'] ?? '—') ?></p>
                                        </div>
                                        <button type="button" onclick="switchTab('groups', 0, <?= $id ?>)" class="ml-2 px-2 py-1 text-xs text-indigo-600 hover:bg-indigo-100 rounded">
                                            Sửa
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 py-8 text-center">Chưa có nhóm trang phục nào</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Right: Form -->
                    <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-6">
                        <h3 class="text-lg font-bold text-indigo-700 mb-4">Thêm Nhóm Trang Phục</h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_group">
                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Tên Nhóm *</label>
                                <input type="text" name="TEN_NHOM" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Cưới, Thuyết trình, Tiệc tối...">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Mô Tả</label>
                                <textarea name="MOTA" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Ghi chú..."></textarea>
                            </div>
                            
                            <div class="flex gap-3 pt-4 border-t border-gray-200">
                                <button type="submit" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-medium text-sm">
                                    Lưu
                                </button>
                                <button type="reset" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm">
                                    Đặt lại
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<!-- Tab Navigation Script -->
<script>
function switchTab(tab, editId = 0, catId = 0) {
    let url = `?page=costumes&tab=${tab}`;
    if (editId > 0) url += `&edit=${editId}`;
    if (catId > 0) url += `&cat_edit=${catId}`;
    window.location.href = url;
}
</script>

</body>
</html>
