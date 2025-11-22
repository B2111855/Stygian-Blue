<?php
include '../../database/config.php';
require_once __DIR__ . '/../../repositories/PackageRepository.php';
require_once __DIR__ . '/../../repositories/ServiceRepository.php';
require_once __DIR__ . '/../../helpers/system_log.php';
use App\Repositories\PackageRepository;
use App\Repositories\ServiceRepository;

date_default_timezone_set('Asia/Ho_Chi_Minh');
$pkgRepo = new PackageRepository($conn);
$svcRepo = new ServiceRepository($conn);

// Inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page   = isset($_GET['p']) ? max(1,(int)$_GET['p']) : 1;
$limit  = 5;
$offset = ($page-1)*$limit;
$totalRows  = $pkgRepo->countPackages($search);
$totalPages = max(1,(int)ceil($totalRows/$limit));
$packages   = $pkgRepo->searchPackages($search,$limit,$offset);

function uploadPkgImage(string $field): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error']!==UPLOAD_ERR_OK) return null;
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $mime = mime_content_type($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) return null;
    $ext  = $allowed[$mime];
    $name = bin2hex(random_bytes(8)).'.'.$ext;
    $dirFS = $_SERVER['DOCUMENT_ROOT'].'/public/images/combo/';
    if (!is_dir($dirFS)) mkdir($dirFS,0777,true);
    if (!move_uploaded_file($_FILES[$field]['tmp_name'],$dirFS.$name)) return null;
    return 'public/images/combo/'.$name;
}

// Create
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_package'])) {
    $payload = [
        'TEN_GOI'       => trim($_POST['TEN_GOI'] ?? ''),
        'MO_TA'         => trim($_POST['MO_TA'] ?? ''),
        'HIEU_LUC_TU'   => $_POST['HIEU_LUC_TU'] ?: null,
        'HIEU_LUC_DEN'  => $_POST['HIEU_LUC_DEN'] ?: null,
    ];
    if ($payload['TEN_GOI']==='' || $payload['MO_TA']==='') {
        $errorMessage = 'Dữ liệu gói không hợp lệ.';
    } else {
        try {
            $img = uploadPkgImage('HINH_ANH');
      $newId = $pkgRepo->create($payload,$img);
      $successMessage = 'Gói đã tạo (ID '.$newId.')';
      record_system_log($conn,'PACKAGE_CREATE','package:'.$newId,null,[
        'ID_GOI'=>$newId,
        'TEN_GOI'=>$payload['TEN_GOI'],
        'TRANG_THAI'=>'nhap'
      ]);
        } catch (\Throwable $e) { $errorMessage = 'Lỗi tạo gói: '.$e->getMessage(); }
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
    if ($pkgRepo->update($payload,$img)) {
      $after = $pkgRepo->find($payload['ID_GOI']);
      $successMessage = 'Cập nhật gói thành công.';
      record_system_log($conn,'PACKAGE_UPDATE','package:'.$payload['ID_GOI'],$before,$after);
    } else { $errorMessage = 'Không cập nhật được gói.'; }
  }
}

