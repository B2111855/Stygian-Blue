<?php /* app/admin/components/sidebar_manager.php */ ?>
<aside class="w-64 bg-gradient-to-b from-gray-900 to-indigo-900 text-white flex flex-col shadow-2xl">
  <div class="p-6 flex items-center gap-3 border-b border-gray-700">
    <i class="fas fa-sitemap text-3xl"></i>
    <h1 class="text-2xl font-extrabold">Quản lý viên</h1>
  </div>
  <nav class="flex-1">
    <ul class="space-y-1 p-4 text-sm font-medium">
      <li><a href="?page=overview"          class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-tachometer-alt"></i>Tổng quan chi nhánh</a></li>
      <li><a href="?page=assignments"       class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-user-cog"></i>Phân công ca/kíp</a></li>
      <li><a href="?page=appointments"      class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-calendar-check"></i>Lịch hẹn chi nhánh</a></li>
      <li><a href="?page=salaries"          class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-hand-holding-usd"></i>Lương/duyệt lương</a></li>
      <li><a href="?page=equipment"         class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-cogs"></i>Thiết bị/tồn kho</a></li>
      <li><a href="?page=costumes"          class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-tshirt"></i>Trang phục chi nhánh</a></li>
      <li><a href="?page=customers"         class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-user-friends"></i>Khách hàng chi nhánh</a></li>
      <li><a href="?page=expenses"          class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-money-bill-wave"></i>Chi phí phát sinh</a></li>
      <li><a href="?page=feedback"          class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-comments"></i>Phản hồi khách</a></li>
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
