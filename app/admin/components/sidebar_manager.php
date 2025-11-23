<?php
$managerSidebarPage = $_GET['page'] ?? 'overview';

if (!function_exists('sbManagerNavClasses')) {
    function sbManagerNavClasses($current, array $targets)
    {
        $base = 'flex items-center gap-3 py-2.5 px-4 rounded-lg transition w-full font-medium';
        return in_array($current, $targets, true)
            ? $base . ' bg-indigo-600 text-white shadow-md shadow-indigo-900/40'
            : $base . ' text-indigo-100 hover:bg-indigo-600/60';
    }
}

// Lấy thông báo cho manager
require_once __DIR__ . '/get_notification_counts.php';
$managerNotifications = ['appointments_pending' => 0, 'appointments_need_assignment' => 0, 'schedule_change_requests' => 0];

if (isset($_SESSION['ID_TK']) && isset($conn)) {
    $currentAccount = $_SESSION['ID_TK'];
    $branchStmt = $conn->prepare('SELECT ID_CN FROM nhan_vien WHERE ID_TK = ? LIMIT 1');
    if ($branchStmt) {
        $branchStmt->bind_param('s', $currentAccount);
        $branchStmt->execute();
        $branchStmt->bind_result($managerBranchId);
        if ($branchStmt->fetch()) {
            $branchStmt->close();
            $managerNotifications = getManagerNotificationCounts($conn, $managerBranchId);
        } else {
            $branchStmt->close();
        }
    }
}

