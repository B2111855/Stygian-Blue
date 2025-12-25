<?php
/**
 * Feedback Component - PRACTICAL UPGRADE (v5)
 * 
 * Cân bằng: Tính năng hữu ích + Đơn giản hóa + Dễ bảo trì
 * 
 * Changes from v4:
 * ✓ Thêm info liên quan (customer + appointment) inline
 * ✓ Quick action links (xem lịch hẹn, xem nhân viên, xem khách)
 * ✓ Optimized single query (JOIN tất cả dữ liệu)
 * ✓ Giữ lại responsive + accessibility
 * ✗ Loại bỏ: 3-column layout, modals phức tạp, real-time updates
 */

include '../../../database/config.php';

// Pagination
$limit = 6;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $limit;

// Get total
$totalQuery = "SELECT COUNT(*) as total FROM phan_hoi_cua_khach_hang";
$total = (int)(mysqli_fetch_assoc(mysqli_query($conn, $totalQuery))['total'] ?? 0);
$pages = max(1, ceil($total / $limit));
$page = min($page, $pages);
$offset = ($page - 1) * $limit;

// ============ OPTIMIZED QUERY ============
// Join tất cả dữ liệu cần thiết trong 1 query
$query = "
  SELECT 
    -- Feedback data
    ph.ID_PHAN_HOI,
    ph.NOI_DUNG,
    ph.XEP_HANG_DV,
    ph.NGAY_GUI,
    
    -- Customer data
    ph.ID_TK,
    tk.HO_TEN as customer_name,
    tk.SDT as customer_phone,
    
    -- Service data
    ph.ID_DV,
    dv.TEN_DV as service_name,
    
    -- Appointment data (most recent)
    lh.ID_LICHHEN,
    lh.THOI_GIAN_BAT_DAU as appointment_start,
    lh.THOI_GIAN_KET_THUC as appointment_end,
    lh.DIA_CHI_HEN as appointment_location,
    lh.TRANGTHAI as appointment_status,
    
    -- Staff assignments for this appointment
    GROUP_CONCAT(DISTINCT nv_info.HO_TEN SEPARATOR ', ') as assigned_staff
    
  FROM phan_hoi_cua_khach_hang ph
  LEFT JOIN tai_khoan tk ON ph.ID_TK = tk.ID_TK
  LEFT JOIN dich_vu dv ON ph.ID_DV = dv.ID_DV
  LEFT JOIN (
    SELECT * FROM lich_hen 
    WHERE ID_LICHHEN IN (
      SELECT MAX(ID_LICHHEN) FROM lich_hen 
      GROUP BY ID_TK, ID_DV
    )
  ) lh ON lh.ID_TK = ph.ID_TK AND lh.ID_DV = ph.ID_DV
  LEFT JOIN phan_cong_nhan_vien pc ON lh.ID_LICHHEN = pc.ID_LICHHEN
  LEFT JOIN tai_khoan nv_info ON pc.ID_TK = nv_info.ID_TK
  
  GROUP BY ph.ID_PHAN_HOI
  ORDER BY ph.NGAY_GUI DESC
  LIMIT $limit OFFSET $offset
";

$feedbacks = [];
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
  $feedbacks[] = $row;
}
?>

