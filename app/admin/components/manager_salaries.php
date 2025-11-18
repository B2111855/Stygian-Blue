<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../middlewares/require_staff_manager.php';
require_once __DIR__ . '/../../../database/config.php';
require_once __DIR__ . '/../../helpers/branch_salary.php';

$managerId = $_SESSION['ID_TK'] ?? null;
$fullName = $_SESSION['HO_TEN'] ?? '';

$alerts = [
    'success' => [],
    'error' => [],
];

if (!$managerId) {
    echo '<div class="rounded-xl bg-red-50 p-6 text-center text-red-600">Bạn cần đăng nhập để xem bảng lương.</div>';
    return;
}

try {
    ensureBranchSalaryMetaTable($conn);
} catch (Throwable $e) {
    echo '<div class="rounded-xl bg-red-50 p-6 text-center text-red-600">' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}

$branchId = null;
try {
    $branchId = getBranchIdForUser($conn, $managerId);
} catch (Throwable $e) {
    $alerts['error'][] = $e->getMessage();
}

if (!$branchId) {
    echo '<div class="rounded-xl bg-yellow-50 p-6 text-center text-yellow-700">Không xác định được chi nhánh bạn quản lý.</div>';
    return;
}

$currentMonth = (int)date('n');
$currentYear = (int)date('Y');
$month = isset($_GET['month']) ? max(1, min(12, (int)$_GET['month'])) : $currentMonth;
$year = isset($_GET['year']) ? max(2000, (int)$_GET['year']) : $currentYear;
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$statusFilter = in_array($statusFilter, ['cho_duyet', 'da_duyet', 'da_chi_tra'], true) ? $statusFilter : '';

if (isset($_GET['notice']) && $_GET['notice'] !== '') {
    $type = ($_GET['noticeType'] ?? 'success') === 'error' ? 'error' : 'success';
    $alerts[$type][] = $_GET['notice'];
}

if (isset($_GET['action']) && $_GET['action'] === 'recalc') {
    try {
        $result = recalculateBranchSalaries($conn, $branchId, $month, $year, $managerId);
        $alerts['success'][] = $result['message'];
    } catch (Throwable $e) {
        $alerts['error'][] = $e->getMessage();
    }
}

$filterSql = ['nv.ID_CN = ?', 'tk.ID_QUYEN = 2'];
$params = [$branchId];
$types = 'i';

if ($search !== '') {
    $filterSql[] = 'tk.HO_TEN LIKE ?';
    $params[] = '%' . $search . '%';
    $types .= 's';
}

if ($statusFilter !== '') {
    $filterSql[] = "COALESCE(meta.TRANG_THAI, 'cho_duyet') = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$whereClause = implode(' AND ', $filterSql);

$query = "
    SELECT
        tk.ID_TK,
        tk.HO_TEN,
        tk.EMAIL,
        tk.SDT,
        nv.CHUYEN_MON,
        lnv.LUONG_CO_BAN,
        lnv.TONG_TIEN_THUONG,
        lnv.TONG_LUONG,
        lnv.NGAY_TINH,
        meta.BASE_TONG_LUONG,
        meta.PHU_CAP,
        meta.KHOAN_TRU,
        meta.THUC_LINH,
        meta.TRANG_THAI,
        meta.GHI_CHU,
        meta.NGAY_CAP_NHAT,
        meta.NGAY_CHI_TRA
    FROM nhan_vien nv
    INNER JOIN tai_khoan tk ON tk.ID_TK = nv.ID_TK
    LEFT JOIN luong_nhan_vien lnv ON lnv.ID_TK = nv.ID_TK AND lnv.THANG = ? AND lnv.NAM = ?
    LEFT JOIN quanly_luong_chinhanh meta ON meta.ID_TK_NV = nv.ID_TK AND meta.THANG = ? AND meta.NAM = ?
    WHERE $whereClause
    ORDER BY tk.HO_TEN ASC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo '<div class="rounded-xl bg-red-50 p-6 text-center text-red-600">Không thể tải dữ liệu lương.</div>';
    return;
}

$bindTypes = 'iiii' . $types;
$bindValues = [$month, $year, $month, $year, ...$params];