$managerSidebarSections = [
    'Điều hành chi nhánh' => [
        [
            'label'   => 'Tổng quan chi nhánh',
            'icon'    => 'fas fa-tachometer-alt',
            'href'    => '?page=overview',
            'targets' => ['overview'],
            'badge'   => null,
        ],
        [
            'label'   => 'Thống kê & báo cáo',
            'icon'    => 'fas fa-chart-pie',
            'href'    => '?page=reports',
            'targets' => ['reports'],
            'badge'   => null,
        ],
        [
            'label'   => 'Phân công ca/kíp',
            'icon'    => 'fas fa-user-cog',
            'href'    => '?page=assignments',
            'targets' => ['assignments'],
            'badge'   => 'schedule_change_requests', // Key trong $managerNotifications
        ],
        [
            'label'   => 'Lịch hẹn chi nhánh',
            'icon'    => 'fas fa-calendar-check',
            'href'    => '?page=appointments',
            'targets' => ['appointments', 'appointment_detail'],
            'badge'   => 'appointments_pending', // Chỉ hiển thị số lịch hẹn cần xác nhận (đang chờ)
        ],
    ],
    'Vận hành & nguồn lực' => [
        [
            'label'   => 'Quản lý hóa đơn',
            'icon'    => 'fas fa-file-invoice-dollar',
            'href'    => '?page=invoices',
            'targets' => ['invoices', 'hoa_don_chi_tiet'],
            'badge'   => null,
        ],
        [
            'label'   => 'Lương/duyệt lương',
            'icon'    => 'fas fa-hand-holding-usd',
            'href'    => '?page=salaries',
            'targets' => ['salaries'],
            'badge'   => null,
        ],
        [
            'label'   => 'Thiết bị/tồn kho',
            'icon'    => 'fas fa-cogs',
            'href'    => '?page=equipment',
            'targets' => ['equipment'],
            'badge'   => null,
        ],
        [
            'label'   => 'Trang phục chi nhánh',
            'icon'    => 'fas fa-tshirt',
            'href'    => '?page=costumes',
            'targets' => ['costumes'],
            'badge'   => null,
        ],
      [
        'label'   => 'Gói dịch vụ',
        'icon'    => 'fas fa-box-open',
        'href'    => '?page=packages',
        'targets' => ['packages'],
        'badge'   => null,
      ],
      [
        'label'   => 'Gói trang phục',
        'icon'    => 'fas fa-palette',
        'href'    => '?page=package_costumes',
        'targets' => ['package_costumes'],
        'badge'   => null,
      ],
        [
          'label'   => 'Đơn thuê trang phục',
          'icon'    => 'fas fa-file-signature',
          'href'    => '?page=costume_rentals',
          'targets' => ['costume_rentals'],
          'badge'   => null,
        ],
        [
            'label'   => 'Chi phí phát sinh',
            'icon'    => 'fas fa-money-bill-wave',
            'href'    => '?page=expenses',
            'targets' => ['expenses'],
            'badge'   => null,
        ],
    ],
    'Khách hàng & chất lượng' => [
        [
            'label'   => 'Khách hàng chi nhánh',
            'icon'    => 'fas fa-user-friends',
            'href'    => '?page=customers',
            'targets' => ['customers'],
            'badge'   => null,
        ],
        [
            'label'   => 'Phản hồi khách',
            'icon'    => 'fas fa-comments',
            'href'    => '?page=feedback',
            'targets' => ['feedback'],
            'badge'   => null,
        ],
    ],
    'Tài khoản cá nhân' => [
        [
            'label'   => 'Thông tin cá nhân',
            'icon'    => 'fas fa-user-circle',
            'href'    => '?page=selfInfo',
            'targets' => ['selfInfo'],
            'badge'   => null,
        ],
    ],
];
?>
<aside class="w-64 bg-gradient-to-b from-gray-900 to-indigo-900 text-white flex flex-col shadow-2xl sticky top-0 h-screen">
  <div class="p-6 flex items-center gap-3 border-b border-gray-700">
    <i class="fas fa-sitemap text-3xl transition-transform duration-300 hover:scale-110 hover:rotate-12"></i>
    <h1 class="text-2xl font-extrabold">Quản lý viên</h1>
  </div>
  <nav class="flex-1 overflow-y-auto" data-persist-scroll="manager-sidebar">
    <div class="p-4 text-sm font-medium space-y-4">
      <?php $sectionIndex = 0; foreach ($managerSidebarSections as $sectionTitle => $sectionItems): $sectionIndex++; $sectionId = 'mgr-section-' . $sectionIndex; ?>
        <div class="sidebar-section" data-section-id="<?= $sectionId ?>">
          <button type="button" class="section-toggle w-full flex items-center justify-between text-xs tracking-widest text-indigo-200 font-semibold mb-2 py-1 px-2 rounded hover:bg-indigo-800/30 transition-all duration-200 group" onclick="toggleSection('<?= $sectionId ?>')">
            <span class="uppercase"><?= htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8') ?></span>
            <i class="fas fa-chevron-down section-icon transition-transform duration-300 text-indigo-300 group-hover:text-white"></i>
          </button>
          <ul class="section-content space-y-1 overflow-hidden transition-all duration-300">
            <?php foreach ($sectionItems as $item): 
              $tooltipText = '';
              if ($item['badge'] === 'appointments_pending') {
                $tooltipText = 'Xem và xác nhận các lịch hẹn đang chờ';
              } elseif ($item['badge'] === 'schedule_change_requests') {
                $tooltipText = 'Duyệt các yêu cầu đổi lịch làm việc';
              } else {
                $tooltipText = 'Chuyển đến ' . strtolower($item['label']);
              }
            ?>
              <li class="relative group/item">
                <a href="<?= $item['href'] ?>" 
                   class="<?= sbManagerNavClasses($managerSidebarPage, $item['targets']) ?> group/link"
                   data-tooltip="<?= htmlspecialchars($tooltipText, ENT_QUOTES, 'UTF-8') ?>">
                  <i class="<?= $item['icon'] ?> transition-all duration-200 group-hover/link:scale-110 group-hover/link:rotate-3"></i>
                  <span class="flex-1"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                  <?php 
                    // Hiển thị badge nếu có
                    if (isset($item['badge']) && $item['badge']) {
                        $badgeCount = $managerNotifications[$item['badge']] ?? 0;
                        if ($badgeCount > 0) {
                          echo '<span class="badge-pulse">' . renderNotificationBadge($badgeCount) . '</span>';
                        }
                    }
                  ?>
                </a>
                <div class="tooltip"><?= htmlspecialchars($tooltipText, ENT_QUOTES, 'UTF-8') ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
      <div class="pt-2 border-t border-indigo-800/50 mt-4">
        <ul class="space-y-1">
          <li>
            <a href="./components/logout.php"
               onclick="return confirm('Bạn có chắc chắn muốn đăng xuất không?')"
               class="flex items-center gap-3 py-2.5 px-4 rounded-lg transition-all duration-200 w-full bg-red-600/80 hover:bg-red-600 hover:scale-105 text-white group/logout">
              <i class="fas fa-sign-out-alt transition-transform duration-200 group-hover/logout:translate-x-1"></i>
              Đăng xuất
            </a>
          </li>
        </ul>
      </div>
    </div>
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

/* Enhanced hover effects */
.group\/link:hover {
  transform: translateX(4px);
}
</style>

<script>
function toggleSection(sectionId) {
  const section = document.querySelector(`[data-section-id="${sectionId}"]`);
  if (!section) return;
  
  const content = section.querySelector('.section-content');
  const icon = section.querySelector('.section-icon');
  
  content.classList.toggle('collapsed');
  icon.classList.toggle('collapsed');
  
  // Save state to localStorage
  const collapsedSections = JSON.parse(localStorage.getItem('manager-collapsed-sections') || '[]');
  const index = collapsedSections.indexOf(sectionId);
  
  if (content.classList.contains('collapsed')) {
    if (index === -1) collapsedSections.push(sectionId);
  } else {
    if (index > -1) collapsedSections.splice(index, 1);
  }
  
  localStorage.setItem('manager-collapsed-sections', JSON.stringify(collapsedSections));
}

// Restore collapsed state on page load
document.addEventListener('DOMContentLoaded', function() {
  const collapsedSections = JSON.parse(localStorage.getItem('manager-collapsed-sections') || '[]');
  
  collapsedSections.forEach(sectionId => {
    const section = document.querySelector(`[data-section-id="${sectionId}"]`);
    if (section) {
      const content = section.querySelector('.section-content');
      const icon = section.querySelector('.section-icon');
      content.classList.add('collapsed');
      icon.classList.add('collapsed');
    }
  });
});
</script>
