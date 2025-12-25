<?php
include '../../database/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$staff_id = $_SESSION['ID_TK'] ?? null;

$query = "
    SELECT lh.ID_LICHHEN, kh.HO_TEN AS ten_khach_hang, 
             COALESCE(ms.SERVICE_LIST, dv.TEN_DV) AS ten_dich_vu,
             COALESCE(ms.SERVICE_COUNT, 1) AS SERVICE_COUNT,
             lh.THOI_GIAN_BAT_DAU, pc.THOI_GIAN_KET_THUC, lh.DIA_CHI_HEN, lh.TRANGTHAI,
             dgdv.DON_GIA,
             lns.KHACH_XUA_HIEN AS KHACH_XUA_HIEN,
             lns.LI_DO_KHONG_DEN AS LI_DO_KHONG_DEN,
             lns.THOI_GIAN_KIEM_TRA_KHONG_DEN
    FROM phan_cong_nhan_vien pc
    JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
    JOIN khach_hang kh ON lh.ID_TK = kh.ID_TK
    JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
    /* Lấy tất cả dịch vụ từ BOOKING_ITEM */
    LEFT JOIN (
        SELECT bi.ID_LICHHEN,
               GROUP_CONCAT(dv2.TEN_DV ORDER BY bi.ID_ITEM SEPARATOR ', ') AS SERVICE_LIST,
               COUNT(DISTINCT bi.REF_ID) AS SERVICE_COUNT
        FROM BOOKING_ITEM bi
        JOIN DICH_VU dv2 ON dv2.ID_DV = bi.REF_ID
        WHERE bi.ITEM_TYPE = 'service'
        GROUP BY bi.ID_LICHHEN
    ) ms ON ms.ID_LICHHEN = lh.ID_LICHHEN
    /* Chỉ lấy một đơn giá đại diện cho mỗi dịch vụ để tránh nhân bản dòng */
    LEFT JOIN (
        SELECT ID_DV, MAX(DON_GIA) AS DON_GIA
        FROM don_gia_dich_vu
        GROUP BY ID_DV
    ) dgdv ON lh.ID_DV = dgdv.ID_DV
    LEFT JOIN lich_hen_no_show lns ON lns.ID_LICHHEN = lh.ID_LICHHEN
    WHERE pc.ID_TK = ?
    ORDER BY 
        CASE 
            WHEN lh.TRANGTHAI = 'Đã hoàn thành' THEN 3
            WHEN lh.TRANGTHAI != 'Đã hoàn thành' AND lh.THOI_GIAN_BAT_DAU < NOW() THEN 1
            ELSE 2
        END ASC,
        lh.THOI_GIAN_BAT_DAU ASC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$result = $stmt->get_result();

function getRequestStatus($id_lichhen, $id_tk)
{
    global $conn;
    $stmt = $conn->prepare("SELECT TRANGTHAI FROM yeu_cau_thay_doi_lich WHERE ID_LICHHEN = ? AND ID_TK = ? ORDER BY NGAY_GUI DESC LIMIT 1");
    if ($stmt === false) return null;
    $stmt->bind_param("is", $id_lichhen, $id_tk);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['TRANGTHAI'];
    }
    return null;
}

$assignments = [];
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $row['request_status'] = getRequestStatus($row['ID_LICHHEN'], $staff_id);
        $assignments[] = $row;
    }
}

$totalAssignments = count($assignments);
$completedAssignments = 0;
$upcomingAssignments = 0;
$pendingChangeRequests = 0;
$pastAssignments = 0;
$now = new DateTime();

