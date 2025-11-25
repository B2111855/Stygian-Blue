<?php
include '../../database/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once __DIR__ . '/../../repositories/ServiceRepository.php';
require_once __DIR__ . '/../../repositories/ServiceCategoryTagRepository.php';
require_once __DIR__ . '/../../repositories/PackageServiceRepository.php';
require_once __DIR__ . '/../../services/DeletionPolicyService.php';
require_once __DIR__ . '/../../helpers/system_log.php';
require_once __DIR__ . '/../../helpers/service_statistics.php';
require_once __DIR__ . '/../../helpers/image_helper.php';

use App\Repositories\ServiceRepository;
use App\Repositories\ServiceCategoryRepository;
use App\Repositories\ServiceTagRepository;
use App\Repositories\PackageServiceRepository;
use App\Services\DeletionPolicyService;

$repo   = new ServiceRepository($conn);
$policy = new DeletionPolicyService($conn);
$categoryRepo = new ServiceCategoryRepository($conn);
$tagRepo = new ServiceTagRepository($conn);
$packageRepo = new PackageServiceRepository($conn);

$categories = $categoryRepo->getAll();
$allTags = $tagRepo->getAll();

function service_url(array $params, array $overrides = []): string
{
    $query = array_merge($params, $overrides);
    $clean = [];
    foreach ($query as $key => $value) {
        if ($value === null) {
            continue;
        }
        if ($value === '' && $value !== '0') {
            continue;
        }
        $clean[$key] = $value;
    }
    $clean = array_merge(['page' => 'services'], $clean);
    return '?' . http_build_query($clean);
}

// Handle Category Management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $tenDanhMuc = trim($_POST['TEN_DANH_MUC'] ?? '');
    if ($tenDanhMuc !== '') {
        $stmt = $conn->prepare("INSERT INTO danh_muc_dich_vu (TEN_DANH_MUC) VALUES (?)");
        $stmt->bind_param('s', $tenDanhMuc);
        if ($stmt->execute()) {
            $successMessage = 'Danh mục đã được thêm thành công!';
            $categories = $categoryRepo->getAll(); // Reload
        } else {
            $errorMessage = 'Lỗi khi thêm danh mục.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $idDanhMuc = (int)$_POST['ID_DANH_MUC'];
    $tenDanhMuc = trim($_POST['TEN_DANH_MUC'] ?? '');
    if ($tenDanhMuc !== '') {
        $stmt = $conn->prepare("UPDATE danh_muc_dich_vu SET TEN_DANH_MUC=? WHERE ID_DANH_MUC=?");
        $stmt->bind_param('si', $tenDanhMuc, $idDanhMuc);
        if ($stmt->execute()) {
            $successMessage = 'Danh mục đã được cập nhật!';
            $categories = $categoryRepo->getAll();
        } else {
            $errorMessage = 'Lỗi khi cập nhật danh mục.';
        }
    }
}

if (isset($_GET['delete_category'])) {
    $idDanhMuc = (int)$_GET['delete_category'];
    // Check if category is in use
    $checkStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM dich_vu WHERE ID_DANH_MUC=?");
    $checkStmt->bind_param('i', $idDanhMuc);
    $checkStmt->execute();
    $result = $checkStmt->get_result()->fetch_assoc();
    
    if ($result['cnt'] > 0) {
        $errorMessage = 'Không thể xóa danh mục đang có dịch vụ sử dụng!';
    } else {
        $stmt = $conn->prepare("DELETE FROM danh_muc_dich_vu WHERE ID_DANH_MUC=?");
        $stmt->bind_param('i', $idDanhMuc);
        if ($stmt->execute()) {
            $successMessage = 'Danh mục đã được xóa!';
            $categories = $categoryRepo->getAll();
        } else {
            $errorMessage = 'Lỗi khi xóa danh mục.';
        }
    }
}

// Handle Tag Management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tag'])) {
    $tenThe = trim($_POST['TEN_THE'] ?? '');
    $mauSac = trim($_POST['MAU_SAC'] ?? '#3B82F6');
    if ($tenThe !== '') {
        $stmt = $conn->prepare("INSERT INTO the_dich_vu (TEN_THE, MAU_SAC) VALUES (?, ?)");
        $stmt->bind_param('ss', $tenThe, $mauSac);
        if ($stmt->execute()) {
            $successMessage = 'Thẻ đã được thêm thành công!';
            $allTags = $tagRepo->getAll();
        } else {
            $errorMessage = 'Lỗi khi thêm thẻ.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_tag'])) {
    $idThe = (int)$_POST['ID_THE'];
    $tenThe = trim($_POST['TEN_THE'] ?? '');
    $mauSac = trim($_POST['MAU_SAC'] ?? '#3B82F6');
    if ($tenThe !== '') {
        $stmt = $conn->prepare("UPDATE the_dich_vu SET TEN_THE=?, MAU_SAC=? WHERE ID_THE=?");
        $stmt->bind_param('ssi', $tenThe, $mauSac, $idThe);
        if ($stmt->execute()) {
            $successMessage = 'Thẻ đã được cập nhật!';
            $allTags = $tagRepo->getAll();
        } else {
            $errorMessage = 'Lỗi khi cập nhật thẻ.';
        }
    }
}

if (isset($_GET['delete_tag'])) {
    $idThe = (int)$_GET['delete_tag'];
    // Check if tag is in use
    $checkStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM dich_vu_the WHERE ID_THE=?");
    $checkStmt->bind_param('i', $idThe);
    $checkStmt->execute();
    $result = $checkStmt->get_result()->fetch_assoc();
    
    if ($result['cnt'] > 0) {
        $errorMessage = 'Không thể xóa thẻ đang có dịch vụ sử dụng!';
    } else {
        $stmt = $conn->prepare("DELETE FROM the_dich_vu WHERE ID_THE=?");
        $stmt->bind_param('i', $idThe);
        if ($stmt->execute()) {
            $successMessage = 'Thẻ đã được xóa!';
            $allTags = $tagRepo->getAll();
        } else {
            $errorMessage = 'Lỗi khi xóa thẻ.';
        }
    }
}

// Load statistics if requested
$showStats = isset($_GET['stats']) && $_GET['stats'] === '1';
$statistics = null;
if ($showStats) {
    $statsHelper = new ServiceStatistics($conn);
    $statistics = [
        'overall' => $statsHelper->getOverallStats(),
        'popular' => $statsHelper->getMostPopularServices(5),
        'revenue' => $statsHelper->getServiceRevenue(5),
        'unused' => $statsHelper->getUnusedServices(30)
    ];
}

// Load statistics if requested
$showStats = isset($_GET['stats']) && $_GET['stats'] === '1';
$statistics = null;
if ($showStats) {
    $statsHelper = new ServiceStatistics($conn);
    $statistics = [
        'overall' => $statsHelper->getOverallStats(),
        'popular' => $statsHelper->getMostPopularServices(5),
        'revenue' => $statsHelper->getServiceRevenue(5),
        'unused' => $statsHelper->getUnusedServices(30)
    ];
}


$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
if (!in_array($statusFilter, ['all', 'active', 'draft', 'retired'], true)) {
    $statusFilter = 'all';
}

$minPrice = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? max(0, (int)$_GET['min_price']) : null;
$maxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? max(0, (int)$_GET['max_price']) : null;
$categoryFilter = isset($_GET['category']) && $_GET['category'] !== '' ? (int)$_GET['category'] : null;
if ($categoryFilter !== null && $categoryFilter <= 0) {
    $categoryFilter = null;
}
$tagFilter = isset($_GET['tag']) && $_GET['tag'] !== '' ? (int)$_GET['tag'] : null;
if ($tagFilter !== null && $tagFilter <= 0) {
    $tagFilter = null;
}

$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'ID_DV';
$sortOrder = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
$page   = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit  = 5;
$offset = ($page - 1) * $limit;
$viewMode = isset($_GET['view']) && $_GET['view'] === 'grid' ? 'grid' : 'table';

$serviceQueryParams = [
    'search' => $search !== '' ? $search : null,
    'status' => $statusFilter !== 'all' ? $statusFilter : null,
    'min_price' => $minPrice,
    'max_price' => $maxPrice,
    'category' => $categoryFilter,
    'tag' => $tagFilter,
    'sort' => $sortBy !== 'ID_DV' ? $sortBy : null,
    'order' => $sortOrder !== 'DESC' ? $sortOrder : null,
    'view' => $viewMode !== 'table' ? $viewMode : null,
    'p' => $page > 1 ? $page : null,
];

$serviceBaseUrl = service_url($serviceQueryParams);
$serviceBaseUrlEsc = htmlspecialchars($serviceBaseUrl, ENT_QUOTES);
$serviceBaseUrlJs = addslashes($serviceBaseUrl);

$exportQueryParams = array_filter([
    'search' => $search !== '' ? $search : null,
    'status' => $statusFilter !== 'all' ? $statusFilter : null,
    'min_price' => $minPrice,
    'max_price' => $maxPrice,
    'category' => $categoryFilter,
    'tag' => $tagFilter,
    'sort' => $sortBy !== 'ID_DV' ? $sortBy : null,
    'order' => $sortOrder !== 'DESC' ? $sortOrder : null,
], static function ($value) {
    return $value !== null && $value !== '';
});
$exportQueryString = $exportQueryParams ? '&' . http_build_query($exportQueryParams) : '';

$totalRows  = $repo->countServices($search, $statusFilter === 'all' ? null : $statusFilter, $minPrice, $maxPrice, $categoryFilter, $tagFilter);
$totalPages = max(1, (int)ceil($totalRows / $limit));
$services   = $repo->searchServices($search, $limit, $offset, $statusFilter === 'all' ? null : $statusFilter, $minPrice, $maxPrice, $categoryFilter, $tagFilter, $sortBy, $sortOrder);

// Price history modal data
$priceHistory = [];
$historyService = null;
if (isset($_GET['history'])) {
    $historyId = (int)$_GET['history'];
    $historyService = $repo->findById($historyId);
    if ($historyService) {
        $priceHistory = $repo->getPriceHistory($historyId, 50);
    }
}


