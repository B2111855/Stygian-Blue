<?php
$managerCurrentPage = $_GET['page'] ?? 'overview';
$managerNavItems = [
  ['slug' => 'overview',     'icon' => 'fas fa-tachometer-alt',     'label' => 'Tổng quan chi nhánh'],
  ['slug' => 'reports',      'icon' => 'fas fa-chart-pie',          'label' => 'Thống kê & báo cáo'],
  ['slug' => 'assignments',  'icon' => 'fas fa-user-cog',           'label' => 'Phân công ca/kíp'],
  ['slug' => 'appointments', 'icon' => 'fas fa-calendar-check',     'label' => 'Lịch hẹn chi nhánh'],
  ['slug' => 'salaries',     'icon' => 'fas fa-hand-holding-usd',   'label' => 'Lương/duyệt lương'],
  ['slug' => 'equipment',    'icon' => 'fas fa-cogs',               'label' => 'Thiết bị/tồn kho'],
  ['slug' => 'costumes',     'icon' => 'fas fa-tshirt',             'label' => 'Trang phục chi nhánh'],
  ['slug' => 'customers',    'icon' => 'fas fa-user-friends',       'label' => 'Khách hàng chi nhánh'],
  ['slug' => 'expenses',     'icon' => 'fas fa-money-bill-wave',    'label' => 'Chi phí phát sinh'],
  ['slug' => 'feedback',     'icon' => 'fas fa-comments',           'label' => 'Phản hồi khách'],
  ['slug' => 'selfInfo',     'icon' => 'fas fa-user-circle',        'label' => 'Thông tin cá nhân'],
];

if (!function_exists('renderManagerNavItem')) {
    function renderManagerNavItem(array $item, string $currentPage): void
    {
        $isActive = $currentPage === $item['slug'];
        $classes = 'flex items-center gap-3 py-2 px-4 rounded-lg transition font-medium ' . (
            $isActive
                ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/40'
                : 'text-gray-100 hover:bg-indigo-600/60'
        );
        echo '<li><a href="?page=' . htmlspecialchars($item['slug']) . '" class="' . $classes . '">'
            . '<i class="' . $item['icon'] . '"></i>'
            . '<span>' . htmlspecialchars($item['label']) . '</span>'
            . '</a></li>';
    }
}
?>
<aside class="w-64 bg-gradient-to-b from-gray-900 to-indigo-900 text-white flex flex-col shadow-2xl">
  <div class="p-6 flex items-center gap-3 border-b border-gray-700">
    <i class="fas fa-sitemap text-3xl"></i>
    <h1 class="text-2xl font-extrabold">Quản lý viên</h1>
  </div>
  <nav class="flex-1">
    <ul class="space-y-1 p-4 text-sm font-medium">
      <?php foreach ($managerNavItems as $item): ?>
        <?php renderManagerNavItem($item, $managerCurrentPage); ?>
      <?php endforeach; ?>
      <li class="pt-2 mt-2 border-t border-white/10">
        <a href="./components/logout.php"
           onclick="return confirm('Bạn có chắc chắn muốn đăng xuất không?')"
           class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-red-600 transition">
          <i class="fas fa-sign-out-alt"></i> Đăng xuất
        </a>
      </li>
    </ul>
  </nav>
</aside>
