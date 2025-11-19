<?php
include '../../database/config.php';

$currentMonth = (int)date('m');
$currentYear = (int)date('Y');

function bindQueryParams(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '' || empty($params)) {
        return;
    }

    $bindValues = [$types];
    foreach ($params as $key => $value) {
        $bindValues[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bindValues);
}

function buildSalaryFilters(?string $search, ?int $month, ?int $year, ?int $branch): array
{
    $conditions = [];
    $types = '';
    $params = [];

    if ($search !== null && $search !== '') {
        $conditions[] = 'tk.HO_TEN LIKE ?';
        $types .= 's';
        $params[] = '%' . $search . '%';
    }

    if ($month !== null) {
        $conditions[] = 'lnv.THANG = ?';
        $types .= 'i';
        $params[] = $month;
    }

    if ($year !== null) {
        $conditions[] = 'lnv.NAM = ?';
        $types .= 'i';
        $params[] = $year;
    }

    if ($branch !== null) {
        $conditions[] = 'nv.ID_CN = ?';
        $types .= 'i';
        $params[] = $branch;
    }

    $clause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    return [$clause, $types, $params];
}

function buildQueryString(array $params): string
{
    $filtered = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $filtered[$key] = $value;
    }

    return http_build_query($filtered);
}

function formatCurrency(int $amount): string
{
    return number_format($amount, 0, ',', '.') . ' VND';
}

function syncPayrollExpenses(mysqli $conn, int $month, int $year, ?int $branchId = null): void
{
    $subQuery = 'SELECT nv.ID_CN, SUM(lnv.TONG_LUONG) AS total_amount
                 FROM luong_nhan_vien lnv
                 JOIN nhan_vien nv ON nv.ID_TK = lnv.ID_TK
                 WHERE lnv.THANG = ? AND lnv.NAM = ?';

    if ($branchId !== null) {
        $subQuery .= ' AND nv.ID_CN = ?';
    }

    $subQuery .= ' GROUP BY nv.ID_CN';

    $sql = "INSERT INTO tai_chinh (LOAI_GIAO_DICH, LOAI_CHI_TIET, SO_TIEN, NGAY_GIAO_DICH, ID_CN)
            SELECT 'chi phí', 'lương nhân viên', sub.total_amount, CURDATE(), sub.ID_CN
            FROM ($subQuery) AS sub
            WHERE NOT EXISTS (
                SELECT 1 FROM tai_chinh tc
                WHERE tc.ID_CN = sub.ID_CN
                  AND tc.LOAI_GIAO_DICH = 'chi phí'
                  AND tc.LOAI_CHI_TIET = 'lương nhân viên'
                  AND MONTH(tc.NGAY_GIAO_DICH) = ?
                  AND YEAR(tc.NGAY_GIAO_DICH) = ?
            )";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Không thể đồng bộ chi phí lương: ' . $conn->error);
    }

    $types = 'ii';
    $params = [$month, $year];

    if ($branchId !== null) {
        $types .= 'i';
        $params[] = $branchId;
    }

    $types .= 'ii';
    $params[] = $month;
    $params[] = $year;

    bindQueryParams($stmt, $types, $params);
    $stmt->execute();
    $stmt->close();
}

