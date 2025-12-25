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

// Resolve project root to build absolute upload paths under public/
function locate_project_root(): ?string {
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if ($documentRoot !== '') {
        $normalized = rtrim(str_replace('\\', '/', $documentRoot), '/');
        if (is_dir($normalized . '/StygianBlue/public')) {
            return $normalized . '/StygianBlue';
        }
        if (is_dir($normalized . '/Stygian-Blue/public')) {
            return $normalized . '/Stygian-Blue';
        }
        if (is_dir($normalized . '/public')) {
            return $normalized;
        }
    }

    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    if ($script !== '') {
        $current = str_replace('\\', '/', dirname($script));
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
        return str_replace('\\', '/', $fallback);
    }

    $cwd = getcwd();
    if ($cwd && is_dir($cwd . '/public')) {
        return str_replace('\\', '/', $cwd);
    }

    return null;
}

function upload_costume_cover(array $file, string $fallbackName): ?string {
    if (!isset($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $originalName = basename($file['name']);
    $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $baseName     = pathinfo($originalName, PATHINFO_FILENAME);
    $safeName     = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $baseName);

    $allowedExt = ['jpg','jpeg','png','gif','webp'];
    if ($extension !== '' && !in_array($extension, $allowedExt, true)) {
        return null;
    }
    if (isset($file['type']) && $file['type'] !== '') {
        $mime = strtolower($file['type']);
        $allowedMime = ['image/jpeg','image/png','image/gif','image/webp'];
        if (!in_array($mime, $allowedMime, true)) {
            return null;
        }
    }

    if ($safeName === '' && $fallbackName !== '') {
        $safeName = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $fallbackName);
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
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            return null;
        }
    }
    if (!is_writable($targetDir)) {
        @chmod($targetDir, 0777);
    }

    $targetPath = $targetDir . $filename;
    $tmp = $file['tmp_name'] ?? '';
    $moved = false;
    if ($tmp !== '' && is_uploaded_file($tmp)) {
        $moved = move_uploaded_file($tmp, $targetPath);
    }
    if (!$moved && $tmp !== '') {
        if (@rename($tmp, $targetPath)) {
            $moved = true;
        } elseif (@copy($tmp, $targetPath)) {
            $moved = true;
            @unlink($tmp);
        }
    }
    if (!$moved || !is_file($targetPath)) {
        return null;
    }

    return 'public/images/trangphuc/' . $filename;
}

function remove_uploaded_costume_files(array $paths): void {
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

function upload_costume_gallery(array $files, string $fallbackName): array {
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
            return ['paths' => [], 'error' => 'Không thể tải ảnh bổ sung, vui lòng thử lại.'];
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
            return ['paths' => [], 'error' => 'Không thể tải ảnh bổ sung, vui lòng thử lại.'];
        }

        $uploadedPaths[] = $path;
    }

    return ['paths' => $uploadedPaths, 'error' => null];
}

