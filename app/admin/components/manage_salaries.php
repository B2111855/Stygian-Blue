<?php
include '../../database/config.php';

$now = new DateTime();
$currentMonth = (int)$now->format('m');
$currentYear = (int)$now->format('Y');

// Tính lương từng nhân viên
$employees = mysqli_query($conn, "
    SELECT tk.ID_TK, nv.ID_CN 
    FROM tai_khoan tk 
    JOIN nhan_vien nv ON tk.ID_TK = nv.ID_TK 
    WHERE tk.ID_QUYEN = 2
");

while ($emp = mysqli_fetch_assoc($employees)) {
    $id_tk = $emp['ID_TK'];
    $id_cn = $emp['ID_CN'];

    $thuongQuery = mysqli_query($conn, "
        SELECT SUM(dgdv.DON_GIA * 0.15) AS tong_thuong
        FROM lich_hen lh
        JOIN hoa_don hd ON lh.ID_LICHHEN = hd.ID_LICHHEN
        JOIN don_gia_dich_vu dgdv ON lh.ID_DV = dgdv.ID_DV
        JOIN phan_cong_nhan_vien pc ON pc.ID_LICHHEN = lh.ID_LICHHEN
        WHERE pc.ID_TK = '$id_tk'
          AND hd.TRANGTHAI_THANHTOAN = 'Đã thanh toán'
          AND MONTH(hd.NGAY_GIO) = $currentMonth
          AND YEAR(hd.NGAY_GIO) = $currentYear
    ");

    $row = mysqli_fetch_assoc($thuongQuery);
    $tongThuong = (int)($row['tong_thuong'] ?? 0);

    $luongCoBan = 5000000;
    $tongLuong = $luongCoBan + $tongThuong;

    $checkExist = mysqli_query($conn, "
        SELECT 1 FROM luong_nhan_vien 
        WHERE ID_TK = '$id_tk' AND THANG = $currentMonth AND NAM = $currentYear
    ");

    if (mysqli_num_rows($checkExist)) {
        mysqli_query($conn, "
            UPDATE luong_nhan_vien 
            SET TONG_TIEN_THUONG = $tongThuong, TONG_LUONG = $tongLuong, NGAY_TINH = CURDATE()
            WHERE ID_TK = '$id_tk' AND THANG = $currentMonth AND NAM = $currentYear
        ");
    } else {
        mysqli_query($conn, "
            INSERT INTO luong_nhan_vien (ID_TK, THANG, NAM, LUONG_CO_BAN, PHAN_TRAM_THUONG, TONG_TIEN_THUONG, TONG_LUONG, NGAY_TINH)
            VALUES ('$id_tk', $currentMonth, $currentYear, $luongCoBan, 0.15, $tongThuong, $tongLuong, CURDATE())
        ");
    }
}

// Thêm chi phí lương vào bảng tài chính nếu chưa có
mysqli_query($conn, "
    INSERT INTO tai_chinh (LOAI_GIAO_DICH, LOAI_CHI_TIET, SO_TIEN, NGAY_GIAO_DICH, ID_CN)
    SELECT 
        'chi phí',
        'lương nhân viên',
        lnv.TONG_LUONG,
        CURDATE(),
        nv.ID_CN
    FROM luong_nhan_vien lnv
    JOIN nhan_vien nv ON lnv.ID_TK = nv.ID_TK
    WHERE lnv.THANG = $currentMonth AND lnv.NAM = $currentYear
    AND NOT EXISTS (
        SELECT 1 FROM tai_chinh tc 
        WHERE tc.ID_CN = nv.ID_CN 
        AND tc.LOAI_GIAO_DICH = 'chi phí' 
        AND tc.LOAI_CHI_TIET = 'lương nhân viên' 
        AND MONTH(tc.NGAY_GIAO_DICH) = $currentMonth 
        AND YEAR(tc.NGAY_GIAO_DICH) = $currentYear
    )
");

// Bộ lọc tìm kiếm
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$start = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$month = isset($_GET['month']) ? (int)$_GET['month'] : null;
$year = isset($_GET['year']) ? (int)$_GET['year'] : null;

$conditions = [];
if (!empty($search)) {
    $searchEscaped = mysqli_real_escape_string($conn, $search);
    $conditions[] = "tk.HO_TEN LIKE '%$searchEscaped%'";
}
if ($month) $conditions[] = "lnv.THANG = $month";
if ($year) $conditions[] = "lnv.NAM = $year";
$where = $conditions ? implode(' AND ', $conditions) : '1';

$totalQuery = mysqli_query($conn, "
    SELECT COUNT(*) as total
    FROM luong_nhan_vien lnv 
    JOIN tai_khoan tk ON lnv.ID_TK = tk.ID_TK 
    WHERE $where
");
$totalRow = mysqli_fetch_assoc($totalQuery);
$total = $totalRow['total'];
$pages = ceil($total / $limit);

$result = mysqli_query($conn, "
    SELECT lnv.*, tk.HO_TEN 
    FROM luong_nhan_vien lnv 
    JOIN tai_khoan tk ON lnv.ID_TK = tk.ID_TK 
    WHERE $where
    ORDER BY lnv.NAM DESC, lnv.THANG DESC
    LIMIT $start, $limit
");
?>

<body class="bg-gradient-to-br from-blue-50 to-indigo-100 p-6 min-h-screen">
<div class="max-w-6xl mx-auto bg-white p-8 rounded-xl shadow-xl">
    <h1 class="text-3xl font-extrabold mb-6 text-center text-indigo-700">📋 Bảng Lương Nhân Viên</h1>

    <form method="GET" class="mb-6 flex flex-wrap justify-center gap-4">
        <input type="hidden" name="page" value="salaries">
        <input type="text" name="search" placeholder="🔍 Tên nhân viên" value="<?= htmlspecialchars($search) ?>"
               class="border border-gray-300 rounded-lg px-4 py-2 w-64 shadow-sm">

        <select name="month" class="border border-gray-300 rounded-lg px-4 py-2 shadow-sm">
            <option value="">Tháng</option>
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= ($month === $m) ? 'selected' : '' ?>><?= $m ?></option>
            <?php endfor; ?>
        </select>

        <select name="year" class="border border-gray-300 rounded-lg px-4 py-2 shadow-sm">
            <option value="">Năm</option>
            <?php for ($y = 2022; $y <= date('Y'); $y++): ?>
                <option value="<?= $y ?>" <?= ($year === $y) ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>

        <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded shadow font-semibold">
            Lọc
        </button>

        <a href="components/export_salary_excel.php?search=<?= urlencode($search) ?>&month=<?= $month ?>&year=<?= $year ?>"
   class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded shadow font-semibold">
   📤 Xuất Excel
</a>

    </form>

    <div class="overflow-x-auto rounded-lg shadow">
        <table class="min-w-full border text-sm text-center">
            <thead class="bg-indigo-600 text-white">
            <tr>
                <th class="px-4 py-2 border">Họ tên</th>
                <th class="px-4 py-2 border">Tháng</th>
                <th class="px-4 py-2 border">Năm</th>
                <th class="px-4 py-2 border">Lương cơ bản</th>
                <th class="px-4 py-2 border">Thưởng</th>
                <th class="px-4 py-2 border">Tổng lương</th>
                <th class="px-4 py-2 border">Ngày tính</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr class="hover:bg-indigo-50">
                    <td class="px-4 py-2 border"><?= htmlspecialchars($row['HO_TEN']) ?></td>
                    <td class="px-4 py-2 border"><?= $row['THANG'] ?></td>
                    <td class="px-4 py-2 border"><?= $row['NAM'] ?></td>
                    <td class="px-4 py-2 border"><?= number_format($row['LUONG_CO_BAN'], 0, ',', '.') ?> VND</td>
                    <td class="px-4 py-2 border"><?= number_format($row['TONG_TIEN_THUONG'], 0, ',', '.') ?> VND</td>
                    <td class="px-4 py-2 border text-green-700 font-bold"><?= number_format($row['TONG_LUONG'], 0, ',', '.') ?> VND</td>
                    <td class="px-4 py-2 border"><?= $row['NGAY_TINH'] ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-6 flex justify-center gap-2">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="?page=salaries&page=<?= $i ?>&search=<?= urlencode($search) ?>&month=<?= $month ?>&year=<?= $year ?>"
               class="px-3 py-1 border rounded-lg <?= ($i == $page) ? 'bg-indigo-600 text-white' : 'bg-white hover:bg-gray-100 text-indigo-600' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
</div>
</body>
</html>
