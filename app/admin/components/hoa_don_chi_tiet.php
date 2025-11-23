<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../database/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_GET['id_hd'])) {
    echo "<script>alert('Thiếu mã hóa đơn.'); history.back();</script>";
    exit;
}

$id_hd = (int)$_GET['id_hd'];

// Xác định nguồn gọi (admin hay manager) dựa trên HTTP_REFERER hoặc tham số
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$backUrl = 'admin_dashboard.php?page=payments'; // Mặc định cho admin (đã ở trong thư mục admin rồi)
$backPage = 'payments'; // Mặc định

// Kiểm tra nếu được gọi từ manager_dashboard
if (strpos($referer, 'manager_dashboard.php') !== false || ($_GET['source'] ?? '') === 'manager') {
    $backUrl = 'manager_dashboard.php?page=invoices';
    $backPage = 'invoices';
} else {
    // Mặc định là admin
    $backUrl = 'admin_dashboard.php?page=payments';
    $backPage = 'payments';
}

function calculateTotalPrice($idLichHen)
{
    global $conn;
    $totalDV = 0;
    $totalTB = 0;

    // Giá dịch vụ mới nhất
    $queryDV = "SELECT dv.thoi_gian, dg.DON_GIA 
                FROM lich_hen lh 
                JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV 
                JOIN (
                    SELECT ID_DV, DON_GIA 
                    FROM don_gia_dich_vu dg1 
                    WHERE NGAY_GIO = (
                        SELECT MAX(NGAY_GIO) 
                        FROM don_gia_dich_vu dg2 
                        WHERE dg2.ID_DV = dg1.ID_DV
                    )
                ) dg ON dv.ID_DV = dg.ID_DV 
                WHERE lh.ID_LICHHEN = ?";
    $stmtDV = mysqli_prepare($conn, $queryDV);
            if (!$stmtDV) {
                error_log('calculateTotalPrice service query failed: ' . mysqli_error($conn));
                return ['totalDV' => 0, 'totalTB' => 0, 'total' => 0];
            }
    mysqli_stmt_bind_param($stmtDV, 'i', $idLichHen);
            if (!mysqli_stmt_execute($stmtDV)) {
                error_log('calculateTotalPrice service execute failed: ' . mysqli_error($conn));
                mysqli_stmt_close($stmtDV);
                return ['totalDV' => 0, 'totalTB' => 0, 'total' => 0];
            }
            $resultDV = mysqli_stmt_get_result($stmtDV);

    if ($resultDV && $rowDV = mysqli_fetch_assoc($resultDV)) {
        $soPhut = (int)$rowDV['thoi_gian'];
        $soGio = $soPhut / 60;
        $totalDV = $soGio * $rowDV['DON_GIA'];
    }
            mysqli_stmt_close($stmtDV);

    // Giá thiết bị mới nhất
    $queryTB = "SELECT dgtb.DON_GIA, lhtb.SO_LUONG 
                FROM lich_hen_thiet_bi lhtb 
                JOIN trang_thiet_bi tb ON lhtb.ID_TB = tb.ID_TB 
                JOIN (
                    SELECT ID_TB, DON_GIA 
                    FROM don_gia_trang_thiet_bi dgtb1 
                    WHERE NGAY_GIO = (
                        SELECT MAX(NGAY_GIO) 
                        FROM don_gia_trang_thiet_bi dgtb2 
                        WHERE dgtb2.ID_TB = dgtb1.ID_TB
                    )
                ) dgtb ON tb.ID_TB = dgtb.ID_TB 
                WHERE lhtb.ID_LICHHEN = ?";
    $stmtTB = mysqli_prepare($conn, $queryTB);
    if ($stmtTB) {
        mysqli_stmt_bind_param($stmtTB, 'i', $idLichHen);
        if (mysqli_stmt_execute($stmtTB)) {
            $resultTB = mysqli_stmt_get_result($stmtTB);

            while ($resultTB && $rowTB = mysqli_fetch_assoc($resultTB)) {
                $totalTB += $rowTB['DON_GIA'] * $rowTB['SO_LUONG'];
            }
        } else {
            error_log('calculateTotalPrice device execute failed: ' . mysqli_error($conn));
        }
        mysqli_stmt_close($stmtTB);
    } else {
        error_log('calculateTotalPrice device query failed: ' . mysqli_error($conn));
    }

    return [
        'totalDV' => $totalDV,
        'totalTB' => $totalTB,
        'total' => $totalDV + $totalTB
    ];
}


