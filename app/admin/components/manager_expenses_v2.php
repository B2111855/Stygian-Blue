<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../database/config.php';
require_once __DIR__ . '/../../helpers/assets.php';

$idTk = $_SESSION['ID_TK'] ?? null;

if (!$idTk) {
    die("<p class='text-red-600'>Lỗi: Bạn cần đăng nhập để quản lý chi phí.</p>");
}

// Lấy thông tin chi nhánh của nhân viên
$branchStmt = $conn->prepare("SELECT nv.ID_CN, cn.TEN_CN FROM nhan_vien nv JOIN chi_nhanh cn ON cn.ID_CN = nv.ID_CN WHERE nv.ID_TK = ?");
$branchStmt->bind_param('s', $idTk);
$branchStmt->execute();
$branchData = $branchStmt->get_result()->fetch_assoc();

if (!$branchData) {
    die("<p class='text-red-600'>Lỗi: Không xác định được chi nhánh của nhân viên!</p>");
}

$idCn = (int)$branchData['ID_CN'];
$branchName = $branchData['TEN_CN'] ?? 'Chi nhánh';

// Xác định tháng hiện tại
$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$selectedMonthObj = DateTime::createFromFormat('Y-m', $selectedMonth);
$selectedMonthLabel = $selectedMonthObj ? $selectedMonthObj->format('m/Y') : date('m/Y');

// Kiểm tra xem tháng được chọn có phải tháng hiện tại không (chỉ tháng hiện tại mới có thể nhập/sửa)
$currentMonth = date('Y-m');
$isCurrentMonth = ($selectedMonth === $currentMonth);

// Kiểm tra xem có quá hạn nhập liệu (cuối tháng) không
$currentDate = new DateTime();
$endOfMonth = new DateTime('last day of this month');
$daysUntilEndOfMonth = $endOfMonth->diff($currentDate)->days;
$isEndOfMonth = $daysUntilEndOfMonth <= 3; // Nhắc nhở khi còn 3 ngày cuối tháng

// Lấy danh sách loại chi phí (active) - được admin tạo sẵn
$categoryStmt = $conn->prepare("SELECT ID_LOAI, TEN_LOAI, MOTA_LOAI FROM chi_phi_loai WHERE TRANG_THAI = 'active' ORDER BY TEN_LOAI");
$categoryStmt->execute();
$categoryResult = $categoryStmt->get_result();
$allCategories = [];
while ($row = $categoryResult->fetch_assoc()) {
    $allCategories[] = $row;
}