function recalculateSalaries(mysqli $conn, ?int $branchId, int $month, int $year): array
{
    $month = max(1, min(12, $month));
    $year = max(2000, $year);
    $baseSalary = 5000000;
    $bonusRate = 0.15;

    $employeeSql = 'SELECT tk.ID_TK, nv.ID_CN
                    FROM tai_khoan tk
                    JOIN nhan_vien nv ON nv.ID_TK = tk.ID_TK
                    WHERE tk.ID_QUYEN = 2';

    if ($branchId !== null) {
        $employeeSql .= ' AND nv.ID_CN = ?';
    }

    $employeeStmt = $conn->prepare($employeeSql);
    if (!$employeeStmt) {
        throw new RuntimeException('Không thể chuẩn bị danh sách nhân viên: ' . $conn->error);
    }

    if ($branchId !== null) {
        $employeeStmt->bind_param('i', $branchId);
    }

    $employeeStmt->execute();
    $employeeResult = $employeeStmt->get_result();
    $employees = [];
    while ($employeeResult && $row = $employeeResult->fetch_assoc()) {
        $employees[] = $row;
    }
    $employeeStmt->close();

    if (!$employees) {
        return [
            'processed' => 0,
            'total_payroll' => 0,
            'total_bonus' => 0,
        ];
    }

    $bonusSql = "SELECT COALESCE(SUM(dgdv.DON_GIA * ?), 0) AS tong_thuong
                 FROM phan_cong_nhan_vien pc
                 JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
                 JOIN hoa_don hd ON hd.ID_LICHHEN = lh.ID_LICHHEN
                 JOIN don_gia_dich_vu dgdv ON lh.ID_DV = dgdv.ID_DV
                 WHERE pc.ID_TK = ?
                   AND hd.TRANGTHAI_THANHTOAN = 'Đã thanh toán'
                   AND MONTH(hd.NGAY_GIO) = ?
                   AND YEAR(hd.NGAY_GIO) = ?";

    $bonusStmt = $conn->prepare($bonusSql);
    if (!$bonusStmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn thưởng: ' . $conn->error);
    }

    $checkStmt = $conn->prepare('SELECT ID_LUONG FROM luong_nhan_vien WHERE ID_TK = ? AND THANG = ? AND NAM = ? LIMIT 1');
    $insertStmt = $conn->prepare('INSERT INTO luong_nhan_vien (ID_TK, THANG, NAM, LUONG_CO_BAN, PHAN_TRAM_THUONG, TONG_TIEN_THUONG, TONG_LUONG, NGAY_TINH) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())');
    $updateStmt = $conn->prepare('UPDATE luong_nhan_vien SET LUONG_CO_BAN = ?, PHAN_TRAM_THUONG = ?, TONG_TIEN_THUONG = ?, TONG_LUONG = ?, NGAY_TINH = CURDATE() WHERE ID_TK = ? AND THANG = ? AND NAM = ?');

    if (!$checkStmt || !$insertStmt || !$updateStmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn ghi dữ liệu lương: ' . $conn->error);
    }

    $processed = 0;
    $totalPayroll = 0;
    $totalBonus = 0;

    foreach ($employees as $employee) {
        $employeeId = $employee['ID_TK'];

        $bonusStmt->bind_param('dsii', $bonusRate, $employeeId, $month, $year);
        $bonusStmt->execute();
        $bonusResult = $bonusStmt->get_result();
        $bonusRow = $bonusResult ? $bonusResult->fetch_assoc() : null;
        $bonusAmount = (int)($bonusRow['tong_thuong'] ?? 0);

        $checkStmt->bind_param('sii', $employeeId, $month, $year);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();

        $total = (int)$baseSalary + $bonusAmount;

        if ($existing) {
            $updateStmt->bind_param('idiisii', $baseSalary, $bonusRate, $bonusAmount, $total, $employeeId, $month, $year);
            $updateStmt->execute();
        } else {
            $insertStmt->bind_param('siiidii', $employeeId, $month, $year, $baseSalary, $bonusRate, $bonusAmount, $total);
            $insertStmt->execute();
        }

        $processed++;
        $totalPayroll += $total;
        $totalBonus += $bonusAmount;
    }

    $bonusStmt->close();
    $checkStmt->close();
    $insertStmt->close();
    $updateStmt->close();

    syncPayrollExpenses($conn, $month, $year, $branchId);

    return [
        'processed' => $processed,
        'total_payroll' => $totalPayroll,
        'total_bonus' => $totalBonus,
    ];
}