// Normalize stored image paths to a web URL; prefix project base and default folder when missing
function resolve_costume_image_url(?string $url): string {
    $raw = trim((string)$url);
    if ($raw === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $raw)) {
        return $raw;
    }

    $normalized = ltrim(str_replace('\\', '/', $raw), '/');

    if (strpos($normalized, 'public/images/trangphuc/') === 0) {
        // already rooted correctly
    } elseif (strpos($normalized, 'images/trangphuc/') === 0) {
        $normalized = 'public/' . $normalized;
    } else {
        $normalized = 'public/images/trangphuc/' . $normalized;
    }

    $base = '/';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if ($script !== '') {
        $script = str_replace('\\', '/', $script);
        $pos = strpos($script, '/app/');
        if ($pos !== false) {
            $base = rtrim(substr($script, 0, $pos), '/') . '/';
        }
    }

    return $base . ltrim($normalized, '/');
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
        $giaThueDm = isset($_POST['DON_GIA']) ? (int)$_POST['DON_GIA'] : 0;
        $idLoai = isset($_POST['ID_LOAI']) ? (int)$_POST['ID_LOAI'] : 0;
        $size = trim($_POST['SIZE'] ?? '');
        $mau = trim($_POST['MAU'] ?? '');
        $ghiChu = trim($_POST['GHI_CHU'] ?? '');
        $scopeType = $_POST['SCOPE_TYPE'] ?? 'global';
        $idCn = null;
        
        // Validate SCOPE_TYPE
        if (!in_array($scopeType, ['global', 'local'])) {
            $scopeType = 'global';
        }
        
        // If local, get branch ID
        if ($scopeType === 'local') {
            $idCn = isset($_POST['ID_CN']) && $_POST['ID_CN'] > 0 ? (int)$_POST['ID_CN'] : null;
            if ($idCn === null) {
                $_SESSION['error'] = 'Vui lòng chọn chi nhánh cho trang phục riêng';
                ob_end_clean();
                header('Location: ?page=costumes&tab=add');
                exit;
            }
        }
        
        if (empty($tenTp) || $giaThueDm <= 0) {
            $_SESSION['error'] = 'Tên trang phục và đơn giá không được trống';
            ob_end_clean();
            header('Location: ?page=costumes&tab=add');
            exit;
        }

        $coverPath = null;
        $galleryPaths = [];

        $coverFile = $_FILES['COVER_IMAGE'] ?? [];
        if (!empty($coverFile) && isset($coverFile['tmp_name']) && ($coverFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $coverPath = upload_costume_cover($coverFile, $tenTp);
            if ($coverPath === null) {
                $_SESSION['error'] = 'Không thể tải ảnh bìa, vui lòng thử lại.';
                ob_end_clean();
                header('Location: ?page=costumes&tab=add');
                exit;
            }
        }

        $galleryUpload = upload_costume_gallery($_FILES['GALLERY_IMAGES'] ?? [], $tenTp);
        if ($galleryUpload['error'] !== null) {
            if ($coverPath) {
                remove_uploaded_costume_files([$coverPath]);
            }
            $_SESSION['error'] = $galleryUpload['error'];
            ob_end_clean();
            header('Location: ?page=costumes&tab=add');
            exit;
        }
        $galleryPaths = $galleryUpload['paths'];

        $stmt = $conn->prepare("INSERT INTO trang_phuc (TEN, GIA_THUE, ID_LOAI, SIZE, MAU_SAC, GHI_CHU, TRANG_THAI, SCOPE_TYPE, ID_CN) 
                              VALUES (?, ?, ?, ?, ?, ?, 'available', ?, ?)");
        if ($stmt !== false) {
            $stmt->bind_param('siissssi', $tenTp, $giaThueDm, $idLoai, $size, $mau, $ghiChu, $scopeType, $idCn);
            if ($stmt->execute()) {
                $newId = (int)$stmt->insert_id;

                $order = 1;
                if ($coverPath) {
                    $coverEsc = $conn->real_escape_string($coverPath);
                    $altEsc = $conn->real_escape_string('Ảnh bìa ' . $tenTp);
                    $conn->query("INSERT INTO trang_phuc_hinh_anh (ID_TP, URL, ALT_TEXT, THU_TU, IS_COVER, IS_ACTIVE, CREATED_AT, UPDATED_AT) VALUES ($newId, '$coverEsc', '$altEsc', $order, 1, 1, NOW(), NOW())");
                    $order++;
                }

                foreach ($galleryPaths as $idx => $path) {
                    $urlEsc = $conn->real_escape_string($path);
                    $altEsc = $conn->real_escape_string('Ảnh trang phục ' . $tenTp . ' #' . ($idx + 1));
                    $position = $order + $idx;
                    $conn->query("INSERT INTO trang_phuc_hinh_anh (ID_TP, URL, ALT_TEXT, THU_TU, IS_COVER, IS_ACTIVE, CREATED_AT, UPDATED_AT) VALUES ($newId, '$urlEsc', '$altEsc', $position, 0, 1, NOW(), NOW())");
                }

                $_SESSION['success'] = 'Thêm trang phục thành công';
                ob_end_clean();
                header('Location: ?page=costumes&tab=list');
            } else {
                if ($coverPath) {
                    remove_uploaded_costume_files([$coverPath]);
                }
                if (!empty($galleryPaths)) {
                    remove_uploaded_costume_files($galleryPaths);
                }
                $_SESSION['error'] = 'Lỗi thêm trang phục: ' . $stmt->error;
                ob_end_clean();
                header('Location: ?page=costumes&tab=add');
            }
        } else {
            if ($coverPath) {
                remove_uploaded_costume_files([$coverPath]);
            }
            if (!empty($galleryPaths)) {
                remove_uploaded_costume_files($galleryPaths);
            }
            $_SESSION['error'] = 'Lỗi chuẩn bị câu lệnh: ' . $conn->error;
            ob_end_clean();
            header('Location: ?page=costumes&tab=add');
        }
        exit;
    }
    
    // Delete Costume (Soft Delete)
    if ($action === 'delete_costume') {
        $idTp = (int)($_POST['ID_TP'] ?? 0);
        
        if ($idTp <= 0) {
            $_SESSION['error'] = 'Trang phục không tồn tại';
            ob_end_clean();
            header('Location: ?page=costumes&tab=list');
            exit;
        }
        
        $deletedBy = $_SESSION['username'] ?? 'unknown';
        $stmt = $conn->prepare("UPDATE trang_phuc SET DELETED_AT = NOW(), DELETED_BY = ? WHERE ID_TRANG_PHUC = ?");
        if ($stmt !== false) {
            $stmt->bind_param('si', $deletedBy, $idTp);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Xóa trang phục thành công';
            } else {
                $_SESSION['error'] = 'Xóa thất bại';
            }
        } else {
            $_SESSION['error'] = 'Lỗi chuẩn bị câu lệnh: ' . $conn->error;
        }
        ob_end_clean();
        header('Location: ?page=costumes&tab=list');
        exit;
    }
    
    // Edit Costume
    if ($action === 'edit_costume') {
        $idTp = (int)($_POST['ID_TP'] ?? 0);
        $tenTp = trim($_POST['TEN_TP'] ?? '');
        $giaThueDm = isset($_POST['DON_GIA']) && $_POST['DON_GIA'] !== '' ? (int)$_POST['DON_GIA'] : null;
        $idLoai = isset($_POST['ID_LOAI']) ? (int)$_POST['ID_LOAI'] : 0;
        $trangThai = $_POST['TINH_TRANG'] ?? 'available';
        $size = trim($_POST['SIZE'] ?? '');
        $mau = trim($_POST['MAU'] ?? '');
        $ghiChu = trim($_POST['GHI_CHU'] ?? '');
        $scopeType = $_POST['SCOPE_TYPE'] ?? 'global';
        $idCn = null;

        if (!in_array($scopeType, ['global', 'local'], true)) {
            $scopeType = 'global';
        }

        if ($scopeType === 'local') {
            $idCn = isset($_POST['ID_CN']) && (int)$_POST['ID_CN'] > 0 ? (int)$_POST['ID_CN'] : null;
            if ($idCn === null) {
                $_SESSION['error'] = 'Vui lòng chọn chi nhánh cho trang phục riêng';
                ob_end_clean();
                header('Location: ?page=costumes&tab=detail&edit=' . $idTp);
                exit;
            }
        }
        
        if (empty($tenTp) || $idTp <= 0) {
            $_SESSION['error'] = 'Dữ liệu không hợp lệ';
            ob_end_clean();
            header('Location: ?page=costumes&tab=detail&edit=' . $idTp);
            exit;
        }

        $newCoverPath = null;
        $newGalleryPaths = [];

        $coverFile = $_FILES['COVER_IMAGE'] ?? [];
        if (!empty($coverFile) && isset($coverFile['tmp_name']) && ($coverFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $newCoverPath = upload_costume_cover($coverFile, $tenTp);
            if ($newCoverPath === null) {
                $_SESSION['error'] = 'Không thể tải ảnh bìa, vui lòng thử lại.';
                ob_end_clean();
                header('Location: ?page=costumes&tab=detail&edit=' . $idTp);
                exit;
            }
        }

        $galleryUpload = upload_costume_gallery($_FILES['GALLERY_IMAGES'] ?? [], $tenTp);
        if ($galleryUpload['error'] !== null) {
            if ($newCoverPath) {
                remove_uploaded_costume_files([$newCoverPath]);
            }
            $_SESSION['error'] = $galleryUpload['error'];
            ob_end_clean();
            header('Location: ?page=costumes&tab=detail&edit=' . $idTp);
            exit;
        }
        $newGalleryPaths = $galleryUpload['paths'];

        if ($giaThueDm !== null) {
            $stmt = $conn->prepare("UPDATE trang_phuc SET TEN=?, GIA_THUE=?, ID_LOAI=?, TRANG_THAI=?, SIZE=?, MAU_SAC=?, GHI_CHU=?, SCOPE_TYPE=?, ID_CN=? WHERE ID_TRANG_PHUC=?");
            if ($stmt !== false) {
                $stmt->bind_param('siisssssii', $tenTp, $giaThueDm, $idLoai, $trangThai, $size, $mau, $ghiChu, $scopeType, $idCn, $idTp);
            }
        } else {
            $stmt = $conn->prepare("UPDATE trang_phuc SET TEN=?, ID_LOAI=?, TRANG_THAI=?, SIZE=?, MAU_SAC=?, GHI_CHU=?, SCOPE_TYPE=?, ID_CN=? WHERE ID_TRANG_PHUC=?");
            if ($stmt !== false) {
                $stmt->bind_param('sisssssii', $tenTp, $idLoai, $trangThai, $size, $mau, $ghiChu, $scopeType, $idCn, $idTp);
            }
        }

        if ($stmt !== false && $stmt->execute()) {
            if ($newCoverPath) {
                $coverEsc = $conn->real_escape_string($newCoverPath);
                $altEsc = $conn->real_escape_string('Ảnh bìa ' . $tenTp);
                $conn->query("UPDATE trang_phuc_hinh_anh SET IS_COVER = 0 WHERE ID_TP = $idTp");
                $conn->query("INSERT INTO trang_phuc_hinh_anh (ID_TP, URL, ALT_TEXT, THU_TU, IS_COVER, IS_ACTIVE, CREATED_AT, UPDATED_AT) VALUES ($idTp, '$coverEsc', '$altEsc', 1, 1, 1, NOW(), NOW())");
            }

            if (!empty($newGalleryPaths)) {
                $maxOrderRes = $conn->query("SELECT COALESCE(MAX(THU_TU),0) AS max_order FROM trang_phuc_hinh_anh WHERE ID_TP = $idTp");
                $maxOrder = 0;
                if ($maxOrderRes && $maxOrderRes->num_rows) {
                    $maxOrder = (int)($maxOrderRes->fetch_assoc()['max_order'] ?? 0);
                }
                foreach ($newGalleryPaths as $idx => $path) {
                    $urlEsc = $conn->real_escape_string($path);
                    $altEsc = $conn->real_escape_string('Ảnh bổ sung ' . $tenTp . ' #' . ($idx + 1));
                    $order = $maxOrder + $idx + 1;
                    $conn->query("INSERT INTO trang_phuc_hinh_anh (ID_TP, URL, ALT_TEXT, THU_TU, IS_COVER, IS_ACTIVE, CREATED_AT, UPDATED_AT) VALUES ($idTp, '$urlEsc', '$altEsc', $order, 0, 1, NOW(), NOW())");
                }
            }

            $_SESSION['success'] = 'Cập nhật trang phục thành công';
            ob_end_clean();
            header('Location: ?page=costumes&tab=list');
        } else {
            if ($newCoverPath) {
                remove_uploaded_costume_files([$newCoverPath]);
            }
            if (!empty($newGalleryPaths)) {
                remove_uploaded_costume_files($newGalleryPaths);
            }
            $_SESSION['error'] = 'Cập nhật thất bại';
            ob_end_clean();
            header('Location: ?page=costumes&tab=detail&edit=' . $idTp);
        }
        exit;
    }
    
    // Add Category
    if ($action === 'add_category') {
        $tenLoai = trim($_POST['TEN_LOAI'] ?? '');
        $mota = trim($_POST['MO_TA'] ?? '');
        $idNhom = (int)($_POST['ID_NHOM'] ?? 0);
        
        if (empty($tenLoai)) {
            $_SESSION['error'] = 'Tên loại không được trống';
            ob_end_clean();
            header('Location: ?page=costumes&tab=categories');
            exit;
        }
        
        // Validate group exists if specified
        if ($idNhom > 0) {
            $grpCheck = $conn->query("SELECT ID_NHOM FROM trang_phuc_nhom WHERE ID_NHOM = $idNhom");
            if (!$grpCheck || $grpCheck->num_rows === 0) {
                $_SESSION['error'] = 'Nhóm không tồn tại';
                ob_end_clean();
                header('Location: ?page=costumes&tab=categories');
                exit;
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO trang_phuc_loai (TEN_LOAI, MO_TA, ID_NHOM, TRANG_THAI) VALUES (?, ?, ?, 'active')");
        if ($stmt !== false) {
            $stmt->bind_param('ssi', $tenLoai, $mota, $idNhom);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Thêm loại thành công';
            } else {
                $_SESSION['error'] = 'Thêm loại thất bại';
            }
        } else {
            $_SESSION['error'] = 'Lỗi chuẩn bị câu lệnh: ' . $conn->error;
        }
        ob_end_clean();
        header('Location: ?page=costumes&tab=categories');
        exit;
    }
    
    // Add Group
    if ($action === 'add_group') {
        $tenNhom = trim($_POST['TEN_NHOM'] ?? '');
        $mota = trim($_POST['MO_TA'] ?? '');
        
        if (empty($tenNhom)) {
            $_SESSION['error'] = 'Tên nhóm không được trống';
            ob_end_clean();
            header('Location: ?page=costumes&tab=groups');
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO trang_phuc_nhom (TEN_NHOM, MO_TA, TRANG_THAI) VALUES (?, ?, 'active')");
        if ($stmt !== false) {
            $stmt->bind_param('ss', $tenNhom, $mota);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Thêm nhóm thành công';
            } else {
                $_SESSION['error'] = 'Thêm nhóm thất bại';
            }
        } else {
            $_SESSION['error'] = 'Lỗi chuẩn bị câu lệnh: ' . $conn->error;
        }
        ob_end_clean();
        header('Location: ?page=costumes&tab=groups');
        exit;
    }

    // Edit Category
    if ($action === 'edit_category') {
        $idLoai = (int)($_POST['ID_LOAI'] ?? 0);
        $tenLoai = trim($_POST['TEN_LOAI'] ?? '');
        $mota = trim($_POST['MO_TA'] ?? '');
        $idNhom = (int)($_POST['ID_NHOM'] ?? 0);
        
        if ($idLoai <= 0 || empty($tenLoai)) {
            $_SESSION['error'] = 'Dữ liệu không hợp lệ';
            ob_end_clean();
            header('Location: ?page=costumes&tab=categories');
            exit;
        }
        
        // Validate group exists if specified
        if ($idNhom > 0) {
            $grpCheck = $conn->query("SELECT ID_NHOM FROM trang_phuc_nhom WHERE ID_NHOM = $idNhom");
            if (!$grpCheck || $grpCheck->num_rows === 0) {
                $_SESSION['error'] = 'Nhóm không tồn tại';
                ob_end_clean();
                header('Location: ?page=costumes&tab=categories');
                exit;
            }
        }
        
        $stmt = $conn->prepare("UPDATE trang_phuc_loai SET TEN_LOAI = ?, MO_TA = ?, ID_NHOM = ? WHERE ID_LOAI = ?");
        if ($stmt !== false) {
            $stmt->bind_param('ssii', $tenLoai, $mota, $idNhom, $idLoai);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Cập nhật loại thành công';
            } else {
                $_SESSION['error'] = 'Cập nhật loại thất bại';
            }
        } else {
            $_SESSION['error'] = 'Lỗi chuẩn bị câu lệnh: ' . $conn->error;
        }
        ob_end_clean();
        header('Location: ?page=costumes&tab=categories');
        exit;
    }

    // Drag & Drop Category
    if ($action === 'drag_drop_category') {
        $idLoai = (int)($_POST['ID_LOAI'] ?? 0);
        $idNhom = (int)($_POST['ID_NHOM'] ?? 0);
        
        if ($idLoai <= 0) {
            echo 'error: invalid_category';
            exit;
        }
        
        // Validate group exists if specified
        if ($idNhom > 0) {
            $checkGroup = $conn->query("SELECT ID_NHOM FROM trang_phuc_nhom WHERE ID_NHOM = $idNhom");
            if (!$checkGroup || $checkGroup->num_rows === 0) {
                echo 'error: group_not_found';
                exit;
            }
        }
        
        // Update category
        $stmt = $conn->prepare("UPDATE trang_phuc_loai SET ID_NHOM = ? WHERE ID_LOAI = ?");
        if ($stmt !== false) {
            $idNhomNull = ($idNhom === 0) ? null : $idNhom;
            $stmt->bind_param('ii', $idNhomNull, $idLoai);
            if ($stmt->execute()) {
                echo 'success: category_moved';
            } else {
                echo 'error: ' . $stmt->error;
            }
        } else {
            echo 'error: ' . $conn->error;
        }
        exit;
    }

    // Delete Category
    if ($action === 'delete_category') {
        $idLoai = (int)($_POST['ID_LOAI'] ?? 0);
        
        if ($idLoai <= 0) {
            $_SESSION['error'] = 'Loại không tồn tại';
            ob_end_clean();
            header('Location: ?page=costumes&tab=categories');
            exit;
        }
        
        // Check if category has costumes
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM trang_phuc WHERE ID_LOAI = ? AND DELETED_AT IS NULL");
        if ($checkStmt !== false) {
            $checkStmt->bind_param('i', $idLoai);
            if ($checkStmt->execute()) {
                $checkResult = $checkStmt->get_result();
                $count = (int)($checkResult->fetch_assoc()['count'] ?? 0);
                
                if ($count > 0) {
                    $_SESSION['error'] = "Không thể xóa loại này vì còn {$count} trang phục đang sử dụng";
                    ob_end_clean();
                    header('Location: ?page=costumes&tab=categories');
                    exit;
                }
            }
        }
        
        // Soft delete category
        $stmt = $conn->prepare("UPDATE trang_phuc_loai SET TRANG_THAI = 'deleted', UPDATED_AT = NOW() WHERE ID_LOAI = ?");
        if ($stmt !== false) {
            $stmt->bind_param('i', $idLoai);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Xóa loại thành công';
            } else {
                $_SESSION['error'] = 'Lỗi khi xóa loại: ' . $stmt->error;
            }
        } else {
            $_SESSION['error'] = 'Lỗi chuẩn bị câu lệnh: ' . $conn->error;
        }
        ob_end_clean();
        header('Location: ?page=costumes&tab=categories');
        exit;
    }

    // Delete Group
    if ($action === 'delete_group') {
        $idNhom = (int)($_POST['ID_NHOM'] ?? 0);
        
        if ($idNhom <= 0) {
            $_SESSION['error'] = 'Nhóm không tồn tại';
            ob_end_clean();
            header('Location: ?page=costumes&tab=groups');
            exit;
        }
        
        // Check if group has categories
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM trang_phuc_loai WHERE ID_NHOM = ? AND TRANG_THAI = 'active'");
        if ($checkStmt !== false) {
            $checkStmt->bind_param('i', $idNhom);
            if ($checkStmt->execute()) {
                $checkResult = $checkStmt->get_result();
                $count = (int)($checkResult->fetch_assoc()['count'] ?? 0);
                
                if ($count > 0) {
                    $_SESSION['error'] = "Không thể xóa nhóm này vì còn {$count} loại đang thuộc nhóm này";
                    ob_end_clean();
                    header('Location: ?page=costumes&tab=groups');
                    exit;
                }
            }
        }
        
        // Soft delete group
        $stmt = $conn->prepare("UPDATE trang_phuc_nhom SET TRANG_THAI = 'deleted', UPDATED_AT = NOW() WHERE ID_NHOM = ?");
        if ($stmt !== false) {
            $stmt->bind_param('i', $idNhom);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Xóa nhóm thành công';
            } else {
                $_SESSION['error'] = 'Lỗi khi xóa nhóm: ' . $stmt->error;
            }
        } else {
            $_SESSION['error'] = 'Lỗi chuẩn bị câu lệnh: ' . $conn->error;
        }
        ob_end_clean();
        header('Location: ?page=costumes&tab=groups');
        exit;
    }

    // Edit Group
    if ($action === 'edit_group') {
        $idNhom = (int)($_POST['ID_NHOM'] ?? 0);
        $tenNhom = trim($_POST['TEN_NHOM'] ?? '');
        $mota = trim($_POST['MO_TA'] ?? '');
        
        if ($idNhom <= 0 || empty($tenNhom)) {
            $_SESSION['error'] = 'Dữ liệu không hợp lệ';
            ob_end_clean();
            header('Location: ?page=costumes&tab=groups');
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE trang_phuc_nhom SET TEN_NHOM = ?, MO_TA = ? WHERE ID_NHOM = ?");
        if ($stmt !== false) {
            $stmt->bind_param('ssi', $tenNhom, $mota, $idNhom);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Cập nhật nhóm thành công';
            } else {
                $_SESSION['error'] = 'Cập nhật nhóm thất bại';
            }
        } else {
            $_SESSION['error'] = 'Lỗi chuẩn bị câu lệnh: ' . $conn->error;
        }
        ob_end_clean();
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
$where = "WHERE 1=1 AND tp.DELETED_AT IS NULL";

// Add branch filtering for managers (must see only their own branch + global costumes)
if ($isBranchManager) {
    // Managers see: GLOBAL costumes + LOCAL costumes from their branch only
    // This matches the pattern used in CostumePackageMasterRepository and PackageRepository
    $where .= " AND (tp.SCOPE_TYPE='global' OR (tp.SCOPE_TYPE='local' AND tp.ID_CN=?))";
} elseif (!$isAdmin && $filterBranch == 0) {
    // Non-admin, non-manager staff: restrict to their branch by default
    $where .= " AND tp.ID_CN=?";
}

$params = [];
$types = '';

// Add manager branch ID or staff branch ID to params (for the queries above)
if ($isBranchManager) {
    array_unshift($params, $userBranchId);
    $types = 'i' . $types;
} elseif (!$isAdmin && $filterBranch == 0) {
    array_unshift($params, $userBranchId);
    $types = 'i' . $types;
}


if ($search !== '') {
    $where .= " AND (tp.TEN LIKE ? OR tp.SIZE LIKE ? OR tp.MAU_SAC LIKE ?)";
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
    $where .= " AND tp.TRANG_THAI = ?";
    $params[] = $filterStatus;
    $types .= 's';
}

if ($filterActive === '1') {
    $where .= " AND tp.TRANG_THAI != 'retired'";
} elseif ($filterActive === '0') {
    $where .= " AND tp.TRANG_THAI = 'retired'";
}

// Costumes query
$sql = "SELECT tp.ID_TRANG_PHUC AS ID_TP, tp.TEN AS TEN_TP, tp.SIZE, tp.MAU_SAC AS MAU, tp.GIA_THUE AS DON_GIA, tp.TRANG_THAI AS TINH_TRANG, 
         tp.ID_LOAI, tp.GHI_CHU, tp.UPDATED_AT, tp.SCOPE_TYPE, tp.ID_CN,
         tl.TEN_LOAI, cn.TEN_CN,
         (SELECT ha.URL FROM trang_phuc_hinh_anh ha WHERE ha.ID_TP = tp.ID_TRANG_PHUC ORDER BY ha.THU_TU ASC, ha.ID_HA ASC LIMIT 1) AS COVER_URL
     FROM trang_phuc tp
     LEFT JOIN trang_phuc_loai tl ON tp.ID_LOAI = tl.ID_LOAI
     LEFT JOIN CHI_NHANH cn ON tp.ID_CN = cn.ID_CN
     $where
     ORDER BY tp.TEN ASC
     LIMIT $limit OFFSET $offset";

$costumes = [];
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    $_SESSION['error'] = 'Lỗi truy vấn: ' . htmlspecialchars($conn->error);
} else {
    if (!empty($params)) {
        if (!$stmt->bind_param($types, ...$params)) {
            $_SESSION['error'] = 'Lỗi bind param: ' . htmlspecialchars($stmt->error);
        }
    }
    if (!$stmt->execute()) {
        $_SESSION['error'] = 'Lỗi execute: ' . htmlspecialchars($stmt->error);
    } else {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $costumes[] = $row;
        }
    }
}

