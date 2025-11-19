<?php
include '../../database/config.php';

// Helper để giữ lại giá trị filter
function old($key)
{
  return isset($_GET[$key]) ? htmlspecialchars($_GET[$key]) : '';
}

// Xử lý phân trang và bộ lọc
$limit = 5;
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $limit;

$where = "WHERE 1";
if (!empty($_GET['ten_kh'])) {
  $ten_kh = mysqli_real_escape_string($conn, $_GET['ten_kh']);
  $where .= " AND kh.HO_TEN LIKE '%$ten_kh%'";
}
if (!empty($_GET['ten_dv'])) {
  $ten_dv = mysqli_real_escape_string($conn, $_GET['ten_dv']);
  $where .= " AND dv.TEN_DV LIKE '%$ten_dv%'";
}
if (!empty($_GET['ma_hd'])) {
  $ma_hd = (int)$_GET['ma_hd'];
  $where .= " AND h.ID_HD = $ma_hd";
}
if (!empty($_GET['trangthai'])) {
  $trangthai = mysqli_real_escape_string($conn, $_GET['trangthai']);
  if ($trangthai === 'pending_confirm') {
    $where .= " AND h.TRANGTHAI_THANHTOAN <> 'Đã thanh toán' AND tt.VNPAY_TRANG_THAI = 'pending'";
  } else {
    $where .= " AND h.TRANGTHAI_THANHTOAN = '$trangthai'";
  }
}
if (!empty($_GET['date_from'])) {
  $date_from = mysqli_real_escape_string($conn, $_GET['date_from']);
  $where .= " AND DATE(h.NGAY_GIO) >= '$date_from'";
}
if (!empty($_GET['date_to'])) {
  $date_to = mysqli_real_escape_string($conn, $_GET['date_to']);
  $where .= " AND DATE(h.NGAY_GIO) <= '$date_to'";
}

$latestVnpayJoin = "
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
  ) tt ON tt.ID_HD = h.ID_HD
";

// Thống kê nhanh
$statsQuery = "
    SELECT 
        COUNT(*) AS total_invoices,
        SUM(CASE WHEN h.TRANGTHAI_THANHTOAN = 'Đã thanh toán' THEN 1 ELSE 0 END) AS paid_invoices,
        SUM(CASE WHEN h.TRANGTHAI_THANHTOAN = 'Chưa thanh toán' THEN 1 ELSE 0 END) AS unpaid_invoices,
        SUM(CASE WHEN h.TRANGTHAI_THANHTOAN = 'Đã thanh toán' THEN h.TONG_TIEN ELSE 0 END) AS paid_amount,
        SUM(CASE WHEN h.TRANGTHAI_THANHTOAN = 'Chưa thanh toán' THEN h.TONG_TIEN ELSE 0 END) AS unpaid_amount,
    SUM(CASE WHEN h.TRANGTHAI_THANHTOAN <> 'Đã thanh toán' AND tt.VNPAY_TRANG_THAI = 'pending' THEN 1 ELSE 0 END) AS pending_confirm
    FROM hoa_don h
    JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
    JOIN tai_khoan kh ON l.ID_TK = kh.ID_TK
    JOIN dich_vu dv ON l.ID_DV = dv.ID_DV
" . $latestVnpayJoin . "
  $where
";

$statsResult = mysqli_query($conn, $statsQuery);
$statsRow = $statsResult ? mysqli_fetch_assoc($statsResult) : [];
$stats = [
  'total_invoices' => (int)($statsRow['total_invoices'] ?? 0),
  'paid_invoices' => (int)($statsRow['paid_invoices'] ?? 0),
  'unpaid_invoices' => (int)($statsRow['unpaid_invoices'] ?? 0),
  'paid_amount' => (float)($statsRow['paid_amount'] ?? 0),
  'unpaid_amount' => (float)($statsRow['unpaid_amount'] ?? 0),
  'pending_confirm' => (int)($statsRow['pending_confirm'] ?? 0),
];

// Truy vấn đếm tổng số bản ghi
$countQuery = "
    SELECT COUNT(*) as total
    FROM hoa_don h
    JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
    JOIN tai_khoan kh ON l.ID_TK = kh.ID_TK
    JOIN dich_vu dv ON l.ID_DV = dv.ID_DV
" . $latestVnpayJoin . "
    $where
";
$countResult = mysqli_query($conn, $countQuery);
$totalRows = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRows / $limit);

// Truy vấn dữ liệu phân trang
$query = "
    SELECT 
      h.ID_HD, h.NGAY_GIO, h.TONG_TIEN, h.TRANGTHAI_THANHTOAN,
      h.PHUONGTHUC_THANHTOAN,
      tt.VNPAY_TRANG_THAI, tt.VNPAY_MA_THAM_CHIEU, tt.VNPAY_UPDATED_AT,
        kh.HO_TEN AS TEN_KH,
        dv.TEN_DV AS TEN_DV
    FROM hoa_don h
    JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
    JOIN tai_khoan kh ON l.ID_TK = kh.ID_TK
    JOIN dich_vu dv ON l.ID_DV = dv.ID_DV
