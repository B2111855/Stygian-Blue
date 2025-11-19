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

$staffSidebarSections = [
    'Công việc hằng ngày' => [
        [
            'label'   => 'Tổng quan',
            'icon'    => 'fa-th-large',
            'href'    => '?page=overview',
            'targets' => ['overview'],
        ],
        [
            'label'   => 'Lịch làm việc',
            'icon'    => 'fa-clipboard-check',
            'href'    => '?page=staff',
            'targets' => ['staff', 'schedule', 'staff_assignments'],
        ],
    ],
    'Hiệu suất & chất lượng' => [
        [
            'label'   => 'Bảng lương',
            'icon'    => 'fa-wallet',
            'href'    => '?page=staff_salary',
            'targets' => ['staff_salary'],
        ],
        [
            'label'   => 'Phản hồi của khách',
            'icon'    => 'fa-comments',
            'href'    => '?page=feedback',
            'targets' => ['feedback'],
        ],
    ],
    'Tài khoản cá nhân' => [
        [
            'label'   => 'Thông tin cá nhân',
            'icon'    => 'fa-user-circle',
            'href'    => '?page=selfInfo',
            'targets' => ['selfInfo'],
        ],
    ],
];
?>
<aside class="w-64 bg-gradient-to-b from-gray-900 to-indigo-900 text-white flex flex-col shadow-2xl sticky top-0 h-screen">
  <div class="p-6 flex items-center gap-3 border-b border-gray-700">
    <i class="fas fa-user-tie text-3xl"></i>
    <h1 class="text-2xl font-extrabold">Nhân viên</h1>
  </div>
  <nav class="flex-1 overflow-y-auto">
    <div class="p-4 text-sm font-medium space-y-6">
      <?php foreach ($staffSidebarSections as $sectionTitle => $sectionItems): ?>
        <div>
          <p class="uppercase text-xs tracking-widest text-indigo-200 font-semibold mb-2"><?= htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8') ?></p>
          <ul class="space-y-1">
            <?php foreach ($sectionItems as $item): ?>
              <li>
                <a href="<?= $item['href'] ?>" class="<?= sbStaffNavClasses($staffSidebarPage, $item['targets']) ?>">
                  <i class="fas <?= $item['icon'] ?>"></i>
                  <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
      <div class="pt-2 border-t border-indigo-800/50">
        <ul class="space-y-1">
          <li>
            <a href="./components/logout.php"
               onclick="return confirm('Bạn có chắc chắn muốn đăng xuất không?')"
               class="flex items-center gap-3 py-2.5 px-4 rounded-lg transition w-full bg-red-600/80 hover:bg-red-600 text-white">
              <i class="fas fa-sign-out-alt"></i>
              Đăng xuất
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>
</aside>
