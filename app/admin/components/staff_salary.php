<?php
include '../../database/config.php';

$employee_id = $_SESSION['ID_TK'] ?? null;
if (!$employee_id) {
    echo "Bạn chưa đăng nhập.";
    exit;
}

// Lấy tháng và năm được chọn, mặc định là tháng hiện tại
$thang = $_GET['thang'] ?? date('n');
$nam = $_GET['nam'] ?? date('Y');

// Truy vấn danh sách tháng-năm có lương để đổ dropdown
$dsLuong = [];
$stmtList = $conn->prepare("SELECT DISTINCT THANG, NAM FROM luong_nhan_vien WHERE ID_TK = ? ORDER BY NAM DESC, THANG DESC");
$stmtList->bind_param("s", $employee_id);
$stmtList->execute();
$resultList = $stmtList->get_result();
while ($row = $resultList->fetch_assoc()) {
    $dsLuong[] = $row;
}

// Truy vấn lương tháng đang chọn
$stmt = $conn->prepare("SELECT * FROM luong_nhan_vien WHERE ID_TK = ? AND THANG = ? AND NAM = ?");
$stmt->bind_param("sii", $employee_id, $thang, $nam);
$stmt->execute();
$result = $stmt->get_result();
$salary = $result->fetch_assoc();
?>



<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen p-8 font-sans text-gray-800">
    <div class="max-w-4xl mx-auto bg-white shadow-xl rounded-xl p-8 fade-in">
        <h1 class="text-3xl font-extrabold text-indigo-700 text-center mb-6">💼 Bảng Lương Cá Nhân</h1>

        <!-- Form chọn tháng -->
        <form method="GET" class="flex flex-wrap gap-4 justify-center mb-8">
            <input type="hidden" name="page" value="staff_salary">

            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-600 mb-1">Tháng</label>
                <select name="thang" class="border border-gray-300 rounded-lg px-4 py-2 w-40 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    <?php foreach ($dsLuong as $item): ?>
                        <?php if ($item['NAM'] == $nam): ?>
                            <option value="<?= $item['THANG'] ?>" <?= $item['THANG'] == $thang ? 'selected' : '' ?>>
                                Tháng <?= $item['THANG'] ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-600 mb-1">Năm</label>
                <select name="nam" class="border border-gray-300 rounded-lg px-4 py-2 w-40 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    <?php
                    $namList = array_unique(array_column($dsLuong, 'NAM'));
                    foreach ($namList as $namItem): ?>
                        <option value="<?= $namItem ?>" <?= $namItem == $nam ? 'selected' : '' ?>>
                            Năm <?= $namItem ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-end">
                <button type="submit"
                        class="bg-indigo-600 text-white font-semibold px-5 py-2 rounded-lg hover:bg-indigo-700 transition">
                    📊 Xem
                </button>
            </div>
        </form>

        <!-- Bảng lương -->
        <?php if ($salary): ?>
            <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
                <table class="min-w-full text-sm text-center">
                    <thead class="bg-indigo-600 text-white font-medium">
                        <tr>
                            <th class="px-6 py-3">Lương cơ bản</th>
                            <th class="px-6 py-3">Thưởng (%)</th>
                            <th class="px-6 py-3">Tiền thưởng</th>
                            <th class="px-6 py-3">Tổng lương</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr class="hover:bg-indigo-50">
                            <td class="px-6 py-4"><?= number_format($salary['LUONG_CO_BAN']) ?> VND</td>
                            <td class="px-6 py-4"><?= $salary['PHAN_TRAM_THUONG'] * 100 ?>%</td>
                            <td class="px-6 py-4"><?= number_format($salary['TONG_TIEN_THUONG']) ?> VND</td>
                            <td class="px-6 py-4 text-green-600 font-semibold"><?= number_format($salary['TONG_LUONG']) ?> VND</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-right italic text-sm text-gray-500 mt-2">Ngày tính: <?= $salary['NGAY_TINH'] ?></p>
        <?php else: ?>
            <p class="text-center text-red-600 font-medium text-lg mt-6">⚠️ Không có dữ liệu lương cho tháng <?= $thang ?>/<?= $nam ?>.</p>
        <?php endif; ?>
    </div>

    <!-- Animation style -->
    <style>
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fade-in 0.4s ease-out both;
        }
    </style>
</body>
