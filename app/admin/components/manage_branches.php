<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Kết nối đến cơ sở dữ liệu
include '../../database/config.php';
require_once __DIR__ . '/../../helpers/assets.php';
function add_flash($type,$msg){ $_SESSION['flash'][] = ['type'=>$type,'msg'=>$msg]; }
function safe_redirect($url){
    if (!headers_sent()) { header('Location: '.$url); exit; }
    $u = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    echo "<script>location.replace('{$u}');</script>"; exit;
}

// Thiết lập phân trang
$limit = 5; // Số bản ghi mỗi trang
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_sql = !empty($search) ? "WHERE TEN_CN LIKE ?" : "";


// Lấy danh sách chi nhánh
function getBranches($conn, $search, $limit, $offset) {
    $search_sql = !empty($search) ? "WHERE TEN_CN LIKE ?" : "";
    $query = "SELECT * FROM chi_nhanh $search_sql ORDER BY TEN_CN ASC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);

    if (!empty($search)) {
        $like = "%$search%";
        $stmt->bind_param("sii", $like, $limit, $offset);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }

    $stmt->execute();
    return $stmt->get_result();
}

$total_query = "SELECT COUNT(*) AS total FROM chi_nhanh " . (!empty($search) ? "WHERE TEN_CN LIKE ?" : "");
$stmt_total = $conn->prepare($total_query);
if (!empty($search)) {
    $like = "%$search%";
    $stmt_total->bind_param("s", $like);
}
$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_rows = $result_total->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);


// Thêm chi nhánh
if (isset($_POST['add_branch'])) {
    $branch_name = trim($_POST['branch_name']);
    $branch_phone = trim($_POST['branch_phone']);
    $branch_address = trim($_POST['branch_address']);
    $nameLen = function_exists('mb_strlen') ? mb_strlen($branch_name,'UTF-8') : strlen($branch_name);
    if (empty($branch_name) || $nameLen > 100) { add_flash('error','Tên chi nhánh không hợp lệ (rỗng hoặc >100 ký tự)'); safe_redirect($_SERVER['REQUEST_URI']); }
    if (!preg_match('/^[0-9]{9,12}$/', $branch_phone)) { add_flash('error','Số điện thoại không hợp lệ (9–12 chữ số)'); safe_redirect($_SERVER['REQUEST_URI']); }
    if (empty($branch_address)) { add_flash('error','Địa chỉ không được bỏ trống'); safe_redirect($_SERVER['REQUEST_URI']); }
    $stmt = $conn->prepare("SELECT 1 FROM chi_nhanh WHERE TEN_CN = ? LIMIT 1");
    $stmt->bind_param("s", $branch_name); $stmt->execute(); $res = $stmt->get_result();
    if ($res->num_rows) { add_flash('error','Tên chi nhánh đã tồn tại'); safe_redirect($_SERVER['REQUEST_URI']); }
    $stmt = $conn->prepare("INSERT INTO chi_nhanh (TEN_CN, SDT_CN, DIA_CHI_CN) VALUES (?,?,?)");
    $stmt->bind_param("sss", $branch_name,$branch_phone,$branch_address);
    if ($stmt->execute()) { add_flash('success','Thêm chi nhánh thành công'); } else { add_flash('error','Lỗi thêm chi nhánh: '.$stmt->error); }
    safe_redirect($_SERVER['REQUEST_URI']);
}