$stmt = mysqli_prepare($conn, "
    SELECT 
        hd.ID_HD, hd.NGAY_GIO, hd.TRANGTHAI_THANHTOAN, hd.PHUONGTHUC_THANHTOAN, hd.TONG_TIEN,
        tt.VNPAY_TRANG_THAI, tt.VNPAY_MA_THAM_CHIEU, tt.VNPAY_UPDATED_AT,
        kh.HO_TEN, kh.EMAIL, kh.SDT,
        lh.ID_LICHHEN, dv.TEN_DV, dv.thoi_gian, dgdv.DON_GIA AS GIA_DV,
        ttp.ID_TTP, ttp.NGAY_NHAN, ttp.NGAY_TRA_DK, ttp.NGAY_TRA_TT, ttp.TRANG_THAI AS TTP_TRANGTHAI,
        ttp.TIEN_COC, ttp.TONG_TIEN_DU_KIEN, ttp.TONG_TIEN_THUC_TE
    FROM hoa_don hd
    LEFT JOIN lich_hen lh ON hd.ID_LICHHEN = lh.ID_LICHHEN
    LEFT JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
    LEFT JOIN don_gia_dich_vu dgdv ON dv.ID_DV = dgdv.ID_DV
    LEFT JOIN don_thue_trang_phuc ttp ON hd.ID_TTP = ttp.ID_TTP
    LEFT JOIN tai_khoan kh ON COALESCE(lh.ID_TK, ttp.ID_TK) = kh.ID_TK
    LEFT JOIN (
        SELECT t1.ID_HD,
               t1.TRANG_THAI AS VNPAY_TRANG_THAI,
               t1.MA_THAM_CHIEU AS VNPAY_MA_THAM_CHIEU,
               t1.CREATED_AT AS VNPAY_UPDATED_AT
        FROM thanh_toan_truc_tuyen t1
        JOIN (
            SELECT ID_HD, MAX(CREATED_AT) AS latest_created
            FROM thanh_toan_truc_tuyen
            WHERE GATEWAY = 'vnpay'
            GROUP BY ID_HD
        ) latest ON latest.ID_HD = t1.ID_HD AND latest.latest_created = t1.CREATED_AT
        WHERE t1.GATEWAY = 'vnpay'
    ) tt ON tt.ID_HD = hd.ID_HD
    WHERE hd.ID_HD = ?");

if (!$stmt) {
    error_log('Invoice detail query prepare failed: ' . mysqli_error($conn));
    echo "<script>alert('Không thể tải dữ liệu hóa đơn lúc này.'); window.location.href='" . $backUrl . "';</script>";
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $id_hd);
mysqli_stmt_execute($stmt);
$invoice = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$invoice) {
    echo "<script>alert('Không tìm thấy hóa đơn.'); history.back();</script>";
    exit;
}

$isPaid = $invoice['TRANGTHAI_THANHTOAN'] === 'Đã thanh toán';
$gatewayStatus = $invoice['VNPAY_TRANG_THAI'] ?? null;
$gatewayRef = $invoice['VNPAY_MA_THAM_CHIEU'] ?? null;
$gatewayUpdatedAt = $invoice['VNPAY_UPDATED_AT'] ?? null;
$isGatewayPending = !$isPaid && $gatewayStatus === 'pending';
$methodLabel = $invoice['PHUONGTHUC_THANHTOAN'] ? strtoupper($invoice['PHUONGTHUC_THANHTOAN']) : 'Chưa ghi nhận';

$gatewayBadge = [
    'class' => 'bg-slate-100 text-slate-600',
    'label' => 'Chưa có giao dịch',
    'description' => 'Sẵn sàng khởi tạo VNPay hoặc ghi nhận chuyển khoản.',
];

