<?php
require_once '../../database/config.php';
require_once __DIR__ . '/../../repositories/PackageRepository.php';
require_once __DIR__ . '/../../repositories/PackageCostumeRepository.php';
require_once __DIR__ . '/../../repositories/CostumePackageMasterRepository.php';

use App\Repositories\PackageRepository;
use App\Repositories\PackageCostumeRepository;
use App\Repositories\CostumePackageMasterRepository;

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$role = (string)($_SESSION['ID_QUYEN'] ?? '');
$staffType = (string)($_SESSION['STAFF_TYPE'] ?? '');
$branchId = isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : 0;
$isBranchManager = ($role === '2' && $staffType === 'quan_ly' && $branchId > 0);

$pkgRepo    = new PackageRepository($conn); // service packages (optional linkage)
$pivotRepo  = new PackageCostumeRepository($conn);
$masterRepo = new CostumePackageMasterRepository($conn);

$idGoi = isset($_GET['id_goi']) ? (int)$_GET['id_goi'] : 0;
if ($idGoi <= 0) {
  die('Thiếu ID gói.');
}

// Master local costume package (must exist now for local creation flow)
$master = $masterRepo->find($idGoi);
$package = $pkgRepo->find($idGoi); // service package may or may not exist

// Chỉ kiểm tra quyền nếu gói dịch vụ tồn tại
// Permission: branch manager can only edit if owns the local package; admin (role=1) can edit all
if ($isBranchManager) {
  $owner = (int)($master['ID_CN_OWNER'] ?? 0);
  if ($owner !== $branchId) {
    die('Bạn không có quyền chỉnh gói trang phục này.');
  }
}

$errors = [];
$success = false;
$infoUpdateSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? 'update_items';

  // Update package basic info (name, description, discount)
  if ($action === 'update_package_info') {
    $newName = trim($_POST['TEN_GOI'] ?? '');
    $newDesc = trim($_POST['MO_TA'] ?? '');
    $newDiscount = isset($_POST['DISCOUNT_PERCENT']) ? (int)$_POST['DISCOUNT_PERCENT'] : 0;
    $newDiscount = max(0, min(100, $newDiscount));

    if ($newName === '') {
      $errors[] = 'Tên gói không được trống.';
    } else {
      try {
        if ($masterRepo->update($idGoi, $newName, $newDesc !== '' ? $newDesc : null, $newDiscount)) {
          $infoUpdateSuccess = true;
          // Reload master data
          $master = $masterRepo->find($idGoi);
        } else {
          $errors[] = 'Cập nhật thông tin gói thất bại.';
        }
      } catch (Exception $e) {
        $errors[] = 'Lỗi: ' . $e->getMessage();
      }
    }
  }
  // Update package items (costumes)
  // Cập nhật danh sách trang phục trong gói
  elseif ($action === 'update_items') {
    // Ưu tiên payload JSON (build từ client) để tránh lỗi index sau khi xóa/kéo thả
    if (!empty($_POST['items_json'])) {
      $items = json_decode($_POST['items_json'], true);
      if (!is_array($items)) {
        $items = [];
      }
    } else {
      $items = $_POST['items'] ?? [];
    }
    $normalized = [];
    foreach ($items as $it) {
      if (empty($it['ID_TRANG_PHUC'])) {
        continue;
      }
      $cid  = (int)$it['ID_TRANG_PHUC'];
      $qty  = max(1, (int)($it['SO_LUONG'] ?? 1));
      $ord  = max(1, (int)($it['THU_TU'] ?? 1));
      $note = trim($it['GHI_CHU'] ?? '');
      $normalized[] = [
        'ID_TRANG_PHUC' => $cid,
        'SO_LUONG'      => $qty,
        'THU_TU'        => $ord,
        'GHI_CHU'       => $note !== '' ? $note : null,
      ];
    }

    if (empty($normalized)) {
      $errors[] = 'Gói phải có ít nhất một trang phục.';
    } else {
      if ($pivotRepo->bulkReplace($idGoi, $normalized)) {
        $success = true;
        // Redirect to refresh page and show updated data from database
        header('Location: ?page=edit_costume_package&id_goi=' . $idGoi . '&saved=1');
        exit;
      } else {
        $errors[] = 'Lưu gói trang phục thất bại, vui lòng thử lại.';
      }
    }
  } // end elseif update_items
}

