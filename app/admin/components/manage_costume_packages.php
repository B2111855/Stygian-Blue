<?php
require_once '../../database/config.php';
require_once __DIR__ . '/../../repositories/PackageCostumeRepository.php';
require_once __DIR__ . '/../../repositories/CostumePackageMasterRepository.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Repositories\PackageCostumeRepository;
use App\Repositories\CostumePackageMasterRepository;

$role = (string)($_SESSION['ID_QUYEN'] ?? '');
$staffType = (string)($_SESSION['STAFF_TYPE'] ?? '');
$branchId = isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : 0;
$isBranchManager = ($role === '2' && $staffType === 'quan_ly' && $branchId > 0);

// Repositories: sử dụng goi_trang_phuc_master (local packages) và goi_trang_phuc_chi_tiet (details)
$pivotRepo = new PackageCostumeRepository($conn);
$masterRepo = new CostumePackageMasterRepository($conn);

$search = trim($_GET['search'] ?? '');
$filterBranchId = isset($_GET['filter_branch']) ? (int)$_GET['filter_branch'] : 0;
$filterStatus = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

// Create local costume package
$newCostumePackageId = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'create_local_costume_package') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
      $createError = 'CSRF token không hợp lệ';
    } else {
      $pkgName = trim($_POST['TEN_GOI'] ?? '');
      $pkgDesc = trim($_POST['MO_TA'] ?? '');
      $ownerBranch = (int)($_POST['ID_CN_OWNER'] ?? 0);
      $discountPercent = isset($_POST['DISCOUNT_PERCENT']) ? (int)$_POST['DISCOUNT_PERCENT'] : 0;
      $discountPercent = max(0, min(100, $discountPercent));
      
      if ($pkgName === '') {
        $createError = 'Tên gói không được trống.';
      } elseif ($ownerBranch <= 0) {
        $createError = 'Phải chọn chi nhánh sở hữu.';
      } elseif ($isBranchManager && $ownerBranch !== $branchId) {
        $createError = 'Manager chỉ có thể tạo gói cho chi nhánh của mình.';
      } else {
        // Manager creates local packages automatically; Admin can create local or global
        $actualOwner = $isBranchManager ? $branchId : $ownerBranch;
        // create($name, $description, $discountPercent, $scopeType, $branchId, $createdBy)
        $id = $masterRepo->create($pkgName, $pkgDesc !== '' ? $pkgDesc : null, $discountPercent, 'local', $actualOwner, '');
        if ($id) {
          $newCostumePackageId = $id;
        } else {
          $createError = 'Tạo gói thất bại.';
        }
      }
    }
  } elseif ($action === 'toggle_status') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
      $createError = 'CSRF token không hợp lệ';
    } else {
      $goiId = (int)($_POST['ID_GOI'] ?? 0);
      $newStatus = ($_POST['NEW_STATUS'] ?? '') === 'inactive' ? 'inactive' : 'active';
      
      // Permission check: admin can toggle any package; manager can toggle own local packages only
      if ($goiId > 0) {
        if ($isBranchManager) {
          // Manager must own the package locally
          $pkgCheckStmt = $conn->prepare("SELECT ID_CN_OWNER FROM goi_trang_phuc_master WHERE ID_GOI = ?");
          $pkgCheckStmt->bind_param('i', $goiId);
          $pkgCheckStmt->execute();
          $pkgCheckRes = $pkgCheckStmt->get_result();
          $pkgCheck = $pkgCheckRes->fetch_assoc();
          if (!$pkgCheck || (int)($pkgCheck['ID_CN_OWNER'] ?? 0) !== $branchId) {
            $createError = 'Bạn chỉ có thể đổi trạng thái gói local của chi nhánh mình.';
          } else {
            $masterRepo->updateStatus($goiId, $newStatus);
            header('Location: ?page=package_costumes');
            exit;
          }
        } else if ($role === '1') {
          // Admin can toggle any package
          $masterRepo->updateStatus($goiId, $newStatus);
          header('Location: ?page=package_costumes');
          exit;
        } else {
          $createError = 'Bạn không có quyền đổi trạng thái gói này.';
        }
      }
    }
  } elseif ($action === 'delete_costume_package') {
    error_log("DELETE ACTION TRIGGERED - ID_GOI: " . ($_POST['ID_GOI'] ?? 'not set'));
    error_log("CSRF Token Match: " . (hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '') ? 'YES' : 'NO'));
    error_log("User Role: $role, IsBranchManager: " . ($isBranchManager ? 'YES' : 'NO'));
    
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
      $createError = 'CSRF token không hợp lệ';
      error_log("CSRF TOKEN FAILED");
    } else {
      $goiId = (int)($_POST['ID_GOI'] ?? 0);
      error_log("Processing delete for package ID: $goiId");
      
      // Permission check: admin can delete any package; manager can delete own local packages only
      if ($goiId > 0) {
        if ($isBranchManager) {
          // Manager must own the package locally
          $pkgCheckStmt = $conn->prepare("SELECT ID_CN_OWNER FROM goi_trang_phuc_master WHERE ID_GOI = ?");
          $pkgCheckStmt->bind_param('i', $goiId);
          $pkgCheckStmt->execute();
          $pkgCheckRes = $pkgCheckStmt->get_result();
          $pkgCheck = $pkgCheckRes->fetch_assoc();
          error_log("Manager check - Package owner: " . ($pkgCheck['ID_CN_OWNER'] ?? 'not found') . ", Branch: $branchId");
          if (!$pkgCheck || (int)($pkgCheck['ID_CN_OWNER'] ?? 0) !== $branchId) {
            $createError = 'Bạn chỉ có thể xóa gói local của chi nhánh mình.';
            error_log("PERMISSION DENIED - Manager doesn't own package");
          } else {
            try {
              error_log("Calling hardDelete for package $goiId");
              if ($masterRepo->hardDelete($goiId)) {
                error_log("DELETE SUCCESS - Redirecting");
                header('Location: ?page=package_costumes');
                exit;
              } else {
                $createError = 'Không xóa được gói.';
                error_log("DELETE FAILED - hardDelete returned false");
              }
            } catch (Exception $e) {
              $createError = 'Lỗi: ' . $e->getMessage();
              error_log("DELETE EXCEPTION: " . $e->getMessage());
            }
          }
        } else if ($role === '1') {
          // Admin can delete any package
          try {
            error_log("Admin calling hardDelete for package $goiId");
            if ($masterRepo->hardDelete($goiId)) {
              error_log("ADMIN DELETE SUCCESS - Redirecting");
              header('Location: ?page=package_costumes');
              exit;
            } else {
              $createError = 'Không xóa được gói.';
              error_log("ADMIN DELETE FAILED - hardDelete returned false");
            }
          } catch (Exception $e) {
            $createError = 'Lỗi: ' . $e->getMessage();
            error_log("ADMIN DELETE EXCEPTION: " . $e->getMessage());
          }
        } else {
          $createError = 'Bạn không có quyền xóa gói này.';
          error_log("PERMISSION DENIED - Not admin or manager");
        }
      }
    }
  }
}

