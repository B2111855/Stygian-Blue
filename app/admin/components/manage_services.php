<?php
include '../../database/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once __DIR__ . '/../../repositories/ServiceRepository.php';
require_once __DIR__ . '/../../services/DeletionPolicyService.php';
require_once __DIR__ . '/../../helpers/system_log.php';
use App\Repositories\ServiceRepository;
use App\Services\DeletionPolicyService;

$repo   = new ServiceRepository($conn);
$policy = new DeletionPolicyService($conn);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
if (!in_array($statusFilter, ['all','active','draft','retired'], true)) { $statusFilter = 'all'; }
// Advanced filters
$minPrice = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? max(0,(int)$_GET['min_price']) : null;
$maxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? max(0,(int)$_GET['max_price']) : null;
$minDuration = isset($_GET['min_duration']) && $_GET['min_duration'] !== '' ? max(0,(int)$_GET['min_duration']) : null;
$maxDuration = isset($_GET['max_duration']) && $_GET['max_duration'] !== '' ? max(0,(int)$_GET['max_duration']) : null;
$page   = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit  = 5;
$offset = ($page - 1) * $limit;
$totalRows  = $repo->countServices($search, $statusFilter==='all'? null : $statusFilter, $minPrice, $maxPrice, $minDuration, $maxDuration);
$totalPages = max(1, (int)ceil($totalRows / $limit));
$services   = $repo->searchServices($search, $limit, $offset, $statusFilter==='all'? null : $statusFilter, $minPrice, $maxPrice, $minDuration, $maxDuration);

// Price history modal data
$priceHistory = [];
$historyService = null;
if (isset($_GET['history'])) {
    $historyId = (int)$_GET['history'];
    $historyService = $repo->findById($historyId);
    if ($historyService) {
        $priceHistory = $repo->getPriceHistory($historyId, 50);
    }
}

// Inline AJAX update (duration & price)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_inline_update'])) {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)($_POST['ID_DV'] ?? 0);
    $newDuration = (int)($_POST['THOI_GIAN'] ?? 0); // minutes
    $newPrice = isset($_POST['GIA']) ? (int)$_POST['GIA'] : null;
    $current = $repo->findById($id);
    if (!$current) {
        echo json_encode(['ok'=>false,'error'=>'Không tìm thấy dịch vụ']);
        exit;
    }
    if ($newDuration <= 0) {
        echo json_encode(['ok'=>false,'error'=>'Thời lượng phải > 0']);
        exit;
    }
    if ($newPrice !== null && $newPrice <= 0) {
        echo json_encode(['ok'=>false,'error'=>'Giá phải > 0']);
        exit;
    }
    $payload = [
        'ID_DV' => $id,
        'TEN_DV' => $current['TEN_DV'],
        'MOTA_DV' => $current['MOTA_DV'],
        'THOI_GIAN' => $newDuration,
    ];
    if ($newPrice !== null) { $payload['GIA'] = $newPrice; }
    $before = $current;
    $ok = $repo->update($payload, null);
    if ($ok) {
        $after = $repo->findById($id);
        record_system_log($conn,'SERVICE_INLINE_UPDATE','service:'.$id,$before,$after);
        $minutes = (int)$after['THOI_GIAN'];
        $h = floor($minutes/60); $m = $minutes % 60; $durationStr = $h.' giờ '.$m.' phút';
        // Get latest price again
        $latest = $repo->searchServices('',1,0,null,null,null,null); // fallback but inefficient; better query direct but acceptable for now
        echo json_encode([
            'ok'=>true,
            'ID_DV'=>$id,
            'THOI_GIAN'=>$after['THOI_GIAN'],
            'duration_fmt'=>$durationStr,
            'DON_GIA'=>isset($after['DON_GIA'])?(int)$after['DON_GIA']:$newPrice,
            'DON_GIA_FMT'=> isset($after['DON_GIA']) ? number_format($after['DON_GIA'],0,',','.').' VND' : 'Chưa có'
        ]);
    } else {
        echo json_encode(['ok'=>false,'error'=>'Cập nhật thất bại']);
    }
    exit;
}

