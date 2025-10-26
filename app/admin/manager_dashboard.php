<?php 
session_start(); 
require_once '..\..\middlewares\require_staff_manager.php';
?>
<!DOCTYPE html>
<html lang="vi">
<?php 
  $pageTitle = 'Manager Dashboard';
  include './components/head.php'; 
?>
<body class="bg-gray-100 font-sans text-gray-800">
  <div class="flex min-h-screen">

    <!-- Sidebar -->
    <?php include './components/sidebar_manager.php'; ?>

    <!-- Main content -->
    <div class="flex-1 relative overflow-hidden">
      <img src="https://storage.googleapis.com/a1aa/image/Fuczd4lY6jrjNZZrBoHIu8nkMLFP5TpPG7lwv9u4f24Wjd7JA.jpg" alt="Background" class="absolute inset-0 w-full h-full object-cover opacity-10" />
      <div class="relative z-10 p-8">
        <div class="bg-white bg-opacity-90 backdrop-blur-md rounded-xl p-6 shadow-xl min-h-[80vh]">

          <div id="notification" class="bg-blue-100 text-blue-700 p-4 rounded mb-6 hidden shadow-md animate-fade-in">
            <strong>🔔 Thông báo:</strong> Bạn có cập nhật mới!
          </div>

          <?php
          // Router: ánh xạ page -> component file (trong ./components/)
          $page = $_GET['page'] ?? 'overview';
          $map  = [
            'overview'          => 'manager_overview.php',
            'assignments'       => 'manager_assignments.php',
            'appointments'      => 'manager_appointments.php',
            'expenses'          => 'manager_expenses.php',
            'salaries'          => 'manager_salaries.php',
            'equipment'         => 'equipment_branch.php',     // có thể dùng chung với admin nếu bạn muốn
            'customers'         => 'customers_branch.php',
            'feedback'          => 'manage_feedback.php',      // file này có thể đã tồn tại
            'selfInfo'          => 'manage_selfInfo.php',      // file này có thể đã tồn tại
            'appointment_detail'=> 'appointment_detail.php',
          ];

          if (isset($map[$page])) {
            $target = './components/' . $map[$page];
            if (file_exists($target)) {
              include $target;
            } else {
              echo "<div class='p-6 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800'>
                      <div class='font-semibold mb-1'>Chức năng đang phát triển</div>
                      <div>Tệp <code class='text-xs bg-gray-100 px-1 py-0.5 rounded'>" . htmlspecialchars($map[$page]) . "</code> chưa tồn tại trong <code class='text-xs bg-gray-100 px-1 py-0.5 rounded'>/app/admin/components/</code>.</div>
                      <div class='mt-2 text-sm'>Bạn có thể tạo tệp này sau, hệ thống vẫn hoạt động bình thường.</div>
                    </div>";
            }
          } else {
            echo "<h1 class='text-2xl font-bold text-center'>Chào mừng đến với Manager Dashboard</h1>";
          }
          ?>
        </div>
      </div>
    </div>
  </div>

  <script>
    document.querySelectorAll('input[type="datetime-local"]').forEach(el=>{
      el.addEventListener('click', function(){ this.showPicker(); });
    });
  </script>
  <style>
    @keyframes fade-in { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
    .animate-fade-in{ animation: fade-in .4s ease-out both; }
  </style>
</body>
</html>