$bindParams = [$bindTypes];
foreach ($bindValues as $key => $value) {
    $bindParams[] = &$bindValues[$key];
}

call_user_func_array([$stmt, 'bind_param'], $bindParams);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$stats = [
    'totalNet' => 0,
    'totalAllowance' => 0,
    'totalDeduction' => 0,
    'pending' => 0,
    'approved' => 0,
    'paid' => 0,
    'missing' => 0,
];

while ($row = $result->fetch_assoc()) {
    $base = isset($row['BASE_TONG_LUONG']) ? (int)$row['BASE_TONG_LUONG'] : (int)($row['TONG_LUONG'] ?? 0);
    $bonus = (int)($row['TONG_TIEN_THUONG'] ?? 0);
    $allowance = (int)($row['PHU_CAP'] ?? 0);
    $deduction = (int)($row['KHOAN_TRU'] ?? 0);
    $net = isset($row['THUC_LINH']) ? (int)$row['THUC_LINH'] : (int)($row['TONG_LUONG'] ?? 0);
    $status = $row['TRANG_THAI'] ?? 'cho_duyet';
    $hasSalary = isset($row['TONG_LUONG']);

    if (!$hasSalary) {
        $stats['missing']++;
    }

    $stats['totalNet'] += $net;
    $stats['totalAllowance'] += $allowance;
    $stats['totalDeduction'] += $deduction;

    if ($status === 'da_duyet') {
        $stats['approved']++;
    } elseif ($status === 'da_chi_tra') {
        $stats['paid']++;
    } else {
        $stats['pending']++;
    }

    $rows[] = [
        'id' => $row['ID_TK'],
        'name' => $row['HO_TEN'] ?: 'Chưa cập nhật',
        'email' => $row['EMAIL'] ?? '',
        'phone' => $row['SDT'] ?? '',
        'specialty' => $row['CHUYEN_MON'] ?? '—',
        'base' => $base,
        'bonus' => $bonus,
        'allowance' => $allowance,
        'deduction' => $deduction,
        'net' => $net,
        'status' => $status,
        'note' => $row['GHI_CHU'] ?? '',
        'updatedAt' => $row['NGAY_CAP_NHAT'] ?? $row['NGAY_TINH'] ?? '',
        'paidAt' => $row['NGAY_CHI_TRA'] ?? '',
        'hasSalary' => $hasSalary,
    ];
}

$stmt->close();

function formatCurrencyVND(int $amount): string
{
    return number_format($amount, 0, ',', '.') . ' ₫';
}

function statusBadgeClass(string $status): string
{
    switch ($status) {
        case 'da_duyet':
            return 'bg-blue-100 text-blue-700';
        case 'da_chi_tra':
            return 'bg-green-100 text-green-700';
        default:
            return 'bg-amber-100 text-amber-700';
    }
}

function statusLabel(string $status): string
{
    switch ($status) {
        case 'da_duyet':
            return 'Đã duyệt';
        case 'da_chi_tra':
            return 'Đã chi trả';
        default:
            return 'Chờ duyệt';
    }
}

$currentQuery = http_build_query([
    'page' => 'salaries',
    'month' => $month,
    'year' => $year,
    'search' => $search,
    'status' => $statusFilter,
]);
?>

