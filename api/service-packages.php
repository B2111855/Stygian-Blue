<?php
/**
 * Service Packages REST API
 * Endpoints: GET/POST/PUT/DELETE /api/service-packages
 * 
 * Handles CRUD operations for service packages (gói dịch vụ)
 * With support for services, costumes, requirements, and promotions
 */

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../app/repositories/PackageRepository.php';
require_once __DIR__ . '/../app/repositories/ServiceRepository.php';
require_once __DIR__ . '/../app/repositories/PackageCostumeRepository.php';
require_once __DIR__ . '/../app/repositories/PromotionRepository.php';
require_once __DIR__ . '/../app/helpers/system_log.php';

if (session_status() === PHP_SESSION_NONE) { 
  session_start(); 
}

use App\Repositories\PackageRepository;
use App\Repositories\ServiceRepository;

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

// Get request method and parse path
$method = $_SERVER['REQUEST_METHOD'];
// Support method override for multipart/form-data where PUT doesn't populate $_POST
if ($method === 'POST' && isset($_POST['_method'])) {
  $override = strtoupper(trim($_POST['_method']));
  if (in_array($override, ['PUT','DELETE'])) {
    $method = $override;
  }
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = array_filter(explode('/', $path));

// Extract ID from URL if present (e.g., /api/service-packages/123)
$id = null;
$action = null;
if (count($pathParts) >= 4) {
  $id = (int)end($pathParts);
  // Check if this is a sub-resource action
  if (count($pathParts) >= 5) {
    $action = $pathParts[count($pathParts) - 2];
  }
}

// Initialize repositories
$pkgRepo = new PackageRepository($conn);
$svcRepo = new ServiceRepository($conn);
$pcRepo = new \App\Repositories\PackageCostumeRepository($conn);
$promoRepo = new PromotionRepository($conn);

try {
  // Determine user role and branch
  $userRole = (string)($_SESSION['ID_QUYEN'] ?? '');
  $staffType = (string)($_SESSION['STAFF_TYPE'] ?? '');
  $branchId = isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : 0;
  $isBranchManager = ($userRole === '2' && $staffType === 'quan_ly' && $branchId > 0);

  switch ($method) {
    
    // ===== GET: List all packages or get single package =====
    case 'GET':
      if ($id) {
        // GET /api/service-packages/{id}
        handleGetPackage($id, $pkgRepo, $svcRepo, $pcRepo, $isBranchManager, $branchId);
      } else {
        // GET /api/service-packages
        handleListPackages($pkgRepo, $isBranchManager, $branchId);
      }
      break;

    // ===== POST: Create package or handle sub-actions =====
    case 'POST':
      $data = parseFormData();
      if ($action === 'requirements') {
        // POST /api/service-packages/{id}/requirements
        handleAddRequirement($id, $data, $conn);
      } elseif ($action === 'promotions') {
        // POST /api/service-packages/{id}/promotions
        handleAddPromotion($id, $data, $promoRepo);
      } else {
        // POST /api/service-packages
        handleCreatePackage($data, $pkgRepo, $isBranchManager, $branchId);
      }
      break;

    // ===== PUT: Update package or sub-resource =====
    case 'PUT':
      $data = parseFormData();
      if ($action === 'requirements') {
        // PUT /api/service-packages/{id}/requirements/{reqId}
        $reqId = $id;
        handleUpdateRequirement($reqId, $data, $conn);
      } elseif ($action === 'promotions') {
        // PUT /api/service-packages/{id}/promotions/{promoId}
        $promoId = $id;
        handleUpdatePromotion($promoId, $data, $promoRepo);
      } else {
        // PUT /api/service-packages/{id}
        handleUpdatePackage($id, $data, $pkgRepo, $isBranchManager, $branchId);
      }
      break;

    // ===== DELETE: Delete package or sub-resource =====
    case 'DELETE':
      if ($action === 'requirements') {
        // DELETE /api/service-packages/{id}/requirements/{reqId}
        $reqId = $id;
        handleDeleteRequirement($reqId, $conn);
      } elseif ($action === 'promotions') {
        // DELETE /api/service-packages/{id}/promotions/{promoId}
        $promoId = $id;
        handleDeletePromotion($promoId, $promoRepo);
      } else {
        // DELETE /api/service-packages/{id}
        handleDeletePackage($id, $pkgRepo, $isBranchManager, $branchId);
      }
      break;

    default:
      http_response_code(405);
      echo json_encode(['error' => 'Method not allowed']);
      break;
  }

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'error' => 'Server error',
    'message' => $e->getMessage()
  ]);
  error_log('API Error: ' . $e->getMessage());
}

// ===== HANDLER FUNCTIONS =====