// AJAX partial response for dynamic search
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: text/html; charset=utf-8');
    foreach ($services as $service) {
        $phut = (int)$service['THOI_GIAN'];
        $gio = floor($phut / 60); $du_phut = $phut % 60; $thoi_gian_dang_dep = "$gio giờ $du_phut phút";
        $st = $service['TRANG_THAI'] ?? 'active';
        echo '<tr class="hover:bg-gray-50">';
        echo '<td class="p-3">'.$service['ID_DV'].'</td>';
        echo '<td class="p-3">'.htmlspecialchars($service['TEN_DV']).'</td>';
        echo '<td class="p-3">'.htmlspecialchars(mb_strimwidth($service['MOTA_DV'],0,30,'...')).'</td>';
        echo '<td class="p-3">'.$thoi_gian_dang_dep.'</td>';
        echo '<td class="p-3">'.(isset($service['DON_GIA'])?number_format($service['DON_GIA'],0,',','.').' VND':'Chưa có').'</td>';
        echo '<td class="p-3"><img src="/'.htmlspecialchars($service['IMAGE']).'" class="w-12 h-12 object-cover rounded" /></td>';
        $badgeClass = $st==='active'?'bg-green-100 text-green-800':($st==='draft'?'bg-yellow-100 text-yellow-800':'bg-gray-300 text-gray-700');
        echo '<td class="p-3"><span class="px-2 py-1 rounded text-xs font-semibold '.$badgeClass.'">'.htmlspecialchars($st).'</span></td>';
        echo '<td class="p-3 flex justify-center gap-2">';
        echo '<a href="?page=services&edit='.$service['ID_DV'].'" class="bg-yellow-500 hover:bg-yellow-600 text-white px-2 py-1 rounded text-xs">Sửa</a>';
        echo '<a href="?page=services&history='.$service['ID_DV'].'" class="bg-indigo-500 hover:bg-indigo-600 text-white px-2 py-1 rounded text-xs">Giá</a>';
        echo '<a href="?page=services&confirm_retire='.$service['ID_DV'].'" class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs">Ngừng</a>';
        echo '</td>';
        echo '</tr>';
    }
    if (empty($services)) {
        echo '<tr><td colspan="8" class="p-3 text-center text-gray-500">Không có dịch vụ.</td></tr>';
    }
    exit;
}

function handleUploadImage(string $field): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $mime = mime_content_type($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) return null;
    $ext = $allowed[$mime];
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $targetDirFS = $_SERVER['DOCUMENT_ROOT'] . '/public/images/dichvu/';
    if (!is_dir($targetDirFS)) mkdir($targetDirFS, 0777, true);
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $targetDirFS . $name)) return null;
    return 'public/images/dichvu/' . $name;
}

// Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $payload = [
        'TEN_DV'    => trim($_POST['TEN_DV'] ?? ''),
        'MOTA_DV'   => trim($_POST['MOTA_DV'] ?? ''),
        'THOI_GIAN' => (int)($_POST['THOI_GIAN'] ?? 0),
        'GIA'       => (int)($_POST['GIA'] ?? 0),
    ];
    if ($payload['TEN_DV'] === '' || $payload['MOTA_DV'] === '' || $payload['THOI_GIAN'] <= 0 || $payload['GIA'] <= 0) {
        $errorMessage = 'Dữ liệu không hợp lệ.';
    } else {
        $img = handleUploadImage('IMAGE');
        try {
            $new = $repo->create($payload, $img);
            $successMessage = 'Dịch vụ đã được thêm thành công!';
            record_system_log($conn,'SERVICE_CREATE','service:'.$new['ID_DV'],null,[
                'ID_DV'=>$new['ID_DV'],
                'TEN_DV'=>$new['TEN_DV'],
                'THOI_GIAN'=>$new['THOI_GIAN'],
                'TRANG_THAI'=>$new['TRANG_THAI'] ?? null
            ]);
        } catch (\Throwable $e) {
            $errorMessage = 'Lỗi thêm dịch vụ: ' . $e->getMessage();
        }
    }
}

// Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_service'])) {
    $payload = [
        'ID_DV'     => (int)$_POST['ID_DV'],
        'TEN_DV'    => trim($_POST['TEN_DV'] ?? ''),
        'MOTA_DV'   => trim($_POST['MOTA_DV'] ?? ''),
        'THOI_GIAN' => (int)($_POST['THOI_GIAN'] ?? 0),
        'GIA'       => (int)($_POST['GIA'] ?? 0),
    ];
    $img = handleUploadImage('IMAGE');
    if ($payload['TEN_DV'] === '' || $payload['MOTA_DV'] === '' || $payload['THOI_GIAN'] <= 0) {
        $errorMessage = 'Dữ liệu cập nhật không hợp lệ.';
    } else {
        $before = $repo->findById($payload['ID_DV']);
        if ($repo->update($payload, $img)) {
            $after = $repo->findById($payload['ID_DV']);
            $successMessage = 'Dịch vụ đã được cập nhật thành công!';
            record_system_log($conn,'SERVICE_UPDATE','service:'.$payload['ID_DV'],$before,$after);
        } else {
            $errorMessage = 'Lỗi khi cập nhật dịch vụ.';
        }
    }
}

// Retire (soft delete) final action
if (isset($_GET['delete'])) {
    $id_dv = (int)$_GET['delete'];
    $reasons = $policy->serviceDeletionCheck($id_dv);
    if ($policy->canRetire($id_dv)) {
        $before = $repo->findById($id_dv);
        if ($repo->retire($id_dv)) {
            $after = $repo->findById($id_dv);
            $successMessage = 'Dịch vụ đã được chuyển sang trạng thái retired.';
            record_system_log($conn,'SERVICE_RETIRE','service:'.$id_dv,$before,$after);
        } else {
            $errorMessage = 'Không thể chuyển trạng thái dịch vụ.';
        }
    } else {
        $errorMessage = 'Không thể ngừng dịch vụ: ' . $policy->formatReasons($reasons);
    }
}

// Pre-retire confirmation modal data
$confirmRetireService = null; $confirmRetireReasons = [];
if (isset($_GET['confirm_retire'])) {
    $cid = (int)$_GET['confirm_retire'];
    $confirmRetireService = $repo->findById($cid);
    if ($confirmRetireService) {
        $confirmRetireReasons = $policy->serviceDeletionCheck($cid);
    }
}

