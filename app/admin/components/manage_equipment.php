<?php
include '../../database/config.php';

/**
 * ADMIN — QUẢN LÝ THIẾT BỊ (NỘI BỘ)
 * - Bảng: trang_thiet_bi (ID_TB, TEN_TB, ID_CN, TINH_TRANG, NGAY_BAO_TRI, IMAGE)
 * - Giá: don_gia_trang_thiet_bi (ID_TB, NGAY_GIO, DON_GIA) — lưu lịch sử
 * - Ảnh: lưu đường dẫn tương đối trong cột IMAGE
 * - Xóa: chặn khi còn gắn vào lịch hẹn đã xác nhận
 */

// ====== cấu hình upload ======
$UPLOAD_DIR_FS = rtrim($_SERVER['DOCUMENT_ROOT'], '/').'/public/uploads/equipment/';
$UPLOAD_DIR_URL = 'public/uploads/equipment/';

// ====== feature flags từ cấu trúc CSDL ======
$hasPriceHistory = false;
if ($checkPrice = $conn->query("SHOW TABLES LIKE 'don_gia_trang_thiet_bi'")) {
    $hasPriceHistory = $checkPrice->num_rows > 0;
    $checkPrice->free();
}

// ====== helper ======
function bind_params(mysqli_stmt $stmt, string $types, array $params): void {
    if ($types === '') {
        return;
    }
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function sql_latest_price_subquery(bool $hasPriceHistory): string {
    if (!$hasPriceHistory) {
        return 'NULL AS DON_GIA';
    }

    return "
        (SELECT d.DON_GIA FROM don_gia_trang_thiet_bi d
            WHERE d.ID_TB = tb.ID_TB
            ORDER BY d.NGAY_GIO DESC
            LIMIT 1
        ) AS DON_GIA
    ";
}

// ====== phân trang & filter ======
$limit  = 5;
$page   = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
$page   = max($page, 1);
$offset = ($page - 1) * $limit;

$search        = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_cn     = isset($_GET['filter_cn']) && $_GET['filter_cn'] !== '' ? (int)$_GET['filter_cn'] : null;
$filter_status = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '';

// ====== danh sách chi nhánh ======
$branchMap = [];
if ($resCN = $conn->query("SELECT ID_CN, TEN_CN FROM chi_nhanh ORDER BY TEN_CN ASC")) {
    while ($r = $resCN->fetch_assoc()) {
        $branchMap[(int)$r['ID_CN']] = $r['TEN_CN'] ?: ('CN #'.$r['ID_CN']);
    }
}

$statusOptions = ['Đang hoạt động', 'Bảo trì', 'Ngưng sử dụng'];
if ($resStatus = $conn->query("SELECT DISTINCT TINH_TRANG FROM trang_thiet_bi ORDER BY TINH_TRANG ASC")) {
    $dbStatuses = [];
    while ($row = $resStatus->fetch_assoc()) {
        $value = trim((string)$row['TINH_TRANG']);
        if ($value !== '') {
            $dbStatuses[$value] = true;
        }
    }
    if ($dbStatuses) {
        $statusOptions = array_keys($dbStatuses);
    }
    $resStatus->free();
}
$defaultStatus = 'Đang hoạt động';
if ($statusOptions) {
    if (!in_array($defaultStatus, $statusOptions, true)) {
        $defaultStatus = $statusOptions[0];
    }
}
if (!$statusOptions) {
    $statusOptions = [$defaultStatus];
}

// ====== thêm thiết bị ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $ten_tb       = trim($_POST['TEN_TB']);
    $id_cn        = (int)$_POST['ID_CN'];
    $tinh_trang   = $defaultStatus;
    $ngay_bao_tri = date('Y-m-d');
    $image_path   = null;
    $don_gia      = null;

    if ($hasPriceHistory) {
        $donGiaInput = $_POST['DON_GIA'] ?? '';
        $don_gia = $donGiaInput !== '' ? (int)$donGiaInput : null;
    }

    if (!empty($_FILES['IMAGE']['name']) && $_FILES['IMAGE']['error'] === UPLOAD_ERR_OK) {
        if (!is_dir($UPLOAD_DIR_FS)) {
            @mkdir($UPLOAD_DIR_FS, 0777, true);
        }
        $filename = time().'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['IMAGE']['name']));
        if (move_uploaded_file($_FILES['IMAGE']['tmp_name'], $UPLOAD_DIR_FS.$filename)) {
            $image_path = $UPLOAD_DIR_URL.$filename;
        } else {
            $_SESSION['error'] = 'Tải ảnh thất bại.';
        }
    }

    $stmt = $conn->prepare("INSERT INTO trang_thiet_bi (TEN_TB, ID_CN, TINH_TRANG, NGAY_BAO_TRI, IMAGE) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        $_SESSION['error'] = 'Lỗi thêm thiết bị: '.$conn->error;
    } else {
        $stmt->bind_param('sisss', $ten_tb, $id_cn, $tinh_trang, $ngay_bao_tri, $image_path);
        if (!$stmt->execute()) {
            $_SESSION['error'] = 'Lỗi thêm thiết bị: '.$stmt->error;
        } else {
            $id_tb = $stmt->insert_id;
            $stmt->close();

            if ($hasPriceHistory && $don_gia !== null) {
                $stmt2 = $conn->prepare("INSERT INTO don_gia_trang_thiet_bi (ID_TB, NGAY_GIO, DON_GIA) VALUES (?, NOW(), ?)");
                if ($stmt2) {
                    $stmt2->bind_param('ii', $id_tb, $don_gia);
                    if ($stmt2->execute()) {
                        $_SESSION['success'] = 'Đã thêm thiết bị thành công.';
                    } else {
                        $_SESSION['error'] = 'Lỗi lưu đơn giá: '.$stmt2->error;
                    }
                    $stmt2->close();
                } else {
                    $_SESSION['error'] = 'Lỗi lưu đơn giá: '.$conn->error;
                }
            } else {
                $_SESSION['success'] = 'Đã thêm thiết bị thành công.';
            }
        }
        if ($stmt->errno) {
            $stmt->close();
        }
    }
}

