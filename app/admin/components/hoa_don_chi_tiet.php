<?php
include '../../database/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_GET['id_hd'])) {
    echo "<script>alert('Thiếu mã hóa đơn.'); history.back();</script>";
    exit;
}

$id_hd = (int)$_GET['id_hd'];

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
    mysqli_stmt_bind_param($stmtDV, 'i', $idLichHen);
    mysqli_stmt_execute($stmtDV);
    $resultDV = mysqli_stmt_get_result($stmtDV);

    if ($resultDV && $rowDV = mysqli_fetch_assoc($resultDV)) {
        $soPhut = (int)$rowDV['thoi_gian'];
        $soGio = $soPhut / 60;
        $totalDV = $soGio * $rowDV['DON_GIA'];
    }

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
    mysqli_stmt_bind_param($stmtTB, 'i', $idLichHen);
    mysqli_stmt_execute($stmtTB);
    $resultTB = mysqli_stmt_get_result($stmtTB);

    while ($rowTB = mysqli_fetch_assoc($resultTB)) {
        $totalTB += $rowTB['DON_GIA'] * $rowTB['SO_LUONG'];
    }

    return [
        'totalDV' => $totalDV,
        'totalTB' => $totalTB,
        'total' => $totalDV + $totalTB
    ];
}


$stmt = mysqli_prepare($conn, "
    SELECT hd.ID_HD, hd.NGAY_GIO, hd.TRANGTHAI_THANHTOAN,
            hd.YEU_CAU_XAC_NHAN,
           kh.HO_TEN, kh.EMAIL, kh.SDT,
           dv.TEN_DV, dv.thoi_gian, dgdv.DON_GIA AS GIA_DV, lh.ID_LICHHEN
    FROM hoa_don hd
    JOIN lich_hen lh ON hd.ID_LICHHEN = lh.ID_LICHHEN
    JOIN tai_khoan kh ON lh.ID_TK = kh.ID_TK
    JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
    JOIN don_gia_dich_vu dgdv ON dv.ID_DV = dgdv.ID_DV
    WHERE hd.ID_HD = ?");

mysqli_stmt_bind_param($stmt, "i", $id_hd);
mysqli_stmt_execute($stmt);
$invoice = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$invoice) {
    echo "<script>alert('Không tìm thấy hóa đơn.'); history.back();</script>";
    exit;
}

$invoiceTotals = calculateTotalPrice($invoice['ID_LICHHEN']);

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

mysqli_stmt_bind_param($stmtTB, "i", $invoice['ID_LICHHEN']);
mysqli_stmt_execute($stmtTB);
$equipments = mysqli_stmt_get_result($stmtTB);
?>

<div class="p-8 bg-white rounded-xl shadow-xl max-w-4xl mx-auto">
    <h1 class="text-3xl font-extrabold text-indigo-700 mb-6">🧾 Hóa đơn #<?= $invoice['ID_HD'] ?></h1>

    <!-- Thông tin chính -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 text-gray-700">
        <p><strong>Mã lịch hẹn:</strong> <?= $invoice['ID_LICHHEN'] ?></p>
        <p><strong>Ngày lập:</strong> <?= $invoice['NGAY_GIO'] ?></p>
        <p>
            <strong>Trạng thái:</strong>
            <?php if ($invoice['TRANGTHAI_THANHTOAN'] === 'Đã thanh toán'): ?>
                <span class="text-green-600 font-semibold">Đã thanh toán</span>
            <?php else: ?>
                <span class="text-red-500 font-semibold">Chưa thanh toán</span>
            <?php endif; ?>
        </p>
    </div>

    <!-- Khách hàng -->
    <h2 class="text-xl font-bold text-gray-800 mb-2 mt-4">👤 Thông tin khách hàng</h2>
    <div class="bg-gray-50 rounded p-4 text-gray-700 mb-4">
        <p><strong>Họ tên:</strong> <?= htmlspecialchars($invoice['HO_TEN']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($invoice['EMAIL']) ?></p>
        <p><strong>SĐT:</strong> <?= htmlspecialchars($invoice['SDT']) ?></p>
    </div>

    <!-- Dịch vụ -->
    <h2 class="text-xl font-bold text-gray-800 mb-2">📸 Dịch vụ sử dụng</h2>
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
                <td class="p-3 border"><?= $invoice['TEN_DV'] ?></td>
                <td class="p-3 border"><?= $invoice['thoi_gian'] ?> phút</td>
                <td class="p-3 border"><?= number_format($invoice['GIA_DV'], 0, ',', '.') ?> VND</td>
                <td class="p-3 border font-semibold text-green-600"><?= number_format($invoiceTotals['totalDV'], 0, ',', '.') ?> VND</td>
            </tr>
        </tbody>
    </table>

    <!-- Thiết bị -->
    <?php if (mysqli_num_rows($equipments) > 0): ?>
        <h2 class="text-xl font-bold text-gray-800 mt-6 mb-2">📦 Thiết bị kèm theo</h2>
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
                <?php while ($eq = mysqli_fetch_assoc($equipments)): ?>
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

    <!-- Tổng cộng -->
    <div class="mt-8 text-right">
        <h2 class="text-2xl font-bold text-indigo-700">Tổng cộng: <?= number_format($invoiceTotals['total'], 0, ',', '.') ?> VND</h2>
    </div>

    <!-- Xác nhận thanh toán hoặc trạng thái -->
<?php if ($invoice['TRANGTHAI_THANHTOAN'] === 'Chưa thanh toán' && $invoice['YEU_CAU_XAC_NHAN'] == 1): ?>
    <form method="POST" action="components/xac_nhan_thanh_toan.php" onsubmit="return confirm('Xác nhận khách hàng đã thanh toán hóa đơn này?');">
        <input type="hidden" name="id_hd" value="<?= $invoice['ID_HD'] ?>">
        <div class="text-right mt-6 flex flex-col sm:flex-row justify-end gap-4">
            <button type="submit"
                class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg shadow-md transition font-semibold">
                ✅ Xác nhận đã thanh toán
            </button>
            <a href="admin_dashboard.php?page=payments"
                class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg shadow-md transition font-semibold">
                🔙 Quay lại danh sách
            </a>
        </div>
    </form>

<?php elseif ($invoice['TRANGTHAI_THANHTOAN'] === 'Chưa thanh toán' && $invoice['YEU_CAU_XAC_NHAN'] != 1): ?>
    <div class="text-right mt-6 flex flex-col sm:flex-row justify-end gap-4">
        <p class="text-yellow-600 font-semibold text-lg flex items-center gap-2">
            ⚠️ Khách hàng chưa gửi yêu cầu xác nhận.
        </p>
        <a href="admin_dashboard.php?page=payments"
            class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg shadow-md transition font-semibold">
            🔙 Quay lại danh sách
        </a>
    </div>

<?php else: ?>
    <div class="text-right mt-6 flex flex-col sm:flex-row justify-end gap-4">
        <p class="text-green-600 font-bold text-lg flex items-center gap-2">
            ✅ Hóa đơn đã được thanh toán
        </p>
        <a href="admin_dashboard.php?page=payments"
            class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg shadow-md transition font-semibold">
            🔙 Quay lại danh sách
        </a>
    </div>
<?php endif; ?>


</div>
