<?php
$currentPage = $_GET['page'] ?? 'overview';

$adminNavGroups = [
  [
    'title' => 'Điều hành',
    'items' => [
      ['slug' => 'overview',    'icon' => 'fas fa-th-large',        'label' => 'Tổng quan'],
      ['slug' => 'finances',    'icon' => 'fas fa-chart-line',      'label' => 'Thống kê tài chính'],
      ['slug' => 'report',      'icon' => 'fas fa-chart-pie',       'label' => 'Phân tích thị trường'],
    ],
  ],
  [
    'title' => 'Vận hành lịch hẹn',
    'items' => [
      ['slug' => 'appointments','icon' => 'fas fa-calendar-check', 'label' => 'Quản lý lịch hẹn'],
      ['slug' => 'assignments', 'icon' => 'fas fa-user-cog',        'label' => 'Phân công nhân viên'],
      ['slug' => 'payments',    'icon' => 'fas fa-file-invoice',    'label' => 'Quản lý thanh toán'],
      ['slug' => 'salaries',    'icon' => 'fas fa-hand-holding-usd','label' => 'Quản lý lương'],
    ],
  ],
  [
    'title' => 'Nhân sự & khách hàng',
    'items' => [
      ['slug' => 'employees',   'icon' => 'fas fa-user-tie',        'label' => 'Quản lý nhân viên'],
      ['slug' => 'customers',   'icon' => 'fas fa-user-friends',    'label' => 'Quản lý khách hàng'],
      ['slug' => 'feedback',    'icon' => 'fas fa-comment-dots',    'label' => 'Phản hồi của khách'],
      ['slug' => 'selfInfo',    'icon' => 'fas fa-user-circle',     'label' => 'Thông tin cá nhân'],
    ],
  ],
  [
    'title' => 'Dịch vụ & tài sản',
    'items' => [
      ['slug' => 'services',    'icon' => 'fas fa-concierge-bell',  'label' => 'Quản lý dịch vụ'],
      ['slug' => 'branches',    'icon' => 'fas fa-code-branch',     'label' => 'Quản lý chi nhánh'],
      ['slug' => 'equipment',   'icon' => 'fas fa-cogs',            'label' => 'Quản lý thiết bị'],
      ['slug' => 'costumes',    'icon' => 'fas fa-tshirt',          'label' => 'Quản lý trang phục'],
    ],
  ],
];

if (!function_exists('renderAdminNavItem')) {
    function renderAdminNavItem(string $slug, string $icon, string $label, string $currentPage): void
    {
        $isActive = $currentPage === $slug;
        $baseClass = 'flex items-center gap-3 py-2.5 px-4 rounded-xl transition font-medium';
        $stateClass = $isActive
            ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/40'
            : 'text-gray-300 hover:bg-indigo-600/40 hover:text-white';
        echo '<li><a href="?page=' . htmlspecialchars($slug) . '" class="' . $baseClass . ' ' . $stateClass . '">'
            . '<i class="' . $icon . '"></i>'
            . '<span>' . htmlspecialchars($label) . '</span>'
            . '</a></li>';
    }
}
?>
<aside class="w-64 bg-gradient-to-b from-gray-900 to-indigo-900 text-white flex flex-col shadow-2xl sticky top-0 h-screen">
  <div class="p-6 flex items-center gap-3 border-b border-gray-700">
    <i class="fas fa-user-shield text-3xl"></i>
    <h1 class="text-2xl font-extrabold">Quản trị viên</h1>
  </div>
  <nav class="flex-1 overflow-y-auto">
    <ul class="space-y-6 p-4 text-sm">
      <?php foreach ($adminNavGroups as $group): ?>
        <li>
          <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">
            <?= htmlspecialchars($group['title']) ?>
          </p>
          <ul class="space-y-1">
            <?php foreach ($group['items'] as $item) {
                renderAdminNavItem($item['slug'], $item['icon'], $item['label'], $currentPage);
            } ?>
          </ul>
        </li>
      <?php endforeach; ?>
      <li class="pt-2 border-t border-white/10">
        <a href="./components/logout.php"
          onclick="return confirm('Bạn có chắc chắn muốn đăng xuất không?')"
          class="flex items-center gap-3 py-2.5 px-4 rounded-xl transition hover:bg-red-600">
          <i class="fas fa-sign-out-alt"></i>
          <span>Đăng xuất</span>
        </a>
      </li>
    </ul>
  </nav>
</aside>