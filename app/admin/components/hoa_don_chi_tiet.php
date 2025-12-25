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

$referer = $_SERVER['HTTP_REFERER'] ?? '';
$backUrl = 'admin_dashboard.php?page=payments';

if (strpos($referer, 'manager_dashboard.php') !== false || ($_GET['source'] ?? '') === 'manager') {
    $backUrl = 'manager_dashboard.php?page=invoices';
}

function calculateTotalPrice($idLichHen)
{
    global $conn;
    $totalDV = 0;
    $totalTB = 0;

    $queryDV = "SELECT dv.thoi_gian,
               (SELECT DON_GIA 
                FROM don_gia_dich_vu dg
                WHERE dg.ID_DV = dv.ID_DV
                ORDER BY NGAY_GIO DESC
                LIMIT 1) AS DON_GIA
        FROM lich_hen lh 
        JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV 
        WHERE lh.ID_LICHHEN = ?";
    $stmtDV = mysqli_prepare($conn, $queryDV);
    if (!$stmtDV) {
        return ['totalDV' => 0, 'totalTB' => 0, 'total' => 0];
    }
    mysqli_stmt_bind_param($stmtDV, 'i', $idLichHen);
    mysqli_stmt_execute($stmtDV);
    $resultDV = mysqli_stmt_get_result($stmtDV);

    if ($resultDV && $rowDV = mysqli_fetch_assoc($resultDV)) {
        // Đơn giá là giá TRỌN GÓI, không tính theo giờ
        $totalDV = $rowDV['DON_GIA'];
    }
    mysqli_stmt_close($stmtDV);

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
        mysqli_stmt_execute($stmtTB);
        $resultTB = mysqli_stmt_get_result($stmtTB);
        while ($resultTB && $rowTB = mysqli_fetch_assoc($resultTB)) {
            $totalTB += $rowTB['DON_GIA'] * $rowTB['SO_LUONG'];
        }
        mysqli_stmt_close($stmtTB);
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
        lh.ID_LICHHEN, dv.TEN_DV, dv.thoi_gian,
        (SELECT DON_GIA FROM don_gia_dich_vu WHERE ID_DV = dv.ID_DV ORDER BY NGAY_GIO DESC LIMIT 1) AS GIA_DV,
        ttp.ID_TTP, ttp.NGAY_NHAN, ttp.NGAY_TRA_DK, ttp.NGAY_TRA_TT, ttp.TRANG_THAI AS TTP_TRANGTHAI,
        ttp.TIEN_COC, ttp.TONG_TIEN_DU_KIEN, ttp.TONG_TIEN_THUC_TE
    FROM hoa_don hd
    LEFT JOIN lich_hen lh ON hd.ID_LICHHEN = lh.ID_LICHHEN
    LEFT JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
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
    echo "<script>alert('Không thể tải dữ liệu hóa đơn.'); history.back();</script>";
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
$isRental = !empty($invoice['ID_TTP']);

// Thu thập chi tiết đa dịch vụ từ BOOKING_ITEM (nếu là lịch hẹn)
$servicesBI = [];
$serviceSubtotal = 0.0;
$travelFee = 0.0;
if (!$isRental && !empty($invoice['ID_LICHHEN'])) {
    $biStmt = mysqli_prepare($conn, "SELECT dv.TEN_DV, bi.DON_GIA, bi.SO_LUONG FROM BOOKING_ITEM bi JOIN DICH_VU dv ON dv.ID_DV = bi.REF_ID WHERE bi.ID_LICHHEN = ? AND bi.ITEM_TYPE = 'service' ORDER BY bi.ID_ITEM ASC");
    if ($biStmt) {
        mysqli_stmt_bind_param($biStmt, 'i', $invoice['ID_LICHHEN']);
        mysqli_stmt_execute($biStmt);
        $biRes = mysqli_stmt_get_result($biStmt);
        while ($biRes && ($row = mysqli_fetch_assoc($biRes))) {
            $row['DON_GIA'] = (float)($row['DON_GIA'] ?? 0);
            $row['SO_LUONG'] = (int)($row['SO_LUONG'] ?? 1);
            $row['THANH_TIEN'] = $row['DON_GIA'] * $row['SO_LUONG'];
            $servicesBI[] = $row;
            $serviceSubtotal += $row['THANH_TIEN'];
        }
        mysqli_stmt_close($biStmt);
    }
    $tfStmt = mysqli_prepare($conn, "SELECT COALESCE(TRAVEL_FEE,0) FROM lich_hen WHERE ID_LICHHEN = ?");
    if ($tfStmt) {
        mysqli_stmt_bind_param($tfStmt, 'i', $invoice['ID_LICHHEN']);
        mysqli_stmt_execute($tfStmt);
        mysqli_stmt_bind_result($tfStmt, $travelFee);
        mysqli_stmt_fetch($tfStmt);
        mysqli_stmt_close($tfStmt);
        $travelFee = (float)$travelFee;
    }
}

$lineItems = [];
$lineStmt = mysqli_prepare($conn, "SELECT MO_TA, SO_LUONG, DON_GIA, THANH_TIEN FROM chi_tiet_hoa_don WHERE ID_HD = ? ORDER BY ID_CTHD ASC");
if ($lineStmt) {
    mysqli_stmt_bind_param($lineStmt, 'i', $invoice['ID_HD']);
    mysqli_stmt_execute($lineStmt);
    $resLines = mysqli_stmt_get_result($lineStmt);
    while ($resLines && $li = mysqli_fetch_assoc($resLines)) {
        $lineItems[] = $li;
    }
    mysqli_stmt_close($lineStmt);
}

$invoiceTotals = $isRental ? ['totalDV' => 0, 'totalTB' => 0, 'total' => (float)$invoice['TONG_TIEN']] : calculateTotalPrice($invoice['ID_LICHHEN']);

$rentalItems = [];
if ($isRental) {
    $riStmt = mysqli_prepare($conn, "
        SELECT ct.SO_LUONG, ct.DON_GIA_AP_DUNG, tp.TEN
        FROM don_thue_trang_phuc_ct ct
        JOIN trang_phuc tp ON ct.ID_TP = tp.ID_TRANG_PHUC
        WHERE ct.ID_TTP = ?");
    if ($riStmt) {
        mysqli_stmt_bind_param($riStmt, 'i', $invoice['ID_TTP']);
        mysqli_stmt_execute($riStmt);
        $riRes = mysqli_stmt_get_result($riStmt);
        while ($riRes && $r = mysqli_fetch_assoc($riRes)) {
            $rentalItems[] = $r;
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
    mysqli_stmt_execute($stmtTB);
    $equipments = mysqli_stmt_get_result($stmtTB);
    mysqli_stmt_close($stmtTB);
} else {
    $equipments = false;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hóa đơn #<?= $invoice['ID_HD'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { background: white !important; }
            .no-print { display: none !important; }
            .invoice-container { max-width: 100% !important; box-shadow: none !important; }
            * { box-shadow: none !important; }
            /* Ẩn sidebar và các phần không cần in */
            .sidebar, aside, nav, .navbar { display: none !important; }
            /* Chỉ hiển thị khu vực hóa đơn để tránh trắng trang khi nằm trong dashboard */
            body * { visibility: hidden; }
            #invoice-print-root, #invoice-print-root * { visibility: visible; }
        }
    </style>
</head>
<body class="bg-gray-50 py-8">
<div id="invoice-print-root" class="invoice-container max-w-3xl mx-auto bg-white rounded-lg shadow-lg p-8">
    <!-- Studio Header -->
    <div class="text-center mb-8 pb-8 border-b">
        <div class="mb-4">
            <img src="../../public/images/StygianBlueLogo.png" alt="Stygian Blue Studio" class="w-16 h-16 mx-auto">
        </div>
        <h1 class="text-2xl font-bold text-gray-900">STYGIAN BLUE STUDIO</h1>
        <p class="text-sm text-gray-600 mt-1">Dịch vụ chuyên nghiệp</p>
    </div>

    <!-- Header -->
    <div class="flex justify-between items-start mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">HÓA ĐƠN #<?= $invoice['ID_HD'] ?></h1>
            <p class="text-sm text-gray-500 mt-1">Ngày <?= date('d/m/Y H:i', strtotime($invoice['NGAY_GIO'])) ?></p>
        </div>
        <div class="flex gap-2 no-print">
            <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition text-sm font-semibold">
                In
            </button>
            <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 transition text-sm font-semibold">
                Quay lại
            </a>
        </div>
    </div>
    <!-- Thông tin khách hàng -->
    <div class="mb-8">
        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Khách hàng</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-xs text-gray-600 uppercase">Họ tên</p>
                <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($invoice['HO_TEN']) ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-600 uppercase">Email</p>
                <p class="text-sm text-gray-900"><?= htmlspecialchars($invoice['EMAIL']) ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-600 uppercase">Số điện thoại</p>
                <p class="text-sm text-gray-900"><?= htmlspecialchars($invoice['SDT']) ?></p>
            </div>
        </div>
    </div>

    <!-- Chi tiết dịch vụ -->
    <?php if (!$isRental): ?>
        <div class="mb-8">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Dịch vụ</h3>
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-100 border">
                        <th class="p-3 text-left">Hạng mục</th>
                        <th class="p-3 text-center">SL</th>
                        <th class="p-3 text-right">Đơn giá</th>
                        <th class="p-3 text-right">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($servicesBI)): ?>
                        <?php foreach ($servicesBI as $svc): ?>
                            <tr class="border">
                                <td class="p-3">Dịch vụ: <?= htmlspecialchars($svc['TEN_DV']) ?></td>
                                <td class="p-3 text-center"><?= (int)$svc['SO_LUONG'] ?></td>
                                <td class="p-3 text-right"><?= number_format((float)$svc['DON_GIA'], 0, ',', '.') ?></td>
                                <td class="p-3 text-right font-semibold"><?= number_format((float)$svc['THANH_TIEN'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($travelFee > 0): ?>
                            <tr class="border">
                                <td class="p-3">Phụ phí di chuyển</td>
                                <td class="p-3 text-center">1</td>
                                <td class="p-3 text-right"><?= number_format($travelFee, 0, ',', '.') ?></td>
                                <td class="p-3 text-right font-semibold"><?= number_format($travelFee, 0, ',', '.') ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php else: ?>
                        <tr class="border">
                            <td class="p-3"><?= htmlspecialchars($invoice['TEN_DV'] ?? 'Dịch vụ') ?></td>
                            <td class="p-3 text-center">1</td>
                            <td class="p-3 text-right"><?= number_format((float)($invoice['GIA_DV'] ?? 0), 0, ',', '.') ?></td>
                            <td class="p-3 text-right font-semibold"><?= number_format((float)($invoiceTotals['totalDV'] ?? ($invoice['GIA_DV'] ?? 0)), 0, ',', '.') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="mb-8">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Trang phục thuê</h3>
            <?php if (!empty($rentalItems)): ?>
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="bg-gray-100 border">
                            <th class="p-3 text-left">Tên trang phục</th>
                            <th class="p-3 text-center">SL</th>
                            <th class="p-3 text-right">Đơn giá</th>
                            <th class="p-3 text-right">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rentalSubtotal = 0; foreach ($rentalItems as $ri): $sub = $ri['SO_LUONG'] * $ri['DON_GIA_AP_DUNG']; $rentalSubtotal += $sub; ?>
                            <tr class="border">
                                <td class="p-3"><?= htmlspecialchars($ri['TEN']) ?></td>
                                <td class="p-3 text-center"><?= (int)$ri['SO_LUONG'] ?></td>
                                <td class="p-3 text-right"><?= number_format($ri['DON_GIA_AP_DUNG'], 0, ',', '.') ?></td>
                                <td class="p-3 text-right"><?= number_format($sub, 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Thiết bị -->
    <?php if ($equipments && mysqli_num_rows($equipments) > 0): ?>
        <div class="mb-8">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Thiết bị kèm theo</h3>
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-100 border">
                        <th class="p-3 text-left">Thiết bị</th>
                        <th class="p-3 text-center">SL</th>
                        <th class="p-3 text-right">Đơn giá</th>
                        <th class="p-3 text-right">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($equipments && $eq = mysqli_fetch_assoc($equipments)): ?>
                        <tr class="border">
                            <td class="p-3"><?= htmlspecialchars($eq['TEN_TB']) ?></td>
                            <td class="p-3 text-center"><?= $eq['SO_LUONG'] ?></td>
                            <td class="p-3 text-right"><?= number_format($eq['DON_GIA'], 0, ',', '.') ?></td>
                            <td class="p-3 text-right"><?= number_format($eq['DON_GIA'] * $eq['SO_LUONG'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Chi tiết hóa đơn -->
    <?php if (!empty($lineItems)): ?>
        <div class="mb-8">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-100 border">
                        <th class="p-3 text-left">Mô tả</th>
                        <th class="p-3 text-center">SL</th>
                        <th class="p-3 text-right">Đơn giá</th>
                        <th class="p-3 text-right">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lineItems as $li): ?>
                        <tr class="border">
                            <td class="p-3"><?= htmlspecialchars($li['MO_TA']) ?></td>
                            <td class="p-3 text-center"><?= (int)$li['SO_LUONG'] ?></td>
                            <td class="p-3 text-right"><?= number_format((float)$li['DON_GIA'], 0, ',', '.') ?></td>
                            <td class="p-3 text-right"><?= number_format((float)$li['THANH_TIEN'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Tổng cộng -->
    <div class="mb-8 pt-6 border-t-2">
        <div class="text-right">
            <div class="text-xl font-bold text-gray-900">
                Tổng cộng: <span class="text-blue-600"><?= number_format((float)$invoice['TONG_TIEN'], 0, ',', '.') ?> VND</span>
            </div>
        </div>
    </div>

    <!-- QR Code (nếu chưa thanh toán) -->
    <?php if (!$isPaid): ?>
        <div class="pt-6 border-t mb-6">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-4">Thông tin chuyển khoản</h3>
            <div class="grid grid-cols-2 gap-6">
                <div class="text-sm">
                    <p class="text-gray-600 mb-2"><strong>Ngân hàng:</strong> Vietcombank (VCB)</p>
                    <p class="text-gray-600 mb-2"><strong>Tài khoản:</strong> Stygian Blue</p>
                    <p class="text-gray-600 mb-2"><strong>Nội dung:</strong> Thanh toan hoa don #<?= (int)$invoice['ID_HD'] ?></p>
                    <p class="text-gray-600 mb-2"><strong>Số tiền:</strong> <span class="font-semibold text-blue-600"><?= number_format((float)$invoice['TONG_TIEN'], 0, ',', '.') ?> VND</span></p>
                </div>
                <div class="text-center">
                    <?php
                    $bankCode = '970441';
                    $accountNo = '1023067681';
                    $amount = (int)$invoice['TONG_TIEN'];
                    $description = 'Thanh toan hoa don #' . (int)$invoice['ID_HD'];
                    $qrContent = "00020126360014com.vietqr011200{$bankCode}010{$accountNo}0208QTTQ001212305060000{$amount}0368440010A000000072701280006970441070869170030813{$description}6304D3C4";
                    $qrImg = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrContent);
                    ?>
                    <img src="<?= htmlspecialchars($qrImg) ?>" alt="QR Code" class="w-32 h-32 mx-auto border border-gray-300 p-2">
                </div>
            </div>
        </div>

        <!-- Upload minh chứng form -->
        <div class="no-print mb-8 p-6 bg-blue-50 border border-blue-200 rounded-lg">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-4">Upload minh chứng thanh toán</h3>
            <form method="POST" action="components/upload_minh_chung_admin.php" enctype="multipart/form-data" onsubmit="return confirm('Lưu minh chứng và đánh dấu hóa đơn là đã thanh toán?');">
                <input type="hidden" name="id_hd" value="<?= (int)$invoice['ID_HD'] ?>">
                
                <div class="space-y-4">
                    <!-- File upload -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Ảnh minh chứng (jpg/png/pdf)</label>
                        <input type="file" name="minh_chung" accept="image/*,.pdf" required
                               class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:bg-blue-600 file:text-white hover:file:bg-blue-700">
                        <p class="text-xs text-gray-500 mt-1">Tối đa 5MB. Chụp hình biên lai hoặc screenshot app ngân hàng.</p>
                    </div>

                    <!-- Reference number (optional) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mã tham chiếu (tuỳ chọn)</label>
                        <input type="text" name="ma_tham_chieu" maxlength="50" placeholder="Ví dụ: 12345678"
                               class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Ghi chú (tuỳ chọn)</label>
                        <textarea name="ghi_chu" maxlength="200" rows="2" placeholder="VD: Khách gọi điện xác nhận đã chuyển..."
                                  class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <!-- Action buttons -->
                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-6 py-2.5 rounded-lg font-semibold shadow-md transition">
                            Xác nhận đã thanh toán
                        </button>
                        <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>"
                            class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2.5 rounded-lg font-semibold shadow-md transition">
                            Quay lại
                        </a>
                    </div>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="mt-8 pt-6 border-t text-center text-xs text-gray-600">
        <p>Stygian Blue © 2025</p>
    </div>
</div>
</body>
</html>
