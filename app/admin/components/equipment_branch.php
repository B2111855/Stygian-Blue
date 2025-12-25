<?php
include '../../database/config.php';
require_once __DIR__ . '/../../helpers/equipment_media.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentAccount = $_SESSION['ID_TK'] ?? null;
if (!$currentAccount) {
    echo '<div class="rounded-xl bg-white p-8 text-center text-red-600 shadow">Vui lòng đăng nhập để xem thiết bị chi nhánh.</div>';
    return;
}

$branchStmt = $conn->prepare('SELECT nv.ID_CN, cn.TEN_CN FROM nhan_vien nv JOIN chi_nhanh cn ON nv.ID_CN = cn.ID_CN WHERE nv.ID_TK = ? LIMIT 1');
$branchStmt->bind_param('s', $currentAccount);
$branchStmt->execute();
$branchStmt->bind_result($branchId, $branchName);
$branchStmt->fetch();
$branchStmt->close();

if (!$branchId) {
    echo '<div class="rounded-xl bg-white p-8 text-center text-red-600 shadow">Tài khoản của bạn chưa được gán vào chi nhánh nào. Liên hệ quản trị viên để được hỗ trợ.</div>';
    return;
}

$UPLOAD_DIR_FS = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/public/uploads/equipment/';
$UPLOAD_DIR_URL = 'public/uploads/equipment/';

function equipmentFlash(string $type, string $message): void
{
    $_SESSION['branch_equipment_flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

function redirectBack(): void
{
    $target = strtok($_SERVER['REQUEST_URI'] ?? '?page=equipment', '#') ?: '?page=equipment';
  if (!headers_sent()) {
    header('Location: ' . $target);
  } else {
    $safe = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');
    echo '<script>window.location.href = ' . json_encode($target) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . $safe . '"></noscript>';
  }
    exit;
}

function bindDynamic(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '' || !$params) {
        return;
    }
    $bind = [$types];
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

function singleValue(mysqli $conn, string $sql, string $types = '', array $params = []): int
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    if ($types !== '' && $params) {
        bindDynamic($stmt, $types, $params);
    }
    $stmt->execute();
    $stmt->bind_result($value);
    $stmt->fetch();
    $stmt->close();
    return (int) ($value ?? 0);
}

function handleImageUpload(string $field, string $baseFs, string $baseUrl): array
{
    if (empty($_FILES[$field]['name'])) {
        return ['path' => null, 'error' => null];
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'Không thể tải ảnh thiết bị.'];
    }
    if (!is_dir($baseFs)) {
        @mkdir($baseFs, 0777, true);
    }
    $filename = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES[$field]['name']));
    $fullPath = $baseFs . $filename;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $fullPath)) {
        return ['path' => null, 'error' => 'Không thể lưu ảnh thiết bị.'];
    }
    return ['path' => $baseUrl . $filename, 'error' => null];
}

function ensureOwnDevice(mysqli $conn, int $deviceId, int $branchId): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM trang_thiet_bi WHERE ID_TB = ? AND ID_CN = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $deviceId, $branchId);
    $stmt->execute();
    $stmt->store_result();
    $ok = $stmt->num_rows > 0;
    $stmt->close();
    return $ok;
}

$statusOptions = ['Đang hoạt động', 'Bảo trì', 'Ngưng sử dụng'];
if ($statusStmt = $conn->query("SELECT DISTINCT TINH_TRANG FROM trang_thiet_bi ORDER BY TINH_TRANG ASC")) {
    $dbStatuses = [];
    while ($row = $statusStmt->fetch_assoc()) {
        $value = trim((string) $row['TINH_TRANG']);
        if ($value !== '') {
            $dbStatuses[$value] = true;
        }
    }
    if ($dbStatuses) {
        $statusOptions = array_keys($dbStatuses);
    }
    $statusStmt->free();
}
$defaultStatus = in_array('Đang hoạt động', $statusOptions, true) ? 'Đang hoạt động' : ($statusOptions[0] ?? 'Đang hoạt động');

function normalizeStatus(string $value, array $options, string $fallback): string
{
    $value = trim($value);
    return in_array($value, $options, true) ? $value : $fallback;
}

