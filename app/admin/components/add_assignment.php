<?php
// File add_assignment.php

// Kết nối cơ sở dữ liệu
include '../../../database/config.php';
require_once __DIR__ . '/../../helpers/assets.php';

$branches = [];
$branch_query = "SELECT ID_CN, TEN_CN FROM chi_nhanh ORDER BY TEN_CN ASC";
$branch_result = mysqli_query($conn, $branch_query);
if ($branch_result) {
    while ($row = mysqli_fetch_assoc($branch_result)) {
        $branches[] = $row;
    }
}
// Lấy danh sách lịch hẹn chưa được phân công, đã xác nhận, chưa quá thời gian hiện tại (chuẩn hóa truy vấn)
$query_schedules = "
        SELECT lh.ID_LICHHEN, lh.THOI_GIAN_BAT_DAU, lh.DIA_CHI_HEN, lh.ID_CHINHANH, lh.ID_TK, kh.HO_TEN as TEN_KH,
                     dv.TEN_DV, dv.THOI_GIAN as TONG_THOILUONG
        FROM lich_hen lh
        INNER JOIN khach_hang kh ON lh.ID_TK = kh.ID_TK
        INNER JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
        WHERE lh.TRANGTHAI = 'Đã xác nhận'
            AND lh.THOI_GIAN_BAT_DAU >= NOW()
            AND NOT EXISTS (
                SELECT 1 FROM phan_cong_nhan_vien pc WHERE pc.ID_LICHHEN = lh.ID_LICHHEN
            )
        ORDER BY lh.THOI_GIAN_BAT_DAU ASC
";
$schedules = mysqli_query($conn, $query_schedules);
if ($schedules === false) {
    die('<div style="color:red">Lỗi truy vấn lịch hẹn: ' . htmlspecialchars(mysqli_error($conn)) . '</div>');
}
if ($schedules === false) {
    die('<div style="color:red">Lỗi truy vấn lịch hẹn: ' . htmlspecialchars(mysqli_error($conn)) . '</div>');
}

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
    <?= sb_tailwind_link_tag(); ?>