// Show success message from redirect
if (isset($_GET['saved']) && $_GET['saved'] === '1') {
  $success = true;
}

$rows = $pivotRepo->listCostumes($idGoi);

// Inventory costumes (available) for branch owner to drag into package
$inventory = [];
$branchOwnerForInventory = (int)($master['ID_CN_OWNER'] ?? 0);
if ($branchOwnerForInventory > 0) {
  $stmtInv = $conn->prepare("SELECT ID_TRANG_PHUC, TEN, MAU_SAC, SIZE, GIA_THUE, TRANG_THAI FROM trang_phuc WHERE ID_CN = ? AND TRANG_THAI = 'available' ORDER BY TEN ASC LIMIT 200");
  $stmtInv->bind_param('i', $branchOwnerForInventory);
  if ($stmtInv->execute()) {
    $resInv = $stmtInv->get_result();
    while ($r = $resInv->fetch_assoc()) {
      $inventory[] = $r;
    }
  }
}

?>

<body class="bg-gray-50 p-6">
  <div class="max-w-5xl mx-auto space-y-6">
    <header class="space-y-1">
      <a href="?page=package_costumes" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Quay lại danh sách gói trang phục</a>
      <h1 class="text-2xl font-bold text-indigo-700 flex flex-wrap items-center gap-2">
        <span>Gói trang phục local:</span>
        <span class="text-indigo-900"><?= htmlspecialchars($master['TEN_GOI'] ?? ('#' . $idGoi)) ?></span>
        <span class="text-gray-400">(#<?= (int)$idGoi ?>)</span>
      </h1>
      <p class="text-sm text-gray-500">Chi nhánh sở hữu: <strong><?= (int)($master['ID_CN_OWNER'] ?? 0) ?></strong> &middot; <?= htmlspecialchars($master['MO_TA'] ?? 'Không mô tả') ?></p>
      <?php if ($package): ?>
        <p class="text-xs text-gray-400 italic">Liên kết tùy chọn với gói dịch vụ ID #<?= (int)$package['ID_GOI'] ?> (scope: <?= htmlspecialchars($package['SCOPE_TYPE'] ?? 'global') ?>)</p>
      <?php endif; ?>
    </header>

    <?php if (!empty($errors)): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">
        <?php foreach ($errors as $e): ?>
          <div><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php elseif ($infoUpdateSuccess): ?>
      <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 text-sm">Đã cập nhật thông tin gói.</div>
    <?php elseif ($success): ?>
      <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 text-sm">Đã lưu gói trang phục.</div>
    <?php endif; ?>

    <!-- Package Basic Info Form -->
    <div class="bg-white rounded-2xl shadow p-6 space-y-4">
      <h2 class="text-lg font-semibold text-indigo-700 border-b pb-2">Thông tin gói</h2>
      <form method="post" class="space-y-4">
        <input type="hidden" name="action" value="update_package_info" />

        <div class="grid md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tên gói <span class="text-red-500">*</span></label>
            <input
              type="text"
              name="TEN_GOI"
              value="<?= htmlspecialchars($master['TEN_GOI'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
              required />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Khuyến mãi toàn gói (%)</label>
            <input
              type="number"
              name="DISCOUNT_PERCENT"
              min="0"
              max="100"
              value="<?= (int)($master['DISCOUNT_PERCENT'] ?? 0) ?>"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            <p class="text-xs text-gray-500 mt-1">Giảm giá áp dụng cho tổng giá gói (0-100%)</p>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Mô tả</label>
          <textarea
            name="MO_TA"
            rows="2"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= htmlspecialchars($master['MO_TA'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="flex justify-end">
          <button
            type="submit"
            class="px-5 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium text-sm transition">
            Lưu thông tin gói
          </button>
        </div>
      </form>
    </div>

    <form method="post" class="bg-white rounded-2xl shadow p-6 space-y-4">
      <input type="hidden" name="action" value="update_items" />
      <input type="hidden" name="items_json" id="items_json" />
      <!-- Dual Panel Layout -->
      <div class="grid md:grid-cols-2 gap-6">
        <!-- LEFT PANEL: Available Inventory -->
        <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 space-y-3">
          <div>
            <h3 class="text-sm font-semibold text-indigo-700 mb-2">Kho trang phục khả dụng</h3>
            <p class="text-[11px] text-gray-500">Chi nhánh #<?= (int)$branchOwnerForInventory ?> • <?= count($inventory) ?> mục</p>
            <p class="text-[10px] text-gray-400 mt-1">Kéo sang phải để thêm vào gói</p>
          </div>
          <div id="inventory-pool" class="space-y-2 max-h-[480px] overflow-y-auto">
            <?php foreach ($inventory as $inv): ?>
              <div class="inv-item group border border-gray-200 bg-white rounded-lg p-3 text-xs cursor-move hover:border-indigo-400 hover:shadow-md hover:bg-indigo-50 transition" draggable="true" data-id="<?= (int)$inv['ID_TRANG_PHUC'] ?>" data-label="<?= htmlspecialchars($inv['TEN']) ?>" data-price="<?= (int)$inv['GIA_THUE'] ?>">
                <div class="flex items-start justify-between gap-2 mb-2">
                  <div class="flex-1 min-w-0">
                    <p class="font-semibold text-gray-800 truncate" title="<?= htmlspecialchars($inv['TEN']) ?>">
                      <?= htmlspecialchars($inv['TEN']) ?>
                    </p>
                    <p class="text-gray-500">#<?= (int)$inv['ID_TRANG_PHUC'] ?></p>
                  </div>
                  <span class="text-indigo-600 font-semibold whitespace-nowrap">
                    <?= number_format((int)$inv['GIA_THUE'], 0, ',', '.') ?>₫
                  </span>
                </div>
                <div class="flex gap-2 text-[10px] text-gray-600">
                  <?php if (!empty($inv['MAU_SAC'])): ?>
                    <span class="px-1.5 py-0.5 rounded bg-gray-100">Màu: <?= htmlspecialchars($inv['MAU_SAC']) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($inv['SIZE'])): ?>
                    <span class="px-1.5 py-0.5 rounded bg-gray-100"><?= htmlspecialchars($inv['SIZE']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($inventory)): ?>
              <div class="col-span-full text-center text-xs text-gray-400 py-8">
                <p>Không có trang phục khả dụng</p>
              </div>
            <?php endif; ?>
          </div>

          <!-- Trash Zone for removing items from package -->
          <div id="trash-zone" class="border-2 border-dashed border-red-300 bg-red-50 rounded-xl p-4 text-center transition-all hover:border-red-500 hover:bg-red-100">
            <div class="flex flex-col items-center gap-2">
              <span class="text-3xl"></span>
              <p class="text-xs font-semibold text-red-700">Kéo vào đây để xóa khỏi gói</p>
              <p class="text-[10px] text-red-600">Thả trang phục vào thùng rác để loại bỏ</p>
            </div>
          </div>
        </div>

        <!-- RIGHT PANEL: Package Editor & Stats -->
        <div class="space-y-4">
          <!-- Header -->
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-indigo-700">Trang phục trong gói</h3>
          </div>

          <!-- Package Table -->
          <div class="border rounded-lg overflow-hidden bg-white" id="package-dropzone">
            <div class="overflow-x-auto">
              <table class="min-w-full text-xs">
                <thead class="bg-indigo-50 border-b border-gray-200">
                  <tr class="text-gray-700 font-semibold">
                    <th class="px-2 py-2 text-center w-6"></th>
                    <th class="px-3 py-2 text-left">Trang phục</th>
                    <th class="px-2 py-2 text-center w-12">SL</th>
                    <th class="px-2 py-2 text-center w-12">Thứ tự</th>
                    <th class="px-3 py-2 text-left">Ghi chú</th>
                    <th class="px-2 py-2 text-center w-8"></th>
                  </tr>
                </thead>
                <tbody id="rows-body">
                  <?php foreach ($rows as $index => $r): ?>
                    <tr class="border-t border-gray-100 draggable-row hover:bg-gray-50 transition" draggable="true" data-row-index="<?= $index ?>" data-price="<?= (int)($r['GIA_THUE'] ?? 0) ?>">
                      <td class="px-1 py-2 align-top text-center">
                        <span class="drag-handle cursor-move text-gray-300 hover:text-indigo-600 text-[10px]"><i class="fas fa-grip-vertical"></i></span>
                      </td>
                      <div class="px-3 py-2 align-top">
                        <div class="space-y-1">
                          <input type="hidden" name="items[<?= $index ?>][ID_TRANG_PHUC]" value="<?= (int)$r['ID_TRANG_PHUC'] ?>" class="costume-id-input" />
                          <input type="text" class="costume-search-input border rounded px-2 py-1 w-full text-xs" placeholder="Gõ để tìm" value="<?= htmlspecialchars($r['TEN']) ?>" autocomplete="off" />
                          <div class="text-[9px] text-gray-500 flex items-center gap-1">
                            <span>#<?= (int)$r['ID_TRANG_PHUC'] ?></span>
                            <span>• <?= htmlspecialchars($r['TRANG_THAI'] ?? '') ?></span>
                            <?php $priceRow = (int)($r['GIA_THUE'] ?? 0); ?>
                            <span class="text-emerald-700 font-semibold">• <?= number_format($priceRow, 0, ',', '.') ?>₫</span>
                          </div>
                          <div class="autocomplete-dropdown hidden border rounded bg-white shadow text-xs max-h-32 overflow-y-auto z-20"></div>
                        </div>
                      </div>
                      <td class="px-2 py-2 text-center align-top">
                        <input type="number" min="1" name="items[<?= $index ?>][SO_LUONG]" value="<?= (int)($r['SO_LUONG'] ?? 1) ?>" class="border rounded px-1 py-0.5 w-12 text-xs text-center" />
                      </td>
                      <td class="px-2 py-2 text-center align-top">
                        <input type="number" min="1" name="items[<?= $index ?>][THU_TU]" value="<?= (int)($r['THU_TU'] ?? 1) ?>" class="border rounded px-1 py-0.5 w-12 text-xs text-center" />
                      </td>
                      <td class="px-3 py-2 align-top">
                        <input name="items[<?= $index ?>][GHI_CHU]" value="<?= htmlspecialchars($r['GHI_CHU'] ?? '') ?>" class="border rounded px-1 py-0.5 w-full text-xs" placeholder="Ghi chú" />
                      </td>
                      <td class="px-2 py-2 text-center align-top">
                        <button type="button" class="text-red-600 hover:text-red-800 font-semibold remove-row" title="Xóa">×</button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php if (empty($rows)): ?>
              <div class="text-center text-xs text-gray-400 py-6">
                Chưa có trang phục nào. Thêm từ kho bên trái.
              </div>
            <?php endif; ?>
          </div>

          <!-- Statistics -->
          <div class="bg-indigo-50 rounded-lg p-3 border border-indigo-200 space-y-2">
            <p class="text-xs font-semibold text-indigo-900">Thống kê gói</p>
            <div class="grid grid-cols-3 gap-2 text-xs text-gray-700">
              <div class="bg-white rounded p-2">
                <span class="text-gray-500">Tổng trang phục:</span><br>
                <strong class="text-lg text-indigo-700" id="stat-count"><?= count($rows) ?></strong>
              </div>
              <div class="bg-white rounded p-2">
                <span class="text-gray-500">Giá gốc:</span><br>
                <strong class="text-lg text-emerald-700" id="stat-price-base">0₫</strong>
              </div>
              <div class="bg-white rounded p-2">
                <span class="text-gray-500">Giá sau KM (<?= (int)($master['DISCOUNT_PERCENT'] ?? 0) ?>%):</span><br>
                <strong class="text-lg text-emerald-700" id="stat-price-net">0₫</strong>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
        <a href="?page=package_costumes" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-600 text-sm hover:bg-gray-50 transition">Hủy</a>
        <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700 transition font-medium">Lưu gói trang phục</button>
      </div>
    </form>
  </div>

  <script>
    (function() {
      const body = document.getElementById('rows-body');
      let index = <?= count($rows) ?>;
      let draggingFromInventoryId = null; // fallback when dataTransfer loses payload
      let isDraggingFromInventory = false;
      let draggingFromPackageRow = null; // track row being dragged from package table

      // Tạo mới một dòng trang phục trong bảng gói
      function createRow(options) {
        const opts = Object.assign({
          id: '',
          label: '',
          quantity: 1,
          order: (body.querySelectorAll('tr').length + 1),
          note: '',
          price: 0
        }, options || {});
        const newIndex = index;
        const tr = document.createElement('tr');
        tr.className = 'border-t border-gray-100 draggable-row';
        tr.setAttribute('draggable', 'true');
        tr.setAttribute('data-row-index', newIndex);
        tr.setAttribute('data-price', opts.price || 0);
        const statusText = opts.id ? `Đã chọn #${opts.id}` : 'Chưa chọn trang phục';
        const valueAttr = opts.id ? ` value="${opts.id}"` : '';
        const labelAttr = opts.label ? ` value="${opts.label}"` : '';
        const noteAttr = opts.note ? ` value="${opts.note}"` : '';
        tr.innerHTML = `
          <td class="px-1 py-2 align-top">
            <span class="drag-handle cursor-move text-gray-300 hover:text-indigo-600"><i class="fas fa-grip-vertical"></i></span>
          </td>
          <td class="px-3 py-2 align-top">
            <div class="space-y-1">
              <input type="hidden" name="items[${newIndex}][ID_TRANG_PHUC]" class="costume-id-input"${valueAttr} />
              <input type="text" class="costume-search-input border rounded px-2 py-1 w-full text-xs"${labelAttr} placeholder="Gõ để tìm trang phục" autocomplete="off" />
              <div class="text-[10px] text-gray-400 status-line">${statusText}</div>
              <div class="autocomplete-dropdown hidden border rounded bg-white shadow text-xs max-h-48 overflow-y-auto"></div>
            </div>
          </td>
          <td class="px-3 py-2 text-center">
            <input type="number" min="1" name="items[${newIndex}][SO_LUONG]" value="${opts.quantity}" class="border rounded px-2 py-1 w-16 text-xs text-center" />
          </td>
          <td class="px-3 py-2 text-center">
            <input type="number" min="1" name="items[${newIndex}][THU_TU]" value="${opts.order}" class="border rounded px-2 py-1 w-16 text-xs text-center" />
          </td>
          <td class="px-3 py-2">
            <input name="items[${newIndex}][GHI_CHU]" class="border rounded px-2 py-1 w-full text-xs"${noteAttr} />
          </td>
          <td class="px-3 py-2 text-center">
            <button type="button" class="text-xs text-red-600 hover:underline remove-row">Xóa</button>
          </td>`;
        body.appendChild(tr);
        initAutocompleteForRow(tr);
        index++;
        // Note: reindexAllRows() will be called by renumberOrder() or explicitly after this
        return tr;
      }

      body.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-row')) {
          const tr = e.target.closest('tr');
          if (tr) {
            tr.remove();
            reindexAllRows();
            updateStats();
          }
        }
      });

      // Đánh lại index toàn bộ dòng để đảm bảo liên tục sau khi xóa/kéo
      function reindexAllRows() {
        const rows = Array.from(body.querySelectorAll('tr'));
        rows.forEach((tr, newIndex) => {
          // Update data attribute
          tr.setAttribute('data-row-index', newIndex);

          // Update all input names with new index
          const inputs = tr.querySelectorAll('input[name^="items["]');
          inputs.forEach(input => {
            const currentName = input.getAttribute('name');
            // Replace items[oldIndex] with items[newIndex]
            const newName = currentName.replace(/items\[\d+\]/, `items[${newIndex}]`);
            input.setAttribute('name', newName);
          });
        });
      }

      // Tính lại thống kê số lượng và giá trị tạm tính
      function updateStats() {
        const rows = Array.from(body.querySelectorAll('tr'));
        const count = rows.length;
        document.getElementById('stat-count').textContent = count;

        let totalPrice = 0;
        rows.forEach(tr => {
          const qtyInput = tr.querySelector('input[name$="[SO_LUONG]"]');
          const qty = qtyInput ? parseInt(qtyInput.value) || 0 : 0;
          const unit = parseInt(tr.getAttribute('data-price')) || 0;
          totalPrice += unit * qty;
        });

        const discountPercent = <?= (int)($master['DISCOUNT_PERCENT'] ?? 0) ?>;
        const netPrice = Math.max(0, Math.round(totalPrice * (100 - discountPercent) / 100));

        const priceBaseEl = document.getElementById('stat-price-base');
        const priceNetEl = document.getElementById('stat-price-net');
        if (priceBaseEl) {
          priceBaseEl.textContent = (totalPrice || 0).toLocaleString('vi-VN') + '₫';
        }
        if (priceNetEl) {
          priceNetEl.textContent = (netPrice || 0).toLocaleString('vi-VN') + '₫';
        }
      }

      // Recalculate when quantity changes
      body.addEventListener('input', function(e) {
        if (e.target.name && e.target.name.endsWith('[SO_LUONG]')) {
          updateStats();
        }
      });

      // Initial stats update
      updateStats();
      // Đổi thứ tự bằng kéo thả và đồng bộ lại index/thứ tự
      function renumberOrder() {
        const rows = Array.from(body.querySelectorAll('tr'));
        rows.forEach((tr, i) => {
          const orderInput = tr.querySelector('input[name^="items"][name$="[THU_TU]"]');
          if (orderInput) {
            orderInput.value = i + 1;
          }
          tr.classList.remove('drag-over');
        });
        reindexAllRows(); // Also reindex when reordering
      }

      let dragSrc = null;
      body.addEventListener('dragstart', function(e) {
        const tr = e.target.closest('.draggable-row');
        if (!tr) return;
        dragSrc = tr;
        draggingFromPackageRow = tr;
        tr.classList.add('opacity-50');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', 'package-row');
      });
      body.addEventListener('dragend', function(e) {
        const tr = e.target.closest('.draggable-row');
        if (tr) tr.classList.remove('opacity-50');
        draggingFromPackageRow = null;
        renumberOrder();
      });
      body.addEventListener('dragover', function(e) {
        e.preventDefault();
        const tr = e.target.closest('.draggable-row');
        if (!tr || tr === dragSrc) return;
        tr.classList.add('drag-over');
      });
      body.addEventListener('dragleave', function(e) {
        const tr = e.target.closest('.draggable-row');
        if (tr) tr.classList.remove('drag-over');
      });
      body.addEventListener('drop', function(e) {
        e.preventDefault();
        const tr = e.target.closest('.draggable-row');
        if (!tr || tr === dragSrc) return;
        tr.classList.remove('drag-over');
        const rows = Array.from(body.querySelectorAll('.draggable-row'));
        const srcIndex = rows.indexOf(dragSrc);
        const targetIndex = rows.indexOf(tr);
        if (srcIndex < targetIndex) {
          tr.after(dragSrc);
        } else {
          tr.before(dragSrc);
        }
        renumberOrder();
      });

      // Drag from inventory to create new row
      const inventoryPool = document.getElementById('inventory-pool');
      inventoryPool.addEventListener('dragstart', function(e) {
        const item = e.target.closest('.inv-item');
        if (!item) return;
        draggingFromInventoryId = item.getAttribute('data-id');
        isDraggingFromInventory = true;
        e.dataTransfer.setData('text/plain', draggingFromInventoryId);
        e.dataTransfer.setData('application/x-costume-id', draggingFromInventoryId);
        e.dataTransfer.effectAllowed = 'copyMove';
      });
      inventoryPool.addEventListener('dragend', function() {
        isDraggingFromInventory = false;
        draggingFromInventoryId = null;
      });

      // Trash zone drag & drop handling
      const trashZone = document.getElementById('trash-zone');

      trashZone.addEventListener('dragover', function(e) {
        if (draggingFromPackageRow) {
          e.preventDefault();
          e.dataTransfer.dropEffect = 'move';
          trashZone.classList.add('scale-105', 'ring-2', 'ring-red-500');
        }
      });

      trashZone.addEventListener('dragleave', function(e) {
        trashZone.classList.remove('scale-105', 'ring-2', 'ring-red-500');
      });

      trashZone.addEventListener('drop', function(e) {
        e.preventDefault();
        trashZone.classList.remove('scale-105', 'ring-2', 'ring-red-500');

        if (draggingFromPackageRow) {
          // Remove the row from package
          draggingFromPackageRow.remove();
          draggingFromPackageRow = null;
          dragSrc = null;
          reindexAllRows();
          updateStats();

          // Visual feedback
          trashZone.classList.add('bg-red-200');
          setTimeout(() => trashZone.classList.remove('bg-red-200'), 300);
        }
      });

      const tableWrapper = document.querySelector('table');
      const dropZone = document.getElementById('package-dropzone');

      function allowDrop(e) {
        if (isDraggingFromInventory) {
          e.preventDefault();
          if (e.dataTransfer) {
            e.dataTransfer.dropEffect = 'copy';
          }
        }
      }

      // Nhận trang phục kéo từ kho vào gói
      function handleDrop(e) {
        e.preventDefault();
        const id = e.dataTransfer.getData('text/plain') || e.dataTransfer.getData('application/x-costume-id') || draggingFromInventoryId;
        draggingFromInventoryId = null;
        if (!id) return;
        const exists = Array.from(body.querySelectorAll('.costume-id-input')).some(h => h.value === id);
        if (exists) {
          const dupRow = Array.from(body.querySelectorAll('.costume-id-input')).find(h => h.value === id).closest('tr');
          dupRow.classList.add('ring-2', 'ring-red-400');
          setTimeout(() => dupRow.classList.remove('ring-2', 'ring-red-400'), 800);
          return;
        }
        const srcEl = inventoryPool.querySelector('.inv-item[data-id="' + id + '"]');
        const label = srcEl ? srcEl.getAttribute('data-label') : ('Trang phục #' + id);
        const price = srcEl ? parseInt(srcEl.getAttribute('data-price')) || 0 : 0;
        createRow({
          id,
          label,
          price
        });
        renumberOrder();
        updateStats();
      }

      [tableWrapper, body, dropZone].forEach(zone => {
        if (!zone) return;
        zone.addEventListener('dragover', allowDrop);
        zone.addEventListener('drop', handleDrop);
      });

      // Autocomplete logic
      const branchOwner = <?= (int)($master['ID_CN_OWNER'] ?? 0) ?>;

      function debounce(fn, ms) {
        let t;
        return function(...args) {
          clearTimeout(t);
          t = setTimeout(() => fn.apply(this, args), ms);
        };
      }

      function fetchSuggestions(term, cb) {
        const url = `./components/ajax_costume_search.php?branch=${branchOwner}&term=${encodeURIComponent(term)}`;
        fetch(url, {
            headers: {
              'Accept': 'application/json'
            }
          })
          .then(r => r.json())
          .then(data => cb(Array.isArray(data) ? data : []))
          .catch(() => cb([]));
      }

      function renderDropdown(dropdown, items) {
        if (!items.length) {
          dropdown.innerHTML = '<div class="px-2 py-1 text-gray-400">Không có kết quả</div>';
          return;
        }
        dropdown.innerHTML = items.map(it => `<button type="button" data-id="${it.id}" class="block w-full text-left px-2 py-1 hover:bg-indigo-50">
          <span class="font-medium">${it.ten}</span>
          <span class="text-[10px] text-gray-500">#${it.id} • ${it.mau||''} ${it.size||''} • ${it.trang_thai}</span>
        </button>`).join('');
      }

      function initAutocompleteForRow(tr) {
        const searchInput = tr.querySelector('.costume-search-input');
        const hiddenId = tr.querySelector('.costume-id-input');
        const statusLine = tr.querySelector('.status-line');
        const dropdown = tr.querySelector('.autocomplete-dropdown');
        if (!searchInput) return;

        const doSearch = debounce(function() {
          const term = searchInput.value.trim();
          if (term.length < 1) {
            dropdown.classList.add('hidden');
            return;
          }
          fetchSuggestions(term, items => {
            renderDropdown(dropdown, items);
            dropdown.classList.remove('hidden');
          });
        }, 300);

        searchInput.addEventListener('input', () => {
          hiddenId.value = '';
          statusLine && (statusLine.textContent = 'Đang gõ...');
          doSearch();
        });
        searchInput.addEventListener('focus', () => {
          if (searchInput.value.trim() !== '') {
            doSearch();
          }
        });
        document.addEventListener('click', (e) => {
          if (!tr.contains(e.target)) {
            dropdown.classList.add('hidden');
          }
        });
        dropdown.addEventListener('click', e => {
          const btn = e.target.closest('button[data-id]');
          if (!btn) return;
          const id = btn.getAttribute('data-id');
          hiddenId.value = id;
          const label = btn.querySelector('.font-medium').textContent;
          searchInput.value = label;
          dropdown.classList.add('hidden');
          statusLine && (statusLine.textContent = 'Đã chọn #' + id);
        });
      }

      // Init existing rows
      body.querySelectorAll('tr').forEach(initAutocompleteForRow);
      renumberOrder();

      // Validate before submit
      // Ensure we bind to the update_items form (the one that contains the package table)
      const form = document.querySelector('form input[name="action"][value="update_items"]')?.closest('form');
      if (!form) return;

      form.addEventListener('submit', function(e) {
        let invalid = [];
        const rowsData = [];
        body.querySelectorAll('tr').forEach((tr, idx) => {
          const hid = tr.querySelector('.costume-id-input');
          const qtyInput = tr.querySelector('input[name$="[SO_LUONG]"]');
          const orderInput = tr.querySelector('input[name$="[THU_TU]"]');
          const noteInput = tr.querySelector('input[name$="[GHI_CHU]"]');

          if (hid && !hid.value) {
            invalid.push(tr);
            tr.classList.add('bg-red-50');
            return;
          }

          rowsData.push({
            ID_TRANG_PHUC: hid ? parseInt(hid.value, 10) : null,
            SO_LUONG: qtyInput ? parseInt(qtyInput.value, 10) || 1 : 1,
            THU_TU: orderInput ? parseInt(orderInput.value, 10) || (idx + 1) : (idx + 1),
            GHI_CHU: noteInput ? noteInput.value : ''
          });
        });

        if (invalid.length) {
          e.preventDefault();
          alert('Có dòng chưa chọn trang phục. Vui lòng chọn trước khi lưu.');
          return;
        }

        if (!rowsData.length) {
          e.preventDefault();
          alert('Gói phải có ít nhất một trang phục.');
          return;
        }

        const hiddenJson = document.getElementById('items_json');
        if (hiddenJson) {
          hiddenJson.value = JSON.stringify(rowsData);
        }
      });
    })();
  </script>
  <style>
    .drag-over {
      outline: 2px dashed #6366f1;
    }

    #inventory-pool .inv-item {
      user-select: none;
    }

    #inventory-pool .inv-item:active {
      opacity: .7;
    }
  </style>
</body>