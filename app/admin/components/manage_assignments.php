<?php
// Kết nối cơ sở dữ liệu
include '../../database/config.php';

$limit = 20;
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $limit;

// Truy vấn đếm tổng số bản ghi
$countQuery = "
    SELECT COUNT(*) as total
    FROM phan_cong_nhan_vien pc
    JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
    JOIN tai_khoan tk ON tk.ID_TK = pc.ID_TK
    WHERE tk.ID_QUYEN = 2
";
$countResult = mysqli_query($conn, $countQuery);
$totalRows = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRows / $limit);

// Truy vấn danh sách phân công
$query = "
    SELECT pc.ID_LICHHEN, tk.HO_TEN, lh.DIA_CHI_HEN, pc.THOI_GIAN_BAT_DAU, pc.THOI_GIAN_KET_THUC, pc.ID_TK, h.ID_HD
    FROM phan_cong_nhan_vien pc
    JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
    JOIN hoa_don h ON lh.ID_LICHHEN = h.ID_LICHHEN
    JOIN tai_khoan tk ON tk.ID_TK = pc.ID_TK
    WHERE tk.ID_QUYEN = 2
    ORDER BY pc.THOI_GIAN_BAT_DAU DESC
    LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Lỗi truy vấn SQL: " . mysqli_error($conn));
}

// Lấy các yêu cầu thay đổi lịch chưa duyệt
$requestQuery = "
    SELECT yc.*, tk.HO_TEN 
    FROM yeu_cau_thay_doi_lich yc
    JOIN tai_khoan tk ON yc.ID_TK = tk.ID_TK
    WHERE yc.TRANGTHAI = 'Chờ duyệt'
    ORDER BY yc.NGAY_GUI DESC
";
$requests = mysqli_query($conn, $requestQuery);

// Đếm số lịch hẹn "Đã xác nhận" nhưng chưa được phân công
$unassignedQuery = "
    SELECT COUNT(*) as unassigned
    FROM lich_hen lh
    WHERE lh.TRANGTHAI = 'Đã xác nhận'
    AND NOT EXISTS (
        SELECT 1 FROM phan_cong_nhan_vien pc WHERE pc.ID_LICHHEN = lh.ID_LICHHEN
    )
";
$unassignedResult = mysqli_query($conn, $unassignedQuery);
$unassignedCount = mysqli_fetch_assoc($unassignedResult)['unassigned'];


function renderPagination($totalPages, $currentPage)
{
    echo '<div class="flex justify-center mt-6 space-x-2">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i == $currentPage ? 'bg-blue-500 text-white' : 'bg-white text-blue-500';
        echo "<a href='?p=$i' class='px-3 py-1 border rounded $active'>$i</a>";
    }
    echo '</div>';
}
?>