function handleUploadImage(string $field): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    
    try {
        $result = ImageHelper::uploadImage($_FILES[$field], 'public/images/dichvu', [
            'max_width' => 1920,
            'max_height' => 1920,
            'create_thumb' => true
        ]);
        
        return $result ? $result['path'] : null;
    } catch (\Exception $e) {
        error_log('Image upload error: ' . $e->getMessage());
        return null;
    }
}

// Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $payload = [
        'TEN_DV'    => trim($_POST['TEN_DV'] ?? ''),
        'MOTA_DV'   => trim($_POST['MOTA_DV'] ?? ''),
        'THOI_GIAN' => (int)($_POST['THOI_GIAN'] ?? 0),
        'GIA'       => (int)($_POST['GIA'] ?? 0),
        'ID_DANH_MUC' => isset($_POST['ID_DANH_MUC']) && $_POST['ID_DANH_MUC'] !== '' ? (int)$_POST['ID_DANH_MUC'] : null,
    ];
    if ($payload['TEN_DV'] === '' || $payload['MOTA_DV'] === '' || $payload['THOI_GIAN'] <= 0 || $payload['GIA'] <= 0) {
        $errorMessage = 'Dữ liệu không hợp lệ.';
    } else {
        $img = handleUploadImage('IMAGE');
        try {
            $new = $repo->create($payload, $img);
            
            // Set tags
            if (isset($_POST['tags']) && is_array($_POST['tags'])) {
                $tagIds = array_map('intval', $_POST['tags']);
                $tagRepo->setTagsForService($new['ID_DV'], $tagIds);
            }
            
            $successMessage = 'Dịch vụ đã được thêm thành công!';
            record_system_log($conn, 'SERVICE_CREATE', 'service:' . $new['ID_DV'], null, [
                'ID_DV' => $new['ID_DV'],
                'TEN_DV' => $new['TEN_DV'],
                'THOI_GIAN' => $new['THOI_GIAN'],
                'TRANG_THAI' => $new['TRANG_THAI'] ?? null
            ]);
        } catch (\Throwable $e) {
            $errorMessage = 'Lỗi thêm dịch vụ: ' . $e->getMessage();
        }
    }
}

// Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_service'])) {
    $payload = [
        'ID_DV'     => (int)$_POST['ID_DV'],
        'TEN_DV'    => trim($_POST['TEN_DV'] ?? ''),
        'MOTA_DV'   => trim($_POST['MOTA_DV'] ?? ''),
        'THOI_GIAN' => (int)($_POST['THOI_GIAN'] ?? 0),
        'GIA'       => (int)($_POST['GIA'] ?? 0),
        'ID_DANH_MUC' => isset($_POST['ID_DANH_MUC']) && $_POST['ID_DANH_MUC'] !== '' ? (int)$_POST['ID_DANH_MUC'] : null,
    ];
    $img = handleUploadImage('IMAGE');
    if ($payload['TEN_DV'] === '' || $payload['MOTA_DV'] === '' || $payload['THOI_GIAN'] <= 0) {
        $errorMessage = 'Dữ liệu cập nhật không hợp lệ.';
    } else {
        $before = $repo->findById($payload['ID_DV']);
        if ($repo->update($payload, $img)) {
            // Update tags
            if (isset($_POST['tags']) && is_array($_POST['tags'])) {
                $tagIds = array_map('intval', $_POST['tags']);
                $tagRepo->setTagsForService($payload['ID_DV'], $tagIds);
            } else {
                $tagRepo->setTagsForService($payload['ID_DV'], []);
            }
            
            $after = $repo->findById($payload['ID_DV']);
            $successMessage = 'Dịch vụ đã được cập nhật thành công!';
            record_system_log($conn, 'SERVICE_UPDATE', 'service:' . $payload['ID_DV'], $before, $after);
        } else {
            $errorMessage = 'Lỗi khi cập nhật dịch vụ.';
        }
    }
}

// Duplicate Service
if (isset($_GET['duplicate'])) {
    $sourceId = (int)$_GET['duplicate'];
    $source = $repo->findById($sourceId);
    
    if ($source) {
        $payload = [
            'TEN_DV'    => $source['TEN_DV'] . ' (Sao chép)',
            'MOTA_DV'   => $source['MOTA_DV'],
            'THOI_GIAN' => (int)$source['THOI_GIAN'],
            'GIA'       => isset($source['DON_GIA']) ? (int)$source['DON_GIA'] : 0,
        ];
        
        try {
            // Copy image if exists
            $newImagePath = null;
            if ($source['IMAGE']) {
                $sourceImagePath = $_SERVER['DOCUMENT_ROOT'] . '/' . $source['IMAGE'];
                if (file_exists($sourceImagePath)) {
                    $ext = pathinfo($sourceImagePath, PATHINFO_EXTENSION);
                    $newName = bin2hex(random_bytes(8)) . '.' . $ext;
                    $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/public/images/dichvu/';
                    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                    $newImagePath = 'public/images/dichvu/' . $newName;
                    copy($sourceImagePath, $_SERVER['DOCUMENT_ROOT'] . '/' . $newImagePath);
                }
            }
            
            $new = $repo->create($payload, $newImagePath);
            $successMessage = 'Dịch vứ đã được sao chép thành công! (ID: ' . $new['ID_DV'] . ')';
            record_system_log($conn, 'SERVICE_DUPLICATE', 'service:' . $new['ID_DV'], null, [
                'source_id' => $sourceId,
                'new_id' => $new['ID_DV'],
                'TEN_DV' => $new['TEN_DV']
            ]);
        } catch (\Throwable $e) {
            $errorMessage = 'Lỗi sao chép dịch vụ: ' . $e->getMessage();
        }
    } else {
        $errorMessage = 'Không tìm thấy dịch vụ gốc.';
    }
}

// Retire (soft delete) final action
if (isset($_GET['delete'])) {
    $id_dv = (int)$_GET['delete'];
    $reasons = $policy->serviceDeletionCheck($id_dv);
    if ($policy->canRetire($id_dv)) {
        $before = $repo->findById($id_dv);
        if ($repo->retire($id_dv)) {
            $after = $repo->findById($id_dv);
            record_system_log($conn, 'SERVICE_RETIRE', 'service:' . $id_dv, $before, $after);
            $redirectUrl = service_url($serviceQueryParams, ['success' => 'retire']);
            echo '<script>window.location.href="' . htmlspecialchars($redirectUrl, ENT_QUOTES) . '";</script>';
            exit;
        } else {
            $errorMessage = 'Không thể chuyển trạng thái dịch vụ.';
        }
    } else {
        $errorMessage = 'Không thể ngừng dịch vụ: ' . $policy->formatReasons($reasons);
    }
}

// Change Status (flexible state transitions)
if (isset($_GET['change_status'])) {
    $id_dv = (int)$_GET['change_status'];
    $newStatus = $_GET['new_status'] ?? '';
    $reason = isset($_GET['reason']) ? trim($_GET['reason']) : '';
    
    $validStatuses = ['active', 'draft', 'retired'];
    if (!in_array($newStatus, $validStatuses)) {
        $errorMessage = 'Trạng thái không hợp lệ.';
    } else {
        $service = $repo->findById($id_dv);
        if ($service) {
            $currentStatus = $service['TRANG_THAI'];
            
            // Check if retiring and validate
            if ($newStatus === 'retired' && !$policy->canRetire($id_dv)) {
                $reasons = $policy->serviceDeletionCheck($id_dv);
                $errorMessage = 'Không thể chuyển sang retired: ' . $policy->formatReasons($reasons);
            } else {
                $stmt = $conn->prepare("UPDATE dich_vu SET TRANG_THAI=? WHERE ID_DV=?");
                $stmt->bind_param('si', $newStatus, $id_dv);
                
                if ($stmt->execute()) {
                    $after = $repo->findById($id_dv);
                    
                    $logData = [
                        'from' => $currentStatus,
                        'to' => $newStatus,
                        'reason' => $reason
                    ];
                    record_system_log($conn, 'SERVICE_STATUS_CHANGE', 'service:' . $id_dv, $service, array_merge($after, $logData));
                    
                    $redirectUrl = service_url($serviceQueryParams, [
                        'success' => 'status_change',
                        'from' => $currentStatus,
                        'to' => $newStatus,
                    ]);
                    echo '<script>window.location.href="' . htmlspecialchars($redirectUrl, ENT_QUOTES) . '";</script>';
                    exit;
                } else {
                    $errorMessage = 'Không thể cập nhật trạng thái.';
                }
            }
        } else {
            $errorMessage = 'Không tìm thấy dịch vụ.';
        }
    }
}

// Pre-retire confirmation modal data
$confirmRetireService = null;
$confirmRetireReasons = [];
if (isset($_GET['confirm_retire'])) {
    $cid = (int)$_GET['confirm_retire'];
    $confirmRetireService = $repo->findById($cid);
    if ($confirmRetireService) {
        $confirmRetireReasons = $policy->serviceDeletionCheck($cid);
    }
}

// Status change modal data
$changeStatusService = null;
if (isset($_GET['status_modal'])) {
    $sid = (int)$_GET['status_modal'];
    $changeStatusService = $repo->findById($sid);
}

// Related packages modal data
$packagesService = null;
$relatedPackages = [];
if (isset($_GET['packages'])) {
    $pid = (int)$_GET['packages'];
    $packagesService = $repo->findById($pid);
    if ($packagesService) {
        $relatedPackages = $packageRepo->getPackagesContainingService($pid);
    }
}

$isEditing = isset($_GET['edit']);
$editService = null;
if ($isEditing) {
    $editService = $repo->findById((int)$_GET['edit']);
}

// Handle success messages from redirects
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'retire':
            $successMessage = 'Dịch vụ đã được chuyển sang trạng thái retired.';
            break;
        case 'status_change':
            $from = $_GET['from'] ?? '';
            $to = $_GET['to'] ?? '';
            $successMessage = "Trạng thái đã được chuyển từ '{$from}' sang '{$to}'.";
            break;
    }
}
?>

