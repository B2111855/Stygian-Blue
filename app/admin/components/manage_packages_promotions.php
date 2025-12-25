<?php
/**
 * Khuyến mãi (Promotions) Handler
 * 
 * Processes CRUD operations for promotions in packages.
 * This file is included by manage_packages.php and has access to:
 * - $conn (database connection)
 * - $pkgRepo (PackageRepository instance)
 * - $promoRepo (PromotionRepository instance)
 * - $promotionsTableExists (boolean)
 * - $isBranchManager (boolean)
 * - $branchId (int)
 * - $errorMessage, $successMessage (passed by reference)
 * - $record_system_log() function
 */

// Safety check: ensure required variables are defined
if (!isset($promotionsTableExists)) $promotionsTableExists = false;
if (!isset($isBranchManager)) $isBranchManager = false;
if (!isset($branchId)) $branchId = 0;

// Promotion CRUD Handler
if ($_SERVER['REQUEST_METHOD']==='POST' && (isset($_POST['promo_create']) || isset($_POST['promo_update']) || isset($_POST['promo_toggle_active']) || isset($_POST['promo_delete']))) {
  $tablePromo = 'goi_dich_vu_khuyen_mai';
  
  if (!pkg_table_exists($conn,$tablePromo)) {
    $errorMessage = 'Chưa chạy migration tạo bảng khuyến mãi.';
  } else {
    $idGoi = (int)($_POST['ID_GOI'] ?? 0);
    $pkgMetaTmp = $idGoi ? $pkgRepo->find($idGoi) : null;
    
    // Permission check: manager only local packages they own
    $deny = false;
    if ($pkgMetaTmp && $isBranchManager) {
      $isGlobalPkg = ($pkgMetaTmp['SCOPE_TYPE'] ?? '') === 'global';
      $isLocalOwnedPkg = ($pkgMetaTmp['SCOPE_TYPE'] ?? '') === 'local' && (int)($pkgMetaTmp['ID_CN_OWNER'] ?? 0) === $branchId;
      if ($isGlobalPkg || !$isLocalOwnedPkg) $deny = true;
    }
    
    if ($deny) {
      $errorMessage = 'Bạn không có quyền chỉnh khuyến mãi cho gói này.';
    } else {
      try {
        // Helper: normalize datetime from form input
        $normDt = function($v){
          $v = trim((string)$v); 
          if($v==='') return date('Y-m-d H:i:00');
          $v = str_replace('T',' ',$v); 
          if(!preg_match('/:\d{2}$/',$v)) $v .= ':00'; 
          return $v;
        };
        
        // CREATE new promotion
        if (isset($_POST['promo_create'])) {
          $data = [
            'ID_GOI' => $idGoi,
            'TEN_CHUONG_TRINH' => trim($_POST['TEN_CHUONG_TRINH'] ?? ''),
            'MO_TA' => trim($_POST['MO_TA'] ?? ''),
            'LOAI_GIAM' => $_POST['LOAI_GIAM'] ?? 'phan_tram',
            'GIA_TRI_GIAM' => (float)($_POST['GIA_TRI_GIAM'] ?? 0),
            'GIAM_TOI_DA' => !empty($_POST['GIAM_TOI_DA']) ? (float)$_POST['GIAM_TOI_DA'] : null,
            'TU_NGAY' => $normDt($_POST['TU_NGAY'] ?? ''),
            'DEN_NGAY' => $normDt($_POST['DEN_NGAY'] ?? ''),
            'ACTIVE' => isset($_POST['ACTIVE_PROMO']) ? 1 : 0
          ];
          
          error_log('PROMO_CREATE_REQUEST pkg='.$idGoi.' role='.(string)$role.' branch='.(int)$branchId.' payload='.json_encode($data, JSON_UNESCAPED_UNICODE));
          
          // Validation
          if ($data['TEN_CHUONG_TRINH']==='') {
            $errorMessage = 'Tên chương trình khuyến mãi không được để trống.';
          } elseif ($data['LOAI_GIAM']==='phan_tram' && ($data['GIA_TRI_GIAM']<=0 || $data['GIA_TRI_GIAM']>100)) {
            $errorMessage = 'Giá trị phần trăm phải từ 1 đến 100.';
          } elseif ($data['LOAI_GIAM']==='so_tien' && $data['GIA_TRI_GIAM']<=0) {
            $errorMessage = 'Số tiền giảm phải > 0.';
          } elseif (strtotime($data['TU_NGAY']) >= strtotime($data['DEN_NGAY'])) {
            $errorMessage = 'Ngày kết thúc phải sau ngày bắt đầu.';
          }
          
          $newId = !isset($errorMessage) ? $promoRepo->createPromotion($data) : null;
          if ($newId) {
            error_log('PROMO_CREATE_SUCCESS id='.$newId.' pkg='.$idGoi);
            $successMessage = 'Đã tạo khuyến mãi mới.';
            record_system_log($conn,'PROMO_CREATE','promo:'.$newId,null,['package'=>$idGoi]);
            $_GET['edit'] = (string)$idGoi; 
            $_GET['tab'] = 'promotions'; 
            $isEditing=true;
          } else if(!isset($errorMessage)) { 
            $errorMessage = 'Không tạo được khuyến mãi.'; 
            error_log('PROMO_CREATE_FAILED pkg='.$idGoi); 
          }
        }
        
        // UPDATE existing promotion
        if (isset($_POST['promo_update'])) {
          $idPromo = (int)($_POST['ID_PROMO'] ?? 0);
          $data = [
            'TEN_CHUONG_TRINH' => trim($_POST['TEN_CHUONG_TRINH'] ?? ''),
            'MO_TA' => trim($_POST['MO_TA'] ?? ''),
            'LOAI_GIAM' => $_POST['LOAI_GIAM'] ?? 'phan_tram',
            'GIA_TRI_GIAM' => (float)($_POST['GIA_TRI_GIAM'] ?? 0),
            'GIAM_TOI_DA' => !empty($_POST['GIAM_TOI_DA']) ? (float)$_POST['GIAM_TOI_DA'] : null,
            'TU_NGAY' => $normDt($_POST['TU_NGAY'] ?? ''),
            'DEN_NGAY' => $normDt($_POST['DEN_NGAY'] ?? ''),
            'ACTIVE' => isset($_POST['ACTIVE_PROMO']) ? 1 : 0
          ];
          
          error_log('PROMO_UPDATE_REQUEST id='.$idPromo.' pkg='.$idGoi.' payload='.json_encode($data, JSON_UNESCAPED_UNICODE));
          
          // Validation
          if ($data['TEN_CHUONG_TRINH']==='') {
            $errorMessage = 'Tên chương trình không được trống.';
          } elseif ($data['LOAI_GIAM']==='phan_tram' && ($data['GIA_TRI_GIAM']<=0 || $data['GIA_TRI_GIAM']>100)) {
            $errorMessage = 'Phần trăm giảm phải 1-100.';
          } elseif ($data['LOAI_GIAM']==='so_tien' && $data['GIA_TRI_GIAM']<=0) {
            $errorMessage = 'Số tiền giảm phải > 0.';
          } elseif (strtotime($data['TU_NGAY']) >= strtotime($data['DEN_NGAY'])) {
            $errorMessage = 'Ngày kết thúc phải sau ngày bắt đầu.';
          }
          
          if (!isset($errorMessage) && $promoRepo->updatePromotion($idPromo, $data)) {
            error_log('PROMO_UPDATE_SUCCESS id='.$idPromo);
            $successMessage = 'Đã cập nhật khuyến mãi.';
            record_system_log($conn,'PROMO_UPDATE','promo:'.$idPromo,null,null);
            $_GET['edit'] = (string)$idGoi; 
            $_GET['tab'] = 'promotions'; 
            $isEditing=true;
          } else if(!isset($errorMessage)) { 
            $errorMessage = 'Không cập nhật được khuyến mãi.'; 
            error_log('PROMO_UPDATE_FAILED id='.$idPromo); 
          }
        }
        
        // TOGGLE active status
        if (isset($_POST['promo_toggle_active'])) {
          $idPromo = (int)($_POST['ID_PROMO'] ?? 0);
          $newActive = (int)($_POST['ACTIVE_VAL'] ?? 0);
          
          if ($promoRepo->toggleActive($idPromo, $newActive)) {
            $successMessage = 'Đã thay đổi trạng thái khuyến mãi.';
            record_system_log($conn,'PROMO_TOGGLE','promo:'.$idPromo,null,['active'=>$newActive]);
            $_GET['edit'] = (string)$idGoi; 
            $_GET['tab'] = 'promotions'; 
            $isEditing=true;
          } else { 
            $errorMessage = 'Không thay đổi được trạng thái.'; 
          }
        }
        
        // DELETE promotion
        if (isset($_POST['promo_delete'])) {
          $idPromo = (int)($_POST['ID_PROMO'] ?? 0);
          
          if ($promoRepo->deletePromotion($idPromo)) {
            $successMessage = 'Đã xóa khuyến mãi.';
            record_system_log($conn,'PROMO_DELETE','promo:'.$idPromo,null,null);
            $_GET['edit'] = (string)$idGoi; 
            $_GET['tab'] = 'promotions'; 
            $isEditing=true;
          } else { 
            $errorMessage = 'Không xóa được khuyến mãi.'; 
          }
        }
      } catch (\Throwable $e) { 
        $errorMessage = 'Lỗi khuyến mãi: '.$e->getMessage(); 
      }
    }
  }
}
?>
