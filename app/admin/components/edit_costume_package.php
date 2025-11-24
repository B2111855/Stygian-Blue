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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $items = $_POST['items'] ?? [];
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
        } else {
            $errors[] = 'Lưu gói trang phục thất bại, vui lòng thử lại.';
        }
    }
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
    while ($r = $resInv->fetch_assoc()) { $inventory[] = $r; }
  }
}

?>
<body class="bg-gray-50 p-6">
  <div class="max-w-5xl mx-auto space-y-6">
    <header class="space-y-1">
      <a href="?page=package_costumes" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Quay lại danh sách gói trang phục</a>
      <h1 class="text-2xl font-bold text-indigo-700 flex flex-wrap items-center gap-2">
        <span>Gói trang phục local:</span>
        <span class="text-indigo-900"><?= htmlspecialchars($master['TEN_GOI'] ?? ('#'.$idGoi)) ?></span>
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
    <?php elseif ($success): ?>
      <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 text-sm">Đã lưu gói trang phục.</div>
    <?php endif; ?>

    <form method="post" class="bg-white rounded-2xl shadow p-4 space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-gray-800">Danh sách trang phục trong gói</h2>
        <button type="button" id="add-row" class="px-3 py-1.5 rounded-lg border border-indigo-200 text-indigo-700 text-sm hover:bg-indigo-50">+ Thêm dòng</button>
      </div>

      <div class="border rounded-xl p-4 bg-gray-50 space-y-3">
        <div class="flex items-center justify-between flex-wrap gap-2">
          <h3 class="text-sm font-semibold text-indigo-700">Kho trang phục khả dụng (kéo vào bảng để thêm)</h3>
          <span class="text-[11px] text-gray-500">Chi nhánh #<?= (int)$branchOwnerForInventory ?> • <?= count($inventory) ?> mục</span>
        </div>
        <div id="inventory-pool" class="grid gap-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
          <?php foreach ($inventory as $inv): ?>
            <div class="inv-item group border border-gray-200 bg-white rounded-lg p-2 text-xs space-y-1 cursor-move hover:border-indigo-300 hover:shadow" draggable="true" data-id="<?= (int)$inv['ID_TRANG_PHUC'] ?>" data-label="<?= htmlspecialchars($inv['TEN']) ?>">
              <div class="flex items-center justify-between">
                <span class="font-medium text-gray-800 truncate w-40" title="<?= htmlspecialchars($inv['TEN']) ?>"><?= htmlspecialchars($inv['TEN']) ?></span>
                <span class="text-[9px] text-gray-400">#<?= (int)$inv['ID_TRANG_PHUC'] ?></span>
              </div>
              <div class="flex items-center justify-between text-[10px] text-gray-500">
                <span><?= htmlspecialchars($inv['MAU_SAC'] ?? '') ?> <?= htmlspecialchars($inv['SIZE'] ?? '') ?></span>
                <span class="text-indigo-600 font-semibold"><?= number_format((int)$inv['GIA_THUE'],0,',','.') ?>₫</span>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (empty($inventory)): ?>
            <div class="col-span-full text-center text-xs text-gray-400 py-4">Không có trang phục khả dụng.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 select-none">
            <tr class="text-gray-600">
              <th class="px-3 py-2 text-left w-7"></th>
              <th class="px-3 py-2 text-left w-64">Trang phục (tìm & chọn)</th>
              <th class="px-3 py-2 text-center">Số lượng</th>
              <th class="px-3 py-2 text-center">Thứ tự</th>
              <th class="px-3 py-2 text-left">Ghi chú</th>
              <th class="px-3 py-2 text-center">Xóa</th>
            </tr>
          </thead>
          <tbody id="rows-body">
            <?php foreach ($rows as $index => $r): ?>
              <tr class="border-t border-gray-100 draggable-row" draggable="true" data-row-index="<?= $index ?>">
                <td class="px-1 py-2 align-top">
                  <span class="drag-handle cursor-move text-gray-400 hover:text-indigo-600"><i class="fas fa-grip-vertical"></i></span>
                </td>
                <td class="px-3 py-2 align-top">
                  <div class="space-y-1">
                    <input type="hidden" name="items[<?= $index ?>][ID_TRANG_PHUC]" value="<?= (int)$r['ID_TRANG_PHUC'] ?>" class="costume-id-input" />
                    <input type="text" class="costume-search-input border rounded px-2 py-1 w-full text-xs" placeholder="Gõ để tìm trang phục" value="<?= htmlspecialchars($r['TEN']) ?>" autocomplete="off" />
                    <div class="text-[10px] text-gray-400">
                      ID: <?= (int)$r['ID_TRANG_PHUC'] ?> • Trạng thái: <?= htmlspecialchars($r['TRANG_THAI'] ?? '') ?>
                    </div>
                    <div class="autocomplete-dropdown hidden border rounded bg-white shadow text-xs max-h-48 overflow-y-auto"></div>
                  </div>
                </td>
                <td class="px-3 py-2 text-center">
                  <input type="number" min="1" name="items[<?= $index ?>][SO_LUONG]" value="<?= (int)($r['SO_LUONG'] ?? 1) ?>" class="border rounded px-2 py-1 w-16 text-xs text-center" />
                </td>
                <td class="px-3 py-2 text-center">
                  <input type="number" min="1" name="items[<?= $index ?>][THU_TU]" value="<?= (int)($r['THU_TU'] ?? 1) ?>" class="border rounded px-2 py-1 w-16 text-xs text-center" />
                </td>
                <td class="px-3 py-2">
                  <input name="items[<?= $index ?>][GHI_CHU]" value="<?= htmlspecialchars($r['GHI_CHU'] ?? '') ?>" class="border rounded px-2 py-1 w-full text-xs" />
                </td>
                <td class="px-3 py-2 text-center">
                  <button type="button" class="text-xs text-red-600 hover:underline remove-row">Xóa</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="flex justify-end gap-3">
        <a href="?page=package_costumes" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-600 text-sm">Hủy</a>
        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700">Lưu gói trang phục</button>
      </div>
    </form>
  </div>

  <script>
    (function () {
      const body = document.getElementById('rows-body');
      const addBtn = document.getElementById('add-row');
      let index = <?= count($rows) ?>;

      function createRow(options){
        const opts = Object.assign({ id:'', label:'', quantity:1, order: (body.querySelectorAll('tr').length + 1), note:'' }, options||{});
        const newIndex = index;
        const tr = document.createElement('tr');
        tr.className = 'border-t border-gray-100 draggable-row';
        tr.setAttribute('draggable','true');
        tr.setAttribute('data-row-index', newIndex);
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
        return tr;
      }

      addBtn.addEventListener('click', function () { createRow(); });

      body.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-row')) {
          const tr = e.target.closest('tr');
          if (tr) tr.remove();
        }
      });
      // Drag & drop reorder logic
      function renumberOrder(){
        const rows = Array.from(body.querySelectorAll('tr'));
        rows.forEach((tr,i)=>{
          const orderInput = tr.querySelector('input[name^="items"][name$="[THU_TU]"]');
          if(orderInput){ orderInput.value = i+1; }
          tr.classList.remove('drag-over');
        });
      }

      let dragSrc = null;
      body.addEventListener('dragstart', function(e){
        const tr = e.target.closest('.draggable-row');
        if(!tr) return;
        dragSrc = tr;
        tr.classList.add('opacity-50');
        e.dataTransfer.effectAllowed = 'move';
      });
      body.addEventListener('dragend', function(e){
        const tr = e.target.closest('.draggable-row');
        if(tr) tr.classList.remove('opacity-50');
        renumberOrder();
      });
      body.addEventListener('dragover', function(e){
        e.preventDefault();
        const tr = e.target.closest('.draggable-row');
        if(!tr || tr===dragSrc) return;
        tr.classList.add('drag-over');
      });
      body.addEventListener('dragleave', function(e){
        const tr = e.target.closest('.draggable-row');
        if(tr) tr.classList.remove('drag-over');
      });
      body.addEventListener('drop', function(e){
        e.preventDefault();
        const tr = e.target.closest('.draggable-row');
        if(!tr || tr===dragSrc) return;
        tr.classList.remove('drag-over');
        const rows = Array.from(body.querySelectorAll('.draggable-row'));
        const srcIndex = rows.indexOf(dragSrc);
        const targetIndex = rows.indexOf(tr);
        if(srcIndex < targetIndex){
          tr.after(dragSrc);
        } else {
          tr.before(dragSrc);
        }
        renumberOrder();
      });

      // Drag from inventory to create new row
      const inventoryPool = document.getElementById('inventory-pool');
      inventoryPool.addEventListener('dragstart', function(e){
        const item = e.target.closest('.inv-item');
        if(!item) return;
        e.dataTransfer.setData('text/plain', item.getAttribute('data-id'));
        e.dataTransfer.effectAllowed = 'copy';
      });

      const tableWrapper = document.querySelector('table');
      tableWrapper.addEventListener('dragover', function(e){
        if(e.dataTransfer.types.includes('text/plain')){ e.preventDefault(); }
      });
      tableWrapper.addEventListener('drop', function(e){
        const id = e.dataTransfer.getData('text/plain');
        if(!id) return;
        // Check duplicate
        const exists = Array.from(body.querySelectorAll('.costume-id-input')).some(h=>h.value === id);
        if(exists){
          const dupRow = Array.from(body.querySelectorAll('.costume-id-input')).find(h=>h.value===id).closest('tr');
          dupRow.classList.add('ring-2','ring-red-400');
          setTimeout(()=>dupRow.classList.remove('ring-2','ring-red-400'),800);
          return;
        }
        // Find label from inventory element
        const srcEl = inventoryPool.querySelector('.inv-item[data-id="'+id+'"]');
        const label = srcEl ? srcEl.getAttribute('data-label') : ('Trang phục #' + id);
        createRow({ id, label });
        renumberOrder();
      });

      // Autocomplete logic
      const branchOwner = <?= (int)($master['ID_CN_OWNER'] ?? 0) ?>;
      function debounce(fn, ms){ let t; return function(...args){ clearTimeout(t); t=setTimeout(()=>fn.apply(this,args), ms); }; }

      function fetchSuggestions(term, cb){
        const url = `./components/ajax_costume_search.php?branch=${branchOwner}&term=${encodeURIComponent(term)}`;
        fetch(url, { headers: { 'Accept': 'application/json' } })
          .then(r => r.json())
          .then(data => cb(Array.isArray(data) ? data : []))
          .catch(()=> cb([]));
      }

      function renderDropdown(dropdown, items){
        if (!items.length){ dropdown.innerHTML = '<div class="px-2 py-1 text-gray-400">Không có kết quả</div>'; return; }
        dropdown.innerHTML = items.map(it => `<button type="button" data-id="${it.id}" class="block w-full text-left px-2 py-1 hover:bg-indigo-50">
          <span class="font-medium">${it.ten}</span>
          <span class="text-[10px] text-gray-500">#${it.id} • ${it.mau||''} ${it.size||''} • ${it.trang_thai}</span>
        </button>`).join('');
      }

      function initAutocompleteForRow(tr){
        const searchInput = tr.querySelector('.costume-search-input');
        const hiddenId = tr.querySelector('.costume-id-input');
        const statusLine = tr.querySelector('.status-line');
        const dropdown = tr.querySelector('.autocomplete-dropdown');
        if(!searchInput) return;

        const doSearch = debounce(function(){
          const term = searchInput.value.trim();
            if(term.length < 1){ dropdown.classList.add('hidden'); return; }
            fetchSuggestions(term, items => {
              renderDropdown(dropdown, items);
              dropdown.classList.remove('hidden');
            });
        }, 300);

        searchInput.addEventListener('input', ()=> { hiddenId.value=''; statusLine && (statusLine.textContent='Đang gõ...'); doSearch(); });
        searchInput.addEventListener('focus', ()=> { if(searchInput.value.trim()!==''){ doSearch(); } });
        document.addEventListener('click', (e)=>{ if(!tr.contains(e.target)){ dropdown.classList.add('hidden'); } });
        dropdown.addEventListener('click', e=>{
          const btn = e.target.closest('button[data-id]');
          if(!btn) return;
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
      const form = document.querySelector('form[method="post"]');
      form.addEventListener('submit', function(e){
        let invalid = [];
        body.querySelectorAll('tr').forEach(tr => {
          const hid = tr.querySelector('.costume-id-input');
          if(hid && !hid.value){ invalid.push(tr); tr.classList.add('bg-red-50'); }
        });
        if(invalid.length){
          e.preventDefault();
          alert('Có dòng chưa chọn trang phục. Vui lòng chọn trước khi lưu.');
        }
      });
    })();
  </script>
  <style>
    .drag-over { outline: 2px dashed #6366f1; }
    #inventory-pool .inv-item { user-select: none; }
    #inventory-pool .inv-item:active { opacity: .7; }
  </style>
</body>
