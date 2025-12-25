<?php
/**
 * Dual-pane UI cho Khuyến mãi (Promotions)
 * 
 * Hiển thị:
 * - Trái: Danh sách KM khả dụng từ database (chưa gán vào gói này)
 * - Phải: Danh sách KM đã gán vào gói hiện tại
 * - Nút bên dưới: [+ Tạo KM mới]
 * 
 * Dependencies từ manage_packages.php:
 * - $isEditing, $currentPackage['ID_GOI'] (phải đang edit gói)
 * - $conn (connection)
 * - pkg_csrf_token() (function for CSRF)
 * - Handler files cho promo_add, promo_remove
 */

if (!isset($isEditing) || !$isEditing || !isset($editPackage['ID_GOI'])) {
  echo '<p class="text-gray-500">Vui lòng chọn gói để quản lý khuyến mãi.</p>';
  return;
}

$idGoi = (int)$editPackage['ID_GOI'];
$tableGioKM = 'goi_khuyen_mai';
$promoTableExists = pkg_table_exists($conn, 'khuyen_mai');
$pivotTableExists = pkg_table_exists($conn, $tableGioKM);

// Load tất cả KM
$allKM = [];
if ($promoTableExists) {
  $result = $conn->query('SELECT ID_KHUYEN_MAI, TEN_KM, KIEU_KM, GIA_TRI, NGAY_BAT_DAU, NGAY_KET_THUC, TRANG_THAI FROM khuyen_mai ORDER BY ID_KHUYEN_MAI DESC');
  while ($r = $result->fetch_assoc()) {
    $allKM[$r['ID_KHUYEN_MAI']] = $r;
  }
}

// Load KM của gói hiện tại (từ pivot table)
$kmOfPackage = [];
if ($pivotTableExists) {
  $stmt = $conn->prepare('SELECT gk.ID_PROMO, gk.THU_TU FROM '.$tableGioKM.' gk WHERE gk.ID_GOI=? ORDER BY gk.THU_TU ASC, gk.ID_PROMO ASC');
  $stmt->bind_param('i', $idGoi);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($r = $result->fetch_assoc()) {
    $kmOfPackage[(int)$r['ID_PROMO']] = (int)$r['THU_TU'];
  }
}

// KM khả dụng (chưa gán)
$availableKM = array_diff_key($allKM, $kmOfPackage);
$selectedKM = array_intersect_key($allKM, $kmOfPackage);

// Helper function
$getKMLabel = function($kmData) {
  $label = 'KM #'.$kmData['ID_KHUYEN_MAI'].': '.htmlspecialchars($kmData['TEN_KM']);
  if ($kmData['KIEU_KM'] === 'percent') {
    $label .= ' ('.$kmData['GIA_TRI'].'%)';
  } else {
    $label .= ' (₫'.number_format($kmData['GIA_TRI'], 0, ',', '.').')';
  }
  if ($kmData['TRANG_THAI'] === 'inactive') {
    $label .= ' <span class="text-orange-600 text-xs font-bold">TẮT</span>';
  }
  return $label;
};

$getKMStatus = function($kmData) {
  $now = new DateTime();
  $start = new DateTime($kmData['NGAY_BAT_DAU']);
  $end = new DateTime($kmData['NGAY_KET_THUC']);
  
  if ($now < $start) return 'Chưa bắt đầu';
  if ($now > $end) return 'Đã hết hạn';
  return 'Đang diễn ra';
};
?>