$isEditing = isset($_GET['edit']);
$editService = null;
if ($isEditing) {
    $editService = $repo->findById((int)$_GET['edit']);
}
?>
<body class="bg-gray-100 p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold text-indigo-700 mb-6 text-center">Quản lý dịch vụ</h1>

        <!-- Thông báo -->
        <?php if (isset($successMessage)) : ?>
            <div class="bg-green-100 text-green-800 p-4 rounded shadow mb-4"><?= $successMessage; ?></div>
        <?php endif; ?>
        <?php if (isset($errorMessage)) : ?>
            <div class="bg-red-100 text-red-700 p-4 rounded shadow mb-4"><?= $errorMessage; ?></div>
        <?php endif; ?>

        <!-- Thanh tìm kiếm và nút thêm -->
        <div class="flex flex-wrap justify-between items-center gap-2 mb-6">
            <form method="GET" id="serviceFilterForm" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="page" value="services">
                <input type="text" name="search" placeholder="🔍 Tìm theo tên hoặc mô tả" value="<?= htmlspecialchars($search) ?>" class="p-2 border rounded w-64 shadow-sm">
                <select name="status" class="p-2 border rounded">
                    <option value="all" <?= $statusFilter==='all'?'selected':'' ?>>Tất cả trạng thái</option>
                    <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>active</option>
                    <option value="draft" <?= $statusFilter==='draft'?'selected':'' ?>>draft</option>
                    <option value="retired" <?= $statusFilter==='retired'?'selected':'' ?>>retired</option>
                </select>
                <input type="number" name="min_price" placeholder="Giá từ" value="<?= $minPrice!==null?$minPrice:'' ?>" class="p-2 border rounded w-24">
                <input type="number" name="max_price" placeholder="Giá đến" value="<?= $maxPrice!==null?$maxPrice:'' ?>" class="p-2 border rounded w-24">
                <input type="number" name="min_duration" placeholder="Phút từ" value="<?= $minDuration!==null?$minDuration:'' ?>" class="p-2 border rounded w-24">
                <input type="number" name="max_duration" placeholder="Phút đến" value="<?= $maxDuration!==null?$maxDuration:'' ?>" class="p-2 border rounded w-24">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded shadow">Lọc</button>
            </form>
            <a href="?page=services&add=true" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow">Thêm dịch vụ</a>
        </div>

        <!-- Form Thêm Dịch vụ -->
        <?php if (isset($_GET['add']) && $_GET['add'] === 'true' && !$isEditing) : ?>
            <form method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded shadow mb-6">
                <h2 class="text-xl font-semibold text-indigo-600 mb-4">Thêm dịch vụ</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="text" name="TEN_DV" placeholder="Tên dịch vụ" class="p-2 border rounded" required>
                    <input type="number" name="THOI_GIAN" placeholder="Thời lượng (phút)" min="1" class="p-2 border rounded" required>
                    <textarea name="MOTA_DV" placeholder="Mô tả" class="p-2 border rounded md:col-span-2" required></textarea>
                    <input type="file" name="IMAGE" class="p-2 border rounded" required>
                    <input type="number" name="GIA" placeholder="Giá dịch vụ (VNĐ)" class="p-2 border rounded" required>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="submit" name="add_service" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Lưu</button>
                    <a href="?page=services" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Hủy</a>
                </div>
            </form>
        <?php endif; ?>

        <!-- Form Cập nhật Dịch vụ -->
        <?php if ($isEditing && $editService) : ?>
            <form method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded shadow mb-6">
                <h2 class="text-xl font-semibold text-indigo-600 mb-4">Cập nhật dịch vụ</h2>
                <input type="hidden" name="ID_DV" value="<?= $editService['ID_DV']; ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="text" name="TEN_DV" value="<?= $editService['TEN_DV']; ?>" class="p-2 border rounded" required>
                    <input type="number" name="THOI_GIAN" value="<?= $editService['THOI_GIAN']; ?>" min="1" class="p-2 border rounded" required>
                    <textarea name="MOTA_DV" class="p-2 border rounded md:col-span-2" required><?= $editService['MOTA_DV']; ?></textarea>
                    <input type="file" name="IMAGE" class="p-2 border rounded">
                    <input type="number" name="GIA" value="<?= $editService['DON_GIA'] ?? ''; ?>" class="p-2 border rounded" required>
                </div>
                <?php if ($editService['IMAGE']) : ?>
                    <div class="mt-4">
                        <img src="/<?= $editService['IMAGE']; ?>" class="w-32 h-32 object-cover rounded shadow" />
                    </div>
                <?php endif; ?>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="submit" name="edit_service" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Lưu</button>
                    <a href="?page=services" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Hủy</a>
                </div>
            </form>
        <?php endif; ?>

        <!-- Danh sách dịch vụ -->
        <div class="overflow-x-auto bg-white rounded shadow">
            <table class="min-w-full table-auto text-sm">
                <thead class="bg-indigo-100 text-indigo-700">
                    <tr>
                        <th class="p-3">ID</th>
                        <th class="p-3">Tên Dịch Vụ</th>
                        <th class="p-3">Mô Tả</th>
                        <th class="p-3">Thời lượng</th>
                        <th class="p-3">Giá</th>
                        <th class="p-3">Ảnh</th>
                        <th class="p-3">Trạng thái</th>
                        <th class="p-3">Hành động</th>
                    </tr>
                </thead>
                <tbody id="servicesTbody" class="text-center divide-y">
                    <?php foreach ($services as $service): ?>
                        <?php
                        $phut = (int)$service['THOI_GIAN'];
                        $gio = floor($phut / 60);
                        $du_phut = $phut % 60;
                        $thoi_gian_dang_dep = "$gio giờ $du_phut phút";
                        ?>
                        <tr class="hover:bg-gray-50" data-id-dv="<?= $service['ID_DV']; ?>" data-thoi-gian="<?= (int)$service['THOI_GIAN']; ?>" data-gia="<?= isset($service['DON_GIA'])?(int)$service['DON_GIA']:0; ?>">
                            <td class="p-3"><?= $service['ID_DV']; ?></td>
                            <td class="p-3"><?= htmlspecialchars($service['TEN_DV']); ?></td>
                            <td class="p-3"><?= mb_strimwidth($service['MOTA_DV'], 0, 30, "..."); ?></td>
                            <td class="p-3 duration-cell cursor-pointer" title="Double click để sửa"><?= $thoi_gian_dang_dep ?></td>
                            <td class="p-3 price-cell cursor-pointer" title="Double click để sửa"><?= isset($service['DON_GIA']) ? number_format($service['DON_GIA'], 0, ',', '.') . ' VND' : 'Chưa có' ?></td>
                            <td class="p-3">
                                <img src="/<?= htmlspecialchars($service['IMAGE']); ?>" alt="Service Image" class="w-12 h-12 object-cover rounded">
                            </td>
                            <td class="p-3">
                                <?php $st = $service['TRANG_THAI'] ?? 'active'; ?>
                                    <script>
                                    // Inline editing script
                                    (function(){
                                        const tbody = document.getElementById('servicesTbody');
                                        if(!tbody) return;
                                        function activateDuration(cell){
                                            if(cell.querySelector('input')) return; // already editing
                                            const row = cell.closest('tr');
                                            const minutes = parseInt(row.dataset.thoiGian||'0',10);
                                            const input = document.createElement('input');
                                            input.type='number'; input.min='1'; input.value=minutes; input.className='w-24 p-1 border rounded text-sm';
                                            cell.innerHTML=''; cell.appendChild(input); input.focus();
                                            const save = ()=>{
                                                const val = parseInt(input.value,10); if(!val||val<=0){input.classList.add('border-red-500'); return;}
                                                sendUpdate(row.dataset.idDv, val, null, cell);
                                            };
                                            input.addEventListener('keydown',e=>{ if(e.key==='Enter') save(); if(e.key==='Escape') cancel(); });
                                            function cancel(){ cell.textContent=formatDuration(minutes); }
                                            input.addEventListener('blur', ()=> save());
                                        }
                                        function activatePrice(cell){
                                            if(cell.querySelector('input')) return;
                                            const row = cell.closest('tr');
                                            const price = parseInt(row.dataset.gia||'0',10);
                                            const input = document.createElement('input');
                                            input.type='number'; input.min='1'; input.value= price>0?price:''; input.placeholder='Giá'; input.className='w-32 p-1 border rounded text-sm';
                                            cell.innerHTML=''; cell.appendChild(input); input.focus();
                                            const save = ()=>{
                                                const valRaw = input.value.trim();
                                                const val = valRaw===''?null:parseInt(valRaw,10);
                                                if(val!==null && val<=0){ input.classList.add('border-red-500'); return; }
                                                sendUpdate(row.dataset.idDv, null, val, cell);
                                            };
                                            input.addEventListener('keydown',e=>{ if(e.key==='Enter') save(); if(e.key==='Escape') cancel(); });
                                            function cancel(){ cell.textContent = price>0? formatPrice(price):'Chưa có'; }
                                            input.addEventListener('blur', ()=> save());
                                        }
                                        function formatDuration(min){ const h=Math.floor(min/60); const m=min%60; return h+' giờ '+m+' phút'; }
                                        function formatPrice(p){ return new Intl.NumberFormat('vi-VN').format(p)+' VND'; }
                                        function sendUpdate(id, duration, price, cell){
                                            const formData = new FormData();
                                            formData.set('ajax_inline_update','1');
                                            formData.set('ID_DV', id);
                                            if(duration!==null) formData.set('THOI_GIAN', duration);
                                            if(price!==null) formData.set('GIA', price);
                                            cell.classList.add('opacity-50');
                                            fetch('?page=services', {method:'POST', body:formData})
                                              .then(r=>r.json())
                                              .then(j=>{
                                                cell.classList.remove('opacity-50');
                                                if(!j.ok){ cell.textContent='Lỗi'; cell.classList.add('text-red-600'); return; }
                                                const row = cell.closest('tr');
                                                if(duration!==null){ row.dataset.thoiGian = j.THOI_GIAN; cell.textContent = j.duration_fmt; }
                                                if(price!==null){ row.dataset.gia = j.DON_GIA; cell.textContent = j.DON_GIA>0? j.DON_GIA_FMT : 'Chưa có'; }
                                              })
                                              .catch(()=>{ cell.classList.remove('opacity-50'); cell.textContent='Lỗi mạng'; cell.classList.add('text-red-600'); });
                                        }
                                        tbody.addEventListener('dblclick', e=>{
                                            const durCell = e.target.closest('.duration-cell');
                                            if(durCell) { activateDuration(durCell); return; }
                                            const priceCell = e.target.closest('.price-cell');
                                            if(priceCell){ activatePrice(priceCell); return; }
                                        });
                                    })();
                                    </script>
                                <span class="px-2 py-1 rounded text-xs font-semibold <?php echo $st==='active'?'bg-green-100 text-green-800':($st==='draft'?'bg-yellow-100 text-yellow-800':'bg-gray-300 text-gray-700'); ?>">
                                    <?= htmlspecialchars($st); ?>
                                </span>
                            </td>
                            <td class="p-3 flex justify-center gap-2">
                                <a href="?page=services&edit=<?= $service['ID_DV']; ?>"
                                   class="bg-yellow-500 hover:bg-yellow-600 text-white px-2 py-1 rounded text-xs">Sửa</a>
                                          <a href="?page=services&history=<?= $service['ID_DV']; ?>"
                                              class="bg-indigo-500 hover:bg-indigo-600 text-white px-2 py-1 rounded text-xs">Giá</a>
                                          <a href="?page=services&confirm_retire=<?= $service['ID_DV']; ?>" class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs">Ngừng</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Phân trang -->
        <?php if ($totalPages > 1): ?>
            <div id="servicesPagination" class="mt-6 flex justify-center gap-2">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=services&search=<?= urlencode($search) ?>&p=<?= $i ?>"
                       class="px-3 py-1 rounded border <?= ($i == $page) ? 'bg-indigo-600 text-white' : 'bg-white hover:bg-gray-100' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if($historyService): ?>
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" onclick="if(event.target===this) window.location='?page=services';">
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
                    <a href="?page=services" class="px-4 py-2 rounded bg-gray-600 hover:bg-gray-700 text-white">Đóng</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if($confirmRetireService): ?>
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" role="dialog" aria-modal="true" onclick="if(event.target===this) window.location='?page=services';">
            <div class="bg-white w-full max-w-lg rounded shadow-lg p-6">
                <h2 class="text-xl font-semibold text-red-600 mb-2">Ngừng dịch vụ: <?= htmlspecialchars($confirmRetireService['TEN_DV']); ?></h2>
                <p class="text-sm text-gray-600 mb-4">Kiểm tra điều kiện trước khi ngừng. Các lý do dưới đây có thể ngăn việc ngừng:</p>
                <ul class="space-y-2 mb-4">
                    <?php if(empty($confirmRetireReasons)): ?>
                        <li class="flex items-start gap-2"><span class="text-green-600">✔</span><span class="text-sm">Không có ràng buộc. Có thể ngừng an toàn.</span></li>
                    <?php else: foreach($confirmRetireReasons as $r): ?>
                        <li class="flex items-start gap-2"><span class="text-red-600">•</span><span class="text-sm"><?= htmlspecialchars($r['message']); ?></span></li>
                    <?php endforeach; endif; ?>
                </ul>
                <div class="flex justify-end gap-2">
                    <a href="?page=services" class="px-4 py-2 rounded bg-gray-600 hover:bg-gray-700 text-white">Hủy</a>
                    <?php if($policy->canRetire((int)$confirmRetireService['ID_DV'])): ?>
                        <a href="?page=services&delete=<?= $confirmRetireService['ID_DV']; ?>" class="px-4 py-2 rounded bg-red-600 hover:bg-red-700 text-white" onclick="return confirm('Xác nhận ngừng dịch vụ?');">Xác nhận ngừng</a>
                    <?php else: ?>
                        <button disabled class="px-4 py-2 rounded bg-red-300 text-white cursor-not-allowed">Không thể ngừng</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <script>
    // Debounced AJAX search + filter updates
    (function(){
        const form = document.getElementById('serviceFilterForm');
        if(!form) return;
        const tbody = document.getElementById('servicesTbody');
        let timer=null;
        function buildQuery(){
            const params = new URLSearchParams(new FormData(form));
            params.set('ajax','1');
            params.set('page','services');
            return params.toString();
        }
        function fetchData(){
            const q = buildQuery();
            const url = '?'+q;
            fetch(url,{headers:{'X-Requested-With':'fetch'}})
              .then(r=>r.text())
              .then(html=>{ tbody.innerHTML = html; })
              .catch(()=>{});
        }
        function debounceFetch(){ clearTimeout(timer); timer=setTimeout(fetchData,350); }
        form.querySelectorAll('input[name=search],select[name=status],input[name=min_price],input[name=max_price],input[name=min_duration],input[name=max_duration]').forEach(el=>{
            el.addEventListener('input', debounceFetch);
            el.addEventListener('change', debounceFetch);
        });
    })();
    </script>
</body>
