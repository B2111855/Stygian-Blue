<?php
session_start();
require_once __DIR__ . '/../../middlewares/require_staff_specialist.php';

$page = $_GET['page'] ?? 'overview';

$componentMap = [
  'overview'            => 'staff_dashboard_overview.php',
  'staff'               => 'staff_assignments.php',
  'schedule'            => 'staff_schedule_grid.php',
  'staff_assignments'   => 'staff_assignments.php',
  'staff_appoinments'   => 'staff_branch_access_notice.php',
  'staff_appointments'  => 'staff_branch_access_notice.php',
  'staff_salary'        => 'staff_salary.php',
  'feedback'            => 'staff_feedback.php',
  'selfInfo'            => 'manage_selfInfo.php',
  'appointment_detail'  => 'appointment_detail.php',
  'schedule_grid'       => 'staff_schedule_grid.php',
];

$componentFile = $componentMap[$page] ?? $componentMap['overview'];
$componentPath = __DIR__ . '/components/' . $componentFile;
// Early exit for CSV export to avoid any prior output before headers
if ($page === 'staff_salary' && isset($_GET['export']) && $_GET['export'] === 'csv') {
  if (file_exists($componentPath)) {
    include $componentPath;
  } else {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Not found";
  }
  exit;
}
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
          <div id="notification" class="bg-blue-100 text-blue-700 p-4 rounded mb-6 hidden shadow-md animate-fade-in">
            <strong>🔔 Thông báo:</strong> Bạn có cập nhật mới!
          </div>

          <?php
            if (file_exists($componentPath)) {
              include $componentPath;
            } else {
              echo "<div class='p-6 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800'>" .
                   "<div class='font-semibold mb-1'>Chức năng đang phát triển</div>" .
                   "<div>Tệp <code class='text-xs bg-gray-100 px-1 py-0.5 rounded'>" . htmlspecialchars($componentFile) . "</code> chưa tồn tại trong <code class='text-xs bg-gray-100 px-1 py-0.5 rounded'>/app/admin/components/</code>.</div>" .
                   "<div class='mt-2 text-sm'>Bạn có thể tạo tệp này sau, hệ thống vẫn hoạt động bình thường.</div>" .
                   "</div>";
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
