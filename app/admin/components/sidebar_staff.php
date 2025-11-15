<?php
$staffSidebarPage = $staffCurrentPage ?? ($_GET['page'] ?? 'overview');

if (!function_exists('sbStaffNavClasses')) {
    function sbStaffNavClasses($current, array $targets)
    {
        $base = 'flex items-center gap-3 py-2 px-4 rounded-lg transition';
        return in_array($current, $targets, true)
            ? $base . ' bg-indigo-600 text-white shadow-md'
            : $base . ' hover:bg-indigo-600';
    }
}
?>
<aside class="w-64 bg-gradient-to-b from-gray-900 to-indigo-900 text-white flex flex-col shadow-2xl">
  <div class="p-6 flex items-center gap-3 border-b border-gray-700">
    <i class="fas fa-user-tie text-3xl"></i>
    <h1 class="text-2xl font-extrabold">Nhân viên</h1>
  </div>
  <nav class="flex-1">
    <ul class="space-y-1 p-4 text-sm font-medium">
      <li><a href="?page=overview"          class="<?= sbStaffNavClasses($staffSidebarPage, ['overview']) ?>"><i class="fas fa-th-large"></i>Tổng quan</a></li>
      <li><a href="?page=staff"             class="<?= sbStaffNavClasses($staffSidebarPage, ['staff', 'schedule', 'staff_assignments']) ?>"><i class="fas fa-clipboard-check"></i>Lịch làm việc</a></li>
      <li><a href="?page=staff_salary"      class="<?= sbStaffNavClasses($staffSidebarPage, ['staff_salary']) ?>"><i class="fas fa-wallet"></i>Bảng lương</a></li>
      <li><a href="?page=feedback"          class="<?= sbStaffNavClasses($staffSidebarPage, ['feedback']) ?>"><i class="fas fa-comments"></i>Phản hồi của khách</a></li>
      <li><a href="?page=selfInfo"          class="<?= sbStaffNavClasses($staffSidebarPage, ['selfInfo']) ?>"><i class="fas fa-user-circle"></i>Thông tin cá nhân</a></li>
      <li>
        <a href="./components/logout.php"
           onclick="return confirm('Bạn có chắc chắn muốn đăng xuất không?')"
           class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-red-600 transition">
          <i class="fas fa-sign-out-alt"></i> Đăng xuất
        </a>
      </li>
    </ul>
  </nav>
</aside>
