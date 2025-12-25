<?php
if (!defined('VIEW_SCHEDULE_DATA_READY')) {
    trigger_error('view_schedule_data.php must be included before view_schedule.php', E_USER_WARNING);
    return;
}

$activeTab = $_SESSION['active_view_schedule_tab'] ?? 'appointments';
unset($_SESSION['active_view_schedule_tab']);
if (!in_array($activeTab, ['appointments', 'rentals'], true)) {
    $activeTab = 'appointments';
}

$schedules      = $schedules ?? [];
$rentalOrders   = $rentalOrders ?? [];
$rentalItemsMap = $rentalItemsMap ?? [];
$multiServiceMap = $multiServiceMap ?? [];

$scheduleCount = is_array($schedules) ? count($schedules) : 0;
$rentalCount   = is_array($rentalOrders) ? count($rentalOrders) : 0;

if (!function_exists('sb_format_datetime')) {
    function sb_format_datetime($value, $format = 'd/m/Y H:i') {
        if (empty($value)) {
            return '—';
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }
        return date($format, $timestamp);
    }
}
?>

<section class="bg-gradient-to-br from-blue-50 via-blue-100 to-blue-200 min-h-screen text-gray-800">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="mb-4 rounded-md bg-green-100 text-green-800 px-4 py-3 text-sm font-medium shadow">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="mb-4 rounded-md bg-red-100 text-red-800 px-4 py-3 text-sm font-medium shadow">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <header class="mb-8 text-center">
            <h1 class="text-3xl md:text-4xl font-extrabold text-blue-900 drop-shadow">
                Lịch hẹn &amp; Đơn Thuê
            </h1>
            <p class="text-gray-600 mt-2 text-sm md:text-base">
                Theo dõi lịch chụp, đơn thuê trang phục và trạng thái thanh toán của bạn tại một nơi.
            </p>
        </header>

        <div id="schedule-tab-root"
             class="mb-6 flex flex-wrap justify-center gap-3"
             data-active-tab="<?= htmlspecialchars($activeTab) ?>"
             role="tablist">
            <?php
                $appointmentActive = $activeTab === 'appointments';
                $rentalActive = $activeTab === 'rentals';
                $appointmentClass = $appointmentActive
                    ? 'bg-white text-blue-900 shadow'
                    : 'bg-white/30 text-blue-700 hover:bg-white/60';
                $rentalClass = $rentalActive
                    ? 'bg-white text-blue-900 shadow'
                    : 'bg-white/30 text-blue-700 hover:bg-white/60';
            ?>
            <button type="button"
                    class="tab-trigger inline-flex items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 <?= $appointmentClass ?>"
                    data-tab-target="appointments"
                    aria-controls="tab-appointments"
                    aria-selected="<?= $appointmentActive ? 'true' : 'false' ?>">
                <span>Lịch hẹn</span>
                <span class="text-xs font-normal text-blue-500">(<?= number_format($scheduleCount) ?>)</span>
            </button>
            <button type="button"
                    class="tab-trigger inline-flex items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 <?= $rentalClass ?>"
                    data-tab-target="rentals"
                    aria-controls="tab-rentals"
                    aria-selected="<?= $rentalActive ? 'true' : 'false' ?>">
                <span>Đơn thuê</span>
                <span class="text-xs font-normal text-blue-500">(<?= number_format($rentalCount) ?>)</span>
            </button>
        </div>

        <div data-tab-panel="appointments" id="tab-appointments" class="space-y-6 <?= $appointmentActive ? '' : 'hidden' ?>">
            <?php if (empty($schedules)): ?>
                <div class="bg-white/60 backdrop-blur-sm border border-gray-200 rounded-xl p-8 text-center shadow">
                    <p class="text-gray-500 text-lg">Bạn chưa có lịch hẹn nào.</p>
                    <a href="../Views/datLich.php"
                       class="inline-block mt-4 bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded-lg shadow hover:bg-blue-700">
                       Đặt lịch ngay
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto bg-white rounded-xl shadow-lg border border-gray-200">
                    <table class="min-w-full text-sm text-center">
                        <thead>
                            <tr class="bg-gradient-to-r from-blue-100 to-blue-200 text-gray-800 text-xs uppercase font-semibold">
                                <th class="py-3 px-4 border-b">#</th>
                                <th class="py-3 px-4 border-b whitespace-nowrap">Mã lịch hẹn</th>
                                <th class="py-3 px-4 border-b">Thời gian</th>
                                <th class="py-3 px-4 border-b">Dịch vụ</th>
                                <th class="py-3 px-4 border-b whitespace-nowrap">Chi nhánh</th>
                                <th class="py-3 px-4 border-b">Trạng thái</th>
                                <th class="py-3 px-4 border-b">Thanh toán</th>
                                <th class="py-3 px-4 border-b">Hành động</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            <?php 
                            $idx = 0;
                            foreach ($schedules as $lichId => $sc): 
                                $idx++;

                                $isDone         = ($sc['TRANGTHAI'] === 'Đã hoàn thành');
                                $isPending      = ($sc['TRANGTHAI'] === 'Đang chờ');
                                $isConfirmed    = ($sc['TRANGTHAI'] === 'Đã xác nhận');
                                $hasFeedback    = ((int)$sc['DA_GUI_PHAN_HOI'] === 1);
                                $canPayNow      = ($isConfirmed && $sc['TRANGTHAI_THANHTOAN'] !== 'Đã thanh toán');

                                // Multi-service adaptation
                                $svcList = $multiServiceMap[$lichId]['SERVICE_LIST'] ?? $sc['TEN_DV'];
                                $svcCount = (int)($multiServiceMap[$lichId]['SERVICE_COUNT'] ?? 0);
                                $isMulti = $svcCount > 1;
                                // Normalize separators (support ' + ' from GROUP_CONCAT) and deduplicate
                                $normalizedSvc = str_replace(' + ', ',', (string)$svcList);
                                $svcArray = array_values(array_unique(array_filter(array_map('trim', explode(',', $normalizedSvc)))));
                                $primarySvc = $svcArray ? $svcArray[0] : ($sc['TEN_DV'] ?? 'Dịch vụ');

                                $detailPayload = [
                                    'id_lichhen'   => $lichId,
                                    'thoi_gian'    => sb_format_datetime($sc['THOI_GIAN_BAT_DAU'] ?? null),
                                    'dia_diem'     => $sc['DIA_CHI_HEN'],
                                    'dich_vu'      => $isMulti ? implode(' + ', $svcArray) : $sc['TEN_DV'],
                                    'chi_nhanh'    => $sc['TEN_CHI_NHANH'] ?? '—',
                                    'trang_thai'   => $sc['TRANGTHAI'],
                                    'nhan_vien'    => $staffMap[$lichId] ?? [],
                                    'thiet_bi'     => $deviceMap[$lichId] ?? [],
                                    'tam_tinh'     => $quoteMap[$lichId]['TONG_TAM_TINH'] ?? null,
                                    'chi_tiet_bg'  => $quoteMap[$lichId]['CHI_TIET_JSON'] ?? null,
                                    'hoa_don'      => [
                                        'id_hd'              => $sc['ID_HD'],
                                        'tong_tien'          => $sc['TONG_TIEN'],
                                        'trang_thai_tt'      => $sc['TRANGTHAI_THANHTOAN'],
                                        'gateway_status'     => $sc['VNPAY_TRANG_THAI'] ?? null,
                                        'gateway_reference'  => $sc['VNPAY_MA_THAM_CHIEU'] ?? null,
                                        'gateway_updated_at' => $sc['VNPAY_UPDATED_AT'] ?? null,
                                    ],
                                ];

                                $payPayload = [
                                    'id_lichhen'   => $lichId,
                                    'id_hd'        => $sc['ID_HD'],
                                    'tong_tien'    => $sc['TONG_TIEN'],
                                    'dang_xu_ly_gateway' => ($sc['VNPAY_TRANG_THAI'] ?? null) === 'pending',
                                ];
                            ?>
                            <tr class="hover:bg-blue-50 transition duration-150 align-top">
                                <td class="py-3 px-4 border-b"><?= $idx ?></td>
                                <td class="py-3 px-4 border-b text-gray-700 whitespace-nowrap font-mono">#<?= htmlspecialchars($lichId) ?></td>
                                <td class="py-3 px-4 border-b text-gray-700">
                                    <div class="font-medium"><?= sb_format_datetime($sc['THOI_GIAN_BAT_DAU'] ?? null) ?></div>
                                </td>
                                <td class="py-3 px-4 border-b text-left">
                                    <?php if ($isMulti): ?>
                                        <?php foreach ($svcArray as $svcName): ?>
                                            <div class="text-gray-800"><?= htmlspecialchars($svcName) ?></div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-gray-800"><?= htmlspecialchars($sc['TEN_DV']) ?></div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="py-3 px-4 border-b text-gray-700">
                                    <?= htmlspecialchars($sc['TEN_CHI_NHANH'] ?? '—') ?>
                                </td>
                                <td class="py-3 px-4 border-b">
                                    <?= renderStatusBadge($sc['TRANGTHAI']) ?>
                                </td>
                                <td class="py-3 px-4 border-b">
                                    <?= renderPaymentBadge($sc['TRANGTHAI_THANHTOAN'] ?? null, $sc['VNPAY_TRANG_THAI'] ?? null) ?>
                                </td>
                                <td class="py-3 px-4 border-b text-xs text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-2">
                                    <?php if ($isPending): ?>
                                    <form method="POST"
                                          onsubmit="return confirm('Bạn chắc chắn muốn hủy lịch hẹn này?')">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="id_lichhen" value="<?= $lichId ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <button type="submit"
                                            class="inline-block bg-red-600 hover:bg-red-700 text-white font-medium px-3 py-1 rounded-md shadow whitespace-nowrap">
                                            Hủy
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <?php if ($isDone): ?>
                                    <button type="button"
                                            onclick="toggleFeedbackForm(<?= $lichId ?>)"
                                            class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium px-3 py-1 rounded-md shadow whitespace-nowrap">
                                        <?= $hasFeedback ? 'Chỉnh sửa phản hồi' : 'Gửi phản hồi' ?>
                                    </button>
                                    <?php endif; ?>

                                    <button type="button"
                                            class="inline-block border border-gray-300 hover:border-gray-400 text-gray-700 font-medium px-3 py-1 rounded-md shadow-sm whitespace-nowrap"
                                            onclick='openDetailModal(<?= jsSafe($detailPayload) ?>)'>
                                        Chi tiết
                                    </button>

                                    <?php if ($canPayNow && $sc['ID_HD']): ?>
                                    <a href="../Views/hoa_don.php#invoice-<?= $sc['ID_HD'] ?>"
                                       class="inline-flex items-center justify-center bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-3 py-1 rounded-md shadow whitespace-nowrap">
                                        Thanh toán ngay
                                    </a>
                                    <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                            <?php if ($isDone): ?>
                            <tr id="feedback-form-<?= $lichId ?>" class="hidden">
                                <td colspan="8" class="bg-gray-50 border-b p-4 text-left">
                                    <form method="POST"
                                          action="../components/luu_phan_hoi.php"
                                          class="space-y-3 max-w-xl">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="id_dv" value="<?= htmlspecialchars($sc['ID_DV']) ?>">

                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                                Phản hồi về dịch vụ:
                                            </label>
                                            <textarea name="noidung"
                                                rows="3"
                                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                placeholder="Bạn thấy trải nghiệm thế nào?"><?= htmlspecialchars($sc['NOI_DUNG_PH'] ?? '') ?></textarea>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                                Đánh giá:
                                            </label>
                                            <select name="xephang"
                                                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                <?php
                                                    $ratingNow = $sc['XEP_HANG_DV'] ?? 5;
                                                    for ($r = 5; $r >= 1; $r--) {
                                                        $label = str_repeat('⭐', $r);
                                                        $sel = ($r == $ratingNow) ? 'selected' : '';
                                                        echo "<option value=\"$r\" $sel>$label</option>";
                                                    }
                                                ?>
                                            </select>
                                        </div>

                                        <button type="submit"
                                            class="inline-flex items-center bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-4 py-2 rounded-lg shadow text-sm">
                                            <?= $hasFeedback ? 'Cập nhật phản hồi' : 'Gửi phản hồi' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div data-tab-panel="rentals" id="tab-rentals" class="space-y-6 <?= $rentalActive ? '' : 'hidden' ?>">
            <?php if (empty($rentalOrders)): ?>
                <div class="bg-white/60 backdrop-blur-sm border border-gray-200 rounded-xl p-8 text-center shadow">
                    <p class="text-gray-500 text-lg">Bạn chưa có đơn thuê trang phục nào.</p>
                    <a href="../Views/trangphuc_datthue.php"
                       class="inline-block mt-4 bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded-lg shadow hover:bg-blue-700">
                       Thuê ngay
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto bg-white rounded-xl shadow-lg border border-gray-200">
                    <table class="min-w-full text-sm text-center">
                        <thead>
                            <tr class="bg-gradient-to-r from-blue-100 to-blue-200 text-gray-800 text-xs uppercase font-semibold">
                                <th class="py-3 px-4 border-b">#</th>
                                <th class="py-3 px-4 border-b">Mã đơn</th>
                                <th class="py-3 px-4 border-b">Thời gian</th>
                                <th class="py-3 px-4 border-b">Chi nhánh</th>
                                <th class="py-3 px-4 border-b">Trang phục</th>
                                <th class="py-3 px-4 border-b">Chi phí</th>
                                <th class="py-3 px-4 border-b">Trạng thái</th>
                                <th class="py-3 px-4 border-b">Thanh toán</th>
                                <th class="py-3 px-4 border-b">Hành động</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            <?php
                            $idx = 0;
                            foreach ($rentalOrders as $rentalId => $order):
                                $idx++;
                                $meta = sb_get_rental_status_meta($order['TRANG_THAI'] ?? '');
                                $items = $rentalItemsMap[$rentalId] ?? [];
                                $itemCount = 0;
                                $itemPreview = [];
                                foreach ($items as $itemRow) {
                                    $qty = (int)($itemRow['SO_LUONG'] ?? 1);
                                    $itemCount += $qty;
                                    if (count($itemPreview) < 2) {
                                        $itemPreview[] = trim(($itemRow['TEN'] ?? 'Trang phục') . ' × ' . $qty);
                                    }
                                }
                                $itemPreviewText = $itemPreview ? implode(', ', $itemPreview) : 'Chờ cập nhật';
                                $canCancelRental = ($order['TRANG_THAI'] ?? '') === 'cho_duyet';
                                $canPayRental = !empty($order['ID_HD']) && (($order['TRANGTHAI_THANHTOAN'] ?? '') !== 'Đã thanh toán');

                                $detailPayload = [
                                    'id_ttp'                => $rentalId,
                                    'chi_nhanh'             => $order['TEN_CN'] ?? '—',
                                    'trang_thai'            => $order['TRANG_THAI'] ?? '',
                                    'trang_thai_label'      => $meta['label'],
                                    'dat_luc_text'          => sb_format_datetime($order['NGAY_DAT'] ?? null),
                                    'ngay_nhan_text'        => sb_format_datetime($order['NGAY_NHAN'] ?? null),
                                    'ngay_tra_du_kien_text' => sb_format_datetime($order['NGAY_TRA_DK'] ?? null),
                                    'ngay_tra_thuc_te_text' => sb_format_datetime($order['NGAY_TRA_TT'] ?? null),
                                    'tien_coc'              => $order['TIEN_COC'] ?? null,
                                    'tong_du_kien'          => $order['TONG_TIEN_DU_KIEN'] ?? null,
                                    'tong_thuc_te'          => $order['TONG_TIEN_THUC_TE'] ?? null,
                                    'ghi_chu'               => $order['GHI_CHU'] ?? '',
                                    'items'                 => array_values($items),
                                    'hoa_don'               => [
                                        'id_hd'             => $order['ID_HD'] ?? null,
                                        'tong_tien'         => $order['TONG_TIEN'] ?? null,
                                        'trang_thai_tt'     => $order['TRANGTHAI_THANHTOAN'] ?? null,
                                        'gateway_status'    => $order['VNPAY_TRANG_THAI'] ?? null,
                                        'gateway_reference' => $order['VNPAY_MA_THAM_CHIEU'] ?? null,
                                        'gateway_updated_at'=> $order['VNPAY_UPDATED_AT'] ?? null,
                                    ],
                                ];
                            ?>
                            <tr class="hover:bg-blue-50 transition duration-150 align-top">
                                <td class="py-3 px-4 border-b"><?= $idx ?></td>
                                <td class="py-3 px-4 border-b text-gray-700 whitespace-nowrap font-mono">#<?= htmlspecialchars($rentalId) ?></td>
                                <td class="py-3 px-4 border-b text-gray-700 space-y-1">
                                    <div><span class="text-xs text-gray-500">Nhận:</span> <span class="font-medium"><?= sb_format_datetime($order['NGAY_NHAN'] ?? null) ?></span></div>
                                    <div><span class="text-xs text-gray-500">Trả dự kiến:</span> <span class="font-medium"><?= sb_format_datetime($order['NGAY_TRA_DK'] ?? null) ?></span></div>
                                </td>
                                <td class="py-3 px-4 border-b text-gray-700">
                                    <?= htmlspecialchars($order['TEN_CN'] ?? '—') ?>
                                </td>
                                <td class="py-3 px-4 border-b text-gray-700">
                                    <div class="font-semibold text-gray-800"><?= htmlspecialchars($itemPreviewText) ?></div>
                                    <div class="text-xs text-gray-500"><?= $itemCount ?> món</div>
                                </td>
                                <td class="py-3 px-4 border-b text-gray-700">
                                    <div class="font-semibold text-blue-900"><?= htmlspecialchars(sb_format_vnd($order['TONG_TIEN_DU_KIEN'] ?? null)) ?></div>
                                    <div class="text-xs text-gray-500">Cọc: <?= htmlspecialchars(sb_format_vnd($order['TIEN_COC'] ?? null)) ?></div>
                                    <?php if (!empty($order['TONG_TIEN_THUC_TE'])): ?>
                                        <div class="text-xs text-emerald-600">TT: <?= htmlspecialchars(sb_format_vnd($order['TONG_TIEN_THUC_TE'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 border-b">
                                    <?= renderRentalStatusBadge($order['TRANG_THAI'] ?? '') ?>
                                </td>
                                <td class="py-3 px-4 border-b">
                                    <?= renderPaymentBadge($order['TRANGTHAI_THANHTOAN'] ?? null, $order['VNPAY_TRANG_THAI'] ?? null) ?>
                                </td>
                                <td class="py-3 px-4 border-b text-xs text-center space-y-2">
                                    <?php if ($canCancelRental): ?>
                                    <form method="POST"
                                          onsubmit="return confirm('Bạn chắc chắn muốn hủy đơn thuê này?')">
                                        <input type="hidden" name="action" value="cancel_rental">
                                        <input type="hidden" name="id_ttp" value="<?= $rentalId ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <button type="submit"
                                            class="w-full inline-block bg-red-600 hover:bg-red-700 text-white font-medium px-3 py-1 rounded-md shadow">
                                            Hủy
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <button type="button"
                                            class="w-full inline-block border border-gray-300 hover:border-gray-400 text-gray-700 font-medium px-3 py-1 rounded-md shadow-sm"
                                            onclick='openRentalDetailModal(<?= jsSafe($detailPayload) ?>)'>
                                        Chi tiết
                                    </button>

                                    <?php if ($canPayRental && !empty($order['ID_HD'])): ?>
                                    <a href="../Views/hoa_don.php#invoice-<?= $order['ID_HD'] ?>"
                                       class="w-full inline-flex items-center justify-center bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-3 py-1 rounded-md shadow">
                                        Thanh toán ngay
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<div id="detailModal" class="fixed inset-0 z-50 hidden items-center justify-center">
    <div class="absolute inset-0 bg-black/40" onclick="closeDetailModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-xl max-w-lg w-full mx-4 p-6 z-10">
        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center justify-between">
            Chi tiết lịch hẹn
            <button class="text-gray-400 hover:text-gray-600" onclick="closeDetailModal()">&times;</button>
        </h2>
        <div class="space-y-4 text-sm text-gray-700 max-h-[60vh] overflow-y-auto">
            <div>
                <div class="text-gray-500 text-xs uppercase font-semibold">Mã lịch hẹn</div>
                <div id="dm-idlich" class="font-medium text-gray-900"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold">Thời gian</div>
                    <div id="dm-time" class="font-medium text-gray-900"></div>
                </div>
                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold">Trạng thái</div>
                    <div id="dm-status" class="font-medium"></div>
                </div>
            </div>
            <div>
                <div class="text-gray-500 text-xs uppercase font-semibold">Dịch vụ</div>
                <div id="dm-service" class="font-medium text-gray-900"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold">Chi nhánh</div>
                    <div id="dm-branch" class="font-medium text-gray-900"></div>
                </div>
                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold">Địa điểm hẹn</div>
                    <div id="dm-address" class="font-medium text-gray-900"></div>
                </div>
            </div>
            <div>
                <div class="text-gray-500 text-xs uppercase font-semibold mb-1">Nhân viên được phân công</div>
                <ul id="dm-staff" class="list-disc list-inside text-gray-800 text-sm space-y-1"></ul>
            </div>
            <div>
                <div class="text-gray-500 text-xs uppercase font-semibold mb-1">Thiết bị đi kèm</div>
                <ul id="dm-devices" class="list-disc list-inside text-gray-800 text-sm space-y-1"></ul>
            </div>
            <div>
                <div class="text-gray-500 text-xs uppercase font-semibold mb-1">Báo giá tạm tính</div>
                <div id="dm-quote-total" class="font-semibold text-blue-700"></div>
            </div>
            <div>
                <div class="text-gray-500 text-xs uppercase font-semibold mb-1">Hóa đơn</div>
                <div class="text-sm text-gray-800">
                    <div><span class="text-gray-500">Mã hóa đơn:</span> <span id="dm-hd-id" class="font-medium"></span></div>
                    <div><span class="text-gray-500">Tổng tiền:</span> <span id="dm-hd-total" class="font-semibold text-emerald-700"></span></div>
                    <div><span class="text-gray-500">Thanh toán:</span> <span id="dm-hd-status" class="font-medium"></span></div>
                    <div><span class="text-gray-500">Cổng VNPay:</span> <span id="dm-hd-verify" class="font-medium"></span></div>
                </div>
            </div>
        </div>
        <div class="mt-6 text-right">
            <button onclick="closeDetailModal()"
                    class="inline-flex items-center justify-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-lg px-4 py-2 text-sm">
                Đóng
            </button>
        </div>
    </div>
</div>

<div id="rentalDetailModal" class="fixed inset-0 z-50 hidden items-center justify-center">
    <div class="absolute inset-0 bg-black/40" onclick="closeRentalDetailModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 p-6 z-10">
        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center justify-between">
            Chi tiết đơn thuê
            <button class="text-gray-400 hover:text-gray-600" onclick="closeRentalDetailModal()">&times;</button>
        </h2>
        <div class="space-y-4 text-sm text-gray-700 max-h-[70vh] overflow-y-auto">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold">Mã đơn</div>
                    <div id="rd-id" class="font-medium text-gray-900"></div>
                </div>
                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold">Trạng thái</div>
                    <div id="rd-status" class="font-medium"></div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold">Đặt lúc</div>
                    <div id="rd-booked" class="font-medium text-gray-900"></div>
                </div>
                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold">Chi nhánh</div>
                    <div id="rd-branch" class="font-medium text-gray-900"></div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold">Nhận trang phục</div>
                    <div id="rd-pickup" class="font-medium text-gray-900"></div>
                </div>
                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold">Trả dự kiến</div>
                    <div id="rd-return-plan" class="font-medium text-gray-900"></div>
                </div>
                <div id="rd-return-actual-wrap">
                    <div class="text-gray-500 text-xs uppercase font-semibold">Trả thực tế</div>
                    <div id="rd-return-actual" class="font-medium text-gray-900"></div>
                </div>
            </div>
            <div>
                <div class="text-gray-500 text-xs uppercase font-semibold mb-1">Danh sách trang phục</div>
                <ul id="rd-items" class="list-disc list-inside text-gray-800 text-sm space-y-1"></ul>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold">Tiền cọc</div>
                    <div id="rd-deposit" class="font-semibold text-blue-900"></div>
                </div>
                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold">Tổng dự kiến</div>
                    <div id="rd-estimate" class="font-semibold text-gray-900"></div>
                </div>
                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold">Tổng thực tế</div>
                    <div id="rd-actual" class="font-semibold text-emerald-700"></div>
                </div>
            </div>
            <div>
                <div class="text-gray-500 text-xs uppercase font-semibold mb-1">Ghi chú</div>
                <div id="rd-note" class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 whitespace-pre-line min-h-[64px]"></div>
            </div>
            <div>
                <div class="text-gray-500 text-xs uppercase font-semibold mb-1">Hóa đơn</div>
                <div class="text-sm text-gray-800 space-y-1">
                    <div><span class="text-gray-500">Mã hóa đơn:</span> <span id="rd-hd-id" class="font-medium"></span></div>
                    <div><span class="text-gray-500">Tổng tiền:</span> <span id="rd-hd-total" class="font-semibold text-emerald-700"></span></div>
                    <div><span class="text-gray-500">Thanh toán:</span> <span id="rd-hd-status" class="font-medium"></span></div>
                    <div><span class="text-gray-500">Cổng VNPay:</span> <span id="rd-hd-verify" class="font-medium"></span></div>
                </div>
            </div>
        </div>
        <div class="mt-6 text-right">
            <button onclick="closeRentalDetailModal()"
                    class="inline-flex items-center justify-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-lg px-4 py-2 text-sm">
                Đóng
            </button>
        </div>
    </div>
</div>

<div id="payModal" class="fixed inset-0 z-50 hidden items-center justify-center">
    <div class="absolute inset-0 bg-black/40" onclick="closePayModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 p-6 z-10">
        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center justify-between">
            Thanh toán chuyển khoản
            <button class="text-gray-400 hover:text-gray-600" onclick="closePayModal()">&times;</button>
        </h2>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-sm text-gray-700 space-y-2">
            <p class="font-medium text-gray-900">Bước 1. Quét mã QR / Chuyển khoản đúng số tiền:</p>
            <ul class="list-disc list-inside text-gray-600 text-sm">
                <li>Ngân hàng: <span class="font-medium text-gray-900">VCB - Stygian Blue</span></li>
                <li>Nội dung CK: <span class="font-medium text-gray-900">Thanh toan lich hen #<span id="pm-lich-id-inline"></span></span></li>
                <li>Số tiền: <span class="font-semibold text-emerald-700" id="pm-total"></span></li>
            </ul>
            <div class="bg-white rounded-md border border-gray-300 p-3 text-center">
                <div class="text-[10px] uppercase text-gray-500 tracking-wide mb-2">QR CODE</div>
                <div class="w-32 h-32 bg-gray-200 mx-auto rounded-md flex items-center justify-center text-[10px] text-gray-500">
                    QR img here
                </div>
            </div>
            <p class="text-[13px] text-gray-500 leading-snug">
                Bước 2. Sau khi chuyển khoản xong, chụp màn hình biên lai (screenshot app ngân hàng).<br>
                Bước 3. Tải ảnh minh chứng lên đây nếu bạn chuyển khoản ngoài VNPay.
            </p>
        </div>
        <form class="mt-4 space-y-4 text-sm"
              method="POST"
              action="../components/upload_minh_chung.php"
              enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="id_hd" id="pm-id-hd">
            <input type="hidden" name="id_lichhen" id="pm-id-lichhen">
            <div>
                <label class="block font-medium text-gray-700 mb-1">Ảnh minh chứng (jpg/png):</label>
                <input type="file" name="minhchung" accept="image/*"
                       class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:bg-blue-600 file:text-white hover:file:bg-blue-700">
            </div>
            <div>
                <label class="block font-medium text-gray-700 mb-1">Ghi chú thêm (tuỳ chọn):</label>
                <input type="text" name="ghichu"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Ví dụ: Em đã chuyển lúc 14:23 qua Vietcombank">
            </div>
            <button type="submit"
                    class="w-full inline-flex items-center justify-center bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg px-4 py-2 text-sm shadow">
                Gửi minh chứng thanh toán
            </button>
        </form>
        <div class="mt-4 text-[11px] text-gray-500 leading-relaxed">
            Sau khi gửi, trạng thái hóa đơn sẽ chuyển sang
            <span class="font-medium text-gray-800">"Đang đối chiếu"</span>. Bộ phận kế toán sẽ kiểm tra giao dịch và cập nhật sang
            <span class="text-emerald-600 font-semibold">"Đã thanh toán"</span>
            nếu hợp lệ. Đối với VNPay, hệ thống sẽ tự động cập nhật nên không cần bước này.
        </div>
        <div class="mt-6 text-right">
            <button onclick="closePayModal()"
                    class="inline-flex items-center justify-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-lg px-4 py-2 text-sm">
                Đóng
            </button>
        </div>
    </div>
</div>

<script>
function toggleFeedbackForm(id) {
    const el = document.getElementById('feedback-form-' + id);
    if (el) {
        el.classList.toggle('hidden');
    }
}

const detailModal    = document.getElementById('detailModal');
const dmIdLich       = document.getElementById('dm-idlich');
const dmTime         = document.getElementById('dm-time');
const dmStatus       = document.getElementById('dm-status');
const dmService      = document.getElementById('dm-service');
const dmBranch       = document.getElementById('dm-branch');
const dmAddress      = document.getElementById('dm-address');
const dmStaff        = document.getElementById('dm-staff');
const dmDevices      = document.getElementById('dm-devices');
const dmQuoteTotal   = document.getElementById('dm-quote-total');
const dmHdId         = document.getElementById('dm-hd-id');
const dmHdTotal      = document.getElementById('dm-hd-total');
const dmHdStatus     = document.getElementById('dm-hd-status');
const dmHdVerify     = document.getElementById('dm-hd-verify');

const rentalDetailModal = document.getElementById('rentalDetailModal');
const rdId              = document.getElementById('rd-id');
const rdStatus          = document.getElementById('rd-status');
const rdBooked          = document.getElementById('rd-booked');
const rdBranch          = document.getElementById('rd-branch');
const rdPickup          = document.getElementById('rd-pickup');
const rdReturnPlan      = document.getElementById('rd-return-plan');
const rdReturnActualWrap= document.getElementById('rd-return-actual-wrap');
const rdReturnActual    = document.getElementById('rd-return-actual');
const rdItems           = document.getElementById('rd-items');
const rdDeposit         = document.getElementById('rd-deposit');
const rdEstimate        = document.getElementById('rd-estimate');
const rdActual          = document.getElementById('rd-actual');
const rdNote            = document.getElementById('rd-note');
const rdHdId            = document.getElementById('rd-hd-id');
const rdHdTotal         = document.getElementById('rd-hd-total');
const rdHdStatus        = document.getElementById('rd-hd-status');
const rdHdVerify        = document.getElementById('rd-hd-verify');

const tabRoot    = document.getElementById('schedule-tab-root');
const tabButtons = tabRoot ? tabRoot.querySelectorAll('[data-tab-target]') : [];
const tabPanels  = document.querySelectorAll('[data-tab-panel]');

// Helpers: format VND and compute total from JSON detail if needed
function formatVND(value){
    if (value === null || value === undefined) return null;
    const s = String(value).trim();
    if (s === '' || s === '0') return null;
    const num = Number(s.replace(/[^0-9.-]/g, ''));
    if (!isFinite(num) || num <= 0) return null;
    return num.toLocaleString('vi-VN') + ' VND';
}

function displayMoney(value){
    const formatted = formatVND(value);
    if (formatted) return formatted;
    if (value === 0 || value === '0') {
        return '0 VND';
    }
    const numeric = Number(value);
    if (Number.isFinite(numeric) && numeric === 0) {
        return '0 VND';
    }
    return '—';
}

function computeQuoteFromDetail(detail){
    try{
        const data = typeof detail === 'string' ? JSON.parse(detail) : detail;
        let sum = 0;
        const acc = (it)=>{
            const donGia = Number(it.gia ?? it.don_gia ?? it.price ?? 0);
            const soLuong = Number(it.so_luong ?? it.qty ?? it.quantity ?? 1);
            const thanhTien = Number(it.thanh_tien ?? it.total ?? (donGia * soLuong));
            if (isFinite(thanhTien)) sum += thanhTien;
        };
        if (Array.isArray(data)) data.forEach(acc);
        else if (data && typeof data === 'object' && Array.isArray(data.items)) data.items.forEach(acc);
        return sum > 0 ? sum : null;
    }catch(e){
        return null;
    }
}

function openDetailModal(payload) {
    dmIdLich.textContent    = '#' + (payload.id_lichhen ?? '');
    dmTime.textContent      = payload.thoi_gian ?? '—';
    dmStatus.textContent    = payload.trang_thai ?? '—';
    dmService.textContent   = payload.dich_vu ?? '—';
    dmBranch.textContent    = payload.chi_nhanh ?? '—';
    dmAddress.textContent   = payload.dia_diem ?? '—';

    dmStaff.innerHTML = '';
    if (payload.nhan_vien && payload.nhan_vien.length) {
        payload.nhan_vien.forEach(st => {
            const li = document.createElement('li');
            li.textContent = (st.HO_TEN || '(chưa rõ)') + (st.CHUYEN_MON ? ` – ${st.CHUYEN_MON}` : '');
            dmStaff.appendChild(li);
        });
    } else {
        const li = document.createElement('li');
        li.textContent = 'Chưa phân công';
        dmStaff.appendChild(li);
    }

    dmDevices.innerHTML = '';
    if (payload.thiet_bi && payload.thiet_bi.length) {
        payload.thiet_bi.forEach(dev => {
            const li = document.createElement('li');
            li.textContent = (dev.TEN_TB || 'Thiết bị') + ` × ${dev.SO_LUONG ?? 1}`;
            dmDevices.appendChild(li);
        });
    } else {
        const li = document.createElement('li');
        li.textContent = 'Không có thiết bị đính kèm';
        dmDevices.appendChild(li);
    }

    // Báo giá tạm tính: ưu tiên trường tổng; nếu thiếu, ước tính từ chi tiết JSON
    let quoteText = '—';
    const formattedDirect = formatVND(payload.tam_tinh);
    if (formattedDirect) {
        quoteText = formattedDirect + ' (tạm tính)';
    } else {
        const estimated = computeQuoteFromDetail(payload.chi_tiet_bg);
        const formattedEst = formatVND(estimated);
        if (formattedEst) quoteText = formattedEst + ' (ước tính)';
    }
    dmQuoteTotal.textContent = quoteText;

    // Ẩn chi tiết JSON của báo giá theo yêu cầu; chỉ hiển thị tổng

    dmHdId.textContent     = payload.hoa_don.id_hd ?? '—';
    dmHdTotal.textContent  = payload.hoa_don.tong_tien
        ? payload.hoa_don.tong_tien + ' VND'
        : '—';
    dmHdStatus.textContent = payload.hoa_don.trang_thai_tt ?? '—';

    const gatewayStatus = payload.hoa_don.gateway_status;
    if (!gatewayStatus) {
        dmHdVerify.textContent = 'Chưa có giao dịch';
    } else if (gatewayStatus === 'pending') {
        dmHdVerify.textContent = 'Đang xử lý';
    } else if (gatewayStatus === 'success') {
        dmHdVerify.textContent = 'Đã xác nhận';
    } else if (gatewayStatus === 'failed') {
        dmHdVerify.textContent = 'Giao dịch lỗi';
    } else {
        dmHdVerify.textContent = gatewayStatus;
    }

    detailModal.classList.remove('hidden');
    detailModal.classList.add('flex');
}

function closeDetailModal() {
    detailModal.classList.add('hidden');
    detailModal.classList.remove('flex');
}

function openRentalDetailModal(payload){
    if (!rentalDetailModal) return;
    rdId.textContent         = '#' + (payload.id_ttp ?? '');
    rdStatus.textContent     = payload.trang_thai_label ?? payload.trang_thai ?? '—';
    rdBooked.textContent     = payload.dat_luc_text ?? '—';
    rdBranch.textContent     = payload.chi_nhanh ?? '—';
    rdPickup.textContent     = payload.ngay_nhan_text ?? '—';
    rdReturnPlan.textContent = payload.ngay_tra_du_kien_text ?? '—';

    const hasActual = payload.ngay_tra_thuc_te_text && payload.ngay_tra_thuc_te_text !== '—';
    if (rdReturnActualWrap) {
        rdReturnActualWrap.classList.toggle('hidden', !hasActual);
    }
    rdReturnActual.textContent = hasActual ? payload.ngay_tra_thuc_te_text : '—';

    rdItems.innerHTML = '';
    if (payload.items && payload.items.length) {
        payload.items.forEach(item => {
            const li = document.createElement('li');
            const name = item.TEN || 'Trang phục';
            const qty = item.SO_LUONG ?? 1;
            const size = item.SIZE ? ` · Size: ${item.SIZE}` : '';
            const color = item.MAU_SAC ? ` · Màu: ${item.MAU_SAC}` : '';
            li.textContent = `${name} × ${qty}${size}${color}`;
            rdItems.appendChild(li);
        });
    } else {
        const li = document.createElement('li');
        li.textContent = 'Chưa có dữ liệu chi tiết';
        rdItems.appendChild(li);
    }

    rdDeposit.textContent  = displayMoney(payload.tien_coc);
    rdEstimate.textContent = displayMoney(payload.tong_du_kien);
    rdActual.textContent   = displayMoney(payload.tong_thuc_te);
    const note = (payload.ghi_chu || '').toString().trim();
    rdNote.textContent = note !== '' ? note : 'Chưa có ghi chú.';

    const hd = payload.hoa_don || {};
    rdHdId.textContent    = hd.id_hd ?? '—';
    rdHdTotal.textContent = displayMoney(hd.tong_tien);
    rdHdStatus.textContent= hd.trang_thai_tt ?? '—';

    const gatewayStatus = hd.gateway_status;
    if (!gatewayStatus) {
        rdHdVerify.textContent = 'Chưa có giao dịch';
    } else if (gatewayStatus === 'pending') {
        rdHdVerify.textContent = 'Đang xử lý';
    } else if (gatewayStatus === 'success') {
        rdHdVerify.textContent = 'Đã xác nhận';
    } else if (gatewayStatus === 'failed') {
        rdHdVerify.textContent = 'Giao dịch lỗi';
    } else {
        rdHdVerify.textContent = gatewayStatus;
    }

    rentalDetailModal.classList.remove('hidden');
    rentalDetailModal.classList.add('flex');
}

function closeRentalDetailModal(){
    if (!rentalDetailModal) return;
    rentalDetailModal.classList.add('hidden');
    rentalDetailModal.classList.remove('flex');
}

function activateTab(target){
    if (!tabRoot) return;
    const normalized = target === 'rentals' ? 'rentals' : 'appointments';
    const activeClasses = ['bg-white', 'text-blue-900', 'shadow'];
    const inactiveClasses = ['bg-white/30', 'text-blue-700', 'hover:bg-white/60'];

    tabButtons.forEach(btn => {
        const isActive = btn.dataset.tabTarget === normalized;
        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        activeClasses.forEach(cls => btn.classList.toggle(cls, isActive));
        inactiveClasses.forEach(cls => btn.classList.toggle(cls, !isActive));
    });

    tabPanels.forEach(panel => {
        if (panel.dataset.tabPanel === normalized) {
            panel.classList.remove('hidden');
        } else {
            panel.classList.add('hidden');
        }
    });

    tabRoot.dataset.activeTab = normalized;
}

if (tabRoot && tabButtons.length) {
    const initialTab = tabRoot.dataset.activeTab || 'appointments';
    activateTab(initialTab);
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => activateTab(btn.dataset.tabTarget));
    });
}

const payModal       = document.getElementById('payModal');
const pmIdHdInput    = document.getElementById('pm-id-hd');
const pmIdLichInput  = document.getElementById('pm-id-lichhen');
const pmLichIdInline = document.getElementById('pm-lich-id-inline');
const pmTotal        = document.getElementById('pm-total');

function openPayModal(payload) {
    if (payload.dang_xu_ly_gateway) {
        alert('VNPay đang xử lý giao dịch hiện tại. Vui lòng chờ hoàn tất trước khi gửi thêm chứng từ.');
        return;
    }

    pmIdHdInput.value     = payload.id_hd || '';
    pmIdLichInput.value   = payload.id_lichhen || '';
    pmLichIdInline.textContent = '#' + (payload.id_lichhen || '');
    pmTotal.textContent   = payload.tong_tien
        ? payload.tong_tien + ' VND'
        : '—';

    payModal.classList.remove('hidden');
    payModal.classList.add('flex');
}

function closePayModal() {
    payModal.classList.add('hidden');
    payModal.classList.remove('flex');
}
</script>
