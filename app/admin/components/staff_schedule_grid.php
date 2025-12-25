<?php
/**
 * Lịch Thời Khóa Biểu Cá Nhân (Nâng Cấp)
 * Timeline-based schedule grid inspired by CodyHouse
 */
include '../../database/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$staff_id = $_SESSION['ID_TK'] ?? null;

// Lấy dữ liệu tuần hiện tại (08:00 - 18:00)
$query = "
    SELECT 
        lh.ID_LICHHEN, 
        kh.HO_TEN AS ten_khach_hang, 
        dv.TEN_DV AS ten_dich_vu,
        lh.THOI_GIAN_BAT_DAU, 
        pc.THOI_GIAN_KET_THUC, 
        lh.DIA_CHI_HEN, 
        lh.TRANGTHAI,
        DAYOFWEEK(lh.THOI_GIAN_BAT_DAU) as day_of_week,
        HOUR(lh.THOI_GIAN_BAT_DAU) as hour_start,
        MINUTE(lh.THOI_GIAN_BAT_DAU) as minute_start,
        HOUR(pc.THOI_GIAN_KET_THUC) as hour_end,
        MINUTE(pc.THOI_GIAN_KET_THUC) as minute_end
    FROM phan_cong_nhan_vien pc
    JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
    JOIN khach_hang kh ON lh.ID_TK = kh.ID_TK
    JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
    WHERE pc.ID_TK = ? 
    AND YEAR(lh.THOI_GIAN_BAT_DAU) = YEAR(NOW())
    AND WEEK(lh.THOI_GIAN_BAT_DAU) = WEEK(NOW())
    ORDER BY lh.THOI_GIAN_BAT_DAU ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$result = $stmt->get_result();

// Khởi tạo events array
$events = [];
$days_order = [2, 3, 4, 5, 6, 7, 1]; // Thứ 2 đến CN

while ($row = $result->fetch_assoc()) {
    $day_of_week = (int)$row['day_of_week'];
    $position = array_search($day_of_week, $days_order);
    if ($position !== false) {
        $row['day_index'] = $position;
        $row['event_id'] = 'event-' . $row['ID_LICHHEN'];
        $events[] = $row;
    }
}

// Timeline: 08:00 - 18:00 (30 min intervals)
$timeline = [];
for ($hour = 8; $hour <= 18; $hour++) {
    $timeline[] = sprintf('%02d:00', $hour);
    $timeline[] = sprintf('%02d:30', $hour);
}

function getStatusColor($status) {
    return match($status) {
        'Đã hoàn thành' => '#5eead4',  // cyan-400
        'Đã xác nhận' => '#38bdf8',    // sky-400
        'Đã hủy' => '#f87171',         // red-400
        default => '#d1d5db'           // gray-300
    };
}

function getStatusBg($status) {
    return match($status) {
        'Đã hoàn thành' => 'bg-teal-100',
        'Đã xác nhận' => 'bg-sky-100',
        'Đã hủy' => 'bg-red-100',
        default => 'bg-gray-100'
    };
}

function getStatusIcon($status) {
    return match($status) {
        'Đã hoàn thành' => '✓',
        'Đã xác nhận' => '→',
        'Đã hủy' => '✕',
        default => '•'
    };
}
?>

