<?php
if (!isset($conn)) {
    require_once '../../../database/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['ID_TK'])) {
    echo "<p class='text-red-500 font-bold'>Vui lòng đăng nhập để xem hóa đơn.</p>";
    exit;
}

$userId = mysqli_real_escape_string($conn, $_SESSION['ID_TK']);
$limit = 5;
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $limit;

$latestVnpayJoin = "
    LEFT JOIN (
        SELECT t1.ID_HD,
               t1.TRANG_THAI AS VNPAY_TRANG_THAI,
               t1.MA_THAM_CHIEU AS VNPAY_MA_THAM_CHIEU,
               t1.SO_TIEN AS VNPAY_SO_TIEN,
               t1.CREATED_AT AS VNPAY_CREATED_AT
        FROM thanh_toan_truc_tuyen t1
        JOIN (
            SELECT ID_HD, MAX(CREATED_AT) AS latest_created
            FROM thanh_toan_truc_tuyen
            WHERE GATEWAY = 'vnpay'
            GROUP BY ID_HD
        ) latest ON latest.ID_HD = t1.ID_HD AND latest.latest_created = t1.CREATED_AT
        WHERE t1.GATEWAY = 'vnpay'
    ) tt ON tt.ID_HD = h.ID_HD
";

// Thông báo trạng thái thanh toán (nếu có)
$flashNotice = $_SESSION['payment_notice'] ?? '';
$flashType = $_SESSION['payment_notice_type'] ?? '';
if ($flashNotice !== '') {
    unset($_SESSION['payment_notice'], $_SESSION['payment_notice_type']);
}

// Thống kê nhanh tình trạng hóa đơn
$statsQuery = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN h.TRANGTHAI_THANHTOAN = 'Đã thanh toán' THEN 1 ELSE 0 END) AS paid,
        SUM(
            CASE
                WHEN h.TRANGTHAI_THANHTOAN <> 'Đã thanh toán' AND tt.VNPAY_TRANG_THAI = 'pending' THEN 1
                ELSE 0
            END
        ) AS verifying,
        SUM(
            CASE
                WHEN h.TRANGTHAI_THANHTOAN <> 'Đã thanh toán' AND (tt.VNPAY_TRANG_THAI IS NULL OR tt.VNPAY_TRANG_THAI <> 'pending') THEN 1
                ELSE 0
            END
        ) AS unpaid
    FROM hoa_don h
    JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
" . $latestVnpayJoin . "
    WHERE l.ID_TK = '$userId'
";
$statsResult = mysqli_query($conn, $statsQuery);
$statsRow = $statsResult ? mysqli_fetch_assoc($statsResult) : [];
$statusStats = [
    'total' => (int) ($statsRow['total'] ?? 0),
    'paid' => (int) ($statsRow['paid'] ?? 0),
    'verifying' => (int) ($statsRow['verifying'] ?? 0),
    'unpaid' => (int) ($statsRow['unpaid'] ?? 0),
];

// Bộ lọc trạng thái
$activeState = isset($_GET['state']) ? strtolower($_GET['state']) : 'all';
$stateFilterClause = '';
switch ($activeState) {
    case 'paid':
        $stateFilterClause = " AND h.TRANGTHAI_THANHTOAN = 'Đã thanh toán'";
        break;
    case 'verifying':
        $stateFilterClause = " AND h.TRANGTHAI_THANHTOAN <> 'Đã thanh toán' AND tt.VNPAY_TRANG_THAI = 'pending'";
        break;
    case 'unpaid':
        $stateFilterClause = " AND h.TRANGTHAI_THANHTOAN <> 'Đã thanh toán' AND (tt.VNPAY_TRANG_THAI IS NULL OR tt.VNPAY_TRANG_THAI <> 'pending')";
        break;
    default:
        $activeState = 'all';
        break;
}

// Đếm tổng hóa đơn sau khi áp dụng bộ lọc
$countQuery = "
    SELECT COUNT(*) AS total
    FROM hoa_don h
    JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
" . $latestVnpayJoin . "
    WHERE l.ID_TK = '$userId' $stateFilterClause
