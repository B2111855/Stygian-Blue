<?php
include '../../database/config.php';
require_once __DIR__ . '/../../repositories/PackageRepository.php';
require_once __DIR__ . '/../../repositories/ServiceRepository.php';
require_once __DIR__ . '/../../repositories/PackageCostumeRepository.php';
// DEPRECATED: Slot-based requirement system removed
// require_once __DIR__ . '/../../repositories/PackageSlotRepository.php';
require_once __DIR__ . '/../../repositories/PromotionRepository.php';
require_once __DIR__ . '/../../helpers/system_log.php';
use App\Repositories\PackageRepository; use App\Repositories\ServiceRepository;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['csrf_token'])) { try { $_SESSION['csrf_token']=bin2hex(random_bytes(32)); } catch(Exception $e){ $_SESSION['csrf_token']=md5(uniqid((string)mt_rand(),true)); } }

date_default_timezone_set('Asia/Ho_Chi_Minh');
// Global POST CSRF validation gate (if functions not already defined)
if (!function_exists('pkg_csrf_token')) {
  function pkg_csrf_token(): string { return $_SESSION['csrf_token'] ?? ''; }
}
if (!function_exists('pkg_require_csrf')) {
  function pkg_require_csrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $posted = $_POST['csrf'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    return ($posted !== '' && $stored !== '' && hash_equals($stored,$posted));
  }
}
if ($_SERVER['REQUEST_METHOD']==='POST' && !pkg_require_csrf()) {
  $errorMessage = 'CSRF không hợp lệ.';
}
$pkgRepo = new PackageRepository($conn);
$svcRepo = new ServiceRepository($conn);
$pcRepo  = new \App\Repositories\PackageCostumeRepository($conn);
// $slotRepo deprecated after migration to direct requirement mapping
$slotRepo = null;
$promoRepo = new PromotionRepository($conn);

// Inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page   = isset($_GET['p']) ? max(1,(int)$_GET['p']) : 1;
$limit  = 5;
$offset = ($page-1)*$limit;
$totalRows  = $pkgRepo->countPackages($search);
$totalPages = max(1,(int)ceil($totalRows/$limit));
$packages   = $pkgRepo->searchPackages($search,$limit,$offset); // will be overridden after role scope detection

// Scope / role detection for package visibility & permissions
$role = (string)($_SESSION['ID_QUYEN'] ?? ''); // '1' admin, '2' staff
$staffType = (string)($_SESSION['STAFF_TYPE'] ?? '');
$branchId = isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : 0;
$isBranchManager = ($role === '2' && $staffType === 'quan_ly' && $branchId > 0);
// If branch manager: show global + local packages for their branch; admin sees all (pass null)
$packages = $pkgRepo->searchPackages($search,$limit,$offset,$isBranchManager ? $branchId : null);

// Recompute pricing with unified promotion logic (covers both new + legacy promo tables)
foreach ($packages as &$pkg) {
  $pricing = $pkgRepo->getPackagePromotionPricing((int)$pkg['ID_GOI'], 0.0);
  $pkg['TONG_GIA_GOI'] = $pricing['base_total'];
  $pkg['GIA_SAU_GIAM'] = $pricing['subtotal'];
  $pkg['SO_TIEN_GIAM'] = $pricing['discount'];
  $pkg['TEN_CHUONG_TRINH'] = $pricing['promotion']['TEN_CHUONG_TRINH']
    ?? $pricing['promotion']['TEN_KM']
    ?? ($pkg['TEN_CHUONG_TRINH'] ?? null);
}
unset($pkg);

// Helpers for new Slots (Yêu cầu trang phục)
if (!function_exists('pkg_table_exists')) {
  function pkg_table_exists(mysqli $conn, string $table): bool {
    $tbl = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '".$tbl."'");
    return $res && $res->num_rows > 0;
  }
}
// Active sub-tab inside edit view
$activeTab = isset($_GET['tab']) && in_array($_GET['tab'], ['info','services','slots','promotions']) ? $_GET['tab'] : 'info';
// New structured requirement tables
$reqTableExists = pkg_table_exists($conn,'goi_trang_phuc_yeu_cau');
$groupTableExists = pkg_table_exists($conn,'trang_phuc_nhom');
$loaiTableExists = pkg_table_exists($conn,'trang_phuc_loai');

// Include specialized handlers for separated concerns (after variables are defined)
include __DIR__ . '/manage_packages_requirements.php';
include __DIR__ . '/manage_packages_promotions.php';
include __DIR__ . '/manage_packages_requirements_handler.php';
include __DIR__ . '/manage_packages_promotions_handler.php';
// Load branches for both form and mapping
$branches = [];
$resBranches = $conn->query("SELECT ID_CN, TEN_CN FROM chi_nhanh ORDER BY TEN_CN");
if ($resBranches) { while($r=$resBranches->fetch_assoc()){ $branches[]=$r; } }

// Branch selector for mapping (admin selects, manager fixed)
$selectedMapBranchId = 0;
if ($activeTab==='slots') {
  if ($isBranchManager) {
    $selectedMapBranchId = $branchId;
  } else {
    $selectedMapBranchId = isset($_GET['map_cn']) ? (int)$_GET['map_cn'] : ((count($branches)>0)? (int)$branches[0]['ID_CN'] : 0);
  }
}

function uploadPkgImage(string $field): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error']!==UPLOAD_ERR_OK) return null;
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $mime = mime_content_type($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) return null;
    $ext  = $allowed[$mime];
    $name = bin2hex(random_bytes(8)).'.'.$ext;
    // Use absolute path from project root
    $projectRoot = dirname(dirname(dirname(__DIR__))); // Go up from admin/components to StygianBlue root
    $dirFS = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'combo' . DIRECTORY_SEPARATOR;
    if (!is_dir($dirFS)) {
        mkdir($dirFS, 0755, true);
        error_log('Created directory: ' . $dirFS);
    }
    $targetPath = $dirFS . $name;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $targetPath)) {
        error_log('Failed to move uploaded file to: ' . $targetPath);
        return null;
    }
    error_log('Successfully uploaded image to: ' . $targetPath);
    return 'public/images/combo/'.$name;
}

// Create
if ($_SERVER['REQUEST_METHOD']==='POST' && (isset($_POST['add_package']) || isset($_POST['add_package_hidden']))) {
  if (isset($errorMessage)) {
    error_log('Skip create: pre-existing error -> '.$errorMessage);
  } else {
    error_log('Add package form submitted');
    error_log('POST keys: '.implode(',', array_keys($_POST)));
    $errors = [];
    $tenGoi = trim($_POST['TEN_GOI'] ?? '');
    if ($tenGoi === '') { $errors[] = 'Tên gói là bắt buộc'; }
    elseif (mb_strlen($tenGoi) < 5) { $errors[] = 'Tên gói phải có ít nhất 5 ký tự'; }
    elseif (mb_strlen($tenGoi) > 150) { $errors[] = 'Tên gói không quá 150 ký tự'; }
    $moTa = trim($_POST['MO_TA'] ?? '');
    if ($moTa === '') { $errors[] = 'Mô tả là bắt buộc'; }
    elseif (mb_strlen($moTa) < 20) { $errors[] = 'Mô tả phải có ít nhất 20 ký tự để khách hàng hiểu rõ'; }
    elseif (mb_strlen($moTa) > 500) { $errors[] = 'Mô tả không quá 500 ký tự'; }
    $scopeType = $isBranchManager ? 'local' : (($_POST['SCOPE_TYPE'] ?? 'global') === 'local' ? 'local' : 'global');
    $idCnOwner = null;
    if ($scopeType === 'local') {
      if ($isBranchManager) { $idCnOwner = $branchId; }
      else {
        $idCnOwner = ($_POST['ID_CN_OWNER'] ?? '') !== '' ? (int)$_POST['ID_CN_OWNER'] : null;
        if (!$idCnOwner) { $errors[] = 'Vui lòng chọn chi nhánh sở hữu cho gói local'; }
      }
    }
    $hieuLucTu = $_POST['HIEU_LUC_TU'] ?: null;
    $hieuLucDen = $_POST['HIEU_LUC_DEN'] ?: null;
    if ($hieuLucTu && $hieuLucDen) {
      $tuDate = strtotime($hieuLucTu); $denDate = strtotime($hieuLucDen);
      if ($tuDate >= $denDate) { $errors[] = 'Ngày kết thúc phải sau ngày bắt đầu'; }
    }
    if (!empty($errors)) {
      $errorMessage = '<strong>Vui lòng sửa các lỗi sau:</strong><ul class="list-disc ml-5 mt-2">';
      foreach ($errors as $err) { $errorMessage .= '<li>'.htmlspecialchars($err).'</li>'; }
      $errorMessage .= '</ul>';
      error_log('Validation errors: '.print_r($errors,true));
    } else {
      $payload = [
        'TEN_GOI' => $tenGoi,
        'MO_TA' => $moTa,
        'HIEU_LUC_TU' => $hieuLucTu,
        'HIEU_LUC_DEN' => $hieuLucDen,
        'SCOPE_TYPE' => $scopeType,
        'ID_CN_OWNER' => $idCnOwner,
      ];
      try {
        $img = uploadPkgImage('HINH_ANH');
        $newId = $pkgRepo->create($payload,$img);
        if ($newId) {
          $successMessage = 'Tạo gói dịch vụ thành công! ID: '.$newId;
          record_system_log($conn,'PACKAGE_CREATE','package:'.$newId,null,[
            'ID_GOI'=>$newId,'TEN_GOI'=>$payload['TEN_GOI'],'TRANG_THAI'=>'nhap','SCOPE_TYPE'=>$scopeType
          ]);
          error_log('Package created successfully: ID='.$newId);
          $redirectUrl = $_SERVER['PHP_SELF'].'?page=packages&edit='.$newId.'&tab=services&created=1';
          if (!headers_sent()) { header('Location: '.$redirectUrl); exit; }
          echo '<script>window.location.href='.json_encode($redirectUrl).';</script>'; exit;
        } else {
          $errorMessage = 'Không thể tạo gói. Vui lòng thử lại.';
          error_log('Package create returned null/false');
        }
      } catch (\Throwable $e) {
        $errorMessage = 'Lỗi khi tạo gói: '.$e->getMessage();
        error_log('Package create exception: '.$e->getMessage());
      }
    }
  }
}

