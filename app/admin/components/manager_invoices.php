<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../database/config.php';

$currentAccount = $_SESSION['ID_TK'] ?? null;
if (!$currentAccount) {
    echo '<div class="rounded-xl bg-white p-8 text-center text-red-600 shadow">Vui lòng đăng nhập để xem hóa đơn.</div>';
    return;
}

// Lấy thông tin chi nhánh của manager
$branchStmt = $conn->prepare('SELECT nv.ID_CN, cn.TEN_CN FROM nhan_vien nv JOIN chi_nhanh cn ON nv.ID_CN = cn.ID_CN WHERE nv.ID_TK = ? LIMIT 1');
$branchStmt->bind_param('s', $currentAccount);
$branchStmt->execute();
$branchStmt->bind_result($branchId, $branchName);
$branchStmt->fetch();
$branchStmt->close();

if (!$branchId) {
    echo '<div class="rounded-xl bg-white p-8 text-center text-red-600 shadow">Tài khoản của bạn chưa được gán vào chi nhánh nào. Hãy liên hệ quản trị viên.</div>';
    return;
}

// Helper functions
function normalizeDateFilter($value) {
    if (!$value) return null;
    $value = trim($value);
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt ? $dt->format('Y-m-d') : null;
}

function humanDateTime($value) {
    if (!$value || $value === '0000-00-00 00:00:00') return '—';
    try {
        $dt = new DateTime($value);
        return $dt->format('d/m/Y H:i');
    } catch (Exception $e) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

function bindParams($stmt, $types, $params) {
    if (!$stmt || $types === '' || empty($params)) return;
    $bind = [$types];
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

// Xử lý filters
$perPage = 15;
$page = isset($_GET['inv_p']) ? max(1, (int)$_GET['inv_p']) : 1;
$offset = ($page - 1) * $perPage;

$statusOptions = ['Chưa thanh toán', 'Đã thanh toán', 'Đã hủy', 'Hoàn tiền'];
$methodOptions = ['VNPay', 'Chuyển khoản', 'Tiền mặt'];

$filters = [
    'customer'   => trim($_GET['customer'] ?? ''),
    'status'     => trim($_GET['status'] ?? ''),
    'method'     => trim($_GET['method'] ?? ''),
    'date_from'  => normalizeDateFilter($_GET['date_from'] ?? ''),
    'date_to'    => normalizeDateFilter($_GET['date_to'] ?? ''),
    'min_amount' => isset($_GET['min_amount']) && $_GET['min_amount'] !== '' ? (float)$_GET['min_amount'] : null,
    'max_amount' => isset($_GET['max_amount']) && $_GET['max_amount'] !== '' ? (float)$_GET['max_amount'] : null,
];

if (!in_array($filters['status'], $statusOptions, true)) {
    $filters['status'] = '';
}
if (!in_array($filters['method'], $methodOptions, true)) {
    $filters['method'] = '';
}

// Build unified (schedule + rental) query parts
$scheduleConds = ['lh.ID_CHINHANH = ?'];
$rentalConds   = ['ttp.ID_CN = ?'];
$baseTypes = 'i';
$scheduleParams = [$branchId];
$rentalParams   = [$branchId];

// Helper to append condition to both sets (field names differ only for customer)
if ($filters['customer'] !== '') {
    $scheduleConds[] = 'tk.HO_TEN LIKE ?';
    $rentalConds[]   = 'tk.HO_TEN LIKE ?';
    $baseTypes      .= 's';
    $scheduleParams[] = '%' . $filters['customer'] . '%';
    $rentalParams[]   = '%' . $filters['customer'] . '%';
}
if ($filters['status'] !== '') {
    $scheduleConds[] = 'hd.TRANGTHAI_THANHTOAN = ?';
    $rentalConds[]   = 'hd.TRANGTHAI_THANHTOAN = ?';
    $baseTypes      .= 's';
    $scheduleParams[] = $filters['status'];
    $rentalParams[]   = $filters['status'];
}
if ($filters['method'] !== '') {
    $scheduleConds[] = 'hd.PHUONGTHUC_THANHTOAN = ?';
    $rentalConds[]   = 'hd.PHUONGTHUC_THANHTOAN = ?';
    $baseTypes      .= 's';
    $scheduleParams[] = $filters['method'];
    $rentalParams[]   = $filters['method'];
}
if ($filters['date_from']) {
    $scheduleConds[] = 'DATE(hd.NGAY_GIO) >= ?';
    $rentalConds[]   = 'DATE(hd.NGAY_GIO) >= ?';
    $baseTypes      .= 's';
    $scheduleParams[] = $filters['date_from'];
    $rentalParams[]   = $filters['date_from'];
}
if ($filters['date_to']) {
    $scheduleConds[] = 'DATE(hd.NGAY_GIO) <= ?';
    $rentalConds[]   = 'DATE(hd.NGAY_GIO) <= ?';
    $baseTypes      .= 's';
    $scheduleParams[] = $filters['date_to'];
    $rentalParams[]   = $filters['date_to'];
}
if ($filters['min_amount'] !== null) {
    $scheduleConds[] = 'hd.TONG_TIEN >= ?';
    $rentalConds[]   = 'hd.TONG_TIEN >= ?';
    $baseTypes      .= 'd';
    $scheduleParams[] = $filters['min_amount'];
    $rentalParams[]   = $filters['min_amount'];
}
if ($filters['max_amount'] !== null) {
    $scheduleConds[] = 'hd.TONG_TIEN <= ?';
    $rentalConds[]   = 'hd.TONG_TIEN <= ?';
    $baseTypes      .= 'd';
    $scheduleParams[] = $filters['max_amount'];
    $rentalParams[]   = $filters['max_amount'];
}

$scheduleWhere = 'WHERE ' . implode(' AND ', $scheduleConds);
$rentalWhere   = 'WHERE ' . implode(' AND ', $rentalConds);

// COUNT with UNION
$countSql = 'SELECT COUNT(*) FROM (
    SELECT hd.ID_HD
    FROM hoa_don hd
    JOIN lich_hen lh ON hd.ID_LICHHEN = lh.ID_LICHHEN
    JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK
    LEFT JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
    ' . $scheduleWhere . '
    UNION ALL
    SELECT hd.ID_HD
    FROM hoa_don hd
    JOIN don_thue_trang_phuc ttp ON hd.ID_TTP = ttp.ID_TTP
    JOIN tai_khoan tk ON ttp.ID_TK = tk.ID_TK
    LEFT JOIN (
        SELECT ct.ID_TTP, GROUP_CONCAT(tp.TEN ORDER BY tp.TEN SEPARATOR ", ") AS item_names
        FROM don_thue_trang_phuc_ct ct
        LEFT JOIN trang_phuc tp ON ct.ID_TP = tp.ID_TRANG_PHUC
        GROUP BY ct.ID_TTP
    ) ic ON ic.ID_TTP = ttp.ID_TTP
    ' . $rentalWhere . '
) merged';
$countStmt = $conn->prepare($countSql);
$countTypes = $baseTypes . $baseTypes; // schedule params + rental params
$countParams = array_merge($scheduleParams, $rentalParams);
bindParams($countStmt, $countTypes, $countParams);
$countStmt->execute();
$countStmt->bind_result($totalRows);
$countStmt->fetch();
$countStmt->close();
$totalRows = (int)($totalRows ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// DATA with UNION
$dataSql = 'SELECT * FROM (
    SELECT hd.ID_HD, hd.NGAY_GIO, hd.TONG_TIEN, hd.TRANGTHAI_THANHTOAN, hd.PHUONGTHUC_THANHTOAN,
           tk.HO_TEN AS TEN_KHACH_HANG, tk.SDT, tk.EMAIL,
           dv.TEN_DV AS TEN_DV, lh.ID_LICHHEN, lh.THOI_GIAN_BAT_DAU,
           "schedule" AS KIND, NULL AS RENTAL_SUMMARY
    FROM hoa_don hd
    JOIN lich_hen lh ON hd.ID_LICHHEN = lh.ID_LICHHEN
    JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK
    LEFT JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
    ' . $scheduleWhere . '
    UNION ALL
    SELECT hd.ID_HD, hd.NGAY_GIO, hd.TONG_TIEN, hd.TRANGTHAI_THANHTOAN, hd.PHUONGTHUC_THANHTOAN,
           tk.HO_TEN AS TEN_KHACH_HANG, tk.SDT, tk.EMAIL,
           CONCAT("Thuê trang phục (", COALESCE(ic.item_count,0), " món)") AS TEN_DV,
           NULL AS ID_LICHHEN, ttp.NGAY_NHAN AS THOI_GIAN_BAT_DAU,
           "rental" AS KIND, ic.item_names AS RENTAL_SUMMARY
    FROM hoa_don hd
    JOIN don_thue_trang_phuc ttp ON hd.ID_TTP = ttp.ID_TTP
    JOIN tai_khoan tk ON ttp.ID_TK = tk.ID_TK
    LEFT JOIN (
        SELECT ct.ID_TTP, COUNT(*) AS item_count,
               GROUP_CONCAT(tp.TEN ORDER BY tp.TEN SEPARATOR ", ") AS item_names
        FROM don_thue_trang_phuc_ct ct
        LEFT JOIN trang_phuc tp ON ct.ID_TP = tp.ID_TRANG_PHUC
        GROUP BY ct.ID_TTP
    ) ic ON ic.ID_TTP = ttp.ID_TTP
    ' . $rentalWhere . '
) merged
ORDER BY NGAY_GIO DESC
LIMIT ? OFFSET ?';
$dataStmt = $conn->prepare($dataSql);
$dataTypes = $baseTypes . $baseTypes . 'ii';
$dataParams = array_merge($scheduleParams, $rentalParams, [$perPage, $offset]);
bindParams($dataStmt, $dataTypes, $dataParams);
$dataStmt->execute();
$dataResult = $dataStmt->get_result();
$invoices = $dataResult ? $dataResult->fetch_all(MYSQLI_ASSOC) : [];
$dataStmt->close();

// Statistics
$statsQuery = $conn->prepare('
    SELECT 
        COUNT(*) as total_invoices,
        SUM(CASE WHEN TRANGTHAI_THANHTOAN = "Đã thanh toán" THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN TRANGTHAI_THANHTOAN = "Chưa thanh toán" THEN 1 ELSE 0 END) as unpaid_count,
        SUM(CASE WHEN TRANGTHAI_THANHTOAN = "Đã thanh toán" THEN TONG_TIEN ELSE 0 END) as total_revenue,
        SUM(CASE WHEN TRANGTHAI_THANHTOAN = "Chưa thanh toán" THEN TONG_TIEN ELSE 0 END) as pending_revenue
    FROM hoa_don hd
    JOIN lich_hen lh ON hd.ID_LICHHEN = lh.ID_LICHHEN
    WHERE lh.ID_CHINHANH = ?
');
$statsQuery->bind_param('i', $branchId);
$statsQuery->execute();
$statsResult = $statsQuery->get_result();
$stats = $statsResult->fetch_assoc();
$statsQuery->close();
?>

<div class="space-y-8">
    <header class="flex flex-col gap-2 rounded-2xl bg-white/90 p-6 shadow">
        <div class="text-sm font-semibold text-indigo-600">Quản lý hóa đơn</div>
        <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($branchName, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-sm text-gray-600">Theo dõi và quản lý tất cả hóa đơn của chi nhánh, xác nhận thanh toán và xử lý hoàn tiền.</p>
    </header>

    <!-- Statistics -->
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-gray-500">Tổng hóa đơn</p>
            <p class="mt-2 text-3xl font-bold text-indigo-700"><?= number_format($stats['total_invoices'] ?? 0) ?></p>
            <p class="text-xs text-gray-500">Tất cả hóa đơn</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-gray-500">Đã thanh toán</p>
            <p class="mt-2 text-3xl font-bold text-emerald-700"><?= number_format($stats['paid_count'] ?? 0) ?></p>
            <p class="text-xs text-gray-500">Hóa đơn hoàn tất</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-gray-500">Chưa thanh toán</p>
            <p class="mt-2 text-3xl font-bold text-rose-700"><?= number_format($stats['unpaid_count'] ?? 0) ?></p>
            <p class="text-xs text-gray-500">Cần xử lý</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-gray-500">Doanh thu</p>
            <p class="mt-2 text-2xl font-bold text-blue-700"><?= number_format($stats['total_revenue'] ?? 0) ?></p>
            <p class="text-xs text-gray-500">VNĐ đã thu</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-gray-500">Chờ thu</p>
            <p class="mt-2 text-2xl font-bold text-amber-700"><?= number_format($stats['pending_revenue'] ?? 0) ?></p>
            <p class="text-xs text-gray-500">VNĐ chưa thu</p>
        </div>
    </section>

    <!-- Filters -->
    <form method="GET" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <input type="hidden" name="page" value="invoices">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-900">Bộ lọc hóa đơn</h2>
            <a href="?page=invoices" class="text-sm text-indigo-600 hover:underline">Đặt lại</a>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            <label class="text-sm font-medium text-gray-700">
                Tên khách hàng
                <input type="text" name="customer" value="<?= htmlspecialchars($filters['customer'], ENT_QUOTES, 'UTF-8') ?>" 
                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Nhập tên khách hàng">
            </label>
            <label class="text-sm font-medium text-gray-700">
                Trạng thái
                <select name="status" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Tất cả</option>
                    <?php foreach ($statusOptions as $status): ?>
                        <option value="<?= $status ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-medium text-gray-700">
                Phương thức
                <select name="method" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Tất cả</option>
                    <?php foreach ($methodOptions as $method): ?>
                        <option value="<?= $method ?>" <?= $filters['method'] === $method ? 'selected' : '' ?>><?= $method ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-medium text-gray-700">
                Từ ngày
                <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </label>
            <label class="text-sm font-medium text-gray-700">
                Đến ngày
                <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </label>
            <label class="text-sm font-medium text-gray-700">
                Số tiền tối thiểu
                <input type="number" name="min_amount" value="<?= $filters['min_amount'] !== null ? $filters['min_amount'] : '' ?>" 
                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="VNĐ">
            </label>
            <label class="text-sm font-medium text-gray-700">
                Số tiền tối đa
                <input type="number" name="max_amount" value="<?= $filters['max_amount'] !== null ? $filters['max_amount'] : '' ?>" 
                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="VNĐ">
            </label>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="submit" class="rounded-lg bg-slate-900 px-6 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                <i class="fas fa-filter mr-2"></i>Áp dụng
            </button>
        </div>
    </form>

    <!-- Invoice List -->
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Danh sách hóa đơn</h2>
                <p class="text-xs text-gray-500">Hiển thị <?= count($invoices) ?> / <?= $totalRows ?> kết quả</p>
            </div>
        </div>

        <?php if (empty($invoices)): ?>
            <p class="px-6 py-10 text-center text-sm text-gray-500">Không có hóa đơn phù hợp với bộ lọc.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Mã HĐ</th>
                            <th class="px-6 py-3">Khách hàng</th>
                            <th class="px-6 py-3">Dịch vụ</th>
                            <th class="px-6 py-3">Ngày lập</th>
                            <th class="px-6 py-3">Số tiền</th>
                            <th class="px-6 py-3">Trạng thái</th>
                            <th class="px-6 py-3 text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($invoices as $row): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-indigo-600">#<?= (int)$row['ID_HD'] ?></div>
                                    <?php if ($row['KIND'] === 'schedule'): ?>
                                        <div class="text-xs text-gray-500">Lịch #<?= (int)($row['ID_LICHHEN'] ?? 0) ?></div>
                                    <?php else: ?>
                                        <div class="text-xs text-amber-600">Thuê trang phục</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900"><?= htmlspecialchars($row['TEN_KHACH_HANG'] ?? 'Chưa rõ', ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($row['SDT'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td class="px-6 py-4 text-gray-800">
                                    <?= htmlspecialchars($row['TEN_DV'] ?? 'Chưa rõ', ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($row['KIND'] === 'rental' && !empty($row['RENTAL_SUMMARY'])): ?>
                                        <div class="mt-1 text-[11px] text-gray-500 line-clamp-2"><?= htmlspecialchars($row['RENTAL_SUMMARY'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-800">
                                    <?= humanDateTime($row['NGAY_GIO'] ?? '') ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900"><?= number_format($row['TONG_TIEN'] ?? 0, 0, ',', '.') ?> VNĐ</div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php 
                                    $statusClass = match($row['TRANGTHAI_THANHTOAN'] ?? '') {
                                        'Đã thanh toán' => 'bg-emerald-100 text-emerald-700',
                                        'Chưa thanh toán' => 'bg-rose-100 text-rose-700',
                                        'Đã hủy' => 'bg-gray-100 text-gray-700',
                                        'Hoàn tiền' => 'bg-amber-100 text-amber-700',
                                        default => 'bg-slate-100 text-slate-700'
                                    };
                                    ?>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $statusClass ?>">
                                        <?= htmlspecialchars($row['TRANGTHAI_THANHTOAN'] ?? 'Không rõ', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <a href="?page=hoa_don_chi_tiet&id_hd=<?= (int)$row['ID_HD'] ?>&source=manager" 
                                       class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 px-4 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50">
                                        <i class="fas fa-eye"></i> Xem
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="flex flex-wrap items-center justify-center gap-2 border-t border-slate-100 px-6 py-4">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php $query = array_merge($_GET, ['inv_p' => $i]); ?>
                        <a href="?<?= htmlspecialchars(http_build_query($query), ENT_QUOTES, 'UTF-8') ?>" 
                           class="rounded-lg px-4 py-2 text-sm font-semibold <?= $i === $page ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<style>
    button, a {
        transition: all 0.2s ease;
    }
    
    button:hover, a:hover {
        transform: translateY(-1px);
    }
    
    button:active, a:active {
        transform: translateY(0);
    }
</style>
