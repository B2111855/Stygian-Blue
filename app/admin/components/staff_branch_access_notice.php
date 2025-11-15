<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<div class="flex min-h-[320px] flex-col items-center justify-center rounded-2xl border border-dashed border-indigo-200 bg-white/80 p-10 text-center shadow-sm">
  <h2 class="text-2xl font-semibold text-gray-800">Chức năng dành cho nhân viên quản lý</h2>
  <p class="mt-3 max-w-md text-sm text-gray-600">
    Lịch hẹn của chi nhánh chỉ được quản lý bởi nhân viên quản lý. Nếu bạn cần hỗ trợ,
    vui lòng liên hệ quản lý ca hoặc phụ trách chi nhánh.
  </p>
  <a href="/app/admin/manager_dashboard.php?page=appointments"
     class="mt-6 inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
    Xem giao diện quản lý
  </a>
</div>