/**
 * GET /api/service-packages - List all packages with pagination
 */
function handleListPackages($pkgRepo, $isBranchManager, $branchId) {
  $search = isset($_GET['search']) ? trim($_GET['search']) : '';
  $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
  $limit = isset($_GET['limit']) ? max(1, min((int)$_GET['limit'], 100)) : 10;
  $offset = ($page - 1) * $limit;

  $totalRows = $pkgRepo->countPackages($search);
  $packages = $pkgRepo->searchPackages($search, $limit, $offset, $isBranchManager ? $branchId : null);

  http_response_code(200);
  echo json_encode([
    'success' => true,
    'data' => $packages,
    'pagination' => [
      'page' => $page,
      'limit' => $limit,
      'total' => $totalRows,
      'pages' => ceil($totalRows / $limit)
    ]
  ]);
}

/**
 * GET /api/service-packages/{id} - Get single package with details
 */
function handleGetPackage($id, $pkgRepo, $svcRepo, $pcRepo, $isBranchManager, $branchId) {
  $package = $pkgRepo->find($id);
  
  if (!$package) {
    http_response_code(404);
    echo json_encode(['error' => 'Package not found']);
    return;
  }

  // Check access permissions
  if ($isBranchManager && ($package['SCOPE_TYPE'] ?? '') === 'global') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    return;
  }

  // Load related data
  $services = $pkgRepo->listServices($id);
  $costumes = $pcRepo->listCostumes($id);

  http_response_code(200);
  echo json_encode([
    'success' => true,
    'data' => [
      'package' => $package,
      'services' => $services,
      'costumes' => $costumes
    ]
  ]);
}

/**
 * POST /api/service-packages - Create new package
 */
function handleCreatePackage($data, $pkgRepo, $isBranchManager, $branchId) {
  // Validate required fields
  $errors = [];
  if (empty($data['TEN_GOI'])) $errors[] = 'Tên gói không được để trống';
  if (strlen($data['TEN_GOI'] ?? '') < 5) $errors[] = 'Tên gói phải từ 5 ký tự trở lên';
  if (empty($data['MO_TA'])) $errors[] = 'Mô tả không được để trống';
  if (strlen($data['MO_TA'] ?? '') < 20) $errors[] = 'Mô tả phải từ 20 ký tự trở lên';

  if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'errors' => $errors
    ]);
    return;
  }

  // Prepare payload
  $payload = [
    'TEN_GOI' => trim($data['TEN_GOI']),
    'MO_TA' => trim($data['MO_TA']),
    'HIEU_LUC_TU' => $data['HIEU_LUC_TU'] ?? null,
    'HIEU_LUC_DEN' => $data['HIEU_LUC_DEN'] ?? null,
    'SCOPE_TYPE' => $isBranchManager ? 'local' : ($data['SCOPE_TYPE'] ?? 'global'),
    'ID_CN_OWNER' => $isBranchManager ? $branchId : (isset($data['ID_CN_OWNER']) && $data['ID_CN_OWNER'] !== '' ? (int)$data['ID_CN_OWNER'] : null)
  ];

  // Handle image upload if present
  $image = null;
  if (isset($_FILES['HINH_ANH'])) {
    $image = uploadPackageImage($_FILES['HINH_ANH']);
  }

  // Create package
  $newId = $pkgRepo->create($payload, $image);

  if ($newId) {
    http_response_code(201);
    echo json_encode([
      'success' => true,
      'message' => 'Gói dịch vụ đã được tạo',
      'id' => $newId,
      'data' => $pkgRepo->find($newId)
    ]);
  } else {
    http_response_code(500);
    echo json_encode([
      'success' => false,
      'error' => 'Không thể tạo gói dịch vụ'
    ]);
  }
}

/**
 * PUT /api/service-packages/{id} - Update package
 */
function handleUpdatePackage($id, $data, $pkgRepo, $isBranchManager, $branchId) {
  $package = $pkgRepo->find($id);
  
  if (!$package) {
    http_response_code(404);
    echo json_encode(['error' => 'Package not found']);
    return;
  }

  // Check permissions
  if ($isBranchManager && ($package['SCOPE_TYPE'] ?? '') === 'global') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    return;
  }

  // Prepare update payload
  $payload = ['ID_GOI' => $id];
  if (isset($data['TEN_GOI'])) $payload['TEN_GOI'] = trim($data['TEN_GOI']);
  if (isset($data['MO_TA'])) $payload['MO_TA'] = trim($data['MO_TA']);
  if (isset($data['HIEU_LUC_TU'])) $payload['HIEU_LUC_TU'] = $data['HIEU_LUC_TU'] ?: null;
  if (isset($data['HIEU_LUC_DEN'])) $payload['HIEU_LUC_DEN'] = $data['HIEU_LUC_DEN'] ?: null;
  if (isset($data['TRANG_THAI'])) $payload['TRANG_THAI'] = $data['TRANG_THAI'];

  // Handle image upload
  $image = null;
  if (isset($_FILES['HINH_ANH'])) {
    $image = uploadPackageImage($_FILES['HINH_ANH']);
  }

  // Update
  if ($pkgRepo->update($payload, $image)) {
    http_response_code(200);
    echo json_encode([
      'success' => true,
      'message' => 'Gói dịch vụ đã được cập nhật',
      'data' => $pkgRepo->find($id)
    ]);
  } else {
    http_response_code(500);
    echo json_encode([
      'success' => false,
      'error' => 'Không thể cập nhật gói dịch vụ'
    ]);
  }
}

