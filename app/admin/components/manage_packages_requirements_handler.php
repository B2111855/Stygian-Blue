<?php
/**
 * Xử lý gán/bỏ Yêu cầu trang phục (Requirements) vào/khỏi gói
 * 
 * File này xử lý các POST requests:
 * - yc_add: Gán 1 YC vào gói
 * - yc_remove: Bỏ 1 YC khỏi gói
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

// Gán YC vào gói (thêm vào pivot table goi_yeu_cau hoặc fallback clone nếu chưa có pivot)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['yc_add'])) {
  $tableGioYC = 'goi_yeu_cau';
  $reqTable = 'goi_trang_phuc_yeu_cau';
  $pivotExists = pkg_table_exists($conn, $tableGioYC);
  $reqTableExistsLocal = pkg_table_exists($conn, $reqTable);
  
  if (!$pivotExists && !$reqTableExistsLocal) {
    $errorMessage = 'Chưa chạy migration bảng yêu cầu (goi_trang_phuc_yeu_cau). Vui lòng tạo bảng trước khi gán.';
  } else {
    $idGoi = (int)($_POST['ID_GOI'] ?? 0);
    $idYC = (int)($_POST['ID_YC'] ?? 0);
    $pkgMeta = $idGoi ? $pkgRepo->find($idGoi) : null;
    
    // Guard: avoid adding the same YC twice to one gói
    if ($pivotExists) {
      $dupCheck = $conn->query('SELECT 1 FROM '.$tableGioYC.' WHERE ID_GOI='.(int)$idGoi.' AND ID_YC='.(int)$idYC.' LIMIT 1');
      if ($dupCheck && $dupCheck->num_rows > 0) {
        $successMessage = 'Yêu cầu đã có trong gói này.';
        $_GET['edit'] = (string)$idGoi; $_GET['tab'] = 'slots'; $isEditing = true;
        return;
      }
    } elseif ($reqTableExistsLocal) {
      $dupCheck = $conn->query('SELECT 1 FROM '.$reqTable.' WHERE ID_GOI='.(int)$idGoi.' AND ID_YC='.(int)$idYC.' LIMIT 1');
      if ($dupCheck && $dupCheck->num_rows > 0) {
        $successMessage = 'Yêu cầu đã có trong gói này.';
        $_GET['edit'] = (string)$idGoi; $_GET['tab'] = 'slots'; $isEditing = true;
        return;
      }
    }
    
    // Permission check
    $deny = false;
    if ($pkgMeta && $isBranchManager) {
      $isGlobalPkg = ($pkgMeta['SCOPE_TYPE'] ?? '') === 'global';
      $isLocalOwnedPkg = ($pkgMeta['SCOPE_TYPE'] ?? '') === 'local' && (int)($pkgMeta['ID_CN_OWNER'] ?? 0) === $branchId;
      if ($isGlobalPkg || !$isLocalOwnedPkg) $deny = true;
    }
    
    if ($deny) {
      $errorMessage = 'Bạn không có quyền thay đổi yêu cầu của gói này.';
    } elseif (!$idGoi || !$idYC) {
      $errorMessage = 'Dữ liệu không hợp lệ (ID_GOI hoặc ID_YC thiếu).';
    } else {
      try {
        // Kiểm tra YC có tồn tại không
        $ycCheck = $conn->query('SELECT * FROM '.$reqTable.' WHERE ID_YC='.(int)$idYC);
        if (!$ycCheck || $ycCheck->num_rows === 0) {
          $errorMessage = 'Yêu cầu không tồn tại.';
        } else {
          $ycRow = $ycCheck->fetch_assoc();
          
          if ($pivotExists) {
            // Gán YC vào gói qua pivot table
            $stmt = $conn->prepare('INSERT INTO '.$tableGioYC.' (ID_GOI, ID_YC, THU_TU) VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE THU_TU = VALUES(THU_TU)');
            $thuTu = (int)($_POST['THU_TU'] ?? 0);
            $stmt->bind_param('iii', $idGoi, $idYC, $thuTu);
            
            if ($stmt->execute()) {
              $successMessage = 'Đã thêm yêu cầu vào gói.';
              record_system_log($conn, 'REQ_ADD_TO_PACKAGE', 'goi:'.$idGoi.':yc:'.$idYC, null, ['ID_GOI'=>$idGoi, 'ID_YC'=>$idYC]);
              $_GET['edit'] = (string)$idGoi;
              $_GET['tab'] = 'slots';
              $isEditing = true;
            } else {
              $errorMessage = 'Không thêm được yêu cầu: '.$conn->error;
            }
          } else {
            // Fallback: clone YC sang gói hiện tại khi chưa có bảng pivot
            if ((int)($ycRow['ID_GOI'] ?? 0) === $idGoi) {
              $successMessage = 'Yêu cầu đã thuộc gói này.';
              $_GET['edit'] = (string)$idGoi;
              $_GET['tab'] = 'slots';
              $isEditing = true;
            } else {
              $colExists = function(string $col) use ($conn, $reqTable): bool {
                $colEsc = $conn->real_escape_string($col);
                $res = $conn->query("SHOW COLUMNS FROM `$reqTable` LIKE '$colEsc'");
                return $res && $res->num_rows > 0;
              };
              $cols = ['ID_GOI','ID_NHOM','LOAI_ID','SO_LUONG','COLOR_PALETTE_JSON','SIZE_SET_JSON','ACCESSORY_SET_JSON','DEADLINE','FITTING_NOTES','BAT_BUOC'];
              $place = ['?','?','?','?','?','?','?','?','?','?'];
              $types = 'iiiisssssi';
              $params = [
                $idGoi,
                isset($ycRow['ID_NHOM']) ? (int)$ycRow['ID_NHOM'] : null,
                isset($ycRow['LOAI_ID']) ? (int)$ycRow['LOAI_ID'] : null,
                isset($ycRow['SO_LUONG']) ? (int)$ycRow['SO_LUONG'] : 1,
                $ycRow['COLOR_PALETTE_JSON'] ?? null,
                $ycRow['SIZE_SET_JSON'] ?? null,
                $ycRow['ACCESSORY_SET_JSON'] ?? null,
                $ycRow['DEADLINE'] ?? null,
                $ycRow['FITTING_NOTES'] ?? null,
                isset($ycRow['BAT_BUOC']) ? (int)$ycRow['BAT_BUOC'] : 0
              ];
              if ($colExists('LOAI_IDS_JSON')) { $cols[]='LOAI_IDS_JSON'; $place[]='?'; $types.='s'; $params[]=$ycRow['LOAI_IDS_JSON'] ?? null; }
              if ($colExists('LOAI_QTY_JSON')) { $cols[]='LOAI_QTY_JSON'; $place[]='?'; $types.='s'; $params[]=$ycRow['LOAI_QTY_JSON'] ?? null; }
              $sqlClone = 'INSERT INTO '.$reqTable.' ('.implode(',', $cols).') VALUES ('.implode(',', $place).')';
              $stmt = $conn->prepare($sqlClone);
              if (!$stmt) { throw new \RuntimeException('Prepare failed: '.$conn->error); }
              $stmt->bind_param($types, ...$params);
              if ($stmt->execute()) {
                $newYcId = $stmt->insert_id;
                $successMessage = 'Đã thêm yêu cầu vào gói (tạo bản sao #'.$newYcId.').';
                record_system_log($conn, 'REQ_ADD_TO_PACKAGE', 'goi:'.$idGoi.':yc_clone:'.$newYcId, null, ['ID_GOI'=>$idGoi, 'SOURCE_YC'=>$idYC]);
                $_GET['edit'] = (string)$idGoi;
                $_GET['tab'] = 'slots';
                $isEditing = true;
              } else {
                $errorMessage = 'Không thêm được yêu cầu: '.($stmt->error ?: $conn->error);
              }
            }
          }
        }
      } catch (\Throwable $e) {
        $errorMessage = 'Lỗi thêm yêu cầu: '.$e->getMessage();
      }
    }
  }
}

// Bỏ YC khỏi gói (xóa từ pivot table goi_yeu_cau hoặc set ID_GOI=NULL nếu dữ liệu cũ)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['yc_remove'])) {
  $tableGioYC = 'goi_yeu_cau';
  $idGoi = (int)($_POST['ID_GOI'] ?? 0);
  $idYC = (int)($_POST['ID_YC'] ?? 0);
  $pkgMeta = $idGoi ? $pkgRepo->find($idGoi) : null;
  
  // Permission check
  $deny = false;
  if ($pkgMeta && $isBranchManager) {
    $isGlobalPkg = ($pkgMeta['SCOPE_TYPE'] ?? '') === 'global';
    $isLocalOwnedPkg = ($pkgMeta['SCOPE_TYPE'] ?? '') === 'local' && (int)($pkgMeta['ID_CN_OWNER'] ?? 0) === $branchId;
    if ($isGlobalPkg || !$isLocalOwnedPkg) $deny = true;
  }
  
  if ($deny) {
    $errorMessage = 'Bạn không có quyền thay đổi yêu cầu của gói này.';
  } elseif (!$idGoi || !$idYC) {
    $errorMessage = 'Dữ liệu không hợp lệ.';
  } else {
    try {
      $pivotTableExists = pkg_table_exists($conn, $tableGioYC);
      
      if ($pivotTableExists) {
        // New way: Try delete from pivot table first
        $stmt = $conn->prepare('DELETE FROM '.$tableGioYC.' WHERE ID_GOI=? AND ID_YC=?');
        $stmt->bind_param('ii', $idGoi, $idYC);
        
        if ($stmt->execute()) {
          if ($stmt->affected_rows > 0) {
            // Successfully deleted from pivot table
            $successMessage = 'Đã bỏ yêu cầu khỏi gói.';
            record_system_log($conn, 'REQ_REMOVE_FROM_PACKAGE', 'goi:'.$idGoi.':yc:'.$idYC, null, null);
            $_GET['edit'] = (string)$idGoi;
            $_GET['tab'] = 'slots';
            $isEditing = true;
          } else {
            // Not found in pivot table, try old way (set ID_GOI to NULL)
            $stmt2 = $conn->prepare('UPDATE goi_trang_phuc_yeu_cau SET ID_GOI=NULL WHERE ID_YC=? AND ID_GOI=?');
            $stmt2->bind_param('ii', $idYC, $idGoi);
            
            if ($stmt2->execute()) {
              $successMessage = 'Đã bỏ yêu cầu khỏi gói.';
              record_system_log($conn, 'REQ_REMOVE_FROM_PACKAGE', 'goi:'.$idGoi.':yc:'.$idYC, null, null);
              $_GET['edit'] = (string)$idGoi;
              $_GET['tab'] = 'slots';
              $isEditing = true;
            } else {
              // FK constraint error on UPDATE
              if (strpos($conn->error, 'foreign key constraint fails') !== false) {
                $errorMessage = 'Không thể xóa: Yêu cầu này đang được sử dụng. Vui lòng kiểm tra lại hoặc liên hệ quản trị viên.';
              } else {
                $errorMessage = 'Không bỏ được yêu cầu: '.$conn->error;
              }
            }
          }
        } else {
          // FK constraint error on DELETE from pivot
          if (strpos($conn->error, 'foreign key constraint fails') !== false || 
              strpos($conn->error, 'fk_yc_goi') !== false) {
            $errorMessage = 'Không thể xóa: Yêu cầu này đang được sử dụng. Vui lòng kiểm tra lại hoặc liên hệ quản trị viên.';
          } else {
            $errorMessage = 'Không bỏ được yêu cầu: '.$conn->error;
          }
        }
      } else {
        // Fallback: Old way - set ID_GOI to NULL in goi_trang_phuc_yeu_cau
        $stmt = $conn->prepare('UPDATE goi_trang_phuc_yeu_cau SET ID_GOI=NULL WHERE ID_YC=? AND ID_GOI=?');
        $stmt->bind_param('ii', $idYC, $idGoi);
        
        if ($stmt->execute()) {
          $successMessage = 'Đã bỏ yêu cầu khỏi gói.';
          record_system_log($conn, 'REQ_REMOVE_FROM_PACKAGE', 'goi:'.$idGoi.':yc:'.$idYC, null, null);
          $_GET['edit'] = (string)$idGoi;
          $_GET['tab'] = 'slots';
          $isEditing = true;
        } else {
          // Check for foreign key constraint error
          if (strpos($conn->error, 'foreign key constraint fails') !== false) {
            $errorMessage = 'Không thể xóa: Yêu cầu này đang được sử dụng. Vui lòng kiểm tra lại hoặc liên hệ quản trị viên.';
          } else {
            $errorMessage = 'Không bỏ được yêu cầu: '.$conn->error;
          }
        }
      }
    } catch (\Throwable $e) {
      // Check for foreign key constraint error
      if (strpos($e->getMessage(), 'foreign key constraint fails') !== false ||
          strpos($e->getMessage(), 'fk_yc_goi') !== false) {
        $errorMessage = 'Không thể xóa: Yêu cầu này đang được sử dụng. Vui lòng kiểm tra lại hoặc liên hệ quản trị viên.';
      } else {
        $errorMessage = 'Lỗi bỏ yêu cầu: '.$e->getMessage();
      }
    }
  }
}


// Sửa yêu cầu
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['yc_edit'])) {
  if (!$reqTableExists) {
    $errorMessage = 'Bảng yêu cầu chưa tạo.';
  } else {
    try {
      $idYC = (int)($_POST['ID_YC'] ?? 0);
      $idNhom = $_POST['ID_NHOM'] !== '' ? (int)$_POST['ID_NHOM'] : null;
      $loaiId = $_POST['LOAI_ID'] !== '' ? (int)$_POST['LOAI_ID'] : null;
      $soLuong = max(1, (int)($_POST['SO_LUONG'] ?? 1));
      $batBuoc = isset($_POST['BAT_BUOC']) ? 1 : 0;
      
      // Validation
      if (!$idYC) {
        $errorMessage = 'ID Yêu cầu không hợp lệ.';
      } elseif (!$idNhom || !$loaiId) {
        $errorMessage = 'Vui lòng chọn Nhóm và Loại.';
      } else {
        // UPDATE yêu cầu
        $stmt = $conn->prepare('UPDATE goi_trang_phuc_yeu_cau SET ID_NHOM=?, LOAI_ID=?, SO_LUONG=?, BAT_BUOC=? WHERE ID_YC=?');
        $stmt->bind_param('iiiii', $idNhom, $loaiId, $soLuong, $batBuoc, $idYC);
        
        if ($stmt->execute()) {
          $successMessage = 'Đã cập nhật yêu cầu thành công.';
          record_system_log($conn, 'REQ_UPDATE', 'yc:'.$idYC, null, ['ID_NHOM'=>$idNhom, 'LOAI_ID'=>$loaiId, 'SO_LUONG'=>$soLuong, 'BAT_BUOC'=>$batBuoc]);
        } else {
          $errorMessage = 'Không cập nhật được yêu cầu: '.$conn->error;
        }
      }
    } catch (\Throwable $e) {
      $errorMessage = 'Lỗi cập nhật yêu cầu: '.$e->getMessage();
    }
  }
}

// Xóa yêu cầu vĩnh viễn
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['yc_delete_permanent'])) {
  if (!$reqTableExists) {
    $errorMessage = 'Bảng yêu cầu chưa tạo.';
  } else {
    try {
      $idYC = (int)($_POST['ID_YC'] ?? 0);
      
      if (!$idYC) {
        $errorMessage = 'ID Yêu cầu không hợp lệ.';
      } else {
        // Check nếu YC đang được dùng bởi gói nào
        $tableGioYC = 'goi_yeu_cau';
        $pivotTableExists = pkg_table_exists($conn, $tableGioYC);
        
        $isInUse = false;
        if ($pivotTableExists) {
          $check = $conn->query('SELECT ID_GOI FROM '.$tableGioYC.' WHERE ID_YC='.$idYC);
          $isInUse = $check && $check->num_rows > 0;
        } else {
          $check = $conn->query('SELECT ID_GOI FROM goi_trang_phuc_yeu_cau WHERE ID_YC='.$idYC.' AND ID_GOI IS NOT NULL');
          $isInUse = $check && $check->num_rows > 0;
        }
        
        if ($isInUse) {
          $errorMessage = 'Không thể xóa: Yêu cầu này đang được sử dụng bởi một hoặc nhiều gói. Hãy bỏ nó khỏi các gói trước.';
        } else {
          // Xóa vĩnh viễn
          $stmt = $conn->prepare('DELETE FROM goi_trang_phuc_yeu_cau WHERE ID_YC=?');
          $stmt->bind_param('i', $idYC);
          
          if ($stmt->execute()) {
            $successMessage = 'Đã xóa yêu cầu vĩnh viễn.';
            record_system_log($conn, 'REQ_DELETE_PERMANENT', 'yc:'.$idYC, null, null);
          } else {
            $errorMessage = 'Không xóa được yêu cầu: '.$conn->error;
          }
        }
      }
    } catch (\Throwable $e) {
      $errorMessage = 'Lỗi xóa yêu cầu: '.$e->getMessage();
    }
  }
}
?>
