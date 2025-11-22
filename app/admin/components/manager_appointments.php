<?php
include '../../database/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$employeeId = $_SESSION['ID_TK'] ?? null;
if (!$employeeId) {
    echo '<div class="rounded-xl bg-white p-6 text-center text-red-600 shadow">Bạn chưa đăng nhập.</div>';
    return;
}

$branchStmt = $conn->prepare('SELECT ID_CN FROM nhan_vien WHERE ID_TK = ? LIMIT 1');
if (!$branchStmt) {
    echo '<div class="rounded-xl bg-white p-6 text-center text-red-600 shadow">Không thể lấy thông tin chi nhánh. Error: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . '</div>';
    return;
}
$branchStmt->bind_param('s', $employeeId);
$branchStmt->execute();
$branchResult = $branchStmt->get_result();
$branchRow = $branchResult->fetch_assoc();
$branchId = $branchRow['ID_CN'] ?? null;
$branchStmt->close();

if (!$branchId) {
    echo '<div class="rounded-xl bg-white p-6 text-center text-red-600 shadow">
            <p>Tài khoản của bạn chưa được gán vào chi nhánh cụ thể.</p>
            <p class="mt-2 text-sm text-gray-600">Employee ID: ' . htmlspecialchars($employeeId, ENT_QUOTES, 'UTF-8') . '</p>
          </div>';
    return;
}

$limit = 8;
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page - 1) * $limit;

$filters = [
    'ten_khach'  => trim($_GET['ten_khach'] ?? ''),
    'ten_dv'     => trim($_GET['ten_dv'] ?? ''),
    'ngay'       => trim($_GET['ngay'] ?? ''),
    'trangthai'  => trim($_GET['trangthai'] ?? ''),
];

function buildWhereClause(mysqli $conn, array $filters, $branchId)
{
    $conditions = [];
    // So sánh với INT thay vì STRING
    $conditions[] = "lh.ID_CHINHANH = " . (int)$branchId;

    if ($filters['ten_khach'] !== '') {
        $keyword = mysqli_real_escape_string($conn, $filters['ten_khach']);
        $conditions[] = "tk.HO_TEN LIKE '%{$keyword}%'";
    }

    if ($filters['ten_dv'] !== '') {
        $keyword = mysqli_real_escape_string($conn, $filters['ten_dv']);
        $conditions[] = "dv.TEN_DV LIKE '%{$keyword}%'";
    }

    if ($filters['ngay'] !== '') {
        $date = mysqli_real_escape_string($conn, $filters['ngay']);
        $conditions[] = "DATE(lh.THOI_GIAN_BAT_DAU) = '{$date}'";
    }

    if ($filters['trangthai'] !== '') {
        $status = mysqli_real_escape_string($conn, $filters['trangthai']);
        $conditions[] = "lh.TRANGTHAI = '{$status}'";
    }

    return $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
}

$whereClause = buildWhereClause($conn, $filters, $branchId);

function countAppointments(mysqli $conn, string $whereClause)
{
    $query = "SELECT COUNT(*) AS total FROM lich_hen lh" .
             " INNER JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK" .
             " INNER JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV" .
             " {$whereClause}";

    $result = $conn->query($query);
    if (!$result) {
        error_log("Count Query Error: " . $conn->error);
        error_log("Count Query: " . $query);
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int)($row['total'] ?? 0);
}

function fetchAppointments(mysqli $conn, string $whereClause, int $offset, int $limit)
{
    $query = "SELECT lh.ID_LICHHEN, lh.THOI_GIAN_BAT_DAU, lh.DIA_CHI_HEN, lh.TRANGTHAI, tk.HO_TEN, dv.TEN_DV" .
             " FROM lich_hen lh" .
             " INNER JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK" .
             " INNER JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV" .
             " {$whereClause}" .
             " ORDER BY lh.THOI_GIAN_BAT_DAU DESC" .
             " LIMIT {$offset}, {$limit}";

    $result = $conn->query($query);
    if (!$result) {
        error_log("Fetch Query Error: " . $conn->error);
        error_log("Fetch Query: " . $query);
    }
    return $result;
}

