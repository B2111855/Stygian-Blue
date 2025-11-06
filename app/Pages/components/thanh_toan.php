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

// Thông báo trạng thái thanh toán (nếu có)
$flashNotice = $_SESSION['payment_notice'] ?? '';
$flashType = $_SESSION['payment_notice_type'] ?? '';
if ($flashNotice !== '') {
    unset($_SESSION['payment_notice'], $_SESSION['payment_notice_type']);
}

// Đếm tổng hóa đơn
$countQuery = "
    SELECT COUNT(*) as total
    FROM hoa_don h
    JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
    WHERE l.ID_TK = '$userId'
";
$countResult = mysqli_query($conn, $countQuery);
$totalRows = mysqli_fetch_assoc($countResult)['total'] ?? 0;
$totalPages = (int) ceil($totalRows / $limit);

// Truy vấn phân trang
$sql = "
    SELECT h.ID_HD, h.NGAY_GIO, h.TONG_TIEN, h.TRANGTHAI_THANHTOAN, h.YEU_CAU_XAC_NHAN,
           h.PHUONGTHUC_THANHTOAN, k.HO_TEN, k.EMAIL, k.SDT, d.TEN_DV,
           tt.TRANG_THAI AS VNPAY_TRANG_THAI, tt.MA_THAM_CHIEU, tt.SO_TIEN AS VNPAY_SO_TIEN,
           tt.CREATED_AT AS VNPAY_CREATED_AT
    FROM hoa_don h
    JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
    JOIN khach_hang k ON l.ID_TK = k.ID_TK
    JOIN dich_vu d ON l.ID_DV = d.ID_DV
    LEFT JOIN (
        SELECT t1.ID_HD, t1.TRANG_THAI, t1.MA_THAM_CHIEU, t1.SO_TIEN, t1.CREATED_AT
        FROM thanh_toan_truc_tuyen t1
        JOIN (
            SELECT ID_HD, MAX(CREATED_AT) AS max_created
            FROM thanh_toan_truc_tuyen
            WHERE GATEWAY = 'vnpay'
            GROUP BY ID_HD
        ) latest ON latest.ID_HD = t1.ID_HD AND latest.max_created = t1.CREATED_AT
        WHERE t1.GATEWAY = 'vnpay'
    ) tt ON tt.ID_HD = h.ID_HD
    WHERE l.ID_TK = '$userId'
    ORDER BY h.NGAY_GIO DESC
    LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $sql);
?>