$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Determine branch filter
$filterBranch = null;
if ($isBranchManager) {
  $filterBranch = $branchId;
} else if ($filterBranchId > 0) {
  $filterBranch = $filterBranchId;
}

// Status filter
$statusFilterSql = '';
if ($filterStatus === 'active' || $filterStatus === 'inactive') {
  $statusFilterSql = $filterStatus;
}

// Count and list from master table
$totalRows = $masterRepo->countAll($filterBranch, $search !== '' ? $search : null, $statusFilterSql !== '' ? $statusFilterSql : null);
$totalPages = max(1, (int)ceil($totalRows / $limit));
$packages = $masterRepo->listAll($filterBranch, $search !== '' ? $search : null, $limit, $offset, $statusFilterSql !== '' ? $statusFilterSql : null);

// Load details for each package from goi_trang_phuc_chi_tiet
$costumesByPackage = [];
foreach ($packages as $pkg) {
    $pkgId = (int)$pkg['ID_GOI'];
    $costumesByPackage[$pkgId] = $pivotRepo->listCostumes($pkgId);
}

// Load branch names
$branchNames = [];
$branchQuery = $conn->query("SELECT ID_CN, TEN_CN FROM chi_nhanh");
while ($b = $branchQuery->fetch_assoc()) {
  $branchNames[(int)$b['ID_CN']] = $b['TEN_CN'];
}

