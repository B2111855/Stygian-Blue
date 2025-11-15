<?php
session_start();
require_once __DIR__ . '/../../middlewares/require_staff_specialist.php';

$staffCurrentPage = $_GET['page'] ?? 'overview';

$componentMap = [
    'overview'            => 'staff_dashboard_overview.php',
    'staff'               => 'staff_assignments.php',
    'schedule'            => 'staff_assignments.php',
    'staff_assignments'   => 'staff_assignments.php',
    'staff_appoinments'   => 'staff_branch_access_notice.php',
    'staff_appointments'  => 'staff_branch_access_notice.php',
    'staff_salary'        => 'staff_salary.php',
    'feedback'            => 'staff_feedback.php',
    'selfInfo'            => 'manage_selfInfo.php',
    'appointment_detail'  => 'appointment_detail.php',
];

$componentFile = $componentMap[$staffCurrentPage] ?? $componentMap['overview'];
?>
<!DOCTYPE html>
<html lang="vi">
<?php
  $pageTitle = 'Nhân viên chuyên trách';
  include __DIR__ . '/components/head.php';
?>
<body class="bg-gray-100 font-sans text-gray-800">
  <div class="flex min-h-screen">

    <!-- Sidebar -->
    <?php include __DIR__ . '/components/sidebar_staff.php'; ?>

    <!-- Main content -->
    <div class="flex-1 relative overflow-hidden">
      <?php include __DIR__ . '/../Pages/components/thongbao.php'; ?>
      <img src="https://storage.googleapis.com/a1aa/image/Fuczd4lY6jrjNZZrBoHIu8nkMLFP5TpPG7lwv9u4f24Wjd7JA.jpg" alt="Background" class="absolute inset-0 w-full h-full object-cover opacity-10" />
      <div class="relative z-10 p-8">
        <div class="bg-white bg-opacity-90 backdrop-blur-md rounded-xl p-6 shadow-xl min-h-[80vh]">
          <?php include __DIR__ . '/components/' . $componentFile; ?>
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
