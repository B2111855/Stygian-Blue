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
$scheduleIdParam = isset($_GET['scheduleId']) ? intval($_GET['scheduleId']) : null;
$branchIdParam = isset($_GET['branchId']) ? intval($_GET['branchId']) : null;

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

// Lấy danh sách nhân viên có chi nhánh (JOIN với bảng nhan_vien), chỉ lấy nhân viên thường (LOAI_NV = 'chuyen_trach')
$query_employees = "
    SELECT tk.ID_TK, tk.HO_TEN, nv.ID_CN
    FROM tai_khoan tk
    JOIN nhan_vien nv ON tk.ID_TK = nv.ID_TK
    WHERE tk.ID_QUYEN = 2
      AND nv.LOAI_NV = 'chuyen_trach'
      AND COALESCE(nv.IS_DELETED, 0) = 0
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
<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen p-6">
<div class="max-w-4xl mx-auto">
    <div class="mb-8 rounded-2xl bg-gradient-to-r from-indigo-600 via-purple-600 to-rose-500 text-white p-8 shadow-lg">
        <h1 class="text-4xl font-bold mb-2">Thêm Phân Công Nhân Viên</h1>
        <p class="text-white/80">Phân công lịch hẹn cho nhân viên phù hợp với chi nhánh và kỹ năng</p>
    </div>

    <div class="bg-white shadow-xl rounded-2xl p-8">
        <?php if (!empty($message)) : ?>
            <div class="mb-6 rounded-xl px-4 py-4 border-l-4 flex items-start gap-3 <?= $messageType === 'success' ? 'bg-green-50 border-green-500 text-green-800' : 'bg-red-50 border-red-500 text-red-800' ?>" role="alert" aria-live="assertive">
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <?php if ($messageType === 'success'): ?>
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    <?php else: ?>
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    <?php endif; ?>
                </svg>
                <div>
                    <strong><?= $messageType === 'success' ? '✓ Thành công' : '✗ Lỗi' ?></strong>
                    <p class="text-sm mt-1"><?= $message ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($schedules && mysqli_num_rows($schedules) === 0): ?>
            <div class="rounded-lg bg-yellow-50 border border-yellow-200 px-4 py-4 text-center text-yellow-800">
                <p class="font-semibold mb-1">⚠️ Không có lịch hẹn chờ phân công</p>
                <p class="text-sm">Tất cả lịch hẹn đã xác nhận đều đã được phân công hoặc quá thời gian hiện tại</p>
            </div>
        <?php endif; ?>

        <form id="assignmentForm" method="POST" action="add_assignment.php" class="space-y-6" novalidate>
            <!-- Section: Chọn Lịch Hẹn -->
            <div class="rounded-xl bg-gradient-to-br from-indigo-50 to-purple-50 p-6 border border-indigo-100">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Chọn Lịch Hẹn
                </h3>
                <div class="space-y-4">
                    <div>
                        <label for="branchFilter" class="block text-sm font-medium text-gray-700 mb-2">Lọc theo chi nhánh</label>
                        <select id="branchFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400" onchange="filterScheduleByBranch()">
                            <option value="">✓ Tất cả chi nhánh</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['ID_CN'] ?>"><?= htmlspecialchars($branch['TEN_CN']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="scheduleId" class="block text-sm font-medium text-gray-700 mb-2">Lịch Hẹn <span class="text-red-500">*</span></label>
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
                    <div id="scheduleDetail" class="mt-3 p-4 bg-white rounded-lg border border-indigo-200 text-sm hidden"></div>
                    </div>
                </div>
            </div>

            <!-- Section: Chọn Nhân Viên -->
            <div class="rounded-xl bg-gradient-to-br from-emerald-50 to-teal-50 p-6 border border-emerald-100">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 4H9m6 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Chọn Nhân Viên
                </h3>
                <div>
                    <label for="employeeId" class="block text-sm font-medium text-gray-700 mb-2">Nhân Viên <span class="text-red-500">*</span></label>
                    <select id="employeeId" name="employeeId" required disabled aria-disabled="true"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Chọn nhân viên --</option>
                        <?php while ($employee = mysqli_fetch_assoc($employees)) : ?>
                            <option value="<?= $employee['ID_TK'] ?>" data-idcn="<?= $employee['ID_CN'] ?>">
                                <?= $employee['HO_TEN'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div id="noMatchWarning" class="text-red-500 text-sm mt-2 hidden">⚠️ Không có nhân viên phù hợp với chi nhánh này</div>
                    <div id="employeeHint" class="text-gray-500 text-xs mt-2 flex items-start gap-2">
                        <span>💡</span>
                        <span>Chọn lịch hẹn trước, danh sách nhân viên sẽ lọc theo chi nhánh</span>
                    </div>
                </div>
            </div>

            <!-- Section: Chọn Thời Gian -->
            <div class="rounded-xl bg-gradient-to-br from-orange-50 to-rose-50 p-6 border border-orange-100">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Chọn Thời Gian
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="startTime" class="block text-sm font-medium text-gray-700 mb-2">Thời Gian Bắt Đầu <span class="text-red-500">*</span></label>
                        <input type="datetime-local" id="startTime" name="startTime" required aria-describedby="timeHelp" readonly
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label for="endTime" class="block text-sm font-medium text-gray-700 mb-2">Thời Gian Kết Thúc <span class="text-red-500">*</span></label>
                        <input type="datetime-local" id="endTime" name="endTime" required aria-describedby="timeHelp" readonly
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                </div>
                <div id="timeHelp" class="text-gray-600 text-xs mt-3 flex items-start gap-2">
                    <span>💡</span>
                    <span>Thời gian được tính tự động dựa trên thời lượng dịch vụ. Nhập readonly để tránh sai sót.</span>
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex gap-3 pt-4 border-t border-gray-200">
                <button type="submit" id="submitBtn"
                    class="flex-1 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white font-semibold px-6 py-3 rounded-lg shadow hover:shadow-lg transition transform hover:scale-105
                           disabled:bg-gray-500 disabled:text-white disabled:border disabled:border-gray-600 disabled:opacity-100 disabled:cursor-not-allowed disabled:shadow-none disabled:hover:scale-100
                           focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    ✓ Thêm Phân Công
                </button>
                <button type="button"
                        onclick="window.location.href='http://localhost:8080/stygianblue/app/admin/admin_dashboard.php?page=assignments'"
                        class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-semibold px-6 py-3 rounded-lg shadow hover:shadow-lg transition">
                    ← Quay Lại
                </button>
            </div>
        </form>
    </div>
</div>
    <script>
        // Dữ liệu từ PHP
        const scheduleIdParam = <?= $scheduleIdParam ? json_encode($scheduleIdParam) : 'null' ?>;
        const branchIdParam = <?= $branchIdParam ? json_encode($branchIdParam) : 'null' ?>;

        // Khởi tạo form khi trang load
        document.addEventListener('DOMContentLoaded', function() {
            if (branchIdParam) {
                document.getElementById('branchFilter').value = branchIdParam;
                filterScheduleByBranch();
            }
            if (scheduleIdParam) {
                document.getElementById('scheduleId').value = scheduleIdParam;
                onScheduleChange();
            }
        });

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
                if (errorEl) {
                    errorEl.classList.remove('hidden');
                }
                document.getElementById('endTime').classList.add('border-red-400');
            } else {
                if (errorEl) {
                    errorEl.classList.add('hidden');
                }
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
            const isDisabled = !(scheduleChosen && employeeChosen && timeValid);
            submitBtn.disabled = isDisabled;
            
            // Apply inline styles to ensure visibility
            if (isDisabled) {
                submitBtn.style.background = '#9ca3af';
                submitBtn.style.color = '#ffffff';
                submitBtn.style.cursor = 'not-allowed';
                submitBtn.style.opacity = '1';
            } else {
                submitBtn.style.background = 'linear-gradient(to right, rgb(22, 163, 74), rgb(16, 185, 129))';
                submitBtn.style.color = '#ffffff';
                submitBtn.style.cursor = 'pointer';
                submitBtn.style.opacity = '1';
            }
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