// Update package
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_package'])) {
    $payload = [
        'ID_GOI'       => (int)$_POST['ID_GOI'],
        'TEN_GOI'      => trim($_POST['TEN_GOI'] ?? ''),
        'MO_TA'        => trim($_POST['MO_TA'] ?? ''),
        'HIEU_LUC_TU'  => $_POST['HIEU_LUC_TU'] ?: null,
        'HIEU_LUC_DEN' => $_POST['HIEU_LUC_DEN'] ?: null,
    ];
    $img = uploadPkgImage('HINH_ANH');
    if ($payload['TEN_GOI']==='' || $payload['MO_TA']==='') {
        $errorMessage = 'Dữ liệu cập nhật không hợp lệ.';
  } else {
    $before = $pkgRepo->find($payload['ID_GOI']);
    if (!$before) {
      $errorMessage = 'Gói không tồn tại.';
    } else {
      $isLocalOwned = ($before['SCOPE_TYPE'] ?? '') === 'local' && (int)($before['ID_CN_OWNER'] ?? 0) === $branchId;
      if ($isBranchManager && !$isLocalOwned) {
        $errorMessage = 'Không thể sửa gói global hoặc gói thuộc chi nhánh khác.';
      } else {
        // Admin có thể đổi scope; manager luôn giữ local
        if ($isBranchManager) {
          $payload['SCOPE_TYPE'] = 'local';
          $payload['ID_CN_OWNER'] = $branchId;
        } else {
          if (isset($_POST['SCOPE_TYPE'])) { $payload['SCOPE_TYPE'] = ($_POST['SCOPE_TYPE'] === 'local' ? 'local' : 'global'); }
          if (isset($_POST['ID_CN_OWNER']) && $_POST['ID_CN_OWNER'] !== '') { $payload['ID_CN_OWNER'] = (int)$_POST['ID_CN_OWNER']; }
        }
        if ($pkgRepo->update($payload,$img)) {
          $after = $pkgRepo->find($payload['ID_GOI']);
          $successMessage = 'Cập nhật gói thành công.';
          record_system_log($conn,'PACKAGE_UPDATE','package:'.$payload['ID_GOI'],$before,$after);
        } else { $errorMessage = 'Không cập nhật được gói.'; }
      }
    }
  }
}

// Delete package via POST
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_package']) && !isset($errorMessage)) {
  $id = (int)$_POST['ID_GOI'];
  $before = $pkgRepo->find($id);
  if (!$before) {
    $errorMessage = 'Gói không tồn tại.';
  } else {
    // Permission check: manager can only delete local packages of their own branch
    if ($isBranchManager) {
      $isLocalOwned = ($before['SCOPE_TYPE'] ?? '') === 'local' && (int)($before['ID_CN_OWNER'] ?? 0) === $branchId;
      if (!$isLocalOwned) {
        $errorMessage = 'Bạn chỉ có thể xóa gói local của chi nhánh mình. Gói này là '.($before['SCOPE_TYPE']==='global'?'global':'của chi nhánh khác').'.';
      }
    }
    
    if (!isset($errorMessage)) {
      if ($pkgRepo->delete($id)) {
        $successMessage = 'Gói đã xóa vĩnh viễn.';
        record_system_log($conn,'PACKAGE_DELETE','package:'.$id,$before,null);
        $showReloadScript = true;
      } else {
        $errorMessage = 'Không xóa được gói.';
      }
    }
  }
}

// Change status (publish / retire) via POST
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['change_status']) && !isset($errorMessage)) {
  $id = (int)$_POST['ID_GOI'];
  $st = $_POST['STATUS'] ?? '';
  $before = $pkgRepo->find($id);
  
  // Permission check: manager can only change status of local packages of their own branch
  if ($isBranchManager && $before) {
    $isLocalOwned = ($before['SCOPE_TYPE'] ?? '') === 'local' && (int)($before['ID_CN_OWNER'] ?? 0) === $branchId;
    if (!$isLocalOwned) {
      $errorMessage = 'Bạn chỉ có thể đổi trạng thái gói local của chi nhánh mình.';
    }
  }
  
  if (!isset($errorMessage)) {
    $reasons = $pkgRepo->deletionBlockingReasons($id);
    if ($st==='ngung') {
    if (empty(array_filter($reasons, fn($r)=>$r['code']!=='selling_active'))) {
      $before = $pkgRepo->find($id);
      if ($pkgRepo->retire($id)) {
        $after = $pkgRepo->find($id);
        $successMessage='Gói đã ngừng.';
        record_system_log($conn,'PACKAGE_RETIRE','package:'.$id,$before,$after);
      } else { $errorMessage='Không ngừng được gói.'; }
    } else {
      $errorMessage = 'Không thể ngừng: '.implode(' | ', array_map(fn($r)=>$r['message'],$reasons));
    }
  } elseif ($st==='ban') {
    $before = $pkgRepo->find($id);
    
    // CRITICAL FIX #S3: Validate package has at least 1 service (canPublishPackage)
    if (!isset($errorMessage) && $before) {
      $validation = $pkgRepo->canPublishPackage($id);
      if (!$validation['can_publish']) {
        $errorMessage = 'Không thể xuất bản: '.$validation['reason'];
        error_log('[CRITICAL_FIX_S3] Package #'.$id.': ' . $validation['reason']);
      }
    }
    
    // Guard: for local packages, ensure required slot mappings exist for owner branch
    if (!isset($errorMessage) && $before && ($before['SCOPE_TYPE'] ?? '') === 'local') {
      if (pkg_table_exists($conn,'goi_dich_vu_trang_phuc_slot')) {
        $ownerCn = (int)($before['ID_CN_OWNER'] ?? 0);
        if ($ownerCn > 0) {
          $missing = (new \App\Repositories\PackageSlotRepository($conn))->countMissingRequiredMappingsForBranch($id, $ownerCn);
          if ($missing > 0) {
            $errorMessage = 'Không thể đăng: còn '.$missing.' yêu cầu trang phục bắt buộc chưa được ánh xạ ở chi nhánh #'.$ownerCn.'.';
          }
        }
      }
    }
    if (!isset($errorMessage) && $isBranchManager && $before && ($before['SCOPE_TYPE'] ?? '') === 'global') {
      $errorMessage='Không thể đổi trạng thái gói global.';
    } elseif (!isset($errorMessage) && $pkgRepo->changeStatus($id,'ban')) {
      $after = $pkgRepo->find($id);
      $successMessage='Gói đã chuyển sang bán.';
      record_system_log($conn,'PACKAGE_PUBLISH','package:'.$id,$before,$after);
      
      // CRITICAL FIX #S4: Lock package prices on successful publish (lockPackagePrices)
      $locked = $pkgRepo->lockPackagePrices($id);
      if ($locked > 0) {
        record_system_log($conn,'PACKAGE_LOCK_PRICES','package:'.$id,null,['locked_count'=>$locked]);
        error_log('[CRITICAL_FIX_S4] Package #'.$id.': Locked '.$locked.' service prices on publish');
      }
    } else {
      if (!isset($errorMessage)) $errorMessage='Không chuyển trạng thái.';
    }
  }
  }
}

// Structured Costume Requirements (Yêu cầu) CRUD
// HANDLER MOVED TO: manage_packages_requirements.php (included at top)
// Processes: yc_create, yc_update, yc_delete


// Direct requirement -> branch costume mapping handler (new normalized flow)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['yc_set_mappings'])) {
  $mapTable = 'goi_yc_branch_trang_phuc';
  if (!pkg_table_exists($conn,$mapTable)) {
    $errorMessage = 'Chưa chạy migration bảng ánh xạ yêu cầu chuẩn hóa.';
  } else {
    $idGoi = (int)($_POST['ID_GOI'] ?? 0);
    $idYc  = (int)($_POST['ID_YC'] ?? 0);
    $mapCn = (int)($_POST['MAP_CN'] ?? 0);
    $payloadJson = $_POST['mapping_payload'] ?? '[]';
    $items = json_decode($payloadJson,true);
    $pkgMetaTmp = $idGoi ? $pkgRepo->find($idGoi) : null;
    $deny = false;
    if ($pkgMetaTmp) {
      $isGlobal = ($pkgMetaTmp['SCOPE_TYPE'] ?? '') === 'global';
      $isLocalOwned = ($pkgMetaTmp['SCOPE_TYPE'] ?? '') === 'local' && (int)($pkgMetaTmp['ID_CN_OWNER'] ?? 0) === $branchId;
      if ($isBranchManager && ($mapCn !== $branchId || $isGlobal || !$isLocalOwned)) $deny = true;
    }
    if ($deny) {
      $errorMessage = 'Bạn không có quyền cấu hình ánh xạ cho chi nhánh này.';
    } elseif (!$idGoi || !$idYc || !$mapCn || !is_array($items)) {
      $errorMessage = 'Dữ liệu ánh xạ không hợp lệ.';
    } else {
      try {
        $conn->begin_transaction();
        $conn->query('DELETE FROM goi_yc_branch_trang_phuc WHERE ID_YC='.(int)$idYc.' AND ID_CN='.(int)$mapCn);
        $count = 0;
        foreach ($items as $it) {
          $tp = (int)($it['ID_TRANG_PHUC'] ?? 0); if(!$tp) continue;
          $adjVal = isset($it['PRICE_ADJUSTMENT']) && $it['PRICE_ADJUSTMENT']!=='' ? (int)$it['PRICE_ADJUSTMENT'] : 'NULL';
          $q = 'INSERT INTO goi_yc_branch_trang_phuc (ID_YC, ID_CN, ID_TRANG_PHUC, PRICE_ADJUSTMENT, ACTIVE) VALUES ('
            .(int)$idYc.','.(int)$mapCn.','.$tp.','.$adjVal.',1)';
          if(!$conn->query($q)) throw new \Exception('Lỗi insert ánh xạ');
          $count++;
        }
        $conn->commit();
        $successMessage = 'Đã lưu ánh xạ yêu cầu chuẩn hóa.';
        record_system_log($conn,'REQ_MAP_SET','yc_map:'.$idYc.':cn:'.$mapCn,null,['count'=>$count]);
        $_GET['edit'] = (string)$idGoi; $_GET['tab']='slots'; $isEditing=true;
      } catch (\Throwable $e) { $conn->rollback(); $errorMessage = 'Lỗi ánh xạ YC: '.$e->getMessage(); }
    }
  }
}

// Add service to package
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_service_to_package'])) {
    $idGoi = (int)$_POST['ID_GOI'];
    $idDv  = (int)$_POST['ID_DV'];
    $soLuong = (int)($_POST['SO_LUONG'] ?? 1);
    $donGiaOverride = $_POST['DON_GIA_AP_DUNG']!=='' ? (int)$_POST['DON_GIA_AP_DUNG'] : null;
    $thuTu = (int)($_POST['THU_TU'] ?? 1);
    if ($pkgRepo->addService($idGoi,$idDv,$soLuong,$donGiaOverride,$thuTu)) {
        $successMessage = 'Đã thêm dịch vụ vào gói.';
    record_system_log($conn,'PACKAGE_ADD_SERVICE','package_service:'.$idGoi.':'.$idDv,null,[
      'ID_GOI'=>$idGoi,'ID_DV'=>$idDv,'SO_LUONG'=>$soLuong,'DON_GIA_AP_DUNG'=>$donGiaOverride,'THU_TU'=>$thuTu
    ]);
    } else { $errorMessage = 'Không thêm được dịch vụ.'; }
}

