<?php
include '../../database/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$now = new DateTimeImmutable('now');

function branchCustomerFlash(string $type, string $message): void
{
    $_SESSION['branch_customer_flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

function redirectToCustomers(): void
{
    $target = '?page=customers';
    if (!empty($_GET)) {
        $params = $_GET;
        $params['page'] = 'customers';
        $target = '?' . http_build_query($params);
    }
    header('Location: ' . $target);
    exit;
}

function ensureBranchCustomerNotesTable(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS branch_customer_notes (
        ID_NOTE INT AUTO_INCREMENT PRIMARY KEY,
        ID_CN INT NOT NULL,
        ID_TK VARCHAR(20) NOT NULL,
        NOTE TEXT NULL,
        TAG ENUM('binh_thuong','tiem_nang','vip','nguy_co','can_cham_soc') DEFAULT 'binh_thuong',
        LAST_CONTACT DATE DEFAULT NULL,
        NEXT_ACTION DATE DEFAULT NULL,
        CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UPDATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_branch_customer (ID_CN, ID_TK),
        CONSTRAINT fk_branch_notes_branch FOREIGN KEY (ID_CN) REFERENCES chi_nhanh(ID_CN) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_branch_notes_customer FOREIGN KEY (ID_TK) REFERENCES khach_hang(ID_TK) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;";
    $conn->query($sql);
}

function bindDynamic(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '' || empty($params)) {
        return;
    }
    $bind = [$types];
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

function normalizeDate(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    $value = trim($value);
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt ? $dt->format('Y-m-d') : null;
}

function buildFilterData(int $branchId, array $filters): array
{
    $conditions = ['lh.ID_CHINHANH = ?'];
    $params = [$branchId];
    $types = 'i';

    if ($filters['keyword'] !== '') {
        $keyword = '%' . $filters['keyword'] . '%';
        $conditions[] = '(kh.HO_TEN LIKE ? OR kh.EMAIL LIKE ? OR kh.SDT LIKE ?)';
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
        $types .= 'sss';
    }

    if ($filters['from']) {
        $conditions[] = 'lh.THOI_GIAN_BAT_DAU >= ?';
        $params[] = $filters['from'] . ' 00:00:00';
        $types .= 's';
    }

    if ($filters['to']) {
        $conditions[] = 'lh.THOI_GIAN_BAT_DAU <= ?';
        $params[] = $filters['to'] . ' 23:59:59';
        $types .= 's';
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    return [$where, $types, $params];
}

function fetchAppointments(mysqli $conn, int $branchId, array $filters): array
{
    [$where, $types, $params] = buildFilterData($branchId, $filters);
    $sql = "
        SELECT
            lh.ID_LICHHEN,
            lh.ID_TK,
            lh.THOI_GIAN_BAT_DAU,
            lh.TRANGTHAI,
            lh.DIA_CHI_HEN,
            dv.TEN_DV,
            kh.HO_TEN,
            kh.EMAIL,
            kh.SDT,
            kh.DIA_CHI,
            kh.NGAY_SINH
        FROM lich_hen lh
        JOIN khach_hang kh ON kh.ID_TK = lh.ID_TK
        LEFT JOIN dich_vu dv ON dv.ID_DV = lh.ID_DV
        $where
        ORDER BY lh.THOI_GIAN_BAT_DAU DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    bindDynamic($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows ?: [];
}

function fetchRevenueMap(mysqli $conn, int $branchId, array $filters): array
{
    [$where, $types, $params] = buildFilterData($branchId, $filters);
    $sql = "
        SELECT lh.ID_TK, SUM(hd.TONG_TIEN) AS total_revenue
        FROM lich_hen lh
        JOIN hoa_don hd ON hd.ID_LICHHEN = lh.ID_LICHHEN AND hd.TRANGTHAI_THANHTOAN = 'Đã thanh toán'
        JOIN khach_hang kh ON kh.ID_TK = lh.ID_TK
        $where
        GROUP BY lh.ID_TK
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    bindDynamic($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $map = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $map[$row['ID_TK']] = (float) $row['total_revenue'];
        }
    }
    $stmt->close();
    return $map;
}

function fetchRatingMap(mysqli $conn, int $branchId, array $filters): array
{
    [$where, $types, $params] = buildFilterData($branchId, $filters);
    $sql = "
        SELECT lh.ID_TK, AVG(ph.XEP_HANG_DV) AS avg_rating, COUNT(ph.ID_DV) AS feedback_count
        FROM phan_hoi_cua_khach_hang ph
        JOIN lich_hen lh ON lh.ID_TK = ph.ID_TK AND lh.ID_DV = ph.ID_DV
        JOIN khach_hang kh ON kh.ID_TK = lh.ID_TK
        $where
        GROUP BY lh.ID_TK
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    bindDynamic($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $map = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $map[$row['ID_TK']] = [
                'avg_rating'    => $row['avg_rating'] !== null ? round((float) $row['avg_rating'], 1) : null,
                'feedback_count'=> (int) $row['feedback_count'],
            ];
        }
    }
    $stmt->close();
    return $map;
}

function aggregateCustomers(array $appointments, array $revenueMap, array $ratingMap): array
{
    $customers = [];
    foreach ($appointments as $row) {
        $id = $row['ID_TK'];
        if (!isset($customers[$id])) {
            $customers[$id] = [
                'ID_TK'          => $id,
                'HO_TEN'         => $row['HO_TEN'],
                'EMAIL'          => $row['EMAIL'],
                'SDT'            => $row['SDT'],
                'DIA_CHI'        => $row['DIA_CHI'],
                'NGAY_SINH'      => $row['NGAY_SINH'],
                'total_bookings' => 0,
                'completed_count'=> 0,
                'pending_count'  => 0,
                'upcoming_count' => 0,
                'first_booking'  => null,
                'last_booking'   => null,
                'next_booking'   => null,
                'services'       => [],
            ];
        }

        $customers[$id]['total_bookings']++;
        $ts = $row['THOI_GIAN_BAT_DAU'];
        if ($ts && (!$customers[$id]['first_booking'] || $ts < $customers[$id]['first_booking'])) {
            $customers[$id]['first_booking'] = $ts;
        }
        if ($ts && (!$customers[$id]['last_booking'] || $ts > $customers[$id]['last_booking'])) {
            $customers[$id]['last_booking'] = $ts;
        }
        $timestamp = $ts ? strtotime($ts) : null;
        if ($timestamp !== null && $timestamp >= time()) {
            $customers[$id]['upcoming_count']++;
            if (!$customers[$id]['next_booking'] || $ts < $customers[$id]['next_booking']) {
                $customers[$id]['next_booking'] = $ts;
            }
        }
        $status = $row['TRANGTHAI'];
        if (in_array($status, ['Đang chờ', 'Đã xác nhận'], true)) {
            $customers[$id]['pending_count']++;
        }
        if ($status === 'Đã hoàn thành') {
            $customers[$id]['completed_count']++;
        }
        if (!empty($row['TEN_DV'])) {
            $customers[$id]['services'][$row['TEN_DV']] = true;
        }
    }

    foreach ($customers as $id => &$customer) {
        $customer['services'] = $customer['services'] ? implode(', ', array_keys($customer['services'])) : '—';
        $customer['total_revenue'] = $revenueMap[$id] ?? 0.0;
        $customer['avg_rating'] = $ratingMap[$id]['avg_rating'] ?? null;
        $customer['feedback_count'] = $ratingMap[$id]['feedback_count'] ?? 0;
    }
    unset($customer);

    return array_values($customers);
}

function determineStatus(array $customer): array
{
    $now = new DateTimeImmutable('now');
    $last = $customer['last_booking'] ? new DateTimeImmutable($customer['last_booking']) : null;
    $days = $last ? $now->diff($last)->days : null;

    if ($customer['upcoming_count'] > 0) {
        return ['upcoming', 'Có lịch sắp tới'];
    }
    if ($last === null) {
        return ['no-history', 'Chưa từng đặt'];
    }
    if ($days !== null && $days <= 30) {
        return ['active', 'Quay lại gần đây'];
    }
    if ($days !== null && $days <= 90) {
        return ['cooldown', 'Ngưng tương tác'];
    }
    return ['at-risk', 'Nguy cơ rời đi'];
}

function determineSegment(array $customer): string
{
    if ($customer['total_bookings'] >= 5 || $customer['total_revenue'] >= 5000000) {
        return 'VIP';
    }
    if ($customer['total_bookings'] >= 3) {
        return 'Trung thành';
    }
    if ($customer['total_bookings'] >= 1) {
        return 'Tiềm năng';
    }
    return 'Khách mới';
}

function fetchNotes(mysqli $conn, int $branchId, array $customerIds): array
{
    if (!$customerIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
    $sql = "SELECT ID_TK, NOTE, TAG, LAST_CONTACT, NEXT_ACTION, UPDATED_AT FROM branch_customer_notes WHERE ID_CN = ? AND ID_TK IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $types = 'i' . str_repeat('s', count($customerIds));
    $params = array_merge([$branchId], $customerIds);
    bindDynamic($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $notes = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $notes[$row['ID_TK']] = $row;
        }
    }
    $stmt->close();
    return $notes;
}

function formatCurrency(float $value): string
{
    return number_format($value, 0, ',', '.') . ' đ';
}

function formatDateLabel(?string $value): string
{
    if (!$value) {
        return '—';
    }
    $dt = new DateTime($value);
    return $dt->format('d/m/Y H:i');
}

function fetchServiceTrends(mysqli $conn, int $branchId): array
{
    $sql = "
        SELECT dv.TEN_DV, COUNT(*) AS total
        FROM lich_hen lh
        JOIN dich_vu dv ON dv.ID_DV = lh.ID_DV
        WHERE lh.ID_CHINHANH = ? AND lh.THOI_GIAN_BAT_DAU >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY dv.ID_DV, dv.TEN_DV
        ORDER BY total DESC
        LIMIT 5
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $branchId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows ?: [];
}

$currentAccount = $_SESSION['ID_TK'] ?? null;
if (!$currentAccount) {
    echo '<div class="rounded-xl bg-white p-8 text-center text-red-600 shadow">Vui lòng đăng nhập để xem dữ liệu khách hàng.</div>';
    return;
}

$branchStmt = $conn->prepare('SELECT nv.ID_CN, cn.TEN_CN FROM nhan_vien nv JOIN chi_nhanh cn ON cn.ID_CN = nv.ID_CN WHERE nv.ID_TK = ? LIMIT 1');
$branchStmt->bind_param('s', $currentAccount);
$branchStmt->execute();
$branchStmt->bind_result($branchId, $branchName);
$branchStmt->fetch();
$branchStmt->close();

if (!$branchId) {
    echo '<div class="rounded-xl bg-white p-8 text-center text-red-600 shadow">Tài khoản của bạn chưa được gán chi nhánh. Liên hệ quản trị viên để được hỗ trợ.</div>';
    return;
}

ensureBranchCustomerNotesTable($conn);

// Note functionality removed

$filters = [
    'keyword' => trim($_GET['keyword'] ?? ''),
    'status'  => $_GET['status'] ?? '',
    'segment' => $_GET['segment'] ?? '',
    'rating'  => $_GET['rating'] ?? '',
    'from'    => normalizeDate($_GET['from'] ?? null),
    'to'      => normalizeDate($_GET['to'] ?? null),
    'sort'    => $_GET['sort'] ?? 'recent',
];

$appointments = fetchAppointments($conn, (int) $branchId, $filters);
$revenueMap = fetchRevenueMap($conn, (int) $branchId, $filters);
$ratingMap = fetchRatingMap($conn, (int) $branchId, $filters);
$customers = aggregateCustomers($appointments, $revenueMap, $ratingMap);

$summary = [
    'total'          => count($customers),
    'vip'            => 0,
    'active'         => 0,
    'new_this_month' => 0,
    'revenue'        => 0.0,
    'avg_rating'     => 0.0,
    'rating_count'   => 0,
];

foreach ($customers as &$customer) {
    [$statusCode, $statusLabel] = determineStatus($customer);
    $customer['status_code'] = $statusCode;
    $customer['status_label'] = $statusLabel;
    $segment = determineSegment($customer);
    $customer['segment'] = $segment;
    $summary['revenue'] += $customer['total_revenue'];
    if ($customer['avg_rating']) {
        $summary['avg_rating'] += $customer['avg_rating'];
        $summary['rating_count']++;
    }
    if (in_array($segment, ['VIP', 'VIP (đánh dấu)'], true)) {
        $summary['vip']++;
    }
    if (in_array($statusCode, ['active', 'upcoming'], true)) {
        $summary['active']++;
    }
    $first = $customer['first_booking'] ? new DateTimeImmutable($customer['first_booking']) : null;
    if ($first && $first->format('Y-m') === $now->format('Y-m')) {
        $summary['new_this_month']++;
    }
}
unset($customer);

if ($summary['rating_count'] > 0) {
    $summary['avg_rating'] = round($summary['avg_rating'] / max(1, $summary['rating_count']), 1);
} else {
    $summary['avg_rating'] = 0.0;
}

$filtered = array_filter($customers, function ($customer) use ($filters) {
    if ($filters['status'] && $customer['status_code'] !== $filters['status']) {
        return false;
    }
    if ($filters['segment'] && stripos($customer['segment'], $filters['segment']) === false) {
        return false;
    }
    if ($filters['rating']) {
        if ($filters['rating'] === '4up' && ($customer['avg_rating'] ?? 0) < 4) {
            return false;
        }
        if ($filters['rating'] === '5' && round($customer['avg_rating'] ?? 0, 0) !== 5) {
            return false;
        }
    }
    return true;
});

$sort = $filters['sort'];
usort($filtered, function ($a, $b) use ($sort) {
    switch ($sort) {
        case 'value':
            return $b['total_revenue'] <=> $a['total_revenue'];
        case 'bookings':
            return $b['total_bookings'] <=> $a['total_bookings'];
        case 'name':
            return strcmp($a['HO_TEN'], $b['HO_TEN']);
        case 'recent':
        default:
            return strcmp($b['last_booking'] ?? '', $a['last_booking'] ?? '');
    }
});

$perPage = 8;
$totalFiltered = count($filtered);
$page = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
$totalPages = max(1, (int) ceil($totalFiltered / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$pagedCustomers = array_slice($filtered, $offset, $perPage);

$serviceTrends = fetchServiceTrends($conn, (int) $branchId);
?>
<div class="space-y-6">
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-indigo-700">Khách hàng chi nhánh <?= htmlspecialchars($branchName) ?></h1>
      <p class="text-sm text-gray-500">Theo dõi hành trình, doanh thu và mức độ trung thành của khách hàng trong 90 ngày gần nhất.</p>
    </div>
    <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700">ID chi nhánh: CN<?= (int) $branchId ?></div>
  </div>

  <?php if (!empty($_SESSION['branch_customer_flash'])): ?>
    <?php $flash = $_SESSION['branch_customer_flash']; unset($_SESSION['branch_customer_flash']); ?>
    <div class="rounded-lg px-4 py-3 text-sm <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
      <?= htmlspecialchars($flash['message']) ?>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow">
      <p class="text-xs uppercase text-gray-500">Tổng khách hàng</p>
      <p class="text-3xl font-bold text-gray-900"><?= number_format($summary['total']) ?></p>
      <p class="mt-1 text-xs text-gray-500"><?= number_format($summary['new_this_month']) ?> mới trong tháng này</p>
    </div>
    <div class="rounded-xl border border-purple-200 bg-purple-50 p-4 shadow">
      <p class="text-xs uppercase text-purple-700">Khách hàng giá trị</p>
      <p class="text-3xl font-bold text-purple-700"><?= number_format($summary['vip']) ?></p>
      <p class="mt-1 text-xs text-purple-600"><?= number_format($summary['active']) ?> đang hoạt động</p>
    </div>
    <div class="rounded-xl border border-green-200 bg-green-50 p-4 shadow">
      <p class="text-xs uppercase text-green-700">Doanh thu theo khách</p>
      <p class="text-3xl font-bold text-green-700"><?= $summary['total'] ? formatCurrency($summary['revenue'] / max(1, $summary['total'])) : '0 đ' ?></p>
      <p class="mt-1 text-xs text-green-600">Tổng <?= formatCurrency($summary['revenue']) ?></p>
    </div>
  </div>

  <form class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow text-sm" method="GET">
    <input type="hidden" name="page" value="customers">
    <div>
      <label class="text-xs font-semibold text-gray-500">Từ khóa</label>
      <input type="text" name="keyword" value="<?= htmlspecialchars($filters['keyword']) ?>" placeholder="Tên, email, SĐT" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:outline-none">
    </div>
    <div class="grid grid-cols-2 gap-2">
      <div>
        <label class="text-xs font-semibold text-gray-500">Từ ngày</label>
        <input type="date" name="from" value="<?= htmlspecialchars($filters['from'] ?? '') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500">
      </div>
      <div>
        <label class="text-xs font-semibold text-gray-500">Đến ngày</label>
        <input type="date" name="to" value="<?= htmlspecialchars($filters['to'] ?? '') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500">
      </div>
    </div>
    <div>
      <label class="text-xs font-semibold text-gray-500">Trạng thái</label>
      <select name="status" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500">
        <option value="">Tất cả</option>
        <?php $statusOptions = ['upcoming' => 'Có lịch sắp tới', 'active' => 'Hoạt động', 'cooldown' => 'Ngưng tương tác', 'at-risk' => 'Nguy cơ rời đi', 'no-history' => 'Chưa từng đặt']; ?>
        <?php foreach ($statusOptions as $value => $label): ?>
          <option value="<?= $value ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="text-xs font-semibold text-gray-500">Phân khúc</label>
      <select name="segment" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500">
        <option value="">Tất cả</option>
        <?php foreach (['VIP','Trung thành','Tiềm năng','Khách mới'] as $segment): ?>
          <option value="<?= $segment ?>" <?= $filters['segment'] === $segment ? 'selected' : '' ?>><?= $segment ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="text-xs font-semibold text-gray-500">Điểm đánh giá</label>
      <select name="rating" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500">
        <option value="">Tất cả</option>
        <option value="4up" <?= $filters['rating'] === '4up' ? 'selected' : '' ?>>≥ 4 sao</option>
        <option value="5" <?= $filters['rating'] === '5' ? 'selected' : '' ?>>5 sao</option>
      </select>
    </div>
    <div>
      <label class="text-xs font-semibold text-gray-500">Sắp xếp</label>
      <select name="sort" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500">
        <?php $sortOptions = ['recent' => 'Gần đây nhất', 'value' => 'Giá trị cao nhất', 'bookings' => 'Nhiều lịch nhất', 'name' => 'Theo tên']; ?>
        <?php foreach ($sortOptions as $value => $label): ?>
          <option value="<?= $value ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex items-end justify-end gap-2">
      <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white shadow hover:bg-indigo-700">Áp dụng</button>
      <a href="?page=customers" class="rounded-lg bg-gray-100 px-4 py-2 font-semibold text-gray-600 hover:bg-gray-200">Xóa lọc</a>
    </div>
  </form>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 overflow-hidden rounded-xl border border-gray-200 bg-white shadow">
      <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
        <h2 class="text-lg font-semibold text-gray-800">Danh sách khách hàng (<?= number_format($totalFiltered) ?>)</h2>
      </div>
      <div class="divide-y divide-gray-100">
        <?php if ($pagedCustomers): ?>
          <div class="hidden lg:grid lg:grid-cols-12 gap-2 px-6 py-3 bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase">
            <div class="lg:col-span-3">Khách hàng</div>
            <div class="lg:col-span-2">Liên hệ</div>
            <div class="lg:col-span-2">Lịch gần nhất</div>
            <div class="lg:col-span-2">Doanh thu</div>
            <div class="lg:col-span-2">Trạng thái & Hành động</div>
          </div>
          <?php foreach ($pagedCustomers as $customer): ?>
            <?php
              $payload = json_encode([
                'id' => $customer['ID_TK'],
                'name' => $customer['HO_TEN'],
                'email' => $customer['EMAIL'],
                'phone' => $customer['SDT'],
                'address' => $customer['DIA_CHI'],
                'services' => $customer['services'],
                'first_booking' => $customer['first_booking'],
                'last_booking' => $customer['last_booking'],
                'next_booking' => $customer['next_booking'],
                'total_bookings' => $customer['total_bookings'],
                'total_revenue' => $customer['total_revenue'],
                'status_label' => $customer['status_label'],
                'segment' => $customer['segment'],
                'avg_rating' => $customer['avg_rating'],
              ], JSON_UNESCAPED_UNICODE);
            ?>
            <!-- Desktop View (Table-like) -->
            <div class="hidden lg:grid lg:grid-cols-12 gap-2 px-6 py-4 items-start border-b border-gray-100 hover:bg-gray-50">
              <!-- Column 1: Customer Info -->
              <div class="lg:col-span-3">
                <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($customer['HO_TEN']) ?></p>
                <p class="text-xs text-gray-500">ID: <?= htmlspecialchars($customer['ID_TK']) ?></p>
                <p class="text-xs text-gray-500"><?= htmlspecialchars($customer['segment']) ?></p>
              </div>
              
              <!-- Column 2: Contact -->
              <div class="lg:col-span-2">
                <p class="text-xs text-gray-600"><i class="fas fa-phone mr-1 text-gray-400"></i><?= htmlspecialchars($customer['SDT']) ?></p>
                <p class="text-xs text-gray-600"><i class="fas fa-envelope mr-1 text-gray-400"></i><?= htmlspecialchars($customer['EMAIL']) ?></p>
              </div>
              
              <!-- Column 3: Last Booking -->
              <div class="lg:col-span-2">
                <p class="text-sm font-semibold text-gray-800"><?= formatDateLabel($customer['last_booking']) ?></p>
                <p class="text-xs text-gray-500">Hoàn thành: <?= $customer['completed_count'] ?> / <?= $customer['total_bookings'] ?></p>
              </div>
              
              <!-- Column 4: Revenue -->
              <div class="lg:col-span-2">
                <p class="text-sm font-semibold text-green-700"><?= formatCurrency((float) $customer['total_revenue']) ?></p>
                <p class="text-xs text-gray-500">Lịch: <?= $customer['total_bookings'] ?></p>
              </div>
              
              <!-- Column 5: Status & Actions -->
              <div class="lg:col-span-2 flex items-center gap-1 flex-wrap">
                <span class="rounded-full bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700"><?= htmlspecialchars($customer['status_label']) ?></span>
                <?php if ($customer['avg_rating']): ?>
                  <span class="rounded-full bg-yellow-50 px-2 py-1 text-xs font-semibold text-yellow-700"><i class="fas fa-star mr-0.5"></i><?= $customer['avg_rating'] ?></span>
                <?php endif; ?>
                <button data-customer='<?= htmlspecialchars($payload, ENT_QUOTES, 'UTF-8') ?>' class="rounded px-2 py-1 text-xs font-semibold border border-gray-200 text-gray-600 hover:border-indigo-400 hover:text-indigo-600 hover:bg-indigo-50">Xem</button>
              </div>
            </div>

            <!-- Mobile View (Card) -->
            <div class="lg:hidden px-4 py-4 border-b border-gray-100">
              <div class="flex justify-between items-start mb-3">
                <div>
                  <p class="font-semibold text-gray-900"><?= htmlspecialchars($customer['HO_TEN']) ?></p>
                  <p class="text-xs text-gray-500">ID: <?= htmlspecialchars($customer['ID_TK']) ?> • <?= htmlspecialchars($customer['segment']) ?></p>
                </div>
                <span class="rounded-full bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 whitespace-nowrap"><?= htmlspecialchars($customer['status_label']) ?></span>
              </div>
              
              <div class="grid grid-cols-2 gap-3 text-xs mb-3">
                <div>
                  <p class="text-gray-500 font-semibold">Liên hệ</p>
                  <p class="text-gray-700"><i class="fas fa-phone mr-1"></i><?= htmlspecialchars($customer['SDT']) ?></p>
                  <p class="text-gray-700"><i class="fas fa-envelope mr-1"></i><?= htmlspecialchars($customer['EMAIL']) ?></p>
                </div>
                <div>
                  <p class="text-gray-500 font-semibold">Lịch gần nhất</p>
                  <p class="text-gray-700"><?= formatDateLabel($customer['last_booking']) ?></p>
                  <p class="text-gray-600">Hoàn thành: <?= $customer['completed_count'] ?></p>
                </div>
              </div>
              
              <div class="grid grid-cols-2 gap-3 text-xs mb-3">
                <div>
                  <p class="text-gray-500 font-semibold">Doanh thu</p>
                  <p class="text-green-700 font-semibold"><?= formatCurrency((float) $customer['total_revenue']) ?></p>
                  <p class="text-gray-600">Lịch: <?= $customer['total_bookings'] ?></p>
                </div>
                <div>
                  <p class="text-gray-500 font-semibold">Đánh giá</p>
                  <?php if ($customer['avg_rating']): ?>
                    <p class="text-yellow-700"><i class="fas fa-star mr-1"></i><?= $customer['avg_rating'] ?></p>
                  <?php else: ?>
                    <p class="text-gray-500">Chưa có</p>
                  <?php endif; ?>
                </div>
              </div>
              
              <div class="flex gap-2">
                <button data-customer='<?= htmlspecialchars($payload, ENT_QUOTES, 'UTF-8') ?>' class="flex-1 rounded px-3 py-2 text-xs font-semibold border border-gray-200 text-gray-600 hover:border-indigo-400 hover:text-indigo-600 hover:bg-indigo-50">Xem hồ sơ</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="px-6 py-10 text-center text-gray-500">Không tìm thấy khách hàng nào phù hợp bộ lọc.</div>
        <?php endif; ?>
      </div>
      <?php if ($totalPages > 1): ?>
        <?php $paginationQuery = $_GET; $paginationQuery['page'] = 'customers'; ?>
        <div class="flex flex-wrap items-center justify-center gap-2 border-t border-gray-100 px-6 py-4 text-sm">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php $paginationQuery['p'] = $i; $pageUrl = '?' . http_build_query($paginationQuery); ?>
            <a href="<?= htmlspecialchars($pageUrl) ?>" class="rounded-lg border px-3 py-1 <?= $i === $page ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 text-gray-600 hover:border-indigo-400' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="space-y-4">
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow">
        <h3 class="text-base font-semibold text-gray-800">Insight nhanh</h3>
        <ul class="mt-3 space-y-2 text-sm text-gray-600">
          <li>• Dịch vụ được đặt nhiều: <?= $serviceTrends ? htmlspecialchars($serviceTrends[0]['TEN_DV']) . ' (' . $serviceTrends[0]['total'] . ')' : 'Chưa có dữ liệu' ?></li>
          <li>• Điểm hài lòng trung bình: <?= $summary['avg_rating'] ?></li>
        </ul>
        <?php if ($serviceTrends): ?>
          <div class="mt-4 space-y-2">
            <?php foreach ($serviceTrends as $service): ?>
              <div>
                <div class="flex justify-between text-xs text-gray-500">
                  <span><?= htmlspecialchars($service['TEN_DV']) ?></span>
                  <span><?= $service['total'] ?> lịch</span>
                </div>
                <div class="mt-1 h-2 w-full rounded-full bg-gray-100">
                  <div class="h-2 rounded-full bg-indigo-500" style="width: <?= min(100, $service['total'] * 10) ?>%"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div id="customerDrawer" class="rounded-xl border border-gray-200 bg-white p-5 shadow">
        <h3 class="text-base font-semibold text-gray-800">Hồ sơ khách hàng</h3>
        <p class="mt-1 text-sm text-gray-500">Chọn "Xem hồ sơ" để hiển thị thông tin chi tiết.</p>
        <div class="mt-4 space-y-3 text-sm text-gray-700 hidden" id="customerProfile">
          <p><strong>Khách hàng:</strong> <span id="profileName"></span></p>
          <p><strong>Liên hệ:</strong> <span id="profileContact"></span></p>
          <p><strong>Phân khúc:</strong> <span id="profileSegment"></span></p>
          <p><strong>Dịch vụ ưa thích:</strong> <span id="profileServices"></span></p>
          <p><strong>Lịch gần nhất:</strong> <span id="profileLast"></span></p>
          <p><strong>Doanh thu tích lũy:</strong> <span id="profileValue"></span></p>
          <p><strong>Ghi chú nội bộ:</strong> <span id="profileNote"></span></p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Utility: Get base API path - detect from document root
function getApiPath(endpoint) {
  const pathname = window.location.pathname;
  console.log('[getApiPath] pathname:', pathname);
  
  // Detect base path from URL
  // If URL contains /StygianBlue/, use absolute path /StygianBlue/api/
  // Otherwise use relative path
  let basePath = '../../../api/';
  
  if (pathname.includes('/StygianBlue/')) {
    basePath = '/StygianBlue/api/';
  }
  
  const fullPath = basePath + endpoint;
  console.log('[getApiPath] returning:', fullPath);
  return fullPath;
}

const profile = document.getElementById('customerProfile');
const profileName = document.getElementById('profileName');
const profileContact = document.getElementById('profileContact');
const profileSegment = document.getElementById('profileSegment');
const profileServices = document.getElementById('profileServices');
const profileLast = document.getElementById('profileLast');
const profileValue = document.getElementById('profileValue');
const profileNote = document.getElementById('profileNote');

document.querySelectorAll('[data-customer]').forEach(btn => {
  btn.addEventListener('click', () => {
    const payload = JSON.parse(btn.dataset.customer);
    profile.classList.remove('hidden');
    profileName.textContent = `${payload.name} (ID: ${payload.id})`;
    profileContact.textContent = `${payload.phone} • ${payload.email}`;
    profileSegment.textContent = payload.segment;
    profileServices.textContent = payload.services || '—';
    profileLast.textContent = payload.last_booking ? new Date(payload.last_booking).toLocaleString('vi-VN') : '—';
    profileValue.textContent = new Intl.NumberFormat('vi-VN').format(payload.total_revenue || 0) + ' đ';
    profileNote.textContent = payload.note || 'Chưa có ghi chú';
  });
});

document.getElementById('closeNoteModal').addEventListener('click', closeNoteModal);
document.getElementById('cancelNoteModal').addEventListener('click', closeNoteModal);
noteModal.addEventListener('click', (e) => {
  if (e.target === noteModal) {
    closeNoteModal();
  }
});

// Handle note form submission via AJAX
const noteFormElement = noteModal.querySelector('form');
if (noteFormElement) {
  noteFormElement.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(noteFormElement);
    formData.append('action', 'save_note');
    
    try {
      const response = await fetch(getApiPath('branch-customers.php'), {
        method: 'POST',
        body: formData
      });
      
      const result = await response.json();
      
      if (result.success) {
        // Show success message
        const toast = document.createElement('div');
        toast.className = 'fixed top-4 right-4 z-50 rounded-lg bg-green-50 text-green-700 border border-green-200 px-4 py-3';
        toast.textContent = result.message || 'Đã lưu ghi chú khách hàng.';
        document.body.appendChild(toast);
        
        setTimeout(() => {
          toast.remove();
          closeNoteModal();
        }, 2000);
      } else {
        // Show error message
        const toast = document.createElement('div');
        toast.className = 'fixed top-4 right-4 z-50 rounded-lg bg-red-50 text-red-700 border border-red-200 px-4 py-3';
        toast.textContent = result.message || 'Lưu ghi chú thất bại.';
        document.body.appendChild(toast);
        
        setTimeout(() => toast.remove(), 3000);
      }
    } catch (err) {
      console.error('Error:', err);
      const toast = document.createElement('div');
      toast.className = 'fixed top-4 right-4 z-50 rounded-lg bg-red-50 text-red-700 border border-red-200 px-4 py-3';
      toast.textContent = 'Lỗi kết nối. Vui lòng thử lại.';
      document.body.appendChild(toast);
      
      setTimeout(() => toast.remove(), 3000);
    }
  });
}
</script>