if ($gatewayStatus === 'pending') {
    $gatewayBadge = [
        'class' => 'bg-amber-100 text-amber-700',
        'label' => 'VNPay đang xử lý',
        'description' => 'Chờ IPN xác nhận. Không cần thao tác thủ công.',
    ];
} elseif ($gatewayStatus === 'success') {
    $gatewayBadge = [
        'class' => 'bg-emerald-100 text-emerald-700',
        'label' => 'VNPay đã xác nhận',
        'description' => 'Invoice sẽ tự động chuyển sang đã thanh toán.',
    ];
} elseif ($gatewayStatus === 'failed') {
    $gatewayBadge = [
        'class' => 'bg-rose-100 text-rose-700',
        'label' => 'VNPay lỗi',
        'description' => 'Cần yêu cầu khách thanh toán lại hoặc xác minh thủ công.',
    ];
}

// Xác định loại hóa đơn: lịch hẹn hay thuê trang phục
$isRental = !empty($invoice['ID_TTP']);

// Lấy line items chuẩn từ chi_tiet_hoa_don (áp dụng cho cả hai loại)
$lineItems = [];
$lineStmt = mysqli_prepare($conn, "SELECT MO_TA, SO_LUONG, DON_GIA, THANH_TIEN FROM chi_tiet_hoa_don WHERE ID_HD = ? ORDER BY ID_CTHD ASC");
if ($lineStmt) {
    mysqli_stmt_bind_param($lineStmt, 'i', $invoice['ID_HD']);
    if (mysqli_stmt_execute($lineStmt)) {
        $resLines = mysqli_stmt_get_result($lineStmt);
        while ($resLines && $li = mysqli_fetch_assoc($resLines)) {
            $lineItems[] = $li;
        }
    }
    mysqli_stmt_close($lineStmt);
}

// Tính tổng theo lịch hẹn (cách cũ) chỉ khi là lịch hẹn
$invoiceTotals = $isRental ? ['totalDV' => 0, 'totalTB' => 0, 'total' => (float)$invoice['TONG_TIEN']] : calculateTotalPrice($invoice['ID_LICHHEN']);

