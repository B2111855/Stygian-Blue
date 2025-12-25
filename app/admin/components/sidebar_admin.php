<?php
$currentPage = $_GET['page'] ?? 'overview';

// Lấy thông báo cho admin
require_once __DIR__ . '/get_notification_counts.php';
$adminNotifications = ['appointments_pending' => 0, 'appointments_need_assignment' => 0, 'schedule_change_requests' => 0, 'rentals_pending' => 0];

if (isset($conn)) {
    $adminNotifications = getAdminNotificationCounts($conn);
}

// Sections to collapse by default
$defaultCollapsedSections = [4, 5, 6, 7];

$adminNavGroups = [
  [
    'title' => 'Điều hành',
    'tooltip' => 'Quản lý tổng quan & thống kê',
    'items' => [
      ['slug' => 'overview',    'icon' => 'fas fa-th-large',        'label' => 'Tổng quan', 'badge' => null],
      ['slug' => 'finances_v2', 'icon' => 'fas fa-chart-line',      'label' => 'Thống kê tài chính', 'badge' => null],
      ['slug' => 'report',      'icon' => 'fas fa-chart-pie',       'label' => 'Phân tích thị trường', 'badge' => null],
    ],
  ],
  [
    'title' => 'Vận hành',
    'tooltip' => 'Vận hành lịch hẹn & đơn thuê',
    'items' => [
      ['slug' => 'appointments','icon' => 'fas fa-calendar-check', 'label' => 'Quản lý lịch hẹn', 'badge' => 'appointments_pending'],
      ['slug' => 'assignments', 'icon' => 'fas fa-user-cog',        'label' => 'Phân công', 'badge' => 'schedule_change_requests'],
      ['slug' => 'costume_rentals','icon' => 'fas fa-file-signature','label' => 'Quản lý đơn thuê', 'badge' => 'rentals_pending'],
    ],
  ],
  [
    'title' => 'Tài chính',
    'tooltip' => 'Quản lý thanh toán & lương',
    'items' => [
      ['slug' => 'payments',    'icon' => 'fas fa-file-invoice',    'label' => 'Quản lý thanh toán', 'badge' => null],
      ['slug' => 'salaries',    'icon' => 'fas fa-hand-holding-usd','label' => 'Quản lý lương', 'badge' => null],
      ['slug' => 'expense_categories','icon' => 'fas fa-tags',       'label' => 'Loại chi phí', 'badge' => null],
    ],
  ],
  [
    'title' => 'Nhân sự',
    'tooltip' => 'Quản lý nhân viên & khách hàng',
    'items' => [
      ['slug' => 'employees',   'icon' => 'fas fa-user-tie',        'label' => 'Quản lý nhân viên', 'badge' => null],
      ['slug' => 'customers',   'icon' => 'fas fa-user-friends',    'label' => 'Quản lý khách hàng', 'badge' => null],
      ['slug' => 'feedback',    'icon' => 'fas fa-comment-dots',    'label' => 'Phản hồi của khách', 'badge' => null],
    ],
  ],
  [
    'title' => 'Tài sản',
    'tooltip' => 'Quản lý tài sản & dịch vụ',
    'items' => [
      ['slug' => 'services',    'icon' => 'fas fa-concierge-bell',  'label' => 'Quản lý dịch vụ', 'badge' => null],
      ['slug' => 'packages',    'icon' => 'fas fa-box-open',        'label' => 'Gói dịch vụ', 'badge' => null],
      ['slug' => 'costumes',    'icon' => 'fas fa-tshirt',          'label' => 'Quản lý trang phục', 'badge' => null],
      ['slug' => 'package_costumes', 'icon' => 'fas fa-palette',  'label' => 'Gói trang phục', 'badge' => null],
      ['slug' => 'equipment',   'icon' => 'fas fa-cogs',            'label' => 'Quản lý thiết bị', 'badge' => null],
      ['slug' => 'albums',      'icon' => 'fas fa-images',          'label' => 'Quản lý Albums', 'badge' => null],
      ['slug' => 'branches',    'icon' => 'fas fa-code-branch',     'label' => 'Quản lý chi nhánh', 'badge' => null],
    ],
  ],
  [
    'title' => 'Cá nhân',
    'tooltip' => 'Thông tin & cài đặt tài khoản',
    'items' => [
      ['slug' => 'selfInfo',    'icon' => 'fas fa-user-circle',     'label' => 'Thông tin cá nhân', 'badge' => null],
    ],
  ],
  [
    'title' => 'Bảo mật',
    'tooltip' => 'Hệ thống & bảo mật',
    'items' => [
      ['slug' => 'admin_accounts', 'icon' => 'fas fa-shield-alt',   'label' => 'Tài khoản Admin', 'badge' => null],
      ['slug' => 'system_logs', 'icon' => 'fas fa-clipboard-list',  'label' => 'Nhật ký hệ thống', 'badge' => null],
    ],
  ],
];

