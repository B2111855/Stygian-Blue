<?php
/**
 * Dual-pane UI cho Yêu cầu trang phục (Requirements)
 * 
 * Hiển thị:
 * - Trái: Danh sách YC khả dụng từ database (chưa gán vào gói này)
 * - Phải: Danh sách YC đã gán vào gói hiện tại
 * - Nút bên dưới: [+ Tạo YC mới]
 * 
 * Dependencies từ manage_packages.php:
 * - $isEditing, $currentPackage['ID_GOI'] (phải đang edit gói)
 * - $conn (connection)
 * - pkg_csrf_token() (function for CSRF)
 * - Handler files cho yc_add, yc_remove
 */

if (!isset($isEditing) || !$isEditing || !isset($editPackage['ID_GOI'])) {
  echo '<p class="text-gray-500">Vui lòng chọn gói để quản lý yêu cầu.</p>';
  return;
}

$idGoi = (int)$editPackage['ID_GOI'];
$tableGioYC = 'goi_yeu_cau';
$reqTableExists = pkg_table_exists($conn, 'goi_trang_phuc_yeu_cau');
$pivotTableExists = pkg_table_exists($conn, $tableGioYC);

// Detect optional columns in goi_trang_phuc_yeu_cau (e.g., LOAI_QTY_JSON may not exist)
$hasLoaiQtyJson = false;
if ($reqTableExists) {
  $colCheck = $conn->query("SHOW COLUMNS FROM goi_trang_phuc_yeu_cau LIKE 'LOAI_QTY_JSON'");
  $hasLoaiQtyJson = $colCheck && $colCheck->num_rows > 0;
}

// Load tất cả YC
$nhomsList = $nhoms ?? [];
$loaisList = $loais ?? [];
$__loaiNameMap = [];
foreach ($loaisList as $t) { $__loaiNameMap[(int)$t['ID_LOAI']] = $t['TEN_LOAI']; }
$GLOBALS['__loaiNameMap'] = $__loaiNameMap;

$loaisByGroup = [];
foreach ($loaisList as $t) {
  $gid = (int)($t['ID_NHOM'] ?? 0);
  if (!isset($loaisByGroup[$gid])) $loaisByGroup[$gid] = [];
  $loaisByGroup[$gid][] = $t;
}

// Fallback fetch if parent page did not load lists
if (empty($nhomsList) && pkg_table_exists($conn, 'trang_phuc_nhom')) {
  $nhomRes = $conn->query("SELECT ID_NHOM, TEN_NHOM FROM trang_phuc_nhom WHERE TRANG_THAI='active' ORDER BY TEN_NHOM");
  if ($nhomRes) { while ($r = $nhomRes->fetch_assoc()) { $nhomsList[] = $r; } }
}
if (empty($loaisList) && pkg_table_exists($conn, 'trang_phuc_loai')) {
  $loaiRes = $conn->query("SELECT ID_LOAI, TEN_LOAI, ID_NHOM FROM trang_phuc_loai WHERE TRANG_THAI='active' ORDER BY TEN_LOAI");
  if ($loaiRes) { while ($r = $loaiRes->fetch_assoc()) { $loaisList[] = $r; } }
}

$allYC = [];
if ($reqTableExists) {
  $selectCols = "yc.ID_YC, yc.ID_NHOM, yc.LOAI_ID, yc.LOAI_IDS_JSON, yc.SO_LUONG, yc.BAT_BUOC, yc.ID_GOI";
  if ($hasLoaiQtyJson) { $selectCols .= ", yc.LOAI_QTY_JSON"; }
  $sql = "SELECT $selectCols, n.TEN_NHOM, l.TEN_LOAI
          FROM goi_trang_phuc_yeu_cau yc
          LEFT JOIN trang_phuc_nhom n ON n.ID_NHOM = yc.ID_NHOM
          LEFT JOIN trang_phuc_loai l ON l.ID_LOAI = yc.LOAI_ID
          ORDER BY yc.ID_YC DESC";
  $result = $conn->query($sql);
  if ($result instanceof mysqli_result) {
    while ($r = $result->fetch_assoc()) {
      $allYC[$r['ID_YC']] = $r;
    }
  } else {
    $errorMessage = 'Không tải được danh sách yêu cầu: ' . htmlspecialchars($conn->error);
  }
}