// Xóa chi nhánh
if (isset($_POST['delete_branch'])) {
    $branch_id = (int)$_POST['branch_id'];
    $wantsJson = (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
                 || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])==='xmlhttprequest');
    $stmt_cnt1 = $conn->prepare("SELECT COUNT(*) AS total FROM nhan_vien WHERE ID_CN = ?");
    $stmt_cnt1->bind_param("i", $branch_id); $stmt_cnt1->execute(); $res1 = $stmt_cnt1->get_result()->fetch_assoc()['total'];
    $stmt_cnt2 = $conn->prepare("SELECT COUNT(*) AS total FROM trang_thiet_bi WHERE ID_CN = ?");
    $stmt_cnt2->bind_param("i", $branch_id); $stmt_cnt2->execute(); $res2 = $stmt_cnt2->get_result()->fetch_assoc()['total'];
    $stmt_cnt3 = $conn->prepare("SELECT COUNT(*) AS total FROM lich_hen WHERE ID_CHINHANH = ? AND TRANGTHAI = 'Đã xác nhận'");
    $stmt_cnt3->bind_param("i", $branch_id); $stmt_cnt3->execute(); $res3 = $stmt_cnt3->get_result()->fetch_assoc()['total'];
    if ($res1 > 0 || $res2 > 0 || $res3 > 0) {
        if ($wantsJson) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'reason'=>'has_links','message'=>'Không thể xóa: còn nhân viên / thiết bị / lịch hẹn đã xác nhận']); exit; }
        add_flash('error','Không thể xóa: còn nhân viên / thiết bị / lịch hẹn đã xác nhận'); safe_redirect($_SERVER['REQUEST_URI']);
    }
    $stmt = $conn->prepare("DELETE FROM chi_nhanh WHERE ID_CN = ?");
    $stmt->bind_param("i", $branch_id);
    if ($stmt->execute()) {
        if ($wantsJson) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>true,'message'=>'Xóa chi nhánh thành công']); exit; }
        add_flash('success','Xóa chi nhánh thành công');
    } else {
        if ($wantsJson) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'message'=>'Lỗi xóa: '.$stmt->error]); exit; }
        add_flash('error','Lỗi xóa: '.$stmt->error);
    }
    safe_redirect($_SERVER['REQUEST_URI']);
}

// Cập nhật chi nhánh
if (isset($_POST['edit_branch'])) {
    $branch_id = (int)$_POST['branch_id'];
    $branch_name = trim($_POST['branch_name']);
    $branch_phone = trim($_POST['branch_phone']);
    $branch_address = trim($_POST['branch_address']);
    $branch_lat = isset($_POST['branch_lat']) && $_POST['branch_lat'] !== '' ? $_POST['branch_lat'] : null;
    $branch_lng = isset($_POST['branch_lng']) && $_POST['branch_lng'] !== '' ? $_POST['branch_lng'] : null;
    $nameLen = function_exists('mb_strlen') ? mb_strlen($branch_name,'UTF-8') : strlen($branch_name);
    if (empty($branch_name) || $nameLen > 100) { add_flash('error','Tên chi nhánh không hợp lệ (rỗng hoặc >100 ký tự)'); safe_redirect($_SERVER['REQUEST_URI']); }
    $stmt_check = $conn->prepare("SELECT 1 FROM chi_nhanh WHERE TEN_CN = ? AND ID_CN <> ? LIMIT 1");
    $stmt_check->bind_param("si", $branch_name, $branch_id); $stmt_check->execute(); if($stmt_check->get_result()->num_rows){ add_flash('error','Tên chi nhánh đã tồn tại (khác ID)'); safe_redirect($_SERVER['REQUEST_URI']); }
    if (!preg_match('/^[0-9]{9,12}$/', $branch_phone)) { add_flash('error','Số điện thoại không hợp lệ (9–12 chữ số)'); safe_redirect($_SERVER['REQUEST_URI']); }
    if (empty($branch_address)) { add_flash('error','Địa chỉ không được bỏ trống'); safe_redirect($_SERVER['REQUEST_URI']); }
    if ($branch_lat !== null && !is_numeric($branch_lat)) { add_flash('error','Latitude không hợp lệ'); safe_redirect($_SERVER['REQUEST_URI']); }
    if ($branch_lng !== null && !is_numeric($branch_lng)) { add_flash('error','Longitude không hợp lệ'); safe_redirect($_SERVER['REQUEST_URI']); }
    $query = "UPDATE chi_nhanh SET TEN_CN = ?, SDT_CN = ?, DIA_CHI_CN = ?, LATITUDE = ?, LONGITUDE = ? WHERE ID_CN = ?";
    $stmt = $conn->prepare($query);
    $latParam = $branch_lat !== null ? (double)$branch_lat : null; $lngParam = $branch_lng !== null ? (double)$branch_lng : null;
    $stmt->bind_param("sssddi", $branch_name,$branch_phone,$branch_address,$latParam,$lngParam,$branch_id);
    if ($stmt->execute()) { add_flash('success','Cập nhật chi nhánh thành công'); } else { add_flash('error','Lỗi cập nhật: '.$stmt->error); }
    safe_redirect($_SERVER['REQUEST_URI']);
}