<div class="grid md:grid-cols-2 gap-6 mb-6">
  <!-- LEFT PANE: Available Promotions -->
  <div class="bg-white p-4 rounded shadow">
    <h3 class="text-lg font-semibold text-indigo-700 mb-4">Khuyến mãi khả dụng</h3>
    <div class="h-72 overflow-y-auto border rounded">
      <?php if (empty($availableKM)): ?>
        <div class="p-3 text-gray-500 text-xs">Tất cả khuyến mãi đã được thêm vào gói.</div>
      <?php else: ?>
        <?php foreach ($availableKM as $kmId => $kmData): ?>
          <div class="flex items-center justify-between px-3 py-2 text-xs border-b bg-gray-50 hover:bg-gray-100">
            <div class="flex-1">
              <span class="truncate w-40 block font-medium" title="<?php echo htmlspecialchars($kmData['TEN_KM']); ?>"><?php echo htmlspecialchars($kmData['TEN_KM']); ?></span>
              <span class="text-[10px] text-gray-500">
                <?php 
                  if ($kmData['KIEU_KM'] === 'percent') {
                    echo $kmData['GIA_TRI'].'%';
                  } else {
                    echo '₫'.number_format($kmData['GIA_TRI'], 0, ',', '.');
                  }
                ?>
              </span>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center min-w-[210px]">
              <div class="flex justify-center">
                <button type="button" class="text-blue-600 hover:text-blue-800 font-semibold text-xs w-full" onclick="openEditPromo(<?php echo $kmId; ?>)">Sửa</button>
              </div>
              <form method="POST" class="w-full flex justify-center" onsubmit="return confirm('Xóa khuyến mãi này?');">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pkg_csrf_token()); ?>">
                <input type="hidden" name="promo_available_delete" value="1">
                <input type="hidden" name="ID_GOI" value="<?php echo $idGoi; ?>">
                <input type="hidden" name="ID_PROMO" value="<?php echo $kmId; ?>">
                <button type="submit" class="text-red-600 hover:text-red-800 font-semibold text-xs w-full">Xóa</button>
              </form>
              <form method="POST" class="w-full flex justify-center">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pkg_csrf_token()); ?>">
                <input type="hidden" name="promo_add" value="1">
                <input type="hidden" name="ID_GOI" value="<?php echo $idGoi; ?>">
                <input type="hidden" name="ID_PROMO" value="<?php echo $kmId; ?>">
                <input type="hidden" name="THU_TU" value="<?php echo count($kmOfPackage); ?>">
                <button type="submit" class="text-green-600 hover:text-green-800 font-semibold text-xs w-full">Thêm</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <p class="text-[11px] text-gray-500 mt-2">Nhấp "Thêm" để thêm khuyến mãi vào gói.</p>
  </div>

  <!-- RIGHT PANE: Selected Promotions -->
  <div class="bg-white p-4 rounded shadow">
    <h3 class="text-lg font-semibold text-indigo-700 mb-4">Khuyến mãi của gói</h3>
    <div class="h-72 overflow-y-auto border rounded">
      <table class="min-w-full text-xs">
        <thead class="bg-indigo-100 text-indigo-700 sticky top-0">
          <tr>
            <th class="p-2 text-left">Khuyến mãi</th>
            <th class="p-2 text-center">Giá trị</th>
            <th class="p-2 text-center">Trạng thái</th>
            <th class="p-2 text-center">Hành động</th>
          </tr>
        </thead>
        <tbody id="promotionsBody">
          <?php if (empty($selectedKM)): ?>
            <tr><td colspan="4" class="p-3 text-gray-500 text-center">Chưa có khuyến mãi nào.</td></tr>
          <?php else: ?>
            <?php foreach ($selectedKM as $kmId => $kmData): ?>
              <tr class="border-b">
                <td class="p-2 truncate font-medium" title="<?php echo htmlspecialchars($kmData['TEN_KM']); ?>"><?php echo htmlspecialchars($kmData['TEN_KM']); ?></td>
                <td class="p-2 text-center font-semibold">
                  <?php 
                    if ($kmData['KIEU_KM'] === 'percent') {
                      echo $kmData['GIA_TRI'].'%';
                    } else {
                      echo '₫'.number_format($kmData['GIA_TRI'], 0, ',', '.');
                    }
                  ?>
                </td>
                <td class="p-2 text-center">
                  <?php 
                    $status = $getKMStatus($kmData);
                    $statusClass = '';
                    if (strpos($status, 'Chưa') !== false) {
                      $statusClass = 'text-blue-600';
                    } elseif (strpos($status, 'Đang') !== false) {
                      $statusClass = 'text-green-600';
                    } else {
                      $statusClass = 'text-gray-500';
                    }
                  ?>
                  <span class="<?php echo $statusClass; ?> font-medium"><?php echo htmlspecialchars($status); ?></span>
                </td>
                <td class="p-2 text-center">
                  <form method="POST" class="inline">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pkg_csrf_token()); ?>">
                    <input type="hidden" name="promo_remove" value="1">
                    <input type="hidden" name="ID_GOI" value="<?php echo $idGoi; ?>">
                    <input type="hidden" name="ID_PROMO" value="<?php echo $kmId; ?>">
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