// Change status (publish / retire)
if (isset($_GET['status']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $st = $_GET['status'];
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
    if ($pkgRepo->changeStatus($id,'ban')) {
      $after = $pkgRepo->find($id);
      $successMessage='Gói đã chuyển sang bán.';
      record_system_log($conn,'PACKAGE_PUBLISH','package:'.$id,$before,$after);
    } else $errorMessage='Không chuyển trạng thái.';
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

if ($isEditing) {
    $editPackage = $pkgRepo->find((int)$_GET['edit']);
    if ($editPackage) { $editServices = $pkgRepo->listServices((int)$editPackage['ID_GOI']); }
    // Load all active services for selection
  $allServices = $svcRepo->searchServices('',200,0);
  $existingIds = array_column($editServices,'ID_DV');
  // Price history inside package edit
  $priceHistory = [];
  $historyService = null;
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
    <h1 class="text-3xl font-bold text-indigo-700 mb-6 text-center">Quản lý gói dịch vụ</h1>
    <?php if(isset($successMessage)): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= $successMessage ?></div><?php endif; ?>
    <?php if(isset($errorMessage)): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= $errorMessage ?></div><?php endif; ?>

    <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
      <form method="GET" class="flex items-center gap-2">
        <input type="hidden" name="page" value="packages" />
        <input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 Tìm tên/mô tả" class="p-2 border rounded w-64" />
        <button class="bg-indigo-600 text-white px-4 py-2 rounded">Tìm</button>
      </form>
      <a href="?page=packages&add=true" class="bg-green-600 text-white px-4 py-2 rounded">Thêm gói</a>
    </div>

    <?php if(isset($_GET['add']) && $_GET['add']==='true' && !$isEditing): ?>
      <form method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded shadow mb-6">
        <h2 class="text-xl font-semibold text-indigo-600 mb-4">Thêm gói dịch vụ</h2>
        <div class="grid md:grid-cols-2 gap-4">
          <input name="TEN_GOI" placeholder="Tên gói" class="p-2 border rounded" required />
          <input type="datetime-local" name="HIEU_LUC_TU" class="p-2 border rounded" />
          <input type="datetime-local" name="HIEU_LUC_DEN" class="p-2 border rounded" />
          <input type="file" name="HINH_ANH" class="p-2 border rounded" />
          <textarea name="MO_TA" placeholder="Mô tả" class="p-2 border rounded md:col-span-2" required></textarea>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <button type="submit" name="add_package" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Lưu</button>
          <a href="?page=packages" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Hủy</a>
        </div>
      </form>
    <?php endif; ?>

    <?php if($isEditing && $editPackage): ?>
      <form method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded shadow mb-6">
        <h2 class="text-xl font-semibold text-indigo-600 mb-4">Cập nhật gói</h2>
        <input type="hidden" name="ID_GOI" value="<?= $editPackage['ID_GOI'] ?>" />
        <div class="grid md:grid-cols-2 gap-4">
          <input name="TEN_GOI" value="<?= htmlspecialchars($editPackage['TEN_GOI']) ?>" class="p-2 border rounded" required />
          <input type="datetime-local" name="HIEU_LUC_TU" value="<?= $editPackage['HIEU_LUC_TU'] ? date('Y-m-d\TH:i', strtotime($editPackage['HIEU_LUC_TU'])) : '' ?>" class="p-2 border rounded" />
          <input type="datetime-local" name="HIEU_LUC_DEN" value="<?= $editPackage['HIEU_LUC_DEN'] ? date('Y-m-d\TH:i', strtotime($editPackage['HIEU_LUC_DEN'])) : '' ?>" class="p-2 border rounded" />
          <input type="file" name="HINH_ANH" class="p-2 border rounded" />
          <textarea name="MO_TA" class="p-2 border rounded md:col-span-2" required><?= htmlspecialchars($editPackage['MO_TA']) ?></textarea>
        </div>
        <?php if($editPackage['HINH_ANH']): ?><div class="mt-4"><img src="/<?= $editPackage['HINH_ANH'] ?>" class="w-32 h-32 object-cover rounded" /></div><?php endif; ?>
        <div class="mt-4 flex justify-end gap-2">
          <button type="submit" name="edit_package" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Lưu</button>
          <a href="?page=packages" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Hủy</a>
        </div>
      </form>

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
                  <tr class="border-b" data-id-dv="<?= $row['ID_DV'] ?>" draggable="true" data-thoi-gian="<?= (int)$row['THOI_GIAN'] ?>" data-gia-base="<?= (int)($row['DON_GIA'] ?? 0) ?>">
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
            <div><span class="font-semibold">Giá mặc định:</span> <span id="sumDefault">0</span></div>
            <div><span class="font-semibold">Giá áp dụng:</span> <span id="sumApplied">0</span></div>
            <div><span class="font-semibold">Tiết kiệm:</span> <span id="sumSavings">0 (0%)</span></div>
          </div>
          <form method="POST" class="mt-3 flex items-center gap-2">
            <input type="hidden" name="ID_GOI" value="<?= $editPackage['ID_GOI'] ?>" />
            <input type="hidden" name="bulk_payload" id="bulkPayload" />
            <button type="submit" name="bulk_update_services" id="bulkSaveBtn" class="bg-green-600 text-white px-4 py-2 rounded disabled:opacity-40" disabled>Lưu thay đổi</button>
            <span class="text-[11px] text-gray-500" id="bulkSummary"></span>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <!-- Package list -->
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
                <span class="px-2 py-1 rounded text-xs font-semibold <?php echo $st==='ban'?'bg-green-100 text-green-800':($st==='nhap'?'bg-yellow-100 text-yellow-800':'bg-gray-300 text-gray-700'); ?>"><?= htmlspecialchars($st) ?></span>
              </td>
              <td class="p-3"><?= $pkg['TONG_GIA_GOI']? number_format($pkg['TONG_GIA_GOI'],0,',','.') : '—' ?></td>
              <td class="p-3 flex justify-center gap-2 text-xs">
                <a href="?page=packages&edit=<?= $pkg['ID_GOI'] ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white px-2 py-1 rounded">Sửa</a>
                <?php if($st!=='ban'): ?>
                  <a href="?page=packages&id=<?= $pkg['ID_GOI'] ?>&status=ban" class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded">Đăng</a>
                <?php endif; ?>
                <?php if($st!=='ngung'): ?>
                  <a href="?page=packages&id=<?= $pkg['ID_GOI'] ?>&status=ngung" onclick="return confirm('Ngừng gói?')" class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded">Ngừng</a>
                <?php endif; ?>
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
      (function(){
        const avail = document.getElementById('availableServices');
        const body = document.getElementById('packageServicesBody');
        const bulkBtn = document.getElementById('bulkSaveBtn');
        const bulkPayload = document.getElementById('bulkPayload');
        const bulkSummary = document.getElementById('bulkSummary');
        const sumCountEl = document.getElementById('sumCount');
        const sumDurationEl = document.getElementById('sumDuration');
        const sumDefaultEl = document.getElementById('sumDefault');
        const sumAppliedEl = document.getElementById('sumApplied');
        const sumSavingsEl = document.getElementById('sumSavings');
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
          sumCountEl.textContent = items.length;
          sumDurationEl.textContent = totalDuration + ' phút';
          sumDefaultEl.textContent = new Intl.NumberFormat('vi-VN').format(totalDefault);
          sumAppliedEl.textContent = new Intl.NumberFormat('vi-VN').format(totalApplied);
          let savings = totalDefault - totalApplied;
            let percent = totalDefault>0 ? (savings/totalDefault*100) : 0;
            sumSavingsEl.textContent = new Intl.NumberFormat('vi-VN').format(savings)+' ('+percent.toFixed(1)+'%)';
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
    </script>
</body>
