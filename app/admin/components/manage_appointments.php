<?php
include '../../database/config.php';

$limit = 6;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$start = ($page - 1) * $limit;

$totalRecords = countFilteredAppointments();
$totalPages = ceil($totalRecords / $limit);

if (!isset($_GET['ten_khach']) && !isset($_GET['ten_dv']) && !isset($_GET['ngay']) && !isset($_GET['trangthai'])) {
  $_GET['ten_khach'] = '';
  $_GET['ten_dv'] = '';
  $_GET['ngay'] = '';
  $_GET['trangthai'] = '';
}

function buildFilterConditions(&$params)
{
  global $conn;
  $conditions = ["1"];

  if (!empty($_GET['ten_khach'])) {
    $params['ten_khach'] = mysqli_real_escape_string($conn, $_GET['ten_khach']);
    $conditions[] = "tk.HO_TEN LIKE '%{$params['ten_khach']}%'";
  }

  if (!empty($_GET['ten_dv'])) {
    $params['ten_dv'] = mysqli_real_escape_string($conn, $_GET['ten_dv']);
    $conditions[] = "dv.TEN_DV LIKE '%{$params['ten_dv']}%'";
  }

  if (!empty($_GET['ngay'])) {
    $params['ngay'] = mysqli_real_escape_string($conn, $_GET['ngay']);
    $conditions[] = "DATE(lh.THOI_GIAN_BAT_DAU) = '{$params['ngay']}'";
  }

  if (isset($_GET['trangthai']) && $_GET['trangthai'] !== '') {
    $params['trangthai'] = mysqli_real_escape_string($conn, $_GET['trangthai']);
    $conditions[] = "lh.TRANGTHAI = '{$params['trangthai']}'";
  }

  return "WHERE " . implode(" AND ", $conditions);
}

function getAppointments($start, $limit)
{
  global $conn;
  $params = [];
  $where = buildFilterConditions($params);

  $query = "
        SELECT 
            lh.ID_LICHHEN, lh.THOI_GIAN_BAT_DAU, lh.DIA_CHI_HEN, lh.TRANGTHAI,
            tk.HO_TEN, dv.TEN_DV,
            xc.TRANGTHAI AS XAC_NHAN_NV
        FROM lich_hen lh
        INNER JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK
        INNER JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
        LEFT JOIN xac_nhan_hoan_thanh xc ON lh.ID_LICHHEN = xc.ID_LICHHEN
        $where
        ORDER BY lh.THOI_GIAN_BAT_DAU DESC
        LIMIT $start, $limit
    ";

  $result = mysqli_query($conn, $query);
  if (!$result) {
    die("Lỗi SQL trong getAppointments: " . mysqli_error($conn));
  }
  return $result;
}

function countFilteredAppointments()
{
  global $conn;
  $params = [];
  $where = buildFilterConditions($params);

  $query = "SELECT COUNT(*) AS total FROM lich_hen lh JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV $where";
  $result = mysqli_query($conn, $query);
  if (!$result) {
    die("Lỗi SQL: " . mysqli_error($conn));
  }
  $row = mysqli_fetch_assoc($result);
  return $row['total'];
}

$appointments = getAppointments($start, $limit);
?>