$branches = getBranches($conn, $search, $limit, $offset);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Chi nhánh</title>
    <?= sb_tailwind_link_tag(); ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>
        // Toast system (polished UI)
        const FLASH_MESSAGES = <?php $__flash = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); echo json_encode($__flash, JSON_UNESCAPED_UNICODE); ?>;
        function ensureToastContainer(){
            let container = document.getElementById('toast-container');
            if(!container){
                container = document.createElement('div');
                container.id='toast-container';
                container.className='fixed top-4 right-4 flex flex-col gap-3';
                Object.assign(container.style, { position:'fixed', top:'16px', right:'16px', zIndex:'2147483647', left:'auto' });
                document.body.appendChild(container);
            }
            return container;
        }
        function ensureToastStyles(){
            if(document.getElementById('sb-toast-style')) return;
            const s = document.createElement('style');
            s.id = 'sb-toast-style';
            s.textContent = `
            .sb-toast{position:relative;display:flex;gap:12px;align-items:flex-start;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:12px;padding:12px 14px;box-shadow:0 10px 15px rgba(0,0,0,.08),0 4px 6px rgba(0,0,0,.06);width:auto;min-width:280px;max-width:560px;overflow:hidden;backdrop-filter:blur(2px);animation:sb-toast-in .22s ease-out both}
            .sb-toast__accent{position:absolute;left:0;top:0;bottom:0;width:6px;background:var(--accent)}
            .sb-toast__icon{color:var(--accent);line-height:0;margin-left:4px}
            .sb-toast__title{font-weight:700;text-transform:uppercase;font-size:12px;letter-spacing:.02em;color:var(--accent);white-space:nowrap}
            .sb-toast__message{font-size:14px;line-height:1.45;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
            .sb-toast__close{appearance:none;background:transparent;border:0;color:var(--text);opacity:.55;cursor:pointer;padding:2px;border-radius:6px}
            .sb-toast__close:hover{opacity:1;color:var(--accent);background:rgba(0,0,0,.04)}
            .sb-toast__progress{position:absolute;left:0;bottom:0;height:3px;background:var(--accent);transform-origin:left;animation:sb-toast-progress linear forwards}
            .sb-toast--hide{animation:sb-toast-out .18s ease-in both}
            @keyframes sb-toast-in{from{opacity:0;transform:translateY(-6px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
            @keyframes sb-toast-out{to{opacity:0;transform:translateY(-6px)}}
            @keyframes sb-toast-progress{from{width:100%}to{width:0}}
            `;
            document.head.appendChild(s);
        }
        function showToast(type, msg, opts={}){
            const {duration=5000} = opts;
            ensureToastStyles();
            const container = ensureToastContainer();
            const palette = {
                success: {accent:'#16a34a', bg:'#f0fdf4', border:'#bbf7d0', text:'#14532d', title:'Thành công', icon:`<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 10-1.214-.882l-3.483 4.792-1.86-1.86a.75.75 0 10-1.06 1.06l2.5 2.5a.75.75 0 001.157-.114l3.96-5.496z" clip-rule="evenodd"/></svg>`},
                error:   {accent:'#dc2626', bg:'#fef2f2', border:'#fecaca', text:'#7f1d1d', title:'Lỗi', icon:`<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7.28 7.28a.75.75 0 011.06 0L10 8.94l1.66-1.66a.75.75 0 111.06 1.06L11.06 10l1.66 1.66a.75.75 0 11-1.06 1.06L10 11.06l-1.66 1.66a.75.75 0 11-1.06-1.06L8.94 10 7.28 8.34a.75.75 0 010-1.06z" clip-rule="evenodd"/></svg>`},
                warning: {accent:'#d97706', bg:'#fffbeb', border:'#fde68a', text:'#78350f', title:'Cảnh báo', icon:`<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M8.257 3.099c.765-1.36 2.721-1.36 3.486 0l6.518 11.594c.75 1.334-.213 2.99-1.743 2.99H3.482c-1.53 0-2.493-1.656-1.743-2.99L8.257 3.1z"/><path fill="#fff" d="M11 14a1 1 0 11-2 0 1 1 0 012 0zm-.25-6.75a.75.75 0 00-1.5 0v4a.75.75 0 001.5 0v-4z"/></svg>`},
                info:    {accent:'#2563eb', bg:'#eff6ff', border:'#bfdbfe', text:'#1e3a8a', title:'Thông báo', icon:`<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM9 8a1 1 0 102 0 1 1 0 00-2 0zm.75 2.25a.75.75 0 000 1.5h.5v3.5a.75.75 0 001.5 0v-4.25a.75.75 0 00-.75-.75h-1.25z" clip-rule="evenodd"/></svg>`},
                default: {accent:'#6b7280', bg:'#f3f4f6', border:'#e5e7eb', text:'#111827', title:'Thông báo', icon:`<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 22a1.75 1.75 0 100-3.5 1.75 1.75 0 000 3.5zM12 2a7.75 7.75 0 00-7.75 7.75c0 2.607-.79 3.86-1.51 4.57A1 1 0 003 16h18a1 1 0 00.26-1.98c-.72-.71-1.51-1.964-1.51-4.57A7.75 7.75 0 0012 2z"/></svg>`}
            };
            const theme = palette[type] || palette.default;
            const el = document.createElement('div');
            el.className = 'sb-toast';
            el.style.setProperty('--accent', theme.accent);
            el.style.setProperty('--bg', theme.bg);
            el.style.setProperty('--border', theme.border);
            el.style.setProperty('--text', theme.text);
            el.innerHTML = `
                <div class="sb-toast__accent"></div>
                <div class="sb-toast__icon" aria-hidden="true">${theme.icon}</div>
                <div class="sb-toast__content" style="flex:1 1 auto;">
                    <div class="sb-toast__title">${theme.title}</div>
                    <div class="sb-toast__message">${msg}</div>
                </div>
                <button class="sb-toast__close" aria-label="Đóng">×</button>
                <div class="sb-toast__progress"></div>
            `;
            const closeBtn = el.querySelector('.sb-toast__close');
            const progress = el.querySelector('.sb-toast__progress');
            progress.style.animationDuration = `${duration}ms`;
            let removed=false; const remove=()=>{ if(removed) return; removed=true; el.classList.add('sb-toast--hide'); setTimeout(()=>el.remove(), 220); };
            closeBtn.addEventListener('click', remove);
            ensureToastContainer().appendChild(el);
            setTimeout(remove, duration);
        }
        // Override alert to use toast
        window.alert = function(msg){
            const m = (msg||'').trim();
            let type='info';
            if(m.startsWith('✅')) type='success'; else if(m.startsWith('❌')) type='error';
            showToast(type,m.replace(/^✅|^❌/,'').trim());
        };
        // Load flash messages after DOM ready
        document.addEventListener('DOMContentLoaded',()=>{ (FLASH_MESSAGES||[]).forEach(f=>showToast(f.type,f.msg)); });
        function confirmDelete() {
            return confirm('Bạn chắc chắn muốn xóa chi nhánh này?');
        }

        function toggleForm(formId) {
            const form = document.getElementById(formId);
            form.classList.toggle('hidden');
        }

        document.addEventListener('DOMContentLoaded', function () {
            const detailModal = document.getElementById('branch_detail_modal');
            const closeBtn = document.getElementById('detail_close');
            const cancelBtn = document.getElementById('detail_cancel');
            const deleteConfirmModal = document.getElementById('delete_confirm_modal');
            const deleteOpenBtn = document.getElementById('open_delete_confirm');
            const deleteCloseBtn = document.getElementById('delete_confirm_close');
            const deleteCancelBtn = document.getElementById('delete_confirm_cancel');
            function openModal(){detailModal.classList.remove('hidden'); detailModal.classList.add('flex','items-center','justify-center');}
            function closeModal(){detailModal.classList.add('hidden'); detailModal.classList.remove('flex','items-center','justify-center');}
            function openDeleteConfirm(){deleteConfirmModal.classList.remove('hidden');}
            function closeDeleteConfirm(){deleteConfirmModal.classList.add('hidden');}
            [closeBtn,cancelBtn].forEach(b=>b&&b.addEventListener('click',closeModal));
            [deleteCloseBtn,deleteCancelBtn].forEach(b=>b&&b.addEventListener('click',closeDeleteConfirm));
            document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeModal();}});

            function renderManagers(list){
                const ul = document.getElementById('detail_managers');
                ul.innerHTML = list.length? list.map(m=>`<li><span class='font-medium'>${m.HO_TEN||'Chưa có tên'}</span> <span class='text-gray-500'>${m.EMAIL||''}</span></li>`).join('') : '<li class="text-gray-500">Không có quản lý</li>';
            }
                function renderStats(stats){
                const ul = document.getElementById('detail_stats');
                ul.innerHTML = `
                    <li>Nhân viên: <strong>${stats.total_employees}</strong></li>
                    <li>Lịch hẹn sắp tới: <strong>${stats.upcoming_appointments}</strong></li>
                    <li>Doanh thu: <strong>${new Intl.NumberFormat('vi-VN').format(stats.revenue)}</strong></li>
                    <li>Chi phí: <strong>${new Intl.NumberFormat('vi-VN').format(stats.expenses)}</strong></li>
                    <li>Lợi nhuận ròng: <strong class='${stats.net>=0?'text-green-600':'text-red-600'}'>${new Intl.NumberFormat('vi-VN').format(stats.net)}</strong></li>`;
            }
            // Leaflet map state
            let leafletMap = null; let leafletMarker = null; let editMode = false;
            function initLeaflet(lat,lng){
                if(!leafletMap){
                    leafletMap = L.map('leaflet_map');
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19, attribution:'&copy; OpenStreetMap'}).addTo(leafletMap);
                    leafletMap.on('click',e=>{
                        if(!editMode) return;
                        const {lat:clat,lng:clng}=e.latlng;
                        setOrMoveMarker(e.latlng,true);
                    });
                }
                const center = (lat && lng)? [lat,lng] : [16.047079,108.20623];
                leafletMap.setView(center,(lat&&lng)? 14:6);
                if(lat && lng){
                    if(leafletMarker){leafletMarker.setLatLng(center);} else {leafletMarker = L.marker(center,{draggable:false}).addTo(leafletMap);}            
                }
                setTimeout(()=>{leafletMap.invalidateSize();},100);
            }
            const BRANCH_DETAILS_URL = '/stygianblue/app/admin/branch_details.php';
            const BRANCH_DELETE_URL = '/stygianblue/app/admin/branch_delete.php';
            // Reverse geocode to auto-fill address (Nominatim)
            let geocodeController = null;
            function reverseGeocode(lat,lng){
                const addrInput = document.getElementById('edit_branch_address');
                if(!addrInput) return;
                // Abort previous
                if(geocodeController){geocodeController.abort();}
                geocodeController = new AbortController();
                const url = `https://nominatim.openstreetmap.org/reverse?lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}&format=json&accept-language=vi`;
                fetch(url,{signal:geocodeController.signal,headers:{'Accept':'application/json'}})
                    .then(r=>{if(!r.ok) throw new Error('HTTP '+r.status); return r.json();})
                    .then(data=>{
                        if(data && data.address){
                            const a = data.address;
                            // Build concise address
                            const parts = [a.road||a.street, a.suburb||a.village||a.hamlet, a.city||a.town||a.county, a.state, a.postcode].filter(Boolean);
                            const formatted = parts.join(', ');
                            if(formatted){
                                addrInput.value = formatted;
                                addrInput.classList.add('border-green-500');
                                setTimeout(()=>addrInput.classList.remove('border-green-500'),2000);
                            }
                        } else if(data && data.display_name){
                            addrInput.value = data.display_name;
                        }
                    })
                    .catch(err=>{console.warn('Reverse geocode lỗi', err);});
            }
            // Helper: update coordinate fields + UI
            function applyCoordinates(lat,lng,chosen){
                document.getElementById('edit_branch_lat').value = lat.toFixed(7);
                document.getElementById('edit_branch_lng').value = lng.toFixed(7);
                document.getElementById('detail_coords').textContent = `Tọa độ: ${lat.toFixed(7)}, ${lng.toFixed(7)}${chosen?' (đã chọn)':''}`;
                reverseGeocode(lat,lng);
            }
            // Helper: set or move marker (draggable when editMode)
            function setOrMoveMarker(latlng,chosen){
                if(!leafletMap) return;
                if(!leafletMarker){
                    leafletMarker = L.marker(latlng,{draggable:editMode}).addTo(leafletMap);
                    leafletMarker.on('dragend',e=>{
                        const pos = e.target.getLatLng();
                        applyCoordinates(pos.lat,pos.lng,true);
                    });
                } else {
                    leafletMarker.setLatLng(latlng);
                    if(editMode && !leafletMarker.dragging._draggable){
                        leafletMap.removeLayer(leafletMarker);
                        leafletMarker = L.marker(latlng,{draggable:true}).addTo(leafletMap);
                        leafletMarker.on('dragend',e=>{
                            const pos = e.target.getLatLng();
                            applyCoordinates(pos.lat,pos.lng,true);
                        });
                    }
                }
                applyCoordinates(latlng.lat,latlng.lng,chosen);
            }
            function loadBranch(id){
                const url = `${BRANCH_DETAILS_URL}?id=${encodeURIComponent(id)}`;
                fetch(url, {headers:{'Accept':'application/json'}})
                    .then(r=>{
                        if(!r.ok){throw new Error('HTTP '+r.status);} 
                        const ct = r.headers.get('Content-Type')||'';
                        if(!ct.includes('application/json')) return r.text().then(t=>{throw new Error('Content-Type không phải JSON: '+ct+"\n"+t.slice(0,200));});
                        return r.json();
                    })
                    .then(data=>{
                        if(data.error){showToast('error',data.error);return;}
                        const b = data.branch; const s = data.stats;
                        document.getElementById('detail_title').textContent = b.TEN_CN || 'Chi nhánh';
                        // Điền vào input inline
                        document.getElementById('detail_coords').textContent = (b.LATITUDE && b.LONGITUDE)? `Tọa độ: ${b.LATITUDE}, ${b.LONGITUDE}` : 'Tọa độ: Không có';
                        renderStats(s); renderManagers(s.managers||[]);
                        document.getElementById('edit_branch_id').value = b.ID_CN;
                        document.getElementById('edit_branch_name').value = b.TEN_CN||'';
                        document.getElementById('edit_branch_phone').value = b.SDT_CN||'';
                        document.getElementById('edit_branch_address').value = b.DIA_CHI_CN||'';
                        document.getElementById('edit_branch_lat').value = b.LATITUDE || '';
                        document.getElementById('edit_branch_lng').value = b.LONGITUDE || '';
                        initLeaflet(b.LATITUDE,b.LONGITUDE);
                        // Reset edit mode state each time modal opens
                        editMode = false;
                        ['edit_branch_name','edit_branch_phone','edit_branch_address'].forEach(id=>{
                            const el=document.getElementById(id); el.disabled=true; el.classList.add('bg-gray-100'); el.classList.remove('bg-white','border-indigo-500');
                        });
                        document.getElementById('map_hint').classList.add('hidden');
                        document.getElementById('save_branch_btn').classList.add('hidden');
                        const toggleBtn=document.getElementById('toggle_edit'); if(toggleBtn) toggleBtn.textContent='Chỉnh sửa';
                        openModal();
                    })
                    .catch(err=>{
                        console.error('Lỗi loadBranch:', err); showToast('error','Lỗi tải chi tiết');
                    });
            }
            document.querySelectorAll('.btn-detail').forEach(btn=>{
                btn.addEventListener('click',()=> loadBranch(btn.dataset.id));
            });
            // Open delete confirmation modal
            if(deleteOpenBtn){
                deleteOpenBtn.addEventListener('click',()=>{
                    const id = document.getElementById('edit_branch_id').value;
                    const name = document.getElementById('edit_branch_name').value || document.getElementById('detail_title').textContent;
                    document.getElementById('delete_branch_id_confirm').value = id;
                    document.getElementById('delete_branch_name').textContent = name;
                    openDeleteConfirm();
                });
            }
            // AJAX submit delete form
            const deleteForm = document.getElementById('delete_confirm_form');
            if(deleteForm){
                deleteForm.addEventListener('submit', async (e)=>{
                    e.preventDefault();
                    const fd = new FormData(deleteForm);
                    // ensure parameter exists
                    if(!fd.has('delete_branch')) fd.append('delete_branch','1');
                    const id = fd.get('branch_id');
                    if(!id){ showToast('error','Thiếu ID chi nhánh'); return; }
                    const submitBtn = deleteForm.querySelector('button[type="submit"]');
                    const originalText = submitBtn.textContent;
                    submitBtn.disabled = true; submitBtn.textContent = 'Đang xóa...';
                    try{
                        const resp = await fetch(BRANCH_DELETE_URL, { method:'POST', body: fd, headers: { 'Accept':'application/json','X-Requested-With':'XMLHttpRequest' }});
                        const ct = resp.headers.get('Content-Type')||'';
                        let data;
                        if(ct.includes('application/json')){
                            data = await resp.json();
                        } else {
                            const text = await resp.text();
                            throw new Error('Phản hồi không phải JSON: '+text.slice(0,120));
                        }
                        if(data.ok){
                            showToast('success', data.message || 'Xóa chi nhánh thành công');
                            closeDeleteConfirm();
                            // Close detail modal as well and refresh list
                            setTimeout(()=>{ location.reload(); }, 600);
                        } else {
                            let msg = data.message || 'Không thể xóa';
                            if(data.counts){
                                const {employees=0,devices=0,appointments=0} = data.counts;
                                msg += ` (Nhân viên: ${employees}, Thiết bị: ${devices}, Lich hẹn: ${appointments})`;
                            }
                            showToast('error', msg);
                        }
                    }catch(err){
                        console.error('Delete error', err);
                        showToast('error','Không thể kết nối máy chủ');
                    } finally {
                        submitBtn.disabled = false; submitBtn.textContent = originalText;
                    }
                });
            }
            // Toggle inline edit mode
            document.addEventListener('click',function(e){
                if(e.target && e.target.id==='toggle_edit'){
                    editMode = !editMode;
                    const ids=['edit_branch_name','edit_branch_phone','edit_branch_address'];
                    ids.forEach(id=>{
                        const el=document.getElementById(id);
                        el.disabled = !editMode;
                        el.classList.toggle('bg-white',editMode);
                        el.classList.toggle('bg-gray-100',!editMode);
                        if(editMode) el.classList.add('border-indigo-500'); else el.classList.remove('border-indigo-500');
                    });
                    document.getElementById('map_hint').classList.toggle('hidden',!editMode);
                    document.getElementById('save_branch_btn').classList.toggle('hidden',!editMode);
                    e.target.textContent = editMode? 'Hủy' : 'Chỉnh sửa';
                    // Enable draggable marker when entering edit mode
                    if(editMode && leafletMarker){
                        const pos = leafletMarker.getLatLng();
                        setOrMoveMarker(pos,true);
                    }
                }
            });
        });
    </script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-6xl mx-auto bg-white p-8 rounded-xl shadow-lg space-y-6">
        <h1 class="text-3xl font-bold text-indigo-700 mb-4 text-center">Quản lý chi nhánh</h1>

        <!-- Thanh tìm kiếm và nút thêm -->
        <form method="GET" class="flex flex-wrap justify-between items-center gap-2 mb-4">
    <!-- Ô tìm kiếm và nút tìm -->
    <div class="flex items-center gap-2">
        <input type="text" name="search" placeholder="Tìm theo tên chi nhánh"
               value="<?= htmlspecialchars($search) ?>"
               class="border rounded px-4 py-2 w-64">
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded shadow">
            Tìm
        </button>
    </div>

    <!-- Nút thêm chi nhánh -->
    <button type="button" onclick="toggleForm('add_branch_form')"
            class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded shadow">
        Thêm chi nhánh
    </button>