/**
 * DELETE /api/service-packages/{id} - Delete package
 */
function handleDeletePackage($id, $pkgRepo, $isBranchManager, $branchId) {
  $package = $pkgRepo->find($id);
  
  if (!$package) {
    http_response_code(404);
    echo json_encode(['error' => 'Package not found']);
    return;
  }

  // Check permissions
  if ($isBranchManager && ($package['SCOPE_TYPE'] ?? '') === 'global') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    return;
  }

  if ($pkgRepo->delete($id)) {
    http_response_code(200);
    echo json_encode([
      'success' => true,
      'message' => 'Gói dịch vụ đã được xóa'
    ]);
  } else {
    http_response_code(500);
    echo json_encode([
      'success' => false,
      'error' => 'Không thể xóa gói dịch vụ'
    ]);
  }
}

/**
 * POST /api/service-packages/{id}/requirements - Add requirement
 */
function handleAddRequirement($packageId, $data, $conn) {
  if (empty($data['TEN_YEU_CAU'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Tên yêu cầu không được để trống']);
    return;
  }

  $stmt = $conn->prepare(
    "INSERT INTO goi_trang_phuc_yeu_cau (ID_GOI, TEN_YEU_CAU, LOAI_ID, ID_NHOM, CREATED_AT) 
     VALUES (?, ?, ?, ?, NOW())"
  );
  
  if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    return;
  }

  $loaiId = isset($data['LOAI_ID']) && $data['LOAI_ID'] !== '' ? (int)$data['LOAI_ID'] : null;
  $nhomId = isset($data['ID_NHOM']) && $data['ID_NHOM'] !== '' ? (int)$data['ID_NHOM'] : null;

  $stmt->bind_param('isii', $packageId, $data['TEN_YEU_CAU'], $loaiId, $nhomId);
  
  if ($stmt->execute()) {
    http_response_code(201);
    echo json_encode([
      'success' => true,
      'message' => 'Yêu cầu đã được thêm',
      'id' => $conn->insert_id
    ]);
  } else {
    http_response_code(500);
    echo json_encode(['error' => 'Không thể thêm yêu cầu']);
  }
  
  $stmt->close();
}

/**
 * PUT /api/service-packages/{id}/requirements/{reqId} - Update requirement
 */
function handleUpdateRequirement($reqId, $data, $conn) {
  $stmt = $conn->prepare(
    "UPDATE goi_trang_phuc_yeu_cau 
     SET TEN_YEU_CAU = ?, LOAI_ID = ?, ID_NHOM = ? 
     WHERE ID_YC = ?"
  );
  
  if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    return;
  }

  $loaiId = isset($data['LOAI_ID']) && $data['LOAI_ID'] !== '' ? (int)$data['LOAI_ID'] : null;
  $nhomId = isset($data['ID_NHOM']) && $data['ID_NHOM'] !== '' ? (int)$data['ID_NHOM'] : null;

  $stmt->bind_param('siii', $data['TEN_YEU_CAU'], $loaiId, $nhomId, $reqId);
  
  if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode([
      'success' => true,
      'message' => 'Yêu cầu đã được cập nhật'
    ]);
  } else {
    http_response_code(500);
    echo json_encode(['error' => 'Không thể cập nhật yêu cầu']);
  }
  
  $stmt->close();
}

/**
 * DELETE /api/service-packages/{id}/requirements/{reqId} - Delete requirement
 */
function handleDeleteRequirement($reqId, $conn) {
  $stmt = $conn->prepare("DELETE FROM goi_trang_phuc_yeu_cau WHERE ID_YC = ?");
  
  if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    return;
  }

  $stmt->bind_param('i', $reqId);
  
  if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode([
      'success' => true,
      'message' => 'Yêu cầu đã được xóa'
    ]);
  } else {
    http_response_code(500);
    echo json_encode(['error' => 'Không thể xóa yêu cầu']);
  }
  
  $stmt->close();
}