<body class="bg-gray-100 p-6">
    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Optimized table styles for desktop */
        .compact-table {
            font-size: 0.813rem; /* 13px */
        }
        .compact-table th,
        .compact-table td {
            padding: 0.5rem 0.375rem !important;
        }
        .compact-table .w-action {
            width: 80px;
            min-width: 80px;
        }
        .compact-table .w-checkbox {
            width: 40px;
            min-width: 40px;
        }
        .compact-table .w-id {
            width: 50px;
            min-width: 50px;
        }
        .compact-table .w-img {
            width: 60px;
            min-width: 60px;
        }
        .compact-table .w-status {
            width: 90px;
            min-width: 90px;
        }
        .compact-table .w-category {
            width: 110px;
            min-width: 110px;
        }
        .compact-table .w-tags {
            width: 80px;
            min-width: 80px;
        }
        .compact-table .w-duration {
            width: 85px;
            min-width: 85px;
        }
        .compact-table .w-price {
            width: 100px;
            min-width: 100px;
        }
        
        /* Tag counter bubble */
        .tag-counter {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: help;
            transition: transform 0.2s;
        }
        .tag-counter:hover {
            transform: scale(1.1);
        }
        .tag-counter svg {
            width: 12px;
            height: 12px;
            margin-right: 2px;
        }
        
        /* Tooltip for tags */
        .tag-tooltip {
            position: absolute;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 50;
            display: none;
            min-width: 150px;
            max-width: 250px;
        }
        .tag-tooltip.show {
            display: block;
        }
        
        /* Floating notification styles */
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            pointer-events: none;
        }
        .notification {
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            padding: 1rem 1.25rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            pointer-events: auto;
            transform: translateX(400px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        .notification.show {
            transform: translateX(0);
            opacity: 1;
        }
        .notification.hide {
            transform: translateX(400px);
            opacity: 0;
        }
        .notification-success {
            border-left: 4px solid #10b981;
        }
        .notification-error {
            border-left: 4px solid #ef4444;
        }
        .notification-icon {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
        }
        .notification-content {
            flex: 1;
            font-size: 0.875rem;
        }
        .notification-close {
            flex-shrink: 0;
            cursor: pointer;
            color: #9ca3af;
            transition: color 0.2s;
        }
        .notification-close:hover {
            color: #374151;
        }
        .notification-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 0 0 0 8px;
            animation: progressBar 5s linear forwards;
        }
        @keyframes progressBar {
            from { width: 100%; }
            to { width: 0%; }
        }
        
        /* Compact action buttons */
        .action-btn-compact {
            padding: 0.375rem 0.5rem;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
        }
        .action-btn-compact svg {
            width: 14px;
            height: 14px;
        }
    </style>
    <div class="max-w-7xl mx-auto">
        <iframe id="downloadFrame" name="downloadFrame" style="display:none;"></iframe>
        <h1 class="text-3xl font-bold text-indigo-700 mb-6 text-center">Quản lý dịch vụ</h1>

        <!-- Notification Container (Floating) -->
        <div id="notificationContainer" class="notification-container"></div>
        
        <!-- Hidden notification data for JS -->
        <?php if (isset($successMessage)) : ?>
            <div id="notificationData" data-type="success" data-message="<?= htmlspecialchars($successMessage); ?>" style="display: none;"></div>
        <?php endif; ?>
        <?php if (isset($errorMessage)) : ?>
            <div id="notificationData" data-type="error" data-message="<?= htmlspecialchars($errorMessage); ?>" style="display: none;"></div>
        <?php endif; ?>

        <!-- Bulk Actions Toolbar -->
        <div id="bulkToolbar" class="bg-indigo-50 border border-indigo-200 p-3 rounded shadow mb-4 hidden">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-sm font-semibold text-indigo-700">
                    <span id="selectedCount">0</span> dịch vụ được chọn
                </span>
                <div class="flex gap-2">
                    <button onclick="bulkAction('activate')" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                        Kích hoạt
                    </button>
                    <button onclick="bulkAction('draft')" class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded text-sm">
                        Chuyển Draft
                    </button>
                    <button onclick="bulkAction('retire')" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                        Ngừng hoạt động
                    </button>
                    <button onclick="clearSelection()" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm">
                        Bỏ chọn
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Dashboard -->
        <?php if ($showStats && $statistics): ?>
            <div class="bg-white p-6 rounded shadow mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-indigo-700">Thống kê dịch vụ</h2>
                    <a href="<?= $serviceBaseUrlEsc ?>" class="text-sm text-gray-600 hover:text-gray-800">× Đóng</a>
                </div>
                
                <!-- Overall Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-green-50 p-4 rounded border border-green-200">
                        <div class="text-sm text-green-600 font-semibold">Kích hoạt</div>
                        <div class="text-3xl font-bold text-green-700"><?= $statistics['overall']['by_status']['active'] ?? 0 ?></div>
                    </div>
                    <div class="bg-yellow-50 p-4 rounded border border-yellow-200">
                        <div class="text-sm text-yellow-600 font-semibold">Bản nháp</div>
                        <div class="text-3xl font-bold text-yellow-700"><?= $statistics['overall']['by_status']['draft'] ?? 0 ?></div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded border border-gray-200">
                        <div class="text-sm text-gray-600 font-semibold">Ngừng</div>
                        <div class="text-3xl font-bold text-gray-700"><?= $statistics['overall']['by_status']['retired'] ?? 0 ?></div>
                    </div>
                    <div class="bg-blue-50 p-4 rounded border border-blue-200">
                        <div class="text-sm text-blue-600 font-semibold">Tổng lịch hẹn</div>
                        <div class="text-3xl font-bold text-blue-700"><?= number_format($statistics['overall']['total_bookings']) ?></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Most Popular Services -->
                    <div class="bg-gradient-to-br from-indigo-50 to-white p-4 rounded border">
                        <h3 class="text-lg font-semibold text-indigo-700 mb-3 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                            </svg>
                            Dịch vụ phổ biến nhất
                        </h3>
                        <div class="space-y-2">
                            <?php foreach ($statistics['popular'] as $idx => $service): ?>
                                <div class="flex items-center gap-3 p-2 bg-white rounded hover:shadow-sm transition">
                                    <div class="flex-shrink-0 w-8 h-8 bg-indigo-600 text-white rounded-full flex items-center justify-center font-bold">
                                        <?= $idx + 1 ?>
                                    </div>
                                    <?php if ($service['IMAGE']): ?>
                                        <img src="/<?= htmlspecialchars($service['IMAGE']) ?>" class="w-10 h-10 object-cover rounded">
                                    <?php endif; ?>
                                    <div class="flex-1">
                                        <div class="font-medium text-sm"><?= htmlspecialchars($service['TEN_DV']) ?></div>
                                        <div class="text-xs text-gray-500"><?= $service['booking_count'] ?> lịch hẹn (Đã hoàn thành: <?= $service['completed_count'] ?>)</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($statistics['popular'])): ?>
                                <p class="text-sm text-gray-500 text-center py-4">Chưa có dữ liệu</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Top Revenue Services -->
                    <div class="bg-gradient-to-br from-green-50 to-white p-4 rounded border">
                        <h3 class="text-lg font-semibold text-green-700 mb-3 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"></path>
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"></path>
                            </svg>
                            Dịch vụ doanh thu cao
                        </h3>
                        <div class="space-y-2">
                            <?php foreach ($statistics['revenue'] as $idx => $service): ?>
                                <div class="flex items-center gap-3 p-2 bg-white rounded hover:shadow-sm transition">
                                    <div class="flex-shrink-0 w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center font-bold">
                                        <?= $idx + 1 ?>
                                    </div>
                                    <?php if ($service['IMAGE']): ?>
                                        <img src="/<?= htmlspecialchars($service['IMAGE']) ?>" class="w-10 h-10 object-cover rounded">
                                    <?php endif; ?>
                                    <div class="flex-1">
                                        <div class="font-medium text-sm"><?= htmlspecialchars($service['TEN_DV']) ?></div>
                                        <div class="text-xs text-green-600 font-semibold"><?= number_format($service['total_revenue']) ?> VNĐ</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($statistics['revenue'])): ?>
                                <p class="text-sm text-gray-500 text-center py-4">Chưa có dữ liệu</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Unused Services Warning -->
                <?php if (!empty($statistics['unused'])): ?>
                    <div class="mt-6 bg-orange-50 border border-orange-200 p-4 rounded">
                        <h3 class="text-lg font-semibold text-orange-700 mb-3 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            Dịch vụ ít được sử dụng (30 ngày qua)
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                            <?php foreach ($statistics['unused'] as $service): ?>
                                <div class="bg-white p-3 rounded border text-sm">
                                    <div class="font-medium"><?= htmlspecialchars($service['TEN_DV']) ?></div>
                                    <div class="text-xs text-gray-500">Lần cuối: <?= $service['last_booking'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Thanh tìm kiếm và nút thêm -->
        <div class="flex flex-wrap justify-between items-center gap-2 mb-6">
            <form method="GET" id="serviceFilterForm" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="page" value="services">
                <input type="text" name="search" placeholder="🔍 Tìm theo tên hoặc mô tả" value="<?= htmlspecialchars($search) ?>" class="p-2 border rounded w-64 shadow-sm">
                <select name="status" class="p-2 border rounded">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tất cả trạng thái</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Hoạt động</option>
                    <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Nháp</option>
                    <option value="retired" <?= $statusFilter === 'retired' ? 'selected' : '' ?>>Ngừng</option>
                </select>
                <input type="number" name="min_price" placeholder="Giá từ" value="<?= $minPrice !== null ? $minPrice : '' ?>" class="p-2 border rounded w-24">
                <input type="number" name="max_price" placeholder="Giá đến" value="<?= $maxPrice !== null ? $maxPrice : '' ?>" class="p-2 border rounded w-24">
                <select name="category" class="p-2 border rounded">
                    <option value="">Tất cả danh mục</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['ID_DANH_MUC'] ?>" <?= $categoryFilter !== null && $categoryFilter === (int)$cat['ID_DANH_MUC'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['TEN_DANH_MUC']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="tag" class="p-2 border rounded">
                    <option value="">Tất cả thẻ</option>
                    <?php foreach ($allTags as $tag): ?>
                        <option value="<?= $tag['ID_THE'] ?>" <?= $tagFilter !== null && $tagFilter === (int)$tag['ID_THE'] ? 'selected' : '' ?>><?= htmlspecialchars($tag['TEN_THE']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded shadow">Lọc</button>
            </form>
            <div class="flex gap-2 items-center">
                <select onchange="if(this.value){window.location.href=this.value;}" class="p-2 border rounded text-sm">
                    <option value="" <?= $sortBy === 'ID_DV' && $sortOrder === 'DESC' ? 'selected' : '' ?> disabled>Sắp xếp</option>
                    <option value="<?= htmlspecialchars(service_url($serviceQueryParams, ['sort' => 'TEN_DV', 'order' => 'ASC', 'p' => null]), ENT_QUOTES) ?>" <?= $sortBy === 'TEN_DV' && $sortOrder === 'ASC' ? 'selected' : '' ?>>Tên A-Z</option>
                    <option value="<?= htmlspecialchars(service_url($serviceQueryParams, ['sort' => 'TEN_DV', 'order' => 'DESC', 'p' => null]), ENT_QUOTES) ?>" <?= $sortBy === 'TEN_DV' && $sortOrder === 'DESC' ? 'selected' : '' ?>>Tên Z-A</option>
                    <option value="<?= htmlspecialchars(service_url($serviceQueryParams, ['sort' => 'DON_GIA', 'order' => 'ASC', 'p' => null]), ENT_QUOTES) ?>" <?= $sortBy === 'DON_GIA' && $sortOrder === 'ASC' ? 'selected' : '' ?>>Giá thấp → cao</option>
                    <option value="<?= htmlspecialchars(service_url($serviceQueryParams, ['sort' => 'DON_GIA', 'order' => 'DESC', 'p' => null]), ENT_QUOTES) ?>" <?= $sortBy === 'DON_GIA' && $sortOrder === 'DESC' ? 'selected' : '' ?>>Giá cao → thấp</option>
                    <option value="<?= htmlspecialchars(service_url($serviceQueryParams, ['sort' => 'THOI_GIAN', 'order' => 'ASC', 'p' => null]), ENT_QUOTES) ?>" <?= $sortBy === 'THOI_GIAN' && $sortOrder === 'ASC' ? 'selected' : '' ?>>Thời gian ngắn → dài</option>
                    <option value="<?= htmlspecialchars(service_url($serviceQueryParams, ['sort' => 'THOI_GIAN', 'order' => 'DESC', 'p' => null]), ENT_QUOTES) ?>" <?= $sortBy === 'THOI_GIAN' && $sortOrder === 'DESC' ? 'selected' : '' ?>>Thời gian dài → ngắn</option>
                    <option value="<?= htmlspecialchars(service_url($serviceQueryParams, ['sort' => 'ID_DV', 'order' => 'DESC', 'p' => null]), ENT_QUOTES) ?>" <?= $sortBy === 'ID_DV' && $sortOrder === 'DESC' ? 'selected' : '' ?>>Mới nhất</option>
                    <option value="<?= htmlspecialchars(service_url($serviceQueryParams, ['sort' => 'ID_DV', 'order' => 'ASC', 'p' => null]), ENT_QUOTES) ?>" <?= $sortBy === 'ID_DV' && $sortOrder === 'ASC' ? 'selected' : '' ?>>Cũ nhất</option>
                </select>
                <div class="flex gap-1 border rounded overflow-hidden">
                    <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['view' => 'table', 'p' => null]), ENT_QUOTES) ?>" 
                       class="px-3 py-2 <?= $viewMode === 'table' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' ?>" title="Table View">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </a>
                    <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['view' => 'grid', 'p' => null]), ENT_QUOTES) ?>" 
                       class="px-3 py-2 <?= $viewMode === 'grid' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' ?>" title="Grid View">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                        </svg>
                    </a>
                </div>
                <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['stats' => 1]), ENT_QUOTES) ?>" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded shadow flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Thống kê
                </a>
                <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['manage_categories' => 1]), ENT_QUOTES) ?>" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded shadow flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                    </svg>
                    Danh mục & Thẻ
                </a>
                <div class="relative group">
                    <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Xuất dữ liệu
                    </button>
                    <div class="hidden group-hover:block absolute right-0 mt-1 bg-white border rounded shadow-lg z-10 min-w-[150px]">
                        <a href="/app/admin/export_services_download.php?export=xlsx<?= $exportQueryString ?>" 
                                   target="downloadFrame" class="block px-4 py-2 hover:bg-gray-100 text-sm text-gray-700">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"></path>
                                </svg>
                                Xuất Excel (.xlsx)
                            </span>
                        </a>
                        <a href="/app/admin/export_services_download.php?export=csv<?= $exportQueryString ?>" 
                                   target="downloadFrame" class="block px-4 py-2 hover:bg-gray-100 text-sm text-gray-700 border-t">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                                </svg>
                                Xuất CSV (.csv)
                            </span>
                        </a>
                    </div>
                </div>
                <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['add' => 'true']), ENT_QUOTES) ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow">Thêm dịch vụ</a>
            </div>
        </div>

        <!-- Form Thêm Dịch vụ -->
        <?php if (isset($_GET['add']) && $_GET['add'] === 'true' && !$isEditing) : ?>
            <form method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded shadow mb-6">
                <h2 class="text-xl font-semibold text-indigo-600 mb-4">Thêm dịch vụ</h2>
                
                <!-- Section: Thông tin cơ bản -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-700 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Thông tin cơ bản
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tên dịch vụ <span class="text-red-500">*</span></label>
                            <input type="text" name="TEN_DV" id="tenDvAdd" placeholder="VD: Massage body thư giãn" class="p-2 border rounded w-full" required maxlength="100">
                            <p class="text-xs text-gray-500 mt-1"><span id="tenDvAddCount">0</span>/100 ký tự</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mô tả <span class="text-red-500">*</span></label>
                            <textarea name="MOTA_DV" id="motaDvAdd" placeholder="Mô tả chi tiết về dịch vụ..." class="p-2 border rounded w-full" rows="4" required maxlength="500"></textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                <span id="motaDvAddCount">0</span>/500 ký tự
                                <span class="ml-2 text-blue-600">💡 Nên viết từ 100-300 ký tự để mô tả đầy đủ</span>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Section: Phân loại -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-700 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                        Phân loại & Tags
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Danh mục</label>
                            <select name="ID_DANH_MUC" class="p-2 border rounded w-full">
                                <option value="">-- Chọn danh mục --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['ID_DANH_MUC'] ?>"><?= htmlspecialchars($cat['TEN_DANH_MUC']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tags đặc biệt</label>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($allTags as $tag): ?>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="tags[]" value="<?= $tag['ID_THE'] ?>" class="mr-1">
                                        <span class="px-2 py-1 rounded text-xs" style="background-color: <?= $tag['MAU_SAC'] ?>20; color: <?= $tag['MAU_SAC'] ?>; border: 1px solid <?= $tag['MAU_SAC'] ?>;">
                                            <?= htmlspecialchars($tag['TEN_THE']) ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Section: Giá & Thời lượng -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-700 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Giá & Thời lượng
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Giá dịch vụ (VNĐ) <span class="text-red-500">*</span></label>
                            <input type="number" name="GIA" placeholder="VD: 500000" class="p-2 border rounded w-full" required min="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Thời lượng (phút) <span class="text-red-500">*</span></label>
                            <input type="number" name="THOI_GIAN" placeholder="VD: 60" min="1" class="p-2 border rounded w-full" required>
                        </div>
                    </div>
                </div>
                
                <!-- Section: Hình ảnh -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-700 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Hình ảnh
                    </h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hình ảnh dịch vụ <span class="text-red-500">*</span></label>
                        <input type="file" name="IMAGE" id="imageInput" accept="image/jpeg,image/png,image/webp,image/gif" class="p-2 border rounded w-full" required>
                        <div id="imagePreview" class="mt-3 hidden">
                            <img src="" alt="Preview" class="w-48 h-48 object-cover rounded border shadow-sm">
                            <button type="button" onclick="clearImagePreview()" class="mt-2 text-sm text-red-600 hover:text-red-800">× Xóa</button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Chấp nhận: JPG, PNG, WEBP, GIF. Tối đa: 1920x1920px, 5MB</p>
                    </div>
                </div>
                
                <div class="mt-4 flex justify-end gap-2">
                    <button type="submit" name="add_service" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Lưu</button>
                    <a href="<?= $serviceBaseUrlEsc ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Hủy</a>
                </div>
            </form>
        <?php endif; ?>

        <!-- Form Cập nhật Dịch vụ -->
        <?php if ($isEditing && $editService) : ?>
            <?php $currentTags = $tagRepo->getTagsForService($editService['ID_DV']); ?>
            <?php $currentTagIds = array_column($currentTags, 'ID_THE'); ?>
            <form method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded shadow mb-6">
                <h2 class="text-xl font-semibold text-indigo-600 mb-4">Cập nhật dịch vụ #<?= $editService['ID_DV']; ?></h2>
                <input type="hidden" name="ID_DV" value="<?= $editService['ID_DV']; ?>">
                
                <!-- Section: Thông tin cơ bản -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-700 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Thông tin cơ bản
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tên dịch vụ <span class="text-red-500">*</span></label>
                            <input type="text" name="TEN_DV" id="tenDvEdit" value="<?= htmlspecialchars($editService['TEN_DV']); ?>" class="p-2 border rounded w-full" required maxlength="100">
                            <p class="text-xs text-gray-500 mt-1"><span id="tenDvEditCount"><?= mb_strlen($editService['TEN_DV']); ?></span>/100 ký tự</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mô tả <span class="text-red-500">*</span></label>
                            <textarea name="MOTA_DV" id="motaDvEdit" class="p-2 border rounded w-full" rows="4" required maxlength="500"><?= htmlspecialchars($editService['MOTA_DV']); ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                <span id="motaDvEditCount"><?= mb_strlen($editService['MOTA_DV']); ?></span>/500 ký tự
                                <span class="ml-2 text-blue-600">💡 Nên viết từ 100-300 ký tự để mô tả đầy đủ</span>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Section: Phân loại -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-700 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                        Phân loại & Tags
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Danh mục</label>
                            <select name="ID_DANH_MUC" class="p-2 border rounded w-full">
                                <option value="">-- Chọn danh mục --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['ID_DANH_MUC'] ?>" <?= isset($editService['ID_DANH_MUC']) && $editService['ID_DANH_MUC'] == $cat['ID_DANH_MUC'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['TEN_DANH_MUC']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tags đặc biệt</label>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($allTags as $tag): ?>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="tags[]" value="<?= $tag['ID_THE'] ?>" <?= in_array($tag['ID_THE'], $currentTagIds) ? 'checked' : '' ?> class="mr-1">
                                        <span class="px-2 py-1 rounded text-xs" style="background-color: <?= $tag['MAU_SAC'] ?>20; color: <?= $tag['MAU_SAC'] ?>; border: 1px solid <?= $tag['MAU_SAC'] ?>;">
                                            <?= htmlspecialchars($tag['TEN_THE']) ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Section: Giá & Thời lượng -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-700 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Giá & Thời lượng
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Giá dịch vụ (VNĐ) <span class="text-red-500">*</span></label>
                            <input type="number" name="GIA" value="<?= $editService['DON_GIA'] ?? ''; ?>" class="p-2 border rounded w-full" required min="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Thời lượng (phút) <span class="text-red-500">*</span></label>
                            <input type="number" name="THOI_GIAN" value="<?= $editService['THOI_GIAN']; ?>" min="1" class="p-2 border rounded w-full" required>
                        </div>
                    </div>
                </div>
                
                <!-- Section: Hình ảnh -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-700 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Hình ảnh
                    </h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hình ảnh dịch vụ</label>
                        <input type="file" name="IMAGE" id="imageInputEdit" accept="image/jpeg,image/png,image/webp,image/gif" class="p-2 border rounded w-full">
                        <?php if ($editService['IMAGE']): ?>
                            <div class="mt-3">
                                <p class="text-sm text-gray-600 mb-2">Ảnh hiện tại:</p>
                                <img src="/<?= htmlspecialchars($editService['IMAGE']); ?>" class="w-48 h-48 object-cover rounded border shadow" />
                            </div>
                        <?php else: ?>
                            <div class="mt-3">
                                <img src="/public/images/placeholders/service-placeholder.svg" class="w-48 h-48 object-cover rounded border shadow bg-gray-100" />
                            </div>
                        <?php endif; ?>
                        <div id="imagePreviewEdit" class="mt-3 hidden">
                            <p class="text-sm text-gray-600 mb-2">Ảnh mới:</p>
                            <img src="" alt="Preview" class="w-48 h-48 object-cover rounded border shadow-sm">
                            <button type="button" onclick="clearImagePreviewEdit()" class="mt-2 text-sm text-red-600 hover:text-red-800">× Xóa</button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Chấp nhận: JPG, PNG, WEBP, GIF. Để trống nếu không thay đổi</p>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end gap-2 pt-4 border-t">
                    <button type="submit" name="edit_service" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded shadow">💾 Lưu thay đổi</button>
                    <a href="<?= $serviceBaseUrlEsc ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded shadow">Hủy</a>
                </div>
            </form>
        <?php endif; ?>

        <!-- Danh sách dịch vụ -->
        <?php if ($viewMode === 'table'): ?>
        <div class="overflow-x-auto bg-white rounded shadow" style="max-height: 70vh; overflow-y: auto;">
            <table class="min-w-full table-auto compact-table">
                <thead class="bg-indigo-100 text-indigo-700 sticky top-0 z-10 shadow-sm">
                    <tr>
                        <th class="p-2 w-checkbox text-center">
                            <input type="checkbox" id="selectAll" class="w-4 h-4 cursor-pointer" title="Chọn tất cả">
                        </th>
                        <th class="p-2 w-id text-center">ID</th>
                        <th class="p-2 w-img text-center">Ảnh</th>
                        <th class="p-2 text-left">Tên Dịch Vụ</th>
                        <th class="p-2 w-category text-center">Danh mục</th>
                        <th class="p-2 w-tags text-center" title="Tags">🏷️</th>
                        <th class="p-2 w-duration text-center">Thời lượng</th>
                        <th class="p-2 w-price text-right">Giá</th>
                        <th class="p-2 w-status text-center">Trạng thái</th>
                        <th class="p-2 w-action text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody id="servicesTbody" class="text-center divide-y">
                    <?php foreach ($services as $service): ?>
                        <?php
                        $phut = (int)$service['THOI_GIAN'];
                        $gio = floor($phut / 60);
                        $du_phut = $phut % 60;
                        $thoi_gian_dang_dep = "$gio giờ $du_phut phút";
                        $st = $service['TRANG_THAI'] ?? 'active';
                        $rowBgClass = $st === 'active' ? 'bg-green-50/30 hover:bg-green-50/50' : ($st === 'draft' ? 'bg-yellow-50/30 hover:bg-yellow-50/50' : 'bg-gray-50/30 hover:bg-gray-50/50');
                        $serviceTags = $tagRepo->getTagsForService($service['ID_DV']);
                        ?>
                        <tr class="<?= $rowBgClass ?>" data-id-dv="<?= $service['ID_DV']; ?>" data-thoi-gian="<?= (int)$service['THOI_GIAN']; ?>" data-gia="<?= isset($service['DON_GIA']) ? (int)$service['DON_GIA'] : 0; ?>">
                            <td class="p-2 text-center">
                                <input type="checkbox" class="service-checkbox w-4 h-4 cursor-pointer" value="<?= $service['ID_DV']; ?>">
                            </td>
                            <td class="p-2 font-semibold text-gray-600 text-center"><?= $service['ID_DV']; ?></td>
                            <td class="p-2 text-center">
                                <?php $imgSrc = $service['IMAGE'] ? '/' . htmlspecialchars($service['IMAGE']) : '/public/images/placeholders/service-placeholder.svg'; ?>
                                <img src="<?= $imgSrc ?>" alt="Service Image" class="w-12 h-12 object-cover rounded shadow-sm mx-auto <?= !$service['IMAGE'] ? 'bg-gray-100' : '' ?>" title="<?= htmlspecialchars($service['TEN_DV']) ?>">
                            </td>
                            <td class="p-2 text-left">
                                <div class="font-medium text-gray-800 truncate" style="max-width: 250px;" title="<?= htmlspecialchars($service['TEN_DV']); ?>"><?= htmlspecialchars($service['TEN_DV']); ?></div>
                            </td>
                            <td class="p-2 text-center">
                                <?php if (isset($service['ID_DANH_MUC']) && $service['ID_DANH_MUC']): ?>
                                    <?php
                                    $categoryName = '';
                                    foreach ($categories as $cat) {
                                        if ($cat['ID_DANH_MUC'] == $service['ID_DANH_MUC']) {
                                            $categoryName = $cat['TEN_DANH_MUC'];
                                            break;
                                        }
                                    }
                                    ?>
                                    <span class="px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs font-medium truncate" style="max-width: 110px; display: inline-block;" title="<?= htmlspecialchars($categoryName); ?>">
                                        <?= htmlspecialchars($categoryName); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-2 text-center">
                                <?php if (!empty($serviceTags)): ?>
                                    <div class="relative inline-block tag-counter-wrapper" data-service-id="<?= $service['ID_DV']; ?>">
                                        <span class="tag-counter">
                                            <svg fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                                            </svg>
                                            <?= count($serviceTags); ?>
                                        </span>
                                        <div class="tag-tooltip" data-service-id="<?= $service['ID_DV']; ?>">
                                            <div class="flex flex-wrap gap-1">
                                                <?php foreach ($serviceTags as $tag): ?>
                                                    <span class="px-2 py-1 rounded text-xs font-medium" style="background-color: <?= $tag['MAU_SAC'] ?>20; color: <?= $tag['MAU_SAC'] ?>; border: 1px solid <?= $tag['MAU_SAC'] ?>40;">
                                                        <?= htmlspecialchars($tag['TEN_THE']) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-2 text-center text-xs"><?= $thoi_gian_dang_dep ?></td>
                            <td class="p-2 font-semibold text-green-600 text-right text-xs"><?= isset($service['DON_GIA']) ? number_format($service['DON_GIA'], 0, ',', '.') : '—' ?></td>
                            <td class="p-2 text-center">
                                <div class="relative inline-block">
                                    <button type="button" class="status-menu-btn px-2 py-1 rounded text-xs font-semibold cursor-pointer <?php echo $st === 'active' ? 'bg-green-100 text-green-800' : ($st === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-300 text-gray-700'); ?>" data-service-id="<?= $service['ID_DV'] ?>">
                                        <?= htmlspecialchars($st); ?> ▼
                                    </button>
                                    <div class="status-dropdown hidden absolute left-0 mt-1 bg-white border rounded shadow-lg z-50 min-w-[120px]" data-service-id="<?= $service['ID_DV'] ?>">
                                        <?php if ($st !== 'active'): ?>
                                            <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['status_modal' => $service['ID_DV'], 'target' => 'active']), ENT_QUOTES) ?>" class="block px-3 py-2 text-xs hover:bg-green-50 text-green-700">✓ Kích hoạt</a>
                                        <?php endif; ?>
                                        <?php if ($st !== 'draft'): ?>
                                            <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['status_modal' => $service['ID_DV'], 'target' => 'draft']), ENT_QUOTES) ?>" class="block px-3 py-2 text-xs hover:bg-yellow-50 text-yellow-700">✎ Draft</a>
                                        <?php endif; ?>
                                        <?php if ($st !== 'retired'): ?>
                                            <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['confirm_retire' => $service['ID_DV']]), ENT_QUOTES) ?>" class="block px-3 py-2 text-xs hover:bg-gray-50 text-gray-700">× Ngừng</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="p-2 text-center">
                                <div class="relative inline-block">
                                    <button type="button" class="action-menu-btn action-btn-compact bg-gray-600 hover:bg-gray-700 text-white rounded shadow" data-service-id="<?= $service['ID_DV']; ?>" title="Menu">
                                        <svg fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                        </svg>
                                    </button>
                                    <div class="action-dropdown hidden absolute right-0 mt-1 bg-white border rounded shadow-lg z-50 min-w-[160px]" data-service-id="<?= $service['ID_DV']; ?>">
                                        <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['edit' => $service['ID_DV']]), ENT_QUOTES) ?>" class="px-4 py-2 text-sm hover:bg-yellow-50 text-yellow-700 flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                            Chỉnh sửa
                                        </a>
                                        <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['history' => $service['ID_DV']]), ENT_QUOTES) ?>" class="px-4 py-2 text-sm hover:bg-indigo-50 text-indigo-700 flex items-center gap-2 border-t">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Lịch sử giá
                                        </a>
                                        <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['packages' => $service['ID_DV']]), ENT_QUOTES) ?>" class="px-4 py-2 text-sm hover:bg-purple-50 text-purple-700 flex items-center gap-2 border-t">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                            </svg>
                                            Xem gói
                                        </a>
                                        <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['duplicate' => $service['ID_DV']]), ENT_QUOTES) ?>" class="px-4 py-2 text-sm hover:bg-blue-50 text-blue-700 flex items-center gap-2 border-t" onclick="return confirm('Sao chép dịch vụ này?');">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                            </svg>
                                            Sao chép
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <!-- Grid View -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <?php foreach ($services as $service): ?>
                <?php
                $phut = (int)$service['THOI_GIAN'];
                $gio = floor($phut / 60);
                $du_phut = $phut % 60;
                $thoi_gian_dang_dep = "$gio giờ $du_phut phút";
                $st = $service['TRANG_THAI'] ?? 'active';
                $imgSrc = $service['IMAGE'] ? '/' . htmlspecialchars($service['IMAGE']) : '/public/images/placeholders/service-placeholder.svg';
                $badgeClass = $st === 'active' ? 'bg-green-100 text-green-800' : ($st === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-300 text-gray-700');
                ?>
                <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow overflow-hidden">
                    <div class="relative">
                        <input type="checkbox" class="service-checkbox absolute top-2 left-2 w-5 h-5 cursor-pointer z-10 bg-white rounded" value="<?= $service['ID_DV']; ?>">
                        <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($service['TEN_DV']) ?>" class="w-full h-48 object-cover <?= !$service['IMAGE'] ? 'bg-gray-100' : '' ?>">
                        <span class="absolute top-2 right-2 px-2 py-1 rounded text-xs font-semibold <?= $badgeClass ?>">
                            <?= $st === 'active' ? 'Hoạt động' : ($st === 'retired' ? 'Ngừng' : ($st === 'draft' ? 'Nháp' : htmlspecialchars($st))); ?>
                        </span>
                    </div>
                    <div class="p-4">
                        <h3 class="font-semibold text-lg text-gray-800 mb-2 truncate" title="<?= htmlspecialchars($service['TEN_DV']) ?>">
                            <?= htmlspecialchars($service['TEN_DV']); ?>
                        </h3>
                        <p class="text-sm text-gray-600 mb-3 line-clamp-2" title="<?= htmlspecialchars($service['MOTA_DV']) ?>">
                            <?= htmlspecialchars(mb_strimwidth($service['MOTA_DV'], 0, 80, '...')); ?>
                        </p>
                        <div class="flex items-center justify-between text-sm mb-3">
                            <div class="flex items-center gap-1 text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <?= $thoi_gian_dang_dep ?>
                            </div>
                            <div class="flex items-center gap-1 text-green-600 font-semibold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <?= isset($service['DON_GIA']) ? number_format($service['DON_GIA'], 0, ',', '.') : 'Chưa có' ?>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['edit' => $service['ID_DV']]), ENT_QUOTES) ?>" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-2 rounded text-xs text-center">Sửa</a>
                            <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['packages' => $service['ID_DV']]), ENT_QUOTES) ?>" class="flex-1 bg-purple-500 hover:bg-purple-600 text-white px-3 py-2 rounded text-xs text-center">Gói</a>
                            <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['duplicate' => $service['ID_DV']]), ENT_QUOTES) ?>" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded text-xs text-center" onclick="return confirm('Sao chép?');">Sao chép</a>
                            <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['status_modal' => $service['ID_DV'], 'target' => 'retired']), ENT_QUOTES) ?>" class="flex-1 bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded text-xs text-center">Ngừng</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($services)): ?>
                <div class="col-span-full text-center py-12 text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                    <p>Không có dịch vụ nào.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Phân trang -->
        <?php if ($totalPages > 1): ?>
            <div id="servicesPagination" class="mt-6 flex justify-center gap-2">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['p' => $i > 1 ? $i : null]), ENT_QUOTES) ?>"
                        class="px-3 py-1 rounded border <?= ($i == $page) ? 'bg-indigo-600 text-white' : 'bg-white hover:bg-gray-100' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($historyService): ?>
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" onclick="if(event.target===this) window.location='<?= $serviceBaseUrlJs ?>';">
            <div class="bg-white w-full max-w-lg rounded shadow-lg p-6 relative">
                <h2 class="text-xl font-semibold text-indigo-700 mb-4">Lịch sử giá: <?= htmlspecialchars($historyService['TEN_DV']); ?></h2>
                <table class="w-full text-sm mb-4">
                    <thead class="bg-indigo-100 text-indigo-700">
                        <tr>
                            <th class="p-2 text-left">Thời điểm</th>
                            <th class="p-2 text-right">Giá (VNĐ)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($priceHistory as $row): ?>
                            <tr>
                                <td class="p-2 text-left text-xs"><?= date('d/m/Y H:i', strtotime($row['NGAY_GIO'])); ?></td>
                                <td class="p-2 text-right font-medium"><?= number_format($row['DON_GIA'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($priceHistory)): ?>
                            <tr>
                                <td colspan="2" class="p-2 text-center text-gray-500">Chưa có lịch sử giá.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="flex justify-end gap-2">
                    <a href="<?= $serviceBaseUrlEsc ?>" class="px-4 py-2 rounded bg-gray-600 hover:bg-gray-700 text-white">Đóng</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($confirmRetireService): ?>
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" role="dialog" aria-modal="true" onclick="if(event.target===this) window.location='<?= $serviceBaseUrlJs ?>';">
            <div class="bg-white w-full max-w-lg rounded shadow-lg p-6">
                <h2 class="text-xl font-semibold text-red-600 mb-2">Ngừng dịch vụ: <?= htmlspecialchars($confirmRetireService['TEN_DV']); ?></h2>
                <p class="text-sm text-gray-600 mb-4">Kiểm tra điều kiện trước khi ngừng. Các lý do dưới đây có thể ngăn việc ngừng:</p>
                <ul class="space-y-2 mb-4">
                    <?php if (empty($confirmRetireReasons)): ?>
                        <li class="flex items-start gap-2"><span class="text-green-600">✔</span><span class="text-sm">Không có ràng buộc. Có thể ngừng an toàn.</span></li>
                        <?php else: foreach ($confirmRetireReasons as $r): ?>
                            <li class="flex items-start gap-2"><span class="text-red-600">•</span><span class="text-sm"><?= htmlspecialchars($r['message']); ?></span></li>
                    <?php endforeach;
                    endif; ?>
                </ul>
                <div class="flex justify-end gap-2">
                    <a href="<?= $serviceBaseUrlEsc ?>" class="px-4 py-2 rounded bg-gray-600 hover:bg-gray-700 text-white">Hủy</a>
                    <?php if ($policy->canRetire((int)$confirmRetireService['ID_DV'])): ?>
                        <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['delete' => $confirmRetireService['ID_DV']]), ENT_QUOTES) ?>" class="px-4 py-2 rounded bg-red-600 hover:bg-red-700 text-white" onclick="return confirm('Xác nhận ngừng dịch vụ?');">Xác nhận ngừng</a>
                    <?php else: ?>
                        <button disabled class="px-4 py-2 rounded bg-red-300 text-white cursor-not-allowed">Không thể ngừng</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($packagesService): ?>
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" onclick="if(event.target===this) window.location='<?= $serviceBaseUrlJs ?>';">
            <div class="bg-white w-full max-w-3xl rounded shadow-lg p-6 relative max-h-[90vh] overflow-y-auto">
                <h2 class="text-xl font-semibold text-purple-700 mb-4">Gói chứa dịch vụ: <?= htmlspecialchars($packagesService['TEN_DV']); ?></h2>
                
                <?php if (empty($relatedPackages)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                        <p>Dịch vụ này chưa thuộc gói nào.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($relatedPackages as $pkg): ?>
                            <?php
                            $statusBadge = $pkg['TRANG_THAI'] === 'ban' ? 'bg-green-100 text-green-800' : 'bg-gray-300 text-gray-700';
                            $isActive = $pkg['TRANG_THAI'] === 'ban';
                            $isValid = true;
                            if ($pkg['HIEU_LUC_TU'] && strtotime($pkg['HIEU_LUC_TU']) > time()) $isValid = false;
                            if ($pkg['HIEU_LUC_DEN'] && strtotime($pkg['HIEU_LUC_DEN']) < time()) $isValid = false;
                            ?>
                            <div class="border rounded-lg p-4 <?= $isActive && $isValid ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-lg text-gray-800"><?= htmlspecialchars($pkg['TEN_GOI']); ?></h3>
                                        <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($pkg['MO_TA'] ?? ''); ?></p>
                                    </div>
                                    <span class="px-2 py-1 rounded text-xs font-semibold <?= $statusBadge ?>">
                                        <?= htmlspecialchars($pkg['TRANG_THAI']); ?>
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3 text-sm">
                                    <div>
                                        <span class="text-gray-500">Giá gói:</span>
                                        <p class="font-semibold text-green-600"><?= number_format($pkg['GIA_GOI'], 0, ',', '.'); ?> VNĐ</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Số lượng:</span>
                                        <p class="font-semibold"><?= $pkg['SO_LUONG']; ?> lần</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Hiệu lực từ:</span>
                                        <p class="font-medium"><?= $pkg['HIEU_LUC_TU'] ? date('d/m/Y', strtotime($pkg['HIEU_LUC_TU'])) : 'Không giới hạn'; ?></p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Hiệu lực đến:</span>
                                        <p class="font-medium"><?= $pkg['HIEU_LUC_DEN'] ? date('d/m/Y', strtotime($pkg['HIEU_LUC_DEN'])) : 'Không giới hạn'; ?></p>
                                    </div>
                                </div>
                                <?php if ($pkg['GHI_CHU']): ?>
                                    <div class="mt-2 p-2 bg-blue-50 rounded text-sm text-blue-700">
                                        <strong>Ghi chú:</strong> <?= htmlspecialchars($pkg['GHI_CHU']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!$isValid): ?>
                                    <div class="mt-2 p-2 bg-orange-50 rounded text-sm text-orange-700">
                                        ⚠️ Gói này hiện không còn hiệu lực
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded">
                        <p class="text-sm text-blue-800">
                            <strong>Tổng cộng:</strong> <?= count($relatedPackages); ?> gói
                            <span class="ml-4">
                                <strong class="text-green-700"><?= count(array_filter($relatedPackages, fn($p) => $p['TRANG_THAI'] === 'ban')); ?></strong> đang bán
                            </span>
                        </p>
                    </div>
                <?php endif; ?>
                
                <div class="flex justify-end gap-2 mt-4">
                    <a href="<?= $serviceBaseUrlEsc ?>" class="px-4 py-2 rounded bg-gray-600 hover:bg-gray-700 text-white">Đóng</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($changeStatusService): ?>
        <?php $targetStatus = $_GET['target'] ?? 'active'; ?>
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" onclick="if(event.target===this) window.location='<?= $serviceBaseUrlJs ?>';">
            <div class="bg-white w-full max-w-md rounded shadow-lg p-6">
                <h2 class="text-xl font-semibold text-indigo-600 mb-4">Chuyển trạng thái</h2>
                <p class="text-sm text-gray-600 mb-2">Dịch vụ: <strong><?= htmlspecialchars($changeStatusService['TEN_DV']); ?></strong></p>
                <p class="text-sm text-gray-600 mb-4">Từ <span class="font-semibold"><?= $changeStatusService['TRANG_THAI'] ?></span> → <span class="font-semibold text-indigo-600"><?= $targetStatus ?></span></p>
                
                <form method="GET" action="">
                    <input type="hidden" name="page" value="services">
                    <input type="hidden" name="change_status" value="<?= $changeStatusService['ID_DV'] ?>">
                    <input type="hidden" name="new_status" value="<?= $targetStatus ?>">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Lý do (tuỳ chọn)</label>
                        <textarea name="reason" rows="3" class="w-full p-2 border rounded" placeholder="Ghi chú lý do thay đổi..."></textarea>
                    </div>
                    
                    <div class="flex justify-end gap-2">
                        <a href="<?= $serviceBaseUrlEsc ?>" class="px-4 py-2 rounded bg-gray-500 hover:bg-gray-600 text-white">Hủy</a>
                        <button type="submit" class="px-4 py-2 rounded bg-indigo-600 hover:bg-indigo-700 text-white">Xác nhận</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Modal: Manage Categories & Tags -->
    <?php if (isset($_GET['manage_categories'])): ?>
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 overflow-y-auto p-4 md:p-8 backdrop-blur-sm" style="align-items: flex-start;" onclick="if(event.target===this) window.location='<?= $serviceBaseUrlJs ?>';">
            <div class="bg-white rounded-2xl shadow-2xl p-6 max-h-[90vh] flex flex-col overflow-visible w-full md:w-auto mt-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-indigo-700">Quản lý Danh mục & Thẻ</h2>
                    <a href="<?= $serviceBaseUrlEsc ?>" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </a>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 overflow-visible">
                    <!-- Categories Section -->
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200 overflow-visible">
                        <h3 class="text-xl font-semibold text-blue-700 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                            Danh mục dịch vụ
                        </h3>
                        
                        <!-- Add Category Form -->
                        <form method="POST" class="mb-4 bg-white p-3 rounded border">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Thêm danh mục mới</label>
                            <div class="flex gap-2">
                                <input type="text" name="TEN_DANH_MUC" placeholder="Nhập tên danh mục..." class="flex-1 p-2 border rounded text-sm" required>
                                <button type="submit" name="add_category" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm whitespace-nowrap">+ Thêm danh mục</button>
                            </div>
                        </form>
                        
                        <!-- Categories List -->
                        <div class="space-y-2 max-h-96 overflow-y-auto">
                            <?php if (empty($categories)): ?>
                                <p class="text-sm text-gray-500 text-center py-4">Chưa có danh mục nào</p>
                            <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                    <div class="bg-white p-3 rounded border hover:shadow-sm transition flex justify-between items-center">
                                        <div class="flex-1">
                                            <span class="font-medium text-gray-800"><?= htmlspecialchars($cat['TEN_DANH_MUC']) ?></span>
                                            <span class="text-xs text-gray-500 ml-2">(ID: <?= $cat['ID_DANH_MUC'] ?>)</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <button onclick="editCategory(<?= $cat['ID_DANH_MUC'] ?>, '<?= htmlspecialchars($cat['TEN_DANH_MUC'], ENT_QUOTES) ?>')" 
                                                    class="text-yellow-600 hover:text-yellow-800 text-sm">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </button>
                                                          <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['manage_categories' => 1, 'delete_category' => $cat['ID_DANH_MUC']]), ENT_QUOTES) ?>" 
                                               onclick="return confirm('Xóa danh mục này?')" 
                                               class="text-red-600 hover:text-red-800 text-sm">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Tags Section -->
                    <div class="bg-purple-50 p-4 rounded-lg border border-purple-200 overflow-visible">
                        <h3 class="text-xl font-semibold text-purple-700 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                            Thẻ dịch vụ
                        </h3>
                        
                        <!-- Add Tag Form -->
                        <form method="POST" class="mb-4 bg-white p-3 rounded border">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Thêm thẻ mới</label>
                            <div class="flex gap-2 items-end">
                                <div class="flex-1">
                                    <input type="text" name="TEN_THE" placeholder="Nhập tên thẻ..." class="w-full p-2 border rounded text-sm mb-2" required>
                                    <div class="flex items-center gap-2">
                                        <label class="text-xs text-gray-600">Màu:</label>
                                        <input type="color" name="MAU_SAC" value="#3B82F6" class="w-12 h-8 border rounded cursor-pointer">
                                        <span class="text-xs text-gray-500">Chọn màu cho thẻ</span>
                                    </div>
                                </div>
                                <button type="submit" name="add_tag" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded text-sm whitespace-nowrap">+ Thêm thẻ</button>
                            </div>
                        </form>
                        
                        <!-- Tags List -->
                        <div class="space-y-2 max-h-96 overflow-y-auto">
                            <?php if (empty($allTags)): ?>
                                <p class="text-sm text-gray-500 text-center py-4">Chưa có thẻ nào</p>
                            <?php else: ?>
                                <?php foreach ($allTags as $tag): ?>
                                    <div class="bg-white p-3 rounded border hover:shadow-sm transition flex justify-between items-center">
                                        <div class="flex items-center gap-3 flex-1">
                                            <span class="px-3 py-1 rounded-full text-sm font-medium" style="background-color: <?= $tag['MAU_SAC'] ?>20; color: <?= $tag['MAU_SAC'] ?>; border: 1px solid <?= $tag['MAU_SAC'] ?>;">
                                                <?= htmlspecialchars($tag['TEN_THE']) ?>
                                            </span>
                                            <span class="text-xs text-gray-500">(ID: <?= $tag['ID_THE'] ?>)</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <button onclick="editTag(<?= $tag['ID_THE'] ?>, '<?= htmlspecialchars($tag['TEN_THE'], ENT_QUOTES) ?>', '<?= $tag['MAU_SAC'] ?>')" 
                                                    class="text-yellow-600 hover:text-yellow-800 text-sm">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </button>
                                                          <a href="<?= htmlspecialchars(service_url($serviceQueryParams, ['manage_categories' => 1, 'delete_tag' => $tag['ID_THE']]), ENT_QUOTES) ?>" 
                                               onclick="return confirm('Xóa thẻ này?')" 
                                               class="text-red-600 hover:text-red-800 text-sm">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <a href="<?= $serviceBaseUrlEsc ?>" class="px-6 py-2 rounded bg-gray-600 hover:bg-gray-700 text-white">Đóng cửa sổ</a>
                </div>
            </div>
        </div>
        
        <!-- Hidden Edit Forms -->
        <div id="editCategoryModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-[60]">
            <div class="bg-white p-6 rounded shadow-lg w-96">
                <h3 class="text-lg font-semibold mb-4">Sửa danh mục</h3>
                <form method="POST">
                    <input type="hidden" name="ID_DANH_MUC" id="editCategoryId">
                    <input type="text" name="TEN_DANH_MUC" id="editCategoryName" class="w-full p-2 border rounded mb-4" required>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeEditCategory()" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded">Hủy</button>
                        <button type="submit" name="edit_category" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">Lưu</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div id="editTagModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-[60]">
            <div class="bg-white p-6 rounded shadow-lg w-96">
                <h3 class="text-lg font-semibold mb-4">Sửa thẻ</h3>
                <form method="POST">
                    <input type="hidden" name="ID_THE" id="editTagId">
                    <input type="text" name="TEN_THE" id="editTagName" class="w-full p-2 border rounded mb-3" required>
                    <div class="flex items-center gap-2 mb-4">
                        <label class="text-sm text-gray-600">Màu:</label>
                        <input type="color" name="MAU_SAC" id="editTagColor" class="w-12 h-8 border rounded cursor-pointer">
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeEditTag()" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded">Hủy</button>
                        <button type="submit" name="edit_tag" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded">Lưu</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    
    <script>
        // Notification System
        (function() {
            const notificationContainer = document.getElementById('notificationContainer');
            const notificationData = document.getElementById('notificationData');
            
            if (notificationData && notificationContainer) {
                const type = notificationData.dataset.type;
                const message = notificationData.dataset.message;
                showNotification(message, type);
            }
            
            function showNotification(message, type = 'success', duration = 5000) {
                const notification = document.createElement('div');
                notification.className = `notification notification-${type}`;
                
                const iconSvg = type === 'success' 
                    ? '<svg class="notification-icon" fill="none" stroke="#10b981" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
                    : '<svg class="notification-icon" fill="none" stroke="#ef4444" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                
                notification.innerHTML = `
                    ${iconSvg}
                    <div class="notification-content">${message}</div>
                    <button class="notification-close" onclick="this.parentElement.remove()">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                    <div class="notification-progress"></div>
                `;
                
                notificationContainer.appendChild(notification);
                
                // Trigger animation
                setTimeout(() => notification.classList.add('show'), 10);
                
                // Auto remove
                setTimeout(() => {
                    notification.classList.add('hide');
                    setTimeout(() => notification.remove(), 300);
                }, duration);
            }
            
            // Export function globally
            window.showNotification = showNotification;
        })();
        
        // Tag Counter Tooltips
        (function() {
            document.addEventListener('click', function(e) {
                const counterWrapper = e.target.closest('.tag-counter-wrapper');
                if (counterWrapper) {
                    e.stopPropagation();
                    const serviceId = counterWrapper.dataset.serviceId;
                    const tooltip = document.querySelector(`.tag-tooltip[data-service-id="${serviceId}"]`);
                    
                    // Close all other tooltips
                    document.querySelectorAll('.tag-tooltip').forEach(t => {
                        if (t !== tooltip) t.classList.remove('show');
                    });
                    
                    // Toggle current tooltip
                    if (tooltip) {
                        tooltip.classList.toggle('show');
                        
                        // Position tooltip
                        const rect = counterWrapper.getBoundingClientRect();
                        tooltip.style.top = (rect.bottom + window.scrollY + 5) + 'px';
                        tooltip.style.left = (rect.left + window.scrollX - 50) + 'px';
                    }
                } else {
                    // Close all tooltips when clicking outside
                    document.querySelectorAll('.tag-tooltip').forEach(t => t.classList.remove('show'));
                }
            });
        })();

        // Bulk Actions Management
        (function() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const bulkToolbar = document.getElementById('bulkToolbar');
            const selectedCountEl = document.getElementById('selectedCount');
            const tbody = document.getElementById('servicesTbody');
            
            function updateBulkToolbar() {
                const checkboxes = document.querySelectorAll('.service-checkbox:checked');
                const count = checkboxes.length;
                selectedCountEl.textContent = count;
                
                if (count > 0) {
                    bulkToolbar.classList.remove('hidden');
                } else {
                    bulkToolbar.classList.add('hidden');
                }
                
                // Update select all checkbox state
                const allCheckboxes = document.querySelectorAll('.service-checkbox');
                if (allCheckboxes.length > 0) {
                    selectAllCheckbox.checked = count === allCheckboxes.length;
                    selectAllCheckbox.indeterminate = count > 0 && count < allCheckboxes.length;
                }
            }
            
            // Select all toggle
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.service-checkbox');
                    checkboxes.forEach(cb => cb.checked = this.checked);
                    updateBulkToolbar();
                });
            }
            
            // Individual checkbox change
            tbody.addEventListener('change', function(e) {
                if (e.target.classList.contains('service-checkbox')) {
                    updateBulkToolbar();
                }
            });
            
            // Bulk action execution
            window.bulkAction = function(action) {
                const checkboxes = document.querySelectorAll('.service-checkbox:checked');
                const ids = Array.from(checkboxes).map(cb => cb.value);
                
                if (ids.length === 0) {
                    alert('Chưa chọn dịch vụ nào!');
                    return;
                }
                
                let confirmMsg = '';
                switch(action) {
                    case 'activate': confirmMsg = `Kích hoạt ${ids.length} dịch vụ?`; break;
                    case 'draft': confirmMsg = `Chuyển ${ids.length} dịch vụ sang draft?`; break;
                    case 'retire': confirmMsg = `Ngừng hoạt động ${ids.length} dịch vụ?`; break;
                    default: return;
                }
                
                if (!confirm(confirmMsg)) return;
                
                const formData = new FormData();
                formData.set('bulk_action', action);
                ids.forEach(id => formData.append('service_ids[]', id));
                
                // Use dedicated AJAX endpoint
                fetch('/StygianBlue/app/admin/ajax_services.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        alert(data.message + (data.failed > 0 ? ` (Thất bại: ${data.failed})` : ''));
                        if (data.errors && data.errors.length > 0) {
                            console.log('Errors:', data.errors);
                        }
                        window.location.reload();
                    } else {
                        alert('Lỗi: ' + (data.error || 'Không xác định'));
                    }
                })
                .catch(err => {
                    alert('Lỗi kết nối: ' + err.message);
                });
            };
            
            window.clearSelection = function() {
                document.querySelectorAll('.service-checkbox').forEach(cb => cb.checked = false);
                if (selectAllCheckbox) selectAllCheckbox.checked = false;
                updateBulkToolbar();
            };
            
            // Initialize on load
            updateBulkToolbar();
        })();

        // Character counter
        (function() {
            function setupCharCounter(inputId, counterId) {
                const input = document.getElementById(inputId);
                const counter = document.getElementById(counterId);
                if (input && counter) {
                    counter.textContent = input.value.length;
                    input.addEventListener('input', () => {
                        counter.textContent = input.value.length;
                    });
                }
            }
            
            setupCharCounter('tenDvAdd', 'tenDvAddCount');
            setupCharCounter('motaDvAdd', 'motaDvAddCount');
            setupCharCounter('tenDvEdit', 'tenDvEditCount');
            setupCharCounter('motaDvEdit', 'motaDvEditCount');
        })();

        // Action dropdown menu handler
        (function() {
            const actionButtons = document.querySelectorAll('.action-menu-btn');
            const actionDropdowns = document.querySelectorAll('.action-dropdown');
            const statusButtons = document.querySelectorAll('.status-menu-btn');
            const statusDropdowns = document.querySelectorAll('.status-dropdown');
            
            // Close all dropdowns
            function closeAllDropdowns() {
                actionDropdowns.forEach(dropdown => dropdown.classList.add('hidden'));
                statusDropdowns.forEach(dropdown => dropdown.classList.add('hidden'));
            }
            
            // Toggle action dropdown on button click
            actionButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const serviceId = this.dataset.serviceId;
                    const dropdown = document.querySelector(`.action-dropdown[data-service-id="${serviceId}"]`);
                    
                    // Close other dropdowns
                    actionDropdowns.forEach(dd => {
                        if (dd !== dropdown) dd.classList.add('hidden');
                    });
                    statusDropdowns.forEach(dd => dd.classList.add('hidden'));
                    
                    // Toggle current dropdown
                    dropdown.classList.toggle('hidden');
                });
            });
            
            // Toggle status dropdown on button click
            statusButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const serviceId = this.dataset.serviceId;
                    const dropdown = document.querySelector(`.status-dropdown[data-service-id="${serviceId}"]`);
                    
                    // Close other dropdowns
                    statusDropdowns.forEach(dd => {
                        if (dd !== dropdown) dd.classList.add('hidden');
                    });
                    actionDropdowns.forEach(dd => dd.classList.add('hidden'));
                    
                    // Toggle current dropdown
                    dropdown.classList.toggle('hidden');
                });
            });
            
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.action-menu-btn') && 
                    !e.target.closest('.action-dropdown') &&
                    !e.target.closest('.status-menu-btn') && 
                    !e.target.closest('.status-dropdown')) {
                    closeAllDropdowns();
                }
            });
            
            // Prevent dropdown from closing when clicking inside
            [...actionDropdowns, ...statusDropdowns].forEach(dropdown => {
                dropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });
        })();

        // Image Preview Handlers
        (function() {
            const imageInput = document.getElementById('imageInput');
            const imageInputEdit = document.getElementById('imageInputEdit');
            
            if (imageInput) {
                imageInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        // Validate file size (max 5MB)
                        if (file.size > 5 * 1024 * 1024) {
                            alert('File quá lớn! Tối đa 5MB');
                            this.value = '';
                            return;
                        }
                        
                        // Validate file type
                        const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                        if (!validTypes.includes(file.type)) {
                            alert('Loại file không hợp lệ! Chỉ chấp nhận JPG, PNG, WEBP, GIF');
                            this.value = '';
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const preview = document.getElementById('imagePreview');
                            const img = preview.querySelector('img');
                            img.src = e.target.result;
                            preview.classList.remove('hidden');
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            if (imageInputEdit) {
                imageInputEdit.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        if (file.size > 5 * 1024 * 1024) {
                            alert('File quá lớn! Tối đa 5MB');
                            this.value = '';
                            return;
                        }
                        
                        const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                        if (!validTypes.includes(file.type)) {
                            alert('Loại file không hợp lệ!');
                            this.value = '';
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const preview = document.getElementById('imagePreviewEdit');
                            const img = preview.querySelector('img');
                            img.src = e.target.result;
                            preview.classList.remove('hidden');
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            window.clearImagePreview = function() {
                const input = document.getElementById('imageInput');
                const preview = document.getElementById('imagePreview');
                input.value = '';
                preview.classList.add('hidden');
            };
            
            window.clearImagePreviewEdit = function() {
                const input = document.getElementById('imageInputEdit');
                const preview = document.getElementById('imagePreviewEdit');
                input.value = '';
                preview.classList.add('hidden');
            };
        })();

        // Inline editing feature removed
        
        // Category & Tag Management
        function editCategory(id, name) {
            document.getElementById('editCategoryId').value = id;
            document.getElementById('editCategoryName').value = name;
            document.getElementById('editCategoryModal').classList.remove('hidden');
        }
        
        function closeEditCategory() {
            document.getElementById('editCategoryModal').classList.add('hidden');
        }
        
        function editTag(id, name, color) {
            document.getElementById('editTagId').value = id;
            document.getElementById('editTagName').value = name;
            document.getElementById('editTagColor').value = color;
            document.getElementById('editTagModal').classList.remove('hidden');
        }
        
        function closeEditTag() {
            document.getElementById('editTagModal').classList.add('hidden');
        }
    </script>
</body>