// Nếu là đơn thuê trang phục: lấy chi tiết trang phục
$rentalItems = [];
if ($isRental) {
    $riStmt = mysqli_prepare($conn, "
        SELECT ct.SO_LUONG, ct.DON_GIA_AP_DUNG, tp.TEN
        FROM don_thue_trang_phuc_ct ct
        JOIN trang_phuc tp ON ct.ID_TP = tp.ID_TRANG_PHUC
        WHERE ct.ID_TTP = ?");
    if ($riStmt) {
        mysqli_stmt_bind_param($riStmt, 'i', $invoice['ID_TTP']);
        if (mysqli_stmt_execute($riStmt)) {
            $riRes = mysqli_stmt_get_result($riStmt);
            while ($riRes && $r = mysqli_fetch_assoc($riRes)) {
                $rentalItems[] = $r;
            }
        }
        mysqli_stmt_close($riStmt);
    }
}

$stmtTB = mysqli_prepare($conn, "
    SELECT tb.TEN_TB, dgtb.DON_GIA, lhtb.SO_LUONG
    FROM lich_hen_thiet_bi lhtb
    JOIN trang_thiet_bi tb ON lhtb.ID_TB = tb.ID_TB
    JOIN (
    SELECT ID_TB, DON_GIA 
    FROM don_gia_trang_thiet_bi dgtb1 
    WHERE NGAY_GIO = (
        SELECT MAX(NGAY_GIO) 
        FROM don_gia_trang_thiet_bi dgtb2 
        WHERE dgtb2.ID_TB = dgtb1.ID_TB
    )
) dgtb ON tb.ID_TB = dgtb.ID_TB

    WHERE lhtb.ID_LICHHEN = ?");
if ($stmtTB) {
    mysqli_stmt_bind_param($stmtTB, "i", $invoice['ID_LICHHEN']);
    if (!mysqli_stmt_execute($stmtTB)) {
        error_log('equipment query execute failed: ' . mysqli_error($conn));
        $equipments = false;
    } else {
        $equipments = mysqli_stmt_get_result($stmtTB);
    }
    mysqli_stmt_close($stmtTB);
} else {
    error_log('equipment query prepare failed: ' . mysqli_error($conn));
    $equipments = false;
}
?>

<div class="p-8 bg-white rounded-xl shadow-xl max-w-5xl mx-auto">
    <?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!empty($_SESSION['refund_notice'])): ?>
        <div class="mb-4 border <?php echo ($_SESSION['refund_notice_type'] ?? '') === 'success' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800'; ?> px-4 py-3 rounded-lg text-sm font-medium">
            <?= htmlspecialchars($_SESSION['refund_notice']) ?>
        </div>
    <?php
        unset($_SESSION['refund_notice'], $_SESSION['refund_notice_type']);
    endif; ?>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Chi tiết hóa đơn</p>
            <h1 class="mt-1 text-3xl font-extrabold text-slate-900">#<?= $invoice['ID_HD'] ?></h1>
        </div>
        <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>"
           class="inline-flex rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-slate-400 hover:text-slate-800">
            Quay lại danh sách
        </a>
    </div>

    <div class="grid gap-4 md:grid-cols-3 mb-8">
        <div class="p-5 rounded-xl border border-slate-100 bg-slate-50">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Trạng thái hiện tại</p>
            <span class="mt-3 inline-flex rounded-full px-3 py-1 text-sm font-semibold <?= $isPaid ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>">
                <?= $isPaid ? 'Đã thanh toán' : 'Chưa thanh toán' ?>
            </span>
            <p class="text-xs text-slate-500 mt-3">Cập nhật <?= date('d/m/Y H:i', strtotime($invoice['NGAY_GIO'])) ?></p>
        </div>
        <div class="p-5 rounded-xl border border-slate-100 bg-slate-50">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Phương thức</p>
            <p class="mt-3 text-lg font-semibold text-slate-900"><?= htmlspecialchars($methodLabel) ?></p>
            <p class="text-xs text-slate-500 mt-3">Thông tin được đồng bộ khi ghi nhận thanh toán.</p>
        </div>
        <div class="p-5 rounded-xl border border-slate-100 bg-slate-50">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Trạng thái VNPay</p>
            <span class="mt-3 inline-flex rounded-full px-3 py-1 text-sm font-semibold <?= $gatewayBadge['class'] ?>">
                <?= htmlspecialchars($gatewayBadge['label']) ?>
            </span>
            <p class="text-xs text-slate-500 mt-3">
                <?= htmlspecialchars($gatewayBadge['description']) ?>
                <?php if ($gatewayRef): ?><br><span class="text-slate-600">Mã: <?= htmlspecialchars($gatewayRef) ?></span><?php endif; ?>
                <?php if ($gatewayUpdatedAt): ?><br><span class="text-slate-400">Cập nhật <?= htmlspecialchars(date('d/m/Y H:i', strtotime($gatewayUpdatedAt))) ?></span><?php endif; ?>
            </p>
        </div>
    </div>

    <?php if ($isGatewayPending): ?>
        <div class="mb-8 border-l-4 border-amber-500 bg-amber-50 px-5 py-4 rounded">
            <p class="text-sm text-amber-800 font-semibold">VNPay đã ghi nhận giao dịch.</p>
            <p class="text-xs text-amber-700 mt-1">Không cần thao tác thủ công. Invoice sẽ tự cập nhật khi IPN trả về.</p>
        </div>
    <?php elseif (!$isPaid): ?>
        <div class="mb-8 border-l-4 border-indigo-500 bg-indigo-50 px-5 py-4 rounded">
            <p class="text-sm text-indigo-800 font-semibold">Hóa đơn chưa được đánh dấu thanh toán.</p>
            <p class="text-xs text-indigo-700 mt-1">Chỉ xác nhận thủ công khi đã kiểm tra chứng từ chuyển khoản.</p>
        </div>
    <?php endif; ?>

    <!-- Thông tin chính -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 text-gray-700">
        <?php if ($isRental): ?>
            <p><strong>Mã đơn thuê:</strong> <?= (int)$invoice['ID_TTP'] ?></p>
            <p><strong>Ngày lập hóa đơn:</strong> <?= date('d/m/Y H:i', strtotime($invoice['NGAY_GIO'])) ?></p>
            <p><strong>Ngày nhận:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($invoice['NGAY_NHAN']))) ?></p>
            <p><strong>Trả dự kiến:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($invoice['NGAY_TRA_DK']))) ?></p>
            <?php if (!empty($invoice['NGAY_TRA_TT'])): ?>
                <p><strong>Trả thực tế:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($invoice['NGAY_TRA_TT']))) ?></p>
            <?php endif; ?>
            <p><strong>Trạng thái đơn thuê:</strong> <?= htmlspecialchars($invoice['TTP_TRANGTHAI'] ?? '-') ?></p>
            <p><strong>Tiền cọc:</strong> <?= number_format((float)$invoice['TIEN_COC'], 0, ',', '.') ?> VND</p>
            <p><strong>Tổng dự kiến:</strong> <?= number_format((float)$invoice['TONG_TIEN_DU_KIEN'], 0, ',', '.') ?> VND</p>
            <?php if (!empty($invoice['TONG_TIEN_THUC_TE'])): ?>
                <p><strong>Tổng thực tế:</strong> <?= number_format((float)$invoice['TONG_TIEN_THUC_TE'], 0, ',', '.') ?> VND</p>
            <?php endif; ?>
        <?php else: ?>
            <p><strong>Mã lịch hẹn:</strong> <?= (int)$invoice['ID_LICHHEN'] ?></p>
            <p><strong>Ngày lập hóa đơn:</strong> <?= date('d/m/Y H:i', strtotime($invoice['NGAY_GIO'])) ?></p>
            <p><strong>Dịch vụ:</strong> <?= htmlspecialchars($invoice['TEN_DV']) ?></p>
            <p><strong>Thời lượng dịch vụ:</strong> <?= (int)$invoice['thoi_gian'] ?> phút</p>
        <?php endif; ?>
    </div>

    <!-- Khách hàng -->
    <h2 class="text-xl font-bold text-slate-900 mb-2 mt-4">Thông tin khách hàng</h2>
    <div class="bg-gray-50 rounded p-4 text-gray-700 mb-4">
        <p><strong>Họ tên:</strong> <?= htmlspecialchars($invoice['HO_TEN']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($invoice['EMAIL']) ?></p>
        <p><strong>SĐT:</strong> <?= htmlspecialchars($invoice['SDT']) ?></p>
    </div>

    <?php if ($isRental): ?>
        <h2 class="text-xl font-bold text-slate-900 mb-2">Trang phục thuê</h2>
        <?php if (!empty($rentalItems)): ?>
            <table class="w-full border mt-2 rounded-lg overflow-hidden text-sm">
                <thead class="bg-indigo-100">
                    <tr>
                        <th class="p-3 border">Tên trang phục</th>
                        <th class="p-3 border">Số lượng</th>
                        <th class="p-3 border">Đơn giá áp dụng</th>
                        <th class="p-3 border">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rentalSubtotal = 0; foreach ($rentalItems as $ri): $sub = $ri['SO_LUONG'] * $ri['DON_GIA_AP_DUNG']; $rentalSubtotal += $sub; ?>
                        <tr class="bg-white hover:bg-gray-50">
                            <td class="p-3 border"><?= htmlspecialchars($ri['TEN']) ?></td>
                            <td class="p-3 border"><?= (int)$ri['SO_LUONG'] ?></td>
                            <td class="p-3 border"><?= number_format($ri['DON_GIA_AP_DUNG'], 0, ',', '.') ?> VND</td>
                            <td class="p-3 border"><?= number_format($sub, 0, ',', '.') ?> VND</td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="font-bold bg-gray-100">
                        <td colspan="3" class="p-3 border text-right">Tạm tính trang phục</td>
                        <td class="p-3 border text-indigo-700"><?= number_format($rentalSubtotal, 0, ',', '.') ?> VND</td>
                    </tr>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-sm text-gray-600 mb-4">Không có dữ liệu trang phục chi tiết.</p>
        <?php endif; ?>
    <?php else: ?>
        <!-- Dịch vụ -->
        <h2 class="text-xl font-bold text-slate-900 mb-2">Dịch vụ sử dụng</h2>
        <table class="w-full border mt-2 rounded-lg overflow-hidden text-sm">
            <thead class="bg-indigo-100">
                <tr>
                    <th class="p-3 border">Tên dịch vụ</th>
                    <th class="p-3 border">Thời lượng</th>
                    <th class="p-3 border">Đơn giá</th>
                    <th class="p-3 border">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <tr class="bg-white">
                    <td class="p-3 border"><?= htmlspecialchars($invoice['TEN_DV']) ?></td>
                    <td class="p-3 border"><?= (int)$invoice['thoi_gian'] ?> phút</td>
                    <td class="p-3 border"><?= number_format((float)$invoice['GIA_DV'], 0, ',', '.') ?> VND</td>
                    <td class="p-3 border font-semibold text-green-600"><?= number_format($invoiceTotals['totalDV'], 0, ',', '.') ?> VND</td>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Thiết bị -->
    <?php if ($equipments && mysqli_num_rows($equipments) > 0): ?>
        <h2 class="text-xl font-bold text-slate-900 mt-6 mb-2">Thiết bị kèm theo</h2>
        <table class="w-full border mt-2 rounded-lg overflow-hidden text-sm">
            <thead class="bg-indigo-100">
                <tr>
                    <th class="p-3 border">Tên thiết bị</th>
                    <th class="p-3 border">Số lượng</th>
                    <th class="p-3 border">Đơn giá</th>
                    <th class="p-3 border">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($equipments && $eq = mysqli_fetch_assoc($equipments)): ?>
                    <tr class="bg-white hover:bg-gray-50">
                        <td class="p-3 border"><?= htmlspecialchars($eq['TEN_TB']) ?></td>
                        <td class="p-3 border"><?= $eq['SO_LUONG'] ?></td>
                        <td class="p-3 border"><?= number_format($eq['DON_GIA'], 0, ',', '.') ?> VND</td>
                        <td class="p-3 border"><?= number_format($eq['DON_GIA'] * $eq['SO_LUONG'], 0, ',', '.') ?> VND</td>
                    </tr>
                <?php endwhile; ?>
                <tr class="font-bold bg-gray-100">
                    <td colspan="3" class="p-3 border text-right">Tổng thiết bị</td>
                    <td class="p-3 border text-green-600"><?= number_format($invoiceTotals['totalTB'], 0, ',', '.') ?> VND</td>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Invoice line items breakdown (chi_tiet_hoa_don) -->
    <?php if (!empty($lineItems)): ?>
        <h2 class="text-xl font-bold text-slate-900 mt-6 mb-2">Các dòng hóa đơn</h2>
        <table class="w-full border mt-2 rounded-lg overflow-hidden text-sm">
            <thead class="bg-indigo-100">
                <tr>
                    <th class="p-3 border">Mô tả</th>
                    <th class="p-3 border">SL</th>
                    <th class="p-3 border">Đơn giá</th>
                    <th class="p-3 border">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php $computedTotal = 0; foreach ($lineItems as $li): $computedTotal += (float)$li['THANH_TIEN']; ?>
                    <tr class="bg-white">
                        <td class="p-3 border"><?= htmlspecialchars($li['MO_TA']) ?></td>
                        <td class="p-3 border text-center"><?= (int)$li['SO_LUONG'] ?></td>
                        <td class="p-3 border"><?= number_format((float)$li['DON_GIA'], 0, ',', '.') ?> VND</td>
                        <td class="p-3 border font-semibold"><?= number_format((float)$li['THANH_TIEN'], 0, ',', '.') ?> VND</td>
                    </tr>
                <?php endforeach; ?>
                <tr class="bg-gray-100 font-bold">
                    <td colspan="3" class="p-3 border text-right">Tổng theo dòng</td>
                    <td class="p-3 border text-indigo-700"><?= number_format($computedTotal, 0, ',', '.') ?> VND</td>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Tổng cộng -->
    <div class="mt-8 text-right">
        <h2 class="text-2xl font-bold text-indigo-700">Tổng cộng hiển thị: <?= number_format((float)$invoice['TONG_TIEN'], 0, ',', '.') ?> VND</h2>
        <?php if (!$isRental && $invoiceTotals['total'] != (float)$invoice['TONG_TIEN']): ?>
            <p class="text-xs text-amber-600 mt-1">Lưu ý: Tổng tính toán dịch vụ/thiết bị (<?= number_format($invoiceTotals['total'], 0, ',', '.') ?>) khác với tổng ghi hóa đơn (<?= number_format((float)$invoice['TONG_TIEN'], 0, ',', '.') ?>).</p>
        <?php endif; ?>
    </div>

    <!-- Xác nhận thanh toán hoặc trạng thái -->
    <?php if (!$isPaid): ?>
        <form method="POST" action="components/xac_nhan_thanh_toan.php" onsubmit="return confirm('Xác nhận khách hàng đã thanh toán hóa đơn này?');">
            <input type="hidden" name="id_hd" value="<?= $invoice['ID_HD'] ?>">
            <div class="mt-6 p-4 rounded-lg border <?= $isGatewayPending ? 'border-amber-300 bg-amber-50' : 'border-gray-200 bg-gray-50' ?>">
                <p class="text-sm <?= $isGatewayPending ? 'text-amber-800' : 'text-gray-700' ?>">
                    <?= $isGatewayPending
                        ? 'VNPay đã ghi nhận giao dịch và đang chờ IPN. Không cần xác nhận thủ công.'
                        : 'Chỉ xác nhận thủ công khi đã đối chiếu được chứng từ chuyển khoản.' ?>
                </p>
            </div>
            <div class="text-right mt-6 flex flex-col sm:flex-row justify-end gap-4">
                <?php $confirmBtnClasses = 'bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg shadow-md transition font-semibold';
                if ($isGatewayPending) {
                    $confirmBtnClasses .= ' opacity-60 cursor-not-allowed';
                }
                ?>
                <button type="submit" <?= $isGatewayPending ? 'disabled' : '' ?>
                    class="<?= $confirmBtnClasses ?>">
                    <?= $isGatewayPending ? 'Chờ VNPay' : 'Xác nhận đã thanh toán' ?>
                </button>
                <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>"
                    class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg shadow-md transition font-semibold">
                    Quay lại danh sách
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="text-right mt-6 flex flex-col sm:flex-row justify-end gap-4">
            <p class="text-green-600 font-semibold text-base">
                Hóa đơn đã được thanh toán
            </p>
            <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>"
                class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg shadow-md transition font-semibold">
                Quay lại danh sách
            </a>
        </div>

        <?php
        $isVNPay = strtoupper((string)$methodLabel) === 'VNPAY';
        if ($isVNPay): ?>
            <div class="mt-8 p-6 rounded-xl border border-indigo-200 bg-indigo-50">
                <h3 class="text-lg font-semibold text-indigo-800 mb-3">Hoàn tiền VNPay</h3>
                <p class="text-sm text-indigo-700 mb-4">Thực hiện hoàn tiền cho hóa đơn này qua VNPay. Có thể hoàn toàn phần hoặc một phần.</p>
                <form method="POST" action="components/process_refund.php" class="grid gap-4 md:grid-cols-3">
                    <input type="hidden" name="id_hd" value="<?= (int)$invoice['ID_HD'] ?>" />
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kiểu hoàn</label>
                        <select name="refund_type" class="border border-gray-300 rounded-lg px-3 py-2 w-full">
                            <option value="full">Hoàn toàn bộ</option>
                            <option value="partial">Hoàn một phần</option>
                        </select>
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Số tiền (VND)</label>
                        <input type="number" name="amount" min="1000" step="1000"
                               value="<?= (int)$invoice['TONG_TIEN'] ?>"
                               class="border border-gray-300 rounded-lg px-3 py-2 w-full" required />
                        <p class="text-xs text-gray-500 mt-1">Tối đa: <?= number_format((float)$invoice['TONG_TIEN'], 0, ',', '.') ?> VND</p>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lý do</label>
                        <input type="text" name="reason" maxlength="200" placeholder="Ghi rõ lý do hoàn tiền"
                               class="border border-gray-300 rounded-lg px-3 py-2 w-full" />
                    </div>
                    <div class="md:col-span-3 text-right">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-semibold shadow">
                            Gửi yêu cầu hoàn tiền
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>


</div>