</form>


        <!-- Bảng dữ liệu -->
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white rounded-lg shadow-md mt-4">
                <thead class="bg-indigo-500 text-white">
                    <tr>
                        <th class="py-2 px-4 border-b text-left">ID</th>
                        <th class="py-2 px-4 border-b text-left">Tên chi nhánh</th>
                        <th class="py-2 px-4 border-b text-left">Số điện thoại</th>
                        <th class="py-2 px-4 border-b text-left">Địa chỉ</th>
                        <th class="py-2 px-4 border-b text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($branch = mysqli_fetch_assoc($branches)): ?>
                    <tr class="hover:bg-gray-100">
                        <td class="py-2 px-4 border-b"><?= $branch['ID_CN'] ?></td>
                        <td class="py-2 px-4 border-b"><?= htmlspecialchars($branch['TEN_CN']) ?></td>
                        <td class="py-2 px-4 border-b"><?= htmlspecialchars($branch['SDT_CN']) ?></td>
                        <td class="py-2 px-4 border-b"><?= htmlspecialchars($branch['DIA_CHI_CN']) ?></td>
                        <td class="py-2 px-4 border-b text-center space-x-2">
                            <button type="button"
                                    class="bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-1 rounded btn-detail"
                                    data-id="<?= $branch['ID_CN'] ?>">
                                Chi tiết
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Phân trang -->
        <div class="flex justify-center items-center gap-2">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=branches&search=<?= urlencode($search) ?>&p=<?= $i ?>"
                   class="px-3 py-1 border rounded <?= $i == $page ? 'bg-indigo-600 text-white' : 'bg-gray-100' ?>">
                   <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>

        <!-- Form thêm -->
        <div id="add_branch_form" class="hidden mt-6 bg-gray-50 p-4 rounded-lg shadow space-y-2">
            <h2 class="text-xl font-semibold mb-2">Thêm chi nhánh</h2>
            <form method="POST">
                <input type="text" name="branch_name" placeholder="Tên chi nhánh" required class="border px-4 py-2 rounded w-full">
                <input type="text" name="branch_phone" placeholder="Số điện thoại" required class="border px-4 py-2 rounded w-full">
                <input type="text" name="branch_address" placeholder="Địa chỉ" required class="border px-4 py-2 rounded w-full">
                <div class="flex justify-end gap-2 mt-2">
                    <button type="submit" name="add_branch" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Lưu</button>
                    <button type="button" onclick="toggleForm('add_branch_form')" class="bg-gray-400 text-white px-4 py-2 rounded">Hủy</button>
                </div>
            </form>
        </div>

        <!-- Modal chi tiết chi nhánh -->
        <div id="branch_detail_modal" class="hidden fixed inset-0 z-50 bg-black/40 p-4">
            <div class="bg-white w-full max-w-5xl rounded-lg shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="flex justify-between items-center px-6 py-4 border-b shrink-0">
                    <h2 class="text-2xl font-semibold" id="detail_title">Chi nhánh</h2>
                    <button type="button" class="text-gray-500 hover:text-gray-700" id="detail_close">✕</button>
                </div>
                <form method="POST" id="inline_edit_form" class="grid md:grid-cols-2 gap-6 p-6 overflow-y-auto">
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Thông tin</h3>
                            <input type="hidden" id="edit_branch_id" name="branch_id">
                            <label class="block text-xs font-medium text-gray-600 mt-2">Tên chi nhánh</label>
                            <input type="text" id="edit_branch_name" name="branch_name" maxlength="100" class="border px-3 py-2 rounded w-full bg-gray-100" disabled>
                            <label class="block text-xs font-medium text-gray-600 mt-2">Số điện thoại</label>
                            <input type="tel" id="edit_branch_phone" name="branch_phone" pattern="^[0-9]{9,12}$" maxlength="12" class="border px-3 py-2 rounded w-full bg-gray-100" disabled>
                            <label class="block text-xs font-medium text-gray-600 mt-2">Địa chỉ</label>
                            <input type="text" id="edit_branch_address" name="branch_address" maxlength="100" class="border px-3 py-2 rounded w-full bg-gray-100" disabled>
                            <div class="mt-2 text-sm text-gray-700" id="detail_coords"></div>
                            <input type="hidden" id="edit_branch_lat" name="branch_lat">
                            <input type="hidden" id="edit_branch_lng" name="branch_lng">
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Thống kê</h3>
                            <ul class="mt-2 space-y-1 text-gray-700 text-sm" id="detail_stats"></ul>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Quản lý</h3>
                            <ul class="mt-2 space-y-1 text-gray-700 text-sm" id="detail_managers"></ul>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="aspect-video w-full bg-gray-100 rounded relative overflow-hidden min-h-[300px]" id="detail_map_wrapper">
                            <div id="leaflet_map" class="w-full h-full"></div>
                            <div id="map_hint" class="absolute top-2 left-2 bg-white/90 text-xs px-2 py-1 rounded shadow hidden">Nhấn bản đồ để chọn tọa độ</div>
                        </div>
                        <div class="border rounded-lg p-4 bg-gray-50">
                            <h3 class="text-sm font-semibold text-gray-600 mb-2">Hành động</h3>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" id="toggle_edit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Chỉnh sửa</button>
                                <button type="submit" name="edit_branch" id="save_branch_btn" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded hidden">Lưu thay đổi</button>
                                <button type="button" id="detail_cancel" class="bg-gray-400 text-white px-4 py-2 rounded">Đóng</button>
                                <button type="button" id="open_delete_confirm" class="ml-auto bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">Xóa chi nhánh</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- Modal xác nhận xóa -->
        <div id="delete_confirm_modal" class="hidden fixed inset-0 z-[60] bg-black/40 p-4">
            <div class="bg-white w-full max-w-lg mx-auto rounded-lg shadow-xl overflow-hidden">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Xác nhận xóa chi nhánh</h3>
                    <button type="button" id="delete_confirm_close" class="text-gray-500 hover:text-gray-700">✕</button>
                </div>
                <div class="p-6 space-y-3 text-sm text-gray-700">
                    <p>Hành động này không thể hoàn tác.</p>
                    <p>Chi nhánh: <span id="delete_branch_name" class="font-semibold"></span></p>
                    <p class="text-red-600 whitespace-nowrap">Lưu ý: Không thể xóa nếu còn nhân viên, thiết bị hoặc lịch hẹn chưa hoàn thành. </p>
                </div>
                <div class="px-6 py-4 border-t flex justify-end gap-2">
                    <button type="button" id="delete_confirm_cancel" class="inline-flex items-center bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 rounded h-11">Hủy</button>
                    <form method="POST" id="delete_confirm_form" action="/stygianblue/app/admin/branch_delete.php">
                        <input type="hidden" name="branch_id" id="delete_branch_id_confirm">
                        <button type="submit" id="delete_confirm_submit" name="delete_branch" class="inline-flex items-center bg-red-600 hover:bg-red-700 text-white px-4 rounded h-11">Xóa vĩnh viễn</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>