";
$countResult = mysqli_query($conn, $countQuery);
$totalRows = (int) ($countResult ? (mysqli_fetch_assoc($countResult)['total'] ?? 0) : 0);
$totalPages = $totalRows > 0 ? (int) ceil($totalRows / $limit) : 1;

// Truy vấn phân trang kèm thông tin lịch hẹn và chi nhánh
$sql = "
    SELECT h.ID_HD, h.NGAY_GIO, h.TONG_TIEN, h.TRANGTHAI_THANHTOAN,
           h.PHUONGTHUC_THANHTOAN, k.HO_TEN, k.EMAIL, k.SDT, d.TEN_DV,
           l.THOI_GIAN_BAT_DAU, l.DIA_CHI_HEN, l.TRANGTHAI AS LICH_TRANGTHAI,
           cn.TEN_CN,
           tt.TRANG_THAI AS VNPAY_TRANG_THAI, tt.MA_THAM_CHIEU, tt.SO_TIEN AS VNPAY_SO_TIEN,
           tt.CREATED_AT AS VNPAY_CREATED_AT
    FROM hoa_don h
    JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
    JOIN khach_hang k ON l.ID_TK = k.ID_TK
    JOIN dich_vu d ON l.ID_DV = d.ID_DV
    LEFT JOIN chi_nhanh cn ON l.ID_CHINHANH = cn.ID_CN
" . $latestVnpayJoin . "
    WHERE l.ID_TK = '$userId' $stateFilterClause
    ORDER BY h.NGAY_GIO DESC
    LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $sql);
$invoices = [];
$invoiceIds = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $invoices[] = $row;
        $invoiceIds[] = (int) $row['ID_HD'];
    }
}

$nextSchedule = null;
if (!empty($invoices)) {
    foreach ($invoices as $row) {
        $startTimestamp = !empty($row['THOI_GIAN_BAT_DAU']) ? strtotime($row['THOI_GIAN_BAT_DAU']) : null;
        if ($startTimestamp && ($nextSchedule === null || $startTimestamp < $nextSchedule['time'])) {
            $nextSchedule = [
                'time' => $startTimestamp,
                'service' => $row['TEN_DV'] ?? '',
                'branch' => $row['TEN_CN'] ?? ''
            ];
        }
    }
}

$paymentHistory = [];
if (!empty($invoiceIds)) {
    $idList = implode(',', array_map('intval', $invoiceIds));

    // Lịch sử thanh toán trực tuyến (VNPAY và cổng khác)
    $onlineQuery = "
        SELECT ID_HD, GATEWAY, MA_THAM_CHIEU, SO_TIEN, TRANG_THAI, CREATED_AT
        FROM thanh_toan_truc_tuyen
        WHERE ID_HD IN ($idList)
        ORDER BY CREATED_AT DESC
    ";
    $onlineResult = mysqli_query($conn, $onlineQuery);
    if ($onlineResult) {
        while ($row = mysqli_fetch_assoc($onlineResult)) {
            $invoiceId = (int) $row['ID_HD'];
            $paymentHistory[$invoiceId][] = [
                'type' => strtoupper($row['GATEWAY']),
                'reference' => $row['MA_THAM_CHIEU'],
                'amount' => $row['SO_TIEN'],
                'status' => $row['TRANG_THAI'],
                'time' => $row['CREATED_AT'],
                'note' => ''
            ];
        }
    }

    // Lịch sử xác nhận thủ công từ chứng từ thanh toán
    $manualQuery = "
        SELECT b.ID_HD, b.TEP_MINH_CHUNG, b.GHI_CHU, b.NGUOI_XAC_NHAN, b.THOI_GIAN_XN, b.KET_QUA
        FROM bang_chung_thanh_toan b
        WHERE b.ID_HD IN ($idList)
        ORDER BY b.THOI_GIAN_XN DESC
    ";
    $manualResult = mysqli_query($conn, $manualQuery);
    if ($manualResult) {
        while ($row = mysqli_fetch_assoc($manualResult)) {
            $invoiceId = (int) $row['ID_HD'];
            $paymentHistory[$invoiceId][] = [
                'type' => 'XÁC MINH',
                'reference' => $row['TEP_MINH_CHUNG'],
                'amount' => null,
                'status' => $row['KET_QUA'] ?? 'pending',
                'time' => $row['THOI_GIAN_XN'],
                'note' => $row['GHI_CHU'] !== null ? $row['GHI_CHU'] : ''
            ];
        }
    }
}

