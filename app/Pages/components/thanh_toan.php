<?php
include '../../../database/config.php';

if (!isset($_SESSION['ID_TK'])) {
    echo "<p class='text-red-500 font-bold'>Vui lòng đăng nhập để xem hóa đơn.</p>";
    exit;
}

$userId = mysqli_real_escape_string($conn, $_SESSION['ID_TK']);
$limit = 5;
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $limit;

// Đếm tổng hóa đơn
$countQuery = "
    SELECT COUNT(*) as total
    FROM hoa_don h
    JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
    WHERE l.ID_TK = '$userId'
";
$countResult = mysqli_query($conn, $countQuery);
$totalRows = mysqli_fetch_assoc($countResult)['total'] ?? 0;
$totalPages = ceil($totalRows / $limit);

// Truy vấn phân trang
$sql = "
    SELECT h.ID_HD, h.NGAY_GIO, h.TONG_TIEN, h.TRANGTHAI_THANHTOAN, h.YEU_CAU_XAC_NHAN,
           k.HO_TEN, d.TEN_DV
    FROM hoa_don h
    JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
    JOIN khach_hang k ON l.ID_TK = k.ID_TK
    JOIN dich_vu d ON l.ID_DV = d.ID_DV
    WHERE l.ID_TK = '$userId'
    ORDER BY h.NGAY_GIO DESC
    LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $sql);
?>

<div class="bg-white p-6 rounded-xl shadow-lg">
    <h2 class="text-3xl font-extrabold text-indigo-700 mb-6 text-center">🧾 Danh sách hóa đơn của bạn</h2>

    <?php if ($result && mysqli_num_rows($result) > 0): ?>
        <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-center border border-gray-300 shadow-sm rounded-md overflow-hidden">
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
                <?php while($row = mysqli_fetch_assoc($result)): 
                    $qrData = "STK:1234567890|TONGTIEN:" . $row['TONG_TIEN'] . "|ND:THANHTOAN_HD_" . $row['ID_HD'];
                    $qrImg = "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($qrData) . "&size=180x180";
                    $trangthai = trim($row['TRANGTHAI_THANHTOAN']);
                    $yeuCau = $row['YEU_CAU_XAC_NHAN'];
                ?>
                <tr class="hover:bg-indigo-50 transition-all duration-200">
                    <td class="p-3 font-semibold text-gray-800">#<?= $row['ID_HD'] ?></td>
                    <td class="p-3 text-gray-600"><?= htmlspecialchars($row['TEN_DV']) ?></td>
                    <td class="p-3 text-gray-600"><?= $row['NGAY_GIO'] ?></td>
                    <td class="p-3 text-blue-600 font-bold text-base">
                        <?= number_format($row['TONG_TIEN'], 0, ',', '.') ?> VNĐ
                    </td>
                    <td class="p-3">
                        <?php if ($trangthai === 'Đã thanh toán'): ?>
                            <span class="inline-block bg-green-500 text-white px-4 py-1.5 rounded-full shadow">Đã thanh toán</span>
                        <?php elseif ($yeuCau == 1): ?>
                            <span class="inline-block bg-yellow-400 text-black px-4 py-1.5 rounded-full shadow">Chờ xác minh</span>
                        <?php else: ?>
                            <button class="bg-blue-600 text-white px-4 py-1.5 rounded-lg shadow hover:bg-blue-700 transition" onclick="togglePaymentOptions(<?= $row['ID_HD'] ?>)">Thanh Toán</button>
                            <div id="payment-options-<?= $row['ID_HD'] ?>" class="payment-options mt-3 hidden animate-fade-in">
                                <div class="bg-indigo-50 border border-indigo-200 p-4 rounded-md">
                                    <p class="mb-2 font-semibold text-gray-700">Chuyển khoản đến STK: <span class="text-indigo-800 font-bold">1234567890</span></p>
                                    <img src="<?= $qrImg ?>" alt="QR Code" class="mx-auto mb-2 w-44 h-44 rounded-md border">
                                    <p class="text-sm text-gray-700">Nội dung: <span class="font-semibold">THANHTOAN_HD_<?= $row['ID_HD'] ?></span></p>
                                    <form method="POST" action="../Controller/process_payment.php" class="mt-3">
                                        <input type="hidden" name="invoice_id" value="<?= $row['ID_HD'] ?>">
                                        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition">Tôi đã chuyển tiền</button>
                                    </form>
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
    div.style.display = div.style.display === 'none' || div.style.display === '' ? 'block' : 'none';
}
</script>

<?php $conn->close(); ?>