/**
 * POST /api/service-packages/{id}/promotions - Add promotion
 */
function handleAddPromotion($packageId, $data, $promoRepo) {
  if (empty($data['TEN_KHUYEN_MAI'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Tên khuyến mãi không được để trống']);
    return;
  }

  $promoData = [
    'ID_GOI' => $packageId,
    'TEN_KHUYEN_MAI' => trim($data['TEN_KHUYEN_MAI']),
    'PHAN_TRAM' => isset($data['PHAN_TRAM']) ? (float)$data['PHAN_TRAM'] : 0,
    'TIEN_CHIET_KHAU' => isset($data['TIEN_CHIET_KHAU']) ? (float)$data['TIEN_CHIET_KHAU'] : 0,
    'NGAY_BAT_DAU' => $data['NGAY_BAT_DAU'] ?? null,
    'NGAY_KET_THUC' => $data['NGAY_KET_THUC'] ?? null,
    'DIEU_KIEN' => $data['DIEU_KIEN'] ?? null,
    'ACTIVE' => isset($data['ACTIVE']) ? (bool)$data['ACTIVE'] : true
  ];

  $promoId = $promoRepo->create($promoData);

  if ($promoId) {
    http_response_code(201);
    echo json_encode([
      'success' => true,
      'message' => 'Khuyến mãi đã được tạo',
      'id' => $promoId
    ]);
  } else {
    http_response_code(500);
    echo json_encode(['error' => 'Không thể tạo khuyến mãi']);
  }
}

/**
 * PUT /api/service-packages/{id}/promotions/{promoId} - Update promotion
 */
function handleUpdatePromotion($promoId, $data, $promoRepo) {
  $promoData = ['ID_KHUYEN_MAI' => $promoId];
  
  if (isset($data['TEN_KHUYEN_MAI'])) $promoData['TEN_KHUYEN_MAI'] = trim($data['TEN_KHUYEN_MAI']);
  if (isset($data['PHAN_TRAM'])) $promoData['PHAN_TRAM'] = (float)$data['PHAN_TRAM'];
  if (isset($data['TIEN_CHIET_KHAU'])) $promoData['TIEN_CHIET_KHAU'] = (float)$data['TIEN_CHIET_KHAU'];
  if (isset($data['NGAY_BAT_DAU'])) $promoData['NGAY_BAT_DAU'] = $data['NGAY_BAT_DAU'];
  if (isset($data['NGAY_KET_THUC'])) $promoData['NGAY_KET_THUC'] = $data['NGAY_KET_THUC'];
  if (isset($data['DIEU_KIEN'])) $promoData['DIEU_KIEN'] = $data['DIEU_KIEN'];
  if (isset($data['ACTIVE'])) $promoData['ACTIVE'] = (bool)$data['ACTIVE'];

  if ($promoRepo->update($promoData)) {
    http_response_code(200);
    echo json_encode([
      'success' => true,
      'message' => 'Khuyến mãi đã được cập nhật'
    ]);
  } else {
    http_response_code(500);
    echo json_encode(['error' => 'Không thể cập nhật khuyến mãi']);
  }
}

/**
 * DELETE /api/service-packages/{id}/promotions/{promoId} - Delete promotion
 */
function handleDeletePromotion($promoId, $promoRepo) {
  if ($promoRepo->delete($promoId)) {
    http_response_code(200);
    echo json_encode([
      'success' => true,
      'message' => 'Khuyến mãi đã được xóa'
    ]);
  } else {
    http_response_code(500);
    echo json_encode(['error' => 'Không thể xóa khuyến mãi']);
  }
}

// ===== UTILITY FUNCTIONS =====

/**
 * Parse form data (handles both application/x-www-form-urlencoded and multipart/form-data)
 */
function parseFormData() {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Check if it's JSON
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
      return json_decode(file_get_contents('php://input'), true) ?? [];
    }
    // Otherwise use $_POST (works for both form-urlencoded and multipart)
    return $_POST;
  }
  return [];
}

/**
 * Upload package image
 */
function uploadPackageImage($file) {
  if ($file['error'] !== UPLOAD_ERR_OK) return null;

  $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
  $mime = mime_content_type($file['tmp_name']);
  
  if (!isset($allowed[$mime])) return null;

  $ext = $allowed[$mime];
  $name = bin2hex(random_bytes(8)) . '.' . $ext;
  $dir = $_SERVER['DOCUMENT_ROOT'] . '/public/images/combo/';
  
  if (!is_dir($dir)) mkdir($dir, 0777, true);
  
  if (move_uploaded_file($file['tmp_name'], $dir . $name)) {
    return 'public/images/combo/' . $name;
  }

  return null;
}
?>
