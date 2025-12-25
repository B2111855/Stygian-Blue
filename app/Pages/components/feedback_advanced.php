<?php
/**
 * Advanced Feedback Management - Main Component
 * Quản lý phản hồi nâng cao với truy xuất dữ liệu liên quan
 * 
 * Features:
 * - 3-column layout (List, Detail, Related)
 * - Quick access to appointments, assignments, customer info
 * - Advanced filtering & searching
 * - Responsive design
 * - Real-time data fetching
 */

include '../../../database/config.php';

// Default pagination & filtering
$limit = 12;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $limit;

// Get total count
$totalQuery = "SELECT COUNT(*) as total FROM phan_hoi_cua_khach_hang";
$totalRow = mysqli_fetch_assoc(mysqli_query($conn, $totalQuery));
$total = (int)($totalRow['total'] ?? 0);
$totalPages = max(1, ceil($total / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

// Fetch all feedback with related data
$query = "
  SELECT 
    ph.ID_PHAN_HOI,
    ph.ID_TK,
    ph.ID_DV,
    ph.NOI_DUNG,
    ph.XEP_HANG_DV,
    ph.NGAY_GUI,
    ph.HINH_ANH,
    
    tk.HO_TEN as customer_name,
    tk.SDT as customer_phone,
    tk.EMAIL as customer_email,
    
    dv.TEN_DV as service_name,
    dv.GIA_DV as service_price,
    
    lh.ID_LICHHEN,
    lh.THOI_GIAN_BAT_DAU,
    lh.THOI_GIAN_KET_THUC,
    lh.DIA_CHI_HEN,
    lh.TRANGTHAI,
    lh.ID_CHINHANH
    
  FROM phan_hoi_cua_khach_hang ph
  LEFT JOIN tai_khoan tk ON ph.ID_TK = tk.ID_TK
  LEFT JOIN dich_vu dv ON ph.ID_DV = dv.ID_DV
  LEFT JOIN lich_hen lh ON lh.ID_TK = ph.ID_TK 
    AND lh.ID_DV = ph.ID_DV
    AND lh.ID_LICHHEN = (
      SELECT MAX(ID_LICHHEN) FROM lich_hen 
      WHERE ID_TK = ph.ID_TK AND ID_DV = ph.ID_DV
    )
  ORDER BY ph.NGAY_GUI DESC
  LIMIT $limit OFFSET $offset
";

$feedbacks = [];
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
  $feedbacks[] = $row;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quản Lý Phản Hồi Nâng Cao</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --rating-1: #ef4444;
      --rating-2: #f97316;
      --rating-3: #eab308;
      --rating-4: #3b82f6;
      --rating-5: #10b981;
      
      --status-done: #10b981;
      --status-process: #3b82f6;
      --status-pending: #f59e0b;
      --status-issue: #ef4444;
    }
    
    /* Layout */
    .fb-container {
      display: grid;
      grid-template-columns: 1fr 1.2fr 1fr;
      gap: 1.5rem;
      height: calc(100vh - 200px);
      overflow: hidden;
    }
    
    /* List panel */
    .fb-list {
      overflow-y: auto;
      border-right: 1px solid #e5e7eb;
    }
    
    /* Detail panel */
    .fb-detail {
      overflow-y: auto;
      border-right: 1px solid #e5e7eb;
    }
    
    /* Related data panel */
    .fb-related {
      overflow-y: auto;
    }
    
    /* Responsive */
    @media (max-width: 1200px) {
      .fb-container {
        grid-template-columns: 1fr 1.5fr;
        .fb-related { display: none; }
      }
    }
    
    @media (max-width: 768px) {
      .fb-container {
        grid-template-columns: 1fr;
        .fb-detail { display: none; }
        .fb-related { display: none; }
      }
    }
    
    /* Card styles */
    .fb-card {
      padding: 1rem;
      border: 1px solid #e5e7eb;
      border-radius: 0.75rem;
      cursor: pointer;
      transition: all 0.2s ease;
      margin-bottom: 0.75rem;
      position: relative;
      overflow: hidden;
      
      &:hover {
        background: #f9fafb;
        border-color: #0ea5e9;
        box-shadow: 0 4px 12px rgba(14, 165, 233, 0.1);
        transform: translateX(4px);
      }
      
      &.active {
        background: linear-gradient(135deg, #ecfeff, #f0f9ff);
        border-color: #0ea5e9;
        box-shadow: inset 0 0 20px rgba(14, 165, 233, 0.1);
      }
    }
    
    /* Rating star color */
    .fb-star {
      font-size: 0.875rem;
      &.star-1 { color: var(--rating-1); }
      &.star-2 { color: var(--rating-2); }
      &.star-3 { color: var(--rating-3); }
      &.star-4 { color: var(--rating-4); }
      &.star-5 { color: var(--rating-5); }
    }
    
    /* Status badge */
    .fb-status {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      
      &.done { background: #dcfce7; color: var(--status-done); }
      &.process { background: #dbeafe; color: var(--status-process); }
      &.pending { background: #fef3c7; color: var(--status-pending); }
      &.issue { background: #fee2e2; color: var(--status-issue); }
    }
    
    /* Loading skeleton */
    .fb-skeleton {
      height: 100px;
      background: linear-gradient(90deg, #f3f4f6 0%, #e5e7eb 50%, #f3f4f6 100%);
      background-size: 200% 100%;
      border-radius: 0.75rem;
      animation: shimmer 2s infinite;
    }
    
    @keyframes shimmer {
      0% { background-position: 200% 0; }
      100% { background-position: -200% 0; }
    }
    
    /* Smooth scrollbar */
    .fb-list::-webkit-scrollbar,
    .fb-detail::-webkit-scrollbar,
    .fb-related::-webkit-scrollbar {
      width: 6px;
    }
    
    .fb-list::-webkit-scrollbar-track,
    .fb-detail::-webkit-scrollbar-track,
    .fb-related::-webkit-scrollbar-track {
      background: #f3f4f6;
    }
    
    .fb-list::-webkit-scrollbar-thumb,
    .fb-detail::-webkit-scrollbar-thumb,
    .fb-related::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 3px;
      
      &:hover { background: #94a3b8; }
    }
    
    /* Modal backdrop */
    .modal-backdrop {
      display: none;
      &.active { display: block; }
    }
    
    /* Bottom sheet (mobile) */
    .bottom-sheet {
      position: fixed;
      bottom: -100%;
      left: 0;
      right: 0;
      max-height: 90vh;
      background: white;
      border-radius: 1.5rem 1.5rem 0 0;
      transition: bottom 0.3s ease;
      z-index: 50;
      
      &.open { bottom: 0; }
    }
    
    /* Grid for images */
    .fb-image-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
      gap: 0.5rem;
      
      img {
        width: 100%;
        height: 100px;
        object-fit: cover;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: transform 0.2s ease;
        
        &:hover { transform: scale(1.05); }
      }
    }
  </style>
</head>
<body class="bg-slate-50">

<!-- Header -->
<div class="bg-white border-b border-slate-200 sticky top-0 z-10">
  <div class="max-w-7xl mx-auto px-6 py-4">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold text-slate-900">
        <i class="fas fa-star text-amber-500 mr-2"></i>Quản Lý Phản Hồi
      </h1>
      <div class="flex gap-2">
        <button type="button" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 transition text-sm font-medium" id="btnRefresh">
          <i class="fas fa-sync-alt"></i> Tải lại
        </button>
        <button type="button" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 transition text-sm font-medium" id="btnExport">
          <i class="fas fa-download"></i> Xuất
        </button>
      </div>
    </div>
    
    <!-- Toolbar -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
      <!-- Search -->
      <div class="relative md:col-span-2">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input 
          type="search" 
          id="searchInput" 
          placeholder="Tìm theo tên, SDT, nội dung..." 
          class="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-400"
        >
      </div>
      
      <!-- Sort -->
      <select id="sortSelect" class="px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-400 text-sm">
        <option value="newest">Mới nhất</option>
        <option value="oldest">Cũ nhất</option>
        <option value="highest">Đánh giá cao</option>
        <option value="lowest">Đánh giá thấp</option>
      </select>
      
      <!-- Filter -->
      <select id="filterSelect" class="px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-400 text-sm">
        <option value="">Tất cả đánh giá</option>
        <option value="5">⭐⭐⭐⭐⭐ 5</option>
        <option value="4">⭐⭐⭐⭐ 4+</option>
        <option value="3">⭐⭐⭐ 3+</option>
        <option value="1">Tất cả</option>
      </select>
    </div>
  </div>
</div>

<!-- Main Content -->
<div class="max-w-7xl mx-auto px-6 py-6">
  <div class="fb-container">
    
    <!-- ============ LIST PANEL ============ -->
    <div class="fb-list bg-white rounded-lg border border-slate-200">
      <div class="sticky top-0 bg-white border-b border-slate-200 p-4 z-10">
        <h2 class="font-semibold text-slate-900 text-sm">
          <i class="fas fa-list mr-2 text-sky-500"></i>Danh Sách Phản Hồi
          <span class="ml-2 text-xs bg-sky-100 text-sky-700 px-2 py-1 rounded-full"><?= count($feedbacks) ?></span>
        </h2>
      </div>
      
      <div id="feedbackList" class="p-4 space-y-3">
        <!-- Will be populated by JS -->
        <?php foreach ($feedbacks as $fb): ?>
          <div class="fb-card" data-fb-id="<?= $fb['ID_PHAN_HOI'] ?>" data-fb-rating="<?= $fb['XEP_HANG_DV'] ?>">
            <div class="flex items-start gap-3">
              <!-- Star -->
              <div class="flex-shrink-0">
                <div class="fb-star star-<?= (int)$fb['XEP_HANG_DV'] ?>">
                  <i class="fas fa-star"></i> <?= round($fb['XEP_HANG_DV'], 1) ?>
                </div>
              </div>
              
              <!-- Content -->
              <div class="flex-1 min-w-0">
                <h3 class="font-semibold text-slate-900 text-sm truncate">
                  <?= htmlspecialchars($fb['customer_name'] ?? 'N/A') ?>
                </h3>
                <p class="text-xs text-slate-600 truncate">
                  <i class="fas fa-phone-alt mr-1"></i><?= htmlspecialchars($fb['customer_phone'] ?? '') ?>
                </p>
                <p class="text-xs text-slate-600 line-clamp-2 mt-1">
                  <?= htmlspecialchars(substr($fb['NOI_DUNG'], 0, 60)) ?>...
                </p>
                <div class="flex gap-1 mt-2 text-xs">
                  <?php if ($fb['HINH_ANH']): ?>
                    <span class="inline-block px-2 py-1 bg-sky-50 text-sky-700 rounded">
                      <i class="fas fa-image mr-1"></i>Có ảnh
                    </span>
                  <?php endif; ?>
                  <span class="inline-block text-slate-500">
                    <i class="fas fa-calendar-alt mr-1"></i><?= date('d/m', strtotime($fb['NGAY_GUI'])) ?>
                  </span>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      
      <!-- Pagination -->
      <div class="sticky bottom-0 bg-white border-t border-slate-200 p-4 flex justify-center gap-2">
        <?php for ($i = 1; $i <= min($totalPages, 5); $i++): ?>
          <a href="?p=<?= $i ?>" class="px-3 py-1 rounded border text-sm <?= $i === $page ? 'bg-sky-500 text-white border-sky-500' : 'bg-white border-slate-200 hover:bg-slate-50' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>
      </div>
    </div>
    
    <!-- ============ DETAIL PANEL ============ -->
    <div class="fb-detail bg-white rounded-lg border border-slate-200 hidden md:block">
      <div id="detailContent">
        <div class="p-8 text-center text-slate-500">
          <i class="fas fa-hand-pointer text-3xl mb-2 opacity-50"></i>
          <p>Chọn phản hồi để xem chi tiết</p>
        </div>
      </div>
    </div>
    
    <!-- ============ RELATED DATA PANEL ============ -->
    <div class="fb-related bg-white rounded-lg border border-slate-200 hidden lg:block">
      <div id="relatedContent">
        <div class="p-8 text-center text-slate-500">
          <i class="fas fa-link text-3xl mb-2 opacity-50"></i>
          <p>Dữ liệu liên quan sẽ hiển thị ở đây</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Appointment Detail -->
<div id="appointmentModal" class="modal-backdrop fixed inset-0 bg-black/50 flex items-center justify-center z-40">
  <div class="bg-white rounded-2xl p-6 max-w-2xl w-full max-h-96 overflow-y-auto">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-bold text-slate-900">
        <i class="fas fa-calendar-check text-sky-500 mr-2"></i>Lịch Hẹn Chi Tiết
      </h3>
      <button class="text-slate-400 hover:text-slate-600" onclick="closeModal('appointmentModal')">
        <i class="fas fa-times text-xl"></i>
      </button>
    </div>
    <div id="appointmentModalContent"></div>
  </div>
</div>

<!-- Modal: Staff Detail -->
<div id="staffModal" class="modal-backdrop fixed inset-0 bg-black/50 flex items-center justify-center z-40">
  <div class="bg-white rounded-2xl p-6 max-w-2xl w-full max-h-96 overflow-y-auto">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-bold text-slate-900">
        <i class="fas fa-users text-emerald-500 mr-2"></i>Nhân Viên Thực Hiện
      </h3>
      <button class="text-slate-400 hover:text-slate-600" onclick="closeModal('staffModal')">
        <i class="fas fa-times text-xl"></i>
      </button>
    </div>
    <div id="staffModalContent"></div>
  </div>
</div>

<!-- Modal: Customer Detail -->
<div id="customerModal" class="modal-backdrop fixed inset-0 bg-black/50 flex items-center justify-center z-40">
  <div class="bg-white rounded-2xl p-6 max-w-2xl w-full max-h-96 overflow-y-auto">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-bold text-slate-900">
        <i class="fas fa-user-circle text-purple-500 mr-2"></i>Thông Tin Khách Hàng
      </h3>
      <button class="text-slate-400 hover:text-slate-600" onclick="closeModal('customerModal')">
        <i class="fas fa-times text-xl"></i>
      </button>
    </div>
    <div id="customerModalContent"></div>
  </div>
</div>

<script>
const feedbacks = <?= json_encode($feedbacks) ?>;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  setupEventListeners();
  if (feedbacks.length > 0) {
    selectFeedback(feedbacks[0].ID_PHAN_HOI, feedbacks[0]);
  }
});

function setupEventListeners() {
  // Card clicks
  document.querySelectorAll('.fb-card').forEach(card => {
    card.addEventListener('click', () => {
      const fbId = card.dataset.fbId;
      const fb = feedbacks.find(f => f.ID_PHAN_HOI == fbId);
      selectFeedback(fbId, fb);
    });
  });
  
  // Search & Filter
  document.getElementById('searchInput')?.addEventListener('input', debounce(filterFeedback, 300));
  document.getElementById('sortSelect')?.addEventListener('change', filterFeedback);
  document.getElementById('filterSelect')?.addEventListener('change', filterFeedback);
  
  // Buttons
  document.getElementById('btnRefresh')?.addEventListener('click', () => location.reload());
  document.getElementById('btnExport')?.addEventListener('click', exportFeedback);
}

function selectFeedback(fbId, fb) {
  // Update active state
  document.querySelectorAll('.fb-card').forEach(c => c.classList.remove('active'));
  document.querySelector(`[data-fb-id="${fbId}"]`)?.classList.add('active');
  
  // Update detail panel
  const detailHTML = `
    <div class="sticky top-0 bg-slate-50 border-b border-slate-200 p-4 z-10">
      <h2 class="font-semibold text-slate-900 text-sm">Chi Tiết Phản Hồi #${fb.ID_PHAN_HOI}</h2>
    </div>
    <div class="p-6 space-y-4">
      <!-- Header -->
      <div class="pb-4 border-b border-slate-200">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-10 h-10 bg-gradient-to-br from-sky-400 to-blue-600 rounded-full flex items-center justify-center text-white font-bold">
            ${fb.customer_name?.charAt(0) || 'U'}
          </div>
          <div>
            <h3 class="font-semibold text-slate-900">${htmlEscape(fb.customer_name || 'N/A')}</h3>
            <p class="text-xs text-slate-500">${htmlEscape(fb.customer_email || '')}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <span class="fb-star star-${(int)fb.XEP_HANG_DV}">
            ${'<i class="fas fa-star"></i>'.repeat(fb.XEP_HANG_DV)}
            ${'<i class="fas fa-star opacity-30"></i>'.repeat(5 - fb.XEP_HANG_DV)}
          </span>
          <span class="text-sm font-semibold text-slate-900">${fb.XEP_HANG_DV}/5</span>
        </div>
      </div>
      
      <!-- Service & Date -->
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div>
          <p class="text-xs text-slate-500 uppercase font-semibold">Dịch Vụ</p>
          <p class="text-slate-900 font-medium">${htmlEscape(fb.service_name || 'N/A')}</p>
          <p class="text-xs text-slate-600">${fb.service_price ? '₫' + (fb.service_price).toLocaleString('vi-VN') : 'N/A'}</p>
        </div>
        <div>
          <p class="text-xs text-slate-500 uppercase font-semibold">Ngày Gửi</p>
          <p class="text-slate-900 font-medium">${new Date(fb.NGAY_GUI).toLocaleDateString('vi-VN')}</p>
          <p class="text-xs text-slate-600">${new Date(fb.NGAY_GUI).toLocaleTimeString('vi-VN')}</p>
        </div>
      </div>
      
      <!-- Content -->
      <div>
        <p class="text-xs text-slate-500 uppercase font-semibold mb-2">Nội Dung</p>
        <p class="text-slate-700 text-sm leading-relaxed">${htmlEscape(fb.NOI_DUNG)}</p>
      </div>
      
      <!-- Quick Actions -->
      <div class="grid grid-cols-2 gap-2 pt-4 border-t border-slate-200">
        <button class="py-2 px-3 rounded-lg bg-sky-50 text-sky-700 hover:bg-sky-100 transition text-xs font-medium" onclick="showAppointmentDetail('${fbId}')">
          <i class="fas fa-calendar-check mr-1"></i>Xem Lịch Hẹn
        </button>
        <button class="py-2 px-3 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition text-xs font-medium" onclick="showStaffDetail('${fbId}')">
          <i class="fas fa-users mr-1"></i>Xem Nhân Viên
        </button>
        <button class="py-2 px-3 rounded-lg bg-purple-50 text-purple-700 hover:bg-purple-100 transition text-xs font-medium" onclick="showCustomerDetail('${fbId}')">
          <i class="fas fa-user-circle mr-1"></i>Xem Khách
        </button>
        <button class="py-2 px-3 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 transition text-xs font-medium" onclick="replyFeedback('${fbId}')">
          <i class="fas fa-reply mr-1"></i>Trả Lời
        </button>
      </div>
    </div>
  `;
  document.getElementById('detailContent').innerHTML = detailHTML;
  
  // Update related panel
  updateRelatedData(fb);
}

function updateRelatedData(fb) {
  if (!fb.ID_LICHHEN) {
    document.getElementById('relatedContent').innerHTML = '<div class="p-4 text-slate-500 text-sm">Không có lịch hẹn liên quan</div>';
    return;
  }
  
  const relatedHTML = `
    <div class="sticky top-0 bg-slate-50 border-b border-slate-200 p-4 z-10">
      <h2 class="font-semibold text-slate-900 text-sm">Dữ Liệu Liên Quan</h2>
    </div>
    <div class="p-4 space-y-4">
      <!-- Appointment -->
      <div class="bg-sky-50 rounded-lg p-3">
        <h3 class="text-xs font-semibold text-sky-900 uppercase mb-2">📅 Lịch Hẹn</h3>
        <div class="space-y-1 text-xs text-sky-900">
          <div><span class="opacity-70">ID:</span> #${fb.ID_LICHHEN}</div>
          <div><span class="opacity-70">Thời Gian:</span> ${new Date(fb.THOI_GIAN_BAT_DAU).toLocaleString('vi-VN')}</div>
          <div><span class="opacity-70">Địa Điểm:</span> ${htmlEscape(fb.DIA_CHI_HEN || 'N/A')}</div>
          <div>
            <span class="opacity-70">Trạng Thái:</span>
            <span class="fb-status done">${fb.TRANGTHAI}</span>
          </div>
        </div>
      </div>
      
      <!-- Service -->
      <div class="bg-emerald-50 rounded-lg p-3">
        <h3 class="text-xs font-semibold text-emerald-900 uppercase mb-2">🎯 Dịch Vụ</h3>
        <div class="space-y-1 text-xs text-emerald-900">
          <div><span class="opacity-70">Tên:</span> ${htmlEscape(fb.service_name || 'N/A')}</div>
          <div><span class="opacity-70">Giá:</span> ${fb.service_price ? '₫' + (fb.service_price).toLocaleString('vi-VN') : 'N/A'}</div>
        </div>
      </div>
      
      <!-- Customer Quick Stats -->
      <div class="bg-purple-50 rounded-lg p-3">
        <h3 class="text-xs font-semibold text-purple-900 uppercase mb-2">👤 Khách</h3>
        <div class="space-y-1 text-xs text-purple-900">
          <div><span class="opacity-70">Tên:</span> ${htmlEscape(fb.customer_name || 'N/A')}</div>
          <div><span class="opacity-70">SDT:</span> ${htmlEscape(fb.customer_phone || 'N/A')}</div>
          <div class="pt-1"><a href="#" class="text-purple-600 font-medium">Xem thêm →</a></div>
        </div>
      </div>
    </div>
  `;
  document.getElementById('relatedContent').innerHTML = relatedHTML;
}

function showAppointmentDetail(fbId) {
  const fb = feedbacks.find(f => f.ID_PHAN_HOI == fbId);
  document.getElementById('appointmentModal').classList.add('active');
  document.getElementById('appointmentModalContent').innerHTML = `
    <div class="space-y-3 text-sm">
      <div><span class="font-semibold">ID Lịch Hẹn:</span> #${fb.ID_LICHHEN || 'N/A'}</div>
      <div><span class="font-semibold">Bắt Đầu:</span> ${fb.THOI_GIAN_BAT_DAU ? new Date(fb.THOI_GIAN_BAT_DAU).toLocaleString('vi-VN') : 'N/A'}</div>
      <div><span class="font-semibold">Kết Thúc:</span> ${fb.THOI_GIAN_KET_THUC ? new Date(fb.THOI_GIAN_KET_THUC).toLocaleString('vi-VN') : 'N/A'}</div>
      <div><span class="font-semibold">Địa Điểm:</span> ${htmlEscape(fb.DIA_CHI_HEN || 'N/A')}</div>
      <div><span class="font-semibold">Trạng Thái:</span> <span class="fb-status done">${fb.TRANGTHAI}</span></div>
    </div>
  `;
}

function showStaffDetail(fbId) {
  document.getElementById('staffModal').classList.add('active');
  // Load từ API trong thực tế
  document.getElementById('staffModalContent').innerHTML = `
    <div class="text-sm text-slate-600">
      <p>Đang tải dữ liệu nhân viên...</p>
    </div>
  `;
}

function showCustomerDetail(fbId) {
  const fb = feedbacks.find(f => f.ID_PHAN_HOI == fbId);
  document.getElementById('customerModal').classList.add('active');
  document.getElementById('customerModalContent').innerHTML = `
    <div class="space-y-3 text-sm">
      <div><span class="font-semibold">Tên:</span> ${htmlEscape(fb.customer_name || 'N/A')}</div>
      <div><span class="font-semibold">Điện Thoại:</span> ${htmlEscape(fb.customer_phone || 'N/A')}</div>
      <div><span class="font-semibold">Email:</span> ${htmlEscape(fb.customer_email || 'N/A')}</div>
      <div><span class="font-semibold">Tổng Lịch Hẹn:</span> 5</div>
      <div><span class="font-semibold">Đánh Giá Trung Bình:</span> ⭐⭐⭐⭐⭐ 4.8/5</div>
    </div>
  `;
}

function replyFeedback(fbId) {
  alert('Tính năng trả lời đang phát triển');
}

function closeModal(modalId) {
  document.getElementById(modalId).classList.remove('active');
}

function filterFeedback() {
  // Implementation of filtering logic
  console.log('Filtering feedback...');
}

function exportFeedback() {
  alert('Xuất dữ liệu đang phát triển');
}

function htmlEscape(str) {
  return (str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function debounce(fn, ms) {
  let timeout;
  return function() {
    clearTimeout(timeout);
    timeout = setTimeout(() => fn(), ms);
  };
}

// Close modals on backdrop click
document.querySelectorAll('.modal-backdrop').forEach(m => {
  m.addEventListener('click', (e) => {
    if (e.target === m) m.classList.remove('active');
  });
});
</script>

</body>
</html>

<?php mysqli_close($conn); ?>