function formatCurrency($amount)
{
    if ($amount === null || $amount === '') {
        return '';
    }
    return number_format((float) $amount, 0, ',', '.') . ' VNĐ';
}

function formatDateTime($datetime)
{
    if (empty($datetime)) {
        return '';
    }
    return date('d/m/Y H:i', strtotime($datetime));
}

function resolveInvoiceState($statusText, $gatewayStatus = null)
{
    $normalized = trim((string) $statusText);
    if ($normalized === 'Đã thanh toán') {
        return 'paid';
    }
    if ($gatewayStatus === 'pending') {
        return 'verifying';
    }
    return 'unpaid';
}

function getPaymentBadgeClasses($status, $gatewayStatus = null)
{
    $normalized = trim((string) $status);
    if ($normalized === 'Đã thanh toán') {
        return ['label' => 'Đã thanh toán', 'class' => 'bg-green-500 text-white'];
    }
    if ($gatewayStatus === 'pending') {
        return ['label' => 'Đang xử lý cổng', 'class' => 'bg-amber-400 text-black'];
    }
    if ($gatewayStatus === 'failed') {
        return ['label' => 'Giao dịch lỗi', 'class' => 'bg-red-500 text-white'];
    }
    return ['label' => 'Chưa thanh toán', 'class' => 'bg-slate-200 text-slate-700'];
}

$filterOptions = [
    'all' => [
        'label' => 'Tất cả',
        'hint' => 'Danh sách đầy đủ',
        'count' => $statusStats['total']
    ],
    'unpaid' => [
        'label' => 'Chưa thanh toán',
        'hint' => 'Ưu tiên xử lý sớm',
        'count' => $statusStats['unpaid']
    ],
    'verifying' => [
        'label' => 'Đang xử lý cổng',
        'hint' => 'VNPay đang xác nhận',
        'count' => $statusStats['verifying']
    ],
    'paid' => [
        'label' => 'Đã thanh toán',
        'hint' => 'Hoàn tất và lưu trữ',
        'count' => $statusStats['paid']
    ],
];

$activeFilter = $filterOptions[$activeState] ?? $filterOptions['all'];
$hasAnyInvoices = $statusStats['total'] > 0;
$stateQueryParam = $activeState !== 'all' ? '&state=' . urlencode($activeState) : '';
?>

