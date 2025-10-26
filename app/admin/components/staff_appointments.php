<?php
include '../../database/config.php';

$employee_id = $_SESSION['ID_TK'] ?? null;
if (!$employee_id) {
    echo "Bạn chưa đăng nhập.";
    exit;
}

// 🔍 Lấy ID_CN từ bảng nhan_vien
$stmtCN = $conn->prepare("SELECT ID_CN FROM nhan_vien WHERE ID_TK = ?");
if (!$stmtCN) {
    die("Lỗi truy vấn lấy chi nhánh nhân viên: " . $conn->error);
}
$stmtCN->bind_param("s", $employee_id);
$stmtCN->execute();
$resCN = $stmtCN->get_result();
$rowCN = $resCN->fetch_assoc();
$id_cn_nv = $rowCN['ID_CN'] ?? null;


$limit = 6;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$start = ($page - 1) * $limit;

if (!isset($_GET['ten_khach']) && !isset($_GET['ten_dv']) && !isset($_GET['ngay']) && !isset($_GET['trangthai'])) {
    $_GET['ten_khach'] = '';
    $_GET['ten_dv'] = '';
    $_GET['ngay'] = '';
    $_GET['trangthai'] = '';
}

function buildFilterConditions(&$params)
{
    global $conn, $id_cn_nv;
    $conditions = ["1"];

    if (!empty($_GET['ten_khach'])) {
        $params['ten_khach'] = mysqli_real_escape_string($conn, $_GET['ten_khach']);
        $conditions[] = "tk.HO_TEN LIKE '%{$params['ten_khach']}%'";
    }

    if (!empty($_GET['ten_dv'])) {
        $params['ten_dv'] = mysqli_real_escape_string($conn, $_GET['ten_dv']);
        $conditions[] = "dv.TEN_DV LIKE '%{$params['ten_dv']}%'";
    }

    if (!empty($_GET['ngay'])) {
        $params['ngay'] = mysqli_real_escape_string($conn, $_GET['ngay']);
        $conditions[] = "DATE(lh.THOI_GIAN_BAT_DAU) = '{$params['ngay']}'";
    }

    if (isset($_GET['trangthai']) && $_GET['trangthai'] !== '') {
        $params['trangthai'] = mysqli_real_escape_string($conn, $_GET['trangthai']);
        $conditions[] = "lh.TRANGTHAI = '{$params['trangthai']}'";
    }

    // 🎯 Giới hạn theo chi nhánh nhân viên
    if (!empty($id_cn_nv)) {
        $conditions[] = "lh.ID_CHINHANH = '$id_cn_nv'";
    }

    return "WHERE " . implode(" AND ", $conditions);
}

function getAppointments($start, $limit)
{
    global $conn;
    $params = [];
    $where = buildFilterConditions($params);

    $query = "
        SELECT 
            lh.ID_LICHHEN, lh.THOI_GIAN_BAT_DAU, lh.DIA_CHI_HEN, lh.TRANGTHAI,
            tk.HO_TEN, dv.TEN_DV
        FROM lich_hen lh
        INNER JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK
        INNER JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
        $where
        ORDER BY lh.THOI_GIAN_BAT_DAU DESC
        LIMIT $start, $limit
    ";

    return mysqli_query($conn, $query);
}

function countFilteredAppointments()
{
    global $conn;
    $params = [];
    $where = buildFilterConditions($params);

    $query = "SELECT COUNT(*) AS total 
              FROM lich_hen lh 
              JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK 
              JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV 
              $where";

    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'];
}

$totalRecords = countFilteredAppointments();
$totalPages = ceil($totalRecords / $limit);
$appointments = getAppointments($start, $limit);
?>