function normalizeDate(?string $value): ?string
{
    if (!$value) {
        return null;
    }
    $value = trim($value);
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt ? $dt->format('Y-m-d') : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['TEN_TB'] ?? '');
        $status = normalizeStatus($_POST['TINH_TRANG'] ?? '', $statusOptions, $defaultStatus);
        $maintenanceDate = normalizeDate($_POST['NGAY_BAO_TRI'] ?? null);
        $upload = handleImageUpload('IMAGE', $UPLOAD_DIR_FS, $UPLOAD_DIR_URL);

        if ($name === '') {
            equipmentFlash('error', 'Vui lòng nhập tên thiết bị.');
            redirectBack();
        }
        if ($upload['error']) {
            equipmentFlash('error', $upload['error']);
            redirectBack();
        }

        $stmt = $conn->prepare('INSERT INTO trang_thiet_bi (TEN_TB, ID_CN, TINH_TRANG, NGAY_BAO_TRI, IMAGE) VALUES (?, ?, ?, ?, ?)');
        if (!$stmt) {
            equipmentFlash('error', 'Không thể thêm thiết bị mới.');
            redirectBack();
        }
        $stmt->bind_param('sisss', $name, $branchId, $status, $maintenanceDate, $upload['path']);
        $ok = $stmt->execute();
        $stmt->close();
        equipmentFlash($ok ? 'success' : 'error', $ok ? 'Đã thêm thiết bị mới.' : 'Thêm thiết bị thất bại.');
        redirectBack();
    }

    if ($action === 'update') {
        $deviceId = isset($_POST['ID_TB']) ? (int) $_POST['ID_TB'] : 0;
        if ($deviceId <= 0 || !ensureOwnDevice($conn, $deviceId, $branchId)) {
            equipmentFlash('error', 'Thiết bị không thuộc chi nhánh của bạn.');
            redirectBack();
        }
        $name = trim($_POST['TEN_TB'] ?? '');
        $status = normalizeStatus($_POST['TINH_TRANG'] ?? '', $statusOptions, $defaultStatus);
        $maintenanceDate = normalizeDate($_POST['NGAY_BAO_TRI'] ?? null);
        $upload = handleImageUpload('IMAGE', $UPLOAD_DIR_FS, $UPLOAD_DIR_URL);
        if ($name === '') {
            equipmentFlash('error', 'Tên thiết bị không được bỏ trống.');
            redirectBack();
        }
        if ($upload['error']) {
            equipmentFlash('error', $upload['error']);
            redirectBack();
        }

        if ($upload['path']) {
          $stmt = $conn->prepare('UPDATE trang_thiet_bi SET TEN_TB = ?, TINH_TRANG = ?, NGAY_BAO_TRI = ?, IMAGE = ? WHERE ID_TB = ? AND ID_CN = ?');
          if ($stmt) {
            $stmt->bind_param('ssssii', $name, $status, $maintenanceDate, $upload['path'], $deviceId, $branchId);
          }
        } else {
            $stmt = $conn->prepare('UPDATE trang_thiet_bi SET TEN_TB = ?, TINH_TRANG = ?, NGAY_BAO_TRI = ? WHERE ID_TB = ? AND ID_CN = ?');
            if ($stmt) {
                $stmt->bind_param('sssii', $name, $status, $maintenanceDate, $deviceId, $branchId);
            }
        }

        if (!$stmt) {
            equipmentFlash('error', 'Không thể cập nhật thiết bị.');
            redirectBack();
        }
        $ok = $stmt->execute();
        $stmt->close();
        equipmentFlash($ok ? 'success' : 'error', $ok ? 'Đã cập nhật thiết bị.' : 'Cập nhật thất bại.');
        redirectBack();
    }

    if ($action === 'delete') {
        $deviceId = isset($_POST['ID_TB']) ? (int) $_POST['ID_TB'] : 0;
        if ($deviceId <= 0 || !ensureOwnDevice($conn, $deviceId, $branchId)) {
            equipmentFlash('error', 'Không thể xóa thiết bị này.');
            redirectBack();
        }
        $checkSql = "
            SELECT 1
            FROM lich_hen_thiet_bi lhtb
            JOIN lich_hen lh ON lh.ID_LICHHEN = lhtb.ID_LICHHEN
            WHERE lhtb.ID_TB = ? AND lh.TRANGTHAI = 'Đã xác nhận'
            LIMIT 1
        ";
        $check = $conn->prepare($checkSql);
        if ($check) {
            $check->bind_param('i', $deviceId);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $check->close();
                equipmentFlash('error', 'Thiết bị đang gắn với lịch hẹn đã xác nhận, không thể xóa.');
                redirectBack();
            }
            $check->close();
        }
        $del = $conn->prepare('DELETE FROM trang_thiet_bi WHERE ID_TB = ? AND ID_CN = ?');
        if (!$del) {
            equipmentFlash('error', 'Không thể xóa thiết bị.');
            redirectBack();
        }
        $del->bind_param('ii', $deviceId, $branchId);
        $ok = $del->execute();
        $del->close();
        equipmentFlash($ok ? 'success' : 'error', $ok ? 'Đã xóa thiết bị.' : 'Xóa thiết bị thất bại.');
        redirectBack();
    }

    if ($action === 'quick_status') {
        $deviceId = isset($_POST['ID_TB']) ? (int) $_POST['ID_TB'] : 0;
        $preset = $_POST['preset'] ?? '';
        if ($deviceId <= 0 || !ensureOwnDevice($conn, $deviceId, $branchId)) {
            equipmentFlash('error', 'Không thể cập nhật nhanh thiết bị này.');
            redirectBack();
        }
        if ($preset === 'active') {
            $stmt = $conn->prepare("UPDATE trang_thiet_bi SET TINH_TRANG = 'Đang hoạt động', NGAY_BAO_TRI = CURDATE() WHERE ID_TB = ? AND ID_CN = ?");
        } elseif ($preset === 'maintenance') {
            $stmt = $conn->prepare("UPDATE trang_thiet_bi SET TINH_TRANG = 'Bảo trì' WHERE ID_TB = ? AND ID_CN = ?");
        } else {
            equipmentFlash('error', 'Trạng thái nhanh không hợp lệ.');
            redirectBack();
        }
        if (!$stmt) {
            equipmentFlash('error', 'Không thể cập nhật trạng thái.');
            redirectBack();
        }
        $stmt->bind_param('ii', $deviceId, $branchId);
        $ok = $stmt->execute();
        $stmt->close();
        equipmentFlash($ok ? 'success' : 'error', $ok ? 'Đã cập nhật trạng thái thiết bị.' : 'Cập nhật trạng thái thất bại.');
        redirectBack();
    }
}