$searchTerm = trim($_GET['search'] ?? '');
$monthFilter = isset($_GET['month']) && $_GET['month'] !== '' ? max(1, min(12, (int)$_GET['month'])) : null;
$yearInput = $_GET['year'] ?? '';
$yearFilter = ($yearInput !== '') ? max(2000, (int)$yearInput) : null;
$branchFilter = isset($_GET['branch']) && $_GET['branch'] !== '' ? max(0, (int)$_GET['branch']) : null;
$sort = $_GET['sort'] ?? 'recent';
$limit = 10;
$pageNumber = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pageNumber - 1) * $limit;

$alert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salary_action'])) {
    if ($_POST['salary_action'] === 'recalculate') {
        $targetMonth = isset($_POST['target_month']) ? max(1, min(12, (int)$_POST['target_month'])) : $currentMonth;
        $targetYear = isset($_POST['target_year']) ? max(2000, (int)$_POST['target_year']) : $currentYear;
        $targetBranch = isset($_POST['target_branch']) && $_POST['target_branch'] !== '' ? max(0, (int)$_POST['target_branch']) : null;

        try {
            $stats = recalculateSalaries($conn, $targetBranch ?: null, $targetMonth, $targetYear);

            if ($stats['processed'] === 0) {
                $alert = [
                    'type' => 'warning',
                    'message' => 'Không tìm thấy nhân viên phù hợp để tính lương cho kỳ đã chọn.',
                ];
            } else {
                $alert = [
                    'type' => 'success',
                    'message' => sprintf(
                        'Đã tính lại lương cho %d nhân viên (%s, thưởng %s).',
                        $stats['processed'],
                        formatCurrency($stats['total_payroll']),
                        formatCurrency($stats['total_bonus'])
                    ),
                ];
            }
        } catch (Throwable $exception) {
            $alert = [
                'type' => 'error',
                'message' => 'Lỗi khi tính lại lương: ' . $exception->getMessage(),
            ];
        }
    }
}

$sortMap = [
    'recent' => 'lnv.NAM DESC, lnv.THANG DESC, lnv.NGAY_TINH DESC',
    'salary_desc' => 'lnv.TONG_LUONG DESC',
    'salary_asc' => 'lnv.TONG_LUONG ASC',
    'name' => 'tk.HO_TEN ASC',
];

if (!array_key_exists($sort, $sortMap)) {
    $sort = 'recent';
}

$branchList = [];
$branchQuery = $conn->query('SELECT ID_CN, TEN_CN FROM chi_nhanh ORDER BY TEN_CN');
if ($branchQuery) {
    while ($branchRow = $branchQuery->fetch_assoc()) {
        $branchList[] = $branchRow;
    }
}

[$whereClause, $whereTypes, $whereParams] = buildSalaryFilters($searchTerm, $monthFilter, $yearFilter, $branchFilter);

$countSql = 'SELECT COUNT(*) AS total
             FROM luong_nhan_vien lnv
             JOIN tai_khoan tk ON lnv.ID_TK = tk.ID_TK
             LEFT JOIN nhan_vien nv ON nv.ID_TK = lnv.ID_TK
             ' . $whereClause;

$countStmt = $conn->prepare($countSql);
if (!$countStmt) {
    throw new RuntimeException('Không thể tải tổng bản ghi: ' . $conn->error);
}
bindQueryParams($countStmt, $whereTypes, $whereParams);
$countStmt->execute();
$countResult = $countStmt->get_result()->fetch_assoc();
$totalRows = (int)($countResult['total'] ?? 0);
$countStmt->close();

$summarySql = 'SELECT 
                    COUNT(DISTINCT lnv.ID_TK) AS employee_count,
                    COALESCE(SUM(lnv.TONG_LUONG), 0) AS total_payroll,
                    COALESCE(SUM(lnv.TONG_TIEN_THUONG), 0) AS total_bonus,
                    MAX(lnv.NGAY_TINH) AS last_calculated
                FROM luong_nhan_vien lnv
                JOIN tai_khoan tk ON lnv.ID_TK = tk.ID_TK
                LEFT JOIN nhan_vien nv ON nv.ID_TK = lnv.ID_TK
                ' . $whereClause;