// Add costume to package (pivot)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_costume_to_package'])) {
  $idGoi = (int)$_POST['ID_GOI'];
  $idTp  = (int)$_POST['ID_TRANG_PHUC'];
  $soLuong = max(1,(int)($_POST['SO_LUONG'] ?? 1));
  // Determine next order
  $current = $pcRepo->listCostumes($idGoi);
  $nextOrder = count($current) + 1;
  // Permission: branch manager cannot add to global package
  $pkgMeta = $pkgRepo->find($idGoi);
  if ($pkgMeta) {
    $isGlobal = ($pkgMeta['SCOPE_TYPE'] ?? '') === 'global';
    if ($isBranchManager && $isGlobal) {
      $errorMessage = 'Không thể thêm trang phục vào gói global với quyền chi nhánh.';
    } else {
      // If local enforce same branch when manager
      if ($isBranchManager && ($pkgMeta['SCOPE_TYPE'] ?? '') === 'local' && (int)$pkgMeta['ID_CN_OWNER'] !== $branchId) {
        $errorMessage = 'Gói thuộc chi nhánh khác.';
      } else {
        // Optional: block retired
        $tpCheck = $conn->query('SELECT TRANG_THAI FROM trang_phuc WHERE ID_TRANG_PHUC=' . $idTp . ' LIMIT 1');
        $rowTp = $tpCheck && $tpCheck->num_rows ? $tpCheck->fetch_assoc() : null;
        if (!$rowTp) {
          $errorMessage = 'Trang phục không tồn tại.';
        } elseif (($rowTp['TRANG_THAI'] ?? '') === 'retired') {
          $errorMessage = 'Không thể thêm trang phục đã retired.';
        } else {
          if ($pcRepo->addCostume($idGoi,$idTp,$soLuong,$nextOrder,null)) {
            $successMessage = 'Đã thêm trang phục vào gói.';
          } else {
            $errorMessage = 'Không thêm được trang phục.';
          }
        }
      }
    }
  } else { $errorMessage = 'Gói không tồn tại.'; }
}

// Remove costume from package
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['remove_costume_from_package'])) {
  $idGoi = (int)$_POST['ID_GOI'];
  $idTp  = (int)$_POST['ID_TRANG_PHUC'];
  $pkgMeta = $pkgRepo->find($idGoi);
  if ($pkgMeta) {
    if ($isBranchManager && ($pkgMeta['SCOPE_TYPE'] ?? '') === 'global') {
      $errorMessage = 'Không thể sửa gói global.';
    } elseif ($isBranchManager && ($pkgMeta['SCOPE_TYPE'] ?? '') === 'local' && (int)$pkgMeta['ID_CN_OWNER'] !== $branchId) {
      $errorMessage = 'Gói thuộc chi nhánh khác.';
    } else {
      if ($pcRepo->removeCostume($idGoi,$idTp)) { $successMessage='Đã xóa trang phục khỏi gói.'; } else { $errorMessage='Không xóa được trang phục.'; }
    }
  } else { $errorMessage='Gói không tồn tại.'; }
}

// Reorder costumes
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reorder_costumes'])) {
  $idGoi = (int)$_POST['ID_GOI'];
  $payload = trim($_POST['order_payload'] ?? '');
  $pkgMeta = $pkgRepo->find($idGoi);
  if ($pkgMeta) {
    if ($isBranchManager && ($pkgMeta['SCOPE_TYPE'] ?? '') === 'global') {
      $errorMessage='Không thể sắp xếp gói global.';
    } else {
      $ids = array_filter(array_map('intval', explode(',', $payload)));
      if ($pcRepo->reorder($idGoi,$ids)) { $successMessage='Đã cập nhật thứ tự trang phục.'; } else { $errorMessage='Không cập nhật thứ tự.'; }
    }
  }
}

// Remove service from package
if (isset($_GET['remove_service']) && isset($_GET['pkg'])) {
    $pkg = (int)$_GET['pkg'];
    $dv  = (int)$_GET['remove_service'];
    if ($pkgRepo->removeService($pkg,$dv)) { $successMessage='Đã xóa dịch vụ khỏi gói.'; } else { $errorMessage='Không xóa được dịch vụ.'; }
  record_system_log($conn,'PACKAGE_REMOVE_SERVICE','package_service:'.$pkg.':'.$dv,null,null);
}

$isEditing = isset($_GET['edit']);
$editPackage = null; $editServices=[];
// Bulk update drag/drop (executed before loading edit services fresh)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bulk_update_services'])) {
  $idBulk = (int)($_POST['ID_GOI'] ?? 0);
  $bulkJson = $_POST['bulk_payload'] ?? '[]';
  $decoded = json_decode($bulkJson, true);
  if ($idBulk && is_array($decoded)) {
    if ($pkgRepo->bulkReplaceServices($idBulk, $decoded)) {
      $successMessage = 'Đã cập nhật thành phần gói.';
      record_system_log($conn,'PACKAGE_BULK_UPDATE','package:'.$idBulk,null,['count'=>count($decoded)]);
      $_GET['edit'] = (string)$idBulk; // ensure we stay on edit view
      $isEditing = true;
    } else { $errorMessage = 'Không thể bulk update.'; }
  } else { $errorMessage = 'Dữ liệu bulk không hợp lệ.'; }
}

// Slot CRUD deprecated – migrated to normalized requirements

// Slot mapping handler deprecated

// Promotion CRUD Handler
// HANDLER MOVED TO: manage_packages_promotions.php (included at top)
// Processes: promo_create, promo_update, promo_toggle_active, promo_delete


if ($isEditing) {
    $editPackage = $pkgRepo->find((int)$_GET['edit']);
    if ($editPackage) { $editServices = $pkgRepo->listServices((int)$editPackage['ID_GOI']); }
    $editCostumes = $editPackage ? $pcRepo->listCostumes((int)$editPackage['ID_GOI']) : [];
    // Load all active services for selection
  $allServices = $svcRepo->searchServices('',200,0);
  $existingIds = array_column($editServices,'ID_DV');
  // Available costumes (exclude retired). Branch manager restricted to its branch
  if ($editPackage) {
      $costumeSql = "SELECT ID_TRANG_PHUC, TEN, GIA_THUE, TRANG_THAI FROM trang_phuc WHERE TRANG_THAI <> 'retired'";
      if ($isBranchManager) { $costumeSql .= ' AND ID_CN=' . (int)$branchId; }
      $costumeSql .= ' ORDER BY TEN LIMIT 300';
      $allCostumesRes = $conn->query($costumeSql);
      $allCostumes = [];
      if ($allCostumesRes) { while($r=$allCostumesRes->fetch_assoc()){ $allCostumes[]=$r; } }
      $existingCostumeIds = array_column($editCostumes,'ID_TRANG_PHUC');
  } else { $allCostumes=[]; $existingCostumeIds=[]; }
  // Slots deprecated – placeholders for legacy variables (UI removed later)
  $slotsTableExists = false; $slots=[]; $canEditSlots=false;
  // Load structured requirements + groups + types
  $reqs = [];
  if ($reqTableExists && $editPackage) {
    $rs = $conn->query('SELECT yc.*, l.TEN_LOAI, n.TEN_NHOM FROM goi_trang_phuc_yeu_cau yc '
      .'LEFT JOIN trang_phuc_loai l ON l.ID_LOAI=yc.LOAI_ID '
      .'LEFT JOIN trang_phuc_nhom n ON n.ID_NHOM=yc.ID_NHOM '
      .'WHERE yc.ID_GOI='.(int)$editPackage['ID_GOI'].' ORDER BY yc.CREATED_AT DESC');
    if ($rs) { while($r=$rs->fetch_assoc()){ $reqs[]=$r; } }
  }
  // Existing mapping data (requirement -> costumes per branch)
  $existingMappingsByReq = [];
  if ($editPackage && $reqTableExists && pkg_table_exists($conn,'goi_yc_branch_trang_phuc')) {
    $mapRes = $conn->query('SELECT m.ID_YC, m.ID_CN, m.ID_TRANG_PHUC, m.PRICE_ADJUSTMENT, m.ACTIVE FROM goi_yc_branch_trang_phuc m JOIN goi_trang_phuc_yeu_cau yc ON yc.ID_YC=m.ID_YC WHERE yc.ID_GOI='.(int)$editPackage['ID_GOI']);
    if ($mapRes) {
      while($row=$mapRes->fetch_assoc()) {
        $rid = (int)$row['ID_YC']; $tpId = (int)$row['ID_TRANG_PHUC'];
        if (!isset($existingMappingsByReq[$rid])) $existingMappingsByReq[$rid] = [];
        $existingMappingsByReq[$rid][$tpId] = $row;
      }
    }
  }
  $nhoms = [];
  if ($groupTableExists) {
    $rn = $conn->query("SELECT ID_NHOM, TEN_NHOM FROM trang_phuc_nhom WHERE TRANG_THAI='active' ORDER BY TEN_NHOM");
    if ($rn) { while($r=$rn->fetch_assoc()){ $nhoms[]=$r; } }
  }
  $loais = [];
  if ($loaiTableExists) {
    $rl = $conn->query("SELECT ID_LOAI, TEN_LOAI FROM trang_phuc_loai WHERE TRANG_THAI='active' ORDER BY TEN_LOAI");
    if ($rl) { while($r=$rl->fetch_assoc()){ $loais[]=$r; } }
  }
  // Load promotions
  $promotions = [];
  $promotionsTableExists = pkg_table_exists($conn,'goi_dich_vu_khuyen_mai');
  $canEditPromotions = false;
  if ($editPackage) {
    $isGlobalPkg = ($editPackage['SCOPE_TYPE'] ?? '') === 'global';
    $isLocalOwnedPkg = ($editPackage['SCOPE_TYPE'] ?? '') === 'local' && (int)($editPackage['ID_CN_OWNER'] ?? 0) === $branchId;
    $canEditPromotions = $isBranchManager ? (!$isGlobalPkg && $isLocalOwnedPkg) : true;
  }
  if ($promotionsTableExists && $editPackage) {
    $promotions = $promoRepo->listPromotions((int)$editPackage['ID_GOI']);
  }
  // Mapping data (slot system removed); will use requirement mapping below
  // Price history inside package edit
  $priceHistory = [];
  $historyService = null;
  // Unified pricing (includes promotions) for current edit view
  $pricingForEdit = $pkgRepo->getPackagePromotionPricing((int)$editPackage['ID_GOI'], 0.0);
  if (isset($_GET['history'])) {
    $historyId = (int)$_GET['history'];
    $historyService = $svcRepo->findById($historyId);
    if ($historyService) { $priceHistory = $svcRepo->getPriceHistory($historyId,50); }
  }
} else { $allServices = []; }