// ====== cập nhật thiết bị ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id_tb        = (int)$_POST['ID_TB'];
    $ten_tb       = trim($_POST['TEN_TB']);
    $id_cn        = (int)$_POST['ID_CN'];
    $tinh_trang   = trim($_POST['TINH_TRANG']);
    $ngay_bao_tri = $_POST['NGAY_BAO_TRI'] ?: null;
    $donGiaNewInput = $_POST['DON_GIA'] ?? '';
    $don_gia_new  = ($hasPriceHistory && $donGiaNewInput !== '') ? (int)$donGiaNewInput : null;

    $image_sql = '';
    $new_path  = null;
    if (!empty($_FILES['IMAGE']['name']) && $_FILES['IMAGE']['error'] === UPLOAD_ERR_OK) {
        if (!is_dir($UPLOAD_DIR_FS)) {
            @mkdir($UPLOAD_DIR_FS, 0777, true);
        }
        $filename = time().'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['IMAGE']['name']));
        if (move_uploaded_file($_FILES['IMAGE']['tmp_name'], $UPLOAD_DIR_FS.$filename)) {
            $new_path = $UPLOAD_DIR_URL.$filename;
            $image_sql = ', IMAGE = ?';
        } else {
            $_SESSION['error'] = 'Tải ảnh thất bại.';
        }
    }

    if ($image_sql) {
        $stmt = $conn->prepare("UPDATE trang_thiet_bi SET TEN_TB=?, ID_CN=?, TINH_TRANG=?, NGAY_BAO_TRI=?, IMAGE=? WHERE ID_TB=?");
        if ($stmt) {
            $stmt->bind_param('sisssi', $ten_tb, $id_cn, $tinh_trang, $ngay_bao_tri, $new_path, $id_tb);
        }
    } else {
        $stmt = $conn->prepare("UPDATE trang_thiet_bi SET TEN_TB=?, ID_CN=?, TINH_TRANG=?, NGAY_BAO_TRI=? WHERE ID_TB=?");
        if ($stmt) {
            $stmt->bind_param('sissi', $ten_tb, $id_cn, $tinh_trang, $ngay_bao_tri, $id_tb);
        }
    }

    if (!$stmt) {
        $_SESSION['error'] = 'Lỗi cập nhật thiết bị: '.$conn->error;
    } else {
        if (!$stmt->execute()) {
            $_SESSION['error'] = 'Lỗi cập nhật thiết bị: '.$stmt->error;
        } else {
            if ($hasPriceHistory && $don_gia_new !== null) {
                $stmt2 = $conn->prepare("INSERT INTO don_gia_trang_thiet_bi (ID_TB, NGAY_GIO, DON_GIA) VALUES (?, NOW(), ?)");
                if ($stmt2) {
                    $stmt2->bind_param('ii', $id_tb, $don_gia_new);
                    if ($stmt2->execute()) {
                        $_SESSION['success'] = 'Đã cập nhật thiết bị & giá.';
                    } else {
                        $_SESSION['error'] = 'Lỗi lưu đơn giá mới: '.$stmt2->error;
                    }
                    $stmt2->close();
                } else {
                    $_SESSION['error'] = 'Lỗi lưu đơn giá mới: '.$conn->error;
                }
            } else {
                $_SESSION['success'] = 'Đã cập nhật thiết bị.';
            }
        }
        $stmt->close();
    }
}

