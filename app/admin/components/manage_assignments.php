<?php
include '../../database/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

$limit = 20;
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$fromDate = $_GET['from'] ?? '';
$toDate = $_GET['to'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

$currentFilters = [
    'page' => 'assignments',
    'p' => $page,
    'search' => $search,
    'status' => $statusFilter,
    'from' => $fromDate,
    'to' => $toDate,
    'sort' => $sort,
];

function buildAssignmentQuery($filters, $overrides = [])
{
    $merged = array_merge($filters, $overrides);
    $pairs = [];
    foreach ($merged as $key => $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $pairs[] = urlencode($key) . '=' . urlencode($value);
    }

    return $pairs ? '?' . implode('&', $pairs) : '';
}

function htmlEscape($value)
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function fetchCount($conn, $query)
{
    $result = mysqli_query($conn, $query);
    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_assoc($result);
    return isset($row['total']) ? (int) $row['total'] : 0;
}

function getAssignmentStatusMeta($startTime, $endTime, $appointmentStatus = '')
{
    $now = new DateTime('now');
    $start = new DateTime($startTime);
    $end = new DateTime($endTime);

    $normalizedStatus = strtolower($appointmentStatus ?? '');
    $isCompleted = strpos($normalizedStatus, 'hoàn') !== false || strpos($normalizedStatus, 'done') !== false;

    if ($now < $start) {
        return ['label' => 'Sắp diễn ra', 'class' => 'bg-blue-100 text-blue-700'];
    }

    if ($now >= $start && $now <= $end) {
        return ['label' => 'Đang thực hiện', 'class' => 'bg-amber-100 text-amber-700'];
    }

    if ($isCompleted) {
        return ['label' => 'Đã hoàn tất', 'class' => 'bg-emerald-100 text-emerald-700'];
    }

    return ['label' => 'Quá hạn', 'class' => 'bg-rose-100 text-rose-700'];
}

function formatDateTime($value)
{
    return $value ? date('d/m/Y H:i', strtotime($value)) : '--';
}

$conditions = ["tk.ID_QUYEN = 2"];

if ($search !== '') {
    $safeSearch = mysqli_real_escape_string($conn, $search);
    $conditions[] = "(tk.HO_TEN LIKE '%$safeSearch%' OR lh.DIA_CHI_HEN LIKE '%$safeSearch%' OR CAST(h.ID_HD AS CHAR) LIKE '%$safeSearch%' OR CAST(lh.ID_LICHHEN AS CHAR) LIKE '%$safeSearch%')";
}

if ($fromDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
    $safeFrom = mysqli_real_escape_string($conn, $fromDate);
    $conditions[] = "DATE(pc.THOI_GIAN_BAT_DAU) >= '$safeFrom'";
}

if ($toDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    $safeTo = mysqli_real_escape_string($conn, $toDate);
    $conditions[] = "DATE(pc.THOI_GIAN_BAT_DAU) <= '$safeTo'";
}

switch ($statusFilter) {
    case 'upcoming':
        $conditions[] = "pc.THOI_GIAN_BAT_DAU > NOW()";
        break;
    case 'in-progress':
        $conditions[] = "NOW() BETWEEN pc.THOI_GIAN_BAT_DAU AND pc.THOI_GIAN_KET_THUC";
        break;
    case 'completed':
        $conditions[] = "pc.THOI_GIAN_KET_THUC <= NOW()";
        break;
    case 'overdue':
        $conditions[] = "pc.THOI_GIAN_KET_THUC < NOW()";
        break;
}

$sortMap = [
    'newest' => 'pc.THOI_GIAN_BAT_DAU DESC',
    'oldest' => 'pc.THOI_GIAN_BAT_DAU ASC',
    'name' => 'tk.HO_TEN ASC',
    'invoice' => 'h.ID_HD DESC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['newest'];

$whereClause = implode(' AND ', $conditions);

$countQuery = "
    SELECT COUNT(*) as total
    FROM phan_cong_nhan_vien pc
    JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
    JOIN tai_khoan tk ON tk.ID_TK = pc.ID_TK
    JOIN hoa_don h ON lh.ID_LICHHEN = h.ID_LICHHEN
    WHERE $whereClause
";
$countResult = mysqli_query($conn, $countQuery);

if (!$countResult) {
    die('Lỗi truy vấn SQL: ' . mysqli_error($conn));
}

$countRow = mysqli_fetch_assoc($countResult);
$totalRows = isset($countRow['total']) ? (int) $countRow['total'] : 0;
$totalPages = max(1, ceil($totalRows / $limit));

$query = "
    SELECT pc.ID_LICHHEN, tk.HO_TEN, lh.DIA_CHI_HEN, pc.THOI_GIAN_BAT_DAU, pc.THOI_GIAN_KET_THUC, pc.ID_TK, h.ID_HD, lh.TRANGTHAI AS TRANGTHAI_LICH
    FROM phan_cong_nhan_vien pc
    JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
    JOIN hoa_don h ON lh.ID_LICHHEN = h.ID_LICHHEN
    JOIN tai_khoan tk ON tk.ID_TK = pc.ID_TK
    WHERE $whereClause
    ORDER BY $orderBy
    LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $query);

if (!$result) {
    die('Lỗi truy vấn SQL: ' . mysqli_error($conn));
}

$requestQuery = "
    SELECT yc.*, tk.HO_TEN
    FROM yeu_cau_thay_doi_lich yc
    JOIN tai_khoan tk ON yc.ID_TK = tk.ID_TK
    WHERE yc.TRANGTHAI = 'Chờ duyệt'
    ORDER BY yc.NGAY_GUI DESC
";
$requests = mysqli_query($conn, $requestQuery);

if (!$requests) {
    die('Lỗi truy vấn yêu cầu: ' . mysqli_error($conn));
}

$requestCount = mysqli_num_rows($requests);
mysqli_data_seek($requests, 0);

$unassignedCount = fetchCount($conn, "
    SELECT COUNT(*) as total
    FROM lich_hen lh
    WHERE lh.TRANGTHAI = 'Đã xác nhận'
    AND NOT EXISTS (
        SELECT 1 FROM phan_cong_nhan_vien pc WHERE pc.ID_LICHHEN = lh.ID_LICHHEN
    )
");

$unassignedList = mysqli_query($conn, "
    SELECT lh.ID_LICHHEN, lh.DIA_CHI_HEN, lh.THOI_GIAN_BAT_DAU
    FROM lich_hen lh
    WHERE lh.TRANGTHAI = 'Đã xác nhận'
    AND NOT EXISTS (
        SELECT 1 FROM phan_cong_nhan_vien pc WHERE pc.ID_LICHHEN = lh.ID_LICHHEN
    )
    ORDER BY lh.THOI_GIAN_BAT_DAU ASC
    LIMIT 5
");
$unassignedListCount = $unassignedList ? mysqli_num_rows($unassignedList) : 0;

$stats = [
    'thisWeek' => fetchCount($conn, "
        SELECT COUNT(*) as total
        FROM phan_cong_nhan_vien pc
        JOIN tai_khoan tk ON tk.ID_TK = pc.ID_TK
        WHERE tk.ID_QUYEN = 2 AND YEARWEEK(pc.THOI_GIAN_BAT_DAU, 1) = YEARWEEK(NOW(), 1)
    "),
    'upcoming' => fetchCount($conn, "
        SELECT COUNT(*) as total
        FROM phan_cong_nhan_vien pc
        JOIN tai_khoan tk ON tk.ID_TK = pc.ID_TK
        WHERE tk.ID_QUYEN = 2 AND pc.THOI_GIAN_BAT_DAU > NOW()
    "),
    'activeStaff' => fetchCount($conn, "
        SELECT COUNT(DISTINCT pc.ID_TK) as total
        FROM phan_cong_nhan_vien pc
        JOIN tai_khoan tk ON tk.ID_TK = pc.ID_TK
        WHERE tk.ID_QUYEN = 2 AND pc.THOI_GIAN_BAT_DAU >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    "),
    'pendingRequests' => $requestCount,
];

$quickStatusFilters = [
    '' => 'Tất cả',
    'upcoming' => 'Sắp diễn ra',
    'in-progress' => 'Đang thực hiện',
    'completed' => 'Đã hoàn tất',
    'overdue' => 'Quá hạn',
];

$activeFilters = [];
if ($search !== '') {
    $activeFilters[] = [
        'label' => 'Từ khóa: ' . $search,
        'url' => buildAssignmentQuery($currentFilters, ['search' => '', 'p' => 1]),
    ];
}
if ($fromDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
    $activeFilters[] = [
        'label' => 'Từ ngày: ' . date('d/m/Y', strtotime($fromDate)),
        'url' => buildAssignmentQuery($currentFilters, ['from' => '', 'p' => 1]),
    ];
}
if ($toDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    $activeFilters[] = [
        'label' => 'Đến ngày: ' . date('d/m/Y', strtotime($toDate)),
        'url' => buildAssignmentQuery($currentFilters, ['to' => '', 'p' => 1]),
    ];
}
if ($statusFilter !== '' && isset($quickStatusFilters[$statusFilter])) {
    $activeFilters[] = [
        'label' => 'Trạng thái: ' . $quickStatusFilters[$statusFilter],
        'url' => buildAssignmentQuery($currentFilters, ['status' => '', 'p' => 1]),
    ];
}

$tableHasRows = mysqli_num_rows($result) > 0;
$visibleCount = $totalRows > $offset ? min($limit, $totalRows - $offset) : 0;
?>

<body class="bg-gray-100 p-4">
    <div class="space-y-8">
        <section class="bg-gradient-to-r from-indigo-600 via-purple-600 to-rose-500 text-white rounded-2xl p-6 shadow-lg">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm uppercase tracking-wide opacity-80">Bảng điều phối</p>
                    <h1 class="text-3xl font-bold">Quản lý phân công nhân viên</h1>
                    <p class="text-sm opacity-90">Theo dõi tiến độ, duyệt yêu cầu và phân bổ nhân sự ngay trong một màn hình.</p>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <div class="bg-white/20 rounded-xl px-4 py-2 text-center">
                        <p class="text-xs uppercase opacity-80">Tổng phân công</p>
                        <p class="text-2xl font-semibold"><?= number_format($totalRows) ?></p>
                    </div>
                    <div class="bg-white/20 rounded-xl px-4 py-2 text-center">
                        <p class="text-xs uppercase opacity-80">Chờ phân công</p>
                        <p class="text-2xl font-semibold"><?= number_format($unassignedCount) ?></p>
                    </div>
                    <a href="./components/add_assignment.php" class="inline-flex items-center gap-2 bg-white text-indigo-700 font-semibold px-4 py-2 rounded-xl shadow transition hover:-translate-y-0.5">
                        <span>Thêm phân công</span>
                    </a>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="bg-white rounded-xl p-4 shadow flex flex-col gap-1">
                <p class="text-sm text-gray-500">Tuần này</p>
                <p class="text-2xl font-semibold text-indigo-700"><?= number_format($stats['thisWeek']) ?></p>
                <span class="text-xs text-gray-400">Phân công đã tạo</span>
            </div>
            <div class="bg-white rounded-xl p-4 shadow flex flex-col gap-1">
                <p class="text-sm text-gray-500">Sắp diễn ra</p>
                <p class="text-2xl font-semibold text-blue-600"><?= number_format($stats['upcoming']) ?></p>
                <span class="text-xs text-gray-400">Trong tương lai gần</span>
            </div>
            <div class="bg-white rounded-xl p-4 shadow flex flex-col gap-1">
                <p class="text-sm text-gray-500">Nhân sự hoạt động</p>
                <p class="text-2xl font-semibold text-emerald-600"><?= number_format($stats['activeStaff']) ?></p>
                <span class="text-xs text-gray-400">30 ngày gần nhất</span>
            </div>
            <div class="bg-white rounded-xl p-4 shadow flex flex-col gap-1">
                <p class="text-sm text-gray-500">Yêu cầu chờ duyệt</p>
                <p class="text-2xl font-semibold text-rose-600"><?= number_format($stats['pendingRequests']) ?></p>
                <span class="text-xs text-gray-400">Đổi lịch / hỗ trợ</span>
            </div>
        </section>

        <section class="bg-white rounded-2xl shadow p-6 space-y-4">
            <div class="flex flex-col gap-2">
                <h2 class="text-xl font-semibold text-gray-800">Bộ lọc nâng cao</h2>
                <p class="text-sm text-gray-500">Kết hợp nhiều điều kiện để tìm phân công cụ thể.</p>
            </div>
            <form id="filtersForm" method="GET" class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <input type="hidden" name="page" value="assignments">
                <input type="hidden" name="p" value="<?= $page ?>">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1" for="search">Từ khóa</label>
                    <input id="search" name="search" value="<?= htmlEscape($search) ?>" placeholder="Tên, mã lịch, mã hóa đơn" class="w-full rounded-lg border border-gray-200 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1" for="from">Từ ngày</label>
                    <input type="date" id="from" name="from" value="<?= htmlEscape($fromDate) ?>" class="w-full rounded-lg border border-gray-200 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1" for="to">Đến ngày</label>
                    <input type="date" id="to" name="to" value="<?= htmlEscape($toDate) ?>" class="w-full rounded-lg border border-gray-200 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1" for="sort">Sắp xếp</label>
                    <select id="sort" name="sort" class="w-full rounded-lg border border-gray-200 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Cũ nhất</option>
                        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Theo tên nhân viên</option>
                        <option value="invoice" <?= $sort === 'invoice' ? 'selected' : '' ?>>Theo mã hóa đơn</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1" for="status">Trạng thái</label>
                    <select id="status" name="status" class="w-full rounded-lg border border-gray-200 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        <option value="">Tất cả</option>
                        <?php foreach ($quickStatusFilters as $key => $label): ?>
                            <?php if ($key === '') { continue; } ?>
                            <option value="<?= $key ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end gap-3">
                    <button type="submit" class="flex-1 bg-indigo-600 text-white font-semibold rounded-lg px-4 py-2 shadow hover:bg-indigo-700">Áp dụng</button>
                    <button type="button" id="clearFilters" class="rounded-lg border border-gray-300 px-4 py-2 text-gray-600 hover:bg-gray-50">Xóa</button>
                </div>
            </form>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($quickStatusFilters as $key => $label): ?>
                    <?php $isActive = $statusFilter === $key || ($key === '' && $statusFilter === ''); ?>
                    <a href="<?= buildAssignmentQuery($currentFilters, ['status' => $key, 'p' => 1]) ?>"
                       class="px-3 py-1 rounded-full border text-sm <?= $isActive ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-300' ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($activeFilters)): ?>
                <div class="flex flex-wrap gap-2 pt-2 border-t border-gray-100">
                    <?php foreach ($activeFilters as $filter): ?>
                        <a href="<?= $filter['url'] ?>" class="inline-flex items-center gap-2 bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-xs hover:bg-gray-200">
                            <?= htmlEscape($filter['label']) ?>
                            <span aria-hidden="true">×</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2 bg-white rounded-2xl shadow p-6">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800">Danh sách phân công</h2>
                        <p class="text-sm text-gray-500">Trang <?= $page ?> / <?= $totalPages ?> &middot; <?= $totalRows ?> bản ghi</p>
                    </div>
                    <div class="text-sm text-gray-500">Hiển thị <?= $visibleCount ?> phân công</div>
                </div>
                <div class="overflow-x-auto">
                    <?php if (!$tableHasRows): ?>
                        <div class="py-10 text-center text-gray-500">Không có phân công nào phù hợp với bộ lọc hiện tại.</div>
                    <?php else: ?>
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-gray-600">
                                    <th class="px-3 py-2 text-left font-medium">#</th>
                                    <th class="px-3 py-2 text-left font-medium">Mã hóa đơn</th>
                                    <th class="px-3 py-2 text-left font-medium">Lịch hẹn</th>
                                    <th class="px-3 py-2 text-left font-medium">Nhân viên</th>
                                    <th class="px-3 py-2 text-left font-medium">Thời gian</th>
                                    <th class="px-3 py-2 text-left font-medium">Địa điểm</th>
                                    <th class="px-3 py-2 text-left font-medium">Trạng thái</th>
                                    <th class="px-3 py-2 text-center font-medium">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php $rowIndex = 0; ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <?php $statusMeta = getAssignmentStatusMeta($row['THOI_GIAN_BAT_DAU'], $row['THOI_GIAN_KET_THUC'], $row['TRANGTHAI_LICH']); ?>
                                    <tr class="hover:bg-indigo-50/40 transition">
                                        <td class="px-3 py-3 text-gray-500"><?= $offset + ++$rowIndex ?></td>
                                        <td class="px-3 py-3 font-semibold text-gray-800">#<?= htmlEscape($row['ID_HD']) ?></td>
                                        <td class="px-3 py-3">
                                            <p class="font-medium text-gray-800">Lịch #<?= htmlEscape($row['ID_LICHHEN']) ?></p>
                                            <p class="text-xs text-gray-500">Bắt đầu: <?= formatDateTime($row['THOI_GIAN_BAT_DAU']) ?></p>
                                        </td>
                                        <td class="px-3 py-3">
                                            <p class="font-semibold text-gray-800"><?= htmlEscape($row['HO_TEN']) ?></p>
                                            <p class="text-xs text-gray-500">ID: <?= htmlEscape($row['ID_TK']) ?></p>
                                        </td>
                                        <td class="px-3 py-3">
                                            <p class="text-sm text-gray-700">BĐ: <?= formatDateTime($row['THOI_GIAN_BAT_DAU']) ?></p>
                                            <p class="text-xs text-gray-500">KT: <?= formatDateTime($row['THOI_GIAN_KET_THUC']) ?></p>
                                        </td>
                                        <td class="px-3 py-3">
                                            <p class="text-sm text-gray-700" title="<?= htmlEscape($row['DIA_CHI_HEN']) ?>"><?= htmlEscape($row['DIA_CHI_HEN']) ?></p>
                                        </td>
                                        <td class="px-3 py-3">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold whitespace-nowrap <?= $statusMeta['class'] ?>">
                                                <?= $statusMeta['label'] ?>
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-center">
                                            <div class="flex flex-wrap justify-center gap-2">
                                                <a href="./components/edit_assignment.php?id=<?= $row['ID_LICHHEN'] ?>&employee_id=<?= $row['ID_TK'] ?>"
                                                   class="px-3 py-1 rounded-lg bg-indigo-600 text-white text-xs font-semibold shadow hover:bg-indigo-700">Sửa</a>
                                                <a href="./components/delete_assignment.php?id=<?= $row['ID_LICHHEN'] ?>&employee_id=<?= $row['ID_TK'] ?>"
                                                   class="px-3 py-1 rounded-lg bg-rose-600 text-white text-xs font-semibold shadow hover:bg-rose-700"
                                                   onclick="return confirm('Bạn chắc chắn muốn xóa phân công này?');">Xóa</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <?php if ($totalPages > 1): ?>
                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-gray-500">Đang ở trang <?= $page ?> / <?= $totalPages ?></p>
                        <div class="flex flex-wrap gap-2">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="<?= buildAssignmentQuery($currentFilters, ['p' => $i]) ?>"
                                   class="px-3 py-1 rounded-lg border text-sm font-semibold <?= $i == $page ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-300' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="space-y-6">
                <div class="rounded-2xl bg-gradient-to-br from-slate-900 via-indigo-900 to-indigo-700 text-white p-5 shadow-lg">
                    <p class="text-sm text-white/70">Hành động nhanh</p>
                    <h3 class="text-2xl font-semibold mb-4">Điều phối hiệu quả</h3>
                    <div class="space-y-2">
                        <a href="./components/add_assignment.php" class="block w-full text-center bg-white text-indigo-700 font-semibold rounded-lg px-4 py-2 shadow hover:-translate-y-0.5 transition">Tạo phân công mới</a>
                        <a href="<?= buildAssignmentQuery($currentFilters, ['status' => 'overdue', 'p' => 1]) ?>" class="block w-full text-center bg-white/10 border border-white/30 text-sm rounded-lg px-4 py-2 hover:bg-white/20 transition">Xem phân công quá hạn</a>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Lịch hẹn chờ phân công</h3>
                            <p class="text-sm text-gray-500"><?= $unassignedCount ?> lịch chưa có nhân sự</p>
                        </div>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-700">Ưu tiên</span>
                    </div>
                    <?php if ($unassignedList && $unassignedListCount > 0): ?>
                        <ul class="divide-y divide-gray-100">
                            <?php while ($item = mysqli_fetch_assoc($unassignedList)): ?>
                                <li class="py-3 flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-gray-800">Lịch #<?= htmlEscape($item['ID_LICHHEN']) ?></p>
                                        <p class="text-xs text-gray-500"><?= formatDateTime($item['THOI_GIAN_BAT_DAU']) ?></p>
                                        <p class="text-xs text-gray-500 truncate max-w-[200px]" title="<?= htmlEscape($item['DIA_CHI_HEN']) ?>"><?= htmlEscape($item['DIA_CHI_HEN']) ?></p>
                                    </div>
                                    <a href="./components/add_assignment.php?scheduleId=<?= $item['ID_LICHHEN'] ?>" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">Phân công</a>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-sm text-gray-500">Tất cả lịch đã được xử lý.</p>
                    <?php endif; ?>
                </div>

                <div class="bg-white rounded-2xl shadow p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Yêu cầu đổi lịch</h3>
                            <p class="text-sm text-gray-500">Theo dõi và phản hồi kịp thời</p>
                        </div>
                        <button id="requestToggle" type="button" class="px-3 py-1 rounded-lg bg-yellow-100 text-yellow-700 text-sm font-semibold"><?= $requestCount ?> yêu cầu</button>
                    </div>
                    <div id="requestPanel" class="mt-4 hidden opacity-0 -translate-y-2 transition">
                        <?php if ($requestCount === 0): ?>
                            <p class="text-sm text-gray-500">Hiện chưa có yêu cầu đổi lịch nào.</p>
                        <?php else: ?>
                            <ul class="space-y-4 max-h-80 overflow-y-auto pr-2">
                                <?php while ($rq = mysqli_fetch_assoc($requests)): ?>
                                    <li class="border border-gray-100 rounded-xl p-3">
                                        <p class="text-sm font-semibold text-gray-800"><?= htmlEscape($rq['HO_TEN']) ?> · Lịch #<?= htmlEscape($rq['ID_LICHHEN']) ?></p>
                                        <p class="text-xs text-gray-500">Gửi lúc <?= date('d/m/Y H:i', strtotime($rq['NGAY_GUI'])) ?></p>
                                        <p class="text-sm text-gray-600 mt-2">Lý do: <span class="italic"><?= nl2br(htmlEscape($rq['NOI_DUNG'])) ?></span></p>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <form method="POST" action="./components/process_request.php" class="inline">
                                                <input type="hidden" name="id_yeucau" value="<?= htmlEscape($rq['ID_YEUCAU']) ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="px-3 py-1 rounded-lg text-xs font-semibold bg-emerald-100 text-emerald-700 hover:bg-emerald-200">Duyệt</button>
                                            </form>
                                            <form method="POST" action="./components/process_request.php" class="inline">
                                                <input type="hidden" name="id_yeucau" value="<?= htmlEscape($rq['ID_YEUCAU']) ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="px-3 py-1 rounded-lg text-xs font-semibold bg-rose-100 text-rose-700 hover:bg-rose-200">Từ chối</button>
                                            </form>
                                        </div>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const requestToggle = document.getElementById('requestToggle');
            const requestPanel = document.getElementById('requestPanel');
            if (requestToggle && requestPanel) {
                requestToggle.addEventListener('click', () => {
                    requestPanel.classList.toggle('hidden');
                    requestPanel.classList.toggle('opacity-0');
                    requestPanel.classList.toggle('-translate-y-2');
                });
            }

            const filtersForm = document.getElementById('filtersForm');
            const clearBtn = document.getElementById('clearFilters');

            if (filtersForm) {
                filtersForm.addEventListener('submit', () => {
                    const pageInput = filtersForm.querySelector('input[name="p"]');
                    if (pageInput) {
                        pageInput.value = 1;
                    }
                });
            }

            if (clearBtn && filtersForm) {
                clearBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    ['search', 'from', 'to'].forEach((name) => {
                        const field = filtersForm.querySelector(`[name="${name}"]`);
                        if (field) {
                            field.value = '';
                        }
                    });
                    const statusSelect = filtersForm.querySelector('select[name="status"]');
                    if (statusSelect) {
                        statusSelect.value = '';
                    }
                    const sortSelect = filtersForm.querySelector('select[name="sort"]');
                    if (sortSelect) {
                        sortSelect.value = 'newest';
                    }
                    const pageInput = filtersForm.querySelector('input[name="p"]');
                    if (pageInput) {
                        pageInput.value = 1;
                    }
                    filtersForm.submit();
                });
            }
        });
    </script>
</body>