// Load YC của gói hiện tại (từ pivot table nếu tồn tại, HOẶC từ ID_GOI nếu còn dữ liệu cũ)
$ycOfPackage = [];
if ($pivotTableExists) {
  $stmt = $conn->prepare('SELECT gy.ID_YC, gy.THU_TU FROM '.$tableGioYC.' gy WHERE gy.ID_GOI=? ORDER BY gy.THU_TU ASC, gy.ID_YC ASC');
  $stmt->bind_param('i', $idGoi);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($r = $result->fetch_assoc()) {
    $ycOfPackage[(int)$r['ID_YC']] = (int)$r['THU_TU'];
  }
}

// THÊM: Nếu có dữ liệu cũ, cộng thêm vào (không xóa dữ liệu cũ)
if ($reqTableExists) {
  // Lấy YC từ cột ID_GOI (dữ liệu cũ) để support backward compatibility
  $result = $conn->query('SELECT ID_YC FROM goi_trang_phuc_yeu_cau WHERE ID_GOI='.$idGoi.' AND ID_GOI IS NOT NULL');
  while ($r = $result->fetch_assoc()) {
    $ycId = (int)$r['ID_YC'];
    // Chỉ thêm nếu chưa có ở pivot table
    if (!isset($ycOfPackage[$ycId])) {
      $ycOfPackage[$ycId] = 999; // Đặt THU_TU cao để xuất hiện cuối
    }
  }
}

// YC khả dụng (chưa gán)
$availableYC = array_diff_key($allYC, $ycOfPackage);
$selectedYC = array_intersect_key($allYC, $ycOfPackage);

// Nếu không có YC nào khả dụng và bảng đang trống, nhưng có YC thuộc chính gói (ID_GOI), coi như khả dụng để add
if (empty($availableYC) && $reqTableExists) {
  foreach ($allYC as $ycId => $ycData) {
    if ((int)($ycData['ID_GOI'] ?? 0) === $idGoi && !isset($selectedYC[$ycId])) {
      $availableYC[$ycId] = $ycData;
    }
  }
}

// Helper function
$getYCLabel = function($ycData) {
  $parts = ['YC #'.$ycData['ID_YC']];
  $nhom = $ycData['TEN_NHOM'] ?? '';
  $loaiNames = [];
  $qtyMap = [];
  if (!empty($ycData['LOAI_QTY_JSON'])) {
    $decodedQty = json_decode($ycData['LOAI_QTY_JSON'], true);
    if (is_array($decodedQty)) $qtyMap = $decodedQty;
  }
  if (!empty($ycData['LOAI_IDS_JSON'])) {
    $ids = json_decode($ycData['LOAI_IDS_JSON'], true) ?: [];
    foreach ($ids as $lid) {
      $lidInt = (int)$lid;
      if (isset($GLOBALS['__loaiNameMap'][$lidInt])) {
        $qtySuffix = isset($qtyMap[$lidInt]) ? ' (x'.$qtyMap[$lidInt].')' : '';
        $loaiNames[] = $GLOBALS['__loaiNameMap'][$lidInt] . $qtySuffix;
      }
    }
  }
  if (empty($loaiNames) && !empty($ycData['TEN_LOAI'])) {
    $loaiNames[] = $ycData['TEN_LOAI'];
  }
  if ($nhom || !empty($loaiNames)) {
    $loaiText = !empty($loaiNames) ? implode(', ', $loaiNames) : ('Loại #'.$ycData['LOAI_ID']);
    $parts[] = trim(($nhom ?: ('Nhóm #'.$ycData['ID_NHOM'])) . ' • ' . $loaiText);
  }
  if ($ycData['SO_LUONG']) {
    $parts[] = $ycData['SO_LUONG'] . ' ' . ($ycData['SO_LUONG'] == 1 ? 'mục' : 'mục');
  }
  if ($ycData['BAT_BUOC']) {
    $parts[] = '<span class="text-red-600 font-bold">*</span>';
  }
  return implode(' ', $parts);
};
?>

