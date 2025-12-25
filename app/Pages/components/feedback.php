<?php
include '../../../database/config.php';

// Pagination
$limit = 6;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $limit;

// Get total count
$totalQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM phan_hoi_cua_khach_hang");
$total = (int)(mysqli_fetch_assoc($totalQuery)['total'] ?? 0);
$pages = max(1, ceil($total / $limit));
$page = min($page, $pages);
$offset = ($page - 1) * $limit;

// ============ OPTIMIZED SINGLE QUERY ============
// Join all related data in one query
$query = "
  SELECT 
    -- Feedback (synthetic ID because table has no PK)
    CONCAT(ph.ID_TK, '-', ph.ID_DV) AS ID_PHAN_HOI,
    ph.NOI_DUNG,
    ph.XEP_HANG_DV,
    ph.NGAY_GUI,
    
    -- Customer
    ph.ID_TK,
    tk.HO_TEN as customer_name,
    tk.SDT as customer_phone,
    
    -- Service
    ph.ID_DV,
    dv.TEN_DV as service_name,
    
    -- Appointment (most recent)
    lh.ID_LICHHEN,
    lh.THOI_GIAN_BAT_DAU as appointment_start,
    lh.DIA_CHI_HEN as appointment_location,
    
    -- Staff (comma-separated)
    GROUP_CONCAT(DISTINCT nv_info.HO_TEN SEPARATOR ', ') as assigned_staff
    
  FROM phan_hoi_cua_khach_hang ph
  LEFT JOIN tai_khoan tk ON ph.ID_TK = tk.ID_TK
  LEFT JOIN dich_vu dv ON ph.ID_DV = dv.ID_DV
  LEFT JOIN lich_hen lh ON lh.ID_TK = ph.ID_TK AND lh.ID_DV = ph.ID_DV
  LEFT JOIN phan_cong_nhan_vien pc ON lh.ID_LICHHEN = pc.ID_LICHHEN
  LEFT JOIN tai_khoan nv_info ON pc.ID_TK = nv_info.ID_TK
  
  GROUP BY
    ph.NOI_DUNG,
    ph.XEP_HANG_DV,
    ph.NGAY_GUI,
    ph.ID_TK,
    tk.HO_TEN,
    tk.SDT,
    ph.ID_DV,
    dv.TEN_DV,
    lh.ID_LICHHEN,
    lh.THOI_GIAN_BAT_DAU,
    lh.DIA_CHI_HEN
  ORDER BY ph.NGAY_GUI DESC
  LIMIT $limit OFFSET $offset
";

$feedbacks = [];
$result = mysqli_query($conn, $query);

