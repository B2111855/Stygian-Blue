<?php
session_start();
// Buffer output so included components can safely redirect with header()
if (!ob_get_level()) { ob_start(); }
require_once __DIR__ . '/../../middlewares/require_staff_manager.php';

// XỬ LÝ POST TRƯỚC KHI CÓ BẤT KỲ OUTPUT HTML NÀO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../database/config.php';
    
    function redirectWithFlash($type, $message) {
        $_SESSION['manager_assignments_flash'] = [
            'type'    => $type,
            'message' => $message,
        ];
        $target = strtok($_SERVER['REQUEST_URI'] ?? './manager_dashboard.php?page=assignments', '#');
        header('Location: ' . $target);
        exit;
    }
    
    function normalizeDateTimeInput($value) {
        if (!$value) return null;
        $value = trim(str_replace('T', ' ', $value));
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $value)) return null;
        return strlen($value) === 16 ? $value . ':00' : $value;
    }
    
    $currentAccount = $_SESSION['ID_TK'] ?? null;
    $branchId = null;
    if ($currentAccount) {
        $branchStmt = $conn->prepare('SELECT nv.ID_CN FROM nhan_vien nv WHERE nv.ID_TK = ? LIMIT 1');
        $branchStmt->bind_param('s', $currentAccount);
        $branchStmt->execute();
        $branchStmt->bind_result($branchId);
        $branchStmt->fetch();
        $branchStmt->close();
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_assignment') {
        $scheduleId = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
        $employeeId = trim($_POST['employee_id'] ?? '');
        $startTime = normalizeDateTimeInput($_POST['start_time'] ?? '');
        $endTime = normalizeDateTimeInput($_POST['end_time'] ?? '');
        
        if ($scheduleId <= 0 || $employeeId === '' || !$startTime || !$endTime) {
            redirectWithFlash('error', 'Vui lòng nhập đầy đủ thông tin hợp lệ để phân công.');
        }
        if (strtotime($endTime) < strtotime($startTime)) {
            redirectWithFlash('error', 'Thời gian kết thúc cần lớn hơn thời gian bắt đầu.');
        }
        
        $scheduleStmt = $conn->prepare("SELECT 1 FROM lich_hen WHERE ID_LICHHEN = ? AND ID_CHINHANH = ? AND TRANGTHAI = 'Đã xác nhận' LIMIT 1");
        $scheduleStmt->bind_param('ii', $scheduleId, $branchId);
        $scheduleStmt->execute();
        $scheduleStmt->store_result();
        if ($scheduleStmt->num_rows === 0) {
            $scheduleStmt->close();
            redirectWithFlash('error', 'Lịch hẹn không thuộc chi nhánh hoặc chưa được xác nhận.');
        }
        $scheduleStmt->close();
        
        $staffStmt = $conn->prepare('SELECT 1 FROM nhan_vien WHERE ID_TK = ? AND ID_CN = ? LIMIT 1');
        $staffStmt->bind_param('si', $employeeId, $branchId);
        $staffStmt->execute();
        $staffStmt->store_result();
        if ($staffStmt->num_rows === 0) {
            $staffStmt->close();
            redirectWithFlash('error', 'Nhân viên được chọn không thuộc chi nhánh này.');
        }
        $staffStmt->close();
        
        $existStmt = $conn->prepare('SELECT 1 FROM phan_cong_nhan_vien WHERE ID_LICHHEN = ? LIMIT 1');
        $existStmt->bind_param('i', $scheduleId);
        $existStmt->execute();
        $existStmt->store_result();
        if ($existStmt->num_rows > 0) {
            $existStmt->close();
            redirectWithFlash('error', 'Lịch hẹn này đã có phân công.');
        }
        $existStmt->close();
        
        $insertStmt = $conn->prepare('INSERT INTO phan_cong_nhan_vien (ID_TK, ID_LICHHEN, THOI_GIAN_BAT_DAU, THOI_GIAN_KET_THUC) VALUES (?, ?, ?, ?)');
        if (!$insertStmt) {
            redirectWithFlash('error', 'Không thể tạo phân công mới.');
        }
        $insertStmt->bind_param('siss', $employeeId, $scheduleId, $startTime, $endTime);
        $ok = $insertStmt->execute();
        $insertStmt->close();
        redirectWithFlash($ok ? 'success' : 'error', $ok ? 'Đã tạo phân công mới.' : 'Không thể lưu phân công, vui lòng thử lại.');
    }
    
    if ($action === 'update_assignment') {
        $scheduleId = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
        $employeeId = trim($_POST['employee_id'] ?? '');
        $startTime = normalizeDateTimeInput($_POST['start_time'] ?? '');
        $endTime = normalizeDateTimeInput($_POST['end_time'] ?? '');
        
        if ($scheduleId <= 0 || $employeeId === '' || !$startTime || !$endTime) {
            redirectWithFlash('error', 'Thông tin chỉnh sửa chưa hợp lệ.');
        }
        if (strtotime($endTime) < strtotime($startTime)) {
            redirectWithFlash('error', 'Thời gian kết thúc cần lớn hơn thời gian bắt đầu.');
        }
        
        $checkStmt = $conn->prepare('SELECT 1 FROM phan_cong_nhan_vien pc JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN WHERE pc.ID_LICHHEN = ? AND pc.ID_TK = ? AND lh.ID_CHINHANH = ? LIMIT 1');
        $checkStmt->bind_param('isi', $scheduleId, $employeeId, $branchId);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows === 0) {
            $checkStmt->close();
            redirectWithFlash('error', 'Không tìm thấy phân công cần chỉnh sửa trong chi nhánh của bạn.');
        }
        $checkStmt->close();
        
        $updateStmt = $conn->prepare('UPDATE phan_cong_nhan_vien SET THOI_GIAN_BAT_DAU = ?, THOI_GIAN_KET_THUC = ? WHERE ID_LICHHEN = ? AND ID_TK = ?');
        if (!$updateStmt) {
            redirectWithFlash('error', 'Không thể cập nhật phân công.');
        }
        $updateStmt->bind_param('ssis', $startTime, $endTime, $scheduleId, $employeeId);
        $ok = $updateStmt->execute();
        $updateStmt->close();
        redirectWithFlash($ok ? 'success' : 'error', $ok ? 'Đã cập nhật thời gian phân công.' : 'Không thể cập nhật phân công.');
    }
    
    if ($action === 'delete_assignment') {
        $scheduleId = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
        $employeeId = trim($_POST['employee_id'] ?? '');
        
        if ($scheduleId <= 0 || $employeeId === '') {
            redirectWithFlash('error', 'Thông tin phân công cần xóa không hợp lệ.');
        }
        
        $checkStmt = $conn->prepare('SELECT 1 FROM phan_cong_nhan_vien pc JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN WHERE pc.ID_LICHHEN = ? AND pc.ID_TK = ? AND lh.ID_CHINHANH = ? LIMIT 1');
        $checkStmt->bind_param('isi', $scheduleId, $employeeId, $branchId);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows === 0) {
            $checkStmt->close();
            redirectWithFlash('error', 'Bạn không thể xóa phân công này.');
        }
        $checkStmt->close();
        
        $deleteStmt = $conn->prepare('DELETE FROM phan_cong_nhan_vien WHERE ID_LICHHEN = ? AND ID_TK = ?');
        if (!$deleteStmt) {
            redirectWithFlash('error', 'Không thể xóa phân công.');
        }
        $deleteStmt->bind_param('is', $scheduleId, $employeeId);
        $ok = $deleteStmt->execute();
        $deleteStmt->close();
        redirectWithFlash($ok ? 'success' : 'error', $ok ? 'Đã xóa phân công.' : 'Không thể xóa phân công lúc này.');
    }
    
    if ($action === 'request_decision') {
        $requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
        $decision = $_POST['decision'] ?? '';
        $decisionLabel = $decision === 'approve' ? 'Đã duyệt' : ($decision === 'reject' ? 'Từ chối' : null);
        
        if ($requestId <= 0 || !$decisionLabel) {
            redirectWithFlash('error', 'Yêu cầu không hợp lệ.');
        }
        
        $infoStmt = $conn->prepare('SELECT yc.ID_LICHHEN, yc.ID_TK FROM yeu_cau_thay_doi_lich yc JOIN lich_hen lh ON yc.ID_LICHHEN = lh.ID_LICHHEN WHERE yc.ID_YEUCAU = ? AND lh.ID_CHINHANH = ? LIMIT 1');
        $infoStmt->bind_param('ii', $requestId, $branchId);
        $infoStmt->execute();
        $infoStmt->bind_result($scheduleId, $staffId);
        $found = $infoStmt->fetch();
        $infoStmt->close();
        if (!$found) {
            redirectWithFlash('error', 'Không tìm thấy yêu cầu đổi lịch thuộc chi nhánh của bạn.');
        }
        
        $updateStmt = $conn->prepare('UPDATE yeu_cau_thay_doi_lich SET TRANGTHAI = ? WHERE ID_YEUCAU = ?');
        if (!$updateStmt) {
            redirectWithFlash('error', 'Không thể cập nhật trạng thái yêu cầu.');
        }
        $updateStmt->bind_param('si', $decisionLabel, $requestId);
        $ok = $updateStmt->execute();
        $updateStmt->close();
        
        if ($decisionLabel === 'Đã duyệt') {
            $delStmt = $conn->prepare('DELETE FROM phan_cong_nhan_vien WHERE ID_LICHHEN = ? AND ID_TK = ?');
            if ($delStmt) {
                $delStmt->bind_param('is', $scheduleId, $staffId);
                $delStmt->execute();
                $delStmt->close();
            }
        }
        
        $msg = $decisionLabel === 'Đã duyệt' ? 'Đã duyệt yêu cầu đổi lịch.' : 'Đã từ chối yêu cầu đổi lịch.';
        redirectWithFlash($ok ? 'success' : 'error', $ok ? $msg : 'Không thể xử lý yêu cầu đổi lịch.');
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<?php
  $pageTitle = 'Quản lý chi nhánh';
  include __DIR__ . '/components/head.php';
?>
<body class="bg-gray-100 font-sans text-gray-800">
  <div class="flex min-h-screen">

    <!-- Sidebar -->
    <?php include __DIR__ . '/components/sidebar_manager.php'; ?>

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
          // Router: ánh xạ page -> component file (trong ./components/)
          $page = $_GET['page'] ?? 'overview';
          $map  = [
            'overview'          => 'manager_overview.php',
            'reports'           => 'manager_reports.php',
            'assignments'       => 'manager_assignments.php',
            'appointments'      => 'manager_appointments.php',
            'employees'         => 'manager_employees.php',
            'deleted_employees' => 'manager_deleted_employees.php',
            'invoices'          => 'manager_invoices.php',
            'hoa_don_chi_tiet'  => 'hoa_don_chi_tiet.php',
            'expenses'          => 'manager_expenses_v2.php',
            'costumes'          => 'manage_costumes.php',
            'costume_rentals'   => 'manage_costume_rentals.php',
            'salaries'          => 'manage_salaries.php',
            'equipment'         => 'equipment_branch.php',     // có thể dùng chung với admin nếu bạn muốn
            'customers'         => 'customers_branch.php',
            'feedback'          => 'manage_feedback.php',      // file này có thể đã tồn tại
            'selfInfo'          => 'manage_selfInfo.php',      // file này có thể đã tồn tại
            'appointment_detail'=> 'appointment_detail.php',
            'packages'          => 'manage_packages.php',
            'package_costumes'  => 'manage_costume_packages.php',
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