$summaryStmt = $conn->prepare($summarySql);
if (!$summaryStmt) {
    throw new RuntimeException('Không thể tải số liệu tổng quan: ' . $conn->error);
}
bindQueryParams($summaryStmt, $whereTypes, $whereParams);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc() ?: [];
$summaryStmt->close();

$listSql = 'SELECT lnv.*, tk.HO_TEN, nv.ID_CN, cn.TEN_CN
            FROM luong_nhan_vien lnv
            JOIN tai_khoan tk ON lnv.ID_TK = tk.ID_TK
            LEFT JOIN nhan_vien nv ON nv.ID_TK = lnv.ID_TK
            LEFT JOIN chi_nhanh cn ON cn.ID_CN = nv.ID_CN
            ' . $whereClause . '
            ORDER BY ' . $sortMap[$sort] . '
            LIMIT ? OFFSET ?';

$listStmt = $conn->prepare($listSql);
if (!$listStmt) {
    throw new RuntimeException('Không thể tải dữ liệu bảng lương: ' . $conn->error);
}

$listTypes = $whereTypes . 'ii';
$listParams = array_merge($whereParams, [$limit, $offset]);
bindQueryParams($listStmt, $listTypes, $listParams);
$listStmt->execute();
$listResult = $listStmt->get_result();
$salaryRows = $listResult ? $listResult->fetch_all(MYSQLI_ASSOC) : [];
$listStmt->close();

$totalPages = max(1, (int)ceil($totalRows / $limit));
$employeeCount = (int)($summary['employee_count'] ?? 0);
$totalPayroll = (int)($summary['total_payroll'] ?? 0);
$totalBonus = (int)($summary['total_bonus'] ?? 0);
$avgSalary = $employeeCount > 0 ? (int)round($totalPayroll / $employeeCount) : 0;
$lastCalculated = $summary['last_calculated'] ?? null;

$filterQueryBase = [
    'page' => 'salaries',
    'search' => $searchTerm !== '' ? $searchTerm : null,
    'month' => $monthFilter,
    'year' => $yearFilter,
    'branch' => $branchFilter,
    'sort' => $sort !== 'recent' ? $sort : null,
];

$previousMonthDate = new DateTime('first day of last month');
$prevMonth = (int)$previousMonthDate->format('m');
$prevYear = (int)$previousMonthDate->format('Y');
?>