// Lấy chi phí phát sinh của tháng hiện tại (của chi nhánh này)
$expenseStmt = $conn->prepare("SELECT 
    cp.ID_CP,
    cp.ID_LOAI,
    cpl.TEN_LOAI,
    cpl.MOTA_LOAI,
    cp.GIA_TRI,
    cp.NGAY_GIO,
    cp.MOTA_CP
FROM chi_phi_phat_sinh cp
LEFT JOIN chi_phi_loai cpl ON cp.ID_LOAI = cpl.ID_LOAI
WHERE cp.ID_CN = ? AND cp.THANG = ?
ORDER BY cpl.TEN_LOAI ASC");

$expenseStmt->bind_param('is', $idCn, $selectedMonth);
$expenseStmt->execute();
$expenseResult = $expenseStmt->get_result();

// Tạo map giá trị chi phí theo loại
$expensesByCategory = [];
$totalExpenses = 0;

while ($row = $expenseResult->fetch_assoc()) {
    $idLoai = (int)$row['ID_LOAI'];
    $expensesByCategory[$idLoai] = [
        'ID_CP' => (int)$row['ID_CP'],
        'TEN_LOAI' => $row['TEN_LOAI'],
        'GIA_TRI' => (float)$row['GIA_TRI'],
        'NGAY_GIO' => $row['NGAY_GIO'],
        'MOTA_CP' => $row['MOTA_CP']
    ];
    $totalExpenses += (float)$row['GIA_TRI'];
}

function formatCurrency($value) {
    return number_format((float)$value, 0, ',', '.');
}

function formatDate($date) {
    if (empty($date)) {
        return 'Chưa nhập';
    }
    $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $date);
    return $dateTime ? $dateTime->format('d/m/Y H:i') : 'Không xác định';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Chi Phí Phát Sinh - <?= htmlspecialchars($branchName) ?></title>
    <?= sb_tailwind_link_tag(); ?>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen py-8 px-4">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm uppercase tracking-widest text-slate-500 font-semibold">Quản lý chi nhánh</p>
                    <h1 class="text-4xl font-bold text-slate-900 mt-2">Chi Phí Phát Sinh</h1>
                    <p class="text-slate-600 mt-2">Chi nhánh: <span class="font-semibold"><?= htmlspecialchars($branchName) ?></span></p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-slate-600">Tháng hiện tại</p>
                    <p class="text-3xl font-bold text-indigo-600"><?= htmlspecialchars($selectedMonthLabel) ?></p>
                </div>
            </div>
        </div>

        <!-- Cảnh báo cuối tháng -->
        <?php if ($isEndOfMonth): ?>
            <div class="bg-amber-50 border-l-4 border-amber-500 rounded-lg p-4 mb-6">
                <div class="flex items-start gap-3">
                    <div>
                        <p class="font-semibold text-amber-900">Nhắc nhở: Cuối tháng!</p>
                        <p class="text-sm text-amber-800 mt-1">Còn <strong><?= $daysUntilEndOfMonth ?></strong> ngày cuối tháng. Vui lòng hoàn thành nhập liệu chi phí phát sinh trước khi kết thúc tháng.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Chọn Tháng Xem -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-8">
            <label class="block text-sm font-semibold text-slate-600 mb-3">Chọn Tháng Xem</label>
            <div class="flex items-center gap-3">
                <input 
                    type="month" 
                    id="monthSelect"
                    value="<?= htmlspecialchars($selectedMonth) ?>"
                    class="px-4 py-2 rounded-lg border border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                <button 
                    onclick="changeMonth()"
                    class="px-6 py-2 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700 transition">
                    Xem
                </button>
                <?php if (!$isCurrentMonth): ?>
                    <span class="text-sm text-slate-500">Chế độ xem - không thể nhập/sửa</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Thông tin tổng quát -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-8">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm uppercase tracking-wide text-slate-500 font-semibold">Số Loại Chi Phí</p>
                    <p class="text-4xl font-bold text-indigo-600 mt-2"><?= count($allCategories) ?></p>
                </div>
                <div>
                    <p class="text-sm uppercase tracking-wide text-slate-500 font-semibold">Tổng Chi Phí Tháng <?= htmlspecialchars($selectedMonthLabel) ?></p>
                    <p class="text-4xl font-bold text-slate-700 mt-2"><?= formatCurrency($totalExpenses) ?> đ</p>
                </div>
            </div>
        </div>

        <!-- Danh sách chi phí -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-gradient-to-r from-indigo-50 to-indigo-100 border-b border-indigo-200 px-6 py-4">
                <h2 class="text-lg font-bold text-slate-900">Chi Phí Phát Sinh Tháng <?= htmlspecialchars($selectedMonthLabel) ?></h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-slate-200 bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left font-semibold text-slate-700">Loại Chi Phí</th>
                            <th class="px-6 py-4 text-right font-semibold text-slate-700">Giá Trị (VND)</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-700">Ngày Nhập</th>
                            <?php if ($isCurrentMonth): ?>
                                <th class="px-6 py-4 text-center font-semibold text-slate-700">Thao Tác</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($allCategories as $category): ?>
                            <?php 
                                $idLoai = (int)$category['ID_LOAI'];
                                $hasData = isset($expensesByCategory[$idLoai]);
                                $giaTriValue = $hasData ? $expensesByCategory[$idLoai]['GIA_TRI'] : 0;
                                $ngayGio = $hasData ? $expensesByCategory[$idLoai]['NGAY_GIO'] : null;
                                $idCp = $hasData ? $expensesByCategory[$idLoai]['ID_CP'] : null;
                            ?>
                            <tr class="hover:bg-slate-50 transition <?= (!$hasData && $isCurrentMonth) ? 'bg-yellow-50' : '' ?>">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-semibold text-slate-900"><?= htmlspecialchars($category['TEN_LOAI']) ?></p>
                                        <?php if (!empty($category['MOTA_LOAI'])): ?>
                                            <p class="text-sm text-slate-500 mt-1"><?= htmlspecialchars($category['MOTA_LOAI']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <p class="text-lg font-bold text-indigo-600"><?= formatCurrency($giaTriValue) ?> đ</p>
                                    <?php if (!$hasData && $isCurrentMonth): ?>
                                        <p class="text-xs text-amber-600 mt-1">Chưa nhập</p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm text-slate-600"><?= formatDate($ngayGio) ?></p>
                                </td>
                                <?php if ($isCurrentMonth): ?>
                                    <td class="px-6 py-4 text-center">
                                        <button 
                                            onclick="editExpense(<?= $idLoai ?>, '<?= htmlspecialchars($category['TEN_LOAI'], ENT_QUOTES) ?>', <?= $giaTriValue ?>, '<?= $idCp ?? 'null' ?>')"
                                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition">
                                            <?= $hasData ? 'Sửa' : 'Nhập' ?>
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Total Row -->
            <div class="bg-gradient-to-r from-indigo-50 to-indigo-100 border-t border-indigo-200 px-6 py-4 flex items-center justify-between">
                <p class="font-bold text-slate-900 text-lg">Tổng Cộng</p>
                <p class="text-2xl font-bold text-indigo-600"><?= formatCurrency($totalExpenses) ?> đ</p>
            </div>
        </div>

        <!-- Info -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p class="text-sm text-blue-900">
                <strong>ℹ️ Hướng dẫn:</strong> 
                <br>• <strong>Tháng hiện tại:</strong> Có thể nhập hoặc sửa giá trị chi phí cho các loại được quản trị viên tạo sẵn
                <br>• <strong>Tháng trước:</strong> Chỉ có thể xem, không thể nhập/sửa
                <br>• Loại chi phí chưa có giá trị sẽ được tô nền vàng
                <br>• <strong>Không thể thêm hoặc xóa loại chi phí</strong> - đó là quyền của quản trị viên hệ thống
            </p>
        </div>
    </div>

    <!-- Modal: Nhập/Sửa Giá Trị Chi Phí -->
    <div id="expenseModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto">
            <h2 class="text-2xl font-bold text-slate-900 mb-2" id="modalTitle">Nhập Chi Phí</h2>
            <p class="text-sm text-slate-600 mb-6" id="modalSubtitle">Loại chi phí</p>

            <form id="expenseForm" class="space-y-4">
                <input type="hidden" id="expenseIdLoai" name="id_loai">
                <input type="hidden" id="expenseIdCp" name="id_cp">

                <div>
                    <label class="block text-sm font-semibold text-slate-600 mb-2">Giá Trị (VND) *</label>
                    <input 
                        type="number" 
                        id="expenseAmount" 
                        name="gia_tri" 
                        required 
                        min="0" 
                        step="1000"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                        placeholder="Nhập giá trị...">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-600 mb-2">Ghi Chú (tùy chọn)</label>
                    <textarea 
                        id="expenseNote" 
                        name="mota_cp"
                        rows="2"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                        placeholder="Ghi chú thêm..."></textarea>
                </div>

                <div class="flex gap-3 pt-4">
                    <button 
                        type="button" 
                        onclick="closeExpenseModal()"
                        class="flex-1 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Hủy
                    </button>
                    <button 
                        type="submit"
                        class="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition">
                        Lưu
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const CURRENT_MONTH = '<?= date('Y-m') ?>';
        const BRANCH_ID = <?= $idCn ?>;

        function changeMonth() {
            const selectedMonth = document.getElementById('monthSelect').value;
            if (selectedMonth) {
                window.location.href = '?page=expenses&month=' + selectedMonth;
            }
        }

        function editExpense(idLoai, tenLoai, giaTriCu, idCpStr) {
            const idCp = idCpStr !== 'null' ? parseInt(idCpStr) : null;
            
            document.getElementById('modalTitle').textContent = idCp ? 'Sửa Chi Phí' : 'Nhập Chi Phí';
            document.getElementById('modalSubtitle').textContent = tenLoai;
            document.getElementById('expenseIdLoai').value = idLoai;
            document.getElementById('expenseIdCp').value = idCp || '';
            document.getElementById('expenseAmount').value = giaTriCu > 0 ? giaTriCu : '';
            document.getElementById('expenseNote').value = '';
            
            document.getElementById('expenseModal').classList.remove('hidden');
            document.getElementById('expenseModal').classList.add('flex');
            document.getElementById('expenseAmount').focus();
            document.getElementById('expenseAmount').select();
        }

        function closeExpenseModal() {
            document.getElementById('expenseModal').classList.add('hidden');
            document.getElementById('expenseModal').classList.remove('flex');
        }

        document.getElementById('expenseForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const idLoai = parseInt(document.getElementById('expenseIdLoai').value);
            const idCp = document.getElementById('expenseIdCp').value;
            const giaTriValue = parseFloat(document.getElementById('expenseAmount').value);
            const motaCp = document.getElementById('expenseNote').value.trim();

            if (!idLoai || giaTriValue < 0) {
                alert('Vui lòng nhập giá trị hợp lệ');
                return;
            }

            const formData = new FormData();
            formData.append('id_loai', idLoai);
            formData.append('id_cn', BRANCH_ID);
            formData.append('thang', CURRENT_MONTH);
            formData.append('gia_tri', giaTriValue);
            formData.append('mota_cp', motaCp);

            // Nếu có ID_CP, đó là update; không có là insert
            const endpoint = idCp 
                ? `components/api_chi_phi_gia_tri.php?action=update&id=${idCp}` 
                : 'components/api_chi_phi_gia_tri.php';
            const method = idCp ? 'PUT' : 'POST';

            try {
                const response = await fetch(endpoint, {
                    method: method,
                    body: formData
                });

                const result = await response.json();
                if (result.status === 'success') {
                    alert('Lưu chi phí thành công');
                    location.reload();
                } else {
                    alert('Lỗi: ' + (result.message || 'Không xác định'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Lỗi kết nối');
            }
        });

        // Close modal on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeExpenseModal();
        });

        // Close modal when clicking outside
        document.getElementById('expenseModal').addEventListener('click', (e) => {
            if (e.target.id === 'expenseModal') {
                closeExpenseModal();
            }
        });
    </script>
</body>
</html>