<div class="bg-white p-6 rounded-xl shadow-lg">
    <h2 class="text-3xl font-extrabold text-indigo-700 mb-4 text-center">Danh sách hóa đơn của bạn</h2>

    <?php if ($flashNotice !== ''): ?>
        <div class="mb-6 rounded-lg border px-4 py-3 text-sm <?php echo $flashType === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-700'; ?>">
            <?php echo htmlspecialchars($flashNotice); ?>
        </div>
    <?php endif; ?>

    <?php if ($result && mysqli_num_rows($result) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-center border border-gray-200 shadow-sm rounded-md overflow-hidden">
                <thead class="bg-indigo-700 text-white">
                    <tr>
                        <th class="p-4">Mã HĐ</th>
                        <th class="p-4">Dịch Vụ</th>
                        <th class="p-4">Thời Gian</th>
                        <th class="p-4">Tổng Tiền</th>
                        <th class="p-4">Thanh Toán</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($row = mysqli_fetch_assoc($result)):
                        $qrData = "STK:1234567890|TONGTIEN:" . $row['TONG_TIEN'] . "|ND:THANHTOAN_HD_" . $row['ID_HD'];
                        $qrImg = "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($qrData) . "&size=180x180";
                        $trangthai = trim($row['TRANGTHAI_THANHTOAN']);
                        $yeuCau = $row['YEU_CAU_XAC_NHAN'];
                        $vnpayStatus = $row['VNPAY_TRANG_THAI'] ?? null;
                        $vnpayReference = $row['MA_THAM_CHIEU'] ?? null;
                        $vnpayCreated = $row['VNPAY_CREATED_AT'] ?? null;
                    ?>
                    <tr class="hover:bg-indigo-50 transition-all duration-200">
                        <td class="p-3 font-semibold text-gray-800">#<?= $row['ID_HD'] ?></td>
                        <td class="p-3 text-gray-600"><?= htmlspecialchars($row['TEN_DV']) ?></td>
                        <td class="p-3 text-gray-600"><?= htmlspecialchars($row['NGAY_GIO']) ?></td>
                        <td class="p-3 text-blue-600 font-bold text-base">
                            <?= number_format($row['TONG_TIEN'], 0, ',', '.') ?> VNĐ
                        </td>
                        <td class="p-3">
                            <?php if ($trangthai === 'Đã thanh toán'): ?>
                                <span class="inline-block bg-green-500 text-white px-4 py-1.5 rounded-full shadow">Đã thanh toán</span>
                            <?php elseif ((int)$yeuCau === 1): ?>
                                <span class="inline-block bg-yellow-400 text-black px-4 py-1.5 rounded-full shadow">Chờ xác minh</span>
                            <?php else: ?>
                                <button class="bg-blue-600 text-white px-4 py-1.5 rounded-lg shadow hover:bg-blue-700 transition" onclick="togglePaymentOptions(<?= $row['ID_HD'] ?>)">
                                    Chọn phương thức
                                </button>
                                <div id="payment-options-<?= $row['ID_HD'] ?>" class="payment-options mt-3 hidden animate-fade-in">
                                    <div class="bg-indigo-50 border border-indigo-200 p-4 rounded-md space-y-4">
                                        <div>
                                            <h3 class="text-lg font-semibold text-indigo-800 mb-2">Chuyển khoản ngân hàng</h3>
                                            <p class="mb-2 font-semibold text-gray-700">Chuyển đến STK: <span class="text-indigo-800">1234567890</span></p>
                                            <img src="<?= $qrImg ?>" alt="QR Code" class="mx-auto mb-2 w-44 h-44 rounded-md border">
                                            <p class="text-sm text-gray-700">Nội dung: <span class="font-semibold">THANHTOAN_HD_<?= $row['ID_HD'] ?></span></p>
                                            <form method="POST" action="../Controller/process_payment.php" class="mt-3">
                                                <input type="hidden" name="invoice_id" value="<?= $row['ID_HD'] ?>">
                                                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition">Tôi đã chuyển tiền</button>
                                            </form>
                                        </div>
                                        <div class="border-t border-indigo-200 pt-4">
                                            <h3 class="text-lg font-semibold text-indigo-800 mb-2">Thanh toán qua VNPAY</h3>
                                            <?php if ($vnpayStatus === 'pending'): ?>
                                                <p class="text-sm text-yellow-700 bg-yellow-50 border border-yellow-200 rounded px-3 py-2">
                                                    Đang chờ xác nhận từ VNPAY. Mã tham chiếu: <?= htmlspecialchars($vnpayReference) ?>.
                                                </p>
                                            <?php elseif ($vnpayStatus === 'failed'): ?>
                                                <p class="text-sm text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2 mb-3">
                                                    Giao dịch VNPAY gần nhất không thành công. Vui lòng thử lại.
                                                </p>
                                            <?php elseif ($vnpayStatus === 'success'): ?>
                                                <p class="text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2 mb-3">
                                                    Giao dịch VNPAY gần nhất đã thành công vào <?= htmlspecialchars($vnpayCreated) ?>.
                                                </p>
                                            <?php endif; ?>

                                            <form method="POST" action="../Controller/vnpay_create_payment.php" class="space-y-3">
                                                <input type="hidden" name="invoice_id" value="<?= $row['ID_HD'] ?>">
                                                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition">
                                                    Thanh toán với VNPAY
                                                </button>
                                            </form>
                                            <p class="text-xs text-gray-500 mt-2">
                                                Bạn sẽ được chuyển tới cổng thanh toán VNPAY để hoàn tất giao dịch.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="mt-6 flex justify-center gap-2">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?p=<?= $i ?>" class="px-4 py-2 border rounded-lg text-sm font-semibold transition-all <?= $i == $page ? 'bg-indigo-600 text-white shadow-md' : 'bg-white text-indigo-700 hover:bg-indigo-100' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p class="text-center text-red-600 font-semibold mt-6">Không có hóa đơn nào.</p>
    <?php endif; ?>
</div>

<script>
function togglePaymentOptions(id) {
    const div = document.getElementById('payment-options-' + id);
    if (div) {
        div.style.display = (div.style.display === 'none' || div.style.display === '') ? 'block' : 'none';
    }
}
</script>

<?php $conn->close(); ?>