function summarizeStatuses(mysqli $conn, string $whereClause)
{
    $query = "SELECT lh.TRANGTHAI, COUNT(*) AS total" .
             " FROM lich_hen lh" .
             " INNER JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK" .
             " INNER JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV" .
             " {$whereClause}" .
             " GROUP BY lh.TRANGTHAI";

    $summary = [
        'Đang chờ'     => 0,
        'Đã xác nhận'  => 0,
        'Đã hoàn thành'=> 0,
        'Đã hủy'       => 0,
    ];

    if ($result = $conn->query($query)) {
        while ($row = $result->fetch_assoc()) {
            $status = $row['TRANGTHAI'] ?? '';
            $count  = (int)($row['total'] ?? 0);
            if (isset($summary[$status])) {
                $summary[$status] = $count;
            }
        }
    }

    return $summary;
}

function formatDateTimeDisplay($value)
{
    if (!$value || $value === '0000-00-00 00:00:00') {
        return '—';
    }

    try {
        $date = new DateTime($value);
        return $date->format('d/m/Y H:i');
    } catch (Exception $e) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

function statusBadgeClass($status)
{
    switch ($status) {
        case 'Đang chờ':
            return 'bg-amber-100 text-amber-700';
        case 'Đã xác nhận':
            return 'bg-blue-100 text-blue-700';
        case 'Đã hoàn thành':
            return 'bg-emerald-100 text-emerald-700';
        case 'Đã hủy':
            return 'bg-rose-100 text-rose-700';
        default:
            return 'bg-gray-100 text-gray-600';
    }
}

$totalRecords = countAppointments($conn, $whereClause);
$totalPages = max(1, (int)ceil($totalRecords / $limit));
$appointments = fetchAppointments($conn, $whereClause, $offset, $limit);
$statusSummary = summarizeStatuses($conn, $whereClause);
?>

<div class="space-y-8 rounded-2xl bg-slate-100 p-8 shadow-inner">
  <header class="space-y-2">
    <h2 class="text-3xl font-semibold text-gray-900">Lịch hẹn chi nhánh</h2>
    <p class="text-sm text-gray-600">Quản lý lịch hẹn khách hàng theo chi nhánh phụ trách, áp dụng bộ lọc để theo dõi trạng thái.</p>
  </header>

  <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
      <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Tổng lịch</p>
      <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $totalRecords ?></p>
      <p class="mt-1 text-xs text-gray-400">Bao gồm toàn bộ lịch hẹn phù hợp bộ lọc hiện tại.</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
      <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Đang chờ</p>
      <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $statusSummary['Đang chờ'] ?? 0 ?></p>
      <p class="mt-1 text-xs text-gray-400">Lịch chưa xác nhận.</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
      <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Đã xác nhận</p>
      <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $statusSummary['Đã xác nhận'] ?? 0 ?></p>
      <p class="mt-1 text-xs text-gray-400">Đã khóa thời gian với khách hàng.</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
      <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Đã hoàn thành</p>
      <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $statusSummary['Đã hoàn thành'] ?? 0 ?></p>
      <p class="mt-1 text-xs text-gray-400">Lịch đã hoàn tất nghiệp vụ.</p>
    </div>
  </section>

  <form method="GET" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
    <input type="hidden" name="page" value="appointments">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
      <label class="flex flex-col gap-2 text-sm font-medium text-gray-600">
        <span>Tên khách hàng</span>
        <input type="text" name="ten_khach" value="<?= htmlspecialchars($filters['ten_khach'], ENT_QUOTES, 'UTF-8') ?>"
               placeholder="Ví dụ: Nguyễn Văn A"
               class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
      </label>
      <label class="flex flex-col gap-2 text-sm font-medium text-gray-600">
        <span>Dịch vụ</span>
        <input type="text" name="ten_dv" value="<?= htmlspecialchars($filters['ten_dv'], ENT_QUOTES, 'UTF-8') ?>"
               placeholder="Ví dụ: Chụp ảnh cưới"
               class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
      </label>
      <label class="flex flex-col gap-2 text-sm font-medium text-gray-600">
        <span>Ngày thực hiện</span>
        <input type="date" name="ngay" value="<?= htmlspecialchars($filters['ngay'], ENT_QUOTES, 'UTF-8') ?>"
               class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
      </label>
      <label class="flex flex-col gap-2 text-sm font-medium text-gray-600">
        <span>Trạng thái</span>
        <select name="trangthai"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
          <option value="">Tất cả</option>
          <option value="Đang chờ" <?= $filters['trangthai'] === 'Đang chờ' ? 'selected' : '' ?>>Đang chờ</option>
          <option value="Đã xác nhận" <?= $filters['trangthai'] === 'Đã xác nhận' ? 'selected' : '' ?>>Đã xác nhận</option>
          <option value="Đã hoàn thành" <?= $filters['trangthai'] === 'Đã hoàn thành' ? 'selected' : '' ?>>Đã hoàn thành</option>
          <option value="Đã hủy" <?= $filters['trangthai'] === 'Đã hủy' ? 'selected' : '' ?>>Đã hủy</option>
        </select>
      </label>
      <div class="flex items-end gap-3">
        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700">Lọc kết quả</button>
        <a href="?page=appointments" class="whitespace-nowrap rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-100">Đặt lại</a>
      </div>
    </div>
  </form>

  <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
    <?php if (!$appointments || $appointments->num_rows === 0): ?>
      <div class="px-6 py-10 text-center text-sm text-gray-500">
        Không có lịch hẹn phù hợp với bộ lọc hiện tại.
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full table-auto text-left text-sm">
          <thead>
            <tr class="bg-indigo-700 text-indigo-50">
              <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide">Mã lịch</th>
              <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide">Dịch vụ</th>
              <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide">Khách hàng</th>
              <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide">Thời gian</th>
              <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide">Địa điểm</th>
              <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide">Trạng thái</th>
              <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-center">Chi tiết</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php while ($row = $appointments->fetch_assoc()): ?>
              <tr class="transition-colors duration-150 hover:bg-indigo-50">
                <td class="px-6 py-4 font-semibold text-indigo-700"><?= (int)($row['ID_LICHHEN'] ?? 0) ?></td>
                <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($row['TEN_DV'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($row['HO_TEN'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td class="px-6 py-4 text-gray-700"><?= formatDateTimeDisplay($row['THOI_GIAN_BAT_DAU'] ?? '') ?></td>
                <td class="px-6 py-4 text-gray-700">
                  <?php 
                    $address = $row['DIA_CHI_HEN'] ?? '—';
                    $displayAddress = mb_strlen($address) > 50 ? mb_substr($address, 0, 50) . '...' : $address;
                  ?>
                  <span title="<?= htmlspecialchars($address, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($displayAddress, ENT_QUOTES, 'UTF-8') ?></span>
                </td>
                <td class="px-6 py-4">
                  <?php $status = $row['TRANGTHAI'] ?? ''; ?>
                  <span class="inline-flex items-center whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold <?= statusBadgeClass($status) ?>">
                    <?= htmlspecialchars($status ?: 'Không xác định', ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </td>
                <td class="px-6 py-4 text-center">
                  <a href="?page=appointment_detail&amp;ID_LICHHEN=<?= (int)($row['ID_LICHHEN'] ?? 0) ?>"
                     class="text-sm font-medium text-indigo-600 transition hover:text-indigo-800">Xem</a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($totalPages > 1): ?>
    <nav class="flex flex-wrap items-center justify-center gap-2">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php $queryString = http_build_query(array_merge($_GET, ['page_num' => $i])); ?>
        <a href="?<?= htmlspecialchars($queryString, ENT_QUOTES, 'UTF-8') ?>"
           class="rounded-lg px-4 py-2 text-sm font-semibold transition <?php if ($i === $page): ?>bg-indigo-600 text-white<?php else: ?>bg-white text-indigo-600 hover:bg-indigo-100 border border-indigo-200<?php endif; ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>