if (!function_exists('renderAdminNavItem')) {
    function renderAdminNavItem(string $slug, string $icon, string $label, string $currentPage, $badge = null, $adminNotifications = []): void
    {
        renderAdminNavItemEnhanced($slug, $icon, $label, $currentPage, $badge, $adminNotifications);
    }
}

if (!function_exists('renderAdminNavItemEnhanced')) {
    function renderAdminNavItemEnhanced(string $slug, string $icon, string $label, string $currentPage, $badge = null, $adminNotifications = []): void
    {
        $isActive = $currentPage === $slug;
        $baseClass = 'flex items-center gap-3 py-2.5 px-4 rounded-xl transition-all duration-200 font-medium group/link';
        $stateClass = $isActive
            ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/40'
            : 'text-gray-300 hover:bg-indigo-600/40 hover:text-white hover:translate-x-1';
        
        $tooltipText = 'Chuyển đến ' . strtolower($label);
        if ($badge === 'appointments_pending') {
            $tooltipText = 'Xem và xác nhận các lịch hẹn đang chờ';
        } elseif ($badge === 'schedule_change_requests') {
            $tooltipText = 'Xử lý yêu cầu đổi lịch làm việc';
        }
        
        echo '<li class="relative group/item">';
        echo '<a href="?page=' . htmlspecialchars($slug) . '" class="' . $baseClass . ' ' . $stateClass . '" data-tooltip="' . htmlspecialchars($tooltipText) . '">';
        echo '<i class="' . $icon . ' transition-all duration-200 group-hover/link:scale-110 group-hover/link:rotate-3"></i>';
        echo '<span class="flex-1">' . htmlspecialchars($label) . '</span>';
        
        // Hiển thị badge nếu có
        if ($badge) {
            $badgeCount = $adminNotifications[$badge] ?? 0;
            if ($badgeCount > 0) {
                echo '<span class="badge-pulse">' . renderNotificationBadge($badgeCount) . '</span>';
            }
        }
        
        echo '</a>';
        echo '<div class="tooltip">' . htmlspecialchars($tooltipText) . '</div>';
        echo '</li>';
    }
}
?>
<aside class="w-64 bg-gradient-to-b from-gray-900 to-indigo-900 text-white flex flex-col shadow-2xl sticky top-0 h-screen">
  <div class="p-6 flex items-center gap-3 border-b border-gray-700">
    <i class="fas fa-user-shield text-3xl transition-transform duration-300 hover:scale-110 hover:rotate-12"></i>
    <h1 class="text-2xl font-extrabold">Quản trị viên</h1>
  </div>
  <nav class="flex-1 overflow-y-auto" data-persist-scroll="admin-sidebar">
    <ul class="space-y-4 p-4 text-sm">
      <?php $adminSectionIndex = 0; foreach ($adminNavGroups as $group): $adminSectionIndex++; $adminSectionId = 'adm-section-' . $adminSectionIndex; 
      // Check if section should be collapsed by default OR if user has it collapsed in localStorage (passed via JavaScript)
      $isCollapsedByDefault = in_array($adminSectionIndex, $defaultCollapsedSections);
      $collapsedClass = $isCollapsedByDefault ? 'collapsed' : '';
      ?>
        <li class="sidebar-section" data-section-id="<?= $adminSectionId ?>" data-default-collapsed="<?= $isCollapsedByDefault ? '1' : '0' ?>">
          <button type="button" class="section-toggle w-full flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2 py-1 px-2 rounded hover:bg-indigo-800/30 transition-all duration-200 group relative" onclick="toggleAdminSection('<?= $adminSectionId ?>')" title="<?= htmlspecialchars($group['tooltip'] ?? $group['title']) ?>">
            <span><?= htmlspecialchars($group['title']) ?></span>
            <i class="fas fa-chevron-down section-icon transition-transform duration-300 text-gray-500 group-hover:text-gray-300 <?= $collapsedClass ?>"></i>
          </button>
          <ul class="section-content space-y-1 overflow-hidden transition-all duration-300 <?= $collapsedClass ?>">
            <?php foreach ($group['items'] as $item) {
                renderAdminNavItemEnhanced(
                    $item['slug'], 
                    $item['icon'], 
                    $item['label'], 
                    $currentPage, 
                    $item['badge'] ?? null, 
                    $adminNotifications
                );
            } ?>
          </ul>
        </li>
      <?php endforeach; ?>
      <li class="pt-2 border-t border-white/10 mt-4">
        <a href="./components/logout.php"
          onclick="return confirm('Bạn có chắc chắn muốn đăng xuất không?')"
          class="flex items-center gap-3 py-2.5 px-4 rounded-xl transition-all duration-200 hover:bg-red-600 hover:scale-105 group/logout">
          <i class="fas fa-sign-out-alt transition-transform duration-200 group-hover/logout:translate-x-1"></i>
          <span>Đăng xuất</span>
        </a>
      </li>
    </ul>
  </nav>