<div class="fade-in space-y-6">
    <!-- Header -->
    <header class="mb-6">
        <h1 class="text-3xl font-bold text-indigo-700 md:text-4xl">Lịch Làm Việc Tuần</h1>
        <p class="mt-2 text-base text-gray-600 md:text-lg">
            Xem lịch làm việc chi tiết theo múi giờ - Click để xem chi tiết
        </p>
    </header>

    <!-- Week Navigation -->
    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div>
            <p class="text-sm font-medium text-gray-600">Tuần Hiện Tại</p>
            <p class="text-lg font-semibold text-gray-900">
                <?= date('d/m/Y', strtotime('Monday this week')) ?> - <?= date('d/m/Y', strtotime('Sunday this week')) ?>
            </p>
        </div>
        <div class="flex gap-2">
            <button class="flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                <span>←</span> Tuần Trước
            </button>
            <button class="flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                Tuần Sau <span>→</span>
            </button>
        </div>
    </div>

    <!-- Schedule Container (Timeline-based) -->
    <div class="cd-schedule cd-schedule--loading margin-top-lg margin-bottom-lg js-cd-schedule rounded-2xl border border-gray-200 bg-white shadow-lg overflow-hidden">
        
        <!-- Timeline (Y-axis: Time) -->
        <div class="cd-schedule__timeline border-r border-gray-200">
            <ul class="space-y-0">
                <?php foreach ($timeline as $time): ?>
                    <li class="flex items-center justify-center border-b border-gray-100 py-3" style="height: 50px;">
                        <span class="text-xs font-semibold text-gray-700"><?= $time ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Events Grid (X-axis: Days) -->
        <div class="cd-schedule__events overflow-x-auto">
            <ul class="flex">
                <?php 
                $days = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ Nhật'];
                $dates = [];
                for ($i = 0; $i < 7; $i++) {
                    $dates[$i] = date('d/m', strtotime("Monday this week +$i days"));
                }
                
                foreach ($days as $idx => $day): 
                ?>
                    <li class="cd-schedule__group flex-1 border-r border-gray-200 last:border-r-0">
                        <!-- Day Header -->
                        <div class="cd-schedule__top-info flex items-center justify-center border-b border-gray-200 px-4 py-3" style="height: 50px; min-width: 140px;">
                            <div class="text-center">
                                <p class="text-sm font-bold text-indigo-700"><?= $day ?></p>
                                <p class="text-xs text-gray-500"><?= $dates[$idx] ?></p>
                            </div>
                        </div>
                        
                        <!-- Events for this day -->
                        <ul class="relative" style="height: <?= (count($timeline) * 50) ?>px; min-width: 140px;">
                            <?php 
                            // Filter events for this day
                            $day_events = array_filter($events, fn($e) => $e['day_index'] === $idx);
                            
                            foreach ($day_events as $event): 
                                $hour_start = (int)$event['hour_start'];
                                $minute_start = (int)$event['minute_start'];
                                $hour_end = (int)$event['hour_end'];
                                $minute_end = (int)$event['minute_end'];
                                
                                // Calculate position from 08:00
                                $start_minutes = ($hour_start - 8) * 60 + $minute_start;
                                $end_minutes = ($hour_end - 8) * 60 + $minute_end;
                                $duration_minutes = max($end_minutes - $start_minutes, 30);
                                
                                // Position in pixels (50px per 30min)
                                $top = ($start_minutes / 30) * 50;
                                $height = ($duration_minutes / 30) * 50;
                                
                                $status_color = getStatusColor($event['TRANGTHAI']);
                                $status_bg = getStatusBg($event['TRANGTHAI']);
                                $status_icon = getStatusIcon($event['TRANGTHAI']);
                            ?>
                                <li class="cd-schedule__event absolute left-0 right-0 px-1" style="top: <?= $top ?>px; height: <?= $height ?>px;">
                                    <button 
                                        type="button"
                                        class="cd-schedule__event-btn w-full h-full rounded-md p-1 text-left transition hover:shadow-md"
                                        style="background-color: <?= $status_color ?>; border-left: 3px solid <?= $status_color ?>;"
                                        data-event="<?= htmlspecialchars($event['event_id'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-schedule-data="<?= htmlspecialchars(json_encode($event), ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="showScheduleDetail(this)"
                                    >
                                        <div class="flex flex-col h-full justify-between">
                                            <div class="truncate">
                                                <p class="text-xs font-bold text-white truncate"><?= htmlspecialchars($event['ten_khach_hang'], ENT_QUOTES, 'UTF-8') ?></p>
                                                <?php if ($height > 40): ?>
                                                    <p class="text-xs text-white opacity-90 truncate"><?= htmlspecialchars(substr($event['ten_dich_vu'], 0, 20), ENT_QUOTES, 'UTF-8') ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($height > 35): ?>
                                                <p class="text-xs font-semibold text-white opacity-75">
                                                    <?= sprintf('%02d:%02d', $hour_start, $minute_start) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

    </div>

    <!-- Legend -->
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
        <p class="mb-3 text-sm font-semibold text-gray-900">Chú Giải Trạng Thái</p>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="flex items-center gap-2">
                <div class="h-4 w-1 rounded-full" style="background-color: #5eead4;"></div>
                <span class="text-xs text-gray-600">Hoàn thành</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-4 w-1 rounded-full" style="background-color: #38bdf8;"></div>
                <span class="text-xs text-gray-600">Xác nhận</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-4 w-1 rounded-full" style="background-color: #f87171;"></div>
                <span class="text-xs text-gray-600">Hủy</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-4 w-1 rounded-full" style="background-color: #d1d5db;"></div>
                <span class="text-xs text-gray-600">Khác</span>
            </div>
        </div>
    </div>
</div>

<!-- Modal Chi Tiết -->
<div id="scheduleModal" class="cd-schedule-modal fixed inset-0 z-50" onclick="if(event.target === this) closeScheduleModal()">
    <header class="cd-schedule-modal__header flex items-center justify-between border-b border-gray-200 bg-indigo-600 p-4">
        <div class="cd-schedule-modal__content">
            <span class="cd-schedule-modal__date text-xs text-indigo-100"></span>
            <h3 class="cd-schedule-modal__name text-lg font-bold text-white mt-1"></h3>
        </div>
        <button type="button" class="cd-schedule-modal__close text-white hover:text-indigo-100 transition" onclick="closeScheduleModal()">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </header>

    <div class="cd-schedule-modal__body overflow-y-auto bg-white p-6">
        <div class="space-y-4 max-w-md">
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">MÃ LỊCH</p>
                <p id="modalLichHenId" class="text-lg font-bold text-indigo-700 mt-1">—</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">KHÁCH HÀNG</p>
                <p id="modalCustomer" class="text-sm text-gray-900 mt-1">—</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">DỊCH VỤ</p>
                <p id="modalService" class="text-sm text-gray-900 mt-1">—</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">THỜI GIAN</p>
                <p id="modalTime" class="text-sm text-gray-900 mt-1">—</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">ĐỊA CHỈ</p>
                <p id="modalLocation" class="text-sm text-gray-900 mt-1">—</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">TRẠNG THÁI</p>
                <p id="modalStatus" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold mt-1">—</p>
            </div>
        </div>
    </div>

    <div class="flex gap-2 border-t border-gray-200 bg-gray-50 p-4">
        <button type="button" class="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition" onclick="closeScheduleModal()">
            Đóng
        </button>
        <button type="button" id="modalConfirmBtn" class="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition">
            Xác Nhận
        </button>
    </div>
</div>

<style>
    @keyframes slideUpFade {
        0% { opacity: 0; transform: translateY(8px); }
        100% { opacity: 1; transform: translateY(0); }
    }

    .fade-in {
        animation: slideUpFade 0.4s ease-out both;
    }

    @keyframes slideInDown {
        0% { opacity: 0; transform: translateY(-20px); }
        100% { opacity: 1; transform: translateY(0); }
    }

    /* Timeline Schedule Styles */
    .cd-schedule {
        display: flex;
        overflow: hidden;
        background: white;
    }

    .cd-schedule__timeline {
        flex-shrink: 0;
        width: 80px;
        background: white;
    }

    .cd-schedule__events {
        flex: 1;
        overflow-x: auto;
        overflow-y: hidden;
    }

    .cd-schedule__events ul {
        display: flex;
        height: auto;
    }

    .cd-schedule__group {
        flex: 1;
        min-width: 140px;
        position: relative;
    }

    .cd-schedule__top-info {
        position: sticky;
        top: 0;
        z-index: 10;
        background: white;
    }

    .cd-schedule__event {
        transition: all 0.2s ease;
    }

    .cd-schedule__event-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        opacity: 0.95;
        transition: all 0.2s ease;
    }

    .cd-schedule__event-btn:hover {
        opacity: 1;
        transform: scale(1.02);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    /* Modal Styles */
    #scheduleModal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 50;
        background-color: rgba(0, 0, 0, 0.5);
        animation: fadeIn 0.2s ease-out;
    }

    #scheduleModal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    @keyframes fadeIn {
        0% { opacity: 0; }
        100% { opacity: 1; }
    }

    .cd-schedule-modal {
        display: flex;
        flex-direction: column;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        animation: modalSlideUp 0.3s ease-out;
    }

    @keyframes modalSlideUp {
        0% { 
            opacity: 0; 
            transform: translateY(20px) scale(0.95);
        }
        100% { 
            opacity: 1; 
            transform: translateY(0) scale(1);
        }
    }

    .cd-schedule-modal__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
    }

    .cd-schedule-modal__body {
        flex: 1;
        overflow-y: auto;
        padding: 24px;
    }

    .cd-schedule-modal__close {
        cursor: pointer;
        padding: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: background-color 0.2s ease;
    }

    .cd-schedule-modal__close:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }

    /* Responsive behavior */
    @media (max-width: 768px) {
        .cd-schedule__group {
            min-width: 100px;
        }

        .cd-schedule__top-info {
            font-size: 0.75rem;
        }

        .cd-schedule__event-btn {
            font-size: 0.625rem;
        }
    }

    /* Scrollbar styling */
    .cd-schedule__events::-webkit-scrollbar {
        height: 8px;
    }

    .cd-schedule__events::-webkit-scrollbar-track {
        background: #f1f5f9;
    }

    .cd-schedule__events::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .cd-schedule__events::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>