$stats = [
    'total'        => singleValue($conn, 'SELECT COUNT(*) FROM trang_thiet_bi WHERE ID_CN = ?', 'i', [$branchId]),
    'active'       => singleValue($conn, "SELECT COUNT(*) FROM trang_thiet_bi WHERE ID_CN = ? AND TINH_TRANG = 'Đang hoạt động'", 'i', [$branchId]),
    'maintenance'  => singleValue($conn, "SELECT COUNT(*) FROM trang_thiet_bi WHERE ID_CN = ? AND TINH_TRANG = 'Bảo trì'", 'i', [$branchId]),
    'overdue'      => singleValue($conn, "SELECT COUNT(*) FROM trang_thiet_bi WHERE ID_CN = ? AND (NGAY_BAO_TRI IS NULL OR NGAY_BAO_TRI <= DATE_SUB(CURDATE(), INTERVAL 30 DAY))", 'i', [$branchId]),
];

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$maintenanceFilter = trim($_GET['maintenance'] ?? '');
$page = isset($_GET['p']) && is_numeric($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
$limit = 8;
$offset = ($page - 1) * $limit;

$whereParts = ['tb.ID_CN = ?'];
$params = [$branchId];
$types = 'i';

if ($search !== '') {
    $like = "%{$search}%";
    $whereParts[] = '(tb.TEN_TB LIKE ? OR tb.TINH_TRANG LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
if ($statusFilter !== '' && in_array($statusFilter, $statusOptions, true)) {
    $whereParts[] = 'tb.TINH_TRANG = ?';
    $params[] = $statusFilter;
    $types .= 's';
} else {
    $statusFilter = '';
}
if ($maintenanceFilter === 'overdue') {
    $whereParts[] = '(tb.NGAY_BAO_TRI IS NULL OR tb.NGAY_BAO_TRI <= DATE_SUB(CURDATE(), INTERVAL 30 DAY))';
} elseif ($maintenanceFilter === 'recent') {
    $whereParts[] = 'tb.NGAY_BAO_TRI >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
} else {
    $maintenanceFilter = '';
}
$whereSql = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$countSql = "SELECT COUNT(*) FROM trang_thiet_bi tb $whereSql";
$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    bindDynamic($countStmt, $types, $params);
    $countStmt->execute();
    $countStmt->bind_result($totalRows);
    $countStmt->fetch();
    $countStmt->close();
} else {
    $totalRows = 0;
}
$totalPages = max(1, (int) ceil(($totalRows ?? 0) / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

$listSql = "
    SELECT tb.ID_TB, tb.TEN_TB, tb.TINH_TRANG, tb.NGAY_BAO_TRI, tb.IMAGE,
           CASE
             WHEN tb.NGAY_BAO_TRI IS NULL THEN NULL
             ELSE DATEDIFF(CURDATE(), tb.NGAY_BAO_TRI)
           END AS DAYS_FROM_MAINTENANCE
    FROM trang_thiet_bi tb
    $whereSql
    ORDER BY (tb.NGAY_BAO_TRI IS NULL) DESC, tb.NGAY_BAO_TRI ASC, tb.ID_TB DESC
    LIMIT ? OFFSET ?
";
$listStmt = $conn->prepare($listSql);
$devices = [];
if ($listStmt) {
    $listParams = $params;
    $listTypes = $types . 'ii';
    $listParams[] = $limit;
    $listParams[] = $offset;
    bindDynamic($listStmt, $listTypes, $listParams);
    $listStmt->execute();
    $result = $listStmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $devices[] = $row;
        }
    }
    $listStmt->close();
}

$editData = null;
if (isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0 && ensureOwnDevice($conn, $editId, $branchId)) {
        $editStmt = $conn->prepare('SELECT ID_TB, TEN_TB, TINH_TRANG, NGAY_BAO_TRI FROM trang_thiet_bi WHERE ID_TB = ? AND ID_CN = ? LIMIT 1');
        if ($editStmt) {
            $editStmt->bind_param('ii', $editId, $branchId);
            $editStmt->execute();
            $result = $editStmt->get_result();
            $editData = $result ? $result->fetch_assoc() : null;
            $editStmt->close();
        }
    }
}