// Check for query errors
if (!$result) {
  error_log("Feedback query failed: " . mysqli_error($conn));
  $feedbacks = [];
} else {
  while ($row = mysqli_fetch_assoc($result)) {
    $feedbacks[] = $row;
  }
}
?>
<!-- feedback.php (v4) — Bright + Server-side search/sort/pagination -->
<section data-sb-feedback role="region" aria-label="Phản hồi khách hàng"
  class="relative overflow-hidden rounded-3xl m-4 mt-6 min-h-[70vh] flex items-center justify-center text-center
         bg-[linear-gradient(135deg,#ecfeff,#ffffff,#fdf2f8)] text-slate-900">

  <!-- Pastel bokeh -->
  <div aria-hidden="true" class="pointer-events-none absolute -top-24 -left-24 w-[44rem] h-[44rem] rounded-full blur-3xl opacity-30 bg-cyan-200 mix-blend-screen"></div>
  <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 -right-24 w-[36rem] h-[36rem] rounded-full blur-3xl opacity-25 bg-fuchsia-200 mix-blend-screen"></div>

  <div class="relative z-10 w-full max-w-6xl mx-auto p-6">
    <h2 class="text-3xl md:text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-sky-600 to-fuchsia-600 mb-6">
      Phản hồi từ khách hàng
    </h2>

    <!-- Toolbar -->
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
          <option value="withPhoto">Có hình ảnh</option>
        </select>
        <button type="button" id="fbRefresh" class="px-3 py-2 rounded-xl text-sm text-slate-700 bg-white/90 border border-slate-200 hover:shadow"
                title="Tải lại"><i class="fas fa-sync-alt"></i></button>
      </div>
    </div>

    <!-- List -->
    <div id="feedback-list" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 text-left" aria-live="polite" aria-busy="false">
      <?php foreach ($feedbacks as $fb): 
        $rating = (int)$fb['XEP_HANG_DV'];
        $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
      ?>
        <div class="fb-card bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-md transition hover:-translate-y-1"
             data-fb-id="<?= $fb['ID_PHAN_HOI'] ?>" data-rating="<?= $fb['XEP_HANG_DV'] ?>">
          <div class="p-4 border-b border-slate-100 bg-gradient-to-r from-sky-50 to-blue-50 flex items-start justify-between gap-2">
            <div class="flex-1 min-w-0">
              <h3 class="font-semibold text-slate-900 text-sm truncate">
                <?= htmlspecialchars($fb['customer_name'] ?? 'Khách hàng') ?>
              </h3>
              <p class="text-xs text-slate-700 truncate">
                <?= htmlspecialchars($fb['service_name'] ?? 'Dịch vụ') ?>
              </p>
            </div>
            <div class="flex-shrink-0 text-right">
              <div class="text-yellow-500 text-sm font-bold leading-none">
                <?= $stars ?>
              </div>
              <span class="text-xs text-slate-600"><?= round($fb['XEP_HANG_DV'], 1) ?>/5</span>
            </div>
          </div>

          <div class="p-4 space-y-3">
            <div>
              <p class="text-xs text-slate-500 uppercase font-semibold mb-1">Phản hồi</p>
              <p class="text-sm text-slate-700 line-clamp-3">
                <?= htmlspecialchars($fb['NOI_DUNG']) ?>
              </p>
            </div>

            <div class="flex items-center justify-between text-xs text-slate-600 pt-2 border-t border-slate-100">
              <span><i class="fas fa-calendar-alt mr-1"></i><?= date('d/m/Y', strtotime($fb['NGAY_GUI'])) ?></span>
              <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-xs">
                #<?= $fb['ID_PHAN_HOI'] ?>
              </span>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Empty / Error -->
    <div class="hidden mt-6" data-sb-fb-empty>
      <div class="rounded-2xl bg-white/80 border border-slate-200 p-6 text-slate-600">
        <i class="far fa-inbox mr-2"></i> Chưa có phản hồi nào khớp điều kiện.
      </div>
    </div>
    <div class="hidden mt-6" data-sb-fb-error>
      <div class="rounded-2xl bg-rose-50 border border-rose-200 p-6 text-rose-700">
        <i class="fas fa-exclamation-triangle mr-2"></i> Không tải được phản hồi. Vui lòng thử lại.
      </div>
    </div>

    <!-- Pagination (sẽ cập nhật động theo kết quả server) -->
    <div class="mt-6 flex justify-center gap-2" id="pagination" role="navigation" aria-label="Phân trang">
      <?php for ($i = 1; $i <= min($pages, 5); $i++): ?>
        <a href="?p=<?= $i ?>"
           class="px-3 py-1.5 rounded border text-sm transition font-medium <?= $i === $page ? 'bg-sky-500 text-white border-sky-500' : 'bg-white border-slate-200 text-slate-700 hover:border-slate-400' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>
    </div>
  </div>
</section>




<style>
/* ===== SCOPED to [data-sb-feedback] ===== */
@media (prefers-reduced-motion: reduce){
  [data-sb-feedback] *{ animation:none !important; transition:none !important; }
}
[data-sb-feedback]{ overflow-x:hidden; }

/* Card styles */
[data-sb-feedback] .fb-card {
  transition: all 0.2s ease;
}

[data-sb-feedback] .fb-card:hover {
  border-color: #0ea5e9;
  box-shadow: 0 8px 16px rgba(14, 165, 233, 0.15);
}

/* Responsive grid */
@media (max-width: 640px) {
  [data-sb-feedback] .grid {
    grid-template-columns: 1fr;
  }
}

/* Smooth scrollbar */
[data-sb-feedback] ::-webkit-scrollbar {
  width: 6px;
}
[data-sb-feedback] ::-webkit-scrollbar-track {
  background: #f3f4f6;
}
[data-sb-feedback] ::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 3px;
}
[data-sb-feedback] ::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>

<script>
(function(){
  const root = document.querySelector('[data-sb-feedback]');
  if(!root) return;

  const list = root.querySelector('#feedback-list');
  const qInput = root.querySelector('#fbSearch');
  const sortSel = root.querySelector('#fbSort');
  const btnRef = root.querySelector('#fbRefresh');

  // Simple client-side filter (optional, for UX polish)
  const feedbackData = [
    <?php echo implode(',', array_map(function($fb) {
      return json_encode([
        'id' => $fb['ID_PHAN_HOI'],
        'customer' => $fb['customer_name'],
        'phone' => $fb['customer_phone'],
        'content' => $fb['NOI_DUNG'],
        'rating' => $fb['XEP_HANG_DV'],
        'service' => $fb['service_name']
      ]);
    }, $feedbacks)); ?>
  ];

  // Search functionality
  qInput?.addEventListener('input', debounce(function() {
    const query = this.value.toLowerCase();
    const cards = document.querySelectorAll('[data-fb-id]');
    
    cards.forEach(card => {
      const text = (
        card.textContent.toLowerCase()
      );
      card.style.display = text.includes(query) ? '' : 'none';
    });
  }, 300));

  // Sort functionality
  sortSel?.addEventListener('change', function() {
    const cards = [...document.querySelectorAll('[data-fb-id]')];
    
    if (this.value === 'highest') {
      cards.sort((a, b) => parseFloat(b.dataset.rating) - parseFloat(a.dataset.rating));
    } else if (this.value === 'lowest') {
      cards.sort((a, b) => parseFloat(a.dataset.rating) - parseFloat(b.dataset.rating));
    }
    
    cards.forEach(card => list.appendChild(card));
  });

  // Refresh button
  btnRef?.addEventListener('click', () => location.reload());

  function debounce(fn, ms) {
    let timeout;
    return function() {
      clearTimeout(timeout);
      timeout = setTimeout(() => fn.call(this), ms);
    };
  }
})();
</script>

<?php mysqli_close($conn); ?>
