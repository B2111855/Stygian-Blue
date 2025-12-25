<?php
/**
 * Xử lý gán/bỏ Khuyến mãi (Promotions) vào/khỏi gói
 * 
 * File này xử lý các POST requests:
 * - promo_add: Gán 1 KM vào gói
 * - promo_remove: Bỏ 1 KM khỏi gói
 * 
 * Dependencies từ manage_packages.php:
 * - $conn (MySQLi connection)
 * - $pkgRepo (PackageRepository)
 * - $isBranchManager, $branchId (permissions)
 * - $errorMessage, $successMessage (feedback)
 */

// Safety check: ensure required variables are defined
if (!isset($isBranchManager)) $isBranchManager = false;
if (!isset($branchId)) $branchId = 0;

// Gán KM vào gói (thêm vào pivot table goi_khuyen_mai)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['promo_add'])) {
  $tableGioKM = 'goi_khuyen_mai';
  
  if (!pkg_table_exists($conn, $tableGioKM)) {
    $errorMessage = 'Chưa chạy migration bảng gán khuyến mãi. Vui lòng chạy: 2025_11_30_000001_create_goi_yeu_cau_table.sql';
  } else {
    $idGoi = (int)($_POST['ID_GOI'] ?? 0);
    $idPromo = (int)($_POST['ID_PROMO'] ?? 0);
    $pkgMeta = $idGoi ? $pkgRepo->find($idGoi) : null;
    
    // Permission check
    $deny = false;
    if ($pkgMeta && $isBranchManager) {
      $isGlobalPkg = ($pkgMeta['SCOPE_TYPE'] ?? '') === 'global';
      $isLocalOwnedPkg = ($pkgMeta['SCOPE_TYPE'] ?? '') === 'local' && (int)($pkgMeta['ID_CN_OWNER'] ?? 0) === $branchId;
      if ($isGlobalPkg || !$isLocalOwnedPkg) $deny = true;
    }
    
    if ($deny) {
      $errorMessage = 'Bạn không có quyền thay đổi khuyến mãi của gói này.';
    } elseif (!$idGoi || !$idPromo) {
      $errorMessage = 'Dữ liệu không hợp lệ (ID_GOI hoặc ID_PROMO thiếu).';
    } else {
      try {
        // Kiểm tra KM có tồn tại không
        $promoCheck = $conn->query('SELECT ID_KHUYEN_MAI FROM khuyen_mai WHERE ID_KHUYEN_MAI='.$idPromo);
        if (!$promoCheck || $promoCheck->num_rows === 0) {
          $errorMessage = 'Khuyến mãi không tồn tại.';
        } else {
          // Gán KM vào gói (INSERT hoặc UPDATE nếu đã tồn tại)
          $stmt = $conn->prepare('INSERT INTO '.$tableGioKM.' (ID_GOI, ID_PROMO, THU_TU) VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE THU_TU = VALUES(THU_TU)');
          $thuTu = (int)($_POST['THU_TU'] ?? 0);
          $stmt->bind_param('iii', $idGoi, $idPromo, $thuTu);
          
          if ($stmt->execute()) {
            $successMessage = 'Đã thêm khuyến mãi vào gói.';
            record_system_log($conn, 'PROMO_ADD_TO_PACKAGE', 'goi:'.$idGoi.':promo:'.$idPromo, null, ['ID_GOI'=>$idGoi, 'ID_PROMO'=>$idPromo]);
            $_GET['edit'] = (string)$idGoi;
            $_GET['tab'] = 'promotions';
            $isEditing = true;
          } else {
            $errorMessage = 'Không thêm được khuyến mãi: '.$conn->error;
          }
        }
      } catch (\Throwable $e) {
        $errorMessage = 'Lỗi thêm khuyến mãi: '.$e->getMessage();
      }
    }
  }
}

// Bỏ KM khỏi gói (xóa từ pivot table goi_khuyen_mai)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['promo_remove'])) {
  $tableGioKM = 'goi_khuyen_mai';
  
  if (!pkg_table_exists($conn, $tableGioKM)) {
    $errorMessage = 'Bảng gán khuyến mãi chưa tạo.';
  } else {
    $idGoi = (int)($_POST['ID_GOI'] ?? 0);
    $idPromo = (int)($_POST['ID_PROMO'] ?? 0);
    $pkgMeta = $idGoi ? $pkgRepo->find($idGoi) : null;
    
    // Permission check
    $deny = false;
    if ($pkgMeta && $isBranchManager) {
      $isGlobalPkg = ($pkgMeta['SCOPE_TYPE'] ?? '') === 'global';
      $isLocalOwnedPkg = ($pkgMeta['SCOPE_TYPE'] ?? '') === 'local' && (int)($pkgMeta['ID_CN_OWNER'] ?? 0) === $branchId;
      if ($isGlobalPkg || !$isLocalOwnedPkg) $deny = true;
    }
    
    if ($deny) {
      $errorMessage = 'Bạn không có quyền thay đổi khuyến mãi của gói này.';
    } elseif (!$idGoi || !$idPromo) {
      $errorMessage = 'Dữ liệu không hợp lệ.';
    } else {
      try {
        $stmt = $conn->prepare('DELETE FROM '.$tableGioKM.' WHERE ID_GOI=? AND ID_PROMO=?');
        $stmt->bind_param('ii', $idGoi, $idPromo);
        
        if ($stmt->execute()) {
          $successMessage = 'Đã bỏ khuyến mãi khỏi gói.';
          record_system_log($conn, 'PROMO_REMOVE_FROM_PACKAGE', 'goi:'.$idGoi.':promo:'.$idPromo, null, null);
          $_GET['edit'] = (string)$idGoi;
          $_GET['tab'] = 'promotions';
          $isEditing = true;
        } else {
          $errorMessage = 'Không bỏ được khuyến mãi: '.$conn->error;
        }
      } catch (\Throwable $e) {
        $errorMessage = 'Lỗi bỏ khuyến mãi: '.$e->getMessage();
      }
    }
  }
}

