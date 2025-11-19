<?php
include '../../database/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$staff_id = $_SESSION['ID_TK'] ?? null;

$query = "
    SELECT lh.ID_LICHHEN, kh.HO_TEN AS ten_khach_hang, dv.TEN_DV AS ten_dich_vu,
           lh.THOI_GIAN_BAT_DAU, pc.THOI_GIAN_KET_THUC, lh.DIA_CHI_HEN, lh.TRANGTHAI
    FROM phan_cong_nhan_vien pc
    JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
    JOIN khach_hang kh ON lh.ID_TK = kh.ID_TK
    JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
    WHERE pc.ID_TK = ?
    ORDER BY lh.THOI_GIAN_BAT_DAU DESC
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
?>

<div class="space-y-10 fade-in">
    <header class="mb-4">
        <h1 class="text-3xl font-bold text-indigo-700 md:text-4xl">Lịch làm việc cá nhân</h1>
        <p class="mt-2 text-base text-gray-600 md:text-lg">Theo dõi lịch hẹn được phân công và cập nhật trạng thái kịp thời.</p>
    </header>

    <?php if ($totalAssignments > 0): ?>
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
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

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
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

    <div class="rounded-2xl border border-gray-200 bg-white shadow-xl">
        <?php if ($totalAssignments === 0): ?>
            <div class="flex flex-col items-center gap-2 py-12 text-center">
                <p class="text-lg font-semibold text-gray-600">Bạn chưa có lịch phân công nào.</p>
                <p class="text-sm text-gray-500">Khi có lịch mới, thông tin chi tiết sẽ hiển thị tại đây.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto text-left text-sm">
                    <thead>
                        <tr class="bg-indigo-700 text-indigo-50">
                            <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Mã lịch</th>
                            <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Khách hàng</th>
                            <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Dịch vụ</th>
                            <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Bắt đầu</th>
                            <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Kết thúc</th>
                            <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Địa điểm</th>
                            <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Yêu cầu đổi</th>
                            <th class="whitespace-nowrap px-6 py-4 text-xs font-semibold tracking-wide uppercase">Trạng thái công việc</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($assignments as $assignment): ?>
                            <?php
                            $requestStatus = $assignment['request_status'] ?? '';
                            $requestStateAttr = $requestStatus !== '' ? $requestStatus : 'none';
                            ?>
                            <tr
                                data-assignment-row
                                data-schedule-state="<?= htmlspecialchars($assignment['schedule_state'], ENT_QUOTES, 'UTF-8') ?>"
                                data-request-state="<?= htmlspecialchars($requestStateAttr, ENT_QUOTES, 'UTF-8') ?>"
                                data-customer="<?= htmlspecialchars($assignment['ten_khach_hang'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-service="<?= htmlspecialchars($assignment['ten_dich_vu'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-location="<?= htmlspecialchars($assignment['DIA_CHI_HEN'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                class="transition-colors duration-200 hover:bg-indigo-50"
                            >
                                <td class="px-6 py-4 font-semibold text-indigo-700"><?= (int)($assignment['ID_LICHHEN'] ?? 0) ?></td>
                                <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($assignment['ten_khach_hang'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($assignment['ten_dich_vu'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($assignment['start_display'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($assignment['end_display'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($assignment['DIA_CHI_HEN'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($requestStatus === 'Chờ duyệt'): ?>
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-600">Chờ duyệt</span>
                                            <form method="POST" action="huy_yeu_cau.php" class="w-full sm:w-auto">
                                                <input type="hidden" name="id_lichhen" value="<?= (int)($assignment['ID_LICHHEN'] ?? 0) ?>">
                                                <button type="submit" class="text-sm font-semibold text-rose-600 transition hover:text-rose-700">Hủy yêu cầu</button>
                                            </form>
                                        </div>
                                    <?php elseif ($requestStatus === 'Đã duyệt'): ?>
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600">Đã duyệt</span>
                                    <?php elseif ($requestStatus === 'Từ chối'): ?>
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-600">Bị từ chối</span>
                                    <?php else: ?>
                                        <form method="POST" action="./components/gui_yeu_cau_thay_doi.php" class="space-y-3">
                                            <input type="hidden" name="id_lichhen" value="<?= (int)($assignment['ID_LICHHEN'] ?? 0) ?>">
                                            <input type="text" name="noidung" placeholder="Nhập lý do đề xuất thay đổi" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200" required>
                                            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Gửi yêu cầu</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (($assignment['TRANGTHAI'] ?? '') === 'Đã hoàn thành'): ?>
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600">Đã hoàn thành</span>
                                    <?php else: ?>
                                        <?php if (!empty($assignment['TRANGTHAI'])): ?>
                                            <span class="block text-xs font-medium uppercase tracking-wide text-gray-400"><?= htmlspecialchars($assignment['TRANGTHAI'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php else: ?>
                                            <span class="block text-xs font-medium uppercase tracking-wide text-gray-400">Đang xử lý</span>
                                        <?php endif; ?>
                                        <form method="POST" action="./components/gui_xac_nhan_hoan_thanh.php" class="mt-3">
                                            <input type="hidden" name="id_lichhen" value="<?= (int)($assignment['ID_LICHHEN'] ?? 0) ?>">
                                            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">Xác nhận hoàn thành</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="emptyFilterState" class="hidden border-t border-gray-100 px-6 py-5 text-center text-sm text-gray-500">
                Không tìm thấy lịch phù hợp với bộ lọc hiện tại.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('assignmentSearch');
    const scheduleFilter = document.getElementById('scheduleFilter');
    const changeFilter = document.getElementById('changeFilter');
    const rows = document.querySelectorAll('[data-assignment-row]');
    const emptyState = document.getElementById('emptyFilterState');

    if (!searchInput || !scheduleFilter || !changeFilter) {
        return;
    }

    const applyFilters = () => {
        const searchValue = searchInput.value.trim().toLowerCase();
        const scheduleValue = scheduleFilter.value;
        const changeValue = changeFilter.value;
        let visibleCount = 0;

        rows.forEach((row) => {
            const matchesSearch = (`${row.dataset.customer} ${row.dataset.service} ${row.dataset.location}`).toLowerCase().includes(searchValue);
            const matchesSchedule = !scheduleValue || row.dataset.scheduleState === scheduleValue;
            const matchesChange = !changeValue || row.dataset.requestState === changeValue;

            if (matchesSearch && matchesSchedule && matchesChange) {
                row.classList.remove('hidden');
                visibleCount++;
            } else {
                row.classList.add('hidden');
            }
        });

        if (emptyState) {
            if (visibleCount === 0) {
                emptyState.classList.remove('hidden');
            } else {
                emptyState.classList.add('hidden');
            }
        }
    };

    searchInput.addEventListener('input', applyFilters);
    scheduleFilter.addEventListener('change', applyFilters);
    changeFilter.addEventListener('change', applyFilters);

    applyFilters();
});
</script>

<style>
    @keyframes slideUpFade {
        0% { opacity: 0; transform: translateY(8px); }
        100% { opacity: 1; transform: translateY(0); }
    }

    .fade-in {
        animation: slideUpFade 0.4s ease-out both;
    }
</style>
