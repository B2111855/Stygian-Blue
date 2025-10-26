<?php
include '../../database/config.php';

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
if (!empty($_GET['trangthai'])) {
  $trangthai = mysqli_real_escape_string($conn, $_GET['trangthai']);
  $where .= " AND h.TRANGTHAI_THANHTOAN = '$trangthai'";
}

// Truy vấn đếm tổng số bản ghi
$countQuery = "
    SELECT COUNT(*) as total
    FROM hoa_don h
    JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
    JOIN tai_khoan kh ON l.ID_TK = kh.ID_TK
    JOIN dich_vu dv ON l.ID_DV = dv.ID_DV
    $where
";
$countResult = mysqli_query($conn, $countQuery);
$totalRows = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRows / $limit);

// Truy vấn dữ liệu phân trang
$query = "
    SELECT 
        h.ID_HD, h.NGAY_GIO, h.TONG_TIEN, h.TRANGTHAI_THANHTOAN,
        h.YEU_CAU_XAC_NHAN,
        kh.HO_TEN AS TEN_KH,
        dv.TEN_DV AS TEN_DV
    FROM hoa_don h
    JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
    JOIN tai_khoan kh ON l.ID_TK = kh.ID_TK
    JOIN dich_vu dv ON l.ID_DV = dv.ID_DV
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

// Tính tổng tiền hóa đơn dựa vào ID hóa đơn
function calculateInvoiceTotal($id_hd)
{
  global $conn;

  $stmt = mysqli_prepare($conn, "
        SELECT hd.ID_HD, dv.TEN_DV, dv.thoi_gian, dgdv.DON_GIA AS GIA_DV, 
               GROUP_CONCAT(tb.TEN_TB SEPARATOR ', ') AS TEN_TB, 
               SUM(dgtb.DON_GIA) AS TONG_TB
        FROM hoa_don hd
        JOIN lich_hen lh ON hd.ID_LICHHEN = lh.ID_LICHHEN
        JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
        JOIN don_gia_dich_vu dgdv ON dv.ID_DV = dgdv.ID_DV
        LEFT JOIN lich_hen_thiet_bi lhtb ON lh.ID_LICHHEN = lhtb.ID_LICHHEN
        LEFT JOIN trang_thiet_bi tb ON lhtb.ID_TB = tb.ID_TB
        LEFT JOIN don_gia_trang_thiet_bi dgtb ON tb.ID_TB = dgtb.ID_TB
        WHERE hd.ID_HD = ?
        GROUP BY hd.ID_HD
    ");

  mysqli_stmt_bind_param($stmt, "i", $id_hd);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $row = mysqli_fetch_assoc($result);

  if ($row) {
    // Tính giá dịch vụ
    $duration = new DateTime($row['thoi_gian']);
    $hours = (int)$duration->format('H') + ((int)$duration->format('i') / 60);
    $totalDV = $hours * $row['GIA_DV'];

    $totalTB = $row['TONG_TB'] ?? 0;

    return [
      'totalDV' => $totalDV,
      'totalTB' => $totalTB,
      'total' => $totalDV + $totalTB
    ];
  }

  return ['totalDV' => 0, 'totalTB' => 0, 'total' => 0];
}





?>
<!--  -->

<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen p-6">
  <div class="max-w-7xl mx-auto bg-white shadow-xl rounded-xl p-8">
    <h1 class="text-3xl font-extrabold text-indigo-700 mb-8 text-center">🧾 Quản Lý Hóa Đơn</h1>

    <!-- Form lọc -->
    <form method="GET" class="flex flex-wrap gap-4 mb-8 justify-center items-center">
      <input type="hidden" name="page" value="payments">

      <input type="text" name="ten_kh" placeholder="Tên khách hàng"
        value="<?= isset($_GET['ten_kh']) ? htmlspecialchars($_GET['ten_kh']) : '' ?>"
        class="border border-gray-300 rounded-lg px-4 py-2 w-48 focus:ring-indigo-500" />

      <input type="text" name="ten_dv" placeholder="Tên dịch vụ"
        value="<?= isset($_GET['ten_dv']) ? htmlspecialchars($_GET['ten_dv']) : '' ?>"
        class="border border-gray-300 rounded-lg px-4 py-2 w-48 focus:ring-indigo-500" />

      <select name="trangthai" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500">
        <option value="">Tất cả trạng thái</option>
        <option value="Chưa thanh toán" <?= (isset($_GET['trangthai']) && $_GET['trangthai'] === 'Chưa thanh toán') ? 'selected' : '' ?>>Chưa thanh toán</option>
        <option value="Đã thanh toán" <?= (isset($_GET['trangthai']) && $_GET['trangthai'] === 'Đã thanh toán') ? 'selected' : '' ?>>Đã thanh toán</option>
      </select>

      <button type="submit"
        class="bg-indigo-600 text-white px-5 py-2 rounded-lg hover:bg-indigo-700 shadow-md font-semibold">
        🔍 Tìm kiếm
      </button>
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
              $rowClass = ($row['YEU_CAU_XAC_NHAN'] == 1 && $row['TRANGTHAI_THANHTOAN'] === 'Chưa thanh toán')
              ? 'bg-yellow-50 border-l-4 border-yellow-400 shadow-inner animate-pulse'
              : '';          
              ?>
              <tr class="hover:shadow-lg hover:bg-indigo-50 transition duration-200 <?= $rowClass ?>">
                <td class="px-6 py-4 font-medium"><?= $row['ID_HD'] ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars($row['TEN_KH']) ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars($row['TEN_DV']) ?></td>
                <td class="px-6 py-4"><?= $row['NGAY_GIO'] ?></td>
                <td class="px-6 py-4 text-green-700 font-semibold">
                  <?= number_format($row['TONG_TIEN'], 0, ',', '.') ?> VND
                </td>
                <td class="px-6 py-4">
                  <?php if ($row['TRANGTHAI_THANHTOAN'] === 'Đã thanh toán'): ?>
                    <span class="inline-flex items-center gap-1 text-green-600 font-semibold">
                      <i class="fas fa-check-circle"></i> Đã thanh toán
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center gap-1 text-red-500 font-semibold">
                      <i class="fas fa-exclamation-circle"></i> Chưa thanh toán
                    </span>
                    <?php if ($row['YEU_CAU_XAC_NHAN'] == 1): ?>
                      <span class="ml-2 inline-flex items-center gap-1 text-yellow-600 font-semibold" title="Khách hàng đã xác nhận chuyển khoản">
                        <i class="fas fa-bell animate-bounce"></i> Yêu cầu xác nhận
                      </span>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-center">
                <a href="?page=hoa_don_chi_tiet&id_hd=<?= $row['ID_HD'] ?>"
  class="flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded shadow transition whitespace-nowrap w-fit mx-auto">
  <i class="fas fa-file-alt"></i><span>Chi tiết</span>
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
  </div>
</body>

<?php
renderPagination($totalPages, $page, $_GET);
mysqli_close($conn); ?>