// Sửa khuyến mãi khả dụng (bảng khuyen_mai)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['promo_available_update'])) {
  if (!pkg_table_exists($conn, 'khuyen_mai')) {
    $errorMessage = 'Bảng khuyến mãi chưa tạo.';
  } else {
    $idGoi = (int)($_POST['ID_GOI'] ?? 0);
    $idPromo = (int)($_POST['ID_PROMO'] ?? 0);
    $tenKM = trim($_POST['TEN_KM'] ?? '');
    $kieuKM = (string)($_POST['KIEU_KM'] ?? '');
    $giaTri = (float)($_POST['GIA_TRI'] ?? 0);
    $ngayBatDau = ($_POST['NGAY_BAT_DAU'] ?? '') ?: null;
    $ngayKetThuc = ($_POST['NGAY_KET_THUC'] ?? '') ?: null;
    $trangThai = isset($_POST['TRANG_THAI']) ? 'active' : 'inactive';

    // Validation
    if (!$idPromo) {
      $errorMessage = 'ID khuyến mãi không hợp lệ.';
    } elseif ($tenKM === '') {
      $errorMessage = 'Tên khuyến mãi không được để trống.';
    } elseif (!in_array($kieuKM, ['percent','fixed'])) {
      $errorMessage = 'Loại khuyến mãi không hợp lệ.';
    } elseif ($giaTri <= 0) {
      $errorMessage = 'Giá trị khuyến mãi phải > 0.';
    } elseif (!$ngayBatDau || !$ngayKetThuc) {
      $errorMessage = 'Vui lòng chọn ngày bắt đầu/kết thúc.';
    } elseif (strtotime($ngayBatDau) >= strtotime($ngayKetThuc)) {
      $errorMessage = 'Ngày kết thúc phải sau ngày bắt đầu.';
    }

    if (!isset($errorMessage)) {
      $stmt = $conn->prepare('UPDATE khuyen_mai SET TEN_KM=?, KIEU_KM=?, GIA_TRI=?, NGAY_BAT_DAU=?, NGAY_KET_THUC=?, TRANG_THAI=? WHERE ID_KHUYEN_MAI=?');
      $stmt->bind_param('ssdsssi', $tenKM, $kieuKM, $giaTri, $ngayBatDau, $ngayKetThuc, $trangThai, $idPromo);
      if ($stmt->execute()) {
        $successMessage = 'Đã cập nhật khuyến mãi.';
        record_system_log($conn, 'PROMO_UPDATE', 'km:'.$idPromo, null, ['ID_GOI'=>$idGoi]);
        $_GET['edit'] = (string)$idGoi;
        $_GET['tab'] = 'promotions';
        $isEditing = true;
      } else {
        $errorMessage = 'Không cập nhật được khuyến mãi: '.$conn->error;
      }
    }
  }
}

// Xóa khuyến mãi khả dụng nếu chưa gán gói nào
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['promo_available_delete'])) {
  if (!pkg_table_exists($conn, 'khuyen_mai')) {
    $errorMessage = 'Bảng khuyến mãi chưa tạo.';
  } else {
    $idGoi = (int)($_POST['ID_GOI'] ?? 0);
    $idPromo = (int)($_POST['ID_PROMO'] ?? 0);
    if (!$idPromo) {
      $errorMessage = 'ID khuyến mãi không hợp lệ.';
    } else {
      // Block delete if promo is used in any package
      $inUse = false;
      $pivotCheck = $conn->query("SHOW TABLES LIKE 'goi_khuyen_mai'");
      if ($pivotCheck && $pivotCheck->num_rows > 0) {
        $useRes = $conn->query('SELECT ID_GOI FROM goi_khuyen_mai WHERE ID_PROMO='.(int)$idPromo.' LIMIT 1');
        if ($useRes && $useRes->num_rows > 0) {
          $inUse = true;
        }
      }
      if ($inUse) {
        $errorMessage = 'Không thể xóa: khuyến mãi đang được gán cho gói khác.';
      } else {
        $stmt = $conn->prepare('DELETE FROM khuyen_mai WHERE ID_KHUYEN_MAI=?');
        $stmt->bind_param('i', $idPromo);
        if ($stmt->execute()) {
          $successMessage = 'Đã xóa khuyến mãi.';
          record_system_log($conn, 'PROMO_DELETE', 'km:'.$idPromo, null, ['ID_GOI'=>$idGoi]);
          $_GET['edit'] = (string)$idGoi;
          $_GET['tab'] = 'promotions';
          $isEditing = true;
        } else {
          $errorMessage = 'Không xóa được khuyến mãi: '.$conn->error;
        }
      }
    }
  }
}
?>