<div class="space-y-6">
    <?php foreach (['success', 'error'] as $type): ?>
        <?php foreach ($alerts[$type] as $message): ?>
            <div class="rounded-xl border px-4 py-3 text-sm <?= $type === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <div class="rounded-2xl border border-gray-200 bg-white/80 p-6 shadow-sm">
        <form class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-6" method="GET">
            <input type="hidden" name="page" value="salaries" />
            <label class="text-sm font-medium text-gray-600">
                <span class="mb-1 block">Tháng</span>
                <select name="month" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label class="text-sm font-medium text-gray-600">
                <span class="mb-1 block">Năm</span>
                <select name="year" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    <?php for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label class="md:col-span-2 lg:col-span-2 text-sm font-medium text-gray-600">
                <span class="mb-1 block">Tìm theo tên</span>
                <input type="text" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nhập tên nhân viên" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            </label>
            <label class="text-sm font-medium text-gray-600">
                <span class="mb-1 block">Trạng thái</span>
                <select name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    <option value="">Tất cả</option>
                    <option value="cho_duyet" <?= $statusFilter === 'cho_duyet' ? 'selected' : '' ?>>Chờ duyệt</option>
                    <option value="da_duyet" <?= $statusFilter === 'da_duyet' ? 'selected' : '' ?>>Đã duyệt</option>
                    <option value="da_chi_tra" <?= $statusFilter === 'da_chi_tra' ? 'selected' : '' ?>>Đã chi trả</option>
                </select>
            </label>
            <div class="flex items-end gap-3">
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-white shadow-sm" type="submit">Lọc</button>
                <a href="?page=salaries" class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-600">Đặt lại</a>
            </div>
        </form>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="?<?= htmlspecialchars($currentQuery . '&action=recalc', ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">
                <i class="fas fa-sync-alt"></i>
                Tính lại lương
            </a>
            <a href="./components/export_branch_salary_excel.php?<?= htmlspecialchars($currentQuery, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-lg border border-green-200 px-4 py-2 text-sm font-semibold text-green-700 hover:bg-green-50">
                <i class="fas fa-file-excel"></i>
                Xuất Excel
            </a>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white/90 p-5 shadow-sm">
            <p class="text-sm text-gray-500">Tổng lương thực lĩnh</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900"><?= formatCurrencyVND($stats['totalNet']) ?></p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white/90 p-5 shadow-sm">
            <p class="text-sm text-gray-500">Phụ cấp đã duyệt</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-600"><?= formatCurrencyVND($stats['totalAllowance']) ?></p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white/90 p-5 shadow-sm">
            <p class="text-sm text-gray-500">Các khoản khấu trừ</p>
            <p class="mt-2 text-2xl font-semibold text-rose-600"><?= formatCurrencyVND($stats['totalDeduction']) ?></p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white/90 p-5 shadow-sm">
            <p class="text-sm text-gray-500">Trạng thái</p>
            <p class="mt-2 text-sm text-gray-600">Chờ duyệt: <?= $stats['pending'] ?> · Đã duyệt: <?= $stats['approved'] ?> · Đã chi trả: <?= $stats['paid'] ?></p>
            <?php if ($stats['missing'] > 0): ?>
                <p class="mt-1 text-xs text-amber-600"><?= $stats['missing'] ?> nhân viên chưa có bảng lương.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white/95 shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Nhân viên</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Lương cơ bản</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Thưởng</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Phụ cấp</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Khấu trừ</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Thực lĩnh</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-600">Trạng thái</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-600">Thao tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($rows)): ?>
                <tr>
                    <td class="px-4 py-6 text-center text-gray-500" colspan="8">Chưa có nhân viên phù hợp với bộ lọc.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr class="hover:bg-indigo-50/30">
                        <td class="px-4 py-3">
                            <div class="font-semibold text-gray-900"><?= htmlspecialchars($row['name']) ?></div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($row['specialty']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-right text-gray-700"><?= formatCurrencyVND($row['base']) ?></td>
                        <td class="px-4 py-3 text-right text-gray-700"><?= formatCurrencyVND($row['bonus']) ?></td>
                        <td class="px-4 py-3 text-right text-emerald-600 font-semibold"><?= formatCurrencyVND($row['allowance']) ?></td>
                        <td class="px-4 py-3 text-right text-rose-600 font-semibold">-<?= formatCurrencyVND($row['deduction']) ?></td>
                        <td class="px-4 py-3 text-right text-gray-900 font-semibold"><?= formatCurrencyVND($row['net']) ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= statusBadgeClass($row['status']) ?>">
                                <?= statusLabel($row['status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 px-3 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-50"
                                data-employee="<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>"
                                data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>"
                                data-allowance="<?= $row['allowance'] ?>"
                                data-deduction="<?= $row['deduction'] ?>"
                                data-net="<?= $row['net'] ?>"
                                data-base="<?= $row['base'] ?>"
                                data-bonus="<?= $row['bonus'] ?>"
                                data-status="<?= $row['status'] ?>"
                                data-note="<?= htmlspecialchars($row['note'], ENT_QUOTES, 'UTF-8') ?>"
                                data-has-salary="<?= $row['hasSalary'] ? '1' : '0' ?>"
                                onclick="openSalaryModal(this)">
                                <i class="fas fa-edit"></i>
                                Điều chỉnh
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="salaryModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4">
    <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Điều chỉnh lương</p>
                <h3 id="modalEmployeeName" class="text-xl font-semibold text-gray-900"></h3>
            </div>
            <button class="text-gray-400 hover:text-gray-600" onclick="closeSalaryModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form class="mt-4 space-y-4" method="POST" action="./components/manager_salary_action.php" id="salaryForm">
            <input type="hidden" name="employee_id" id="modalEmployeeId" />
            <input type="hidden" name="month" value="<?= $month ?>" />
            <input type="hidden" name="year" value="<?= $year ?>" />
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/app/admin/manager_dashboard.php?page=salaries', ENT_QUOTES, 'UTF-8') ?>" />
            <div class="grid gap-4 md:grid-cols-2">
                <label class="text-sm font-medium text-gray-600">
                    <span class="mb-1 block">Phụ cấp (₫)</span>
                    <input type="number" min="0" name="allowance" id="modalAllowance" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </label>
                <label class="text-sm font-medium text-gray-600">
                    <span class="mb-1 block">Khấu trừ (₫)</span>
                    <input type="number" min="0" name="deduction" id="modalDeduction" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </label>
            </div>
            <label class="text-sm font-medium text-gray-600">
                <span class="mb-1 block">Ghi chú nội bộ</span>
                <textarea name="note" id="modalNote" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2"></textarea>
            </label>
            <label class="text-sm font-medium text-gray-600">
                <span class="mb-1 block">Trạng thái</span>
                <select name="status" id="modalStatus" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    <option value="cho_duyet">Chờ duyệt</option>
                    <option value="da_duyet">Đã duyệt</option>
                    <option value="da_chi_tra">Đã chi trả</option>
                </select>
            </label>
            <div class="rounded-xl bg-slate-50 p-4 text-sm">
                <p><span class="text-gray-500">Lương cơ bản:</span> <span id="modalBase" class="font-semibold text-gray-800"></span></p>
                <p><span class="text-gray-500">Thưởng:</span> <span id="modalBonus" class="font-semibold text-gray-800"></span></p>
                <p><span class="text-gray-500">Thực lĩnh dự kiến:</span> <span id="modalNetPreview" class="font-semibold text-indigo-700"></span></p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" class="rounded-lg border border-gray-200 px-4 py-2 text-sm" onclick="closeSalaryModal()">Hủy</button>
                <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white">Lưu cập nhật</button>
            </div>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('salaryModal');
const form = document.getElementById('salaryForm');
const allowanceInput = document.getElementById('modalAllowance');
const deductionInput = document.getElementById('modalDeduction');
const baseLabel = document.getElementById('modalBase');
const bonusLabel = document.getElementById('modalBonus');
const netLabel = document.getElementById('modalNetPreview');
const formatter = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' });

function openSalaryModal(button) {
    document.getElementById('modalEmployeeId').value = button.dataset.employee;
    document.getElementById('modalEmployeeName').textContent = button.dataset.name;
    document.getElementById('modalStatus').value = button.dataset.status;
    document.getElementById('modalNote').value = button.dataset.note || '';

    const base = Number(button.dataset.base || 0);
    const bonus = Number(button.dataset.bonus || 0);
    const totalCore = base + bonus;

    allowanceInput.value = button.dataset.allowance || 0;
    deductionInput.value = button.dataset.deduction || 0;
    allowanceInput.dataset.totalCore = totalCore;

    baseLabel.textContent = formatter.format(base);
    bonusLabel.textContent = formatter.format(bonus);
    updateNetPreview();

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeSalaryModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function updateNetPreview() {
    const core = Number(allowanceInput.dataset.totalCore || 0);
    const allowance = Number(allowanceInput.value || 0);
    const deduction = Number(deductionInput.value || 0);
    const net = Math.max(0, core + allowance - deduction);
    netLabel.textContent = formatter.format(net);
}

allowanceInput.addEventListener('input', updateNetPreview);
deductionInput.addEventListener('input', updateNetPreview);
</script>
