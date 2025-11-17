<?php
include '../../database/config.php';

$limit = 6;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$start = ($page - 1) * $limit;

if (!isset($_GET['ten_khach']) && !isset($_GET['ten_dv']) && !isset($_GET['ngay']) && !isset($_GET['trangthai']) && !isset($_GET['chi_nhanh'])) {
  $_GET['ten_khach'] = '';
  $_GET['ten_dv'] = '';
  $_GET['ngay'] = '';
  $_GET['trangthai'] = '';
  $_GET['chi_nhanh'] = '';
}

$totalRecords = countFilteredAppointments();
$totalPages = max(1, ceil($totalRecords / $limit));

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

  if (isset($_GET['chi_nhanh']) && $_GET['chi_nhanh'] !== '') {
    $params['chi_nhanh'] = (int)$_GET['chi_nhanh'];
    $conditions[] = "lh.ID_CHINHANH = {$params['chi_nhanh']}";
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
          tk.HO_TEN, dv.TEN_DV, cn.TEN_CN,
            xc.TRANGTHAI AS XAC_NHAN_NV
        FROM lich_hen lh
        INNER JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK
        INNER JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
        LEFT JOIN chi_nhanh cn ON lh.ID_CHINHANH = cn.ID_CN
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

function getBranches()
{
  global $conn;
  $branches = [];
  $query = "SELECT ID_CN, TEN_CN FROM chi_nhanh ORDER BY TEN_CN";
  $result = mysqli_query($conn, $query);
  if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $branches[] = $row;
    }
    mysqli_free_result($result);
  }

  return $branches;
}

$appointments = getAppointments($start, $limit);
$branches = getBranches();
?>

<div class="bg-white p-8 rounded-xl shadow-xl max-w-7xl mx-auto">
  <h2 class="text-3xl font-extrabold text-gray-900 mb-6 text-center">Quản lý lịch hẹn</h2>

  <!-- Form lọc -->
  <form method="GET" class="grid gap-4 md:grid-cols-2 lg:grid-cols-5 mb-8">
    <input type="hidden" name="page" value="appointments">

    <input type="text" name="ten_khach" placeholder="Tên khách hàng"
      value="<?= isset($_GET['ten_khach']) ? htmlspecialchars($_GET['ten_khach']) : '' ?>"
      class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm" />

    <input type="text" name="ten_dv" placeholder="Dịch vụ"
      value="<?= isset($_GET['ten_dv']) ? htmlspecialchars($_GET['ten_dv']) : '' ?>"
      class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm" />

    <input type="date" name="ngay" value="<?= $_GET['ngay'] ?? '' ?>"
      class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm" />

    <select name="trangthai" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
      <option value="">Tất cả trạng thái</option>
      <option value="Đang chờ" <?= ($_GET['trangthai'] ?? '') === 'Đang chờ' ? 'selected' : '' ?>>Đang chờ</option>
      <option value="Đã xác nhận" <?= ($_GET['trangthai'] ?? '') === 'Đã xác nhận' ? 'selected' : '' ?>>Đã xác nhận</option>
      <option value="Đã hoàn thành" <?= ($_GET['trangthai'] ?? '') === 'Đã hoàn thành' ? 'selected' : '' ?>>Đã hoàn thành</option>
      <option value="Đã hủy" <?= ($_GET['trangthai'] ?? '') === 'Đã hủy' ? 'selected' : '' ?>>Đã hủy</option>
    </select>

    <select name="chi_nhanh" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
      <option value="">Tất cả chi nhánh</option>
      <?php foreach ($branches as $branch): ?>
        <option value="<?= $branch['ID_CN'] ?>" <?= (isset($_GET['chi_nhanh']) && $_GET['chi_nhanh'] == $branch['ID_CN']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($branch['TEN_CN']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <button type="submit"
      class="bg-indigo-600 text-white px-5 py-2 rounded-lg hover:bg-indigo-700 transition font-semibold shadow-md">
      Tìm kiếm
    </button>
  </form>

  <!-- Bảng dữ liệu -->
  <div class="overflow-x-auto shadow rounded-lg">
    <table class="min-w-full text-sm text-left border border-gray-300 bg-white">
      <thead class="bg-indigo-600 text-white">
        <tr>
          <th class="border px-6 py-3 font-semibold">Mã</th>
          <th class="border px-6 py-3 font-semibold">Dịch vụ</th>
          <th class="border px-6 py-3 font-semibold">Chi nhánh</th>
          <th class="border px-6 py-3 font-semibold">Khách hàng</th>
          <th class="border px-6 py-3 font-semibold">Thời gian bắt đầu</th>
          <th class="border px-6 py-3 font-semibold">Trạng thái</th>
          <th class="border px-6 py-3 font-semibold">Chi tiết</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
      <?php if (mysqli_num_rows($appointments) === 0): ?>
          <tr>
            <td colspan="7" class="px-6 py-6 text-center text-gray-500">Không tìm thấy lịch hẹn theo tiêu chí lọc.</td>
          </tr>
        <?php else: ?>
          <?php while ($row = mysqli_fetch_assoc($appointments)): ?>
            <tr class="hover:bg-gray-50 transition">
              <td class="px-6 py-4 font-semibold text-gray-900">#<?= $row['ID_LICHHEN'] ?></td>
              <td class="px-6 py-4">
                <div class="text-gray-900 font-medium"><?= htmlspecialchars($row['TEN_DV']) ?></div>
                <div class="text-xs text-gray-500 truncate" title="<?= htmlspecialchars($row['DIA_CHI_HEN']) ?>">
                  <?= htmlspecialchars($row['DIA_CHI_HEN']) ?>
                </div>
              </td>
              <td class="px-6 py-4"><?= htmlspecialchars($row['TEN_CN'] ?? 'Đang cập nhật') ?></td>
              <td class="px-6 py-4">
                <div class="text-gray-900 font-medium"><?= htmlspecialchars($row['HO_TEN']) ?></div>
              </td>
              <td class="px-6 py-4 text-sm text-gray-600">
                <?= date('d/m/Y H:i', strtotime($row['THOI_GIAN_BAT_DAU'])) ?>
              </td>
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
                <span class="font-semibold <?= $statusColor ?>"><?= htmlspecialchars($status) ?></span>
              </td>
              <td class="px-6 py-4 text-center">
                <a href="?page=appointment_detail&ID_LICHHEN=<?= $row['ID_LICHHEN'] ?>"
                  class="inline-flex items-center justify-center border border-indigo-200 text-indigo-700 px-4 py-2 rounded-lg hover:bg-indigo-50 transition font-semibold">
                  Xem chi tiết
                </a>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
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