// Reorder services (drag & drop submission)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reorder_services'])) {
  $idGoi = (int)$_POST['ID_GOI'];
  $orderPayload = trim($_POST['order_payload'] ?? '');
  if ($idGoi && $orderPayload !== '') {
    $ids = array_filter(array_map('intval', explode(',', $orderPayload)));
    if ($pkgRepo->reorderServices($idGoi, $ids)) {
      $successMessage = 'Đã cập nhật thứ tự dịch vụ.';
      record_system_log($conn,'PACKAGE_REORDER','package:'.$idGoi,null,['order'=>$ids]);
      // Refresh list after reorder
      if ($isEditing) { $editServices = $pkgRepo->listServices($idGoi); }
    } else { $errorMessage = 'Không cập nhật được thứ tự.'; }
  }
}
?>
<body class="bg-gray-50 p-6">
  <div class="max-w-7xl mx-auto">
    <!-- Breadcrumb -->
    <nav class="mb-4 text-sm">
      <span class="text-gray-500">Admin</span>
      <span class="mx-2 text-gray-400">›</span>
      <?php if($isEditing): ?>
        <a href="?page=packages" class="text-indigo-600 hover:underline">Quản lý gói dịch vụ</a>
        <span class="mx-2 text-gray-400">›</span>
        <span class="text-gray-700 font-medium"><?= htmlspecialchars($editPackage['TEN_GOI'] ?? 'Chỉnh sửa') ?></span>
      <?php else: ?>
        <span class="text-gray-700 font-medium">Quản lý gói dịch vụ</span>
      <?php endif; ?>
    </nav>

    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold text-indigo-700">
        <?php if($isEditing): ?>
          <a href="?page=packages" class="text-gray-400 hover:text-indigo-600 mr-2" title="Quay lại danh sách">‹</a>
        <?php endif; ?>
        Quản lý gói dịch vụ
      </h1>
    </div>

    <!-- Alerts (compact) -->
    <?php if(isset($successMessage)): ?>
      <div class="success-alert bg-green-50 border border-green-400 text-green-700 px-3 py-2 rounded mb-3 flex items-center gap-2 text-sm" role="alert">
        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        <span class="flex-1 leading-tight"><?= $successMessage ?></span>
        <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800 font-semibold text-base">×</button>
      </div>
    <?php endif; ?>
    <?php if(isset($_GET['created']) && $_GET['created']==='1'): ?>
      <div class="bg-blue-50 border border-blue-400 text-blue-700 px-3 py-2 rounded mb-3 flex items-start gap-2 text-sm" role="alert">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
        <div class="flex-1 leading-tight">
          <strong>Đã tạo gói!</strong>
          <p class="mt-0.5">Thêm dịch vụ ở tab "Dịch vụ" bên dưới.</p>
        </div>
        <button onclick="this.parentElement.remove()" class="text-blue-600 hover:text-blue-800 font-semibold text-base">×</button>
      </div>
    <?php endif; ?>
    <?php if(isset($errorMessage)): ?>
      <div class="bg-red-50 border border-red-400 text-red-700 px-3 py-2 rounded mb-3 flex items-start gap-2 text-sm" role="alert">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293z" clip-rule="evenodd"/></svg>
        <div class="flex-1 leading-tight"><?= $errorMessage ?></div>
        <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800 font-semibold text-base">×</button>
      </div>
    <?php endif; ?>

    <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
      <form method="GET" class="flex items-center gap-2" data-action="filter_packages">
        <input type="hidden" name="page" value="packages" />
        <input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 Tìm tên/mô tả" class="p-2 border rounded w-64" data-action="search_packages" />
        <button class="bg-indigo-600 text-white px-4 py-2 rounded">Tìm</button>
      </form>
      <div class="flex gap-2">
        <a href="?page=packages&add=true" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition">Thêm gói</a>
        <button type="button" onclick="toggleModal('createYCGlobalModal')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition">Tạo yêu cầu trang phục</button>
        <button type="button" onclick="document.getElementById('createKMGlobalModal')?.classList.remove('hidden')" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded transition" style="color: #ffffff; background-color: #9333ea;">Tạo khuyến mãi</button>
      </div>
    </div>

    <?php if(isset($_GET['add']) && $_GET['add']==='true' && !$isEditing): ?>
      <form method="POST" action="admin_dashboard.php?page=packages&add=true" enctype="multipart/form-data" class="bg-white rounded-lg shadow-lg mb-6" data-action="add_package">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(pkg_csrf_token()) ?>" />
        <input type="hidden" name="add_package_hidden" value="1" />
        
        <!-- Form Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white p-6 rounded-t-lg">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-2xl font-bold mb-1">Tạo gói dịch vụ mới</h2>
              <p class="text-indigo-100 text-sm">Điền thông tin cơ bản để tạo gói, sau đó thêm dịch vụ và trang phục</p>
            </div>
            <a href="?page=packages" class="text-white hover:text-indigo-200 text-3xl leading-none" title="Đóng">&times;</a>
          </div>
        </div>

        <!-- Progress Steps -->
        <div class="px-6 py-4 bg-gray-50 border-b">
          <div class="flex items-center justify-center gap-3 text-sm">
            <div class="flex items-center gap-2">
              <span class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold">1</span>
              <span class="font-medium text-indigo-600">Thông tin cơ bản</span>
            </div>
            <span class="text-gray-300">→</span>
            <div class="flex items-center gap-2 opacity-50">
              <span class="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold">2</span>
              <span class="text-gray-500">Thêm dịch vụ</span>
            </div>
            <span class="text-gray-300">→</span>
            <div class="flex items-center gap-2 opacity-50">
              <span class="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold">3</span>
              <span class="text-gray-500">Cấu hình trang phục</span>
            </div>
          </div>
        </div>

        <!-- Form Body -->
        <div class="p-6">
          <div class="space-y-6">
            <!-- Thông tin chính -->
            <div class="border-b pb-4">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Thông tin chính</h3>
              <div class="grid md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Tên gói dịch vụ <span class="text-red-500">*</span>
                  </label>
                  <input 
                    type="text" 
                    name="TEN_GOI" 
                    placeholder="Ví dụ: Gói chụp ảnh cưới trọn gói" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" 
                    required 
                    minlength="5"
                  />
                  <p class="text-xs text-gray-500 mt-1">Tên ngắn gọn, dễ hiểu, từ 5-150 ký tự</p>
                </div>
                
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Mô tả chi tiết <span class="text-red-500">*</span>
                  </label>
                  <textarea 
                    name="MO_TA" 
                    rows="4" 
                    placeholder="Mô tả đầy đủ về gói: bao gồm dịch vụ gì, phù hợp cho đối tượng nào, ưu điểm nổi bật..." 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" 
                    required
                    minlength="20"
                  ></textarea>
                  <p class="text-xs text-gray-500 mt-1">Mô tả từ 20-500 ký tự giúp khách hàng hiểu rõ gói dịch vụ</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Hình ảnh đại diện</label>
                  <input 
                    type="file" 
                    name="HINH_ANH" 
                    accept="image/jpeg,image/png,image/webp" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    onchange="previewImage(this)"
                  />
                  <p class="text-xs text-gray-500 mt-1">JPG, PNG hoặc WebP, tối đa 2MB</p>
                  <div id="imagePreview" class="mt-2 hidden">
                    <img id="previewImg" class="h-32 rounded border" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Thời gian hiệu lực -->
            <div class="border-b pb-4">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Thời gian hiệu lực</h3>
              <div class="grid md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Có hiệu lực từ ngày</label>
                  <input 
                    type="datetime-local" 
                    name="HIEU_LUC_TU" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" 
                    value="<?= date('Y-m-d\TH:i') ?>" 
                  />
                  <p class="text-xs text-gray-500 mt-1">Từ thời điểm nào gói bắt đầu có thể sử dụng</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Đến hết ngày</label>
                  <input 
                    type="datetime-local" 
                    name="HIEU_LUC_DEN" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" 
                    value="<?= date('Y-m-d\TH:i', strtotime('+1 year')) ?>" 
                  />
                  <p class="text-xs text-gray-500 mt-1">Hết hiệu lực khi nào (mặc định 1 năm)</p>
                </div>
              </div>
            </div>

            <!-- Phạm vi áp dụng -->
            <?php if(!$isBranchManager): ?>
            <div>
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Phạm vi áp dụng</h3>
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Loại gói</label>
                  <select 
                    name="SCOPE_TYPE" 
                    id="scopeTypeSelect"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                    onchange="toggleBranchSelector()"
                  >
                    <option value="global">Toàn hệ thống (Global) - Áp dụng cho tất cả chi nhánh</option>
                    <option value="local">Chi nhánh cụ thể (Local) - Chỉ cho một chi nhánh</option>
                  </select>
                  <p class="text-xs text-gray-500 mt-1">Global: dùng chung, Local: riêng từng chi nhánh</p>
                </div>
                
                <div id="branchSelectorDiv" class="hidden">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Chi nhánh sở hữu <span class="text-red-500">*</span>
                  </label>
                  <select 
                    name="ID_CN_OWNER" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                  >
                    <option value="">-- Chọn chi nhánh --</option>
                    <?php foreach($branches as $br): ?>
                      <option value="<?= $br['ID_CN'] ?>"><?= htmlspecialchars($br['TEN_CN']) ?> (ID: <?= $br['ID_CN'] ?>)</option>
                    <?php endforeach; ?>
                  </select>
                  <p class="text-xs text-gray-500 mt-1">Gói local chỉ quản lý bởi chi nhánh này</p>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                  <p class="text-sm text-blue-800">
                    <strong>Lưu ý:</strong> Gói toàn hệ thống có thể được mọi chi nhánh sử dụng. 
                    Gói chi nhánh chỉ hiển thị và quản lý bởi chi nhánh sở hữu.
                  </p>
                </div>
              </div>
            </div>
            <?php else: ?>
              <input type="hidden" name="SCOPE_TYPE" value="local" />
              <input type="hidden" name="ID_CN_OWNER" value="<?= $branchId ?>" />
              <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <p class="text-sm text-gray-700">
                  Gói sẽ được tạo cho chi nhánh của bạn (ID: <?= $branchId ?>)
                </p>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Form Footer -->
        <div class="bg-gray-50 px-6 py-4 rounded-b-lg border-t flex items-center justify-between">
          <p class="text-sm text-gray-600">
            Sau khi tạo, bạn sẽ chuyển đến trang chỉnh sửa để thêm dịch vụ và cấu hình trang phục
          </p>
          <div class="flex gap-3">
            <a href="?page=packages" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">Hủy bỏ</a>
            <button 
              type="submit" 
              name="add_package"
              id="submitPackageBtn"
              class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Tạo gói dịch vụ
            </button>
          </div>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <?php if($isEditing && $editPackage): ?>

      <!-- Sub-tabs for edit view -->
      <div class="flex items-center gap-2 mb-4 bg-white p-2 rounded-lg shadow-sm sticky top-0 z-10">
        <a href="?page=packages&edit=<?= $editPackage['ID_GOI'] ?>&tab=info" class="px-3 py-1 rounded <?= $activeTab==='info'?'bg-indigo-600 text-white':'bg-indigo-50 text-indigo-700' ?>">Thông tin</a>
        <a href="?page=packages&edit=<?= $editPackage['ID_GOI'] ?>&tab=services" class="px-3 py-1 rounded <?= $activeTab==='services'?'bg-indigo-600 text-white':'bg-indigo-50 text-indigo-700' ?>">Dịch vụ</a>
        <a href="?page=packages&edit=<?= $editPackage['ID_GOI'] ?>&tab=slots" class="px-3 py-1 rounded <?= $activeTab==='slots'?'bg-indigo-600 text-white':'bg-indigo-50 text-indigo-700' ?>">Yêu cầu trang phục</a>
        <a href="?page=packages&edit=<?= $editPackage['ID_GOI'] ?>&tab=promotions" class="px-3 py-1 rounded <?= $activeTab==='promotions'?'bg-indigo-600 text-white':'bg-indigo-50 text-indigo-700' ?>">Khuyến mãi</a>
      </div>

      <!-- Info Tab -->
      <?php if($activeTab==='info'): ?>
      <form method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-lg mb-6" data-action="edit_package" data-package-id="<?= $editPackage['ID_GOI'] ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(pkg_csrf_token()) ?>" />
        
        <!-- Form Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white p-6 rounded-t-lg">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-2xl font-bold mb-1">Cập nhật gói dịch vụ</h2>
              <p class="text-indigo-100 text-sm">Chỉnh sửa thông tin cơ bản của gói</p>
            </div>
            <a href="?page=packages" class="text-white hover:text-indigo-200 text-3xl leading-none" title="Đóng">&times;</a>
          </div>
        </div>

        <!-- Form Body -->
        <div class="p-6">
          <input type="hidden" name="ID_GOI" value="<?= $editPackage['ID_GOI'] ?>" />
          <div class="space-y-6">
            <!-- Thông tin chính -->
            <div class="border-b pb-4">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Thông tin chính</h3>
              <div class="grid md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Tên gói dịch vụ <span class="text-red-500">*</span>
                  </label>
                  <input 
                    type="text" 
                    name="TEN_GOI" 
                    value="<?= htmlspecialchars($editPackage['TEN_GOI']) ?>" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" 
                    required 
                    minlength="5"
                  />
                </div>
                
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Mô tả chi tiết <span class="text-red-500">*</span>
                  </label>
                  <textarea 
                    name="MO_TA" 
                    rows="4" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" 
                    required
                    minlength="20"
                  ><?= htmlspecialchars($editPackage['MO_TA']) ?></textarea>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Hình ảnh đại diện</label>
                  <input 
                    type="file" 
                    name="HINH_ANH" 
                    accept="image/jpeg,image/png,image/webp" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    onchange="previewImage(this)"
                  />
                  <p class="text-xs text-gray-500 mt-1">JPG, PNG hoặc WebP</p>
                  <div id="imagePreview" class="mt-2 hidden">
                    <img id="previewImg" class="h-32 rounded border" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Thời gian hiệu lực -->
            <div class="border-b pb-4">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Thời gian hiệu lực</h3>
              <div class="grid md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Có hiệu lực từ ngày</label>
                  <input 
                    type="datetime-local" 
                    name="HIEU_LUC_TU" 
                    value="<?= $editPackage['HIEU_LUC_TU'] ? date('Y-m-d\TH:i', strtotime($editPackage['HIEU_LUC_TU'])) : '' ?>" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" 
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Đến hết ngày</label>
                  <input 
                    type="datetime-local" 
                    name="HIEU_LUC_DEN" 
                    value="<?= $editPackage['HIEU_LUC_DEN'] ? date('Y-m-d\TH:i', strtotime($editPackage['HIEU_LUC_DEN'])) : '' ?>" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" 
                  />
                </div>
              </div>
            </div>

            <!-- Phạm vi áp dụng -->
            <?php if(!$isBranchManager): ?>
            <div>
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Phạm vi áp dụng</h3>
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Loại gói</label>
                  <select 
                    name="SCOPE_TYPE" 
                    id="scopeTypeSelectEdit"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                    onchange="toggleBranchSelector()"
                  >
                    <option value="global" <?= ($editPackage['SCOPE_TYPE'] ?? '')==='global'?'selected':''; ?>>Toàn hệ thống (Global) - Áp dụng cho tất cả chi nhánh</option>
                    <option value="local" <?= ($editPackage['SCOPE_TYPE'] ?? '')==='local'?'selected':''; ?>>Chi nhánh cụ thể (Local) - Chỉ cho một chi nhánh</option>
                  </select>
                </div>
                
                <div id="branchSelectorDivEdit" class="<?= ($editPackage['SCOPE_TYPE'] ?? '')==='local'?'':'hidden'; ?>">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Chi nhánh sở hữu <span class="text-red-500">*</span>
                  </label>
                  <select 
                    name="ID_CN_OWNER" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                  >
                    <option value="">-- Chọn chi nhánh --</option>
                    <?php foreach($branches as $br): ?>
                      <option value="<?= $br['ID_CN'] ?>" <?= ($editPackage['ID_CN_OWNER'] ?? '')==$br['ID_CN']?'selected':''; ?>><?= htmlspecialchars($br['TEN_CN']) ?> (ID: <?= $br['ID_CN'] ?>)</option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                  <p class="text-sm text-blue-800">
                    <strong>Lưu ý:</strong> Gói toàn hệ thống có thể được mọi chi nhánh sử dụng. 
                    Gói chi nhánh chỉ hiển thị và quản lý bởi chi nhánh sở hữu.
                  </p>
                </div>
              </div>
            </div>
            <?php else: ?>
              <input type="hidden" name="ID_CN_OWNER" value="<?= $branchId ?>" />
              <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <p class="text-sm text-gray-700">
                  Gói sẽ được cập nhật cho chi nhánh của bạn (ID: <?= $branchId ?>)
                </p>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Hình ảnh hiện tại -->
        <?php if($editPackage['HINH_ANH']): ?>
          <?php 
            // Build absolute URL for images when app is in subfolder (e.g., /StygianBlue)
            $imgRel = (string)$editPackage['HINH_ANH'];
            $script = $_SERVER['SCRIPT_NAME'] ?? '';
            $parts = array_values(array_filter(explode('/', $script)));
            $subfolder = isset($parts[0]) ? '/' . $parts[0] : ''; // e.g., '/StygianBlue'
            $imgSrc = (str_starts_with($imgRel, '/')) ? $imgRel : ($subfolder . '/' . ltrim($imgRel,'/'));
          ?>
          <div class="px-6 py-4 border-t bg-gray-50">
            <p class="text-sm font-medium text-gray-700 mb-3">Hình ảnh hiện tại</p>
            <img src="<?= htmlspecialchars($imgSrc) ?>" class="w-32 h-32 object-cover rounded-lg border border-gray-300" />
          </div>
        <?php endif; ?>

        <!-- Form Footer -->
        <div class="bg-gray-50 px-6 py-4 rounded-b-lg border-t flex items-center justify-between">
          <p class="text-sm text-gray-600">
            ID gói: <?= $editPackage['ID_GOI'] ?>
          </p>
          <div class="flex gap-3">
            <a href="?page=packages" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">Hủy bỏ</a>
            <button 
              type="submit" 
              name="edit_package"
              class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Cập nhật gói
            </button>
          </div>
        </div>
      </form>
      <?php endif; ?>

      <?php if($activeTab==='services'): ?>
      <!-- Dual pane drag & drop -->
      <div class="grid md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white p-4 rounded shadow">
          <h3 class="text-lg font-semibold text-indigo-700 mb-4">Dịch vụ khả dụng</h3>
          <div class="h-72 overflow-y-auto border rounded" id="availableServices">
            <?php foreach($allServices as $svc): ?>
              <div class="flex items-center justify-between px-3 py-2 text-xs border-b bg-gray-50 hover:bg-gray-100 cursor-grab service-item"
                   draggable="true"
                   data-id-dv="<?= $svc['ID_DV'] ?>"
                   data-ten-dv="<?= htmlspecialchars($svc['TEN_DV']) ?>"
                   data-thoi-gian="<?= (int)$svc['THOI_GIAN'] ?>"
                   data-gia="<?= (int)($svc['DON_GIA'] ?? 0) ?>"
                   data-existing="<?= in_array($svc['ID_DV'],$existingIds)?'1':'0' ?>">
                <span class="truncate w-40" title="<?= htmlspecialchars($svc['TEN_DV']) ?>"><?= htmlspecialchars($svc['TEN_DV']) ?></span>
                <span class="text-[10px] text-gray-500">
                  <?= isset($svc['DON_GIA'])?number_format($svc['DON_GIA'],0,',','.'):'-' ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="text-[11px] text-gray-500 mt-2">Kéo sang phải để thêm.</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
          <h3 class="text-lg font-semibold text-indigo-700 mb-4">Dịch vụ trong gói</h3>
          <div class="h-72 overflow-y-auto border rounded" id="packageDropZone">
            <table class="min-w-full text-xs">
              <thead class="bg-indigo-100 text-indigo-700 sticky top-0">
                <tr>
                  <th class="p-2">Tên</th>
                  <th class="p-2">SL</th>
                  <th class="p-2">Giá override</th>
                  <th class="p-2">Thứ tự</th>
                  <th class="p-2">Thao tác</th>
                </tr>
              </thead>
              <tbody id="packageServicesBody">
                <?php foreach($editServices as $row): ?>
                  <tr class="border-b" data-id-dv="<?= $row['ID_DV'] ?>" draggable="true" data-thoi-gian="<?= (int)$row['THOI_GIAN'] ?>" data-gia-base="<?= (int)($row['DON_GIA'] ?? $row['DON_GIA_AP_DUNG'] ?? 0) ?>">
                    <td class="p-2 truncate" title="<?= htmlspecialchars($row['TEN_DV']) ?>"><?= htmlspecialchars($row['TEN_DV']) ?></td>
                    <td class="p-2"><input type="number" min="1" value="<?= $row['SO_LUONG'] ?>" class="w-14 px-1 py-0.5 border rounded qty-input" /></td>
                    <td class="p-2"><input type="number" min="0" value="<?= $row['DON_GIA_AP_DUNG'] ?? '' ?>" placeholder="Mặc định" class="w-20 px-1 py-0.5 border rounded price-input" /></td>
                    <td class="p-2 cursor-move sort-handle"></td>
                    <td class="p-2 flex gap-2">
                      <a class="text-indigo-600" href="?page=packages&edit=<?= $editPackage['ID_GOI'] ?>&history=<?= $row['ID_DV'] ?>">Giá</a>
                      <button type="button" class="text-red-600 remove-btn">Xóa</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php if(empty($editServices)): ?><div class="p-3 text-gray-500 text-xs">Chưa có dịch vụ.</div><?php endif; ?>
          </div>
          <!-- Dynamic summary bar -->
          <div id="packageSummaryBar" class="mt-3 p-3 rounded border bg-gray-50 text-[12px] grid grid-cols-2 md:grid-cols-5 gap-2">
            <div><span class="font-semibold">Tổng dịch vụ:</span> <span id="sumCount">0</span></div>
            <div><span class="font-semibold">Tổng thời lượng:</span> <span id="sumDuration">0 phút</span></div>
            <div><span class="font-semibold">Giá áp dụng:</span> <span id="sumApplied">0</span></div>
            <div><span class="font-semibold">Giảm KM:</span> <span id="sumPromo">-<?= number_format((int)$pricingForEdit['discount'],0,',','.') ?></span></div>
            <div><span class="font-semibold">Giá sau KM:</span> <span id="sumFinal" class="text-green-700 font-semibold"><?= number_format((int)$pricingForEdit['subtotal'],0,',','.') ?></span></div>
          </div>
          <div id="promoMeta" data-pricing='<?= json_encode($pricingForEdit, JSON_UNESCAPED_UNICODE) ?>' class="hidden"></div>
          <form method="POST" class="mt-3 flex items-center gap-2">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(pkg_csrf_token()) ?>" />
            <input type="hidden" name="ID_GOI" value="<?= $editPackage['ID_GOI'] ?>" />
            <input type="hidden" name="bulk_payload" id="bulkPayload" />
            <button type="submit" name="bulk_update_services" id="bulkSaveBtn" class="bg-green-600 text-white px-4 py-2 rounded disabled:opacity-40" disabled>Lưu thay đổi</button>
            <span class="text-[11px] text-gray-500" id="bulkSummary"></span>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <?php if($activeTab==='slots'): ?>
      <!-- Dual-pane UI for Requirements -->
      <?php include __DIR__ . '/manage_packages_requirements_ui.php'; ?>
      <?php endif; ?>

      <?php if($activeTab==='promotions'): ?>
      <!-- Dual-pane UI for Promotions -->
      <?php include __DIR__ . '/manage_packages_promotions_ui.php'; ?>
      <?php endif; ?>

    <?php endif; ?>

    <!-- Quick stats -->
    <?php if(!$isEditing): ?>
    <div class="grid md:grid-cols-4 gap-4 mb-6">
      <div class="bg-blue-600 text-white p-4 rounded-lg shadow">
        <div class="text-2xl font-bold"><?= count(array_filter($packages, fn($p)=>$p['TRANG_THAI']==='ban')) ?></div>
        <div class="text-sm font-medium">Đang bán</div>
      </div>
      <div class="bg-yellow-600 text-white p-4 rounded-lg shadow">
        <div class="text-2xl font-bold"><?= count(array_filter($packages, fn($p)=>$p['TRANG_THAI']==='nhap')) ?></div>
        <div class="text-sm font-medium">Nháp</div>
      </div>
      <div class="bg-green-600 text-white p-4 rounded-lg shadow">
        <div class="text-2xl font-bold"><?= count(array_filter($packages, fn($p)=>!empty($p['ID_KM']))) ?></div>
        <div class="text-sm font-medium">Có khuyến mãi</div>
      </div>
      <div class="bg-purple-600 text-white p-4 rounded-lg shadow" style="color: #ffffff; background-color: #9333ea;">
        <div class="text-2xl font-bold" style="color: #ffffff;"><?= count($packages) ?></div>
        <div class="text-sm font-medium" style="color: #ffffff;">Tổng gói</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Package list -->
    <?php if(!$isEditing): ?>
    <div class="overflow-x-auto bg-white rounded shadow">
      <table class="min-w-full text-sm">
        <thead class="bg-indigo-100 text-indigo-700">
          <tr>
            <th class="p-3">ID</th>
            <th class="p-3">Tên gói</th>
            <th class="p-3">Mô tả</th>
            <th class="p-3">Hiệu lực</th>
            <th class="p-3">Trạng thái</th>
            <th class="p-3">Tổng giá</th>
            <th class="p-3">Scope</th>
            <th class="p-3">Hành động</th>
          </tr>
        </thead>
        <tbody class="text-center divide-y">
          <?php foreach($packages as $pkg): ?>
            <tr class="hover:bg-gray-50">
              <td class="p-3"><?= $pkg['ID_GOI'] ?></td>
              <td class="p-3"><?= htmlspecialchars($pkg['TEN_GOI']) ?></td>
              <td class="p-3"><?= mb_strimwidth($pkg['MO_TA'],0,40,'...') ?></td>
              <td class="p-3 text-xs">
                <?= $pkg['HIEU_LUC_TU'] ? date('d/m/Y',strtotime($pkg['HIEU_LUC_TU'])) : '—' ?> →
                <?= $pkg['HIEU_LUC_DEN'] ? date('d/m/Y',strtotime($pkg['HIEU_LUC_DEN'])) : '—' ?>
              </td>
              <td class="p-3">
                <?php $st=$pkg['TRANG_THAI']; ?>
                <?php 
                  $stDisplay = match($st) {
                    'ban' => 'Hoạt động',
                    'nhap' => 'Chỉnh sửa',
                    'ngung' => 'Tạm ngừng',
                    default => $st
                  };
                  $stClass = match($st) {
                    'ban' => 'bg-green-100 text-green-800',
                    'nhap' => 'bg-yellow-100 text-yellow-800',
                    'ngung' => 'bg-gray-300 text-gray-700',
                    default => 'bg-gray-300 text-gray-700'
                  };
                ?>
                <span class="px-2 py-1 rounded text-xs font-semibold <?= $stClass ?>"><?= htmlspecialchars($stDisplay) ?></span>
              </td>
              <td class="p-3">
                <?php 
                  $giaGoc = $pkg['TONG_GIA_GOI'] ?? 0;
                  $giaSauGiam = $pkg['GIA_SAU_GIAM'] ?? null;
                  $soTienGiam = $pkg['SO_TIEN_GIAM'] ?? null;
                  $tenKM = $pkg['TEN_CHUONG_TRINH'] ?? null;
                ?>
                <?php if ($giaSauGiam && $soTienGiam > 0): ?>
                  <div class="text-xs">
                    <span class="line-through text-gray-400"><?= number_format($giaGoc,0,',','.') ?></span>
                    <span class="block font-semibold text-green-600"><?= number_format($giaSauGiam,0,',','.') ?></span>
                    <span class="text-[10px] text-green-600" title="<?= htmlspecialchars($tenKM) ?>">-<?= number_format($soTienGiam,0,',','.') ?></span>
                  </div>
                <?php else: ?>
                  <?= $giaGoc ? number_format($giaGoc,0,',','.') : '—' ?>
                <?php endif; ?>
              </td>
              <td class="p-3 text-xs">
                <?php 
                  $scopeType = $pkg['SCOPE_TYPE'] ?? 'global';
                  $scopeBadge = $scopeType === 'global' 
                    ? '<span class="px-2 py-1 rounded-full text-[11px] font-semibold bg-indigo-100 text-indigo-700 border border-indigo-200">🌍 Global</span>'
                    : '<span class="px-2 py-1 rounded-full text-[11px] font-semibold bg-cyan-100 text-cyan-700 border border-cyan-200">CN#'.(int)($pkg['ID_CN_OWNER'] ?? 0).'</span>';
                  echo $scopeBadge;
                ?>
              </td>
              <td class="p-3">
                <div class="relative inline-block">
                  <button class="menu-btn bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm font-semibold" data-pkg="<?= $pkg['ID_GOI'] ?>" style="color: #ffffff; background-color: #4b5563;">⋯</button>
                  <div class="menu-dropdown absolute right-0 mt-1 w-48 bg-white border border-gray-200 rounded shadow-lg hidden z-20">
                    <?php 
                      // Determine if manager can edit/delete this package
                      $canEditDeletePackage = !$isBranchManager || (
                        ($pkg['SCOPE_TYPE'] ?? '') === 'local' && 
                        (int)($pkg['ID_CN_OWNER'] ?? 0) === $branchId
                      );
                    ?>
                    <a href="?page=packages&edit=<?= $pkg['ID_GOI'] ?>" class="block px-4 py-2 text-left text-gray-700 hover:bg-yellow-50 border-b" title="<?= !$canEditDeletePackage ? 'Bạn không có quyền sửa gói này' : '' ?>" <?= !$canEditDeletePackage ? 'style="opacity:0.5; cursor:not-allowed;" onclick="return false;"' : '' ?>>Chỉnh sửa</a>
                    <?php if($st!=='ban'): ?>
                      <?php
                        $publishDisabled = false; $publishTitle = '';
                        if (!$canEditDeletePackage) {
                          $publishDisabled = true;
                          $publishTitle = 'Bạn không có quyền đổi trạng thái gói này';
                        } elseif (($pkg['SCOPE_TYPE'] ?? '')==='local' && pkg_table_exists($conn,'goi_trang_phuc_yeu_cau') && pkg_table_exists($conn,'goi_yc_branch_trang_phuc')) {
                          $ownerCn = (int)($pkg['ID_CN_OWNER'] ?? 0);
                          if ($ownerCn>0) {
                            $qMiss = $conn->query('SELECT yc.ID_YC FROM goi_trang_phuc_yeu_cau yc LEFT JOIN goi_yc_branch_trang_phuc m ON m.ID_YC=yc.ID_YC AND m.ID_CN='.(int)$ownerCn.' AND m.ACTIVE=1 WHERE yc.ID_GOI='.(int)$pkg['ID_GOI'].' AND yc.BAT_BUOC=1 GROUP BY yc.ID_YC, yc.SO_LUONG HAVING COUNT(m.ID_TRANG_PHUC) < yc.SO_LUONG');
                            $miss = $qMiss ? $qMiss->num_rows : 0;
                            if ($miss>0) { $publishDisabled = true; $publishTitle = 'Thiếu '.$miss.' yêu cầu chuẩn hóa bắt buộc tại chi nhánh #'.$ownerCn; }
                          }
                        }
                      ?>
                      <form method="POST" class="contents">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(pkg_csrf_token()) ?>" />
                        <input type="hidden" name="ID_GOI" value="<?= $pkg['ID_GOI'] ?>" />
                        <input type="hidden" name="STATUS" value="ban" />
                        <button type="submit" name="change_status" class="block w-full text-left px-4 py-2 text-gray-700 hover:bg-blue-50 border-b disabled:opacity-50 disabled:cursor-not-allowed" title="<?= htmlspecialchars($publishTitle) ?>" <?= $publishDisabled?'disabled':''; ?>>Xuất bản</button>
                      </form>
                      <?php if(($pkg['SCOPE_TYPE'] ?? '')==='local' && !empty($publishDisabled)): ?>
                        <a href="?page=packages&edit=<?= $pkg['ID_GOI'] ?>&tab=slots&map_cn=<?= (int)($pkg['ID_CN_OWNER'] ?? 0) ?>" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50 border-b" title="Tới cấu hình yêu cầu trang phục">Thiết lập</a>
                      <?php endif; ?>
                    <?php endif; ?>
                    <?php if($st!=='ngung'): ?>
                      <form method="POST" class="contents" onsubmit="return confirm('Tạm ngừng gói dịch vụ?\n\nGói sẽ không hiển thị cho khách hàng.')">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(pkg_csrf_token()) ?>" />
                        <input type="hidden" name="ID_GOI" value="<?= $pkg['ID_GOI'] ?>" />
                        <input type="hidden" name="STATUS" value="ngung" />
                        <button type="submit" name="change_status" class="block w-full text-left px-4 py-2 text-gray-700 hover:bg-orange-50 border-b" <?= !$canEditDeletePackage ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?> title="<?= !$canEditDeletePackage ? 'Bạn không có quyền đổi trạng thái gói này' : '' ?>">Tạm ngừng</button>
                      </form>
                    <?php endif; ?>
                    <form method="POST" class="contents" onsubmit="<?= !$canEditDeletePackage ? 'alert(\"Bạn không có quyền xóa gói này\"); return false;' : 'return confirm(\"Xóa vĩnh viễn gói này?\\n\\nHành động không thể hoàn tác. Đảm bảo gói không được sử dụng trong lịch hẹn nào.\");' ?>">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars(pkg_csrf_token()) ?>" />
                      <input type="hidden" name="ID_GOI" value="<?= $pkg['ID_GOI'] ?>" />
                      <button type="submit" name="delete_package" class="block w-full text-left px-4 py-2 text-red-700 hover:bg-red-50 font-semibold" <?= !$canEditDeletePackage ? 'style="opacity:0.5; cursor:not-allowed;" onclick="return false;"' : '' ?> title="<?= !$canEditDeletePackage ? 'Bạn không có quyền xóa gói này' : '' ?>">Xóa</button>
                    </form>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if(empty($packages)): ?><tr><td colspan="7" class="p-3 text-gray-500">Không có gói.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if($totalPages>1): ?>
      <div class="mt-6 flex justify-center gap-2">
        <?php for($i=1;$i<=$totalPages;$i++): ?>
          <a href="?page=packages&search=<?= urlencode($search) ?>&p=<?= $i ?>" class="px-3 py-1 rounded border <?= $i==$page? 'bg-indigo-600 text-white':'bg-white hover:bg-gray-100' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
    <?php endif; ?>
        
  </div>
    <?php if(isset($historyService) && $historyService): ?>
    <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" onclick="if(event.target===this) window.location='?page=packages&edit=<?= $editPackage['ID_GOI'] ?>';">
      <div class="bg-white w-full max-w-lg rounded shadow-lg p-6 relative">
        <h2 class="text-xl font-semibold text-indigo-700 mb-4">Lịch sử giá: <?= htmlspecialchars($historyService['TEN_DV']); ?></h2>
        <table class="w-full text-sm mb-4">
          <thead class="bg-indigo-100 text-indigo-700">
            <tr><th class="p-2 text-left">Thời điểm</th><th class="p-2 text-right">Giá (VNĐ)</th></tr>
          </thead>
          <tbody class="divide-y">
            <?php foreach($priceHistory as $row): ?>
              <tr>
                <td class="p-2 text-left text-xs"><?= date('d/m/Y H:i', strtotime($row['NGAY_GIO'])); ?></td>
                <td class="p-2 text-right font-medium"><?= number_format($row['DON_GIA'],0,',','.'); ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($priceHistory)): ?>
              <tr><td colspan="2" class="p-2 text-center text-gray-500">Chưa có lịch sử giá.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        <div class="flex justify-end gap-2">
          <a href="?page=packages&edit=<?= $editPackage['ID_GOI'] ?>" class="px-4 py-2 rounded bg-gray-600 hover:bg-gray-700 text-white">Đóng</a>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <script>
      // Build mapping payloads for slot mapping forms
      (function(){
        const forms = document.querySelectorAll('.slot-map-form');
        forms.forEach(form => {
          form.addEventListener('submit', function(e){
            const payloadEl = form.querySelector('.mapping-payload');
            const items = [];
            const checks = form.querySelectorAll('.map-check');
            checks.forEach(ch => {
              const id = parseInt(ch.getAttribute('data-id-tp'), 10);
              const adjEl = form.querySelector('.map-adj[data-id-tp="'+id+'"]');
              const adjVal = adjEl ? adjEl.value.trim() : '';
              if (ch.checked) {
                items.push({ ID_TRANG_PHUC: id, PRICE_ADJUSTMENT: adjVal!=='' ? parseInt(adjVal, 10) : null, ACTIVE: 1 });
              }
            });
            payloadEl.value = JSON.stringify(items);
          });
        });
      })();
      (function(){
        const avail = document.getElementById('availableServices');
        const body = document.getElementById('packageServicesBody');
        const bulkBtn = document.getElementById('bulkSaveBtn');
        const bulkPayload = document.getElementById('bulkPayload');
        const bulkSummary = document.getElementById('bulkSummary');
        const sumCountEl = document.getElementById('sumCount');
        const sumDurationEl = document.getElementById('sumDuration');
        const sumAppliedEl = document.getElementById('sumApplied');
        const sumPromoEl = document.getElementById('sumPromo');
        const sumFinalEl = document.getElementById('sumFinal');
        const promoMeta = document.getElementById('promoMeta');
        const promoData = promoMeta ? JSON.parse(promoMeta.dataset.pricing || '{}') : null;
        if(!avail || !body) return;
        let dragEl = null;
        function updateOrder(){
          Array.from(body.querySelectorAll('tr')).forEach((tr,i)=>{
            const handle = tr.querySelector('.sort-handle');
            if(handle) handle.textContent = i+1;
          });
        }
        function serialize(){
          const rows = Array.from(body.querySelectorAll('tr'));
          let totalDuration = 0;
          let totalDefault = 0;
          let totalApplied = 0;
          let invalid = false;
          const items = rows.map((tr,i)=>{
            const qtyInput = tr.querySelector('.qty-input');
            const priceInput = tr.querySelector('.price-input');
            const qty = parseInt(qtyInput.value,10) || 0;
            const overrideRaw = priceInput.value.trim();
            const override = overrideRaw === '' ? null : (parseInt(overrideRaw,10)||0);
            const basePrice = parseInt(tr.getAttribute('data-gia-base')||'0',10);
            const duration = parseInt(tr.getAttribute('data-thoi-gian')||'0',10);
            // Validation visuals
            if(qty <= 0){ qtyInput.classList.add('border-red-500'); invalid = true; } else { qtyInput.classList.remove('border-red-500'); }
            if(override !== null && override < 0){ priceInput.classList.add('border-red-500'); invalid = true; } else { priceInput.classList.remove('border-red-500'); }
            totalDuration += duration * Math.max(qty,0);
            totalDefault += basePrice * Math.max(qty,0);
            const appliedPriceUnit = override !== null ? override : basePrice;
            totalApplied += appliedPriceUnit * Math.max(qty,0);
            return {
              ID_DV: tr.getAttribute('data-id-dv'),
              SO_LUONG: qty,
              DON_GIA_AP_DUNG: override !== null ? override : '',
              THU_TU: i+1
            };
          });
          bulkPayload.value = JSON.stringify(items);
          bulkSummary.textContent = items.length+' dịch vụ.';
          if (sumCountEl) sumCountEl.textContent = items.length;
          if (sumDurationEl) sumDurationEl.textContent = totalDuration + ' phút';
          if (sumAppliedEl) sumAppliedEl.textContent = new Intl.NumberFormat('vi-VN').format(totalApplied);

          // Apply promotion discount for final price display
          if (promoData && typeof promoData.discount !== 'undefined') {
            const promoDiscount = parseInt(promoData.discount || 0, 10);
            if (sumPromoEl) sumPromoEl.textContent = '-' + new Intl.NumberFormat('vi-VN').format(promoDiscount);
            const final = Math.max(0, totalApplied - promoDiscount);
            if (sumFinalEl) sumFinalEl.textContent = new Intl.NumberFormat('vi-VN').format(final);
          } else {
            if (sumPromoEl) sumPromoEl.textContent = '0';
            if (sumFinalEl) sumFinalEl.textContent = new Intl.NumberFormat('vi-VN').format(totalApplied);
          }
          bulkBtn.disabled = items.length===0 || invalid;
        }
        avail.addEventListener('dragstart', e=>{
          const el=e.target.closest('.service-item'); if(!el) return; dragEl=el; el.classList.add('opacity-50'); e.dataTransfer.effectAllowed='move';
        });
        avail.addEventListener('dragend', ()=>{ if(dragEl) dragEl.classList.remove('opacity-50'); dragEl=null; });
        const dropZone=document.getElementById('packageDropZone');
        dropZone.addEventListener('dragover', e=>e.preventDefault());
        dropZone.addEventListener('drop', e=>{
          e.preventDefault();
          if(!dragEl) return;
          if(dragEl.dataset.existing==='1'){ dragEl.classList.remove('opacity-50'); return; }
          const id=dragEl.dataset.idDv;
          if(body.querySelector('tr[data-id-dv="'+id+'"]')) { dragEl.classList.remove('opacity-50'); return; }
          const tr=document.createElement('tr'); tr.className='border-b'; tr.setAttribute('data-id-dv',id); tr.setAttribute('draggable','true'); tr.setAttribute('data-thoi-gian',dragEl.dataset.thoiGian||'0'); tr.setAttribute('data-gia-base',dragEl.dataset.gia||'0');
          tr.innerHTML='<td class="p-2 truncate" title="'+dragEl.dataset.tenDv+'">'+dragEl.dataset.tenDv+'</td>'+
            '<td class="p-2"><input type="number" min="1" value="1" class="w-14 px-1 py-0.5 border rounded qty-input" /></td>'+
            '<td class="p-2"><input type="number" min="0" value="" placeholder="Mặc định" class="w-20 px-1 py-0.5 border rounded price-input" /></td>'+
            '<td class="p-2 cursor-move sort-handle"></td>'+
            '<td class="p-2"><button type="button" class="text-red-600 remove-btn">Xóa</button></td>';
          body.appendChild(tr);
          dragEl.classList.remove('opacity-50'); dragEl.dataset.existing='1';
          updateOrder(); serialize();
        });
        body.addEventListener('dragstart', e=>{
          const row=e.target.closest('tr'); if(!row) return; dragEl=row; row.classList.add('opacity-50'); e.dataTransfer.effectAllowed='move';
        });
        body.addEventListener('dragend', ()=>{ if(dragEl){ dragEl.classList.remove('opacity-50'); dragEl=null; updateOrder(); serialize(); } });
        body.addEventListener('dragover', e=>{
          e.preventDefault();
          const target=e.target.closest('tr');
          if(!dragEl||!target||dragEl===target) return;
          const rect=target.getBoundingClientRect();
          const before=(e.clientY-rect.top)<rect.height/2;
          if(before) target.parentNode.insertBefore(dragEl,target); else target.parentNode.insertBefore(dragEl,target.nextSibling);
        });
        body.addEventListener('click', e=>{
          if(e.target.classList.contains('remove-btn')){
            const tr=e.target.closest('tr'); const id=tr.getAttribute('data-id-dv');
            const src=avail.querySelector('.service-item[data-id-dv="'+id+'"]'); if(src) src.dataset.existing='0';
            tr.remove(); updateOrder(); serialize();
          }
        });
        body.addEventListener('input', e=>{
          if(e.target.classList.contains('qty-input') || e.target.classList.contains('price-input')) serialize();
        });
        updateOrder(); serialize();
      })();
      // Costumes drag & order
      (function(){
        const body = document.getElementById('costumeBody');
        const orderInput = document.getElementById('costumeOrderPayload');
        const saveBtn = document.getElementById('saveCostumeOrderBtn');
        const meta = document.getElementById('costumeCountMeta');
        if(!body) return;
        let dragEl=null;
        function updateOrder(){
          const rows=[...body.querySelectorAll('tr')];
          rows.forEach((tr,i)=>{ const h=tr.querySelector('.order-handle'); if(h) h.textContent=i+1; });
          orderInput.value = rows.map(r=>r.getAttribute('data-id-tp')).join(',');
          saveBtn.disabled = rows.length===0;
          meta.textContent = rows.length + ' trang phục';
        }
        body.addEventListener('dragstart',e=>{ const r=e.target.closest('tr'); if(!r) return; dragEl=r; r.classList.add('opacity-50'); e.dataTransfer.effectAllowed='move'; });
        body.addEventListener('dragend',()=>{ if(dragEl){ dragEl.classList.remove('opacity-50'); dragEl=null; updateOrder(); }});
        body.addEventListener('dragover',e=>{ e.preventDefault(); const target=e.target.closest('tr'); if(!dragEl||!target||dragEl===target) return; const rect=target.getBoundingClientRect(); const before=(e.clientY-rect.top)<rect.height/2; if(before) target.parentNode.insertBefore(dragEl,target); else target.parentNode.insertBefore(dragEl,target.nextSibling); });
        updateOrder();
      })();

      // Toggle GIAM_TOI_DA field visibility based on LOAI_GIAM
      (function(){
        const selectEl = document.getElementById('loaiGiamSelect');
        const divEl = document.getElementById('giamToiDaDiv');
        if (!selectEl || !divEl) return;
        function toggle() {
          if (selectEl.value === 'phan_tram') {
            divEl.style.display = 'block';
          } else {
            divEl.style.display = 'none';
          }
        }
        selectEl.addEventListener('change', toggle);
        toggle();
      })();

      // Client-side validation for package form
      // Client-side validation for package form
      function validatePackageForm(form) {
        console.log('validatePackageForm called');
        
        const errors = [];
        const submitBtn = form.querySelector('#submitPackageBtn');
        
        // Remove previous error highlights
        form.querySelectorAll('.border-red-500').forEach(el => {
          el.classList.remove('border-red-500');
          el.classList.add('border-gray-300');
        });
        
        const tenGoi = form.querySelector('[name="TEN_GOI"]');
        const moTa = form.querySelector('[name="MO_TA"]');
        const scopeType = form.querySelector('[name="SCOPE_TYPE"]');
        const idCnOwner = form.querySelector('[name="ID_CN_OWNER"]');
        const hieuLucTu = form.querySelector('[name="HIEU_LUC_TU"]');
        const hieuLucDen = form.querySelector('[name="HIEU_LUC_DEN"]');
        
        // Kiểm tra tên gói
        if (!tenGoi || !tenGoi.value.trim()) {
          errors.push({field: tenGoi, message: 'Tên gói là bắt buộc'});
        } else if (tenGoi.value.trim().length < 5) {
          errors.push({field: tenGoi, message: 'Tên gói phải có ít nhất 5 ký tự (hiện tại: ' + tenGoi.value.trim().length + ')'});
        } else if (tenGoi.value.trim().length > 150) {
          errors.push({field: tenGoi, message: 'Tên gói không quá 150 ký tự (hiện tại: ' + tenGoi.value.trim().length + ')'});
        }
        
        // Kiểm tra mô tả
        if (!moTa || !moTa.value.trim()) {
          errors.push({field: moTa, message: 'Mô tả là bắt buộc'});
        } else if (moTa.value.trim().length < 20) {
          errors.push({field: moTa, message: 'Mô tả phải có ít nhất 20 ký tự (hiện tại: ' + moTa.value.trim().length + ')'});
        } else if (moTa.value.trim().length > 500) {
          errors.push({field: moTa, message: 'Mô tả không quá 500 ký tự (hiện tại: ' + moTa.value.trim().length + ')'});
        }
        
        // Kiểm tra chi nhánh nếu là local
        if (scopeType && scopeType.value === 'local') {
          if (idCnOwner && !idCnOwner.value) {
            errors.push({field: idCnOwner, message: 'Vui lòng chọn chi nhánh sở hữu cho gói local'});
          }
        }
        
        // Kiểm tra ngày hiệu lực
        if (hieuLucTu && hieuLucDen && hieuLucTu.value && hieuLucDen.value) {
          const tuDate = new Date(hieuLucTu.value);
          const denDate = new Date(hieuLucDen.value);
          if (tuDate >= denDate) {
            errors.push({field: hieuLucDen, message: 'Ngày kết thúc phải sau ngày bắt đầu'});
          }
        }
        
        if (errors.length > 0) {
          console.log('Validation failed:', errors);
          
          // Highlight các field lỗi
          errors.forEach(err => {
            if (err.field) {
              err.field.classList.remove('border-gray-300');
              err.field.classList.add('border-red-500');
            }
          });
          
          // Hiển thị thông báo lỗi
          let errorMsg = '⚠️ Vui lòng sửa các lỗi sau:\n\n';
          errors.forEach((err, idx) => {
            errorMsg += (idx + 1) + '. ' + err.message + '\n';
          });
          alert(errorMsg);
          
          // Focus vào field lỗi đầu tiên
          if (errors[0].field) {
            errors[0].field.focus();
            errors[0].field.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
          
          return false;
        }
        
        // Validation pass - hiển thị loading state
        console.log('Validation passed, submitting...');
        console.log('Form data:', {
          TEN_GOI: tenGoi.value,
          MO_TA: moTa.value.substring(0, 50) + '...',
          SCOPE_TYPE: scopeType ? scopeType.value : 'N/A',
          ID_CN_OWNER: idCnOwner ? idCnOwner.value : 'N/A'
        });
        
        // Disable button và hiển thị loading
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<span class="inline-block animate-spin mr-2">⏳</span> Đang tạo gói...';
        }
        
        return true;
      }

      // Auto-dismiss success messages after 5 seconds
      (function() {
        const successAlerts = document.querySelectorAll('.success-alert');
        successAlerts.forEach(alert => {
          setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
          }, 5000);
        });
      })();
      
      // Auto-reload page after delete success
      <?php if(isset($showReloadScript) && $showReloadScript): ?>
      (function() {
        setTimeout(() => {
          window.location.href = '?page=packages';
        }, 1500);
      })();
      <?php endif; ?>

      // Smooth scroll to errors
      (function() {
        const errorAlert = document.querySelector('.bg-red-100');
        if (errorAlert) {
          errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      })();

      // Add keyboard shortcuts hint
      (function() {
        document.addEventListener('keydown', e => {
          // Ctrl/Cmd + K to focus search
          if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
              searchInput.focus();
              searchInput.select();
            }
          }
          // ESC to close modals/forms
          if (e.key === 'Escape') {
            const closeBtn = document.querySelector('a[title="Đóng"]');
            if (closeBtn && window.location.href.includes('add=true')) {
              window.location.href = '?page=packages';
            }
          }
        });
      })();

      // Format number inputs with thousand separators (visual only)
      (function() {
        const priceInputs = document.querySelectorAll('.map-adj, input[name="GIA_TRI_GIAM"], input[name="GIAM_TOI_DA"]');
        priceInputs.forEach(input => {
          input.addEventListener('blur', function() {
            if (this.value) {
              const val = parseFloat(this.value.replace(/,/g, ''));
              if (!isNaN(val)) {
                this.dataset.rawValue = val;
                this.value = val.toLocaleString('vi-VN');
              }
            }
          });
          input.addEventListener('focus', function() {
            if (this.dataset.rawValue) {
              this.value = this.dataset.rawValue;
            }
          });
        });
      })();

      // Prevent double-submit on all forms
      (function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
          let submitted = false;
          form.addEventListener('submit', function(e) {
            if (submitted) {
              e.preventDefault();
              return false;
            }
            submitted = true;
            setTimeout(() => { submitted = false; }, 3000);
          });
        });
      })();

      // Image preview
      function previewImage(input) {
        const preview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        if (input.files && input.files[0]) {
          const reader = new FileReader();
          reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.classList.remove('hidden');
          };
          reader.readAsDataURL(input.files[0]);
        }
      }

      // Toggle branch selector based on scope type
      function toggleBranchSelector() {
        const scopeSelect = document.getElementById('scopeTypeSelect');
        const branchDiv = document.getElementById('branchSelectorDiv');
        const branchSelect = branchDiv?.querySelector('select');
        
        if (scopeSelect && branchDiv) {
          if (scopeSelect.value === 'local') {
            branchDiv.classList.remove('hidden');
            if (branchSelect) branchSelect.required = true;
          } else {
            branchDiv.classList.add('hidden');
            if (branchSelect) {
              branchSelect.required = false;
              branchSelect.value = '';
            }
          }
        }
      }

      // Initialize on page load
      document.addEventListener('DOMContentLoaded', function() {
        toggleBranchSelector();
        
        // Dropdown menu handler
        const menuBtns = document.querySelectorAll('.menu-btn');
        menuBtns.forEach(btn => {
          btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = this.nextElementSibling;
            const isHidden = dropdown.classList.contains('hidden');
            
            // Close all other dropdowns
            document.querySelectorAll('.menu-dropdown').forEach(d => d.classList.add('hidden'));
            
            // Toggle current dropdown
            if (isHidden) {
              dropdown.classList.remove('hidden');
            } else {
              dropdown.classList.add('hidden');
            }
          });
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
          if (!e.target.closest('.menu-btn') && !e.target.closest('.menu-dropdown')) {
            document.querySelectorAll('.menu-dropdown').forEach(d => d.classList.add('hidden'));
          }
        });
      });

      // Add loading spinner CSS animation
      const style = document.createElement('style');
      style.textContent = `
        @keyframes spin { to { transform: rotate(360deg); } }
        .animate-spin { animation: spin 1s linear infinite; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.3s ease-out; }
        .transition-all { transition: all 0.2s ease; }
        .transition-colors { transition: color 0.2s, background-color 0.2s; }
      `;
      document.head.appendChild(style);
    </script>

  <!-- API Client Utilities -->
  <script src="../../public/assets/js/api-client.js"></script>
  
  <!-- Page-Specific Package Management -->
  <script src="../../public/assets/js/manage-packages.js"></script>
</body>

