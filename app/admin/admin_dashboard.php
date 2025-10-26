<?php
// KHÔNG cần session_start ở đây nếu require_* đã tự start; nếu chưa, vẫn ok.
require_once '../../middlewares/require_admin.php';
include './components/head.php';


$pageTitle = 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="vi">

<body class="bg-gray-100 font-sans text-gray-800">
  <div class="flex min-h-screen">

    <!-- Sidebar -->
    <?php include './components/sidebar_admin.php'; ?>

    <!-- Main content -->
    <div class="flex-1 relative overflow-hidden">
      <img src="https://storage.googleapis.com/a1aa/image/Fuczd4lY6jrjNZZrBoHIu8nkMLFP5TpPG7lwv9u4f24Wjd7JA.jpg"
           alt="Background"
           class="absolute inset-0 w-full h-full object-cover opacity-10" />

      <div class="relative z-10 p-8">
        <div class="bg-white bg-opacity-90 backdrop-blur-md rounded-xl p-6 shadow-xl min-h-[80vh]">

          <div id="notification" class="bg-blue-100 text-blue-700 p-4 rounded mb-6 hidden shadow-md animate-fade-in">
            <strong>🔔 Thông báo:</strong> Bạn có cập nhật mới!
          </div>

          <?php
          // --- Router WHITELIST an toàn ---
          $page = $_GET['page'] ?? 'finances';
          $map  = [
            'finances'          => 'manage_finances.php',
            'report'            => 'trending_report.php',
            'payments'          => 'admin_confirm_payments.php',
            'hoa_don_chi_tiet'  => 'hoa_don_chi_tiet.php',
            'appointment_detail'=> 'appointment_detail.php',
            'appointments'      => 'manage_appointments.php',
            'employees'         => 'manage_employees.php',
            'customers'         => 'manage_customers.php',
            'salaries'          => 'manage_salaries.php',
            'services'          => 'manage_services.php',
            'branches'          => 'manage_branches.php',
            'assignments'       => 'manage_assignments.php',
            'equipment'         => 'manage_equipment.php',
            'expenses'          => 'manager_expenses.php',
            'feedback'          => 'manage_feedback.php',
            'selfInfo'          => 'manage_selfInfo.php',
          ];

          if (isset($map[$page])) {
            include __DIR__ . '/components/' . $map[$page];
          } else {
            echo "<h1 class='text-2xl font-bold text-center'>Chào mừng đến với Admin Dashboard 🎯</h1>";
          }
          ?>
        </div>
      </div>
    </div>
  </div>

  <script>
    document.querySelectorAll('input[type="datetime-local"]').forEach(input => {
      input.addEventListener('click', function () { this.showPicker(); });
    });
  </script>

  <style>
    @keyframes fade-in {
      from { opacity:0; transform: translateY(10px); }
      to   { opacity:1; transform: translateY(0); }
    }
    .animate-fade-in { animation: fade-in 0.4s ease-out both; }
  </style>
</body>
</html>