// ====== xóa thiết bị (an toàn) ======
if (isset($_GET['delete'])) {
    $id_tb = (int)$_GET['delete'];

    $sqlCheck = "
        SELECT 1
        FROM lich_hen_thiet_bi lhtb
        JOIN lich_hen lh ON lh.ID_LICHHEN = lhtb.ID_LICHHEN
        WHERE lhtb.ID_TB = ? AND lh.TRANGTHAI = 'Đã xác nhận'
        LIMIT 1
    ";
    $stmt = $conn->prepare($sqlCheck);
    if ($stmt) {
        $stmt->bind_param('i', $id_tb);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $_SESSION['error'] = 'Không thể xóa: thiết bị đang ở lịch hẹn đã xác nhận.';
        } else {
            if ($hasPriceHistory) {
                if ($delPrice = $conn->prepare('DELETE FROM don_gia_trang_thiet_bi WHERE ID_TB = ?')) {
                    $delPrice->bind_param('i', $id_tb);
                    $delPrice->execute();
                    $delPrice->close();
                }
            }
            if ($delEquip = $conn->prepare('DELETE FROM trang_thiet_bi WHERE ID_TB = ?')) {
                $delEquip->bind_param('i', $id_tb);
                $delEquip->execute();
                $delEquip->close();
            }
            $_SESSION['success'] = 'Đã xóa thiết bị.';
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = 'Không thể kiểm tra thiết bị: '.$conn->error;
    }
}

// ====== dữ liệu cần sửa ======
$editData = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $sql = "
        SELECT tb.*, ".sql_latest_price_subquery($hasPriceHistory)."
        FROM trang_thiet_bi tb
        WHERE tb.ID_TB = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $editData = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
}