<div class="grid md:grid-cols-2 gap-6 mb-6">
  <!-- LEFT PANE: Available Requirements -->
  <div class="bg-white p-4 rounded shadow">
    <h3 class="text-lg font-semibold text-indigo-700 mb-4">Yêu cầu khả dụng</h3>
    <div class="h-72 overflow-y-auto border rounded">
      <?php if (empty($availableYC)): ?>
        <div class="p-3 text-gray-500 text-xs">Tất cả yêu cầu đã được thêm vào gói.</div>
      <?php else: ?>
        <?php foreach ($availableYC as $ycId => $ycData): ?>
          <div class="flex items-center justify-between px-3 py-2 text-xs border-b bg-gray-50 hover:bg-gray-100">
            <div class="flex-1">
              <span class="truncate w-48 block font-medium" title="<?php echo htmlspecialchars(strip_tags($getYCLabel($ycData))); ?>"><?php echo $getYCLabel($ycData); ?></span>
              <span class="text-[10px] text-gray-500">
                Nhóm: <?php echo htmlspecialchars($ycData['TEN_NHOM'] ?? ('#'.$ycData['ID_NHOM'])); ?>
                · Loại: 
                <?php
                  $typeNames = [];
                  $qtyMap = !empty($ycData['LOAI_QTY_JSON']) ? (json_decode($ycData['LOAI_QTY_JSON'], true) ?: []) : [];
                  if (!empty($ycData['LOAI_IDS_JSON'])) {
                    $ids = json_decode($ycData['LOAI_IDS_JSON'], true) ?: [];
                    foreach ($ids as $lid) {
                      $lidInt = (int)$lid;
                      if (isset($GLOBALS['__loaiNameMap'][$lidInt])) {
                        $suffix = isset($qtyMap[$lidInt]) ? ' (x'.$qtyMap[$lidInt].')' : '';
                        $typeNames[] = $GLOBALS['__loaiNameMap'][$lidInt] . $suffix;
                      }
                    }
                  } elseif (!empty($ycData['TEN_LOAI'])) {
                    $typeNames[] = $ycData['TEN_LOAI'];
                  }
                  echo htmlspecialchars(!empty($typeNames) ? implode(', ', $typeNames) : ('#'.$ycData['LOAI_ID']));
                ?>
              </span>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center min-w-[210px]">
              <div class="flex justify-center">
                <button type="button" class="text-blue-600 hover:text-blue-800 font-semibold text-xs w-full" onclick="editYC(<?php echo $ycId; ?>)">Sửa</button>
              </div>
              <form method="POST" class="w-full flex justify-center" onsubmit="return confirm('Xóa yêu cầu này?');">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pkg_csrf_token()); ?>">
                <input type="hidden" name="yc_delete" value="1">
                <input type="hidden" name="ID_GOI" value="<?php echo $idGoi; ?>">
                <input type="hidden" name="ID_YC" value="<?php echo $ycId; ?>">
                <button type="submit" class="text-red-600 hover:text-red-800 font-semibold text-xs w-full">Xóa</button>
              </form>
              <form method="POST" class="w-full flex justify-center">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pkg_csrf_token()); ?>">
                <input type="hidden" name="yc_add" value="1">
                <input type="hidden" name="ID_GOI" value="<?php echo $idGoi; ?>">
                <input type="hidden" name="ID_YC" value="<?php echo $ycId; ?>">
                <input type="hidden" name="THU_TU" value="<?php echo count($ycOfPackage); ?>">
                <button type="submit" class="text-green-600 hover:text-green-800 font-semibold text-xs w-full">Thêm</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <p class="text-[11px] text-gray-500 mt-2">Nhấp "Thêm" để thêm yêu cầu vào gói.</p>
  </div>

  <!-- RIGHT PANE: Selected Requirements -->
  <div class="bg-white p-4 rounded shadow">
    <h3 class="text-lg font-semibold text-indigo-700 mb-4">Yêu cầu của gói</h3>
    <div class="h-72 overflow-y-auto border rounded">
      <table class="min-w-full text-xs">
        <thead class="bg-indigo-100 text-indigo-700 sticky top-0">
          <tr>
            <th class="p-2 text-left">Yêu cầu</th>
            <th class="p-2 text-left">Nhóm/Loại</th>
            <th class="p-2 text-center">Bắt buộc</th>
            <th class="p-2 text-center">Hành động</th>
          </tr>
        </thead>
        <tbody id="requirementsBody">
          <?php if (empty($selectedYC)): ?>
            <tr><td colspan="4" class="p-3 text-gray-500 text-center">Chưa có yêu cầu nào.</td></tr>
          <?php else: ?>
            <?php foreach ($selectedYC as $ycId => $ycData): ?>
              <tr class="border-b">
                <td class="p-2"><?php echo $getYCLabel($ycData); ?></td>
                <td class="p-2 text-gray-600">
                  Nhóm: <?php echo htmlspecialchars($ycData['TEN_NHOM'] ?? ('#'.$ycData['ID_NHOM'])); ?><br>
                  Loại: <?php
                    $typeNames = [];
                    $qtyMap = !empty($ycData['LOAI_QTY_JSON']) ? (json_decode($ycData['LOAI_QTY_JSON'], true) ?: []) : [];
                    if (!empty($ycData['LOAI_IDS_JSON'])) {
                      $ids = json_decode($ycData['LOAI_IDS_JSON'], true) ?: [];
                      foreach ($ids as $lid) {
                        $lidInt = (int)$lid;
                        if (isset($GLOBALS['__loaiNameMap'][$lidInt])) {
                          $suffix = isset($qtyMap[$lidInt]) ? ' (x'.$qtyMap[$lidInt].')' : '';
                          $typeNames[] = $GLOBALS['__loaiNameMap'][$lidInt] . $suffix;
                        }
                      }
                    } elseif (!empty($ycData['TEN_LOAI'])) {
                      $typeNames[] = $ycData['TEN_LOAI'];
                    }
                    echo htmlspecialchars(!empty($typeNames) ? implode(', ', $typeNames) : ('#'.$ycData['LOAI_ID']));
                  ?>
                </td>
                <td class="p-2 text-center"><?php echo $ycData['BAT_BUOC'] ? '<span class="text-red-600 font-bold">✓</span>' : '—'; ?></td>
                <td class="p-2 text-center">
                  <form method="POST" class="inline">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pkg_csrf_token()); ?>">
                    <input type="hidden" name="yc_remove" value="1">
                    <input type="hidden" name="ID_GOI" value="<?php echo $idGoi; ?>">
                    <input type="hidden" name="ID_YC" value="<?php echo $ycId; ?>">
                    <button type="submit" class="text-red-600 hover:text-red-800 font-semibold">Xóa</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: Sửa Yêu cầu -->