<div class="space-y-10">
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-800 to-slate-900 p-8 text-white shadow-2xl">
        <div class="flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between">
            <div class="space-y-4">
                <p class="text-xs uppercase tracking-[0.3em] text-indigo-200">Invoice Center</p>
                <h1 class="text-3xl font-bold leading-tight md:text-4xl">Quản lý hóa đơn &amp; thanh toán</h1>
                <p class="text-sm text-indigo-100 md:text-base">Theo dõi trọn vẹn hành trình từ đặt lịch đến thanh toán và lưu trữ chứng từ trong một màn hình thống nhất.</p>
                <?php if ($nextSchedule): ?>
                    <div class="flex items-center gap-4 rounded-2xl bg-white/10 px-4 py-3 text-sm text-indigo-100 backdrop-blur">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/20 text-xs font-semibold tracking-widest">CAL</div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-indigo-200">Lịch sắp tới</p>
                            <p class="text-sm font-semibold text-white">
                                <?= htmlspecialchars(formatDateTime(date('Y-m-d H:i:s', $nextSchedule['time']))) ?>
                                • <?= htmlspecialchars($nextSchedule['service']) ?>
                                <?php if (!empty($nextSchedule['branch'])): ?>
                                    • <?= htmlspecialchars($nextSchedule['branch']) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            $heroTiles = [
                ['label' => 'Tổng hóa đơn', 'value' => number_format($statusStats['total']), 'sub' => 'Toàn bộ lịch sử'],
                ['label' => 'Chưa thanh toán', 'value' => number_format($statusStats['unpaid']), 'sub' => 'Đang chờ xử lý'],
                ['label' => 'Đang xử lý', 'value' => number_format($statusStats['verifying']), 'sub' => 'VNPay đang xác nhận'],
                ['label' => 'Đã thanh toán', 'value' => number_format($statusStats['paid']), 'sub' => 'Hoàn tất'],
            ];
            ?>
            <div class="grid flex-1 gap-4 sm:grid-cols-2">
                <?php foreach ($heroTiles as $tile): ?>
                    <div class="rounded-2xl bg-white/10 p-4 text-left">
                        <p class="text-xs uppercase tracking-wide text-indigo-200"><?= htmlspecialchars($tile['label']) ?></p>
                        <p class="mt-2 text-3xl font-semibold text-white"><?= htmlspecialchars($tile['value']) ?></p>
                        <p class="text-xs text-indigo-100"><?= htmlspecialchars($tile['sub']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if ($flashNotice !== ''): ?>
        <div class="rounded-2xl border px-4 py-3 text-sm <?php echo $flashType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'; ?>">
            <?php echo htmlspecialchars($flashNotice); ?>
        </div>
    <?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-semibold text-slate-900">Bộ lọc trạng thái</p>
                <p class="text-xs text-slate-500">Chọn nhanh nhóm hóa đơn cần kiểm tra</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($filterOptions as $key => $filter): ?>
                    <?php
                    $isActive = $activeState === $key;
                    $baseClasses = $isActive
                        ? 'border-slate-900 bg-slate-900 text-white shadow-lg shadow-slate-900/20'
                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50';
                    ?>
                    <a href="<?= $key === 'all' ? '?' : '?state=' . urlencode($key) ?>" class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition <?= $baseClasses ?>" title="<?= htmlspecialchars($filter['hint']) ?>">
                        <span><?= htmlspecialchars($filter['label']) ?></span>
                        <span class="inline-flex min-w-[2rem] items-center justify-center rounded-full bg-white/20 px-2 text-xs font-bold <?= $isActive ? 'text-white' : 'text-slate-500' ?>">
                            <?= htmlspecialchars($filter['count']) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if (!empty($invoices)): ?>
        <div class="space-y-8">
            <?php foreach ($invoices as $row):
                $invoiceId = (int) $row['ID_HD'];
                $qrData = "STK:1234567890|TONGTIEN:" . $row['TONG_TIEN'] . "|ND:THANHTOAN_HD_" . $invoiceId;
                $qrImg = "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($qrData) . "&size=180x180";
                $gatewayStatus = $row['VNPAY_TRANG_THAI'] ?? null;
                $badge = getPaymentBadgeClasses($row['TRANGTHAI_THANHTOAN'], $gatewayStatus);
                $history = $paymentHistory[$invoiceId] ?? [];
                $latestHistory = $history[0] ?? null;
                $isPaidInvoice = trim($row['TRANGTHAI_THANHTOAN']) === 'Đã thanh toán';
                $paymentDescription = 'Vui lòng hoàn tất thanh toán để giữ lịch.';
                $paymentState = 'pending';

                if ($isPaidInvoice) {
                    $paymentDescription = 'Khoản phí đã được tất toán đầy đủ.';
                    $paymentState = 'done';
                } elseif ($gatewayStatus === 'pending') {
                    $paymentDescription = 'VNPay đã ghi nhận giao dịch, hệ thống sẽ tự động cập nhật sau ít phút.';
                    $paymentState = 'processing';
                } elseif ($gatewayStatus === 'failed') {
                    $paymentDescription = 'Lần thanh toán gần nhất không thành công. Vui lòng thử lại trên VNPay hoặc chọn phương thức khác.';
                }

                $timeline = [
                    [
                        'label' => 'Đặt lịch',
                        'description' => 'Lịch hẹn cho dịch vụ ' . $row['TEN_DV'],
                        'time' => $row['THOI_GIAN_BAT_DAU'],
                        'state' => 'done'
                    ],
                    [
                        'label' => 'Xuất hóa đơn',
                        'description' => 'Hóa đơn đã được phát hành và gửi tới bạn.',
                        'time' => $row['NGAY_GIO'],
                        'state' => 'done'
                    ],
                    [
                        'label' => 'Thanh toán',
                        'description' => $paymentDescription,
                        'time' => $latestHistory['time'] ?? null,
                        'state' => $paymentState
                    ],
                    [
                        'label' => 'Lịch sử thanh toán',
                        'description' => empty($history)
                            ? 'Chưa ghi nhận giao dịch nào.'
                            : 'Nhật ký giao dịch đã được lưu.',
                        'time' => $latestHistory['time'] ?? null,
                        'state' => empty($history) ? 'pending' : 'done'
                    ],
                ];
            ?>
                <div id="invoice-<?= $invoiceId ?>" class="rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-200/70">
                    <div class="flex flex-col lg:flex-row">
                        <div class="flex-1 space-y-6 p-6">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hóa đơn</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-3">
                                        <h2 class="text-3xl font-bold text-slate-900">#<?= $invoiceId ?></h2>
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?= $badge['class'] ?>">
                                            <?= htmlspecialchars($badge['label']) ?>
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500">Phát hành <?= htmlspecialchars(formatDateTime($row['NGAY_GIO'])) ?></p>
                                </div>
                                <div class="text-left md:text-right">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tổng cộng</p>
                                    <p class="mt-1 text-3xl font-bold text-slate-900"><?= htmlspecialchars(formatCurrency($row['TONG_TIEN'])) ?></p>
                                    <p class="text-xs text-slate-500"><?= htmlspecialchars($row['PHUONGTHUC_THANHTOAN'] ? 'Phương thức: ' . $row['PHUONGTHUC_THANHTOAN'] : 'Chưa chọn phương thức') ?></p>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="rounded-2xl border border-slate-100 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Thông tin lịch hẹn</p>
                                    <dl class="mt-3 space-y-2 text-sm text-slate-600">
                                        <div class="flex justify-between gap-2"><dt class="font-medium text-slate-700">Dịch vụ</dt><dd class="text-right"><?= htmlspecialchars($row['TEN_DV']) ?></dd></div>
                                        <div class="flex justify-between gap-2"><dt class="font-medium text-slate-700">Thời gian</dt><dd><?= htmlspecialchars(formatDateTime($row['THOI_GIAN_BAT_DAU'])) ?></dd></div>
                                        <div class="flex justify-between gap-2"><dt class="font-medium text-slate-700">Chi nhánh</dt><dd><?= htmlspecialchars($row['TEN_CN'] ?? 'Đang cập nhật') ?></dd></div>
                                        <div class="flex justify-between gap-2"><dt class="font-medium text-slate-700">Trạng thái lịch</dt><dd><?= htmlspecialchars($row['LICH_TRANGTHAI'] ?? 'Chưa xác định') ?></dd></div>
                                        <?php if (!empty($row['DIA_CHI_HEN'])): ?>
                                            <div class="flex justify-between gap-2"><dt class="font-medium text-slate-700">Địa điểm</dt><dd class="text-right text-slate-600 md:text-left"><?= htmlspecialchars($row['DIA_CHI_HEN']) ?></dd></div>
                                        <?php endif; ?>
                                    </dl>
                                </div>
                                <div class="rounded-2xl border border-slate-100 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Khách hàng</p>
                                    <dl class="mt-3 space-y-2 text-sm text-slate-600">
                                        <div class="flex justify-between gap-2"><dt class="font-medium text-slate-700">Họ tên</dt><dd><?= htmlspecialchars($row['HO_TEN'] ?? '—') ?></dd></div>
                                        <div class="flex justify-between gap-2"><dt class="font-medium text-slate-700">Email</dt><dd><?= htmlspecialchars($row['EMAIL'] ?? '—') ?></dd></div>
                                        <div class="flex justify-between gap-2"><dt class="font-medium text-slate-700">Số điện thoại</dt><dd><?= htmlspecialchars($row['SDT'] ?? '—') ?></dd></div>
                                        <div class="flex justify-between gap-2"><dt class="font-medium text-slate-700">Phương thức</dt><dd><?= htmlspecialchars($row['PHUONGTHUC_THANHTOAN'] ?? 'Chưa cập nhật') ?></dd></div>
                                    </dl>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-dashed border-slate-200 p-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lộ trình xử lý</p>
                                    <?php if ($latestHistory): ?>
                                        <p class="text-xs text-slate-400">Cập nhật <?= htmlspecialchars(formatDateTime($latestHistory['time'])) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    <?php foreach ($timeline as $step):
                                        $boxClass = $step['state'] === 'done'
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                            : ($step['state'] === 'processing'
                                                ? 'border-amber-200 bg-amber-50 text-amber-700'
                                                : 'border-slate-200 bg-slate-50 text-slate-600');
                                        $dotClass = $step['state'] === 'done'
                                            ? 'bg-emerald-500'
                                            : ($step['state'] === 'processing' ? 'bg-amber-400' : 'bg-slate-300');
                                    ?>
                                        <div class="rounded-xl border <?= $boxClass ?> p-3">
                                            <div class="flex items-center gap-2">
                                                <span class="h-2 w-2 rounded-full <?= $dotClass ?>"></span>
                                                <p class="text-sm font-semibold"><?= htmlspecialchars($step['label']) ?></p>
                                            </div>
                                            <p class="mt-1 text-xs leading-relaxed"><?= htmlspecialchars($step['description']) ?></p>
                                            <?php if (!empty($step['time'])): ?>
                                                <p class="mt-2 text-xs font-medium text-slate-500"><?= htmlspecialchars(formatDateTime($step['time'])) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="w-full border-t border-slate-100 bg-slate-50 p-6 lg:w-80 lg:border-t-0 lg:border-l">
                            <div class="space-y-5">
                                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hành động nhanh</p>
                                    <?php if (trim($row['TRANGTHAI_THANHTOAN']) === 'Đã thanh toán'): ?>
                                        <p class="mt-3 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Chúng tôi đã ghi nhận khoản thanh toán hoàn tất. Xin cảm ơn!</p>
                                    <?php else: ?>
                                        <div class="space-y-4">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-800">Chuyển khoản ngân hàng</p>
                                                <p class="mt-1 text-xs text-slate-500">Quét QR hoặc nhập STK: <span class="font-semibold text-slate-900">1234567890</span>.</p>
                                                <img src="<?= $qrImg ?>" alt="QR hóa đơn <?= $invoiceId ?>" class="mx-auto mt-3 w-40 rounded-lg border border-slate-200">
                                                <p class="mt-2 text-xs text-slate-600">Nội dung chuyển khoản: <span class="font-semibold">THANHTOAN_HD_<?= $invoiceId ?></span></p>
                                                <p class="pt-2 text-xs text-slate-500">Hệ thống sẽ tự động cập nhật khi xác nhận thanh toán thành công.</p>
                                            </div>
                                            <div class="border-t border-dashed border-slate-200 pt-4">
                                                <p class="text-sm font-semibold text-slate-800">Thanh toán qua VNPAY</p>
                                                <?php if (!empty($row['VNPAY_TRANG_THAI'])):
                                                    $vnpayClass = $row['VNPAY_TRANG_THAI'] === 'success'
                                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                        : ($row['VNPAY_TRANG_THAI'] === 'failed'
                                                            ? 'border-red-200 bg-red-50 text-red-700'
                                                            : 'border-amber-200 bg-amber-50 text-amber-700');
                                                ?>
                                                    <p class="mt-2 rounded-lg border <?= $vnpayClass ?> px-3 py-2 text-xs">
                                                        <?php if ($row['VNPAY_TRANG_THAI'] === 'pending'): ?>
                                                            Đang chờ xác nhận từ VNPAY. Mã tham chiếu: <?= htmlspecialchars($row['MA_THAM_CHIEU']) ?>.
                                                        <?php elseif ($row['VNPAY_TRANG_THAI'] === 'failed'): ?>
                                                            Giao dịch VNPAY gần nhất không thành công. Vui lòng thử lại.
                                                        <?php else: ?>
                                                            VNPAY báo thành công lúc <?= htmlspecialchars(formatDateTime($row['VNPAY_CREATED_AT'])) ?>.
                                                        <?php endif; ?>
                                                    </p>
                                                <?php endif; ?>
                                                <form method="POST" action="../Controller/vnpay_create_payment.php" class="space-y-3 pt-2">
                                                    <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">
                                                    <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700">Thanh toán với VNPAY</button>
                                                </form>
                                                <p class="mt-2 text-xs text-slate-500">Bạn sẽ được chuyển sang cổng VNPAY để hoàn tất giao dịch.</p>
                                            </div>
                                        </div>
                                        <?php if ($gatewayStatus === 'pending'): ?>
                                            <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">Đang đợi VNPay xác nhận giao dịch. Hóa đơn sẽ tự động chuyển sang trạng thái đã thanh toán ngay khi nhận được IPN.</p>
                                        <?php elseif ($gatewayStatus === 'failed'): ?>
                                            <p class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">Giao dịch VNPay gần nhất không thành công. Bạn có thể thử lại hoặc chọn chuyển khoản ngân hàng.</p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 bg-slate-50 px-6 py-5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Nhật ký giao dịch</h3>
                            <?php if (!empty($history)): ?>
                                <span class="text-xs text-slate-400"><?= count($history) ?> bản ghi</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($history)): ?>
                            <div class="mt-4 overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-100">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Thời gian</th>
                                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Hình thức</th>
                                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Mã tham chiếu / Tệp</th>
                                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Số tiền</th>
                                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Trạng thái</th>
                                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Ghi chú</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        <?php foreach ($history as $log):
                                            $statusText = $log['status'] ?? '';
                                            $statusClass = 'text-slate-600';
                                            if ($statusText === 'success' || $statusText === 'dong_y') {
                                                $statusClass = 'text-emerald-600';
                                            } elseif ($statusText === 'failed' || $statusText === 'tu_choi') {
                                                $statusClass = 'text-red-600';
                                            } elseif ($statusText === 'pending') {
                                                $statusClass = 'text-amber-600';
                                            }
                                        ?>
                                            <tr>
                                                <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars(formatDateTime($log['time'])) ?></td>
                                                <td class="px-3 py-2 font-semibold text-slate-700"><?= htmlspecialchars($log['type']) ?></td>
                                                <td class="px-3 py-2 text-slate-600">
                                                    <?= htmlspecialchars($log['reference'] ?: '—') ?>
                                                </td>
                                                <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars(formatCurrency($log['amount'])) ?></td>
                                                <td class="px-3 py-2 font-semibold <?= $statusClass ?>">
                                                    <?= htmlspecialchars(strtoupper($statusText ?: 'ĐANG XỬ LÝ')) ?>
                                                </td>
                                                <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($log['note'] ?: '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="mt-3 rounded-lg bg-white px-4 py-3 text-sm text-slate-600">Chưa có giao dịch hoặc chứng từ nào cho hóa đơn này.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="flex flex-wrap justify-center gap-2">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?p=<?= $i ?><?= htmlspecialchars($stateQueryParam, ENT_QUOTES, 'UTF-8') ?>" class="rounded-full px-4 py-2 text-sm font-semibold transition <?= $i === $page ? 'bg-indigo-600 text-white shadow-lg' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm">
            <?php if ($hasAnyInvoices): ?>
                <p class="text-base font-semibold text-slate-700">Không tìm thấy hóa đơn phù hợp với bộ lọc "<?= htmlspecialchars($activeFilter['label']) ?>".</p>
                <p class="mt-2 text-sm text-slate-500">Thử quay lại danh sách đầy đủ để xem tất cả.</p>
                <a href="?" class="mt-4 inline-flex items-center justify-center rounded-full bg-indigo-600 px-6 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700">Xem tất cả hóa đơn</a>
            <?php else: ?>
                <p class="text-base font-semibold text-slate-700">Bạn chưa có hóa đơn nào.</p>
                <p class="mt-2 text-sm text-slate-500">Khi hoàn tất lịch hẹn đầu tiên, hóa đơn sẽ xuất hiện tại đây.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php $conn->close(); ?>