<div class="bg-gradient-to-br from-blue-50 to-indigo-100 p-6 rounded-xl shadow-xl fade-in">
    <h2 class="text-3xl font-bold text-indigo-700 mb-6 text-center">📍 Lịch hẹn của chi nhánh bạn</h2>

    <!-- Form lọc -->
    <form method="GET" class="flex flex-wrap gap-4 justify-center mb-8 items-end">
        <input type="hidden" name="page" value="appointments">

        <div class="flex flex-col">
            <label class="text-sm font-medium text-gray-600 mb-1">Tên khách hàng</label>
            <input type="text" name="ten_khach" placeholder="VD: Nguyễn Văn A"
                value="<?= htmlspecialchars($_GET['ten_khach']) ?>"
                class="border border-gray-300 rounded-lg px-4 py-2 w-52 focus:ring-indigo-500 focus:border-indigo-500 transition" />
        </div>

        <div class="flex flex-col">
            <label class="text-sm font-medium text-gray-600 mb-1">Dịch vụ</label>
            <input type="text" name="ten_dv" placeholder="VD: Chụp ảnh cưới"
                value="<?= htmlspecialchars($_GET['ten_dv']) ?>"
                class="border border-gray-300 rounded-lg px-4 py-2 w-52 focus:ring-indigo-500 focus:border-indigo-500 transition" />
        </div>

        <div class="flex flex-col">
            <label class="text-sm font-medium text-gray-600 mb-1">Ngày</label>
            <input type="date" name="ngay" value="<?= $_GET['ngay'] ?? '' ?>"
                class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 transition" />
        </div>

        <div class="flex flex-col">
            <label class="text-sm font-medium text-gray-600 mb-1">Trạng thái</label>
            <select name="trangthai"
                class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                <option value="">Tất cả</option>
                <option value="Đang chờ" <?= $_GET['trangthai'] === 'Đang chờ' ? 'selected' : '' ?>>Đang chờ</option>
                <option value="Đã xác nhận" <?= $_GET['trangthai'] === 'Đã xác nhận' ? 'selected' : '' ?>>Đã xác nhận</option>
                <option value="Đã hoàn thành" <?= $_GET['trangthai'] === 'Đã hoàn thành' ? 'selected' : '' ?>>Đã hoàn thành</option>
                <option value="Đã hủy" <?= $_GET['trangthai'] === 'Đã hủy' ? 'selected' : '' ?>>Đã hủy</option>
            </select>
        </div>

        <button type="submit"
            class="bg-indigo-600 text-white font-semibold px-5 py-2 rounded-lg hover:bg-indigo-700 transition">
            🔍 Tìm kiếm
        </button>
    </form>

    <!-- Bảng danh sách -->
    <div class="overflow-x-auto rounded-lg shadow">
        <table class="min-w-full bg-white rounded-xl overflow-hidden">
            <thead class="bg-indigo-600 text-white text-sm font-semibold">
                <tr>
                    <th class="px-6 py-3 text-center">#</th>
                    <th class="px-6 py-3 text-center">Dịch Vụ</th>
                    <th class="px-6 py-3 text-center">Khách Hàng</th>
                    <th class="px-6 py-3 text-center">Thời Gian</th>
                    <th class="px-6 py-3 text-center">Trạng Thái</th>
                    <th class="px-6 py-3 text-center">Chi Tiết</th>
                </tr>
            </thead>
            <tbody class="text-sm text-gray-800 text-center divide-y divide-gray-200">
                <?php while ($row = mysqli_fetch_assoc($appointments)): ?>
                    <tr class="hover:bg-indigo-50 transition">
                        <td class="px-6 py-4 font-bold text-indigo-600"><?= $row['ID_LICHHEN'] ?></td>
                        <td class="px-6 py-4"><?= $row['TEN_DV'] ?></td>
                        <td class="px-6 py-4"><?= $row['HO_TEN'] ?></td>
                        <td class="px-6 py-4"><?= $row['THOI_GIAN_BAT_DAU'] ?></td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold
                                <?= $row['TRANGTHAI'] === 'Đang chờ' ? 'bg-yellow-100 text-yellow-800' : '' ?>
                                <?= $row['TRANGTHAI'] === 'Đã xác nhận' ? 'bg-blue-100 text-blue-800' : '' ?>
                                <?= $row['TRANGTHAI'] === 'Đã hoàn thành' ? 'bg-green-100 text-green-800' : '' ?>
                                <?= $row['TRANGTHAI'] === 'Đã hủy' ? 'bg-red-100 text-red-800' : '' ?>">
                                <?= $row['TRANGTHAI'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <a href="?page=appointment_detail&ID_LICHHEN=<?= $row['ID_LICHHEN'] ?>"
                               class="text-indigo-600 hover:underline font-medium text-sm">Xem</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Phân trang -->
    <div class="mt-8 flex justify-center space-x-2">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php $queryString = http_build_query(array_merge($_GET, ['page_num' => $i])); ?>
            <a href="?<?= $queryString ?>"
               class="px-4 py-2 border rounded-lg text-sm font-semibold
               <?= $i == $page ? 'bg-indigo-600 text-white' : 'bg-white text-indigo-600 hover:bg-indigo-100' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
</div>

<!-- Animation -->
<style>
    @keyframes fade-in {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .fade-in {
        animation: fade-in 0.4s ease-out both;
    }
</style>