</head>
<body class="bg-gray-100 min-h-screen p-6">
<div class="max-w-2xl mx-auto">
    <div class="bg-white shadow-xl rounded-lg p-6">
        <h2 class="text-3xl font-bold text-indigo-700 mb-6 text-center">Thêm Phân Công</h2>

        <?php if (!empty($message)) : ?>
            <div class="<?= $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?> border <?= $messageType === 'success' ? 'border-green-300' : 'border-red-300' ?> px-4 py-3 rounded mb-4" role="alert" aria-live="assertive">
                <strong><?= $messageType === 'success' ? 'Thành công' : 'Lỗi' ?>:</strong>
                <span><?= $message ?></span>
            </div>
        <?php endif; ?>

        <?php if ($schedules && mysqli_num_rows($schedules) === 0): ?>
            <div class="text-red-600 font-semibold text-center mb-4">
                Không có lịch hẹn nào chờ phân công.
            </div>
        <?php endif; ?>


        <form id="assignmentForm" method="POST" action="add_assignment.php" class="space-y-4" novalidate>
            <div>
                <label for="branchFilter" class="block font-medium text-gray-700 mb-1">Chọn chi nhánh</label>
                <select id="branchFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg mb-2 focus:outline-none focus:ring-2 focus:ring-indigo-400" onchange="filterScheduleByBranch()">
                    <option value="">Tất cả chi nhánh</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= $branch['ID_CN'] ?>"><?= htmlspecialchars($branch['TEN_CN']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="scheduleId" class="block font-medium text-gray-700 mb-1">Chọn Lịch Hẹn</label>
                <select id="scheduleId" name="scheduleId" required onchange="onScheduleChange()"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Chọn lịch hẹn --</option>
                    <?php 
                    $lichHenArr = [];
                    while ($schedule = mysqli_fetch_assoc($schedules)) : 
                        $lichHenArr[$schedule['ID_LICHHEN']] = $schedule;
                        $address = $schedule['DIA_CHI_HEN'];
                        $displayAddress = mb_strlen($address) > 40 ? mb_substr($address, 0, 40) . '...' : $address;
                    ?>
                        <option value="<?= $schedule['ID_LICHHEN'] ?>" 
                            data-idcn="<?= $schedule['ID_CHINHANH'] ?>" 
                            data-start-time="<?= $schedule['THOI_GIAN_BAT_DAU'] ?>"
                            data-diachi="<?= htmlspecialchars($schedule['DIA_CHI_HEN']) ?>"
                            data-khachhang="<?= htmlspecialchars($schedule['TEN_KH']) ?>"
                            data-dsdichvu="<?= htmlspecialchars($schedule['TEN_DV']) ?>"
                            data-tongthoiluong="<?= $schedule['TONG_THOILUONG'] ?>"
                            title="<?= htmlspecialchars($address) ?>"
                        >
                            <?= "#{$schedule['ID_LICHHEN']} | {$schedule['TEN_KH']} | {$schedule['TEN_DV']} | {$displayAddress} | Bắt đầu: {$schedule['THOI_GIAN_BAT_DAU']}" ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <div id="scheduleDetail" class="mt-2 p-3 bg-gray-50 border rounded text-sm hidden"></div>
            </div>

            <div>
                <label for="employeeId" class="block font-medium text-gray-700 mb-1">Chọn Nhân Viên</label>
                <select id="employeeId" name="employeeId" required disabled aria-disabled="true"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Chọn nhân viên --</option>
                    <?php while ($employee = mysqli_fetch_assoc($employees)) : ?>
                        <option value="<?= $employee['ID_TK'] ?>" data-idcn="<?= $employee['ID_CN'] ?>">
                            <?= $employee['HO_TEN'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <div id="noMatchWarning" class="text-red-500 text-sm mt-1 hidden">Không có nhân viên phù hợp với chi nhánh này.</div>
                <div id="employeeHint" class="text-gray-500 text-xs mt-1">Chọn lịch hẹn trước, danh sách nhân viên sẽ được lọc theo chi nhánh.</div>
            </div>

            <div>
                <label for="startTime" class="block font-medium text-gray-700 mb-1">Thời Gian Bắt Đầu</label>
                <input type="datetime-local" id="startTime" name="startTime" required aria-describedby="timeHelp" readonly
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div>
                <label for="endTime" class="block font-medium text-gray-700 mb-1">Thời Gian Kết Thúc (tự động)</label>
                <input type="datetime-local" id="endTime" name="endTime" required aria-describedby="timeHelp" readonly
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                <div id="timeHelp" class="text-gray-500 text-xs mt-1">Thời gian kết thúc được tính tự động dựa trên thời lượng dịch vụ.</div>
            </div>

            <div class="flex justify-between mt-6">
                <button type="submit" id="submitBtn"
                    class="bg-green-600 hover:bg-green-700 disabled:bg-green-300 disabled:cursor-not-allowed text-white font-semibold px-5 py-2 rounded-lg shadow transition">
                    Thêm Phân Công
                </button>
                <button type="button"
                        onclick="window.location.href='http://localhost:8080/stygianblue/app/admin/admin_dashboard.php?page=assignments'"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2 rounded-lg shadow">
                    Quay Lại
                </button>
            </div>
        </form>
    </div>
</div>
    <script>
        // Lọc option lịch hẹn theo chi nhánh
        function filterScheduleByBranch() {
            const branchId = document.getElementById('branchFilter').value;
            const select = document.getElementById('scheduleId');
            let hasVisible = false;
            for (let i = 1; i < select.options.length; i++) {
                const opt = select.options[i];
                const optBranch = opt.getAttribute('data-idcn');
                if (!branchId || optBranch === branchId) {
                    opt.hidden = false;
                    hasVisible = true;
                } else {
                    opt.hidden = true;
                }
            }
            if (!hasVisible) select.selectedIndex = 0;
        }
        // Hàm chuyển đổi datetime-local sang đối tượng Date
        function parseDatetimeLocal(str) {
            if (!str) return null;
            return new Date(str.replace(' ', 'T'));
        }

        // Hàm cộng phút vào chuỗi datetime-local (giữ đúng múi giờ địa phương)
        function addMinutesToDatetimeLocal(datetimeStr, minutes) {
            const date = parseDatetimeLocal(datetimeStr);
            if (!date) return '';
            date.setMinutes(date.getMinutes() + minutes);
            // Định dạng lại yyyy-MM-ddTHH:mm giữ nguyên múi giờ
            const pad = n => n < 10 ? '0' + n : n;
            const year = date.getFullYear();
            const month = pad(date.getMonth() + 1);
            const day = pad(date.getDate());
            const hours = pad(date.getHours());
            const minutes_str = pad(date.getMinutes());
            return `${year}-${month}-${day}T${hours}:${minutes_str}`;
        }

        // Hiển thị chi tiết lịch hẹn khi chọn
        function onScheduleChange() {
            filterEmployeesByBranch();
            const select = document.getElementById('scheduleId');
            const detailDiv = document.getElementById('scheduleDetail');
            const opt = select.options[select.selectedIndex];
            if (!opt || !opt.value) {
                detailDiv.classList.add('hidden');
                document.getElementById('startTime').value = '';
                document.getElementById('endTime').value = '';
                return;
            }
            // Lấy thông tin chi tiết
            const khachhang = opt.getAttribute('data-khachhang');
            const diachi = opt.getAttribute('data-diachi');
            const dsdichvu = opt.getAttribute('data-dsdichvu');
            const tongthoiluong = parseInt(opt.getAttribute('data-tongthoiluong')) || 0;
            const startTime = opt.getAttribute('data-start-time').replace(' ', 'T');
            // Hiển thị chi tiết
            detailDiv.innerHTML = `<b>Khách hàng:</b> ${khachhang}<br><b>Địa chỉ:</b> ${diachi}<br><b>Dịch vụ:</b> ${dsdichvu}<br><b>Tổng thời lượng:</b> ${tongthoiluong} phút`;
            detailDiv.classList.remove('hidden');
            // Set thời gian bắt đầu/kết thúc
            document.getElementById('startTime').value = startTime;
            // Thêm buffer 5 phút nếu tổng thời lượng >= 60 phút
            let buffer = 0;
            if (tongthoiluong >= 120) buffer = 15;
            else if (tongthoiluong >= 60) buffer = 10;
            else if (tongthoiluong > 0) buffer = 5;
            const endTime = addMinutesToDatetimeLocal(startTime, tongthoiluong + buffer);
            document.getElementById('endTime').value = endTime;
        }
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
        // ...existing code...
        function filterEmployeesByBranch() {
            const selectedSchedule = document.querySelector('#scheduleId option:checked');
            const scheduleBranchId = selectedSchedule.getAttribute('data-idcn');
            const employees = document.querySelectorAll('#employeeId option');
            const employeeSelect = document.getElementById('employeeId');
            let matchCount = 0;

            employees.forEach(option => {
                if (option.value === '') return; // skip placeholder
                if (!scheduleBranchId || option.getAttribute('data-idcn') === scheduleBranchId) {
                    option.hidden = false;
                    matchCount++;
                } else {
                    option.hidden = true;
                }
            });

            employeeSelect.selectedIndex = 0;
            employeeSelect.disabled = matchCount === 0;
            employeeSelect.classList.toggle('bg-gray-100', employeeSelect.disabled);
            employeeSelect.classList.toggle('text-gray-600', employeeSelect.disabled);
            document.getElementById('noMatchWarning').classList.toggle('hidden', matchCount > 0);

            const startTime = selectedSchedule.getAttribute('data-start-time');
            if (startTime) {
                const formatted = startTime.replace(' ', 'T');
                const startInput = document.getElementById('startTime');
                const endInput = document.getElementById('endTime');
                startInput.value = formatted;
                endInput.value = formatted;
                endInput.min = formatted;
                validateTimes();
            }
            toggleSubmitState();
        }

        function validateTimes() {
            const start = document.getElementById('startTime').value;
            const end = document.getElementById('endTime').value;
            const errorEl = document.getElementById('timeError');
            if (start && end && end <= start) {
                errorEl.classList.remove('hidden');
                document.getElementById('endTime').classList.add('border-red-400');
            } else {
                errorEl.classList.add('hidden');
                document.getElementById('endTime').classList.remove('border-red-400');
            }
            toggleSubmitState();
        }

        function toggleSubmitState() {
            const scheduleChosen = document.getElementById('scheduleId').value !== '';
            const employeeChosen = document.getElementById('employeeId').value !== '' && !document.getElementById('employeeId').disabled;
            const start = document.getElementById('startTime').value;
            const end = document.getElementById('endTime').value;
            const timeValid = start && end && end > start;
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = !(scheduleChosen && employeeChosen && timeValid);
        }

        document.getElementById('startTime').addEventListener('change', function(){
            const endInput = document.getElementById('endTime');
            if (this.value) {
                endInput.min = this.value;
            }
            validateTimes();
        });
        document.getElementById('endTime').addEventListener('change', validateTimes);
        document.getElementById('employeeId').addEventListener('change', toggleSubmitState);
        document.getElementById('scheduleId').addEventListener('change', toggleSubmitState);

        document.getElementById('assignmentForm').addEventListener('submit', function(e){
            const start = document.getElementById('startTime').value;
            const end = document.getElementById('endTime').value;
            if (start && end && end <= start) {
                e.preventDefault();
                validateTimes();
            }
        });
    </script>
</body>

</html>