<div id="editYCModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 p-4 overflow-y-auto">
  <div class="flex items-center justify-center min-h-screen">
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full">
    <div class="p-6">
      <h2 class="text-xl font-bold mb-4">Sửa yêu cầu trang phục</h2>
      
      <form method="POST" class="space-y-4">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pkg_csrf_token()); ?>">
        <input type="hidden" name="yc_update" value="1">
        <input type="hidden" name="ID_GOI" value="<?php echo $idGoi; ?>">
        <input type="hidden" name="ID_YC" id="editYC_ID" value="">
        
        <div>
          <label class="block text-sm font-medium mb-1">Nhóm trang phục *</label>
          <select name="ID_NHOM" id="editYC_NHOM" required class="w-full border rounded px-3 py-2">
            <option value="">-- Chọn --</option>
            <?php foreach ($nhomsList as $g): ?>
              <option value="<?= $g['ID_NHOM'] ?>"><?= htmlspecialchars($g['TEN_NHOM']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div>
          <label class="block text-sm font-medium mb-1">Loại trang phục (cùng nhóm, có thể chọn nhiều) *</label>
          <select name="LOAI_IDS[]" id="editYC_LOAI" multiple required class="w-full border rounded px-3 py-2 min-h-[140px]"></select>
          <p class="text-xs text-gray-500 mt-1">Chỉ hiển thị loại thuộc nhóm đã chọn.</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Số lượng theo loại</label>
          <div id="editYC_LOAI_QTY" class="space-y-2 text-sm text-gray-700"></div>
        </div>
        
        <div>
          <label class="flex items-center gap-2">
            <input type="checkbox" name="BAT_BUOC" id="editYC_BAT_BUOC" class="rounded">
            <span class="text-sm">Bắt buộc (yêu cầu này không được bỏ qua)</span>
          </label>
        </div>
        
        <div class="flex gap-2 pt-4">
          <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded font-medium transition">
            Lưu
          </button>
          <button type="button" onclick="toggleModal('editYCModal')" class="flex-1 bg-gray-400 hover:bg-gray-500 text-white py-2 rounded transition">
            Hủy
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function toggleModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) modal.classList.toggle('hidden');
}