<div class="bg-white p-8 rounded-xl shadow-xl max-w-7xl mx-auto">
  <h2 class="text-3xl font-extrabold text-indigo-700 mb-6 text-center">📅 Quản lý lịch hẹn</h2>

  <!-- Form lọc -->
  <form method="GET" class="flex flex-wrap gap-4 justify-center items-end mb-8">
    <input type="hidden" name="page" value="appointments">

    <input type="text" name="ten_khach" placeholder="Tên khách hàng"
      value="<?= isset($_GET['ten_khach']) ? htmlspecialchars($_GET['ten_khach']) : '' ?>"
      class="border border-gray-300 rounded-lg px-4 py-2 w-48 focus:ring-indigo-500 shadow-sm" />

    <input type="text" name="ten_dv" placeholder="Dịch vụ"
      value="<?= isset($_GET['ten_dv']) ? htmlspecialchars($_GET['ten_dv']) : '' ?>"
      class="border border-gray-300 rounded-lg px-4 py-2 w-48 focus:ring-indigo-500 shadow-sm" />

    <input type="date" name="ngay" value="<?= $_GET['ngay'] ?? '' ?>"
      class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500 shadow-sm" />

    <select name="trangthai" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500 shadow-sm">
      <option value="">Tất cả trạng thái</option>
      <option value="Đang chờ" <?= $_GET['trangthai'] === 'Đang chờ' ? 'selected' : '' ?>>Đang chờ</option>
      <option value="Đã xác nhận" <?= $_GET['trangthai'] === 'Đã xác nhận' ? 'selected' : '' ?>>Đã xác nhận</option>
      <option value="Đã hoàn thành" <?= $_GET['trangthai'] === 'Đã hoàn thành' ? 'selected' : '' ?>>Đã hoàn thành</option>
      <option value="Đã hủy" <?= $_GET['trangthai'] === 'Đã hủy' ? 'selected' : '' ?>>Đã hủy</option>
    </select>

    <button type="submit"
      class="bg-indigo-600 text-white px-5 py-2 rounded-lg hover:bg-indigo-700 transition font-semibold shadow-md">
      🔍 Tìm kiếm
    </button>
  </form>

  <!-- Bảng dữ liệu -->
  <div class="overflow-x-auto shadow rounded-lg">
    <table class="min-w-full text-sm text-left border border-gray-300 bg-white">
      <thead class="bg-indigo-600 text-white">
        <tr>
          <th class="border px-6 py-3 font-semibold">Mã</th>
          <th class="border px-6 py-3 font-semibold">Dịch Vụ</th>
          <th class="border px-6 py-3 font-semibold">Khách Hàng</th>
          <th class="border px-6 py-3 font-semibold">Thời Gian Bắt Đầu</th>
          <th class="border px-6 py-3 font-semibold">Trạng Thái</th>
          <th class="border px-6 py-3 font-semibold">Chi Tiết</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
      <?php while ($row = mysqli_fetch_assoc($appointments)): ?>
          <tr class="hover:bg-gray-50 transition">
            <td class="px-6 py-4"><?= $row['ID_LICHHEN'] ?></td>
            <td class="px-6 py-4"><?= $row['TEN_DV'] ?></td>
            <td class="px-6 py-4"><?= $row['HO_TEN'] ?></td>
            <td class="px-6 py-4"><?= $row['THOI_GIAN_BAT_DAU'] ?></td>
            <td class="px-6 py-4">
              <?php
              $status = $row['TRANGTHAI'];
              $statusColor = match ($status) {
                'Đang chờ' => 'text-yellow-600',
                'Đã xác nhận' => 'text-blue-600',
                'Đã hoàn thành' => 'text-green-600',
                'Đã hủy' => 'text-red-600',
                default => 'text-gray-600',
              };
              ?>
              <span class="font-semibold <?= $statusColor ?>"><?= $status ?></span>
            </td>
            <td class="px-6 py-4 text-center">
              <a href="?page=appointment_detail&ID_LICHHEN=<?= $row['ID_LICHHEN'] ?>"
                class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 shadow transition">
                Xem chi tiết
              </a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Phân trang -->
  <div class="mt-8 flex justify-center gap-2">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <?php $queryString = http_build_query(array_merge($_GET, ['page_num' => $i])); ?>
      <a href="?<?= $queryString ?>"
        class="px-4 py-2 rounded-lg text-sm font-semibold border
        <?= $i == $page
          ? 'bg-indigo-600 text-white shadow'
          : 'bg-white text-indigo-700 hover:bg-indigo-100 border-gray-300' ?>">
        <?= $i ?>
      </a>
    <?php endfor; ?>
  </div>
</div>
