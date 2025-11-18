<?php
// Kết nối cơ sở dữ liệu
include '../../../database/config.php';
require_once __DIR__ . '/../../helpers/assets.php';

// Lấy thông tin phân công theo ID Lịch Hẹn và ID Nhân Viên
if (isset($_GET['id']) && isset($_GET['employee_id'])) {
    $id = $_GET['id'];
    $employeeId = $_GET['employee_id'];

    // Truy vấn để lấy thông tin phân công
    $query = "SELECT * FROM phan_cong_nhan_vien WHERE ID_LICHHEN = '$id' AND ID_TK = '$employeeId'";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $assignment = mysqli_fetch_assoc($result);
    } else {
        echo "Không tìm thấy phân công với ID lịch hẹn: $id và ID nhân viên: $employeeId.";
        exit; // Dừng lại nếu không tìm thấy phân công
    }
} else {
    echo "Tham số ID lịch hẹn và ID nhân viên không hợp lệ.";
    exit; // Dừng lại nếu tham số không hợp lệ
}

// Lấy thông tin lịch hẹn và nhân viên
$query_schedules = "SELECT ID_LICHHEN, THOI_GIAN_BAT_DAU, DIA_CHI_HEN FROM lich_hen WHERE TRANGTHAI = 'Đã xác nhận'";
$query_employees = "SELECT ID_TK, HO_TEN FROM tai_khoan WHERE ID_QUYEN = 2"; // Lấy nhân viên

$schedules = mysqli_query($conn, $query_schedules);
$employees = mysqli_query($conn, $query_employees);

// Cập nhật phân công khi submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scheduleId = $_POST['scheduleId'];
    $employeeId = $_POST['employeeId'];
    $startTime = $_POST['startTime'];
    $endTime = $_POST['endTime'];

    $update_query = "UPDATE phan_cong_nhan_vien
                     SET ID_TK = '$employeeId', ID_LICHHEN = '$scheduleId', THOI_GIAN_BAT_DAU = '$startTime', THOI_GIAN_KET_THUC = '$endTime'
                     WHERE ID_LICHHEN = '$id' AND ID_TK = '$employeeId'";



    if (mysqli_query($conn, $update_query)) {
        // Thông báo thành công
        echo "<div class='bg-green-100 text-green-800 p-4 rounded-lg shadow-md'>
            Cập nhật phân công thành công!
          </div>";
    } else {
        // Thông báo lỗi
        echo "<div class='bg-red-100 text-red-800 p-4 rounded-lg shadow-md'>
            Lỗi: " . mysqli_error($conn) . "
          </div>";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cập Nhật Phân Công</title>
  <?= sb_tailwind_link_tag(); ?>
  <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
</head>

<body class="bg-gray-100 min-h-screen p-6">
  <div class="max-w-2xl mx-auto">
    <div class="bg-white shadow-xl rounded-xl p-8">
      <h2 class="text-3xl font-bold text-indigo-700 mb-6 text-center">
        ✏️ Cập Nhật Phân Công
      </h2>

      <form method="POST"
        action="edit_assignment.php?id=<?= $id ?>&employee_id=<?= $employeeId ?>"
        class="space-y-5">

        <div>
          <label for="scheduleId" class="block text-sm font-medium text-gray-700 mb-1">Lịch Hẹn</label>
          <input type="text" id="scheduleId" name="scheduleId"
            value="<?= $assignment['ID_LICHHEN'] ?>" readonly
            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-700" />
        </div>

        <div>
          <label for="employeeId" class="block text-sm font-medium text-gray-700 mb-1">Nhân Viên</label>
          <input type="text" id="employeeId" name="employeeId"
            value="<?= $assignment['ID_TK'] ?>" readonly
            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-700" />
        </div>

        <div>
          <label for="startTime" class="block text-sm font-medium text-gray-700 mb-1">Thời Gian Bắt Đầu</label>
          <input type="datetime-local" id="startTime" name="startTime"
            value="<?= date('Y-m-d\TH:i', strtotime($assignment['THOI_GIAN_BAT_DAU'])) ?>" required
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>

        <div>
          <label for="endTime" class="block text-sm font-medium text-gray-700 mb-1">Thời Gian Kết Thúc</label>
          <input type="datetime-local" id="endTime" name="endTime"
            value="<?= date('Y-m-d\TH:i', strtotime($assignment['THOI_GIAN_KET_THUC'])) ?>" required
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>

        <div class="flex justify-between mt-6">
          <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow transition">
            💾 Lưu Thay Đổi
          </button>
          <button type="button"
            onclick="window.location.href='http://localhost:8080/stygianblue/app/admin/admin_dashboard.php?page=assignments'"
            class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg shadow">
            ⬅️ Quay Lại
          </button>
        </div>
      </form>
    </div>
  </div>
</body>

</html>
