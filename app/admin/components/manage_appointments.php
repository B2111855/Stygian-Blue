<?php
include '../../database/config.php';

$limit = 6;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$start = ($page - 1) * $limit;

$multiServiceJoin = "
  LEFT JOIN (
    SELECT bi.ID_LICHHEN,
         GROUP_CONCAT(dv.TEN_DV ORDER BY bi.ID_ITEM SEPARATOR ', ') AS SERVICE_LIST,
         COUNT(DISTINCT bi.REF_ID) AS SERVICE_COUNT
    FROM BOOKING_ITEM bi
    JOIN DICH_VU dv ON dv.ID_DV = bi.REF_ID
    WHERE bi.ITEM_TYPE = 'service'
    GROUP BY bi.ID_LICHHEN
  ) ms ON ms.ID_LICHHEN = lh.ID_LICHHEN
";

if (!isset($_GET['ten_khach']) && !isset($_GET['ten_dv']) && !isset($_GET['ngay']) && !isset($_GET['trangthai']) && !isset($_GET['chi_nhanh']) && !isset($_GET['id_lichhen'])) {
  $_GET['ten_khach'] = '';
  $_GET['ten_dv'] = '';
  $_GET['ngay'] = '';
  $_GET['trangthai'] = '';
  $_GET['chi_nhanh'] = '';
  $_GET['id_lichhen'] = '';
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
    $conditions[] = "COALESCE(ms.SERVICE_LIST, dv.TEN_DV) LIKE '%{$params['ten_dv']}%'";
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

  if (!empty($_GET['id_lichhen'])) {
    $params['id_lichhen'] = (int)$_GET['id_lichhen'];
    $conditions[] = "lh.ID_LICHHEN = {$params['id_lichhen']}";
  }

  return "WHERE " . implode(" AND ", $conditions);
}

