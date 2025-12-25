<?php
/**
 * Yêu cầu chuẩn hóa (Structured Costume Requirements) Handler
 * 
 * Processes CRUD operations for costume requirements in packages.
 * This file is included by manage_packages.php and has access to:
 * - $conn (database connection)
 * - $pkgRepo (PackageRepository instance)
 * - $reqTableExists (boolean)
 * - $isBranchManager (boolean)
 * - $branchId (int)
 * - $errorMessage, $successMessage (passed by reference)
 * - $record_system_log() function
 */

// Safety check: ensure required variables are defined
if (!isset($reqTableExists)) $reqTableExists = false;
if (!isset($isBranchManager)) $isBranchManager = false;
if (!isset($branchId)) $branchId = 0;

// Normalize aliases from global modal
if (isset($_POST['yc_create_new']) && !isset($_POST['yc_create'])) {
  $_POST['yc_create'] = $_POST['yc_create_new'];
}

// Structured Costume Requirements (Yêu cầu chuẩn hóa) CRUD Handler
if ($_SERVER['REQUEST_METHOD']==='POST' && (isset($_POST['yc_create']) || isset($_POST['yc_update']) || isset($_POST['yc_delete']))) {
  if (!$reqTableExists) {
    $errorMessage = 'Chưa chạy migration bảng goi_trang_phuc_yeu_cau. Vui lòng tạo bảng trước khi thêm yêu cầu.';
  } else {
    // Helper: check column existence once per request to avoid wrong bind counts
    $reqColExists = function(string $col) use ($conn): bool {
      $colEsc = $conn->real_escape_string($col);
      $res = $conn->query("SHOW COLUMNS FROM `goi_trang_phuc_yeu_cau` LIKE '$colEsc'");
      return $res && $res->num_rows > 0;
    };
    $hasLoaiIdsJson = $reqColExists('LOAI_IDS_JSON');
    $hasLoaiQtyJson = $reqColExists('LOAI_QTY_JSON');

    $idGoi = ($_POST['ID_GOI'] ?? '') !== '' ? (int)$_POST['ID_GOI'] : null;
    $pkgMetaTmp = $idGoi ? $pkgRepo->find($idGoi) : null;
    
    // Preload loại -> nhóm mapping for validation
    $loaiGroupMap = [];
    $loaiRes = $conn->query("SELECT ID_LOAI, ID_NHOM FROM trang_phuc_loai");
    if ($loaiRes) {
      while ($r = $loaiRes->fetch_assoc()) {
        $loaiGroupMap[(int)$r['ID_LOAI']] = (int)($r['ID_NHOM'] ?? 0);
      }
    }
    
    // Permission check: manager only local packages they own (skip if yêu cầu độc lập)
    $deny = false;
    if ($pkgMetaTmp) {
      $isGlobal = ($pkgMetaTmp['SCOPE_TYPE'] ?? '') === 'global';
      $isLocalOwned = ($pkgMetaTmp['SCOPE_TYPE'] ?? '') === 'local' && (int)($pkgMetaTmp['ID_CN_OWNER'] ?? 0) === $branchId;
      if ($isBranchManager && ($isGlobal || !$isLocalOwned)) $deny = true;
    }
    
    if ($deny) {
      $errorMessage = 'Bạn không có quyền chỉnh sửa yêu cầu chuẩn hóa của gói này.';
    } else {
      try {
        // CREATE new requirement
        if (isset($_POST['yc_create'])) {
          $idNhom = $_POST['ID_NHOM'] !== '' ? (int)$_POST['ID_NHOM'] : null;
          $loaiIds = [];
          $loaiQtyMap = [];
          if (isset($_POST['LOAI_IDS']) && is_array($_POST['LOAI_IDS'])) {
            foreach ($_POST['LOAI_IDS'] as $lid) {
              $lid = (int)$lid;
              if ($lid > 0) $loaiIds[] = $lid;
            }
          } elseif (isset($_POST['LOAI_ID']) && $_POST['LOAI_ID'] !== '') {
            $loaiIds[] = (int)$_POST['LOAI_ID']; // backward compatibility
          }
          $loaiIds = array_values(array_unique($loaiIds));
          if (isset($_POST['LOAI_QTY']) && is_array($_POST['LOAI_QTY'])) {
            foreach ($_POST['LOAI_QTY'] as $lid => $qty) {
              $lid = (int)$lid;
              if ($lid > 0) {
                $qtyVal = max(1, (int)$qty);
                $loaiQtyMap[$lid] = $qtyVal;
              }
            }
          }
          $loaiId = $loaiIds[0] ?? null; // primary for backward compatibility
          if (!$idNhom || empty($loaiIds)) {
            $errorMessage = 'Vui lòng chọn Nhóm và ít nhất một Loại trang phục.';
          } else {
            // Validate all selected types belong to the chosen group
            $allSameGroup = true;
            foreach ($loaiIds as $lid) {
              if (!isset($loaiGroupMap[$lid]) || $loaiGroupMap[$lid] !== $idNhom) {
                $allSameGroup = false; break;
              }
            }
            if (!$allSameGroup) {
              $errorMessage = 'Tất cả loại phải thuộc cùng nhóm đã chọn.';
            } else {
              // Build per-type quantities; default missing types to 1
              $finalQtyMap = [];
              foreach ($loaiIds as $lid) {
                $finalQtyMap[$lid] = $loaiQtyMap[$lid] ?? 1;
              }
              $soLuong = array_sum($finalQtyMap);
              if ($soLuong < 1) $soLuong = 1;
            
              // Parse CSV to JSON arrays
              $csvToJson = function($str){
                $out = [];
                foreach (preg_split('/[\n,]/', (string)$str) as $t) { 
                  $t = trim($t); 
                  if ($t!=='') $out[]=$t; 
                }
                return !empty($out) ? json_encode($out, JSON_UNESCAPED_UNICODE) : null;
              };
            
              $colorJson = $csvToJson($_POST['YC_COLOR'] ?? '');
              $sizeJson  = $csvToJson($_POST['YC_SIZE'] ?? '');
              $accJson   = $csvToJson($_POST['YC_ACCESSORIES'] ?? '');
              $deadline  = ($_POST['YC_DEADLINE'] ?? '') ?: null;
              $fitting   = trim($_POST['YC_FITTING'] ?? '');
              $batBuoc   = isset($_POST['YC_BAT_BUOC']) ? 1 : 0;
            
              // Build dynamic insert based on actual columns present
              $cols = ['ID_GOI','ID_NHOM','LOAI_ID','SO_LUONG','COLOR_PALETTE_JSON','SIZE_SET_JSON','ACCESSORY_SET_JSON','DEADLINE','FITTING_NOTES','BAT_BUOC'];
              $place = ['?','?','?','?','?','?','?','?','?','?'];
              $types = 'iiiisssssi';
              $params = [$idGoi, $idNhom, $loaiId, $soLuong, $colorJson, $sizeJson, $accJson, $deadline, $fitting, $batBuoc];
              $loaiIdsJson = !empty($loaiIds) ? json_encode($loaiIds, JSON_UNESCAPED_UNICODE) : null;
              $loaiQtyJson = json_encode($finalQtyMap, JSON_UNESCAPED_UNICODE);
              if ($hasLoaiIdsJson) { $cols[]='LOAI_IDS_JSON'; $place[]='?'; $types.='s'; $params[]=$loaiIdsJson; }
              if ($hasLoaiQtyJson) { $cols[]='LOAI_QTY_JSON'; $place[]='?'; $types.='s'; $params[]=$loaiQtyJson; }
              $sql = 'INSERT INTO goi_trang_phuc_yeu_cau ('.implode(',', $cols).') VALUES ('.implode(',', $place).')';
              $stmt = $conn->prepare($sql);
              if (!$stmt) { throw new \RuntimeException('Prepare failed: '.$conn->error); }
              $stmt->bind_param($types, ...$params);
              
              if ($stmt->execute()) {
                $newYcId = $stmt->insert_id;
                $successMessage = 'Đã tạo yêu cầu chuẩn hóa (#'.$newYcId.').';
                record_system_log($conn,'REQ_CREATE','yc:'.$newYcId,null,[ 'ID_GOI'=>$idGoi,'LOAI_ID'=>$loaiId,'ID_NHOM'=>$idNhom,'SO_LUONG'=>$soLuong ]);
              } else { 
                $errorMessage = 'Không tạo được yêu cầu chuẩn hóa: '.htmlspecialchars($stmt->error ?: $conn->error);
              }
            }
          }
        }
        
        // UPDATE existing requirement
        if (isset($_POST['yc_update'])) {
          $idYc = (int)($_POST['ID_YC'] ?? 0);
          $idNhom = $_POST['ID_NHOM'] !== '' ? (int)$_POST['ID_NHOM'] : null;
          $loaiIds = [];
          $loaiQtyMap = [];
          if (isset($_POST['LOAI_IDS']) && is_array($_POST['LOAI_IDS'])) {
            foreach ($_POST['LOAI_IDS'] as $lid) {
              $lid = (int)$lid;
              if ($lid > 0) $loaiIds[] = $lid;
            }
          } elseif (isset($_POST['LOAI_ID']) && $_POST['LOAI_ID'] !== '') {
            $loaiIds[] = (int)$_POST['LOAI_ID'];
          }
          $loaiIds = array_values(array_unique($loaiIds));
          if (isset($_POST['LOAI_QTY']) && is_array($_POST['LOAI_QTY'])) {
            foreach ($_POST['LOAI_QTY'] as $lid => $qty) {
              $lid = (int)$lid;
              if ($lid > 0) {
                $qtyVal = max(1, (int)$qty);
                $loaiQtyMap[$lid] = $qtyVal;
              }
            }
          }
          $loaiId = $loaiIds[0] ?? null;
          if (!$idNhom || empty($loaiIds)) {
            $errorMessage = 'Vui lòng chọn Nhóm và ít nhất một Loại trang phục.';
          } else {
            // Validate same group
            $allSameGroup = true;
            foreach ($loaiIds as $lid) {
              if (!isset($loaiGroupMap[$lid]) || $loaiGroupMap[$lid] !== $idNhom) { $allSameGroup = false; break; }
            }
            if (!$allSameGroup) {
              $errorMessage = 'Tất cả loại phải thuộc cùng nhóm đã chọn.';
            }
          }
          
          $csvToJson = function($str){ 
            $out=[]; 
            foreach (preg_split('/[,\n]/', (string)$str) as $t){ 
              $t=trim($t); 
              if($t!=='') $out[]=$t; 
            } 
            return !empty($out)? json_encode($out, JSON_UNESCAPED_UNICODE) : null; 
          };
          
          $colorJson = $csvToJson($_POST['YC_COLOR'] ?? '');
          $sizeJson  = $csvToJson($_POST['YC_SIZE'] ?? '');
          $accJson   = $csvToJson($_POST['YC_ACCESSORIES'] ?? '');
          $deadline  = ($_POST['YC_DEADLINE'] ?? '') ?: null;
          $fitting   = trim($_POST['YC_FITTING'] ?? '');
          $batBuoc   = isset($_POST['YC_BAT_BUOC']) ? 1 : 0;
          
          if (!isset($errorMessage)) {
            $loaiIdsJson = !empty($loaiIds) ? json_encode($loaiIds, JSON_UNESCAPED_UNICODE) : null;
            $finalQtyMap = [];
            foreach ($loaiIds as $lid) {
              $finalQtyMap[$lid] = $loaiQtyMap[$lid] ?? 1;
            }
            $soLuong = array_sum($finalQtyMap);
            if ($soLuong < 1) $soLuong = 1;
            $loaiQtyJson = json_encode($finalQtyMap, JSON_UNESCAPED_UNICODE);

            $sets = ['ID_NHOM=?','LOAI_ID=?','SO_LUONG=?','COLOR_PALETTE_JSON=?','SIZE_SET_JSON=?','ACCESSORY_SET_JSON=?','DEADLINE=?','FITTING_NOTES=?','BAT_BUOC=?'];
            $types = 'iiiisssssi';
            $params = [$idNhom, $loaiId, $soLuong, $colorJson, $sizeJson, $accJson, $deadline, $fitting, $batBuoc];
            if ($hasLoaiIdsJson) { $sets[]='LOAI_IDS_JSON=?'; $types.='s'; $params[]=$loaiIdsJson; }
            if ($hasLoaiQtyJson) { $sets[]='LOAI_QTY_JSON=?'; $types.='s'; $params[]=$loaiQtyJson; }
            $types .= 'i'; $params[] = $idYc;
            $sql = 'UPDATE goi_trang_phuc_yeu_cau SET '.implode(',', $sets).' WHERE ID_YC=?';
            $stmt = $conn->prepare($sql);
            if (!$stmt) { throw new \RuntimeException('Prepare failed: '.$conn->error); }
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
              $successMessage = 'Đã cập nhật yêu cầu chuẩn hóa.';
              record_system_log($conn,'REQ_UPDATE','yc:'.$idYc,null,[ 'ID_GOI'=>$idGoi ]);
            } else { 
              $errorMessage = 'Không cập nhật được yêu cầu chuẩn hóa.'; 
            }
          }
        }
        
        // DELETE requirement and unlink slots
        if (isset($_POST['yc_delete'])) {
          $idYc = (int)($_POST['ID_YC'] ?? 0);
          $inUseOther = false;
          // Check pivot table usage in other packages
          $tblCheck = $conn->query("SHOW TABLES LIKE 'goi_yeu_cau'");
          if ($tblCheck && $tblCheck->num_rows > 0) {
            $useRes = $conn->query('SELECT DISTINCT ID_GOI FROM goi_yeu_cau WHERE ID_YC='.(int)$idYc);
            if ($useRes) {
              while ($row = $useRes->fetch_assoc()) {
                $gid = (int)($row['ID_GOI'] ?? 0);
                if (!$idGoi || $gid !== $idGoi) { $inUseOther = true; break; }
              }
            }
          }
          // Legacy usage check in main table
          if (!$inUseOther) {
            $legacyRes = $conn->query('SELECT DISTINCT ID_GOI FROM goi_trang_phuc_yeu_cau WHERE ID_YC='.(int)$idYc.' AND ID_GOI IS NOT NULL');
            if ($legacyRes) {
              while ($row = $legacyRes->fetch_assoc()) {
                $gid = (int)($row['ID_GOI'] ?? 0);
                if (!$idGoi || $gid !== $idGoi) { $inUseOther = true; break; }
              }
            }
          }
          if ($inUseOther) {
            $errorMessage = 'Không thể xóa: yêu cầu đang được gán cho gói khác.';
          }
          if (isset($errorMessage)) {
            // skip deletion
          }
          
          if (!isset($errorMessage)) {
            // Unlink slots referencing this requirement if tied to a package
            if ($idGoi) {
              $conn->query("UPDATE goi_dich_vu_trang_phuc_slot SET ID_YC=NULL WHERE ID_GOI=".(int)$idGoi." AND ID_YC=".(int)$idYc);
            }
            
            $stmt = $conn->prepare("DELETE FROM goi_trang_phuc_yeu_cau WHERE ID_YC=?");
            $stmt->bind_param('i',$idYc);
            
            if ($stmt->execute()) {
              $successMessage = 'Đã xóa yêu cầu chuẩn hóa.';
              record_system_log($conn,'REQ_DELETE','yc:'.$idYc,null,null);
            } else { 
              $errorMessage = 'Không xóa được yêu cầu chuẩn hóa.'; 
            }
          }
        }
      } catch (\Throwable $e) { 
        $errorMessage = 'Lỗi yêu cầu chuẩn hóa: '.$e->getMessage(); 
      }
    }
  }
}
?>
