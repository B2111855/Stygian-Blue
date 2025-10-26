<?php
// File add_assignment.php

// Kết nối cơ sở dữ liệu
include '../../../database/config.php';

// Lấy danh sách lịch hẹn chưa được phân công và đã xác nhận kèm ID_CHINHANH
$query_schedules = "
    SELECT lh.ID_LICHHEN, lh.THOI_GIAN_BAT_DAU, lh.DIA_CHI_HEN, lh.ID_CHINHANH
    FROM lich_hen lh
    WHERE lh.TRANGTHAI = 'Đã xác nhận'
    AND NOT EXISTS (
        SELECT 1 FROM phan_cong_nhan_vien pc WHERE pc.ID_LICHHEN = lh.ID_LICHHEN
    )
";
$schedules = mysqli_query($conn, $query_schedules);

// Lấy danh sách nhân viên có chi nhánh (JOIN với bảng nhan_vien)
$query_employees = "
    SELECT tk.ID_TK, tk.HO_TEN, nv.ID_CN
    FROM tai_khoan tk
    JOIN nhan_vien nv ON tk.ID_TK = nv.ID_TK
    WHERE tk.ID_QUYEN = 2
";
$employees = mysqli_query($conn, $query_employees);

// Xử lý thêm phân công nếu có submit
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scheduleId = $_POST['scheduleId'];
    $employeeId = $_POST['employeeId'];
    $startTime = $_POST['startTime'];
    $endTime = $_POST['endTime'];

    $check_query = "SELECT * FROM phan_cong_nhan_vien WHERE ID_LICHHEN = '$scheduleId'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        $message = "Lịch hẹn này đã được phân công trước đó.";
        $messageType = 'error';
    } else {
        $insert_query = "INSERT INTO phan_cong_nhan_vien (ID_TK, ID_LICHHEN, THOI_GIAN_BAT_DAU, THOI_GIAN_KET_THUC)
                         VALUES ('$employeeId', '$scheduleId', '$startTime', '$endTime')";
        if (mysqli_query($conn, $insert_query)) {
            $message = "Phân công đã được lưu vào hệ thống!";
            $messageType = 'success';
        } else {
            $message = "Lỗi: " . mysqli_error($conn);
            $messageType = 'error';
        }
    }
}
?>