<body class="bg-gray-100 p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold text-indigo-700 mb-6 text-center">📋 Quản lý phân công nhân viên</h1>

        <!-- Nút thêm và yêu cầu đổi -->
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
            <a href="./components/add_assignment.php"
               class="relative bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow font-medium transition">
               ➕ Thêm phân công
                <?php if ($unassignedCount > 0): ?>
                    <span class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full text-xs px-2"><?= $unassignedCount ?></span>
                <?php endif; ?>
            </a>

            <div class="relative">
                <button onclick="toggleRequestBox()"
                        class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded shadow font-medium transition relative">
                    📬 Yêu cầu đổi lịch
                    <?php if (mysqli_num_rows($requests) > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full text-xs px-2"><?= mysqli_num_rows($requests) ?></span>
                    <?php endif; ?>
                </button>

                <!-- Hộp yêu cầu đổi lịch -->
                <div id="requestBox"
                     class="hidden absolute right-0 mt-2 w-[22rem] bg-white shadow-lg rounded border border-gray-200 z-10">
                    <div class="p-4 max-h-96 overflow-y-auto">
                        <h3 class="text-lg font-semibold text-indigo-700 mb-3">📑 Danh sách yêu cầu</h3>
                        <?php if (mysqli_num_rows($requests) === 0): ?>
                            <p class="text-gray-500 text-sm italic">Hiện chưa có yêu cầu đổi lịch nào.</p>
                        <?php else: ?>
                            <ul class="divide-y">
                                <?php while ($rq = mysqli_fetch_assoc($requests)): ?>
                                    <li class="py-3 text-sm">
                                        <p class="font-medium text-gray-800">
                                            🧑 <strong><?= htmlspecialchars($rq['HO_TEN']) ?></strong> yêu cầu đổi lịch
                                            <span class="text-blue-600 font-semibold">#<?= $rq['ID_LICHHEN'] ?></span>
                                        </p>
                                        <p class="text-xs text-gray-500 italic">🕒 Gửi lúc: <?= date('d/m/Y H:i', strtotime($rq['NGAY_GUI'])) ?></p>
                                        <p class="text-gray-600 mt-1">📄 Lý do: <span class="italic"><?= nl2br(htmlspecialchars($rq['NOI_DUNG'])) ?></span></p>
                                        <div class="mt-2 flex gap-2">
                                            <form method="POST" action="./components/process_request.php" class="inline-block">
                                                <input type="hidden" name="id_yeucau" value="<?= $rq['ID_YEUCAU'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs font-semibold">
                                                    ✔ Duyệt
                                                </button>
                                            </form>

                                            <form method="POST" action="./components/process_request.php" class="inline-block">
                                                <input type="hidden" name="id_yeucau" value="<?= $rq['ID_YEUCAU'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-xs font-semibold">
                                                    ✖ Từ chối
                                                </button>
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

        <!-- Bảng phân công -->
        <div class="overflow-x-auto bg-white rounded-lg shadow">
            <table class="min-w-full text-sm text-left">
                <thead class="bg-indigo-100 text-indigo-700">
                    <tr>
                        <th class="py-3 px-4">🧾 ID Hóa Đơn</th>
                        <th class="py-3 px-4">📅 ID Lịch Hẹn</th>
                        <th class="py-3 px-4">👤 Họ Tên</th>
                        <th class="py-3 px-4">📍 Địa Chỉ</th>
                        <th class="py-3 px-4">⏰ Bắt Đầu</th>
                        <th class="py-3 px-4">⏳ Kết Thúc</th>
                        <th class="py-3 px-4 text-center">⚙ Hành Động</th>
                    </tr>
                </thead>
                <tbody class="divide-y text-gray-700">
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="py-2 px-4"><?= htmlspecialchars($row['ID_HD']) ?></td>
                            <td class="py-2 px-4"><?= htmlspecialchars($row['ID_LICHHEN']) ?></td>
                            <td class="py-2 px-4"><?= htmlspecialchars($row['HO_TEN']) ?></td>
                            <td class="py-2 px-4"><?= htmlspecialchars($row['DIA_CHI_HEN']) ?></td>
                            <td class="py-2 px-4"><?= htmlspecialchars($row['THOI_GIAN_BAT_DAU']) ?></td>
                            <td class="py-2 px-4"><?= htmlspecialchars($row['THOI_GIAN_KET_THUC']) ?></td>
                            <td class="py-2 px-4 text-center">
                                <a href="./components/edit_assignment.php?id=<?= $row['ID_LICHHEN'] ?>&employee_id=<?= $row['ID_TK'] ?>"
                                   class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded mr-2 text-sm font-medium transition">
                                    ✏ Sửa
                                </a>
                                <a href="./components/delete_assignment.php?id=<?= $row['ID_LICHHEN'] ?>&employee_id=<?= $row['ID_TK'] ?>"
                                   class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm font-medium transition"
                                   onclick="return confirm('❗Bạn chắc chắn muốn xóa phân công này?');">
                                    🗑 Xóa
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Phân trang -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-6 flex justify-center gap-2">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=assignments&p=<?= $i ?>"
                       class="px-3 py-1 border rounded font-medium <?= ($i == $page) ? 'bg-indigo-600 text-white' : 'bg-white hover:bg-gray-100 text-indigo-700' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleRequestBox() {
            const box = document.getElementById('requestBox');
            box.classList.toggle('hidden');
        }
    </script>
</body>
