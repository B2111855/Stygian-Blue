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

$managerSidebarSections = [
    'Điều hành chi nhánh' => [
        [
            'label'   => 'Tổng quan chi nhánh',
            'icon'    => 'fas fa-tachometer-alt',
            'href'    => '?page=overview',
            'targets' => ['overview'],
        ],
        [
            'label'   => 'Thống kê & báo cáo',
            'icon'    => 'fas fa-chart-pie',
            'href'    => '?page=reports',
            'targets' => ['reports'],
        ],
        [
            'label'   => 'Phân công ca/kíp',
            'icon'    => 'fas fa-user-cog',
            'href'    => '?page=assignments',
            'targets' => ['assignments'],
        ],
        [
            'label'   => 'Lịch hẹn chi nhánh',
            'icon'    => 'fas fa-calendar-check',
            'href'    => '?page=appointments',
            'targets' => ['appointments', 'appointment_detail'],
        ],
    ],
    'Vận hành & nguồn lực' => [
        [
            'label'   => 'Lương/duyệt lương',
            'icon'    => 'fas fa-hand-holding-usd',
            'href'    => '?page=salaries',
            'targets' => ['salaries'],
        ],
        [
            'label'   => 'Thiết bị/tồn kho',
            'icon'    => 'fas fa-cogs',
            'href'    => '?page=equipment',
            'targets' => ['equipment'],
        ],
        [
            'label'   => 'Trang phục chi nhánh',
            'icon'    => 'fas fa-tshirt',
            'href'    => '?page=costumes',
            'targets' => ['costumes'],
        ],
        [
            'label'   => 'Chi phí phát sinh',
            'icon'    => 'fas fa-money-bill-wave',
            'href'    => '?page=expenses',
            'targets' => ['expenses'],
        ],
    ],
    'Khách hàng & chất lượng' => [
        [
            'label'   => 'Khách hàng chi nhánh',
            'icon'    => 'fas fa-user-friends',
            'href'    => '?page=customers',
            'targets' => ['customers'],
        ],
        [
            'label'   => 'Phản hồi khách',
            'icon'    => 'fas fa-comments',
            'href'    => '?page=feedback',
            'targets' => ['feedback'],
        ],
    ],
    'Tài khoản cá nhân' => [
        [
            'label'   => 'Thông tin cá nhân',
            'icon'    => 'fas fa-user-circle',
            'href'    => '?page=selfInfo',
            'targets' => ['selfInfo'],
        ],
    ],
];
?>
<aside class="w-64 bg-gradient-to-b from-gray-900 to-indigo-900 text-white flex flex-col shadow-2xl sticky top-0 h-screen">
  <div class="p-6 flex items-center gap-3 border-b border-gray-700">
    <i class="fas fa-sitemap text-3xl"></i>
    <h1 class="text-2xl font-extrabold">Quản lý viên</h1>
  </div>
  <nav class="flex-1 overflow-y-auto">
    <div class="p-4 text-sm font-medium space-y-6">
      <?php foreach ($managerSidebarSections as $sectionTitle => $sectionItems): ?>
        <div>
          <p class="uppercase text-xs tracking-widest text-indigo-200 font-semibold mb-2"><?= htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8') ?></p>
          <ul class="space-y-1">
            <?php foreach ($sectionItems as $item): ?>
              <li>
                <a href="<?= $item['href'] ?>" class="<?= sbManagerNavClasses($managerSidebarPage, $item['targets']) ?>">
                  <i class="<?= $item['icon'] ?>"></i>
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