" . $latestVnpayJoin . "
    $where
    ORDER BY h.NGAY_GIO DESC
    LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $query);

// Giao diện phân trang HTML
function renderPagination($totalPages, $currentPage, $searchParams = [])
{
  echo '<div class="flex justify-center mt-6 space-x-2">';
  for ($i = 1; $i <= $totalPages; $i++) {
    $params = array_merge($searchParams, ['p' => $i]);
    $url = '?' . http_build_query($params);
    $active = $i == $currentPage ? 'bg-blue-500 text-white' : 'bg-white text-blue-500';
    echo "<a href=\"$url\" class=\"px-3 py-1 border rounded $active\">$i</a>";
  }
  echo '</div>';
}


?>
<!--  -->

<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen p-6">
  <div class="max-w-7xl mx-auto bg-white shadow-xl rounded-xl p-8">
    <h1 class="text-3xl font-extrabold text-indigo-700 mb-6 text-center">Quản Lý Hóa Đơn</h1>

    <?php
    $resolvedPercent = $stats['total_invoices'] > 0 ? round(($stats['paid_invoices'] / $stats['total_invoices']) * 100) : 0;
    $pendingPercent = $stats['total_invoices'] > 0 ? round(($stats['unpaid_invoices'] / $stats['total_invoices']) * 100) : 0;
    ?>

    <div class="grid lg:grid-cols-4 md:grid-cols-2 gap-4 mb-8">
      <div class="p-5 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-800 shadow-sm">
        <p class="text-sm uppercase tracking-wide text-indigo-500">Tổng hóa đơn</p>
        <p class="text-3xl font-bold text-indigo-700"><?= number_format($stats['total_invoices']) ?></p>
        <p class="mt-2 text-xs text-indigo-500">Đang lọc theo điều kiện hiện tại</p>
      </div>
      <div class="p-5 rounded-xl bg-white border shadow-sm">
        <p class="text-sm font-semibold text-gray-500">Đã thanh toán</p>
        <p class="text-2xl font-bold text-green-600"><?= number_format($stats['paid_invoices']) ?></p>
        <p class="text-sm text-gray-400 mt-1">≈ <?= $resolvedPercent ?>% tổng hóa đơn</p>
        <p class="text-xs text-gray-500 mt-2"><?= number_format($stats['paid_amount'], 0, ',', '.') ?> VND</p>
      </div>
      <div class="p-5 rounded-xl bg-white border shadow-sm">
        <p class="text-sm font-semibold text-gray-500">Chưa thanh toán</p>
        <p class="text-2xl font-bold text-red-500"><?= number_format($stats['unpaid_invoices']) ?></p>
        <p class="text-xs text-gray-500 mt-2"><?= number_format($stats['unpaid_amount'], 0, ',', '.') ?> VND</p>
        <div class="mt-3 h-2 bg-gray-200 rounded-full">
          <div class="h-full bg-red-400 rounded-full" style="width: <?= $pendingPercent ?>%"></div>
        </div>
      </div>
      <div class="p-5 rounded-xl bg-white border shadow-sm flex flex-col justify-between">
        <div>
          <p class="text-sm font-semibold text-gray-500">VNPay đang xử lý</p>
          <p class="text-2xl font-bold text-amber-500">
            <?= number_format($stats['pending_confirm']) ?>
          </p>
        </div>
        <p class="text-xs text-gray-500">Theo dõi các IPN chưa phản hồi để đảm bảo dòng tiền.</p>
      </div>
    </div>

    <!-- Form lọc -->
    <form method="GET" class="flex flex-wrap gap-4 mb-8 justify-center items-center">
      <input type="hidden" name="page" value="payments">

      <input type="text" name="ten_kh" placeholder="Tên khách hàng" value="<?= old('ten_kh') ?>"
        class="border border-gray-300 rounded-lg px-4 py-2 w-48 focus:ring-indigo-500" />

      <input type="text" name="ten_dv" placeholder="Tên dịch vụ" value="<?= old('ten_dv') ?>"
        class="border border-gray-300 rounded-lg px-4 py-2 w-48 focus:ring-indigo-500" />

      <input type="number" name="ma_hd" placeholder="Mã hóa đơn" value="<?= old('ma_hd') ?>"
        class="border border-gray-300 rounded-lg px-4 py-2 w-40 focus:ring-indigo-500" min="1" />

      <div class="flex items-center gap-2">
        <input type="date" name="date_from" value="<?= old('date_from') ?>"
          class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500" />
        <span class="text-sm text-gray-500">đến</span>
        <input type="date" name="date_to" value="<?= old('date_to') ?>"
          class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500" />
      </div>

      <select name="trangthai" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500">
        <option value="">Tất cả trạng thái</option>
        <option value="Chưa thanh toán" <?= old('trangthai') === 'Chưa thanh toán' ? 'selected' : '' ?>>Chưa thanh toán</option>
        <option value="Đã thanh toán" <?= old('trangthai') === 'Đã thanh toán' ? 'selected' : '' ?>>Đã thanh toán</option>
        <option value="pending_confirm" <?= old('trangthai') === 'pending_confirm' ? 'selected' : '' ?>>VNPay đang xử lý</option>
      </select>

      <button type="submit"
        class="bg-indigo-600 text-white px-5 py-2 rounded-lg hover:bg-indigo-700 shadow-md font-semibold">
        Tìm kiếm
      </button>

      <a href="?page=payments"
        class="border border-gray-300 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-100 shadow-sm font-semibold">
        Làm mới
      </a>
    </form>

    <!-- Bảng hóa đơn -->
    <div class="overflow-x-auto rounded-lg shadow">
      <table class="min-w-full text-sm text-left bg-white border border-gray-200">
        <thead class="bg-indigo-600 text-white">
          <tr>
            <th class="px-6 py-3">Mã hóa đơn</th>
            <th class="px-6 py-3">Khách hàng</th>
            <th class="px-6 py-3">Dịch vụ</th>
            <th class="px-6 py-3">Ngày giờ</th>
            <th class="px-6 py-3">Tổng tiền</th>
            <th class="px-6 py-3">Trạng thái</th>
            <th class="px-6 py-3 text-center">Hành động</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php if (mysqli_num_rows($result)): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
              <?php
              $isPaid = $row['TRANGTHAI_THANHTOAN'] === 'Đã thanh toán';
              $gatewayStatus = $row['VNPAY_TRANG_THAI'] ?? null;
              $isGatewayPending = !$isPaid && $gatewayStatus === 'pending';
              $rowClass = $isGatewayPending
                ? 'bg-amber-50 border-l-4 border-amber-400 shadow-inner'
                : '';
              $methodLabel = $row['PHUONGTHUC_THANHTOAN'] ? strtoupper($row['PHUONGTHUC_THANHTOAN']) : 'Chưa ghi nhận';
              ?>
              <tr class="hover:shadow-lg hover:bg-indigo-50 transition duration-200 <?= $rowClass ?>">
                <td class="px-6 py-4 font-medium whitespace-nowrap">#<?= $row['ID_HD'] ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($row['TEN_KH']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($row['TEN_DV']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                  <?= date('d/m/Y H:i', strtotime($row['NGAY_GIO'])) ?>
                </td>
                <td class="px-6 py-4 text-green-700 font-semibold whitespace-nowrap">
                  <?= number_format($row['TONG_TIEN'], 0, ',', '.') ?> VND
                </td>
                <td class="px-6 py-4">
                  <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold <?= $isPaid ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                    <?= $isPaid ? 'Đã thanh toán' : 'Chưa thanh toán' ?>
                  </span>
                  <div class="text-xs text-gray-500 mt-1">Phương thức: <?= htmlspecialchars($methodLabel) ?></div>
                  <?php if ($gatewayStatus): ?>
                    <?php
                      $gatewayMap = [
                        'pending' => ['label' => 'VNPay đang xử lý', 'class' => 'text-amber-600'],
                        'success' => ['label' => 'VNPay đã xác nhận', 'class' => 'text-emerald-600'],
                        'failed'  => ['label' => 'VNPay lỗi', 'class' => 'text-rose-600'],
                      ];
                      $gwCfg = $gatewayMap[$gatewayStatus] ?? ['label' => 'VNPay: ' . strtoupper($gatewayStatus), 'class' => 'text-slate-600'];
                    ?>
                    <div class="mt-1 inline-flex items-center gap-1 text-xs font-semibold <?= $gwCfg['class'] ?>">
                      <?= $gwCfg['label'] ?>
                    </div>
                    <?php if (!empty($row['VNPAY_MA_THAM_CHIEU'])): ?>
                      <div class="text-[11px] text-gray-500 mt-0.5">Mã: <?= htmlspecialchars($row['VNPAY_MA_THAM_CHIEU']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($row['VNPAY_UPDATED_AT'])): ?>
                      <div class="text-[11px] text-gray-400">Cập nhật <?= htmlspecialchars(date('d/m H:i', strtotime($row['VNPAY_UPDATED_AT']))) ?></div>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-center">
                  <a href="?page=hoa_don_chi_tiet&id_hd=<?= $row['ID_HD'] ?>"
                    class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded shadow transition whitespace-nowrap">
                    <span>Chi tiết</span>
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="text-center px-6 py-4 text-gray-500">
                Không có hóa đơn nào khớp với điều kiện tìm kiếm.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>

      </table>
    </div>
    <?php renderPagination($totalPages, $page, $_GET); ?>
  </div>
</body>

<?php
mysqli_close($conn); ?>