<!-- feedback.php (v5) — Practical upgrade with inline related data -->
<section data-sb-feedback role="region" aria-label="Phản hồi khách hàng"
  class="relative overflow-hidden rounded-3xl m-4 mt-6 min-h-[70vh] flex items-center justify-center text-center
         bg-[linear-gradient(135deg,#ecfeff,#ffffff,#fdf2f8)] text-slate-900">

  <div aria-hidden="true" class="pointer-events-none absolute -top-24 -left-24 w-[44rem] h-[44rem] rounded-full blur-3xl opacity-30 bg-cyan-200 mix-blend-screen"></div>
  <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 -right-24 w-[36rem] h-[36rem] rounded-full blur-3xl opacity-25 bg-fuchsia-200 mix-blend-screen"></div>

  <div class="relative z-10 w-full max-w-6xl mx-auto p-6">
    <h2 class="text-3xl md:text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-sky-600 to-fuchsia-600 mb-6">
      Phản hồi từ khách hàng
    </h2>

    <!-- Toolbar (giữ lại từ v4) -->
    <div class="flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between mb-4 text-left">
      <label class="relative w-full sm:w-80">
        <input id="fbSearch" type="search" inputmode="search" placeholder="Tìm theo tên hoặc nội dung…"
               class="w-full rounded-xl bg-white/90 border border-slate-200 px-4 py-2.5 pr-9 text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-400"
               aria-label="Tìm phản hồi">
        <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-500"></i>
      </label>

      <div class="flex gap-2 justify-end">
        <select id="fbSort" class="rounded-xl bg-white/90 border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400"
                aria-label="Sắp xếp">
          <option value="newest">Mới nhất</option>
          <option value="highest">Điểm cao</option>
          <option value="lowest">Điểm thấp</option>
        </select>
        <button type="button" id="fbRefresh" class="px-3 py-2 rounded-xl text-sm text-slate-700 bg-white/90 border border-slate-200 hover:shadow"
                title="Tải lại"><i class="fas fa-sync-alt"></i></button>
      </div>
    </div>

    <!-- List - Grid layout -->
    <div id="feedback-list" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 text-left" aria-live="polite" aria-busy="false">
      
      <?php foreach ($feedbacks as $fb): ?>
        <div class="fb-card bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-md transition group"
             data-fb-id="<?= $fb['ID_PHAN_HOI'] ?>" data-rating="<?= $fb['XEP_HANG_DV'] ?>">
          
          <!-- Header: Rating + Customer -->
          <div class="p-4 border-b border-slate-100 bg-gradient-to-r from-sky-50 to-blue-50">
            <div class="flex items-start justify-between gap-2 mb-2">
              <div class="flex-1">
                <h3 class="font-semibold text-slate-900 text-sm line-clamp-1">
                  <?= htmlspecialchars($fb['customer_name'] ?? 'N/A') ?>
                </h3>
                <p class="text-xs text-slate-600">
                  <i class="fas fa-phone-alt mr-1"></i><?= htmlspecialchars($fb['customer_phone'] ?? '') ?>
                </p>
              </div>
              <div class="flex-shrink-0 text-right">
                <div class="text-yellow-500 text-sm font-bold">
                  <?= str_repeat('★', (int)$fb['XEP_HANG_DV']) . str_repeat('☆', 5 - (int)$fb['XEP_HANG_DV']) ?>
                </div>
                <span class="text-xs text-slate-600"><?= round($fb['XEP_HANG_DV'], 1) ?>/5</span>
              </div>
            </div>
          </div>

          <!-- Body: Content + Service + Appointment -->
          <div class="p-4 space-y-3">
            
            <!-- Feedback content -->
            <div>
              <p class="text-xs text-slate-500 uppercase font-semibold mb-1">Phản hồi</p>
              <p class="text-sm text-slate-700 line-clamp-2">
                <?= htmlspecialchars($fb['NOI_DUNG']) ?>
              </p>
            </div>

            <!-- Service info -->
            <div class="bg-slate-50 rounded-lg p-2">
              <p class="text-xs text-slate-600">
                <i class="fas fa-star text-amber-500 mr-1"></i>
                <strong>Dịch vụ:</strong> <?= htmlspecialchars($fb['service_name'] ?? 'N/A') ?>
              </p>
            </div>

            <!-- Appointment info (if exists) -->
            <?php if ($fb['ID_LICHHEN']): ?>
              <div class="bg-sky-50 rounded-lg p-2 border border-sky-200">
                <p class="text-xs text-sky-900 mb-1">
                  <i class="fas fa-calendar-check mr-1"></i>
                  <strong>Lịch hẹn:</strong>
                </p>
                <p class="text-xs text-sky-800">
                  📅 <?= date('d/m/Y H:i', strtotime($fb['appointment_start'])) ?>
                </p>
                <p class="text-xs text-sky-800">
                  📍 <?= htmlspecialchars($fb['appointment_location'] ?? 'N/A') ?>
                </p>
                <?php if ($fb['assigned_staff']): ?>
                  <p class="text-xs text-sky-800">
                    👤 <?= htmlspecialchars($fb['assigned_staff']) ?>
                  </p>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="bg-slate-50 rounded-lg p-2 text-xs text-slate-600">
                <i class="fas fa-info-circle mr-1"></i>Chưa có lịch hẹn liên quan
              </div>
            <?php endif; ?>

            <!-- Meta -->
            <div class="flex items-center justify-between text-xs text-slate-600 pt-2 border-t border-slate-100">
              <span><i class="fas fa-calendar-alt mr-1"></i><?= date('d/m/Y', strtotime($fb['NGAY_GUI'])) ?></span>
              <span class="bg-slate-100 text-slate-700 px-2 py-1 rounded text-xs">
                ID: <?= $fb['ID_PHAN_HOI'] ?>
              </span>
            </div>
          </div>

          <!-- Footer: Quick Actions -->
          <div class="p-3 bg-slate-50 border-t border-slate-100 flex gap-2 flex-wrap">
            <a href="?page=appointments&filter_customer=<?= $fb['ID_TK'] ?>" 
               class="flex-1 text-center px-2 py-1.5 rounded text-xs font-medium text-sky-700 bg-sky-50 hover:bg-sky-100 transition"
               title="Xem tất cả lịch hẹn của khách hàng này">
              <i class="fas fa-eye mr-1"></i>Hẹn
            </a>
            <a href="?page=customers&id=<?= $fb['ID_TK'] ?>" 
               class="flex-1 text-center px-2 py-1.5 rounded text-xs font-medium text-purple-700 bg-purple-50 hover:bg-purple-100 transition"
               title="Xem thông tin khách hàng">
              <i class="fas fa-user mr-1"></i>KH
            </a>
            <a href="?page=services&id=<?= $fb['ID_DV'] ?>" 
               class="flex-1 text-center px-2 py-1.5 rounded text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition"
               title="Xem dịch vụ">
              <i class="fas fa-cube mr-1"></i>DV
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Empty state -->
    <div class="hidden mt-6" data-sb-fb-empty>
      <div class="rounded-2xl bg-white/80 border border-slate-200 p-6 text-slate-600 text-center">
        <i class="far fa-inbox text-3xl mb-2 opacity-50"></i>
        <p>Chưa có phản hồi nào.</p>
      </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6 flex justify-center gap-2" id="pagination" role="navigation" aria-label="Phân trang">
      <?php for ($i = 1; $i <= min($pages, 5); $i++): ?>
        <a href="?p=<?= $i ?>"
           class="px-3 py-1 rounded border text-sm transition <?= $i === $page ? 'bg-sky-500 text-white border-sky-500' : 'bg-white border-slate-200 hover:border-slate-400' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>
    </div>
  </div>
</section>

<style>
[data-sb-feedback] {
  overflow-x: hidden;
}

[data-sb-feedback] .fb-card {
  display: flex;
  flex-direction: column;
  transition: all 0.2s ease;
}

[data-sb-feedback] .fb-card:hover {
  transform: translateY(-2px);
  border-color: #0ea5e9;
  box-shadow: 0 8px 16px rgba(14, 165, 233, 0.15);
}

/* Responsive adjustments */
@media (max-width: 640px) {
  [data-sb-feedback] .grid {
    grid-template-columns: 1fr;
  }
}

@media (prefers-reduced-motion: reduce) {
  [data-sb-feedback] * {
    animation: none !important;
    transition: none !important;
  }
}
</style>

<script>
(function(){
  const root = document.querySelector('[data-sb-feedback]');
  if(!root) return;

  const list = root.querySelector('#feedback-list');
  const emptyBox = root.querySelector('[data-sb-fb-empty]');
  const searchInput = root.querySelector('#fbSearch');
  const sortSelect = root.querySelector('#fbSort');
  const refreshBtn = root.querySelector('#fbRefresh');

  const feedbacks = <?= json_encode($feedbacks) ?>;

  // Simple search
  searchInput?.addEventListener('input', debounce(function() {
    const query = this.value.toLowerCase();
    const filtered = feedbacks.filter(fb =>
      fb.customer_name?.toLowerCase().includes(query) ||
      fb.customer_phone?.includes(query) ||
      fb.NOI_DUNG?.toLowerCase().includes(query)
    );
    
    updateList(filtered);
  }, 300));

  // Sort
  sortSelect?.addEventListener('change', function() {
    let sorted = [...feedbacks];
    
    switch(this.value) {
      case 'highest':
        sorted.sort((a, b) => b.XEP_HANG_DV - a.XEP_HANG_DV);
        break;
      case 'lowest':
        sorted.sort((a, b) => a.XEP_HANG_DV - b.XEP_HANG_DV);
        break;
      default: // newest
        sorted.sort((a, b) => new Date(b.NGAY_GUI) - new Date(a.NGAY_GUI));
    }
    
    updateList(sorted);
  });

  // Refresh
  refreshBtn?.addEventListener('click', () => location.reload());

  function updateList(data) {
    if (data.length === 0) {
      list.innerHTML = '';
      emptyBox?.classList.remove('hidden');
      return;
    }

    emptyBox?.classList.add('hidden');
    // Re-render list (simplified - just show count for now)
    console.log('Showing', data.length, 'feedback items');
  }

  function debounce(fn, ms) {
    let timeout;
    return function(...args) {
      clearTimeout(timeout);
      timeout = setTimeout(() => fn.apply(this, args), ms);
    };
  }
})();
</script>

<?php mysqli_close($conn); ?>
