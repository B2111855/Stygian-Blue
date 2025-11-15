<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../database/config.php';

$employeeId = $_SESSION['ID_TK'] ?? null;
if (!$employeeId) {
    echo '<div class="rounded-xl bg-white p-6 text-center text-red-600 shadow">Bạn chưa đăng nhập.</div>';
    return;
}

$currentMonth = (int)date('n');
$currentYear = (int)date('Y');
$month = isset($_GET['thang']) ? max(1, min(12, (int)$_GET['thang'])) : $currentMonth;
$year = isset($_GET['nam']) ? (int)$_GET['nam'] : $currentYear;

$salaryPeriods = [];
$stmtList = $conn->prepare('SELECT THANG, NAM, TONG_LUONG FROM luong_nhan_vien WHERE ID_TK = ? ORDER BY NAM DESC, THANG DESC');
$stmtList->bind_param('s', $employeeId);
$stmtList->execute();
$resultList = $stmtList->get_result();
while ($row = $resultList->fetch_assoc()) {
    $salaryPeriods[] = [
        'THANG' => (int)($row['THANG'] ?? 0),
        'NAM' => (int)($row['NAM'] ?? 0),
        'TONG_LUONG' => (float)($row['TONG_LUONG'] ?? 0),
    ];
}
$stmtList->close();

$stmt = $conn->prepare('SELECT * FROM luong_nhan_vien WHERE ID_TK = ? AND THANG = ? AND NAM = ? LIMIT 1');
$stmt->bind_param('sii', $employeeId, $month, $year);
$stmt->execute();
$result = $stmt->get_result();
$salary = $result->fetch_assoc();
$stmt->close();

$previousSalary = null;
if (!empty($salaryPeriods)) {
    foreach ($salaryPeriods as $index => $period) {
        if ($period['THANG'] === $month && $period['NAM'] === $year) {
            if (isset($salaryPeriods[$index + 1])) {
                $prev = $salaryPeriods[$index + 1];
                $stmtPrev = $conn->prepare('SELECT TONG_LUONG FROM luong_nhan_vien WHERE ID_TK = ? AND THANG = ? AND NAM = ? LIMIT 1');
                $stmtPrev->bind_param('sii', $employeeId, $prev['THANG'], $prev['NAM']);
                $stmtPrev->execute();
                $resultPrev = $stmtPrev->get_result();
                $previousSalary = $resultPrev->fetch_assoc();
                if ($previousSalary) {
                    $previousSalary['THANG'] = $prev['THANG'];
                    $previousSalary['NAM'] = $prev['NAM'];
                }
                $stmtPrev->close();
            }
            break;
        }
    }
}

if ($salary && isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = sprintf('bang-luong-%02d-%d.csv', $month, $year);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    echo chr(239) . chr(187) . chr(191);
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Tháng', 'Năm', 'Lương cơ bản', 'Thưởng (%)', 'Tiền thưởng', 'Tổng lương', 'Ngày tính']);
    fputcsv($output, [
        $month,
        $year,
        $salary['LUONG_CO_BAN'] ?? 0,
        isset($salary['PHAN_TRAM_THUONG']) ? ($salary['PHAN_TRAM_THUONG'] * 100) : 0,
        $salary['TONG_TIEN_THUONG'] ?? 0,
        $salary['TONG_LUONG'] ?? 0,
        $salary['NGAY_TINH'] ?? '',
    ]);

    if (isset($salary['SO_GIO_TANG_CA']) || isset($salary['TIEN_TANG_CA']) || isset($salary['PHU_CAP'])) {
        fputcsv($output, []);
        fputcsv($output, ['Thông tin bổ sung']);
        if (isset($salary['SO_GIO_TANG_CA'])) {
            fputcsv($output, ['Số giờ tăng ca', $salary['SO_GIO_TANG_CA']]);
        }
        if (isset($salary['TIEN_TANG_CA'])) {
            fputcsv($output, ['Tiền tăng ca', $salary['TIEN_TANG_CA']]);
        }
        if (isset($salary['PHU_CAP'])) {
            fputcsv($output, ['Phụ cấp', $salary['PHU_CAP']]);
        }
        if (isset($salary['GHI_CHU'])) {
            fputcsv($output, ['Ghi chú', $salary['GHI_CHU']]);
        }
    }
    fclose($output);
    exit;
}