function renderQtyInputs(selectEl, containerEl, qtyMap) {
  if (!selectEl || !containerEl) return;
  const selected = Array.from(selectEl.selectedOptions).map(o => ({ id: o.value, name: o.textContent }));
  containerEl.innerHTML = '';
  if (!selected.length) return;
  selected.forEach(item => {
    const wrap = document.createElement('div');
    wrap.className = 'flex items-center gap-3';
    const label = document.createElement('div');
    label.className = 'flex-1';
    label.textContent = item.name;
    const input = document.createElement('input');
    input.type = 'number';
    input.min = '1';
    input.name = `LOAI_QTY[${item.id}]`;
    input.value = qtyMap[item.id] ? qtyMap[item.id] : '1';
    input.className = 'w-20 px-2 py-1 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500';
    wrap.appendChild(label);
    wrap.appendChild(input);
    containerEl.appendChild(wrap);
  });
}

// Edit YC modal
function editYC(ycId) {
  // Populate edit form with YC data (via AJAX in production, or simple JS here)
  document.getElementById('editYC_ID').value = ycId;
  const ycLookup = <?php echo json_encode($availableYC + $selectedYC, JSON_UNESCAPED_UNICODE); ?>;
  const loaisByGroup = <?php echo json_encode($loaisByGroup, JSON_UNESCAPED_UNICODE); ?>;
  const ycData = ycLookup[ycId];
  
  if (ycData) {
    const nhomId = ycData['ID_NHOM'];
    document.getElementById('editYC_NHOM').value = nhomId;
    const loaiSelect = document.getElementById('editYC_LOAI');
    loaiSelect.innerHTML = '';
    const choices = loaisByGroup[nhomId] || [];
    choices.forEach(t => {
      const opt = document.createElement('option');
      opt.value = t.ID_LOAI;
      opt.textContent = t.TEN_LOAI;
      loaiSelect.appendChild(opt);
    });
    const ids = ycData['LOAI_IDS_JSON'] ? JSON.parse(ycData['LOAI_IDS_JSON']) : (ycData['LOAI_ID'] ? [ycData['LOAI_ID']] : []);
    Array.from(loaiSelect.options).forEach(opt => {
      opt.selected = ids.map(i=>parseInt(i,10)).includes(parseInt(opt.value,10));
    });
    const qtyMap = ycData['LOAI_QTY_JSON'] ? (JSON.parse(ycData['LOAI_QTY_JSON']) || {}) : {};
    renderQtyInputs(loaiSelect, document.getElementById('editYC_LOAI_QTY'), qtyMap);
    document.getElementById('editYC_BAT_BUOC').checked = ycData['BAT_BUOC'] ? true : false;
    
    toggleModal('editYCModal');
  }
}

// Keep quantity inputs in sync when types change
document.addEventListener('DOMContentLoaded', () => {
  const loaiSelect = document.getElementById('editYC_LOAI');
  const qtyWrap = document.getElementById('editYC_LOAI_QTY');
  if (loaiSelect && qtyWrap) {
    loaiSelect.addEventListener('change', () => renderQtyInputs(loaiSelect, qtyWrap, {}));
  }
});
</script>

