<?php
// /app/admin/components/sidebar_admin.php
?>
<aside class="w-64 bg-gradient-to-b from-gray-900 to-indigo-900 text-white flex flex-col shadow-2xl">
  <div class="p-6 flex items-center gap-3 border-b border-gray-700">
    <i class="fas fa-user-shield text-3xl"></i>
    <h1 class="text-2xl font-extrabold">Admin Panel</h1>
  </div>
  <nav class="flex-1">
    <ul class="space-y-1 p-4 text-sm font-medium">
      <li><a href="?page=finances" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-chart-line"></i>Thống kê</a></li>
      <li><a href="?page=report" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-chart-pie"></i>Phân tích thị trường</a></li>
      <li><a href="?page=appointments" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-calendar-check"></i>Quản lý lịch hẹn</a></li>
      <li><a href="?page=assignments" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-user-cog"></i>Phân công nhân viên</a></li>
      <li><a href="?page=payments" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-file-invoice"></i>Quản lý thanh toán</a></li>
      <li><a href="?page=salaries" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-hand-holding-usd"></i>Quản lý lương</a></li>
      <li><a href="?page=employees" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-user-tie"></i>Quản lý nhân viên</a></li>
      <li><a href="?page=customers" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-user-friends"></i>Quản lý khách hàng</a></li>
      <li><a href="?page=services" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-concierge-bell"></i>Quản lý dịch vụ</a></li>
      <li><a href="?page=branches" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-code-branch"></i>Quản lý chi nhánh</a></li>
      <li><a href="?page=equipment" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-cogs"></i>Quản lý thiết bị</a></li>
      <li><a href="?page=costumes" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-tshirt"></i>Quản lý trang phục</a></li>
      <li><a href="?page=feedback" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-comment-dots"></i>Phản hồi của khách</a></li>
      <li><a href="?page=selfInfo" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-user-circle"></i>Thông tin cá nhân</a></li>
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