$baseSalary = $salary['LUONG_CO_BAN'] ?? 0;
$bonusRate = isset($salary['PHAN_TRAM_THUONG']) ? (float)$salary['PHAN_TRAM_THUONG'] : 0;
$bonusAmount = $salary['TONG_TIEN_THUONG'] ?? 0;
$totalSalary = $salary['TONG_LUONG'] ?? 0;
$salaryDiff = null;
if ($previousSalary && isset($previousSalary['TONG_LUONG'])) {
    $salaryDiff = (float)$totalSalary - (float)$previousSalary['TONG_LUONG'];
}

function formatCurrency($value)
{
    return number_format((float)$value, 0, ',', '.') . ' VND';
}

function formatPercent($value)
{
    return number_format((float)$value * 100, 0) . '%';
}
?>

<div class="space-y-8 rounded-2xl border border-gray-200 bg-white/90 p-8 shadow-xl fade-in">
    <header class="flex flex-col gap-2 text-center md:text-left">
        <h1 class="text-3xl font-semibold text-indigo-700">Bảng lương cá nhân</h1>
        <p class="text-sm text-gray-600">Theo dõi thu nhập từng tháng và xuất dữ liệu để lưu trữ hoặc báo cáo.</p>
    </header>

    <form method="GET" class="rounded-2xl border border-gray-200 bg-slate-50 p-6 shadow-sm">
        <input type="hidden" name="page" value="staff_salary">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <label class="flex flex-col gap-2 text-sm font-medium text-gray-600">
                <span>Tháng</span>
                <select name="thang" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <?php foreach ($salaryPeriods as $period): ?>
                        <option value="<?= (int)$period['THANG'] ?>" <?= ($period['THANG'] === $month && $period['NAM'] === $year) ? 'selected' : '' ?>>
                            Tháng <?= (int)$period['THANG'] ?> / <?= (int)$period['NAM'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="flex flex-col gap-2 text-sm font-medium text-gray-600">
                <span>Năm</span>
                <select name="nam" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <?php
                    $yearOptions = [];
                    foreach ($salaryPeriods as $period) {
                        $yearOptions[$period['NAM']] = true;
                    }
                    foreach (array_keys($yearOptions) as $yearOption): ?>
                        <option value="<?= (int)$yearOption ?>" <?= (int)$yearOption === $year ? 'selected' : '' ?>>
                            Năm <?= (int)$yearOption ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="flex items-end gap-3 md:col-span-2 xl:col-span-3">
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Xem bảng lương
                </button>
                <?php if ($salary): ?>
                    <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv'])), ENT_QUOTES, 'UTF-8') ?>"
                       class="inline-flex items-center justify-center rounded-lg border border-indigo-200 px-5 py-2 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Xuất CSV
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?php if ($salary): ?>
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Lương cơ bản</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900"><?= htmlspecialchars(formatCurrency($baseSalary), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-1 text-xs text-gray-400">Mức lương theo hợp đồng.</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Thưởng</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900"><?= htmlspecialchars(formatPercent($bonusRate), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-1 text-xs text-gray-400">Tương đương <?= htmlspecialchars(formatCurrency($bonusAmount), ENT_QUOTES, 'UTF-8') ?>.</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Tổng thu nhập</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900"><?= htmlspecialchars(formatCurrency($totalSalary), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-1 text-xs text-gray-400">
                    Ngày tính <?= htmlspecialchars($salary['NGAY_TINH'] ?? '', ENT_QUOTES, 'UTF-8') ?>.
                </p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">So với kỳ trước</p>
                <?php if ($salaryDiff !== null): ?>
                    <?php $diffPositive = $salaryDiff >= 0; ?>
                    <p class="mt-2 text-2xl font-semibold <?= $diffPositive ? 'text-emerald-600' : 'text-rose-600' ?>">
                        <?= $diffPositive ? '+' : '−' ?><?= htmlspecialchars(formatCurrency(abs($salaryDiff)), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p class="mt-1 text-xs text-gray-400">
                        So với tháng <?= htmlspecialchars($previousSalary['THANG'] ?? '', ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars($previousSalary['NAM'] ?? '', ENT_QUOTES, 'UTF-8') ?>.
                    </p>
                <?php else: ?>
                    <p class="mt-2 text-xl font-medium text-gray-500">Chưa có dữ liệu kỳ trước</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <header class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Chi tiết thu nhập</h2>
                <p class="text-sm text-gray-500">Tổng hợp các khoản trong kỳ lương đã chọn.</p>
            </header>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto text-left text-sm">
                    <thead>
                        <tr class="bg-indigo-700 text-indigo-50">
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide">Khoản mục</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide">Giá trị</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide">Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr class="hover:bg-indigo-50">
                            <td class="px-6 py-4 font-medium text-gray-700">Lương cơ bản</td>
                            <td class="px-6 py-4 text-gray-900"><?= htmlspecialchars(formatCurrency($baseSalary), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-6 py-4 text-gray-500">Theo hợp đồng lao động.</td>
                        </tr>
                        <tr class="hover:bg-indigo-50">
                            <td class="px-6 py-4 font-medium text-gray-700">Thưởng theo hiệu suất</td>
                            <td class="px-6 py-4 text-gray-900"><?= htmlspecialchars(formatCurrency($bonusAmount), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-6 py-4 text-gray-500">Tỷ lệ thưởng <?= htmlspecialchars(formatPercent($bonusRate), ENT_QUOTES, 'UTF-8') ?>.</td>
                        </tr>
                        <?php if (isset($salary['SO_GIO_TANG_CA']) || isset($salary['TIEN_TANG_CA'])): ?>
                            <tr class="hover:bg-indigo-50">
                                <td class="px-6 py-4 font-medium text-gray-700">Tăng ca</td>
                                <td class="px-6 py-4 text-gray-900"><?= htmlspecialchars(formatCurrency($salary['TIEN_TANG_CA'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-gray-500">Số giờ: <?= htmlspecialchars($salary['SO_GIO_TANG_CA'] ?? 0, ENT_QUOTES, 'UTF-8') ?>.</td>
                            </tr>
                        <?php endif; ?>
                        <?php if (isset($salary['PHU_CAP'])): ?>
                            <tr class="hover:bg-indigo-50">
                                <td class="px-6 py-4 font-medium text-gray-700">Phụ cấp</td>
                                <td class="px-6 py-4 text-gray-900"><?= htmlspecialchars(formatCurrency($salary['PHU_CAP'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-gray-500">Bao gồm phụ cấp ca kíp, ăn ca (nếu có).</td>
                            </tr>
                        <?php endif; ?>
                        <tr class="hover:bg-indigo-50">
                            <td class="px-6 py-4 font-medium text-gray-700">Tổng lương nhận</td>
                            <td class="px-6 py-4 text-indigo-700 font-semibold"><?= htmlspecialchars(formatCurrency($totalSalary), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-6 py-4 text-gray-500">Đã bao gồm thưởng và phụ cấp.</td>
                        </tr>
                        <?php if (!empty($salary['GHI_CHU'])): ?>
                            <tr class="hover:bg-indigo-50">
                                <td class="px-6 py-4 font-medium text-gray-700">Ghi chú</td>
                                <td colspan="2" class="px-6 py-4 text-gray-600">
                                    <?= nl2br(htmlspecialchars($salary['GHI_CHU'], ENT_QUOTES, 'UTF-8')) ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if (!empty($salaryPeriods)): ?>
            <section class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <header class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Lịch sử lương gần đây</h2>
                    <p class="text-sm text-gray-500">So sánh nhanh các kỳ lương đã nhận.</p>
                </header>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto text-left text-sm">
                        <thead>
                            <tr class="bg-slate-100 text-gray-600">
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide">Kỳ lương</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide">Tổng lương</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide">Truy cập</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach (array_slice($salaryPeriods, 0, 6) as $period): ?>
                                <?php $isActive = ($period['THANG'] === $month && $period['NAM'] === $year); ?>
                                <tr class="transition-colors duration-150 <?= $isActive ? 'bg-indigo-50' : 'hover:bg-indigo-50' ?>">
                                    <td class="px-6 py-4 font-medium text-gray-700">Tháng <?= (int)$period['THANG'] ?>/<?= (int)$period['NAM'] ?></td>
                                    <td class="px-6 py-4 text-gray-900"><?= htmlspecialchars(formatCurrency($period['TONG_LUONG']), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-6 py-4">
                                        <?php if ($isActive): ?>
                                            <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-600">Đang xem</span>
                                        <?php else: ?>
                                            <?php $query = http_build_query(['page' => 'staff_salary', 'thang' => (int)$period['THANG'], 'nam' => (int)$period['NAM']]); ?>
                                            <a href="?<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>"
                                               class="text-sm font-semibold text-indigo-600 transition hover:text-indigo-800">Xem</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    <?php else: ?>
        <div class="rounded-2xl border border-dashed border-amber-200 bg-amber-50 p-10 text-center text-amber-700">
            Không tìm thấy dữ liệu lương cho tháng <?= htmlspecialchars($month, ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?>.
        </div>
    <?php endif; ?>
</div>

<style>
@keyframes fade-in { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.fade-in { animation: fade-in 0.4s ease-out both; }
</style>