// ====== build condition cho list ======
$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = '(tb.TEN_TB LIKE ? OR tb.TINH_TRANG LIKE ?)';
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}
if ($filter_cn !== null) {
    $where[] = 'tb.ID_CN = ?';
    $params[] = $filter_cn;
    $types   .= 'i';
}
if ($filter_status !== '') {
    $where[] = 'tb.TINH_TRANG = ?';
    $params[] = $filter_status;
    $types   .= 's';
}
$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// ====== tổng dòng ======
$sqlCount = "SELECT COUNT(*) AS total FROM trang_thiet_bi tb $whereSql";
$stmt = $conn->prepare($sqlCount);
if ($stmt) {
    bind_params($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $totalRows = $result ? (int)$result->fetch_assoc()['total'] : 0;
    $stmt->close();
} else {
    $totalRows = 0;
}
$totalPages = max(1, (int)ceil($totalRows / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

// ====== danh sách ======
$sqlList = "
    SELECT tb.ID_TB, tb.TEN_TB, tb.ID_CN, tb.TINH_TRANG, tb.NGAY_BAO_TRI, tb.IMAGE,
           ".sql_latest_price_subquery($hasPriceHistory)."
    FROM trang_thiet_bi tb
    $whereSql
    ORDER BY tb.ID_TB DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sqlList);
if ($stmt) {
    $paramsForList = $params;
    $typesForList  = $types.'ii';
    $paramsForList[] = $limit;
    $paramsForList[] = $offset;
    bind_params($stmt, $typesForList, $paramsForList);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = false;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Quản Lý Thiết Bị</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
<?php if (!empty($_SESSION['success'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded mb-4">✅ <?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4">❌ <?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<div class="max-w-6xl mx-auto bg-white p-6 rounded-lg shadow-xl space-y-6">
    <h1 class="text-3xl font-bold text-indigo-700 text-center">🛠️ Quản Lý Thiết Bị (Nội bộ)</h1>

    <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="page" value="equipment">
            <input type="text" name="search" placeholder="🔍 Tìm theo tên / tình trạng" value="<?= htmlspecialchars($search) ?>"
                   class="px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-72">

            <select name="filter_cn" class="border border-gray-300 rounded-lg px-3 py-2 shadow-sm">
                <option value="">Tất cả chi nhánh</option>
                <?php foreach ($branchMap as $id => $name): ?>
                    <option value="<?= $id ?>" <?= ($filter_cn === $id) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="filter_status" class="border border-gray-300 rounded-lg px-3 py-2 shadow-sm">
                <option value="">Tất cả tình trạng</option>
                <?php foreach ($statusOptions as $status): ?>
                    <option value="<?= htmlspecialchars($status) ?>" <?= $filter_status === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg shadow">🔍 Tìm</button>
        </form>

        <button onclick="toggleForm()" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg shadow-lg font-semibold transition">
            ➕ Thêm Thiết Bị
        </button>
    </div>

    <div id="addForm" class="hidden border border-gray-200 p-6 rounded-lg bg-gray-50">
        <form method="POST" enctype="multipart/form-data" class="grid md:grid-cols-2 gap-4">
            <input type="hidden" name="action" value="add">

            <div>
                <label class="block font-medium mb-1">Tên thiết bị</label>
                <input name="TEN_TB" required class="w-full border px-3 py-2 rounded focus:ring focus:ring-indigo-200">
            </div>

            <div>
                <label class="block font-medium mb-1">Chi nhánh</label>
                <select name="ID_CN" class="w-full border px-3 py-2 rounded focus:ring focus:ring-indigo-200">
                    <?php foreach ($branchMap as $id => $name): ?>
                        <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($hasPriceHistory): ?>
                <div>
                    <label class="block font-medium mb-1">Đơn giá hiện hành (VND)</label>
                    <input type="number" name="DON_GIA" required class="w-full border px-3 py-2 rounded focus:ring focus:ring-indigo-200">
                </div>
            <?php endif; ?>

            <div>
                <label class="block font-medium mb-1">Ảnh thiết bị</label>
                <input type="file" name="IMAGE" accept="image/*" class="w-full">
            </div>

            <div class="md:col-span-2 flex justify-between mt-4">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded shadow">✅ Thêm</button>
                <button type="button" onclick="toggleForm()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded shadow">❌ Đóng</button>
            </div>
        </form>
    </div>

    <?php if ($editData): ?>
        <div class="border p-6 rounded-lg bg-yellow-50">
            <h2 class="text-xl font-bold text-yellow-800 mb-4">✏️ Chỉnh sửa thiết bị #<?= $editData['ID_TB'] ?></h2>
            <form method="POST" enctype="multipart/form-data" class="grid md:grid-cols-2 gap-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="ID_TB" value="<?= $editData['ID_TB'] ?>">

                <div>
                    <label class="block font-medium mb-1">Tên thiết bị</label>
                    <input name="TEN_TB" value="<?= htmlspecialchars($editData['TEN_TB']) ?>" class="w-full border px-3 py-2 rounded">
                </div>

                <div>
                    <label class="block font-medium mb-1">Chi nhánh</label>
                    <select name="ID_CN" class="w-full border px-3 py-2 rounded">
                        <?php foreach ($branchMap as $id => $name): ?>
                            <option value="<?= $id ?>" <?= ((int)$editData['ID_CN'] === (int)$id) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-medium mb-1">Tình trạng</label>
                    <select name="TINH_TRANG" class="w-full border px-3 py-2 rounded">
                        <?php foreach ($statusOptions as $status): ?>
                            <option value="<?= htmlspecialchars($status) ?>" <?= $editData['TINH_TRANG'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-medium mb-1">Ngày bảo trì</label>
                    <input type="date" name="NGAY_BAO_TRI" value="<?= htmlspecialchars($editData['NGAY_BAO_TRI']) ?>" class="w-full border px-3 py-2 rounded">
                </div>

                <?php if ($hasPriceHistory): ?>
                    <div>
                        <label class="block font-medium mb-1">Đơn giá mới (tạo lịch sử)</label>
                        <input type="number" name="DON_GIA" placeholder="<?= number_format((int)($editData['DON_GIA'] ?? 0),0,',','.') ?> VND hiện hành" class="w-full border px-3 py-2 rounded">
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block font-medium mb-1">Ảnh mới (nếu thay)</label>
                    <input type="file" name="IMAGE" accept="image/*" class="w-full">
                </div>

                <div class="md:col-span-2 flex justify-end gap-2 mt-4">
                    <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded">💾 Lưu</button>
                    <a href="?page=equipment" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">❌ Hủy</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="w-full text-sm border border-gray-300">
            <thead class="bg-indigo-600 text-white">
                <tr>
                    <th class="px-4 py-2">ID</th>
                    <th class="px-4 py-2">Tên thiết bị</th>
                    <th class="px-4 py-2">Chi nhánh</th>
                    <th class="px-4 py-2">Tình trạng</th>
                    <th class="px-4 py-2">Ngày bảo trì</th>
                    <?php if ($hasPriceHistory): ?>
                        <th class="px-4 py-2">Đơn giá hiện hành</th>
                    <?php endif; ?>
                    <th class="px-4 py-2">Ảnh</th>
                    <th class="px-4 py-2">Hành động</th>
                </tr>
            </thead>
            <tbody class="bg-white">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-4 py-2 font-semibold">#<?= (int)$row['ID_TB'] ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($row['TEN_TB']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($branchMap[(int)$row['ID_CN']] ?? '—') ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($row['TINH_TRANG']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($row['NGAY_BAO_TRI']) ?></td>
                        <?php if ($hasPriceHistory): ?>
                            <td class="px-4 py-2"><?= ($row['DON_GIA']!==null) ? number_format((int)$row['DON_GIA'],0,',','.') . ' VND' : '—' ?></td>
                        <?php endif; ?>
                        <td class="px-4 py-2">
                            <?php if (!empty($row['IMAGE'])): ?>
                                <img src="../../<?= htmlspecialchars($row['IMAGE']) ?>" alt="Ảnh thiết bị" class="h-12 w-12 object-cover rounded-full mx-auto">
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="px-4 py-2 space-x-2 text-center">
                            <a href="?page=equipment&edit=<?= (int)$row['ID_TB'] ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded">✏</a>
                            <a href="?page=equipment&delete=<?= (int)$row['ID_TB'] ?>" onclick="return confirm('Xóa thiết bị này?')" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">🗑</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= $hasPriceHistory ? 8 : 7 ?>" class="px-4 py-6 text-center text-gray-500">Không có thiết bị nào phù hợp.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <div class="mt-4 flex justify-center space-x-2">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=equipment&p=<?= $i ?>&search=<?= urlencode($search) ?>&filter_cn=<?= urlencode((string)$filter_cn) ?>&filter_status=<?= urlencode($filter_status) ?>"
               class="px-3 py-1 border rounded <?= ($i == $page) ? 'bg-indigo-600 text-white' : 'bg-white hover:bg-gray-100' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<script>
function toggleForm(){
    document.getElementById('addForm').classList.toggle('hidden');
}
</script>
</body>
</html>