<?php session_start(); 
require_once '..\..\middlewares\require_staff_specialist.php'
?>
<!DOCTYPE html>
<html lang="vi">
<?php 
  $pageTitle = 'Nhân viên chuyên trách'; 
  include './components/head.php'; // dùng chung head của admin
?>
<body class="bg-gray-100 font-sans text-gray-800">
  <div class="flex min-h-screen">

    <!-- Sidebar -->
    <?php include './components/sidebar_staff.php'; ?>

    <!-- Main content -->
    <div class="flex-1 relative overflow-hidden">
      <?php include '../Pages/components/thongbao.php'; ?>
      <img src="https://storage.googleapis.com/a1aa/image/Fuczd4lY6jrjNZZrBoHIu8nkMLFP5TpPG7lwv9u4f24Wjd7JA.jpg" alt="Background" class="absolute inset-0 w-full h-full object-cover opacity-10" />
      <div class="relative z-10 p-8">
        <div class="bg-white bg-opacity-90 backdrop-blur-md rounded-xl p-6 shadow-xl min-h-[80vh]">

          <div id="notification" class="bg-blue-100 text-blue-700 p-4 rounded mb-6 hidden shadow-md animate-fade-in">
            <strong>🔔 Thông báo:</strong> Bạn có cập nhật mới!
          </div>

          <?php
          $page = $_GET['page'] ?? 'staff';
          $map  = [
            'staff'              => 'staff_assignments.php',
            'staff_appoinments'  => 'staff_appointments.php',
            'staff_salary'       => 'staff_salary.php',
            'feedback'           => 'manage_feedback.php',
            'selfInfo'           => 'manage_selfInfo.php',
            'appointment_detail' => 'appointment_detail.php',
          ];
          if (isset($map[$page])) {
            include './components/' . $map[$page];
          } else {
            echo "<h1 class='text-2xl font-bold text-center'>Chào mừng đến với Staff Dashboard (Nhân viên chuyên trách)</h1>";
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
