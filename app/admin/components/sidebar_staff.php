<?php /* app/admin/components/sidebar_staff.php */ ?>
<aside class="w-64 bg-gradient-to-b from-gray-900 to-indigo-900 text-white flex flex-col shadow-2xl">
  <div class="p-6 flex items-center gap-3 border-b border-gray-700">
    <i class="fas fa-user-tie text-3xl"></i>
    <h1 class="text-2xl font-extrabold">Nhân viên</h1>
  </div>
  <nav class="flex-1">
    <ul class="space-y-1 p-4 text-sm font-medium">
      <li><a href="?page=staff"             class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-clipboard-check"></i>Lịch làm việc</a></li>
      <li><a href="?page=staff_salary"      class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-wallet"></i>Bảng lương</a></li>
      <li><a href="?page=feedback"          class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-comments"></i>Phản hồi của khách</a></li>
      <li><a href="?page=selfInfo"          class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-user-circle"></i>Thông tin cá nhân</a></li>
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