<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm Phân Công</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">
<div class="max-w-2xl mx-auto">
    <div class="bg-white shadow-xl rounded-lg p-6">
        <h2 class="text-3xl font-bold text-indigo-700 mb-6 text-center">➕ Thêm Phân Công</h2>

        <?php if (!empty($message)) : ?>
            <div class="<?= $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?> border <?= $messageType === 'success' ? 'border-green-300' : 'border-red-300' ?> px-4 py-3 rounded mb-4">
                <strong><?= $messageType === 'success' ? '✅ Thành công!' : '❌ Lỗi!' ?></strong>
                <span><?= $message ?></span>
            </div>
        <?php endif; ?>

        <?php if (mysqli_num_rows($schedules) === 0): ?>
            <div class="text-red-600 font-semibold text-center mb-4">
                Không có lịch hẹn nào chờ phân công.
            </div>
        <?php endif; ?>

        <form method="POST" action="add_assignment.php" class="space-y-4">
            <div>
                <label for="scheduleId" class="block font-medium text-gray-700 mb-1">Chọn Lịch Hẹn</label>
                <select id="scheduleId" name="scheduleId" required onchange="filterEmployeesByBranch()"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Chọn lịch hẹn --</option>
                    <?php while ($schedule = mysqli_fetch_assoc($schedules)) : ?>
                        <option value="<?= $schedule['ID_LICHHEN'] ?>" data-idcn="<?= $schedule['ID_CHINHANH'] ?>" data-start-time="<?= $schedule['THOI_GIAN_BAT_DAU'] ?>">
                            <?= "ID: {$schedule['ID_LICHHEN']} | Bắt đầu: {$schedule['THOI_GIAN_BAT_DAU']}" ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label for="employeeId" class="block font-medium text-gray-700 mb-1">Chọn Nhân Viên</label>
                <select id="employeeId" name="employeeId" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Chọn nhân viên --</option>
                    <?php while ($employee = mysqli_fetch_assoc($employees)) : ?>
                        <option value="<?= $employee['ID_TK'] ?>" data-idcn="<?= $employee['ID_CN'] ?>">
                            <?= $employee['HO_TEN'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <div id="noMatchWarning" class="text-red-500 text-sm mt-1 hidden">Không có nhân viên phù hợp với chi nhánh này.</div>
            </div>

            <div>
                <label for="startTime" class="block font-medium text-gray-700 mb-1">Thời Gian Bắt Đầu</label>
                <input type="datetime-local" id="startTime" name="startTime" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div>
                <label for="endTime" class="block font-medium text-gray-700 mb-1">Thời Gian Kết Thúc</label>
                <input type="datetime-local" id="endTime" name="endTime" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div class="flex justify-between mt-6">
                <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white font-semibold px-5 py-2 rounded-lg shadow transition">
                    ✔️ Thêm Phân Công
                </button>
                <button type="button"
                        onclick="window.location.href='http://localhost:8080/stygianblue/app/admin/admin_dashboard.php?page=assignments'"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2 rounded-lg shadow">
                    ⬅️ Quay Lại
                </button>
            </div>
        </form>
    </div>
</div>
    <script>
        // JavaScript function to update start time based on selected schedule
        function updateStartTime() {
            var scheduleSelect = document.getElementById('scheduleId');
            var startTimeInput = document.getElementById('startTime');
            var endTimeInput = document.getElementById('endTime');

            // Lấy giá trị thời gian bắt đầu từ tùy chọn được chọn
            var selectedOption = scheduleSelect.options[scheduleSelect.selectedIndex];
            var startTime = selectedOption.getAttribute('data-start-time');

            // Gán giá trị thời gian bắt đầu và kết thúc
            if (startTime) {
                startTime = startTime.replace(' ', 'T'); // Chuyển đổi thành định dạng datetime-local
                startTimeInput.value = startTime;
                endTimeInput.value = startTime; // Gán giá trị mặc định cho thời gian kết thúc
            }
            document.getElementById('startTime').addEventListener('click', function() {
                this.focus(); // Đảm bảo trường được focus khi nhấp chuột
            });
            document.getElementById('endTime').addEventListener('click', function() {
                this.focus(); // Tương tự cho trường thời gian kết thúc
            });

            document.getElementById('startTime').addEventListener('click', function() {
                this.showPicker(); // Kích hoạt picker khi nhấp vào input
            });
            document.getElementById('endTime').addEventListener('click', function() {
                this.showPicker();
            });
            document.querySelectorAll('input[type="datetime-local"]').forEach(input => {
                input.addEventListener('click', function() {
                    this.showPicker();
                });
            });

        }
        function filterEmployeesByBranch() {
        const selectedSchedule = document.querySelector('#scheduleId option:checked');
        const scheduleBranchId = selectedSchedule.getAttribute('data-idcn');
        const employees = document.querySelectorAll('#employeeId option');
        let matchCount = 0;

        employees.forEach(option => {
            if (!scheduleBranchId || option.getAttribute('data-idcn') === scheduleBranchId) {
                option.hidden = false;
                matchCount++;
            } else {
                option.hidden = true;
            }
        });

        document.getElementById('employeeId').selectedIndex = 0;
        document.getElementById('noMatchWarning').classList.toggle('hidden', matchCount > 0);

        const startTime = selectedSchedule.getAttribute('data-start-time');
        if (startTime) {
            const formatted = startTime.replace(' ', 'T');
            document.getElementById('startTime').value = formatted;
            document.getElementById('endTime').value = formatted;
        }
    }
    </script>
</body>

</html>