<script>
    // Store current event data in modal
    let currentEventData = null;

    function showScheduleDetail(button) {
        try {
            const dataStr = button.getAttribute('data-schedule-data');
            currentEventData = JSON.parse(decodeURIComponent(dataStr));
            
            // Update modal header
            const startTime = new Date(currentEventData.THOI_GIAN_BAT_DAU);
            document.querySelector('.cd-schedule-modal__date').textContent = 
                startTime.toLocaleDateString('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
            document.querySelector('.cd-schedule-modal__name').textContent = currentEventData.ten_khach_hang || 'Chi tiết lịch hẹn';
            
            // Update modal body
            document.getElementById('modalLichHenId').textContent = currentEventData.ID_LICHHEN || '—';
            document.getElementById('modalCustomer').textContent = currentEventData.ten_khach_hang || '—';
            document.getElementById('modalService').textContent = currentEventData.ten_dich_vu || '—';
            
            const endTime = new Date(currentEventData.THOI_GIAN_BAT_DAU);
            endTime.setMinutes(endTime.getMinutes() + (currentEventData.THOI_LUONG || 30));
            
            document.getElementById('modalTime').textContent = 
                startTime.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) + 
                ' - ' +
                endTime.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
            
            document.getElementById('modalLocation').textContent = currentEventData.DIA_CHI_HEN || '—';
            
            const statusEl = document.getElementById('modalStatus');
            const statusText = getStatusText(currentEventData.TRANGTHAI);
            const statusColor = currentEventData.TRANGTHAI === 'Đã hoàn thành' ? 'bg-teal-100 text-teal-700' :
                                currentEventData.TRANGTHAI === 'Đã xác nhận' ? 'bg-sky-100 text-sky-700' :
                                currentEventData.TRANGTHAI === 'Đã hủy' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700';
            statusEl.textContent = statusText;
            statusEl.className = 'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold mt-1 ' + statusColor;
            
            // Show modal
            const modal = document.getElementById('scheduleModal');
            modal.style.display = 'flex';
            modal.style.alignItems = 'center';
            modal.style.justifyContent = 'center';
        } catch (error) {
            console.error('Error parsing schedule data:', error);
        }
    }

    function closeScheduleModal() {
        const modal = document.getElementById('scheduleModal');
        modal.style.display = 'none';
        currentEventData = null;
    }

    function getStatusText(status) {
        const statusMap = {
            'Đã hoàn thành': '✓ Hoàn thành',
            'Đã xác nhận': '→ Xác nhận',
            'Đã hủy': '✕ Hủy',
        };
        return statusMap[status] || status || 'Chưa xác định';
    }

    // Handle confirm button click
    document.getElementById('modalConfirmBtn')?.addEventListener('click', async function() {
        if (!currentEventData) return;
        
        try {
            const response = await fetch('./api_staff_confirmation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'confirm',
                    ID_LICHHEN: currentEventData.ID_LICHHEN,
                    ID_PHAN_CONG: currentEventData.ID_PHAN_CONG
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Xác nhận thành công!');
                location.reload(); // Reload to show updated status
            } else {
                alert('Lỗi: ' + (result.message || 'Không xác nhận được'));
            }
        } catch (error) {
            console.error('Error confirming schedule:', error);
            alert('Lỗi khi gửi yêu cầu');
        }
    });

    // Close modal when clicking outside (on the modal overlay)
    document.getElementById('scheduleModal')?.addEventListener('click', function (event) {
        if (event.target === this) {
            closeScheduleModal();
        }
    });

    // Initialize modal display state
    document.getElementById('scheduleModal').style.display = 'none';
</script>