foreach ($assignments as &$assignment) {
    $startTime = null;
    if (!empty($assignment['THOI_GIAN_BAT_DAU']) && $assignment['THOI_GIAN_BAT_DAU'] !== '0000-00-00 00:00:00') {
        try {
            $startTime = new DateTime($assignment['THOI_GIAN_BAT_DAU']);
        } catch (Exception $e) {
            $startTime = null;
        }
    }

    $assignment['schedule_state'] = 'upcoming';
    if (($assignment['TRANGTHAI'] ?? '') === 'Đã hoàn thành') {
        $assignment['schedule_state'] = 'completed';
    } elseif ($startTime && $startTime < $now) {
        $assignment['schedule_state'] = 'past';
    }
    // Nếu trạng thái yêu cầu đổi lịch là Đã duyệt, đánh dấu assignment này là đã bị thay thế
    if (($assignment['request_status'] ?? '') === 'Đã duyệt') {
        $assignment['is_replaced'] = true;
    } else {
        $assignment['is_replaced'] = false;
    }

    $assignment['start_display'] = $startTime ? $startTime->format('d/m/Y H:i') : ($assignment['THOI_GIAN_BAT_DAU'] ?: '—');

    $endTime = null;
    if (!empty($assignment['THOI_GIAN_KET_THUC']) && $assignment['THOI_GIAN_KET_THUC'] !== '0000-00-00 00:00:00') {
        try {
            $endTime = new DateTime($assignment['THOI_GIAN_KET_THUC']);
        } catch (Exception $e) {
            $endTime = null;
        }
    }
    $assignment['end_display'] = $endTime ? $endTime->format('d/m/Y H:i') : ($assignment['THOI_GIAN_KET_THUC'] ?: '—');

    // Calculate time remaining
    if ($startTime) {
        $now = new DateTime();
        $interval = $now->diff($startTime);
        if ($startTime > $now) {
            $hours = $interval->h + ($interval->days * 24);
            $mins = $interval->i;
            $assignment['time_remaining'] = $hours . 'h' . str_pad($mins, 2, '0', STR_PAD_LEFT) . 'p';
            $assignment['is_urgent'] = $hours < 4;
            $assignment['is_overdue'] = false;
        } else {
            $daysOverdue = $interval->days;
            if ($daysOverdue == 0) {
                $assignment['time_remaining'] = 'Quá hạn ' . $interval->h . 'h';
            } else {
                $assignment['time_remaining'] = 'Quá hạn ' . $daysOverdue . ' ngày';
            }
            $assignment['is_overdue'] = true;
            $assignment['is_urgent'] = false;
        }
    } else {
        $assignment['time_remaining'] = '—';
        $assignment['is_urgent'] = false;
        $assignment['is_overdue'] = false;
    }

    $assignment['no_show_recorded'] = (int)($assignment['KHACH_XUA_HIEN'] ?? 0) === 1 || (($assignment['TRANGTHAI'] ?? '') === 'Không đến');
    $assignment['no_show_reason'] = $assignment['LI_DO_KHONG_DEN'] ?? '';
    $assignment['no_show_checked_at'] = $assignment['THOI_GIAN_KIEM_TRA_KHONG_DEN'] ?? null;
    $assignment['no_show_checked_display'] = null;
    if (!empty($assignment['no_show_checked_at'])) {
        try {
            $checkedAt = new DateTime($assignment['no_show_checked_at']);
            $assignment['no_show_checked_display'] = $checkedAt->format('d/m/Y H:i');
        } catch (Exception $e) {
            $assignment['no_show_checked_display'] = null;
        }
    }

    $assignment['no_show_ready_display'] = null;
    $assignment['can_mark_no_show'] = false;
    if ($startTime instanceof DateTime) {
        $noShowReadyAt = clone $startTime;
        $noShowReadyAt->modify('+1 hour');
        $assignment['no_show_ready_display'] = $noShowReadyAt->format('d/m/Y H:i');

        $nowForNoShow = new DateTime();
        if ($nowForNoShow >= $noShowReadyAt &&
            !$assignment['no_show_recorded'] &&
            !in_array($assignment['TRANGTHAI'] ?? '', ['Đã hoàn thành', 'Đã hủy'], true)
        ) {
            $assignment['can_mark_no_show'] = true;
        }
    }

    if ($assignment['no_show_recorded']) {
        $assignment['TRANGTHAI'] = 'Không đến';
    }

    if (($assignment['TRANGTHAI'] ?? '') === 'Đã hoàn thành') {
        $completedAssignments++;
    }

    if ($assignment['schedule_state'] === 'upcoming') {
        $upcomingAssignments++;
    }

    if ($assignment['schedule_state'] === 'past') {
        $pastAssignments++;
    }

    if (($assignment['request_status'] ?? '') === 'Chờ duyệt') {
        $pendingChangeRequests++;
    }
}
unset($assignment);

$activeAssignments = [];
$overdueAssignments = [];
foreach ($assignments as $item) {
    if (($item['TRANGTHAI'] ?? '') === 'Đã hoàn thành' || ($item['is_replaced'] ?? false)) {
        continue;
    }

    if ($item['schedule_state'] === 'upcoming') {
        $activeAssignments[] = $item;
    }

    if ($item['schedule_state'] === 'past') {
        $overdueAssignments[] = $item;
    }
}
$activeCount = count($activeAssignments);
$overdueCount = count($overdueAssignments);
?>