// Total count
$totalRows = 0;
$totalPages = 1;
$countSql = "SELECT COUNT(*) as total FROM trang_phuc tp $where";
$countStmt = $conn->prepare($countSql);
if ($countStmt !== false) {
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    if ($countStmt->execute()) {
        $countResult = $countStmt->get_result();
        $totalRows = (int)($countResult->fetch_assoc()['total'] ?? 0);
        $totalPages = max(1, (int)ceil($totalRows / $limit));
    }
}

// Branches for form select (table does not include TRANG_THAI column)
$branches = [];
$branchResult = $conn->query("SELECT ID_CN, TEN_CN FROM chi_nhanh ORDER BY TEN_CN");
if ($branchResult !== false) {
    while ($row = $branchResult->fetch_assoc()) {
        $branches[$row['ID_CN']] = $row['TEN_CN'];
    }
}

// Categories with group info
$categories = [];
$categoryItemCounts = [];  // To store item counts per category
$catResult = $conn->query("SELECT tl.ID_LOAI, tl.TEN_LOAI, tl.MO_TA, tl.ID_NHOM, 
                                   tn.TEN_NHOM, COUNT(tp.ID_TRANG_PHUC) as item_count
                            FROM trang_phuc_loai tl
                            LEFT JOIN trang_phuc_nhom tn ON tn.ID_NHOM = tl.ID_NHOM AND tn.TRANG_THAI = 'active'
                            LEFT JOIN trang_phuc tp ON tp.ID_LOAI = tl.ID_LOAI
                            WHERE tl.TRANG_THAI = 'active'
                            GROUP BY tl.ID_LOAI
                            ORDER BY tn.TEN_NHOM, tl.TEN_LOAI");
if ($catResult !== false) {
    while ($row = $catResult->fetch_assoc()) {
        $categories[$row['ID_LOAI']] = $row;
        $categoryItemCounts[$row['ID_LOAI']] = (int)($row['item_count'] ?? 0);
    }
}

// Groups with category and item counts
$groups = [];
$groupStats = [];  // To store stats per group
$grpResult = $conn->query("SELECT tn.ID_NHOM, tn.TEN_NHOM, tn.MO_TA,
                                   COUNT(DISTINCT tl.ID_LOAI) as cat_count,
                                   COUNT(DISTINCT tp.ID_TRANG_PHUC) as item_count
                            FROM trang_phuc_nhom tn
                            LEFT JOIN trang_phuc_loai tl ON tl.ID_NHOM = tn.ID_NHOM AND tl.TRANG_THAI = 'active'
                            LEFT JOIN trang_phuc tp ON tp.ID_LOAI = tl.ID_LOAI
                            WHERE tn.TRANG_THAI = 'active'
                            GROUP BY tn.ID_NHOM
                            ORDER BY tn.TEN_NHOM");
if ($grpResult !== false) {
    while ($row = $grpResult->fetch_assoc()) {
        $groups[$row['ID_NHOM']] = $row;
        $groupStats[$row['ID_NHOM']] = [
            'cat_count' => (int)($row['cat_count'] ?? 0),
            'item_count' => (int)($row['item_count'] ?? 0)
        ];
    }
}

// Edit data
$editData = null;
$editImages = [];
if ($editId > 0) {
    $editStmt = $conn->prepare("SELECT tp.ID_TRANG_PHUC AS ID_TP, tp.TEN AS TEN_TP, tp.SIZE, tp.MAU_SAC AS MAU, tp.GIA_THUE AS DON_GIA, 
                                       tp.TRANG_THAI AS TINH_TRANG, tp.ID_LOAI, tp.GHI_CHU, tp.UPDATED_AT, tp.SCOPE_TYPE, tp.ID_CN,
                                       cn.TEN_CN
                                FROM trang_phuc tp
                                LEFT JOIN CHI_NHANH cn ON tp.ID_CN = cn.ID_CN
                                WHERE tp.ID_TRANG_PHUC = ?");
    if ($editStmt !== false) {
        $editStmt->bind_param('i', $editId);
        if ($editStmt->execute()) {
            $editResult = $editStmt->get_result();
            $editData = $editResult->fetch_assoc();

            // Fetch images for detail view
            $editImages = [];
            $imgStmt = $conn->prepare("SELECT ID_HA, URL, ALT_TEXT, THU_TU FROM trang_phuc_hinh_anh WHERE ID_TP = ? ORDER BY THU_TU ASC, ID_HA ASC");
            if ($imgStmt !== false) {
                $imgStmt->bind_param('i', $editId);
                if ($imgStmt->execute()) {
                    $imgResult = $imgStmt->get_result();
                    while ($row = $imgResult->fetch_assoc()) {
                        $editImages[] = $row;
                    }
                }
            }
        }
    }
}

// Edit category data
$editCategoryData = null;
if ($editCategoryId > 0 && isset($categories[$editCategoryId])) {
    $editCategoryData = $categories[$editCategoryId];
}

// Edit group data
$editGroupData = null;
if ($editGroupId > 0 && isset($groups[$editGroupId])) {
    $editGroupData = $groups[$editGroupId];
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý Trang Phục</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Button Styling - Soft background colors */
        .btn-soft-green { background-color: #dcfce7 !important; color: #15803d !important; }
        .btn-soft-green:hover { background-color: #bbf7d0 !important; }
        
        .btn-soft-gray { background-color: #f3f4f6 !important; color: #4b5563 !important; }
        .btn-soft-gray:hover { background-color: #e5e7eb !important; }
        
        .btn-soft-indigo { background-color: #e0e7ff !important; color: #4f46e5 !important; }
        .btn-soft-indigo:hover { background-color: #c7d2fe !important; }
        
        .btn-soft-red { background-color: #fee2e2 !important; color: #dc2626 !important; }
        .btn-soft-red:hover { background-color: #fecaca !important; }
        
        button, a[role="button"] { border-radius: 0.5rem; }
    </style>
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
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" data-action="search"
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
                                <button type="submit" class="flex-1 px-4 py-2 text-sm font-semibold btn-soft-indigo transition rounded-lg">
                                    Áp dụng
                                </button>
                                <a href="?page=costumes&tab=list" class="flex-1 px-4 py-2 text-sm font-semibold btn-soft-gray transition rounded-lg text-center">
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
                                        <th class="px-4 py-3 text-left">Ảnh</th>
                                        <th class="px-4 py-3 text-left">Tên Trang Phục</th>
                                        <th class="px-4 py-3 text-left">Loại</th>
                                        <th class="px-4 py-3 text-left">Chi Nhánh</th>
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
                                                    <?php $coverUrl = resolve_costume_image_url($costume['COVER_URL'] ?? ''); ?>
                                                    <?php if ($coverUrl !== ''): ?>
                                                        <div class="w-14 h-14 rounded-lg overflow-hidden border border-gray-200 bg-gray-100">
                                                            <img src="<?= htmlspecialchars($coverUrl) ?>" alt="Ảnh đại diện" class="w-full h-full object-cover">
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="w-14 h-14 rounded-lg border border-dashed border-gray-300 bg-gray-50 flex items-center justify-center text-[10px] text-gray-400">
                                                            No image
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <p class="font-semibold text-gray-900"><?= htmlspecialchars($costume['TEN_TP']) ?></p>
                                                    <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($costume['GHI_CHU'] ?? '') ?></p>
                                                </td>
                                                <td class="px-4 py-3 text-sm">
                                                    <?= htmlspecialchars($costume['TEN_LOAI'] ?? 'Chưa phân loại') ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm">
                                                    <span class="text-gray-700 text-sm"><?= htmlspecialchars($costume['TEN_CN'] ?? '') ?></span>
                                                </td>
                                                <td class="px-4 py-3 text-right font-semibold text-emerald-600">
                                                    <?= number_format((int)($costume['DON_GIA'] ?? 0), 0, ',', '.') ?> ₫
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
                                                    <button data-action="delete_costume" data-costume-id="<?= $costume['ID_TP'] ?>" data-costume-name="<?= htmlspecialchars($costume['TEN_TP']) ?>" 
                                                        class="px-3 py-1 rounded text-xs font-medium border border-red-200 text-red-600 hover:bg-red-50 transition">
                                                        Xóa
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="px-4 py-8 text-center text-gray-500 text-sm">
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
                    <form method="POST" enctype="multipart/form-data" data-action="add_costume" class="bg-white rounded-2xl shadow-md border border-gray-200 p-6 space-y-4">
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
                        
                        <!-- Branch Select (Always show, required) -->
                        <div id="branchSelect">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Chi Nhánh <span class="text-red-500">*</span></label>
                            <select name="ID_CN" id="ID_CN" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none" required>
                                <option value="">-- Chọn chi nhánh --</option>
                                <?php foreach ($branches as $id => $name): ?>
                                    <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Trang phục này sẽ chỉ hiển thị cho chi nhánh đã chọn</p>
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

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Ảnh Bìa</label>
                                <input type="file" name="COVER_IMAGE" accept="image/*" class="w-full text-sm text-gray-600">
                                <p class="text-xs text-gray-500 mt-1">Ảnh lưu tại public/images/trangphuc</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Ảnh Bổ Sung</label>
                                <input type="file" name="GALLERY_IMAGES[]" accept="image/*" multiple class="w-full text-sm text-gray-600">
                                <p class="text-xs text-gray-500 mt-1">Chọn nhiều ảnh, sắp xếp theo thứ tự tải lên</p>
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                            <button type="reset" class="px-4 py-2 text-sm font-semibold btn-soft-gray rounded-lg">
                                Đặt lại
                            </button>
                            <button type="submit" class="px-4 py-2 text-sm font-semibold btn-soft-green rounded-lg">
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
                        <p class="text-xs text-gray-500 mb-4">ID: <?= $editData['ID_TP'] ?> | Cập nhật: <?= ($editData['UPDATED_AT'] ? date('d/m/Y H:i', strtotime($editData['UPDATED_AT'])) : 'N/A') ?></p>
                        
                        <!-- Info Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <p class="text-xs font-semibold text-blue-700 uppercase mb-1">Chi Nhánh</p>
                                <p class="text-sm font-bold text-blue-900">
                                    <?= htmlspecialchars($editData['TEN_CN'] ?? '') ?>
                                </p>
                            </div>
                            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                                <p class="text-xs font-semibold text-purple-700 uppercase mb-1">Chi Nhánh Sở Hữu</p>
                                <p class="text-sm font-bold text-purple-900">
                                    <?php if (($editData['SCOPE_TYPE'] ?? 'global') === 'local' && !empty($editData['TEN_CN'])): ?>
                                        <?= htmlspecialchars($editData['TEN_CN']) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                <p class="text-xs font-semibold text-green-700 uppercase mb-1">Trạng Thái Hiện Tại</p>
                                <p class="text-sm font-bold">
                                    <span class="px-2 py-1 rounded text-xs <?= status_meta($statusCatalog, $editData['TINH_TRANG'])['badge_class'] ?>">
                                        <?= status_meta($statusCatalog, $editData['TINH_TRANG'])['label'] ?>
                                    </span>
                                </p>
                            </div>
                        </div>

                        <?php if (!empty($editImages)): ?>
                            <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-4 space-y-3 mb-6">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-semibold text-gray-800">Hình ảnh</h3>
                                    <span class="text-xs text-gray-500"><?= count($editImages) ?> ảnh</span>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                    <?php foreach ($editImages as $idx => $img): ?>
                                        <div class="border border-gray-200 rounded-lg overflow-hidden bg-gray-50">
                                            <div class="relative h-32 bg-gray-100">
                                                <?php $imgUrl = resolve_costume_image_url($img['URL'] ?? ''); ?>
                                                <?php if ($imgUrl !== ''): ?>
                                                    <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($img['ALT_TEXT'] ?? 'Hình ảnh') ?>" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <div class="w-full h-full flex items-center justify-center text-[11px] text-gray-400">No image</div>
                                                <?php endif; ?>
                                                <?php if ($idx === 0): ?>
                                                    <span class="absolute top-2 left-2 px-2 py-1 text-[10px] font-semibold rounded bg-indigo-600 text-white">Ảnh bìa</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="px-3 py-2 border-t border-gray-100">
                                                <p class="text-xs font-semibold text-gray-800 truncate">#<?= (int)($img['ID_HA'] ?? 0) ?> · Thứ tự <?= (int)($img['THU_TU'] ?? 0) ?></p>
                                                <?php if (!empty($img['ALT_TEXT'])): ?>
                                                    <p class="text-[11px] text-gray-500 truncate"><?= htmlspecialchars($img['ALT_TEXT']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data" data-action="edit_costume" class="bg-white rounded-2xl shadow-md border border-gray-200 p-6 space-y-4">
                            <input type="hidden" name="action" value="edit_costume">
                            <input type="hidden" name="ID_TP" value="<?= $editData['ID_TP'] ?>">
                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Tên Trang Phục *</label>
                                <input type="text" name="TEN_TP" required value="<?= htmlspecialchars($editData['TEN_TP'] ?? '') ?>" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Loại</label>
                                    <select name="ID_LOAI" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                        <option value="">Chưa phân loại</option>
                                        <?php foreach ($categories as $id => $cat): ?>
                                            <option value="<?= $id ?>" <?= (int)($editData['ID_LOAI'] ?? 0) === $id ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['TEN_LOAI']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tình Trạng</label>
                                    <select name="TINH_TRANG" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                        <?php foreach ($statusSelectableKeys as $key): ?>
                                            <option value="<?= $key ?>" <?= ($editData['TINH_TRANG'] ?? '') === $key ? 'selected' : '' ?>>
                                                <?= $statusCatalog[$key]['label'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Branch Select (Always show, required) -->
                            <div id="branchSelect">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Chi Nhánh <span class="text-red-500">*</span></label>
                                <select name="ID_CN" id="ID_CN" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none" required <?= (isset($editData['ID_TP']) ? '' : '') ?>>
                                    <option value="">-- Chọn chi nhánh --</option>
                                    <?php foreach ($branches as $id => $name): ?>
                                        <option value="<?= $id ?>" <?= (int)($editData['ID_CN'] ?? 0) === $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Trang phục này sẽ chỉ hiển thị cho chi nhánh đã chọn</p>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Kích Thước</label>
                                    <input type="text" name="SIZE" value="<?= htmlspecialchars($editData['SIZE'] ?? '') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Màu Sắc</label>
                                    <input type="text" name="MAU" value="<?= htmlspecialchars($editData['MAU'] ?? '') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Đơn Giá Mới (₫)</label>
                                    <input type="number" name="DON_GIA" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Giữ nguyên nếu bỏ trống">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Ghi Chú</label>
                                <textarea name="GHI_CHU" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg"><?= htmlspecialchars($editData['GHI_CHU'] ?? '') ?></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Ảnh Bìa Mới</label>
                                    <input type="file" name="COVER_IMAGE" accept="image/*" class="w-full text-sm text-gray-600">
                                    <p class="text-xs text-gray-500 mt-1">Tải lên để thay ảnh bìa (lưu tại public/images/trangphuc)</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Ảnh Bổ Sung Mới</label>
                                    <input type="file" name="GALLERY_IMAGES[]" accept="image/*" multiple class="w-full text-sm text-gray-600">
                                    <p class="text-xs text-gray-500 mt-1">Ảnh mới sẽ nối tiếp sau danh sách hiện có</p>
                                </div>
                            </div>
                            
                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                                <button type="button" onclick="switchTab('list')" class="px-4 py-2 text-sm font-semibold btn-soft-gray rounded-lg">
                                    Hủy
                                </button>
                                <button type="submit" class="px-4 py-2 text-sm font-semibold btn-soft-green rounded-lg">
                                    Lưu Thay Đổi
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-12 text-gray-500">
                            <p>Chọn trang phục trong danh sách để chỉnh sửa</p>
                            <button type="button" onclick="switchTab('list')" class="mt-4 px-4 py-2 text-sm font-semibold btn-soft-indigo rounded-lg">
                                Quay lại Danh Sách
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 4: LOẠI (CATEGORIES) - Dual Panel -->
            <?php if ($activeTab === 'categories'): ?>
                <div class="grid md:grid-cols-2 gap-6 mb-6">
                    <!-- Left: Dropzone by Group -->
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                        <h3 class="text-lg font-semibold text-indigo-700 mb-4">Loại Trang Phục</h3>
                        <div class="h-96 overflow-y-auto border border-gray-300 rounded-md space-y-2">
                            <?php if (!empty($groups) || !empty($categories)): ?>
                                <!-- Dropzones by Group -->
                                <?php foreach ($groups as $groupId => $group): 
                                    $groupCats = array_filter($categories, function($cat) use ($groupId) {
                                        return ($cat['ID_NHOM'] ?? 0) == $groupId;
                                    });
                                ?>
                                    <div class="border border-gray-300 bg-gray-50 p-3 rounded-md min-h-[80px]"
                                         data-group-id="<?= $groupId ?>"
                                         ondragover="allowDrop(event)"
                                         ondrop="dropCategory(event)">
                                        <p class="text-xs font-semibold text-gray-700 mb-2">
                                            <?= htmlspecialchars($group['TEN_NHOM']) ?>
                                        </p>
                                        <div class="space-y-1">
                                            <?php foreach ($groupCats as $catId => $cat): ?>
                                                <div class="bg-white border border-gray-300 p-2 rounded text-xs hover:bg-indigo-50 transition"
                                                     data-category-id="<?= $catId ?>">
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div class="flex-1 cursor-move" draggable="true" ondragstart="dragStart(event)">
                                                            <p class="font-medium text-gray-900"><?= htmlspecialchars($cat['TEN_LOAI']) ?></p>
                                                            <p class="text-gray-500 text-[10px]"><?= $categoryItemCounts[$catId] ?? 0 ?> item</p>
                                                        </div>
                                                        <div class="flex gap-1 flex-shrink-0">
                                                            <button type="button" onclick="switchTab('categories', 0, <?= $catId ?>)" 
                                                                class="px-2 py-0.5 text-[10px] font-semibold text-indigo-600 hover:text-indigo-800 border border-indigo-200 rounded hover:bg-indigo-50">
                                                                Sửa
                                                            </button>
                                                            <button type="button" onclick="deleteCategoryItem(<?= $catId ?>, '<?= htmlspecialchars($cat['TEN_LOAI'], ENT_QUOTES) ?>', <?= $categoryItemCounts[$catId] ?? 0 ?>)" 
                                                                class="px-2 py-0.5 text-[10px] font-semibold text-red-600 hover:text-red-800 border border-red-200 rounded hover:bg-red-50">
                                                                Xóa
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <!-- Unassigned Dropzone -->
                                <div class="border border-gray-300 bg-gray-50 p-3 rounded-md min-h-[80px]"
                                     data-group-id="0"
                                     ondragover="allowDrop(event)"
                                     ondrop="dropCategory(event)">
                                    <p class="text-xs font-semibold text-gray-700 mb-2">
                                        Chưa gán nhóm
                                    </p>
                                    <div class="space-y-1">
                                        <?php 
                                        $unassignedCats = array_filter($categories, function($cat) {
                                            return ($cat['ID_NHOM'] ?? 0) == 0;
                                        });
                                        foreach ($unassignedCats as $catId => $cat): 
                                        ?>
                                            <div class="bg-white border border-gray-300 p-2 rounded text-xs hover:bg-indigo-50 transition"
                                                 data-category-id="<?= $catId ?>">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="flex-1 cursor-move" draggable="true" ondragstart="dragStart(event)">
                                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($cat['TEN_LOAI']) ?></p>
                                                        <p class="text-gray-500 text-[10px]"><?= $categoryItemCounts[$catId] ?? 0 ?> item</p>
                                                    </div>
                                                    <div class="flex gap-1 flex-shrink-0">
                                                        <button type="button" onclick="switchTab('categories', 0, <?= $catId ?>)" 
                                                            class="px-2 py-0.5 text-[10px] font-semibold text-indigo-600 hover:text-indigo-800 border border-indigo-200 rounded hover:bg-indigo-50">
                                                            Sửa
                                                        </button>
                                                        <button type="button" onclick="deleteCategoryItem(<?= $catId ?>, '<?= htmlspecialchars($cat['TEN_LOAI'], ENT_QUOTES) ?>', <?= $categoryItemCounts[$catId] ?? 0 ?>)" 
                                                            class="px-2 py-0.5 text-[10px] font-semibold text-red-600 hover:text-red-800 border border-red-200 rounded hover:bg-red-50">
                                                            Xóa
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="p-3 text-gray-500 text-xs text-center">Chưa có loại trang phục nào</div>
                            <?php endif; ?>
                        </div>
                        <p class="text-[11px] text-gray-500 mt-2">Kéo loại để thay đổi nhóm.</p>
                    </div>
                    
                    <!-- Right: Form -->
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                        <?php if ($editCategoryData): ?>
                            <h3 class="text-lg font-semibold text-indigo-700 mb-4">Sửa Loại Trang Phục</h3>
                            <form method="POST" data-action="edit_category" class="space-y-3">
                                <input type="hidden" name="action" value="edit_category">
                                <input type="hidden" name="ID_LOAI" value="<?= $editCategoryId ?>">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Tên Loại *</label>
                                    <input type="text" name="TEN_LOAI" required value="<?= htmlspecialchars($editCategoryData['TEN_LOAI'] ?? '') ?>" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md">
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Mô Tả</label>
                                    <textarea name="MO_TA" rows="2" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md"><?= htmlspecialchars($editCategoryData['MO_TA'] ?? '') ?></textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nhóm Trang Phục</label>
                                    <select name="ID_NHOM" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md">
                                        <option value="0">— Không gán nhóm —</option>
                                        <?php foreach ($groups as $group): ?>
                                            <option value="<?= $group['ID_NHOM'] ?>" <?= ($editCategoryData['ID_NHOM'] ?? 0) == $group['ID_NHOM'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($group['TEN_NHOM']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="flex gap-2 pt-3 border-t border-gray-200">
                                    <button type="submit" class="flex-1 px-3 py-2 text-sm font-semibold btn-soft-green rounded-lg">Cập nhật</button>
                                    <button type="button" onclick="switchTab('categories', 0, 0)" class="flex-1 px-3 py-2 text-sm font-semibold btn-soft-gray rounded-lg">Hủy</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <h3 class="text-lg font-semibold text-indigo-700 mb-4">Thêm Loại Trang Phục</h3>
                            <form method="POST" data-action="add_category" class="space-y-3">
                                <input type="hidden" name="action" value="add_category">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Tên Loại *</label>
                                    <input type="text" name="TEN_LOAI" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md">
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Mô Tả</label>
                                    <textarea name="MO_TA" rows="2" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md"></textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nhóm Trang Phục</label>
                                    <select name="ID_NHOM" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md">
                                        <option value="0">— Không gán nhóm —</option>
                                        <?php foreach ($groups as $group): ?>
                                            <option value="<?= $group['ID_NHOM'] ?>">
                                                <?= htmlspecialchars($group['TEN_NHOM']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="flex gap-2 pt-3 border-t border-gray-200">
                                    <button type="submit" class="flex-1 px-3 py-2 text-sm font-semibold btn-soft-green rounded-lg">Lưu</button>
                                    <button type="reset" class="flex-1 px-3 py-2 text-sm font-semibold btn-soft-gray rounded-lg">Đặt lại</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- JavaScript for Drag & Drop -->
                <script>
                let draggedCategoryId = null;
                
                function dragStart(event) {
                    draggedCategoryId = event.currentTarget.getAttribute('data-category-id');
                    event.currentTarget.style.opacity = '0.6';
                    event.dataTransfer.effectAllowed = 'move';
                }
                
                function allowDrop(event) {
                    event.preventDefault();
                    event.dataTransfer.dropEffect = 'move';
                    event.currentTarget.style.backgroundColor = '#fef3c7';
                }
                
                function dropCategory(event) {
                    event.preventDefault();
                    const dropzone = event.currentTarget;
                    dropzone.style.backgroundColor = '';
                    
                    if (!draggedCategoryId) return;
                    
                    const groupId = dropzone.getAttribute('data-group-id');
                    
                    // AJAX POST - silent operation
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=drag_drop_category&ID_LOAI=' + draggedCategoryId + '&ID_NHOM=' + groupId + '&csrf=<?= csrf_token() ?>'
                    })
                    .then(response => response.text())
                    .then(data => {
                        if (data.includes('success')) {
                            // Reload to reflect database changes
                            setTimeout(() => location.reload(), 300);
                        }
                        // Silently fail - user will notice nothing moved
                    })
                    .catch(() => {
                        // Network error - silently ignore
                    });
                    
                    draggedCategoryId = null;
                }
                </script>
            <?php endif; ?>

            <!-- TAB 5: NHÓM (GROUPS) - Dual Panel -->
            <?php if ($activeTab === 'groups'): ?>
                <div class="grid md:grid-cols-2 gap-6 mb-6">
                    <!-- Left: List -->
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                        <h3 class="text-lg font-semibold text-indigo-700 mb-4">Nhóm Trang Phục</h3>
                        <div class="h-96 overflow-y-auto border border-gray-300 rounded-md">
                            <?php if (!empty($groups)): ?>
                                <?php foreach ($groups as $id => $grp): 
                                    $stats = $groupStats[$id] ?? ['cat_count' => 0, 'item_count' => 0];
                                    $groupCats = array_filter($categories, function($cat) use ($id) {
                                        return ($cat['ID_NHOM'] ?? 0) == $id;
                                    });
                                ?>
                                    <div class="flex items-center justify-between px-3 py-2 text-xs border-b bg-gray-50 hover:bg-gray-100">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <p class="font-medium text-gray-900"><?= htmlspecialchars($grp['TEN_NHOM']) ?></p>
                                                <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-indigo-100 text-indigo-700 whitespace-nowrap">
                                                    <?= $stats['cat_count'] ?> loại
                                                </span>
                                            </div>
                                            <p class="text-[10px] text-gray-500"><?= $stats['item_count'] ?> items</p>
                                        </div>
                                        <div class="flex gap-2 ml-2 flex-shrink-0">
                                            <button type="button" onclick="switchTab('groups', 0, 0, <?= $id ?>)" 
                                                class="px-2 py-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800 border border-indigo-200 rounded hover:bg-indigo-50">
                                                Sửa
                                            </button>
                                            <button type="button" onclick="deleteGroupItem(<?= $id ?>, '<?= htmlspecialchars($grp['TEN_NHOM'], ENT_QUOTES) ?>', <?= $stats['cat_count'] ?>)" 
                                                class="px-2 py-1 text-xs font-semibold text-red-600 hover:text-red-800 border border-red-200 rounded hover:bg-red-50">
                                                Xóa
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="p-3 text-gray-500 text-xs text-center">Chưa có nhóm trang phục nào</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Right: Form -->
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                        <?php if ($editGroupData): ?>
                            <h3 class="text-lg font-semibold text-indigo-700 mb-4">Sửa Nhóm Trang Phục</h3>
                            <form method="POST" data-action="edit_group" class="space-y-3">
                                <input type="hidden" name="action" value="edit_group">
                                <input type="hidden" name="ID_NHOM" value="<?= $editGroupId ?>">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Tên Nhóm *</label>
                                    <input type="text" name="TEN_NHOM" required value="<?= htmlspecialchars($editGroupData['TEN_NHOM'] ?? '') ?>" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md">
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Mô Tả</label>
                                    <textarea name="MO_TA" rows="2" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md"><?= htmlspecialchars($editGroupData['MO_TA'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="flex gap-2 pt-3 border-t border-gray-200">
                                    <button type="submit" class="flex-1 px-3 py-2 text-sm font-semibold btn-soft-green rounded-lg">Cập nhật</button>
                                    <button type="button" onclick="switchTab('groups', 0, 0)" class="flex-1 px-3 py-2 text-sm font-semibold btn-soft-gray rounded-lg">Hủy</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <h3 class="text-lg font-semibold text-indigo-700 mb-4">Thêm Nhóm Trang Phục</h3>
                            <form method="POST" data-action="add_group" class="space-y-3">
                                <input type="hidden" name="action" value="add_group">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Tên Nhóm *</label>
                                    <input type="text" name="TEN_NHOM" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md">
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Mô Tả</label>
                                    <textarea name="MO_TA" rows="2" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md"></textarea>
                                </div>
                                
                                <div class="flex gap-2 pt-3 border-t border-gray-200">
                                    <button type="submit" class="flex-1 px-3 py-2 text-sm font-semibold btn-soft-green rounded-lg">Lưu</button>
                                    <button type="reset" class="flex-1 px-3 py-2 text-sm font-semibold btn-soft-gray rounded-lg">Đặt lại</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<!-- Tab Navigation Script -->
<script>
function switchTab(tab, editId = 0, catId = 0, grpId = 0) {
    let url = `?page=costumes&tab=${tab}`;
    if (editId > 0) url += `&edit=${editId}`;
    if (catId > 0) url += `&cat_edit=${catId}`;
    if (grpId > 0) url += `&grp_edit=${grpId}`;
    window.location.href = url;
}

function deleteCategoryItem(categoryId, categoryName, itemCount) {
    if (itemCount > 0) {
        if (!confirm(`Loại "${categoryName}" có ${itemCount} trang phục đang sử dụng.\n\nKhông thể xóa loại này!`)) {
            return;
        }
        alert('Không thể xóa loại có trang phục đang sử dụng!');
        return;
    }
    
    if (!confirm(`Xác nhận xóa loại "${categoryName}"?`)) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href;
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete_category';
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'ID_LOAI';
    idInput.value = categoryId;
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf';
    csrfInput.value = '<?= csrf_token() ?>';
    
    form.appendChild(actionInput);
    form.appendChild(idInput);
    form.appendChild(csrfInput);
    document.body.appendChild(form);
    form.submit();
}

function deleteGroupItem(groupId, groupName, categoryCount) {
    if (categoryCount > 0) {
        if (!confirm(`Nhóm "${groupName}" có ${categoryCount} loại đang thuộc nhóm này.\n\nKhông thể xóa nhóm này!`)) {
            return;
        }
        alert('Không thể xóa nhóm có loại đang thuộc!');
        return;
    }
    
    if (!confirm(`Xác nhận xóa nhóm "${groupName}"?`)) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href;
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete_group';
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'ID_NHOM';
    idInput.value = groupId;
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf';
    csrfInput.value = '<?= csrf_token() ?>';
    
    form.appendChild(actionInput);
    form.appendChild(idInput);
    form.appendChild(csrfInput);
    document.body.appendChild(form);
    form.submit();
}

// Không còn lựa chọn global/local, luôn yêu cầu chọn chi nhánh
// Không cho phép đổi chi nhánh nếu trang phục đã thuộc gói trang phục
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($activeTab === 'detail' && $editData): ?>
    fetch('api/check_costume_in_package.php?id_tp=<?= (int)$editData['ID_TP'] ?>')
        .then(res => res.json())
        .then(data => {
            if (data && data.in_package) {
                const branchSelect = document.getElementById('ID_CN');
                if (branchSelect) {
                    branchSelect.disabled = true;
                    branchSelect.title = 'Không thể đổi chi nhánh vì trang phục đang thuộc gói trang phục';
                }
            }
        });
    <?php endif; ?>
});
</script>

<!-- API Client Utilities -->
<script src="../../public/assets/js/api-client.js"></script>

<!-- Page-Specific Costume Management -->
<script src="../../public/assets/js/manage-costumes.js"></script>

</body>
</html>