<!-- Modal: Sửa khuyến mãi khả dụng -->
<div id="editPromoModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 p-4 overflow-y-auto">
  <div class="flex items-center justify-center min-h-screen">
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full">
      <div class="p-6 border-b">
        <h2 class="text-xl font-bold text-indigo-700">Sửa khuyến mãi</h2>
        <p class="text-sm text-gray-500">Chỉnh sửa thông tin khuyến mãi khả dụng</p>
      </div>
      <form method="POST" class="p-6 space-y-4">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pkg_csrf_token()); ?>">
        <input type="hidden" name="promo_available_update" value="1">
        <input type="hidden" name="ID_GOI" value="<?php echo $idGoi; ?>">
        <input type="hidden" name="ID_PROMO" id="editPromoId" value="">
        
        <div>
          <label class="block text-sm font-medium mb-1">Tên khuyến mãi *</label>
          <input type="text" name="TEN_KM" id="editPromoName" required class="w-full border rounded px-3 py-2" />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Loại *</label>
            <select name="KIEU_KM" id="editPromoType" required class="w-full border rounded px-3 py-2">
              <option value="percent">Phần trăm (%)</option>
              <option value="fixed">Cố định (₫)</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Giá trị *</label>
            <input type="number" step="0.01" min="0" name="GIA_TRI" id="editPromoValue" required class="w-full border rounded px-3 py-2" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Bắt đầu *</label>
            <input type="datetime-local" name="NGAY_BAT_DAU" id="editPromoStart" required class="w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Kết thúc *</label>
            <input type="datetime-local" name="NGAY_KET_THUC" id="editPromoEnd" required class="w-full border rounded px-3 py-2" />
          </div>
        </div>

        <div class="flex items-center gap-2">
          <input type="checkbox" name="TRANG_THAI" id="editPromoActive" class="rounded">
          <label for="editPromoActive" class="text-sm">Kích hoạt</label>
        </div>

        <div class="flex gap-2 pt-2">
          <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded font-semibold">Lưu</button>
          <button type="button" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 rounded" onclick="closeEditPromo()">Hủy</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const promoLookup = <?php echo json_encode($allKM, JSON_UNESCAPED_UNICODE); ?>;
  function closeEditPromo(){
    const modal = document.getElementById('editPromoModal');
    if (modal) modal.classList.add('hidden');
  }
  function normalizeDt(val){
    if (!val) return '';
    return val.replace(' ', 'T').slice(0,16);
  }
  function openEditPromo(id){
    const data = promoLookup[id];
    if (!data) return;
    document.getElementById('editPromoId').value = id;
    document.getElementById('editPromoName').value = data['TEN_KM'] || '';
    document.getElementById('editPromoType').value = data['KIEU_KM'] || 'percent';
    document.getElementById('editPromoValue').value = data['GIA_TRI'] || '';
    document.getElementById('editPromoStart').value = normalizeDt(data['NGAY_BAT_DAU'] || '');
    document.getElementById('editPromoEnd').value = normalizeDt(data['NGAY_KET_THUC'] || '');
    document.getElementById('editPromoActive').checked = (data['TRANG_THAI'] || '') === 'active';
    const modal = document.getElementById('editPromoModal');
    if (modal) modal.classList.remove('hidden');
  }
</script>