<div class="space-y-10 fade-in">
    <header class="mb-4">
        <h1 class="text-3xl font-bold text-indigo-700 md:text-4xl">Lịch làm việc cá nhân</h1>
        <p class="mt-2 text-base text-gray-600 md:text-lg">Theo dõi lịch hẹn được phân công và cập nhật trạng thái kịp thời.</p>
    </header>

    <?php if ($totalAssignments > 0): ?>
        <div class="flex items-center gap-3">
            <button id="toggleStatsBtn" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700 transition">
                Hiển thị thống kê
            </button>
        </div>

        <section id="statsSection" class="is-hidden grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4 transition-opacity duration-300">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-gray-500">Tổng lịch</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $totalAssignments ?></p>
                <p class="mt-1 text-xs text-gray-400">Toàn bộ lịch bạn được giao phụ trách.</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-gray-500">Sắp diễn ra</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $upcomingAssignments ?></p>
                <p class="mt-1 text-xs text-gray-400">Lịch có thời gian bắt đầu trong tương lai.</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-gray-500">Đã hoàn thành</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $completedAssignments ?></p>
                <p class="mt-1 text-xs text-gray-400">Lịch đã xác nhận hoàn tất công việc.</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-gray-500">Đã quá hạn / chưa hoàn tất</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $pastAssignments ?></p>
                <p class="mt-1 text-xs text-gray-400">Ưu tiên theo dõi các lịch chưa hoàn thành.</p>
            </div>
        </section>

        <div id="filterSection" class="is-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-opacity duration-300">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Bộ lọc nhanh</h2>
                    <p class="text-sm text-gray-500">Lọc theo khách hàng, dịch vụ, địa điểm hoặc trạng thái.</p>
                </div>
                <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                    <input type="search" id="assignmentSearch" placeholder="Tìm kiếm lịch đã phân công..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 sm:max-w-xs">
                    <select id="scheduleFilter" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 sm:max-w-[160px]">
                        <option value="">Tất cả thời gian</option>
                        <option value="upcoming">Sắp diễn ra</option>
                        <option value="past">Đã quá hạn</option>
                        <option value="completed">Đã hoàn thành</option>
                    </select>
                    <select id="changeFilter" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 sm:max-w-[180px]">
                        <option value="">Tất cả yêu cầu</option>
                        <option value="Chờ duyệt">Chờ duyệt</option>
                        <option value="Đã duyệt">Đã duyệt</option>
                        <option value="Từ chối">Bị từ chối</option>
                        <option value="none">Không có yêu cầu</option>
                    </select>
                </div>
            </div>
        </div>
    <?php endif; ?>
        <?php if ($totalAssignments === 0): ?>
            <div class="flex flex-col items-center gap-2 py-12 text-center">
                <p class="text-lg font-semibold text-gray-600">Bạn chưa có lịch phân công nào.</p>
                <p class="text-sm text-gray-500">Khi có lịch mới, thông tin chi tiết sẽ hiển thị tại đây.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <!-- ACTIVE SCHEDULES -->
                <div class="rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden">
                    <div class="bg-indigo-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Lịch sắp diễn ra</h3>
                        <p class="text-sm text-gray-600 mt-1">Chỉ hiển thị lịch chuẩn bị thực hiện hoặc đang diễn ra.</p>
                    </div>
                    <?php if ($activeCount > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full table-auto text-left text-sm">
                                <thead>
                                    <tr class="bg-indigo-700 text-indigo-50">
                                        <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Mã lịch</th>
                                        <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Khách hàng</th>
                                        <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Dịch vụ</th>
                                        <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Bắt đầu</th>
                                        <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Còn lại</th>
                                        <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Đổi lịch</th>
                                        <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($activeAssignments as $assignment): ?>
                                        <?php
                                            $requestStatus = $assignment['request_status'] ?? '';
                                            $requestStateAttr = $requestStatus !== '' ? $requestStatus : 'none';
                                            $detailUrl = './staff_dashboard.php?page=appointment_detail&ID_LICHHEN=' . (int)($assignment['ID_LICHHEN'] ?? 0);
                                        ?>
                                        <tr
                                            data-assignment-row
                                            data-table-group="active"
                                            data-detail-url="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>"
                                            data-schedule-state="<?= htmlspecialchars($assignment['schedule_state'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-request-state="<?= htmlspecialchars($requestStateAttr, ENT_QUOTES, 'UTF-8') ?>"
                                            data-no-show-state="<?= ($assignment['no_show_recorded'] ?? false) ? 'recorded' : 'pending' ?>"
                                            data-customer="<?= htmlspecialchars($assignment['ten_khach_hang'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-service="<?= htmlspecialchars($assignment['ten_dich_vu'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-location="<?= htmlspecialchars($assignment['DIA_CHI_HEN'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            class="cursor-pointer transition-colors duration-200 hover:bg-indigo-50"
                                        >
                                            <td class="px-6 py-4 font-semibold text-indigo-700 whitespace-nowrap"><?= (int)($assignment['ID_LICHHEN'] ?? 0) ?></td>
                                            <td class="px-6 py-4 text-gray-700 whitespace-nowrap overflow-hidden text-ellipsis max-w-xs"><?= htmlspecialchars($assignment['ten_khach_hang'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-6 py-4 text-gray-700 whitespace-nowrap overflow-hidden text-ellipsis max-w-xs"><?= htmlspecialchars($assignment['ten_dich_vu'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-6 py-4 text-gray-700 whitespace-nowrap"><?= htmlspecialchars($assignment['start_display'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-6 py-4">
                                                <?php if ($assignment['is_urgent'] ?? false): ?>
                                                    <span class="inline-flex items-center rounded-full bg-yellow-100 px-3 py-1 text-xs font-semibold text-yellow-800"><?= htmlspecialchars($assignment['time_remaining'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php else: ?>
                                                    <span class="text-gray-600 text-sm"><?= htmlspecialchars($assignment['time_remaining'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4" data-request-cell>
                                                <?php if ($requestStatus === 'Chờ duyệt'): ?>
                                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-2">
                                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-600">Chờ duyệt</span>
                                                        <button type="button" class="cancel-request-btn text-xs font-semibold text-rose-600 hover:text-rose-700 transition whitespace-nowrap" data-id-lichhen="<?= (int)($assignment['ID_LICHHEN'] ?? 0) ?>">Hủy</button>
                                                    </div>
                                                <?php elseif ($requestStatus === 'Đã duyệt'): ?>
                                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600">Đã duyệt</span>
                                                <?php elseif ($requestStatus === 'Từ chối'): ?>
                                                    <span class="inline-flex items-center rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-600">Bị từ chối</span>
                                                <?php else: ?>
                                                    <button type="button" class="request-change-btn text-sm font-semibold text-blue-600 hover:text-blue-700 transition" data-id-lichhen="<?= (int)($assignment['ID_LICHHEN'] ?? 0) ?>">
                                                        Gửi yêu cầu
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4" data-confirm-cell>
                                                <?php if ($assignment['no_show_recorded'] ?? false): ?>
                                                    <span class="inline-flex items-center rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold text-gray-700">Không thể xác nhận</span>
                                                <?php else: ?>
                                                    <button type="button" class="confirm-completion-btn inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700 transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2" data-id-lichhen="<?= (int)($assignment['ID_LICHHEN'] ?? 0) ?>" data-bonus="<?= (int)(($assignment['DON_GIA'] ?? 0) * 0.15) ?>" data-customer="<?= htmlspecialchars($assignment['ten_khach_hang'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-location="<?= htmlspecialchars($assignment['DIA_CHI_HEN'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                        Xác nhận
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="px-6 py-5 text-sm text-gray-500">
                            Không có lịch sắp diễn ra.
                        </div>
                    <?php endif; ?>
                    <div id="emptyFilterState" class="is-hidden border-t border-gray-100 px-6 py-5 text-center text-sm text-gray-500">
                        Không tìm thấy lịch phù hợp với bộ lọc hiện tại.
                    </div>
                </div>

                <!-- OVERDUE SCHEDULES -->
                <div class="rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden">
                    <div class="bg-rose-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Lịch quá hạn</h3>
                        <p class="text-sm text-gray-600 mt-1">Theo dõi riêng các lịch đã quá giờ nhưng chưa hoàn tất.</p>
                    </div>
                    <?php if ($overdueCount > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full table-auto text-left text-sm">
                                <thead>
                                    <tr class="bg-rose-700 text-rose-50">
                                        <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Mã lịch</th>
                                        <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Khách hàng</th>
                                        <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Quá hạn</th>
                                        <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Khách không đến</th>
                                        <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($overdueAssignments as $assignment): ?>
                                        <?php
                                            $requestStatus = $assignment['request_status'] ?? '';
                                            $requestStateAttr = $requestStatus !== '' ? $requestStatus : 'none';
                                            $detailUrl = './staff_dashboard.php?page=appointment_detail&ID_LICHHEN=' . (int)($assignment['ID_LICHHEN'] ?? 0);
                                        ?>
                                        <tr
                                            data-assignment-row
                                            data-table-group="overdue"
                                            data-detail-url="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>"
                                            data-schedule-state="<?= htmlspecialchars($assignment['schedule_state'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-request-state="<?= htmlspecialchars($requestStateAttr, ENT_QUOTES, 'UTF-8') ?>"
                                            data-no-show-state="<?= ($assignment['no_show_recorded'] ?? false) ? 'recorded' : 'pending' ?>"
                                            data-customer="<?= htmlspecialchars($assignment['ten_khach_hang'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-service="<?= htmlspecialchars($assignment['ten_dich_vu'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-location="<?= htmlspecialchars($assignment['DIA_CHI_HEN'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            class="cursor-pointer transition-colors duration-200 bg-rose-50 hover:bg-rose-100"
                                        >
                                            <td class="px-6 py-4 font-semibold text-rose-800 whitespace-nowrap"><?= (int)($assignment['ID_LICHHEN'] ?? 0) ?></td>
                                            <td class="px-6 py-4 text-gray-700 whitespace-nowrap overflow-hidden text-ellipsis max-w-xs">
                                                <div class="space-y-1">
                                                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($assignment['ten_khach_hang'] ?? '—', ENT_QUOTES, 'UTF-8') ?></p>
                                                    <p class="text-xs text-gray-500">Dịch vụ: <?= htmlspecialchars($assignment['ten_dich_vu'] ?? 'Đang cập nhật', ENT_QUOTES, 'UTF-8') ?></p>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm font-semibold text-rose-700">
                                                <?= htmlspecialchars($assignment['time_remaining'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                                <?php if (!empty($assignment['start_display'])): ?>
                                                    <p class="text-[11px] text-gray-600 mt-1">Bắt đầu: <?= htmlspecialchars($assignment['start_display'], ENT_QUOTES, 'UTF-8') ?></p>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4" data-no-show-cell>
                                                <?php if ($assignment['no_show_recorded'] ?? false): ?>
                                                    <div class="space-y-1">
                                                        <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">Đã báo khách không đến</span>
                                                        <?php if (!empty($assignment['no_show_reason'])): ?>
                                                            <p class="text-xs text-gray-600">Lý do: <?= htmlspecialchars($assignment['no_show_reason'], ENT_QUOTES, 'UTF-8') ?></p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($assignment['no_show_checked_display'])): ?>
                                                            <p class="text-[11px] text-gray-500">Cập nhật: <?= htmlspecialchars($assignment['no_show_checked_display'], ENT_QUOTES, 'UTF-8') ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <?php if ($assignment['can_mark_no_show'] ?? false): ?>
                                                        <button type="button" class="mark-no-show-btn inline-flex items-center rounded-lg bg-white px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition" data-id-lichhen="<?= (int)($assignment['ID_LICHHEN'] ?? 0) ?>" data-customer="<?= htmlspecialchars($assignment['ten_khach_hang'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-service="<?= htmlspecialchars($assignment['ten_dich_vu'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                            Khách không đến
                                                        </button>
                                                        <p class="text-[11px] text-gray-500 mt-1">Chỉ chọn khi đã chờ thêm 1 giờ.</p>
                                                    <?php elseif (!empty($assignment['no_show_ready_display'])): ?>
                                                        <p class="text-xs text-gray-500">Có thể báo sau <?= htmlspecialchars($assignment['no_show_ready_display'], ENT_QUOTES, 'UTF-8') ?></p>
                                                    <?php else: ?>
                                                        <span class="text-xs text-gray-400">—</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="space-y-3">
                                                    <div data-request-cell>
                                                        <?php if ($requestStatus === 'Chờ duyệt'): ?>
                                                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-2">
                                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-600">Chờ duyệt</span>
                                                                <button type="button" class="cancel-request-btn text-xs font-semibold text-rose-600 hover:text-rose-700 transition whitespace-nowrap" data-id-lichhen="<?= (int)($assignment['ID_LICHHEN'] ?? 0) ?>">Hủy</button>
                                                            </div>
                                                        <?php elseif ($requestStatus === 'Đã duyệt'): ?>
                                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600">Đã duyệt</span>
                                                        <?php elseif ($requestStatus === 'Từ chối'): ?>
                                                            <span class="inline-flex items-center rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-600">Bị từ chối</span>
                                                        <?php else: ?>
                                                            <button type="button" class="request-change-btn text-sm font-semibold text-blue-600 hover:text-blue-700 transition" data-id-lichhen="<?= (int)($assignment['ID_LICHHEN'] ?? 0) ?>">
                                                                Đề xuất
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div data-confirm-cell>
                                                        <?php if ($assignment['no_show_recorded'] ?? false): ?>
                                                            <span class="inline-flex items-center rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold text-gray-700">Không thể xác nhận</span>
                                                        <?php else: ?>
                                                            <button type="button" class="confirm-completion-btn inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700 transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2" data-id-lichhen="<?= (int)($assignment['ID_LICHHEN'] ?? 0) ?>" data-bonus="<?= (int)(($assignment['DON_GIA'] ?? 0) * 0.15) ?>" data-customer="<?= htmlspecialchars($assignment['ten_khach_hang'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-location="<?= htmlspecialchars($assignment['DIA_CHI_HEN'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                                Xác nhận
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="px-6 py-5 text-sm text-gray-500">
                            Không có lịch quá hạn.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- COMPLETED SCHEDULES (Collapsed) -->
                <div class="rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden">
                    <button id="toggleCompletedBtn" class="w-full flex items-center justify-between px-6 py-4 bg-emerald-50 hover:bg-emerald-100 border-b border-gray-200 transition">
                        <div class="text-left">
                            <h3 class="text-lg font-semibold text-gray-900">Lịch đã hoàn thành</h3>
                            <p class="text-sm text-gray-600 mt-1"><span id="completedCount"><?= $completedAssignments ?></span> lịch</p>
                        </div>
                        <span id="toggleIcon" class="text-2xl transform transition">▼</span>
                    </button>
                    <div id="completedSection" class="is-hidden overflow-x-auto">
                        <table class="min-w-full table-auto text-left text-sm">
                            <thead>
                                <tr class="bg-emerald-700 text-emerald-50">
                                    <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Mã lịch</th>
                                    <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Khách hàng</th>
                                    <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Dịch vụ</th>
                                    <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Bắt đầu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($assignments as $assignment): ?>
                                    <?php if (($assignment['TRANGTHAI'] ?? '') !== 'Đã hoàn thành') continue; ?>
                                    <tr class="hover:bg-emerald-50 transition">
                                        <td class="px-6 py-4 font-semibold text-emerald-700 whitespace-nowrap"><?= (int)($assignment['ID_LICHHEN'] ?? 0) ?></td>
                                        <td class="px-6 py-4 text-gray-700 whitespace-nowrap overflow-hidden text-ellipsis max-w-xs"><?= htmlspecialchars($assignment['ten_khach_hang'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="px-6 py-4 text-gray-700 whitespace-nowrap overflow-hidden text-ellipsis max-w-xs"><?= htmlspecialchars($assignment['ten_dich_vu'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="px-6 py-4 text-gray-700 whitespace-nowrap"><?= htmlspecialchars($assignment['start_display'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Yêu Cầu Đổi Lịch -->
    <dialog id="changeRequestModal">
        <div class="bg-white shadow-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 bg-gradient-fallback px-6 py-4 text-white">
                <h2 class="text-xl font-bold text-white">Đề Xuất Thay Đổi Lịch</h2>
            </div>
            <form id="changeRequestForm" method="POST" action="./components/gui_yeu_cau_thay_doi.php" class="p-6 space-y-4">
                <input type="hidden" id="changeRequestLichhenId" name="id_lichhen" value="">
                
                <div id="changeRequestInfo" class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 text-sm space-y-2">
                    <!-- Schedule info populated dynamically -->
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Lý do thay đổi *</label>
                    <textarea name="noidung" placeholder="Nhập lý do đề xuất thay đổi..." class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm text-gray-700 placeholder-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 resize-none" rows="4" required></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="document.getElementById('changeRequestModal').close()" class="px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 rounded-lg transition">Hủy</button>
                    <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition">Gửi Yêu Cầu</button>
                </div>
            </form>
        </div>
    </dialog>

    <style>
        /* Modal dialog styling */
        #detailModal,
        #changeRequestModal {
            padding: 0;
            border: none;
            border-radius: 0;
            width: 90%;
            max-width: 768px;
            box-shadow: none;
        }

        #detailModal::backdrop,
        #changeRequestModal::backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }

        /* Hide browser tooltip on overflowed text */
        td[title] {
            position: relative;
        }

        td[title]:hover::before {
            display: none;
        }

        /* Remove default tooltip for ellipsis elements */
        .text-ellipsis[title] {
            pointer-events: none;
        }
    </style>
</div>

<script>
    const emptyState = document.getElementById('emptyFilterState');
    const escapeHtml = (unsafe = '') => String(unsafe)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    // ========== TOGGLE COMPLETED SECTION ==========
    const toggleCompletedBtn = document.getElementById('toggleCompletedBtn');
    const completedSection = document.getElementById('completedSection');
    const toggleIcon = document.getElementById('toggleIcon');

    if (toggleCompletedBtn && completedSection) {
        toggleCompletedBtn.addEventListener('click', function() {
            completedSection.classList.toggle('is-hidden');
            toggleIcon.style.transform = completedSection.classList.contains('is-hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
        });
    }

    // ========== TOGGLE STATS/FILTERS ==========
    const toggleStatsBtn = document.getElementById('toggleStatsBtn');
    const statsSection = document.getElementById('statsSection');
    const filterSection = document.getElementById('filterSection');

    if (toggleStatsBtn && statsSection && filterSection) {
        toggleStatsBtn.addEventListener('click', function() {
            statsSection.classList.toggle('is-hidden');
            filterSection.classList.toggle('is-hidden');
            this.textContent = statsSection.classList.contains('is-hidden') ? 'Hiển thị thống kê' : 'Ẩn thống kê';
        });
    }

    // ========== MODAL HANDLERS (Initialize before filter check) ==========
    // Mở modal yêu cầu đổi lịch
    const handleRequestChangeClick = function(e) {
        e.preventDefault();
        const modal = document.getElementById('changeRequestModal');
        const form = document.getElementById('changeRequestForm');
        const infoDiv = document.getElementById('changeRequestInfo');
        const idInput = document.getElementById('changeRequestLichhenId');
        const submitBtn = form ? form.querySelector('button[type="submit"]') : null;
        const textarea = form ? form.querySelector('textarea[name="noidung"]') : null;
        
        const row = this.closest('[data-assignment-row]');
        const idLichhen = this.dataset.idLichhen;
        const customer = row.dataset.customer;
        const service = row.dataset.service;
        const requestState = (row.dataset.requestState || '').trim();

        idInput.value = idLichhen;
        infoDiv.innerHTML = `
            <div><strong>Mã Lịch:</strong> #${idLichhen}</div>
            <div><strong>Khách Hàng:</strong> ${customer}</div>
            <div><strong>Dịch Vụ:</strong> ${service}</div>
        `;

        form.reset();
        modal.showModal();

        // Prevent resubmission if already sent or approved
        const canSend = !requestState || requestState === 'Từ chối' || requestState === 'none';
        if (submitBtn) {
            if (canSend) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Gửi Yêu Cầu';
                if (textarea) textarea.disabled = false;
            } else {
                submitBtn.disabled = true;
                submitBtn.textContent = requestState === 'Đã duyệt' ? 'Đã duyệt' : 'Đã gửi (chờ duyệt)';
                if (textarea) textarea.disabled = true;
            }
        }
    };

    document.querySelectorAll('.request-change-btn').forEach((btn) => {
        btn.addEventListener('click', handleRequestChangeClick);
    });

    // Submit change request without closing modal or alerting
    (function initChangeRequestSubmit() {
        const form = document.getElementById('changeRequestForm');
        if (!form) return;
        const submitBtn = form.querySelector('button[type="submit"]');
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!submitBtn) return;
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Đang gửi...';

            try {
                const formData = new FormData(form);
                const response = await fetch('./components/gui_yeu_cau_thay_doi.php', {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                });

                // Expect JSON; fallback to success if 200
                let ok = response.ok;
                try {
                    const data = await response.json();
                    ok = !!(data && data.success !== false);
                } catch (_) {
                    // Non-JSON response; treat 200 as success
                }

                if (ok) {
                    // Keep modal open, no alerts; mark button as sent
                    submitBtn.textContent = 'Đã gửi';
                    submitBtn.disabled = true;
                    // Update the corresponding row state to prevent re-open submissions
                    try {
                        const idLichhen = document.getElementById('changeRequestLichhenId').value;
                        const row = document.querySelector(`[data-assignment-row][data-id-lichhen="${idLichhen}"]`) ||
                                    Array.from(document.querySelectorAll('[data-assignment-row]')).find(r => (r.dataset.idLichhen || '') === String(idLichhen));
                        if (row) {
                            row.dataset.requestState = 'Chờ duyệt';
                            const btn = row.querySelector('.request-change-btn');
                            if (btn) btn.disabled = true;
                        }
                    } catch (__) {}
                } else {
                    // On failure, re-enable for retry (no alert per requirement)
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }
            } catch (err) {
                // Network error: allow retry
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                console.error('Change request submit error:', err);
            }
        });
    })();

    // ========== FILTER LOGIC (if filter elements exist) ==========
    const searchInput = document.getElementById('assignmentSearch');
    const scheduleFilter = document.getElementById('scheduleFilter');
    const changeFilter = document.getElementById('changeFilter');
    const rows = document.querySelectorAll('[data-assignment-row]');
    const hasActiveRows = Array.from(rows).some((row) => row.dataset.tableGroup === 'active');

    rows.forEach((row) => {
        row.addEventListener('click', (event) => {
            const interactive = event.target.closest('button, a, input, textarea, label');
            if (interactive) {
                return;
            }
            const detailUrl = row.dataset.detailUrl;
            if (detailUrl) {
                window.location.href = detailUrl;
            }
        });
    });

    const applyFilters = () => {
        if (!searchInput || !scheduleFilter || !changeFilter) {
            return;
        }
        
        const searchValue = searchInput.value.trim().toLowerCase();
        const scheduleValue = scheduleFilter.value;
        const changeValue = changeFilter.value;
        let activeVisible = 0;

        rows.forEach((row) => {
            const matchesSearch = (`${row.dataset.customer} ${row.dataset.service} ${row.dataset.location}`).toLowerCase().includes(searchValue);
            const matchesSchedule = !scheduleValue || row.dataset.scheduleState === scheduleValue;
            const matchesChange = !changeValue || row.dataset.requestState === changeValue;

            if (matchesSearch && matchesSchedule && matchesChange) {
                row.classList.remove('is-hidden');
                if (row.dataset.tableGroup === 'active') {
                    activeVisible++;
                }
            } else {
                row.classList.add('is-hidden');
            }
        });

        if (emptyState) {
            if (hasActiveRows && activeVisible === 0) {
                emptyState.classList.remove('is-hidden');
            } else {
                emptyState.classList.add('is-hidden');
            }
        }
    };

    // Attach filter event listeners if they exist
    if (searchInput && scheduleFilter && changeFilter) {
        searchInput.addEventListener('input', applyFilters);
        scheduleFilter.addEventListener('change', applyFilters);
        changeFilter.addEventListener('change', applyFilters);
        applyFilters();
    }

    // ========== AJAX Handlers ==========

    /**
     * Đánh dấu khách không đến
     */
    document.querySelectorAll('.mark-no-show-btn').forEach((btn) => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const idLichHen = this.dataset.idLichhen;
            const customer = this.dataset.customer || 'khách hàng';
            const service = this.dataset.service || '';

            if (!idLichHen) {
                alert('Không tìm thấy mã lịch hẹn.');
                return;
            }

            const confirmMsg = `Bạn xác nhận khách ${customer} (${service}) không đến?\n\nHành động này sẽ chuyển lịch sang trạng thái "Không đến".`;
            if (!confirm(confirmMsg)) {
                return;
            }

            let reason = prompt('Ghi chú thêm (tùy chọn):', '') ?? '';
            reason = reason.trim();

            const btnEl = this;
            const originalText = btnEl.textContent;
            btnEl.disabled = true;
            btnEl.textContent = 'Đang gửi...';

            try {
                const response = await fetch('./components/api_staff_confirmation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'mark_no_show',
                        id_lichhen: idLichHen,
                        reason,
                    }),
                });

                const data = await response.json();

                if (!data.success) {
                    alert(data.message || 'Không thể cập nhật.');
                    btnEl.disabled = false;
                    btnEl.textContent = originalText;
                    return;
                }

                const reasonText = data.data && data.data.reason ? escapeHtml(data.data.reason) : escapeHtml(reason);
                const recordedAt = data.data && data.data.recorded_at ? escapeHtml(data.data.recorded_at) : '';

                const cell = btnEl.closest('[data-no-show-cell]');
                if (cell) {
                    cell.innerHTML = `
                        <div class="space-y-1">
                            <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">Đã báo khách không đến</span>
                            ${reasonText ? `<p class="text-xs text-gray-600">Lý do: ${reasonText}</p>` : ''}
                            ${recordedAt ? `<p class="text-[11px] text-gray-500">Cập nhật: ${recordedAt}</p>` : ''}
                        </div>
                    `;
                }

                const row = btnEl.closest('[data-assignment-row]');
                if (row) {
                    row.dataset.noShowState = 'recorded';
                    row.classList.add('bg-red-50');
                    const confirmCell = row.querySelector('[data-confirm-cell]');
                    if (confirmCell) {
                        confirmCell.innerHTML = '<span class="inline-flex items-center rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold text-gray-700">Không thể xác nhận</span>';
                    }
                }

                if (typeof applyFilters === 'function') {
                    applyFilters();
                }
            } catch (error) {
                console.error('AJAX Error:', error);
                alert('Lỗi kết nối: ' + error.message);
                btnEl.disabled = false;
                btnEl.textContent = originalText;
                return;
            }
        });
    });

    /**
     * Xác nhận hoàn thành lịch hẹn
     */
    document.querySelectorAll('.confirm-completion-btn').forEach((btn) => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const idLichHen = this.dataset.idLichhen;
            const bonus = parseInt(this.dataset.bonus) || 0;
            const customer = this.dataset.customer || 'N/A';
            const location = this.dataset.location || 'N/A';
            const now = new Date().toLocaleString('vi-VN');

            const confirmMsg = `Bạn chắc chắn đã hoàn thành công việc?\n\n` +
                `Địa chỉ: ${location}\n` +
                `Khách hàng: ${customer}\n` +
                `Thời điểm xác nhận: ${now}\n` +
                `Thưởng ước tính: ${bonus.toLocaleString('vi-VN')} VND\n\n` +
                `Hành động này không thể hoàn tác.`;

            if (!confirm(confirmMsg)) {
                return;
            }

            const btn = this;
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Đang xử lý...';

            try {
                const response = await fetch('./components/api_staff_confirmation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'confirm_completion',
                        id_lichhen: idLichHen
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert('Lịch đã được xác nhận hoàn thành.');
                    const row = btn.closest('[data-assignment-row]');
                    if (row) {
                        row.style.opacity = '0.5';
                        row.style.pointerEvents = 'none';
                        const statusCell = row.querySelector('td:last-child');
                        statusCell.innerHTML = '<span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600">Đã hoàn thành</span>';
                        row.dataset.scheduleState = 'completed';
                        applyFilters();
                    }
                } else {
                    alert(data.message || 'Có lỗi xảy ra');
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            } catch (error) {
                console.error('AJAX Error:', error);
                alert('Lỗi kết nối: ' + error.message);
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
    });

    /**
     * Hủy yêu cầu đổi lịch
     */
    document.querySelectorAll('.cancel-request-btn').forEach((btn) => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const idLichHen = this.dataset.idLichhen;

            if (!confirm('Bạn chắc chắn muốn hủy yêu cầu này?')) {
                return;
            }

            const btn = this;
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Đang xử lý...';

            try {
                const response = await fetch('./components/api_staff_confirmation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'cancel_request',
                        id_lichhen: idLichHen
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert('Đã hủy yêu cầu');
                    const row = btn.closest('[data-assignment-row]');
                    if (row) {
                        const requestCell = row.querySelector('[data-request-cell]');
                        if (requestCell) {
                            requestCell.innerHTML = `
                                <button type="button" class="request-change-btn text-sm font-semibold text-blue-600 hover:text-blue-700 transition" data-id-lichhen="${idLichHen}">
                                    Đề xuất
                                </button>
                            `;
                            const newBtn = requestCell.querySelector('.request-change-btn');
                            if (newBtn) {
                                newBtn.addEventListener('click', handleRequestChangeClick);
                            }
                            row.dataset.requestState = 'none';
                            applyFilters();
                        }
                    }
                } else {
                    alert(data.message || 'Có lỗi xảy ra');
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            } catch (error) {
                console.error('AJAX Error:', error);
                alert('Lỗi kết nối: ' + error.message);
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
    });

    /**
     * Hiển thị thông báo (Legacy - không sử dụng)
     */
    function showNotification(message, type = 'info') {
        // Thay thế bằng alert
        if (type === 'success') {
            alert(message);
        } else if (type === 'error') {
            alert(message);
        }
    }

</script>

<style>
    @keyframes slideUpFade {
        0% { opacity: 0; transform: translateY(8px); }
        100% { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeOut {
        0% { opacity: 1; }
        100% { opacity: 0; }
    }

    .fade-in {
        animation: slideUpFade 0.4s ease-out both;
    }

    @keyframes slideIn {
        0% { transform: translateX(400px); opacity: 0; }
        100% { transform: translateX(0); opacity: 1; }
    }

    /* Dialog styling */
    dialog {
        border: none;
        padding: 0;
    }

    dialog::backdrop {
        background: rgba(0, 0, 0, 0.5);
    }

    /* Row hover tooltip (only when data-tooltip is present) */
    [data-assignment-row] { position: relative; }

    /* Only create tooltip for elements that explicitly have data-tooltip */
    [data-assignment-row][data-tooltip]::after {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0, 0, 0, 0.9);
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        white-space: nowrap;
        font-size: 12px;
        pointer-events: none;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s, visibility 0.2s;
        z-index: 50;
        margin-bottom: 8px;
    }

    [data-assignment-row][data-tooltip]:hover::after {
        opacity: 1;
        visibility: visible;
    }

    /* Toggle button icon rotation */
    #toggleIcon {
        will-change: transform;
    }

    /* Completed table collapse animation */
    #completedSection {
        max-height: 1000px;
        overflow: hidden;
        transition: max-height 0.3s ease, opacity 0.3s ease;
        opacity: 1;
    }

    #completedSection.is-hidden {
        max-height: 0;
        opacity: 0;
        overflow: hidden;
    }

    .is-hidden {
        display: none !important;
    }
    
    /* Fallback for Tailwind gradient header when --tw-gradient-stops isn't defined */
    .bg-gradient-fallback {
        --tw-gradient-from: #2563eb; /* blue-600 */
        --tw-gradient-to: #1d4ed8;   /* blue-700 */
        --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to);
        background-image: linear-gradient(to right, var(--tw-gradient-stops));
        background-color: #2563eb; /* solid fallback so text-white stays readable */
    }
</style>