function getAppointments($start, $limit)
{
  global $conn;
  global $multiServiceJoin;
  $params = [];
  $where = buildFilterConditions($params);

  $query = "
        SELECT 
          lh.ID_LICHHEN, lh.THOI_GIAN_BAT_DAU, lh.DIA_CHI_HEN, lh.TRANGTHAI,
          lh.KHACH_XAC_NHAN, lh.EMAIL_XAC_NHAN_SENT,
          tk.HO_TEN, dv.TEN_DV, cn.TEN_CN,
          COALESCE(ms.SERVICE_LIST, dv.TEN_DV) AS DISPLAY_SERVICE,
          COALESCE(ms.SERVICE_COUNT, 1) AS SERVICE_COUNT,
            xc.TRANGTHAI AS XAC_NHAN_NV
        FROM lich_hen lh
        INNER JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK
        INNER JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
        LEFT JOIN chi_nhanh cn ON lh.ID_CHINHANH = cn.ID_CN
        LEFT JOIN xac_nhan_hoan_thanh xc ON lh.ID_LICHHEN = xc.ID_LICHHEN
        $multiServiceJoin
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
  global $multiServiceJoin;
  $params = [];
  $where = buildFilterConditions($params);

  $query = "SELECT COUNT(*) AS total FROM lich_hen lh JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV $multiServiceJoin $where";
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
  <form method="GET" class="grid gap-4 md:grid-cols-2 lg:grid-cols-5 mb-8" data-action="filter_appointments">
    <input type="hidden" name="page" value="appointments">

    <input type="text" name="id_lichhen" placeholder="Mã lịch hẹn" data-action="search_appointments"
      value="<?= isset($_GET['id_lichhen']) ? htmlspecialchars($_GET['id_lichhen']) : '' ?>"
      class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm" />

    <input type="text" name="ten_khach" placeholder="Tên khách hàng" data-action="search_appointments"
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
          <th class="border px-6 py-3 font-semibold whitespace-nowrap">Mã</th>
          <th class="border px-6 py-3 font-semibold whitespace-nowrap">Dịch vụ</th>
          <th class="border px-6 py-3 font-semibold whitespace-nowrap">Chi nhánh</th>
          <th class="border px-6 py-3 font-semibold whitespace-nowrap">Khách hàng</th>
          <th class="border px-6 py-3 font-semibold whitespace-nowrap">Thời gian bắt đầu</th>
          <th class="border px-6 py-3 font-semibold whitespace-nowrap">Trạng thái</th>
          <th class="border px-6 py-3 font-semibold whitespace-nowrap">Khách xác nhận</th>
          <th class="border px-6 py-3 font-semibold whitespace-nowrap">Chi tiết</th>
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
              <td class="px-6 py-4 max-h-20 overflow-hidden">
                <?php
                  $svcLabel = $row['DISPLAY_SERVICE'] ?? $row['TEN_DV'] ?? '—';
                  $svcCount = (int)($row['SERVICE_COUNT'] ?? 1);
                  if ($svcCount > 1):
                      $svcParts = array_map('trim', explode(',', $svcLabel));
                      $primarySvc = $svcParts[0] ?? $svcLabel;
                      $extras = $svcCount - 1;
                ?>
                  <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700 truncate" title="<?= htmlspecialchars($primarySvc) ?>">
                    <?= htmlspecialchars($primarySvc) ?>
                  </span>
                  <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600 ml-1">
                    +<?= $extras ?>
                  </span>
                <?php else: ?>
                  <div class="text-gray-900 font-medium truncate" title="<?= htmlspecialchars($svcLabel) ?>"><?= htmlspecialchars($svcLabel) ?></div>
                <?php endif; ?>
                <div class="text-xs text-gray-500 truncate" title="<?= htmlspecialchars($row['DIA_CHI_HEN']) ?>">
                  <?= htmlspecialchars($row['DIA_CHI_HEN']) ?>
                </div>
              </td>
              <td class="px-6 py-4"><?= htmlspecialchars($row['TEN_CN'] ?? 'Đang cập nhật') ?></td>
              <td class="px-6 py-4">
                <div class="text-gray-900 font-medium truncate" title="<?= htmlspecialchars($row['HO_TEN']) ?>"><?= htmlspecialchars($row['HO_TEN']) ?></div>
              </td>
              <td class="px-6 py-4 text-sm text-gray-600">
                <?= date('d/m/Y H:i', strtotime($row['THOI_GIAN_BAT_DAU'])) ?>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
              <?php
              $status = $row['TRANGTHAI'];
              $statusColor = match ($status) {
                'Đang chờ' => 'text-yellow-600',
                'Đã xác nhận' => 'text-blue-600',
                'Đã hoàn thành' => 'text-green-600',
                'Đã hủy' => 'text-red-600',
                default => 'text-gray-600',
              };
              
              // Check if appointment is overdue
              $appointmentTime = strtotime($row['THOI_GIAN_BAT_DAU']);
              $isOverdue = $appointmentTime < time() && !in_array($status, ['Không đến', 'Đã hoàn thành', 'Đã hủy']);
              ?>
                <div class="flex flex-col gap-1">
                  <span class="font-semibold whitespace-nowrap <?= $statusColor ?>"><?= htmlspecialchars($status) ?></span>
                  <?php if ($isOverdue): ?>
                    <span class="inline-block bg-red-100 text-red-800 px-2 py-0.5 text-xs font-bold rounded">Quá hạn</span>
                  <?php endif; ?>
                </div>
              </td>
              <td class="px-6 py-4 text-center">
                <?php
                  $confirmed = (int)($row['KHACH_XAC_NHAN'] ?? 0);
                  $emailSent = (int)($row['EMAIL_XAC_NHAN_SENT'] ?? 0);
                ?>
                <?php if ($confirmed): ?>
                  <span class="inline-block rounded-full bg-green-100 text-green-800 px-3 py-1 text-sm font-semibold whitespace-nowrap">
                    Đã xác nhận
                  </span>
                <?php elseif ($emailSent): ?>
                  <span class="inline-block rounded-full bg-blue-100 text-blue-800 px-3 py-1 text-sm font-semibold whitespace-nowrap">
                    Email gửi
                  </span>
                <?php else: ?>
                  <span class="inline-block rounded-full bg-gray-100 text-gray-800 px-3 py-1 text-sm font-semibold whitespace-nowrap">
                    Chưa
                  </span>
                <?php endif; ?>
              </td>
              <td class="px-6 py-4 text-center">
                <a href="?page=appointment_detail&amp;ID_LICHHEN=<?= $row['ID_LICHHEN'] ?>"
                  class="inline-block border border-indigo-200 text-indigo-700 px-2 py-1 rounded-lg hover:bg-indigo-50 transition font-semibold text-xs whitespace-nowrap">
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

<script src="../../public/assets/js/api-client.js"></script>
<script src="../../public/assets/js/manage-appointments.js"></script>