function renderCostumeBadge(array $row): string {
  return (int)($row['SO_LUONG'] ?? 1) > 0 ? 'Bắt buộc' : 'Tùy chọn';
}

$baseUrl = '?page=package_costumes';
$searchParams = $search !== '' ? '&search=' . urlencode($search) : '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<body class="bg-gray-50 p-6">
  <!-- API Client JS -->
  <script src="/StygianBlue/public/assets/js/api-client.js"></script>
  <!-- Page-specific API Integration -->
  <script src="/StygianBlue/public/assets/js/manage-costume-packages.js"></script>

  <div class="max-w-6xl mx-auto space-y-6">
    <header class="flex flex-col gap-3">
      <h1 class="text-3xl font-bold text-indigo-700">Quản lý gói trang phục</h1>
      <div class="flex flex-wrap items-center gap-3">
        <form action="?" method="get" class="flex items-center gap-2 flex-wrap">
          <input type="hidden" name="page" value="package_costumes" />
          <input name="search" class="border border-gray-300 rounded px-3 py-2 w-64" placeholder="Tìm theo tên trang phục trong gói" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" />
          <select name="filter_branch" class="border border-gray-300 rounded px-2 py-2 text-sm">
            <option value="0">Tất cả chi nhánh</option>
            <?php foreach ($branchNames as $bid => $bname): ?>
              <option value="<?= (int)$bid ?>" <?= $filterBranchId===$bid?'selected':'' ?>><?= htmlspecialchars($bname) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="filter_status" class="border border-gray-300 rounded px-2 py-2 text-sm">
            <option value="">Tất cả trạng thái</option>
            <option value="active" <?= $filterStatus==='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $filterStatus==='inactive'?'selected':'' ?>>Inactive</option>
          </select>
          <button class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 transition">Lọc</button>
          <?php if ($role === '1' || $isBranchManager): ?>
            <button
              type="button"
              id="open-create-modal"
              class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 text-sm transition flex items-center"
              style="height:40px;"
              style="margin-top: 0;"
            >
              Tạo gói trang phục (local)
            </button>
          <?php endif; ?>
        </form>
      </div>
    </header>

    <?php if (!empty($createError)): ?>
      <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-start gap-3">
        <div class="text-red-600 text-lg">⚠️</div>
        <div class="flex-1">
          <p class="text-sm font-semibold text-red-800">Lỗi</p>
          <p class="text-sm text-red-700"><?= htmlspecialchars($createError) ?></p>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($newCostumePackageId)): ?>
      <script>
        window.location.href = '?page=edit_costume_package&id_goi=<?= (int)$newCostumePackageId ?>';
      </script>
    <?php endif; ?>

    <?php if (empty($packages)): ?>
      <div class="bg-white rounded-2xl shadow-md border border-dashed border-gray-200 p-10 flex flex-col items-center text-center gap-5">
        <div class="w-16 h-16 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 text-2xl">
          <i class="fas fa-box-open"></i>
        </div>
        <div class="space-y-2 max-w-md">
          <?php if ($search !== ''): ?>
            <h2 class="text-lg font-semibold text-gray-800">Không tìm thấy gói phù hợp</h2>
            <p class="text-sm text-gray-500">Không có gói trang phục nào khớp với từ khóa "<?= htmlspecialchars($search) ?>". Thử từ khóa khác hoặc xóa bộ lọc.</p>
          <?php else: ?>
            <h2 class="text-lg font-semibold text-gray-800">Chưa có gói trang phục nào</h2>
            <p class="text-sm text-gray-500">Tạo gói để nhóm các trang phục thường thuê chung theo trải nghiệm chi nhánh. Gói giúp báo giá và thao tác nhanh hơn.</p>
          <?php endif; ?>
        </div>
        <div class="flex flex-wrap gap-3 justify-center">
          <?php if (($role === '1' || $isBranchManager) && $search === ''): ?>
            <button type="button" id="open-create-modal-empty" class="px-5 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium shadow hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">+ Tạo gói đầu tiên</button>
          <?php endif; ?>
          <?php if ($search !== ''): ?>
            <a href="<?= $baseUrl ?>" class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Xóa bộ lọc</a>
          <?php endif; ?>
        </div>
        <?php if ($role !== '1' && !$isBranchManager && $search === ''): ?>
          <p class="text-xs text-gray-400">Liên hệ quản trị viên để tạo gói mới cho chi nhánh này.</p>
        <?php endif; ?>
      </div>
      <?php if ($role === '1'): ?>
      <script>
        // Reuse modal open logic for empty state CTA
        (function(){
          const btn = document.getElementById('open-create-modal-empty');
          const modalBtn = document.getElementById('open-create-modal');
          if(btn && modalBtn){ btn.addEventListener('click', ()=> modalBtn.click()); }
        })();
      </script>
      <?php endif; ?>
    <?php else: ?>
      <?php foreach ($packages as $pkg): ?>
        <?php
          $costumes = $costumesByPackage[$pkg['ID_GOI']] ?? [];
          $mandatory = array_filter($costumes, fn($row) => (int)($row['SO_LUONG'] ?? 1) > 0);
          $optional = [];
          $totalCount = count($costumes);
        ?>
        <article class="bg-white rounded-2xl shadow-md overflow-hidden border border-gray-200">
          <div class="p-6 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2 class="text-xl font-semibold text-indigo-800 flex items-center gap-2">
                  <span><?= htmlspecialchars($pkg['TEN_GOI']) ?></span>
                  <span class="text-gray-400">(#<?= (int)$pkg['ID_GOI'] ?>)</span>
                  <?php $status = $pkg['TRANG_THAI'] ?? 'active'; ?>
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $status === 'active' ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'bg-gray-200 text-gray-600 border border-gray-300' ?>">
                    <?= $status === 'active' ? 'ACTIVE' : 'INACTIVE' ?>
                  </span>
                </h2>
                <p class="text-xs text-gray-500">Chi nhánh: <strong><?= htmlspecialchars($branchNames[(int)$pkg['ID_CN_OWNER']] ?? ('#'.(int)$pkg['ID_CN_OWNER'])) ?></strong> &middot; <?= htmlspecialchars($pkg['MO_TA'] ?? 'Không mô tả') ?></p>
              </div>
              <div class="flex items-center gap-4 text-xs uppercase tracking-wide text-gray-500">
                <?php
                // Show branch owner
                $pkgOwner = (int)($pkg['ID_CN_OWNER'] ?? 0);
                $ownerName = $branchNames[$pkgOwner] ?? "CN#{$pkgOwner}";
                $scopeBadgeHtml = '<span class="px-3 py-1 rounded-full bg-cyan-50 text-cyan-700">🏢 ' . htmlspecialchars($ownerName) . '</span>';
                echo $scopeBadgeHtml;
                
                // Permission check for buttons
                $canEditDeletePackage = !$isBranchManager || ($pkgOwner === $branchId);
                ?>
                <?php if ($role === '1' || $isBranchManager): ?>
                  <form method="post" class="m-0 p-0">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                    <input type="hidden" name="action" value="toggle_status" />
                    <input type="hidden" name="ID_GOI" value="<?= (int)$pkg['ID_GOI'] ?>" />
                    <button 
                      class="text-[10px] font-semibold px-2 py-1 rounded border <?= $status === 'active' ? 'border-emerald-300 text-emerald-700 hover:bg-emerald-50' : 'border-gray-300 text-gray-600 hover:bg-gray-100' ?>" 
                      name="NEW_STATUS" 
                      value="<?= $status === 'active' ? 'inactive' : 'active' ?>" 
                      title="<?= $canEditDeletePackage ? 'Đổi trạng thái' : 'Bạn không có quyền sửa gói này' ?>"
                      <?= !$canEditDeletePackage ? 'style="opacity:0.5; cursor:not-allowed;" disabled' : '' ?>
                    >
                      <?= $status === 'active' ? 'Tắt' : 'Bật' ?>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
            <div class="flex flex-wrap gap-4 items-center text-sm text-gray-600">
              <span><strong><?= $totalCount ?></strong> trang phục</span>
              <span><strong><?= count($mandatory) ?></strong> bắt buộc</span>
              <span><strong><?= count($optional) ?></strong> tùy chọn</span>
              <?php if (!empty($pkg['DISCOUNT_PERCENT']) && (int)$pkg['DISCOUNT_PERCENT'] > 0): ?>
                <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-semibold">Khuyến mãi: <?= (int)$pkg['DISCOUNT_PERCENT'] ?>%</span>
              <?php endif; ?>
              <a 
                href="?page=edit_costume_package&id_goi=<?= (int)$pkg['ID_GOI'] ?>" 
                class="ml-auto text-xs px-3 py-1 rounded-full border border-indigo-200 text-indigo-700 hover:bg-indigo-50"
                title="<?= $canEditDeletePackage ? 'Chỉnh sửa gói' : 'Bạn không có quyền sửa gói này' ?>"
                <?= !$canEditDeletePackage ? 'style="opacity:0.5; cursor:not-allowed; pointer-events:none;" onclick="return false;"' : '' ?>
              >
                Quản lý chi tiết gói trang phục
              </a>
              <?php if (($role === '1' || $isBranchManager) && $totalCount === 0): ?>
                <form method="post" class="inline-block ml-2" onsubmit="console.log('Delete form submitting', {ID_GOI: this.querySelector('[name=ID_GOI]').value}); <?php if ($canEditDeletePackage) { ?>if(!confirm('⚠️ CẢNH BÁO: Bạn chắc chắn muốn xóa vĩnh viễn gói này?\n\nHành động này KHÔNG THỂ KHÔI PHỤC!')){return false;}<?php } else { ?>alert('Bạn không có quyền xóa gói này'); return false;<?php } ?>">
                  <input type="hidden" name="action" value="delete_costume_package" />
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                  <input type="hidden" name="ID_GOI" value="<?= (int)$pkg['ID_GOI'] ?>" />
                  <button 
                    type="submit" 
                    class="text-xs px-3 py-1 rounded-full border border-red-200 text-red-700 hover:bg-red-50" 
                    title="<?= $canEditDeletePackage ? 'Xóa vĩnh viễn gói này (không thể khôi phục)' : 'Bạn không có quyền xóa gói này' ?>"
                    <?= !$canEditDeletePackage ? 'style="opacity:0.5; cursor:not-allowed;" disabled' : '' ?>
                  >
                    🗑️ Xóa vĩnh viễn
                  </button>
                </form>
              <?php endif; ?>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <?php if ($totalCount === 0): ?>
                <div class="col-span-full rounded-xl border border-dashed border-indigo-200 bg-indigo-50 p-4 text-center text-sm text-indigo-700">Chưa có trang phục nào được gán.</div>
              <?php else: ?>
                <?php foreach ($costumes as $row): ?>
                  <div class="rounded-2xl border border-gray-100 p-4 bg-gray-50 space-y-2">
                    <div class="flex items-center justify-between text-xs uppercase tracking-widest text-gray-500">
                      <span><?= renderCostumeBadge($row) ?></span>
                      <span class="font-semibold text-indigo-700">SL: <?= (int)($row['SO_LUONG'] ?? 1) ?></span>
                    </div>
                    <div>
                      <p class="font-semibold text-gray-800"><?= htmlspecialchars($row['TEN']) ?></p>
                      <p class="text-xs text-gray-500">ID trang phục #<?= (int)$row['ID_TRANG_PHUC'] ?></p>
                    </div>
                    <div class="flex items-center justify-between text-sm text-gray-700">
                      <span>Giá thuê: <strong><?= number_format((int)$row['GIA_THUE'], 0, ',', '.') ?>₫</strong></span>
                      <span class="px-2 py-0.5 rounded-full text-[11px] bg-white border border-gray-200"><?= htmlspecialchars($row['TRANG_THAI']) ?></span>
                    </div>
                    <?php if (!empty($row['MAU_SAC'])): ?>
                      <p class="text-xs text-gray-400">Màu sắc: <?= htmlspecialchars($row['MAU_SAC']) ?></p>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>

      <?php if ($totalPages > 1): ?>
        <div class="flex flex-wrap items-center justify-center gap-3 mt-6">
          <?php if ($page > 1): ?>
            <a href="<?= $baseUrl ?>&p=<?= $page-1 ?><?= $searchParams ?>" class="px-3 py-1 rounded border text-xs bg-white hover:bg-gray-50">« Trước</a>
          <?php endif; ?>
          <span class="text-xs text-gray-600">Trang <?= $page ?> / <?= $totalPages ?></span>
          <?php if ($page < $totalPages): ?>
            <a href="<?= $baseUrl ?>&p=<?= $page+1 ?><?= $searchParams ?>" class="px-3 py-1 rounded border text-xs bg-white hover:bg-gray-50">Sau »</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php if ($role === '1' || $isBranchManager): ?>
  <!-- Modal tạo gói -->
  <div id="create-modal" class="fixed inset-0 hidden flex items-center justify-center bg-black/40 backdrop-blur-sm z-50" role="dialog" aria-modal="true" aria-labelledby="create-modal-title">
    <div class="bg-white w-full max-w-md rounded-xl shadow-lg p-6 space-y-4 relative animate-fade-in">
      <button type="button" id="close-create-modal" class="absolute top-2 right-2 text-gray-400 hover:text-gray-600">×</button>
      <h2 id="create-modal-title" class="text-lg font-semibold text-indigo-700">Tạo gói trang phục (local)</h2>
      <?php if (!empty($createError)): ?>
        <div class="text-red-600 text-xs font-medium mb-1"><?= htmlspecialchars($createError) ?></div>
      <?php endif; ?>
      <form method="post" class="space-y-3" id="create-package-form">
        <input type="hidden" name="action" value="create_local_costume_package" />
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
        <div>
          <label class="text-xs font-semibold text-gray-600">Tên gói</label>
          <input name="TEN_GOI" id="TEN_GOI_INPUT" class="mt-1 w-full border rounded px-2 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="Ví dụ: Gói Áo Dài Cần Thơ" required />
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-600">Mô tả</label>
          <textarea name="MO_TA" class="mt-1 w-full border rounded px-2 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" rows="2" placeholder="Mô tả ngắn"></textarea>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-600">Chi nhánh sở hữu</label>
          <?php if ($isBranchManager): ?>
            <input type="hidden" name="ID_CN_OWNER" value="<?= (int)$branchId ?>" />
            <input type="text" class="mt-1 w-full border rounded px-2 py-2 text-sm bg-gray-100" value="<?= htmlspecialchars($branchNames[$branchId] ?? "Chi nhánh #{$branchId}") ?>" disabled />
            <p class="text-xs text-gray-500 mt-1">Manager chỉ có thể tạo gói cho chi nhánh của mình</p>
          <?php else: ?>
            <select name="ID_CN_OWNER" class="mt-1 w-full border rounded px-2 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" required>
              <option value="">-- Chọn chi nhánh --</option>
              <?php
              $branchRes = $conn->query("SELECT ID_CN, TEN_CN FROM chi_nhanh ORDER BY TEN_CN");
              while ($b = $branchRes->fetch_assoc()): ?>
                <option value="<?= (int)$b['ID_CN'] ?>"><?= htmlspecialchars($b['TEN_CN']) ?></option>
              <?php endwhile; ?>
            </select>
          <?php endif; ?>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-600">Khuyến mãi toàn gói (%)</label>
          <input type="number" name="DISCOUNT_PERCENT" min="0" max="100" value="0" class="mt-1 w-full border rounded px-2 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none" placeholder="Giảm giá (%)" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" id="cancel-create" class="px-4 py-2 text-sm rounded border border-gray-300 text-gray-600 hover:bg-gray-50">Hủy</button>
          <button type="submit" class="px-4 py-2 text-sm rounded bg-emerald-600 text-white hover:bg-emerald-700">Tạo gói</button>
        </div>
      </form>
    </div>
  </div>
  <script>
    (function(){
      const openBtn = document.getElementById('open-create-modal');
      const modal = document.getElementById('create-modal');
      const closeBtn = document.getElementById('close-create-modal');
      const cancelBtn = document.getElementById('cancel-create');
      function open(){ modal.classList.remove('hidden'); setTimeout(()=>document.getElementById('TEN_GOI_INPUT').focus(),50); }
      function close(){ modal.classList.add('hidden'); }
      if(openBtn){ openBtn.addEventListener('click', open); }
      if(closeBtn){ closeBtn.addEventListener('click', close); }
      if(cancelBtn){ cancelBtn.addEventListener('click', close); }
      modal.addEventListener('click', e=>{ if(e.target === modal) close(); });
      document.addEventListener('keydown', e=>{ if(e.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
    })();
  </script>
  <?php endif; ?>
</body>