<div class="space-y-6">
    <h1 class="text-3xl font-extrabold text-indigo-700 text-center">Quản lý bảng lương</h1>

    <?php if ($alert): ?>
        <?php
        $alertClasses = [
            'success' => 'bg-green-50 text-green-700 border-green-200',
            'error' => 'bg-red-50 text-red-700 border-red-200',
            'warning' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
        ];
        $className = $alertClasses[$alert['type']] ?? 'bg-blue-50 text-blue-700 border-blue-200';
        ?>
        <div class="rounded-xl border px-4 py-3 text-sm <?= $className ?>">
            <?= htmlspecialchars($alert['message']) ?>
        </div>
    <?php endif; ?>

    <div class="grid gap-4 lg:grid-cols-2">
        <form method="GET" class="bg-white rounded-2xl shadow p-4 lg:p-6 space-y-4">
            <input type="hidden" name="page" value="salaries">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-1">
                    <label class="text-sm font-semibold text-gray-600">Tìm kiếm</label>
                    <input type="text"
                           name="search"
                           value="<?= htmlspecialchars($searchTerm) ?>"
                           placeholder="Nhập tên nhân viên"
                           class="w-full rounded-lg border border-gray-200 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-semibold text-gray-600">Chi nhánh</label>
                    <select name="branch" class="w-full rounded-lg border border-gray-200 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tất cả</option>
                        <?php foreach ($branchList as $branch): ?>
                            <option value="<?= $branch['ID_CN'] ?>" <?= ($branchFilter === (int)$branch['ID_CN']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($branch['TEN_CN']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-semibold text-gray-600">Tháng</label>
                    <select name="month" class="w-full rounded-lg border border-gray-200 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tất cả</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= ($monthFilter === $m) ? 'selected' : '' ?>>Tháng <?= $m ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-semibold text-gray-600">Năm</label>
                    <select name="year" class="w-full rounded-lg border border-gray-200 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tất cả</option>
                        <?php for ($y = 2022; $y <= date('Y'); $y++): ?>
                            <option value="<?= $y ?>" <?= ($yearFilter === $y) ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <select name="sort" class="rounded-lg border border-gray-200 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Mới nhất</option>
                    <option value="salary_desc" <?= $sort === 'salary_desc' ? 'selected' : '' ?>>Tổng lương cao → thấp</option>
                    <option value="salary_asc" <?= $sort === 'salary_asc' ? 'selected' : '' ?>>Tổng lương thấp → cao</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Theo tên A → Z</option>
                </select>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-2 font-semibold text-white transition hover:bg-indigo-700">
                    Áp dụng
                </button>
                <a href="?page=salaries"
                   class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:border-indigo-300 hover:text-indigo-600">
                    Đặt lại
                </a>
            </div>
        </form>

        <form method="POST" class="bg-white rounded-2xl shadow p-4 lg:p-6 space-y-4">
            <input type="hidden" name="salary_action" value="recalculate">
            <div class="flex items-center gap-3">
                <span class="text-lg font-semibold text-gray-800">Tính lại lương</span>
                <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600">Thao tác tức thì</span>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1">
                    <label class="text-sm font-semibold text-gray-600">Tháng</label>
                    <select name="target_month" class="w-full rounded-lg border border-gray-200 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= ($monthFilter ?? $currentMonth) === $m ? 'selected' : '' ?>>Tháng <?= $m ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-semibold text-gray-600">Năm</label>
                    <select name="target_year" class="w-full rounded-lg border border-gray-200 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                        <?php for ($y = 2022; $y <= date('Y'); $y++): ?>
                            <option value="<?= $y ?>" <?= ($yearFilter ?? $currentYear) === $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="space-y-1 sm:col-span-2">
                    <label class="text-sm font-semibold text-gray-600">Chi nhánh cần đồng bộ</label>
                    <select name="target_branch" class="w-full rounded-lg border border-gray-200 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tất cả chi nhánh</option>
                        <?php foreach ($branchList as $branch): ?>
                            <option value="<?= $branch['ID_CN'] ?>" <?= ($branchFilter === (int)$branch['ID_CN']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($branch['TEN_CN']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <p class="text-sm text-gray-500">
                Hệ thống sẽ tính lại lương dựa trên lịch hẹn đã thanh toán và cập nhật bảng tài chính tương ứng.
            </p>
            <button type="submit" class="w-full rounded-lg bg-emerald-500 py-2.5 font-semibold text-white shadow-md transition hover:bg-emerald-600">
                Thực hiện tính lại
            </button>
        </form>
    </div>

    <div class="flex flex-wrap items-center gap-3 text-sm">
        <span class="text-gray-500 font-semibold">Bộ lọc nhanh:</span>
        <a href="?<?= buildQueryString(array_merge($filterQueryBase, ['month' => $currentMonth, 'year' => $currentYear, 'p' => 1])) ?>"
           class="rounded-full border border-gray-200 px-4 py-1.5 text-gray-700 transition hover:border-indigo-400 hover:text-indigo-600">
            Tháng này
        </a>
        <a href="?<?= buildQueryString(array_merge($filterQueryBase, ['month' => $prevMonth, 'year' => $prevYear, 'p' => 1])) ?>"
           class="rounded-full border border-gray-200 px-4 py-1.5 text-gray-700 transition hover:border-indigo-400 hover:text-indigo-600">
            Tháng trước
        </a>
        <a href="?<?= buildQueryString(['page' => 'salaries']) ?>"
           class="rounded-full border border-gray-200 px-4 py-1.5 text-gray-700 transition hover:border-indigo-400 hover:text-indigo-600">
            Xem toàn bộ
        </a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-gray-500">Tổng quỹ lương</p>
            <p class="mt-2 text-2xl font-bold text-gray-900"><?= formatCurrency($totalPayroll) ?></p>
            <p class="text-xs text-gray-400">Nguồn dữ liệu theo bộ lọc hiện tại.</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-gray-500">Tổng thưởng</p>
            <p class="mt-2 text-2xl font-bold text-emerald-600"><?= formatCurrency($totalBonus) ?></p>
            <p class="text-xs text-gray-400">Bao gồm 15% giá dịch vụ đã thanh toán.</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-gray-500">Nhân sự nhận lương</p>
            <p class="mt-2 text-2xl font-bold text-gray-900"><?= $employeeCount ?></p>
            <p class="text-xs text-gray-400">Nhân viên xuất hiện trong bảng dữ liệu.</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-gray-500">Thu nhập trung bình</p>
            <p class="mt-2 text-2xl font-bold text-indigo-600"><?= formatCurrency($avgSalary) ?></p>
            <p class="text-xs text-gray-400">Tính dựa trên tổng lương / nhân viên.</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Danh sách bảng lương</h2>
                <p class="text-sm text-gray-500">Cập nhật gần nhất: <?= $lastCalculated ? date('d/m/Y', strtotime($lastCalculated)) : 'Chưa có dữ liệu' ?></p>
            </div>
            <a href="components/export_salary_excel.php?<?= buildQueryString([
                'search' => $searchTerm !== '' ? $searchTerm : null,
                'month' => $monthFilter,
                'year' => $yearFilter,
                'branch' => $branchFilter,
            ]) ?>"
               class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-600">
                Xuất Excel
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-indigo-50 text-xs font-semibold uppercase tracking-wider text-gray-600">
                <tr>
                    <th class="px-4 py-3 text-left">Nhân viên</th>
                    <th class="px-4 py-3 text-left">Chi nhánh</th>
                    <th class="px-4 py-3 text-center">Tháng/Năm</th>
                    <th class="px-4 py-3 text-right">Lương cơ bản</th>
                    <th class="px-4 py-3 text-right">Thưởng</th>
                    <th class="px-4 py-3 text-right">Tổng nhận</th>
                    <th class="px-4 py-3 text-center">Cập nhật</th>
                    <th class="px-4 py-3 text-center">Thao tác</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                <?php if (empty($salaryRows)): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                            Không tìm thấy bản ghi nào khớp với bộ lọc.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($salaryRows as $row): ?>
                        <?php
                        $detailPayload = htmlspecialchars(json_encode([
                            'name' => $row['HO_TEN'],
                            'branch' => $row['TEN_CN'] ?: 'Chưa gán',
                            'month' => $row['THANG'],
                            'year' => $row['NAM'],
                            'base' => formatCurrency((int)$row['LUONG_CO_BAN']),
                            'bonus' => formatCurrency((int)$row['TONG_TIEN_THUONG']),
                            'percent' => round((float)$row['PHAN_TRAM_THUONG'] * 100, 2) . '%',
                            'total' => formatCurrency((int)$row['TONG_LUONG']),
                            'updated' => $row['NGAY_TINH'] ? date('d/m/Y', strtotime($row['NGAY_TINH'])) : 'Chưa cập nhật',
                        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="hover:bg-gray-50/80">
                            <td class="px-4 py-3 font-semibold text-gray-800">
                                <?= htmlspecialchars($row['HO_TEN']) ?>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <?= htmlspecialchars($row['TEN_CN'] ?? 'Chưa gán') ?>
                            </td>
                            <td class="px-4 py-3 text-center font-semibold text-gray-700">
                                <?= $row['THANG'] ?>/<?= $row['NAM'] ?>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-700">
                                <?= formatCurrency((int)$row['LUONG_CO_BAN']) ?>
                            </td>
                            <td class="px-4 py-3 text-right text-emerald-600 font-semibold">
                                <?= formatCurrency((int)$row['TONG_TIEN_THUONG']) ?>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="rounded-full bg-indigo-50 px-3 py-1 text-sm font-bold text-indigo-700">
                                    <?= formatCurrency((int)$row['TONG_LUONG']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-500">
                                <?= $row['NGAY_TINH'] ? date('d/m/Y', strtotime($row['NGAY_TINH'])) : '—' ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button type="button"
                                        class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-600 transition hover:border-indigo-400 hover:text-indigo-600"
                                        data-salary-detail="<?= $detailPayload ?>">
                                    Chi tiết
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-gray-100 px-4 py-3 text-sm text-gray-600">
            <span>Hiển thị <?= count($salaryRows) ?> / <?= $totalRows ?> bản ghi</span>
            <div class="flex flex-wrap items-center gap-2">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php
                    $isActive = $i === $pageNumber;
                    $pageLinkClass = $isActive
                        ? 'rounded-lg border px-3 py-1 border-indigo-500 bg-indigo-50 text-indigo-600 font-semibold'
                        : 'rounded-lg border px-3 py-1 border-gray-200 text-gray-600 hover:border-indigo-300 hover:text-indigo-600';
                    ?>
                    <a href="?<?= buildQueryString(array_merge($filterQueryBase, ['p' => $i])) ?>"
                       class="<?= $pageLinkClass ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<div id="salaryDetailModal" class="fixed inset-0 z-30 hidden items-center justify-center bg-gray-900/60 px-4">
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
        <div class="flex items-start justify-between">
            <div>
                <h3 class="text-xl font-bold text-gray-800" data-field="name"></h3>
                <p class="text-sm text-gray-500" data-field="branch"></p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600" data-close-modal>&times;</button>
        </div>
        <div class="mt-4 grid gap-3 text-sm text-gray-700">
            <div class="flex justify-between"><span>Tháng/Năm</span><strong data-field="period"></strong></div>
            <div class="flex justify-between"><span>Lương cơ bản</span><strong data-field="base"></strong></div>
            <div class="flex justify-between"><span>Thưởng</span><strong data-field="bonus"></strong></div>
            <div class="flex justify-between"><span>Tỷ lệ thưởng</span><strong data-field="percent"></strong></div>
            <div class="flex justify-between text-indigo-700"><span>Tổng nhận</span><strong data-field="total"></strong></div>
            <div class="flex justify-between text-gray-500"><span>Cập nhật lần cuối</span><strong data-field="updated"></strong></div>
        </div>
        <div class="mt-6 flex justify-end">
            <button type="button" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white" data-close-modal>Đóng</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('salaryDetailModal');
        if (!modal) return;

        const nameField = modal.querySelector('[data-field="name"]');
        const branchField = modal.querySelector('[data-field="branch"]');
        const periodField = modal.querySelector('[data-field="period"]');
        const baseField = modal.querySelector('[data-field="base"]');
        const bonusField = modal.querySelector('[data-field="bonus"]');
        const percentField = modal.querySelector('[data-field="percent"]');
        const totalField = modal.querySelector('[data-field="total"]');
        const updatedField = modal.querySelector('[data-field="updated"]');

        const closeModal = () => {
            modal.classList.add('hidden');
        };

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        modal.querySelectorAll('[data-close-modal]').forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        document.querySelectorAll('[data-salary-detail]').forEach((button) => {
            button.addEventListener('click', () => {
                const detail = button.getAttribute('data-salary-detail');
                if (!detail) return;

                const payload = JSON.parse(detail);
                nameField.textContent = payload.name;
                branchField.textContent = payload.branch;
                periodField.textContent = `${payload.month}/${payload.year}`;
                baseField.textContent = payload.base;
                bonusField.textContent = payload.bonus;
                percentField.textContent = payload.percent;
                totalField.textContent = payload.total;
                updatedField.textContent = payload.updated;

                modal.classList.remove('hidden');
            });
        });
    });
</script>