</aside>

<style>
/* Badge pulse animation */
@keyframes badge-pulse {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.1); opacity: 0.8; }
}

.badge-pulse {
  animation: badge-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
  display: inline-block;
}

/* Tooltip styles */
.tooltip {
  position: absolute;
  left: 100%;
  top: 50%;
  transform: translateY(-50%) translateX(10px);
  background: rgba(17, 24, 39, 0.95);
  color: white;
  padding: 0.5rem 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.75rem;
  white-space: nowrap;
  pointer-events: none;
  opacity: 0;
  transition: opacity 0.2s, transform 0.2s;
  z-index: 1000;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(8px);
}

.tooltip::before {
  content: '';
  position: absolute;
  right: 100%;
  top: 50%;
  transform: translateY(-50%);
  border: 5px solid transparent;
  border-right-color: rgba(17, 24, 39, 0.95);
}

.group\/item:hover .tooltip {
  opacity: 1;
  transform: translateY(-50%) translateX(15px);
}

/* Section collapse animation */
.section-content {
  max-height: 500px;
  opacity: 1;
}

.section-content.collapsed {
  max-height: 0;
  opacity: 0;
  margin-top: 0 !important;
  padding-top: 0;
  padding-bottom: 0;
}

.section-icon.collapsed {
  transform: rotate(-90deg);
}

body.sidebar-initializing .section-toggle,
body.sidebar-initializing .section-content,
body.sidebar-initializing .section-icon {
  transition: none !important;
}
</style>

<script>
function toggleAdminSection(sectionId) {
  const section = document.querySelector(`[data-section-id="${sectionId}"]`);
  if (!section) return;
  
  const content = section.querySelector('.section-content');
  const icon = section.querySelector('.section-icon');
  
  content.classList.toggle('collapsed');
  icon.classList.toggle('collapsed');
  
  // Save state to localStorage
  const collapsedSections = JSON.parse(localStorage.getItem('admin-collapsed-sections') || '[]');
  const index = collapsedSections.indexOf(sectionId);
  
  if (content.classList.contains('collapsed')) {
    if (index === -1) collapsedSections.push(sectionId);
  } else {
    if (index > -1) collapsedSections.splice(index, 1);
  }
  
  localStorage.setItem('admin-collapsed-sections', JSON.stringify(collapsedSections));
}

// Restore collapsed state from localStorage (user's previous interactions)
document.addEventListener('DOMContentLoaded', function() {
  document.body.classList.add('sidebar-initializing');
  const collapsedSections = JSON.parse(localStorage.getItem('admin-collapsed-sections') || '[]');
  
  document.querySelectorAll('[data-section-id]').forEach(section => {
    const sectionId = section.getAttribute('data-section-id');
    const isDefaultCollapsed = section.getAttribute('data-default-collapsed') === '1';
    const wasUserCollapsed = collapsedSections.includes(sectionId);
    const content = section.querySelector('.section-content');
    const icon = section.querySelector('.section-icon');

    if (!content || !icon) {
      return;
    }

    // If user has toggled this section before, use that state
    // Otherwise use the default state
    const shouldBeCollapsed = wasUserCollapsed !== isDefaultCollapsed ? wasUserCollapsed : isDefaultCollapsed;

    content.classList.toggle('collapsed', shouldBeCollapsed);
    icon.classList.toggle('collapsed', shouldBeCollapsed);
  });

  // Restore saved scroll positions for persistent containers
  document.querySelectorAll('[data-persist-scroll]').forEach(container => {
    const key = 'scroll-pos-' + container.getAttribute('data-persist-scroll');
    const savedPosition = localStorage.getItem(key);

    if (savedPosition !== null) {
      const numericPosition = parseInt(savedPosition, 10);
      if (!Number.isNaN(numericPosition)) {
        container.scrollTop = numericPosition;
      }
    }

    container.addEventListener('scroll', () => {
      localStorage.setItem(key, String(container.scrollTop));
    });
  });

  requestAnimationFrame(() => {
    document.body.classList.remove('sidebar-initializing');
  });
});
</script>