function statusBadgeClass(string $status): string
{
  $normalize = function (string $value): string {
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
  };
    $map = [
        'Đang hoạt động' => 'bg-green-100 text-green-800',
        'Bảo trì'        => 'bg-yellow-100 text-yellow-800',
        'Ngưng sử dụng'  => 'bg-red-100 text-red-700',
    ];
    foreach ($map as $key => $class) {
    if ($normalize($status) === $normalize($key)) {
            return $class;
        }
    }
    return 'bg-gray-100 text-gray-700';
}

function maintenanceLabel(?int $days): string
{
    if ($days === null) {
        return 'Chưa có lịch sử';
    }
    if ($days <= 7) {
        return 'Vừa bảo trì';
    }
    if ($days <= 30) {
        return 'Trong 30 ngày';
    }
    return 'Quá ' . $days . ' ngày';
}
?>
<div class="space-y-6">
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-indigo-700">Thiết bị chi nhánh <?= htmlspecialchars($branchName ?? '') ?></h1>
      <p class="text-sm text-gray-500">Theo dõi, cập nhật bảo trì và tồn kho thiết bị tại chi nhánh của bạn.</p>
    </div>
    <div class="px-4 py-2 bg-indigo-50 border border-indigo-100 rounded-lg text-indigo-700 text-sm font-medium">ID chi nhánh: CN<?= (int) $branchId ?></div>
  </div>

  <?php if (!empty($_SESSION['branch_equipment_flash'])): ?>
    <?php $flash = $_SESSION['branch_equipment_flash']; unset($_SESSION['branch_equipment_flash']); ?>
    <div class="rounded-lg px-4 py-3 text-sm <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
      <?= htmlspecialchars($flash['message']) ?>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow">
      <p class="text-xs uppercase text-gray-500">Tổng thiết bị</p>
      <p class="text-3xl font-bold text-gray-900"><?= number_format($stats['total']) ?></p>
    </div>
    <div class="rounded-xl border border-green-200 bg-green-50 p-4 shadow">
      <p class="text-xs uppercase text-green-700">Đang hoạt động</p>
      <p class="text-3xl font-bold text-green-700"><?= number_format($stats['active']) ?></p>
    </div>
    <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 shadow">
      <p class="text-xs uppercase text-yellow-700">Đang bảo trì</p>
      <p class="text-3xl font-bold text-yellow-700"><?= number_format($stats['maintenance']) ?></p>
    </div>
    <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow">
      <p class="text-xs uppercase text-red-700">Quá 30 ngày bảo trì</p>
      <p class="text-3xl font-bold text-red-700"><?= number_format($stats['overdue']) ?></p>
    </div>
  </div>

  <div class="flex flex-wrap gap-4 justify-between items-center">
    <form method="GET" class="flex flex-wrap items-center gap-3 text-sm">
      <input type="hidden" name="page" value="equipment">
      <input type="text" name="search" placeholder="Tìm theo tên hoặc trạng thái" value="<?= htmlspecialchars($search) ?>" class="w-64 rounded-lg border border-gray-300 px-4 py-2 focus:border-indigo-500 focus:outline-none">
      <select name="status" class="rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500">
        <option value="">Tất cả trạng thái</option>
        <?php foreach ($statusOptions as $status): ?>
          <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="maintenance" class="rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500">
        <option value="">Tất cả bảo trì</option>
        <option value="overdue" <?= $maintenanceFilter === 'overdue' ? 'selected' : '' ?>>Quá 30 ngày</option>
        <option value="recent" <?= $maintenanceFilter === 'recent' ? 'selected' : '' ?>>Trong 30 ngày</option>
      </select>
      <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white shadow hover:bg-indigo-700">Lọc</button>
      <?php if ($search || $statusFilter || $maintenanceFilter): ?>
        <a href="?page=equipment" class="text-sm text-gray-500 underline">Bỏ lọc</a>
      <?php endif; ?>
    </form>
    <button onclick="document.getElementById('addEquipmentForm').classList.toggle('hidden')" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-green-700">+ Thêm thiết bị</button>
  </div>

  <div id="addEquipmentForm" class="<?= isset($_GET['showForm']) ? '' : 'hidden' ?> rounded-lg border border-gray-200 bg-white p-5 shadow">
    <form method="POST" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
      <input type="hidden" name="action" value="create">
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-600">Tên thiết bị</label>
        <input name="TEN_TB" required class="w-full rounded border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:outline-none">
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-600">Trạng thái</label>
        <select name="TINH_TRANG" class="w-full rounded border border-gray-300 px-3 py-2 focus:border-indigo-500">
          <?php foreach ($statusOptions as $status): ?>
            <option value="<?= htmlspecialchars($status) ?>" <?= $status === $defaultStatus ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-600">Ngày bảo trì gần nhất</label>
        <input type="date" name="NGAY_BAO_TRI" class="w-full rounded border border-gray-300 px-3 py-2 focus:border-indigo-500">
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-600">Ảnh minh họa</label>
        <input type="file" name="IMAGE" accept="image/*" class="w-full">
      </div>
      <div class="md:col-span-2 flex justify-end gap-3">
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-white">Lưu thiết bị</button>
        <button type="button" onclick="document.getElementById('addEquipmentForm').classList.add('hidden')" class="rounded-lg bg-gray-200 px-4 py-2 text-gray-700">Đóng</button>
      </div>
    </form>
  </div>

  <?php if ($editData): ?>
    <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-5 shadow">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-yellow-800">Chỉnh sửa thiết bị #<?= (int) $editData['ID_TB'] ?></h2>
        <a href="?page=equipment" class="text-sm text-gray-600 underline">Đóng</a>
      </div>
      <form method="POST" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="ID_TB" value="<?= (int) $editData['ID_TB'] ?>">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-600">Tên thiết bị</label>
          <input name="TEN_TB" value="<?= htmlspecialchars($editData['TEN_TB']) ?>" required class="w-full rounded border border-gray-300 px-3 py-2 focus:border-indigo-500">
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-600">Trạng thái</label>
          <select name="TINH_TRANG" class="w-full rounded border border-gray-300 px-3 py-2 focus:border-indigo-500">
            <?php foreach ($statusOptions as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $editData['TINH_TRANG'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-600">Ngày bảo trì gần nhất</label>
          <input type="date" name="NGAY_BAO_TRI" value="<?= htmlspecialchars($editData['NGAY_BAO_TRI']) ?>" class="w-full rounded border border-gray-300 px-3 py-2 focus:border-indigo-500">
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-600">Ảnh mới (nếu có)</label>
          <input type="file" name="IMAGE" accept="image/*" class="w-full">
        </div>
        <div class="md:col-span-2 flex justify-end gap-3">
          <button type="submit" class="rounded-lg bg-yellow-600 px-4 py-2 text-white">Cập nhật</button>
          <a href="?page=equipment" class="rounded-lg bg-gray-200 px-4 py-2 text-gray-700">Hủy</a>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-3 text-left font-semibold text-gray-600">Thiết bị</th>
          <th class="px-4 py-3 text-left font-semibold text-gray-600">Trạng thái</th>
          <th class="px-4 py-3 text-left font-semibold text-gray-600">Ngày bảo trì</th>
          <th class="px-4 py-3 text-left font-semibold text-gray-600">Ghi chú</th>
          <th class="px-4 py-3 text-center font-semibold text-gray-600">Thao tác</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php if ($devices): ?>
          <?php foreach ($devices as $device): ?>
            <?php
              $overdue = $device['NGAY_BAO_TRI'] === null || (int) $device['DAYS_FROM_MAINTENANCE'] > 30;
              $badge = statusBadgeClass($device['TINH_TRANG']);
            ?>
            <tr class="<?= $overdue ? 'bg-red-50/60' : '' ?>">
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <?php $deviceImage = sb_equipment_image_url($device['IMAGE'] ?? null); ?>
                  <img src="../../<?= htmlspecialchars($deviceImage) ?>" alt="<?= htmlspecialchars($device['TEN_TB']) ?>" class="h-12 w-12 rounded-lg object-cover">
                  <div>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($device['TEN_TB']) ?></p>
                    <p class="text-xs text-gray-500">#<?= (int) $device['ID_TB'] ?></p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $badge ?>"><?= htmlspecialchars($device['TINH_TRANG']) ?></span>
              </td>
              <td class="px-4 py-3 text-gray-700">
                <?= $device['NGAY_BAO_TRI'] ? htmlspecialchars($device['NGAY_BAO_TRI']) : '—' ?>
                <p class="text-xs text-gray-500"><?= htmlspecialchars(maintenanceLabel($device['DAYS_FROM_MAINTENANCE'] !== null ? (int) $device['DAYS_FROM_MAINTENANCE'] : null)) ?></p>
              </td>
              <td class="px-4 py-3 text-gray-600">
                <?= $overdue ? '⚠️ Cần bảo trì' : 'Ổn định' ?>
              </td>
              <td class="px-4 py-3 text-center">
                <div class="flex flex-wrap items-center justify-center gap-2">
                  <form method="POST" class="inline">
                    <input type="hidden" name="action" value="quick_status">
                    <input type="hidden" name="preset" value="active">
                    <input type="hidden" name="ID_TB" value="<?= (int) $device['ID_TB'] ?>">
                    <button type="submit" class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700 hover:bg-green-200">Hoạt động</button>
                  </form>
                  <form method="POST" class="inline">
                    <input type="hidden" name="action" value="quick_status">
                    <input type="hidden" name="preset" value="maintenance">
                    <input type="hidden" name="ID_TB" value="<?= (int) $device['ID_TB'] ?>">
                    <button type="submit" class="rounded-full bg-yellow-100 px-3 py-1 text-xs font-semibold text-yellow-700 hover:bg-yellow-200">Bảo trì</button>
                  </form>
                  <a href="?page=equipment&edit=<?= (int) $device['ID_TB'] ?>" class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-200">Sửa</a>
                  <form method="POST" onsubmit="return confirm('Bạn chắc chắn muốn xóa thiết bị này?')" class="inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="ID_TB" value="<?= (int) $device['ID_TB'] ?>">
                    <button type="submit" class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-200">Xóa</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="5" class="px-4 py-6 text-center text-gray-500">Không có thiết bị nào phù hợp với bộ lọc.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
    <div class="flex flex-wrap items-center justify-center gap-2">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=equipment&p=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&maintenance=<?= urlencode($maintenanceFilter) ?>" class="rounded-lg border px-3 py-1 text-sm <?= $i === $page ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 bg-white text-gray-600 hover:border-indigo-400' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
