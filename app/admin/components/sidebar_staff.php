<?php
$staffSidebarPage = $staffCurrentPage ?? ($_GET['page'] ?? 'overview');

if (!function_exists('sbStaffNavClasses')) {
    function sbStaffNavClasses($current, array $targets)
    {
        $base = 'flex items-center gap-3 py-2.5 px-4 rounded-lg transition w-full';
        return in_array($current, $targets, true)
            ? $base . ' bg-indigo-600 text-white shadow-md'
            : $base . ' hover:bg-indigo-500/60 text-indigo-100';
    }
}

// Lấy thông báo cho staff
require_once __DIR__ . '/get_notification_counts.php';
$staffNotifications = ['new_assignments' => 0];

if (isset($_SESSION['ID_TK']) && isset($conn)) {
    $staffAccountId = $_SESSION['ID_TK'];
    $staffNotifications = getStaffNotificationCounts($conn, $staffAccountId);
}

$staffSidebarSections = [
    'Công việc hằng ngày' => [
        [
            'label'   => 'Tổng quan',
            'icon'    => 'fa-th-large',
            'href'    => '?page=overview',
            'targets' => ['overview'],
            'badge'   => null,
        ],
        [
            'label'   => 'Lịch làm việc',
            'icon'    => 'fa-clipboard-check',
            'href'    => '?page=staff',
            'targets' => ['staff', 'staff_assignments'],
            'badge'   => 'new_assignments', // Hiển thị số lịch hẹn mới được phân công
        ],
      
    ],
    'Hiệu suất & chất lượng' => [
        [
            'label'   => 'Bảng lương',
            'icon'    => 'fa-wallet',
            'href'    => '?page=staff_salary',
            'targets' => ['staff_salary'],
            'badge'   => null,
        ],
        [
            'label'   => 'Phản hồi của khách',
            'icon'    => 'fa-comments',
            'href'    => '?page=feedback',
            'targets' => ['feedback'],
            'badge'   => null,
        ],
    ],

];
?>
<aside class="w-64 bg-gradient-to-b from-gray-900 to-indigo-900 text-white flex flex-col shadow-2xl sticky top-0 h-screen">
  <div class="p-6 flex items-center gap-3 border-b border-gray-700">
    <i class="fas fa-user-tie text-3xl transition-transform duration-300 hover:scale-110 hover:rotate-12"></i>
    <h1 class="text-2xl font-extrabold">Nhân viên</h1>
  </div>
  <nav class="flex-1 overflow-y-auto" data-persist-scroll="staff-sidebar">
    <div class="p-4 text-sm font-medium space-y-4">
      <?php $staffSectionIndex = 0; foreach ($staffSidebarSections as $sectionTitle => $sectionItems): $staffSectionIndex++; $staffSectionId = 'staff-section-' . $staffSectionIndex; ?>
        <div class="sidebar-section" data-section-id="<?= $staffSectionId ?>">
          <button type="button" class="section-toggle w-full flex items-center justify-between uppercase text-xs tracking-widest text-indigo-200 font-semibold mb-2 py-1 px-2 rounded hover:bg-indigo-800/30 transition-all duration-200 group" onclick="toggleStaffSection('<?= $staffSectionId ?>')">
            <span><?= htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8') ?></span>
            <i class="fas fa-chevron-down section-icon transition-transform duration-300 text-indigo-300 group-hover:text-white"></i>
          </button>
          <ul class="section-content space-y-1 overflow-hidden transition-all duration-300">
            <?php foreach ($sectionItems as $item): 
              $tooltipText = '';
              if ($item['badge'] === 'new_assignments') {
                $tooltipText = 'Xem lịch hẹn mới được phân công';
              } else {
                $tooltipText = 'Chuyển đến ' . strtolower($item['label']);
              }
            ?>
              <li class="relative group/item">
                <a href="<?= $item['href'] ?>" 
                   class="<?= sbStaffNavClasses($staffSidebarPage, $item['targets']) ?> group/link transition-all duration-200 hover:translate-x-1"
                   data-tooltip="<?= htmlspecialchars($tooltipText, ENT_QUOTES, 'UTF-8') ?>">
                  <i class="fas <?= $item['icon'] ?> transition-all duration-200 group-hover/link:scale-110 group-hover/link:rotate-3"></i>
                  <span class="flex-1"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                  <?php 
                    // Hiển thị badge nếu có
                    if (isset($item['badge']) && $item['badge']) {
                        $badgeCount = $staffNotifications[$item['badge']] ?? 0;
                        if ($badgeCount > 0) {
                          echo '<span class="badge-pulse">' . renderNotificationBadge($badgeCount, 'bg-green-500') . '</span>';
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

body.sidebar-initializing .section-toggle,
body.sidebar-initializing .section-content,
body.sidebar-initializing .section-icon {
  transition: none !important;
}
</style>

<script>
function toggleStaffSection(sectionId) {
  const section = document.querySelector(`[data-section-id="${sectionId}"]`);
  if (!section) return;
  
  const content = section.querySelector('.section-content');
  const icon = section.querySelector('.section-icon');
  
  content.classList.toggle('collapsed');
  icon.classList.toggle('collapsed');
  
  // Save state to localStorage
  const collapsedSections = JSON.parse(localStorage.getItem('staff-collapsed-sections') || '[]');
  const index = collapsedSections.indexOf(sectionId);
  
  if (content.classList.contains('collapsed')) {
    if (index === -1) collapsedSections.push(sectionId);
  } else {
    if (index > -1) collapsedSections.splice(index, 1);
  }
  
  localStorage.setItem('staff-collapsed-sections', JSON.stringify(collapsedSections));
}

// Restore collapsed state on page load
document.addEventListener('DOMContentLoaded', function() {
  document.body.classList.add('sidebar-initializing');
  const collapsedSections = JSON.parse(localStorage.getItem('staff-collapsed-sections') || '[]');
  const sidebar = document.querySelector('nav[data-persist-scroll="staff-sidebar"]');

  if (sidebar) {
    sidebar.querySelectorAll('[data-section-id]').forEach(section => {
      const sectionId = section.getAttribute('data-section-id');
      const content = section.querySelector('.section-content');
      const icon = section.querySelector('.section-icon');

      if (!sectionId || !content || !icon) {
        return;
      }

      const shouldCollapse = collapsedSections.includes(sectionId);
      content.classList.toggle('collapsed', shouldCollapse);
      icon.classList.toggle('collapsed', shouldCollapse);
    });
  }

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
