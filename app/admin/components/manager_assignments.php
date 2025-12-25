
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Ho_Chi_Minh');
include '../../database/config.php';

// ===== HELPER FUNCTIONS =====
function normalizeDateTimeInput($value)
{
    if (!$value) {
        return null;
    }
    $value = trim(str_replace('T', ' ', $value));
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $value)) {
        return null;
    }
    return strlen($value) === 16 ? $value . ':00' : $value;
}

function normalizeDateFilter($value)
{
    if (!$value) {
        return null;
    }
    $value = trim($value);
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt ? $dt->format('Y-m-d') : null;
}

function bindParams($stmt, $types, $params)
{
    if (!$stmt || $types === '' || empty($params)) {
        return;
    }
    $bind = [$types];
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

function singleValue($conn, $sql, $types = '', $params = [])
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    if ($types !== '' && !empty($params)) {
        bindParams($stmt, $types, $params);
    }
    $stmt->execute();
    $value = null;
    $stmt->bind_result($value);
    $stmt->fetch();
    $stmt->close();
    return (int)($value ?? 0);
}

function redirectWithFlash($type, $message)
{
    $_SESSION['manager_assignments_flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
    $target = strtok($_SERVER['REQUEST_URI'] ?? './manager_dashboard.php?page=assignments', '#');
    header('Location: ' . $target);
    exit;
}

// ===== PROCESS POST REQUESTS =====
$currentAccount = $_SESSION['ID_TK'] ?? null;
$branchId = null;

if ($currentAccount) {
    $branchStmt = $conn->prepare('SELECT nv.ID_CN FROM nhan_vien nv WHERE nv.ID_TK = ? LIMIT 1');
    $branchStmt->bind_param('s', $currentAccount);
    $branchStmt->execute();
    $branchStmt->bind_result($branchId);
    $branchStmt->fetch();
    $branchStmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $branchId) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_assignment') {
        $scheduleId = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
        $employeeId = trim($_POST['employee_id'] ?? '');
        $startTime = normalizeDateTimeInput($_POST['start_time'] ?? '');
        $endTime = normalizeDateTimeInput($_POST['end_time'] ?? '');

        if ($scheduleId <= 0 || $employeeId === '' || !$startTime || !$endTime) {
            redirectWithFlash('error', 'Vui lòng nhập đầy đủ thông tin hợp lệ để phân công.');
        }

        if (strtotime($endTime) < strtotime($startTime)) {
            redirectWithFlash('error', 'Thời gian kết thúc cần lớn hơn thời gian bắt đầu.');
        }

        $scheduleStmt = $conn->prepare("SELECT 1 FROM lich_hen WHERE ID_LICHHEN = ? AND ID_CHINHANH = ? AND TRANGTHAI = 'Đã xác nhận' LIMIT 1");
        $scheduleStmt->bind_param('ii', $scheduleId, $branchId);
        $scheduleStmt->execute();
        $scheduleStmt->store_result();
        if ($scheduleStmt->num_rows === 0) {
            $scheduleStmt->close();
            redirectWithFlash('error', 'Lịch hẹn không thuộc chi nhánh hoặc chưa được xác nhận.');
        }
        $scheduleStmt->close();

        $staffStmt = $conn->prepare('SELECT 1 FROM nhan_vien WHERE ID_TK = ? AND ID_CN = ? LIMIT 1');
        $staffStmt->bind_param('si', $employeeId, $branchId);
        $staffStmt->execute();
        $staffStmt->store_result();
        if ($staffStmt->num_rows === 0) {
            $staffStmt->close();
            redirectWithFlash('error', 'Nhân viên được chọn không thuộc chi nhánh này.');
        }
        $staffStmt->close();

        $existStmt = $conn->prepare('SELECT 1 FROM phan_cong_nhan_vien WHERE ID_LICHHEN = ? LIMIT 1');
        $existStmt->bind_param('i', $scheduleId);
        $existStmt->execute();
        $existStmt->store_result();
        if ($existStmt->num_rows > 0) {
            $existStmt->close();
            redirectWithFlash('error', 'Lịch hẹn này đã có phân công.');
        }
        $existStmt->close();

        $insertStmt = $conn->prepare('INSERT INTO phan_cong_nhan_vien (ID_TK, ID_LICHHEN, THOI_GIAN_BAT_DAU, THOI_GIAN_KET_THUC) VALUES (?, ?, ?, ?)');
        if (!$insertStmt) {
            redirectWithFlash('error', 'Không thể tạo phân công mới.');
        }
        $insertStmt->bind_param('siss', $employeeId, $scheduleId, $startTime, $endTime);
        $ok = $insertStmt->execute();
        $insertStmt->close();

        redirectWithFlash($ok ? 'success' : 'error', $ok ? 'Đã tạo phân công mới.' : 'Không thể lưu phân công, vui lòng thử lại.');
    }

    if ($action === 'update_assignment') {
        $scheduleId = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
        $employeeId = trim($_POST['employee_id'] ?? '');
        $startTime = normalizeDateTimeInput($_POST['start_time'] ?? '');
        $endTime = normalizeDateTimeInput($_POST['end_time'] ?? '');

        if ($scheduleId <= 0 || $employeeId === '' || !$startTime || !$endTime) {
            redirectWithFlash('error', 'Thông tin chỉnh sửa chưa hợp lệ.');
        }

        if (strtotime($endTime) < strtotime($startTime)) {
            redirectWithFlash('error', 'Thời gian kết thúc cần lớn hơn thời gian bắt đầu.');
        }

        $checkStmt = $conn->prepare('SELECT 1 FROM phan_cong_nhan_vien pc JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN WHERE pc.ID_LICHHEN = ? AND pc.ID_TK = ? AND lh.ID_CHINHANH = ? LIMIT 1');
        $checkStmt->bind_param('isi', $scheduleId, $employeeId, $branchId);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows === 0) {
            $checkStmt->close();
            redirectWithFlash('error', 'Không tìm thấy phân công cần chỉnh sửa trong chi nhánh của bạn.');
        }
        $checkStmt->close();

        $updateStmt = $conn->prepare('UPDATE phan_cong_nhan_vien SET THOI_GIAN_BAT_DAU = ?, THOI_GIAN_KET_THUC = ? WHERE ID_LICHHEN = ? AND ID_TK = ?');
        if (!$updateStmt) {
            redirectWithFlash('error', 'Không thể cập nhật phân công.');
        }
        $updateStmt->bind_param('ssis', $startTime, $endTime, $scheduleId, $employeeId);
        $ok = $updateStmt->execute();
        $updateStmt->close();

        redirectWithFlash($ok ? 'success' : 'error', $ok ? 'Đã cập nhật thời gian phân công.' : 'Không thể cập nhật phân công.');
    }

    if ($action === 'delete_assignment') {
        $scheduleId = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
        $employeeId = trim($_POST['employee_id'] ?? '');

        if ($scheduleId <= 0 || $employeeId === '') {
            redirectWithFlash('error', 'Thông tin phân công cần xóa không hợp lệ.');
        }

        $checkStmt = $conn->prepare('SELECT 1 FROM phan_cong_nhan_vien pc JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN WHERE pc.ID_LICHHEN = ? AND pc.ID_TK = ? AND lh.ID_CHINHANH = ? LIMIT 1');
        $checkStmt->bind_param('isi', $scheduleId, $employeeId, $branchId);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows === 0) {
            $checkStmt->close();
            redirectWithFlash('error', 'Bạn không thể xóa phân công này.');
        }
        $checkStmt->close();

        $deleteStmt = $conn->prepare('DELETE FROM phan_cong_nhan_vien WHERE ID_LICHHEN = ? AND ID_TK = ?');
        if (!$deleteStmt) {
            redirectWithFlash('error', 'Không thể xóa phân công.');
        }
        $deleteStmt->bind_param('is', $scheduleId, $employeeId);
        $ok = $deleteStmt->execute();
        $deleteStmt->close();

        redirectWithFlash($ok ? 'success' : 'error', $ok ? 'Đã xóa phân công.' : 'Không thể xóa phân công lúc này.');
    }

    if ($action === 'request_decision') {
        $requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
        $decision = $_POST['decision'] ?? '';
        $decisionLabel = $decision === 'approve' ? 'Đã duyệt' : ($decision === 'reject' ? 'Từ chối' : null);

        if ($requestId <= 0 || !$decisionLabel) {
            redirectWithFlash('error', 'Yêu cầu không hợp lệ.');
        }

        $infoStmt = $conn->prepare('SELECT yc.ID_LICHHEN, yc.ID_TK FROM yeu_cau_thay_doi_lich yc JOIN lich_hen lh ON yc.ID_LICHHEN = lh.ID_LICHHEN WHERE yc.ID_YEUCAU = ? AND lh.ID_CHINHANH = ? LIMIT 1');
        $infoStmt->bind_param('ii', $requestId, $branchId);
        $infoStmt->execute();
        $infoStmt->bind_result($scheduleId, $staffId);
        $found = $infoStmt->fetch();
        $infoStmt->close();
        if (!$found) {
            redirectWithFlash('error', 'Không tìm thấy yêu cầu đổi lịch thuộc chi nhánh của bạn.');
        }

        $updateStmt = $conn->prepare('UPDATE yeu_cau_thay_doi_lich SET TRANGTHAI = ? WHERE ID_YEUCAU = ?');
        if (!$updateStmt) {
            redirectWithFlash('error', 'Không thể cập nhật trạng thái yêu cầu.');
        }
        $updateStmt->bind_param('si', $decisionLabel, $requestId);
        $ok = $updateStmt->execute();
        $updateStmt->close();

        if ($decisionLabel === 'Đã duyệt') {
            $delStmt = $conn->prepare('DELETE FROM phan_cong_nhan_vien WHERE ID_LICHHEN = ? AND ID_TK = ?');
            if ($delStmt) {
                $delStmt->bind_param('is', $scheduleId, $staffId);
                $delStmt->execute();
                $delStmt->close();
            }
        }

        $msg = $decisionLabel === 'Đã duyệt' ? 'Đã duyệt yêu cầu đổi lịch.' : 'Đã từ chối yêu cầu đổi lịch.';
        redirectWithFlash($ok ? 'success' : 'error', $ok ? $msg : 'Không thể xử lý yêu cầu đổi lịch.');
    }
}

function humanDateTime($value)
{
    if (!$value || $value === '0000-00-00 00:00:00') {
        return '—';
    }
    try {
        $dt = new DateTime($value);
        return $dt->format('d/m/Y H:i');
    } catch (Exception $e) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}


$currentAccount = $_SESSION['ID_TK'] ?? null;
if (!$currentAccount) {
  echo '<div class="rounded-xl bg-white p-8 text-center text-red-600 shadow">Vui lòng đăng nhập để xem phân công.</div>';
  return;
}

if (!$branchId) {
  echo '<div class="rounded-xl bg-white p-8 text-center text-red-600 shadow">Tài khoản của bạn chưa được gán vào chi nhánh nào. Hãy liên hệ quản trị viên.</div>';
  return;
}

// ===== GET BRANCH INFO =====
$branchName = '';
$branchStmt = $conn->prepare('SELECT cn.TEN_CN FROM chi_nhanh cn WHERE cn.ID_CN = ? LIMIT 1');
$branchStmt->bind_param('i', $branchId);
$branchStmt->execute();
$branchStmt->bind_result($branchName);
$branchStmt->fetch();
$branchStmt->close();

$flash = $_SESSION['manager_assignments_flash'] ?? null;
unset($_SESSION['manager_assignments_flash']);

$perPage = 8;
$page = isset($_GET['assign_p']) ? max(1, (int)$_GET['assign_p']) : 1;
$offset = ($page - 1) * $perPage;

$statusOptions = ['Đang chờ', 'Đã xác nhận', 'Đã hoàn thành', 'Đã hủy'];
$scopeOptions = ['upcoming', 'seven', 'today', 'past', 'all'];

$filters = [
    'staff'    => trim($_GET['staff'] ?? ''),
    'service'  => trim($_GET['service'] ?? ''),
    'status'   => trim($_GET['status'] ?? ''),
    'date_from'=> normalizeDateFilter($_GET['date_from'] ?? ''),
    'date_to'  => normalizeDateFilter($_GET['date_to'] ?? ''),
    'scope'    => $_GET['scope'] ?? 'all',
];

if (!in_array($filters['status'], $statusOptions, true)) {
    $filters['status'] = '';
}
if (!in_array($filters['scope'], $scopeOptions, true)) {
    $filters['scope'] = 'upcoming';
}

$fromClause = 'FROM phan_cong_nhan_vien pc
JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
JOIN tai_khoan tk ON pc.ID_TK = tk.ID_TK
LEFT JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
LEFT JOIN hoa_don hd ON lh.ID_LICHHEN = hd.ID_LICHHEN';

$conditions = ['lh.ID_CHINHANH = ?'];
$types = 'i';
$params = [$branchId];

if ($filters['staff'] !== '') {
    $conditions[] = 'tk.HO_TEN LIKE ?';
    $types .= 's';
    $params[] = '%' . $filters['staff'] . '%';
}
if ($filters['service'] !== '') {
    $conditions[] = 'dv.TEN_DV LIKE ?';
    $types .= 's';
    $params[] = '%' . $filters['service'] . '%';
}
if ($filters['status'] !== '') {
    $conditions[] = 'lh.TRANGTHAI = ?';
    $types .= 's';
    $params[] = $filters['status'];
}
if ($filters['date_from']) {
    $conditions[] = 'DATE(pc.THOI_GIAN_BAT_DAU) >= ?';
    $types .= 's';
    $params[] = $filters['date_from'];
}
if ($filters['date_to']) {
    $conditions[] = 'DATE(pc.THOI_GIAN_BAT_DAU) <= ?';
    $types .= 's';
    $params[] = $filters['date_to'];
}

switch ($filters['scope']) {
    case 'seven':
        $conditions[] = 'pc.THOI_GIAN_BAT_DAU BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)';
        break;
    case 'today':
        $conditions[] = 'DATE(pc.THOI_GIAN_BAT_DAU) = CURDATE()';
        break;
    case 'past':
        $conditions[] = 'pc.THOI_GIAN_BAT_DAU < NOW()';
        break;
    case 'all':
        break;
    default:
        $conditions[] = 'pc.THOI_GIAN_BAT_DAU >= NOW()';
        break;
}

$whereClause = 'WHERE ' . implode(' AND ', $conditions);

// Count total rows for pagination
$countSql = 'SELECT COUNT(*) ' . $fromClause . ' ' . $whereClause;
$countStmt = $conn->prepare($countSql);
bindParams($countStmt, $types, $params);
$countStmt->execute();
$countStmt->bind_result($totalRows);
$countStmt->fetch();
$countStmt->close();
$totalRows = (int)($totalRows ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// If no rows, simplify and just get all assignments for the branch
if ($totalRows === 0) {
  $fromClause = 'FROM phan_cong_nhan_vien pc
  JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
  JOIN tai_khoan tk ON pc.ID_TK = tk.ID_TK
  LEFT JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
  LEFT JOIN hoa_don hd ON lh.ID_LICHHEN = hd.ID_LICHHEN';
  $whereClause = 'WHERE lh.ID_CHINHANH = ?';
  $types = 'i';
  $params = [$branchId];
  $totalRows = singleValue($conn, 'SELECT COUNT(*) FROM phan_cong_nhan_vien pc JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN WHERE lh.ID_CHINHANH = ?', 'i', [$branchId]);
  $totalPages = max(1, (int)ceil($totalRows / $perPage));
}

// Build prepared data query for assignments
$dataSql = 'SELECT pc.ID_LICHHEN, pc.ID_TK, pc.THOI_GIAN_BAT_DAU, pc.THOI_GIAN_KET_THUC, tk.HO_TEN AS STAFF_NAME, tk.SDT, tk.EMAIL, lh.DIA_CHI_HEN, lh.TRANGTHAI, dv.TEN_DV, hd.ID_HD '
  . $fromClause . ' ' . $whereClause . ' ORDER BY pc.THOI_GIAN_BAT_DAU DESC, pc.ID_LICHHEN DESC LIMIT ? OFFSET ?';
$dataStmt = $conn->prepare($dataSql);
if ($dataStmt) {
  $dataTypes = $types . 'ii';
  $dataParams = array_merge($params, [$perPage, $offset]);
  bindParams($dataStmt, $dataTypes, $dataParams);
  $dataStmt->execute();
  $dataResult = $dataStmt->get_result();
  $assignments = $dataResult ? ($dataResult->fetch_all(MYSQLI_ASSOC) ?: []) : [];
  $dataStmt->close();
} else {
  error_log('Prepare failed for assignments query: ' . $conn->error);
  $assignments = [];
}

$employees = [];
$empStmt = $conn->prepare('SELECT tk.ID_TK, tk.HO_TEN, tk.SDT, nv.CHUYEN_MON FROM nhan_vien nv JOIN tai_khoan tk ON nv.ID_TK = tk.ID_TK WHERE nv.ID_CN = ? ORDER BY tk.HO_TEN ASC');
$empStmt->bind_param('i', $branchId);
$empStmt->execute();
$empRes = $empStmt->get_result();
if ($empRes) {
    $employees = $empRes->fetch_all(MYSQLI_ASSOC);
}
$empStmt->close();

// Tính số ca trong ngày cho mỗi nhân viên (để gợi ý ưu tiên)
$employeeWorkload = [];
foreach ($employees as &$emp) {
    $wlStmt = $conn->prepare('SELECT COUNT(*) FROM phan_cong_nhan_vien pc WHERE pc.ID_TK = ? AND DATE(pc.THOI_GIAN_BAT_DAU) = CURDATE()');
    $wlStmt->bind_param('s', $emp['ID_TK']);
    $wlStmt->execute();
    $wlStmt->bind_result($count);
    $wlStmt->fetch();
    $wlStmt->close();
    $emp['CA_TRONG_NGAY'] = (int)$count;
    $employeeWorkload[$emp['ID_TK']] = (int)$count;
}
unset($emp);


$unassigned = [];
$overdue_unassigned = [];
$unStmt = $conn->prepare("SELECT lh.ID_LICHHEN, lh.THOI_GIAN_BAT_DAU, lh.DIA_CHI_HEN, dv.TEN_DV, dv.THOI_LUONG_PHUT, kh.HO_TEN AS TEN_KHACH_HANG, kh.SDT AS SDT_KHACH_HANG FROM lich_hen lh LEFT JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV LEFT JOIN khach_hang kh ON lh.ID_TK = kh.ID_TK WHERE lh.ID_CHINHANH = ? AND lh.TRANGTHAI = 'Đã xác nhận' AND NOT EXISTS (SELECT 1 FROM phan_cong_nhan_vien pc WHERE pc.ID_LICHHEN = lh.ID_LICHHEN) ORDER BY lh.THOI_GIAN_BAT_DAU ASC");
if (!$unStmt) {
    error_log("Prepare failed for unassigned query: " . $conn->error);
    // Fallback query without THOI_LUONG_PHUT if column doesn't exist
    $unStmt = $conn->prepare("SELECT lh.ID_LICHHEN, lh.THOI_GIAN_BAT_DAU, lh.DIA_CHI_HEN, dv.TEN_DV, kh.HO_TEN AS TEN_KHACH_HANG, kh.SDT AS SDT_KHACH_HANG FROM lich_hen lh LEFT JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV LEFT JOIN khach_hang kh ON lh.ID_TK = kh.ID_TK WHERE lh.ID_CHINHANH = ? AND lh.TRANGTHAI = 'Đã xác nhận' AND NOT EXISTS (SELECT 1 FROM phan_cong_nhan_vien pc WHERE pc.ID_LICHHEN = lh.ID_LICHHEN) ORDER BY lh.THOI_GIAN_BAT_DAU ASC");
}
if ($unStmt) {
    $unStmt->bind_param('i', $branchId);
    $unStmt->execute();
    $unRes = $unStmt->get_result();
    if ($unRes) {
      while ($row = $unRes->fetch_assoc()) {
        // Set default duration if not exists
        if (!isset($row['THOI_LUONG_PHUT'])) {
            $row['THOI_LUONG_PHUT'] = 60;
        }
        if (strtotime($row['THOI_GIAN_BAT_DAU']) >= time()) {
          $unassigned[] = $row;
        } else {
          $overdue_unassigned[] = $row;
        }
      }
    }
    $unStmt->close();
}

$requests = [];
$reqStmt = $conn->prepare("SELECT yc.ID_YEUCAU, yc.ID_LICHHEN, yc.ID_TK AS STAFF_ID, yc.NOI_DUNG, yc.NGAY_GUI, tkstaff.HO_TEN AS STAFF_NAME, lh.THOI_GIAN_BAT_DAU, lh.DIA_CHI_HEN, dv.TEN_DV FROM yeu_cau_thay_doi_lich yc JOIN lich_hen lh ON yc.ID_LICHHEN = lh.ID_LICHHEN LEFT JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV LEFT JOIN tai_khoan tkstaff ON yc.ID_TK = tkstaff.ID_TK WHERE lh.ID_CHINHANH = ? AND yc.TRANGTHAI = 'Chờ duyệt' ORDER BY yc.NGAY_GUI DESC LIMIT 5");
$reqStmt->bind_param('i', $branchId);
$reqStmt->execute();
$reqRes = $reqStmt->get_result();
if ($reqRes) {
    $requests = $reqRes->fetch_all(MYSQLI_ASSOC);
}
$reqStmt->close();

$stats = [
  'total'      => singleValue($conn, 'SELECT COUNT(*) FROM phan_cong_nhan_vien pc JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN WHERE lh.ID_CHINHANH = ?', 'i', [$branchId]),
  'upcoming'   => singleValue($conn, 'SELECT COUNT(*) FROM phan_cong_nhan_vien pc JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN WHERE lh.ID_CHINHANH = ? AND pc.THOI_GIAN_BAT_DAU >= NOW()', 'i', [$branchId]),
  'week'       => singleValue($conn, 'SELECT COUNT(*) FROM phan_cong_nhan_vien pc JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN WHERE lh.ID_CHINHANH = ? AND pc.THOI_GIAN_BAT_DAU BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)', 'i', [$branchId]),
  'staff'      => singleValue($conn, 'SELECT COUNT(*) FROM nhan_vien WHERE ID_CN = ?', 'i', [$branchId]),
  'unassigned' => count($unassigned),
  'overdue_unassigned' => count($overdue_unassigned),
];
?>

<div class="space-y-8">
  <?php if (!empty($flash)): ?>
    <div class="rounded-xl border px-4 py-3 shadow <?php echo $flash['type'] === 'success' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-700'; ?>">
      <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <section class="bg-gradient-to-r from-indigo-600 via-purple-600 to-rose-500 text-white rounded-2xl p-6 shadow-lg">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <p class="text-sm uppercase tracking-wide opacity-80">Bảng điều phối</p>
        <h1 class="text-3xl font-bold">Phân công nhân viên - <?= htmlspecialchars($branchName, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-sm opacity-90">Theo dõi tiến độ, phân công nhân sự và xử lý yêu cầu đổi lịch của chi nhánh.</p>
      </div>
      <div class="flex flex-wrap items-center gap-4">
        <div class="bg-white/20 rounded-xl px-4 py-2 text-center">
          <p class="text-xs uppercase opacity-80">Tổng phân công</p>
          <p class="text-2xl font-semibold"><?= number_format($stats['total']) ?></p>
        </div>
        <div class="bg-white/20 rounded-xl px-4 py-2 text-center">
          <p class="text-xs uppercase opacity-80">Chờ phân công</p>
          <p class="text-2xl font-semibold"><?= number_format($stats['unassigned']) ?></p>
        </div>
        <div class="bg-white/20 rounded-xl px-4 py-2 text-center">
          <p class="text-xs uppercase opacity-80">Quá hẹn</p>
          <p class="text-2xl font-semibold text-pink-200"><?= number_format($stats['overdue_unassigned']) ?></p>
        </div>
        <button onclick="openAddAssignmentModal()" class="inline-flex items-center gap-2 bg-white text-indigo-700 font-semibold px-4 py-2 rounded-xl shadow transition hover:-translate-y-0.5">
          <span>Thêm phân công</span>
        </button>
      </div>
    </div>
  </section>

  <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="bg-white rounded-xl p-4 shadow flex flex-col gap-1">
      <p class="text-sm text-gray-500">Tổng phân công</p>
      <p class="text-2xl font-semibold text-indigo-700"><?= number_format($stats['total']) ?></p>
      <span class="text-xs text-gray-400">Toàn bộ chi nhánh</span>
    </div>
    <div class="bg-white rounded-xl p-4 shadow flex flex-col gap-1">
      <p class="text-sm text-gray-500">Sắp diễn ra</p>
      <p class="text-2xl font-semibold text-blue-600"><?= number_format($stats['upcoming']) ?></p>
      <span class="text-xs text-gray-400">Từ hiện tại trở đi</span>
    </div>
    <div class="bg-white rounded-xl p-4 shadow flex flex-col gap-1">
      <p class="text-sm text-gray-500">Trong 7 ngày</p>
      <p class="text-2xl font-semibold text-emerald-600"><?= number_format($stats['week']) ?></p>
      <span class="text-xs text-gray-400">Kế hoạch chuẩn bị</span>
    </div>
    <div class="bg-white rounded-xl p-4 shadow flex flex-col gap-1">
      <p class="text-sm text-gray-500">Nhân sự</p>
      <p class="text-2xl font-semibold text-rose-600"><?= number_format($stats['staff']) ?></p>
      <span class="text-xs text-gray-400">Trong chi nhánh</span>
    </div>
  </section>

  <div class="grid gap-6 xl:grid-cols-3">
    <!-- Main Content - Table Section -->
    <div class="xl:col-span-2 bg-white rounded-2xl shadow p-6">
      <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between mb-4">
        <div>
          <h2 class="text-xl font-semibold text-gray-800">Danh sách phân công</h2>
          <p class="text-sm text-gray-500">Trang <?= $page ?> / <?= $totalPages ?> &middot; <?= $stats['total'] ?> bản ghi</p>
        </div>
      </div>

      <div class="overflow-x-auto">
        <?php if (count($assignments) === 0): ?>
          <div class="py-10 text-center text-gray-500">Không có phân công nào.</div>
        <?php else: ?>
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
              <tr class="bg-gray-50 text-gray-600">
                <th class="px-3 py-2 text-left font-medium">#</th>
                <th class="px-3 py-2 text-left font-medium">Lịch hẹn</th>
                <th class="px-3 py-2 text-left font-medium">Nhân viên</th>
                <th class="px-3 py-2 text-left font-medium">Thời gian</th>
                <th class="px-3 py-2 text-left font-medium">Địa điểm</th>
                <th class="px-3 py-2 text-left font-medium">Dịch vụ</th>
                <th class="px-3 py-2 text-center font-medium">Trạng thái</th>
                <th class="px-3 py-2 text-center font-medium">Thao tác</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php foreach ($assignments as $index => $row): ?>
                <?php 
                  $formId = 'edit-form-' . $row['ID_LICHHEN'] . '-' . str_replace(' ', '-', $row['ID_TK']);
                  $isProtected = strtotime($row['THOI_GIAN_BAT_DAU']) <= time();
                ?>
                <tr class="hover:bg-indigo-50/40 transition">
                  <td class="px-3 py-3 text-gray-500"><?= $offset + $index + 1 ?></td>
                  <td class="px-3 py-3">
                    <p class="font-medium text-gray-800">Lịch #<?= htmlspecialchars($row['ID_LICHHEN'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-xs text-gray-500">Bắt đầu: <?= date('d/m/Y H:i', strtotime($row['THOI_GIAN_BAT_DAU'])) ?></p>
                  </td>
                  <td class="px-3 py-3">
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($row['STAFF_NAME'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-xs text-gray-500">ID: <?= htmlspecialchars($row['ID_TK'], ENT_QUOTES, 'UTF-8') ?></p>
                  </td>
                  <td class="px-3 py-3">
                    <p class="text-sm text-gray-700">BĐ: <?= date('d/m/Y H:i', strtotime($row['THOI_GIAN_BAT_DAU'])) ?></p>
                    <p class="text-xs text-gray-500">KT: <?= date('d/m/Y H:i', strtotime($row['THOI_GIAN_KET_THUC'])) ?></p>
                  </td>
                  <td class="px-3 py-3">
                    <p class="text-sm text-gray-700 truncate max-w-[120px]" title="<?= htmlspecialchars($row['DIA_CHI_HEN'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($row['DIA_CHI_HEN'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                  </td>
                  <td class="px-3 py-3">
                    <p class="text-sm text-gray-700 truncate max-w-[100px]" title="<?= htmlspecialchars($row['TEN_DV'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($row['TEN_DV'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                  </td>
                  <td class="px-3 py-3 text-center">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold whitespace-nowrap <?= $row['TRANGTHAI'] === 'Đã hoàn thành' ? 'bg-green-100 text-green-700' : ($row['TRANGTHAI'] === 'Đã hủy' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') ?>">
                      <?= htmlspecialchars($row['TRANGTHAI'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  </td>
                  <td class="px-3 py-3 text-center">
                    <div class="flex flex-wrap justify-center gap-1">
                      <button 
                        type="button" 
                        data-toggle-edit="<?= $formId ?>" 
                        class="px-2 py-1 text-xs font-semibold rounded-lg shadow transition-colors <?= $isProtected ? 'bg-gray-300 text-gray-500 cursor-not-allowed opacity-60' : 'bg-indigo-600 text-white hover:bg-indigo-700' ?>"
                        <?= $isProtected ? 'disabled title="Không thể sửa phân công cho lịch hẹn đã hoàn thành hoặc quá hạn"' : 'title="Chỉnh sửa thời gian phân công"' ?>>
                        Sửa
                      </button>
                      <form method="POST" onsubmit="<?= $isProtected ? 'return false;' : "return confirm('Bạn chắc muốn xóa phân công này?');" ?>" class="inline">
                        <input type="hidden" name="action" value="delete_assignment">
                        <input type="hidden" name="schedule_id" value="<?= (int)$row['ID_LICHHEN'] ?>">
                        <input type="hidden" name="employee_id" value="<?= htmlspecialchars($row['ID_TK'], ENT_QUOTES, 'UTF-8') ?>">
                        <button 
                          type="submit" 
                          class="px-2 py-1 text-xs font-semibold rounded-lg shadow transition-colors <?= $isProtected ? 'bg-gray-300 text-gray-500 cursor-not-allowed opacity-60' : 'bg-rose-600 text-white hover:bg-rose-700' ?>"
                          <?= $isProtected ? 'disabled title="Không thể xóa phân công cho lịch hẹn đã hoàn thành hoặc quá hạn"' : 'title="Xóa phân công"' ?>>
                          Xóa
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
                <tr id="<?= $formId ?>" class="hidden bg-slate-50/70">
                  <td colspan="8" class="px-3 py-4">
                    <form method="POST" class="grid gap-4 md:grid-cols-4">
                      <input type="hidden" name="action" value="update_assignment">
                      <input type="hidden" name="schedule_id" value="<?= (int)$row['ID_LICHHEN'] ?>">
                      <input type="hidden" name="employee_id" value="<?= htmlspecialchars($row['ID_TK'], ENT_QUOTES, 'UTF-8') ?>">
                      <label class="text-xs font-medium text-gray-600">
                        Bắt đầu
                        <input type="datetime-local" name="start_time" value="<?= htmlspecialchars(str_replace(' ', 'T', substr($row['THOI_GIAN_BAT_DAU'], 0, 16)), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
                      </label>
                      <label class="text-xs font-medium text-gray-600">
                        Kết thúc
                        <input type="datetime-local" name="end_time" value="<?= htmlspecialchars(str_replace(' ', 'T', substr($row['THOI_GIAN_KET_THUC'], 0, 16)), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
                      </label>
                      <div class="flex items-end gap-2">
                        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Lưu</button>
                        <button type="button" data-toggle-edit="<?= $formId ?>" class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100">Đóng</button>
                      </div>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <div class="flex flex-wrap items-center justify-center gap-2 border-t border-slate-100 px-6 py-4">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php $query = array_merge($_GET, ['assign_p' => $i]); ?>
            <a href="?<?= htmlspecialchars(http_build_query($query), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg px-4 py-2 text-sm font-semibold <?= $i === $page ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
              <?= $i ?>
            </a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Sidebar - Right Column -->
    <div class="space-y-6">
    <!-- Quick Actions Card -->
    <div class="rounded-2xl bg-gradient-to-br from-slate-900 via-indigo-900 to-indigo-700 text-white p-5 shadow-lg">
      <p class="text-sm text-white/70">Hành động nhanh</p>
      <h3 class="text-2xl font-semibold mb-4">Điều phối hiệu quả</h3>
      <div class="space-y-2">
        <button onclick="openAddAssignmentModal()" class="block w-full text-center bg-white text-indigo-700 font-semibold rounded-lg px-4 py-2 shadow hover:-translate-y-0.5 transition">Tạo phân công mới</button>
        <a href="<?= $_SERVER['REQUEST_URI'] ?>#" class="block w-full text-center bg-white/10 border border-white/30 text-sm rounded-lg px-4 py-2 hover:bg-white/20 transition">Xem phân công quá hạn</a>
      </div>
    </div>

    <!-- Unassigned Schedules -->
    <div class="bg-white rounded-2xl shadow p-5">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-lg font-semibold text-gray-800">Lịch hẹn chờ phân công</h3>
          <p class="text-sm text-gray-500"><?= count($unassigned) ?> lịch chưa quá hạn</p>
        </div>
        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-700">Ưu tiên</span>
      </div>
      <?php if (count($unassigned) > 0): ?>
        <ul class="divide-y divide-gray-100">
          <?php foreach ($unassigned as $item): ?>
            <li class="py-3 flex items-start justify-between gap-3">
              <div>
                <p class="font-semibold text-gray-800">Lịch #<?= htmlspecialchars($item['ID_LICHHEN'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($item['THOI_GIAN_BAT_DAU'])) ?></p>
                <p class="text-xs text-gray-500 truncate max-w-[200px]" title="<?= htmlspecialchars($item['DIA_CHI_HEN'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['DIA_CHI_HEN'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <button onclick="openAddAssignmentModal()" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 whitespace-nowrap">Phân công</button>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="text-sm text-gray-500">Tất cả lịch đã được xử lý.</p>
      <?php endif; ?>
    </div>

    <!-- Overdue Unassigned Schedules -->
    <div class="bg-white rounded-2xl shadow p-5">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-lg font-semibold text-pink-800">Lịch quá hẹn</h3>
          <p class="text-sm text-pink-600"><?= count($overdue_unassigned) ?> lịch chưa phân công, đã quá hạn</p>
        </div>
        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-pink-100 text-pink-700">Cảnh báo</span>
      </div>
      <?php if (count($overdue_unassigned) > 0): ?>
        <ul class="divide-y divide-pink-100">
          <?php foreach ($overdue_unassigned as $item): ?>
            <li class="py-3 flex items-start justify-between gap-3">
              <div>
                <p class="font-semibold text-pink-800">Lịch #<?= htmlspecialchars($item['ID_LICHHEN'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-pink-600"><?= date('d/m/Y H:i', strtotime($item['THOI_GIAN_BAT_DAU'])) ?></p>
                <p class="text-xs text-pink-500 truncate max-w-[200px]" title="<?= htmlspecialchars($item['DIA_CHI_HEN'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['DIA_CHI_HEN'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <span class="text-xs font-semibold text-gray-400 cursor-not-allowed" title="Không thể phân công cho lịch đã quá hạn">Quá hạn</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="text-sm text-pink-600">Không có lịch quá hạn.</p>
      <?php endif; ?>
    </div>

    <!-- Schedule Change Requests -->
    <div class="bg-white rounded-2xl shadow p-5">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-lg font-semibold text-gray-800">Yêu cầu đổi lịch</h3>
          <p class="text-sm text-gray-500">Theo dõi và phản hồi kịp thời</p>
        </div>
        <button id="requestToggle" type="button" class="px-3 py-1 rounded-lg bg-yellow-100 text-yellow-700 text-sm font-semibold"><?= count($requests) ?> yêu cầu</button>
      </div>
      <div id="requestPanel" class="mt-4 hidden opacity-0 -translate-y-2 transition">
        <?php if (count($requests) === 0): ?>
          <p class="text-sm text-gray-500">Hiện chưa có yêu cầu đổi lịch nào.</p>
        <?php else: ?>
          <ul class="space-y-4 max-h-80 overflow-y-auto pr-2">
            <?php foreach ($requests as $rq): ?>
              <li class="border border-gray-100 rounded-xl p-3">
                <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($rq['STAFF_NAME'], ENT_QUOTES, 'UTF-8') ?> · Lịch #<?= htmlspecialchars($rq['ID_LICHHEN'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-gray-500">Gửi lúc <?= date('d/m/Y H:i', strtotime($rq['NGAY_GUI'])) ?></p>
                <p class="text-sm text-gray-600 mt-2">Lý do: <span class="italic"><?= nl2br(htmlspecialchars($rq['NOI_DUNG'], ENT_QUOTES, 'UTF-8')) ?></span></p>
                <div class="mt-3 flex flex-wrap gap-2">
                  <form method="POST" action="" class="inline">
                    <input type="hidden" name="action" value="approve_request">
                    <input type="hidden" name="request_id" value="<?= htmlspecialchars($rq['ID_YEUCAU'], ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="px-3 py-1 rounded-lg text-xs font-semibold bg-emerald-100 text-emerald-700 hover:bg-emerald-200">Duyệt</button>
                  </form>
                  <form method="POST" action="" class="inline">
                    <input type="hidden" name="action" value="reject_request">
                    <input type="hidden" name="request_id" value="<?= htmlspecialchars($rq['ID_YEUCAU'], ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="px-3 py-1 rounded-lg text-xs font-semibold bg-rose-100 text-rose-700 hover:bg-rose-200">Từ chối</button>
                  </form>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
    </div>
  </div>

  <!-- Add Assignment Modal -->
  <div id="addAssignmentModal" class="fixed inset-0 bg-black/50 items-center justify-center hidden z-50 p-4" style="display: none;">
    <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto animate-fade-in">
      <!-- Modal Header -->
      <div class="sticky top-0 bg-gradient-to-r from-indigo-600 via-purple-600 to-rose-500 p-6 text-white rounded-t-2xl flex items-center justify-between">
        <h2 class="text-2xl font-bold">Thêm Phân Công</h2>
        <button 
          type="button"
          onclick="closeAddAssignmentModal()"
          class="ml-auto bg-white/20 hover:bg-white/30 rounded-full p-2 w-10 h-10 flex items-center justify-center transition">
          <span class="text-xl">&times;</span>
        </button>
      </div>

      <!-- Modal Body -->
      <form method="POST" class="p-6" onsubmit="return validateAndConfirmAssignment(event);">
        <input type="hidden" name="action" value="create_assignment">

        <!-- Lịch Hẹn Selection -->
        <div class="mb-6">
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            <span class="text-red-500">*</span> Chọn Lịch Hẹn
          </label>
          <select id="modalScheduleSelect" name="schedule_id" class="w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 placeholder-gray-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200" required>
            <option value="">-- Chọn lịch hẹn --</option>
            <?php foreach ($unassigned as $schedule): ?>
              <option 
                value="<?= (int)$schedule['ID_LICHHEN'] ?>"
                data-customer="<?= htmlspecialchars($schedule['TEN_KHACH_HANG'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"
                data-phone="<?= htmlspecialchars($schedule['SDT_KHACH_HANG'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"
                data-service="<?= htmlspecialchars($schedule['TEN_DV'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"
                data-duration="<?= (int)($schedule['THOI_LUONG_PHUT'] ?? 60) ?>"
                data-address="<?= htmlspecialchars($schedule['DIA_CHI_HEN'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"
                data-start="<?= str_replace(' ', 'T', substr($schedule['THOI_GIAN_BAT_DAU'], 0, 16)) ?>">
                <?= htmlspecialchars($schedule['TEN_KHACH_HANG'] ?? '-', ENT_QUOTES, 'UTF-8') ?> - <?= substr($schedule['THOI_GIAN_BAT_DAU'], 0, 10) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Schedule Detail Display -->
        <div id="modalScheduleDetail" class="mb-6 hidden bg-gradient-to-br from-indigo-50 to-purple-50 rounded-lg p-4 border border-indigo-100">
          <h3 class="font-semibold text-gray-900 mb-3">Chi Tiết Lịch Hẹn</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div>
              <span class="text-gray-600">Khách hàng:</span>
              <p id="modalDetailCustomer" class="font-semibold text-gray-900">-</p>
            </div>
            <div>
              <span class="text-gray-600">Điện thoại:</span>
              <p id="modalDetailPhone" class="font-semibold text-gray-900">-</p>
            </div>
            <div>
              <span class="text-gray-600">Dịch vụ:</span>
              <p id="modalDetailService" class="font-semibold text-gray-900">-</p>
            </div>
            <div>
              <span class="text-gray-600">Thời lượng:</span>
              <p id="modalDetailDuration" class="font-semibold text-gray-900">-</p>
            </div>
            <div class="md:col-span-2">
              <span class="text-gray-600">Địa chỉ:</span>
              <p id="modalDetailAddress" class="font-semibold text-gray-900">-</p>
            </div>
          </div>
        </div>

        <!-- Employee Selection -->
        <div class="mb-6">
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            <span class="text-red-500">*</span> Chọn Nhân Viên
          </label>
          <select id="modalEmployeeSelect" name="employee_id" class="w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 placeholder-gray-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200" required>
            <option value="">-- Chọn nhân viên --</option>
            <?php foreach ($employees as $emp): ?>
              <option value="<?= htmlspecialchars($emp['ID_TK'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($emp['HO_TEN'], ENT_QUOTES, 'UTF-8') ?> (<?= (int)($emp['CA_TRONG_NGAY'] ?? 0) ?> ca/ngày)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Time Selection Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <span class="text-red-500">*</span> Bắt Đầu
            </label>
            <input 
              type="datetime-local" 
              id="modalStartTime"
              name="start_time" 
              class="w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200" 
              required>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <span class="text-red-500">*</span> Kết Thúc
            </label>
            <input 
              type="datetime-local" 
              id="modalEndTime"
              name="end_time" 
              class="w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200" 
              required>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="flex gap-3 pt-4 border-t border-gray-200">
          <button 
            type="submit"
            class="flex-1 rounded-lg bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-3 font-semibold text-white hover:shadow-lg hover:from-indigo-700 hover:to-purple-700 transition transform hover:scale-105">
            Thêm Phân Công
          </button>
          <button 
            type="button"
            onclick="closeAddAssignmentModal()"
            class="flex-1 rounded-lg border border-gray-300 px-6 py-3 font-semibold text-gray-700 hover:bg-gray-50 transition">
            Đóng
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // ===== VALIDATION FUNCTION =====
  function validateAndConfirmAssignment(event) {
    const scheduleSelect = document.getElementById('modalScheduleSelect');
    const employeeSelect = document.getElementById('modalEmployeeSelect');
    const startTimeInput = document.getElementById('modalStartTime');
    const endTimeInput = document.getElementById('modalEndTime');

    // Validate all required fields are filled
    if (!scheduleSelect.value) {
      alert('⚠️ Vui lòng chọn lịch hẹn');
      scheduleSelect.focus();
      return false;
    }
    if (!employeeSelect.value) {
      alert('⚠️ Vui lòng chọn nhân viên');
      employeeSelect.focus();
      return false;
    }
    if (!startTimeInput.value) {
      alert('⚠️ Vui lòng nhập thời gian bắt đầu');
      startTimeInput.focus();
      return false;
    }
    if (!endTimeInput.value) {
      alert('⚠️ Vui lòng nhập thời gian kết thúc');
      endTimeInput.focus();
      return false;
    }

    // Validate time constraint: end time must be after start time
    const startDate = new Date(startTimeInput.value);
    const endDate = new Date(endTimeInput.value);
    
    if (endDate <= startDate) {
      alert('⚠️ Thời gian kết thúc phải sau thời gian bắt đầu');
      endTimeInput.focus();
      return false;
    }

    // Validate time difference is reasonable (not too long, e.g., max 8 hours)
    const timeDiffMs = endDate - startDate;
    const timeDiffHours = timeDiffMs / (1000 * 60 * 60);
    
    if (timeDiffHours > 8) {
      alert('⚠️ Thời gian phân công quá dài (tối đa 8 giờ)');
      return false;
    }

    // Validate end time is not in the past
    const now = new Date();
    if (endDate < now) {
      alert('⚠️ Không thể phân công cho thời gian đã qua');
      return false;
    }

    // Final confirmation
    const schedule = scheduleSelect.options[scheduleSelect.selectedIndex];
    const customer = schedule.getAttribute('data-customer');
    const service = schedule.getAttribute('data-service');
    const employee = employeeSelect.options[employeeSelect.selectedIndex];

    const message = `Xác nhận thêm phân công:\n\n` +
      `Khách hàng: ${customer}\n` +
      `Dịch vụ: ${service}\n` +
      `Nhân viên: ${employee.text}\n` +
      `Thời gian: ${new Date(startDate).toLocaleString('vi-VN')}\n` +
      `Đến: ${new Date(endDate).toLocaleString('vi-VN')}\n\n` +
      `Bạn chắc chắn muốn thêm phân công này?`;

    return confirm(message);
  }

  // Toggle edit form
  document.querySelectorAll('[data-toggle-edit]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = document.getElementById(btn.dataset.toggleEdit);
      if (target) {
        target.classList.toggle('hidden');
      }
    });
  });

  // Request toggle
  const requestToggle = document.getElementById('requestToggle');
  const requestPanel = document.getElementById('requestPanel');
  if (requestToggle && requestPanel) {
    requestToggle.addEventListener('click', () => {
      requestPanel.classList.toggle('hidden');
      requestPanel.classList.toggle('opacity-0');
      requestPanel.classList.toggle('-translate-y-2');
    });
  }

  // Modal control functions
  function openAddAssignmentModal() {
    document.getElementById('addAssignmentModal').style.display = 'flex';
  }

  function closeAddAssignmentModal() {
    document.getElementById('addAssignmentModal').style.display = 'none';
    resetModalForm();
  }

  // Reset modal form
  function resetModalForm() {
    document.getElementById('modalScheduleSelect').value = '';
    document.getElementById('modalEmployeeSelect').value = '';
    document.getElementById('modalStartTime').value = '';
    document.getElementById('modalEndTime').value = '';
    document.getElementById('modalScheduleDetail').classList.add('hidden');
  }

  // Close modal on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const modal = document.getElementById('addAssignmentModal');
      if (modal && modal.style.display !== 'none') {
        closeAddAssignmentModal();
      }
    }
  });

  // Close modal on backdrop click
  document.getElementById('addAssignmentModal').addEventListener('click', (e) => {
    if (e.target.id === 'addAssignmentModal') {
      closeAddAssignmentModal();
    }
  });

  // Enhanced schedule selection with detail display and auto-fill
  const modalScheduleSelect = document.getElementById('modalScheduleSelect');
  if (modalScheduleSelect) {
    modalScheduleSelect.addEventListener('change', () => {
      const option = modalScheduleSelect.options[modalScheduleSelect.selectedIndex];
      const detailDiv = document.getElementById('modalScheduleDetail');
      
      if (option && option.value) {
        // Hiển thị chi tiết lịch hẹn
        document.getElementById('modalDetailCustomer').textContent = option.getAttribute('data-customer') || '-';
        document.getElementById('modalDetailPhone').textContent = option.getAttribute('data-phone') || '-';
        document.getElementById('modalDetailService').textContent = option.getAttribute('data-service') || '-';
        document.getElementById('modalDetailDuration').textContent = option.getAttribute('data-duration') || '60';
        document.getElementById('modalDetailAddress').textContent = option.getAttribute('data-address') || '-';
        detailDiv.classList.remove('hidden');
        
        // Auto-fill thời gian
        const start = option.getAttribute('data-start');
        const duration = parseInt(option.getAttribute('data-duration')) || 60;
        
        if (start) {
          const startField = document.getElementById('modalStartTime');
          const endField = document.getElementById('modalEndTime');
          startField.value = start;
          
          // Tính thời gian kết thúc dựa trên thời lượng dịch vụ
          const startDate = new Date(start);
          const endDate = new Date(startDate.getTime() + duration * 60000);
          
          // Format thành datetime-local string (YYYY-MM-DDTHH:mm)
          const year = endDate.getFullYear();
          const month = String(endDate.getMonth() + 1).padStart(2, '0');
          const day = String(endDate.getDate()).padStart(2, '0');
          const hours = String(endDate.getHours()).padStart(2, '0');
          const minutes = String(endDate.getMinutes()).padStart(2, '0');
          const endStr = `${year}-${month}-${day}T${hours}:${minutes}`;
          
          endField.value = endStr;
        }
      } else {
        detailDiv.classList.add('hidden');
      }
    });
  }

  // Toast notification (nếu có flash message)
  <?php if (!empty($flash)): ?>
  setTimeout(() => {
    const flashDiv = document.querySelector('.rounded-xl.border.px-4.py-3.shadow');
    if (flashDiv) {
      flashDiv.style.transition = 'opacity 0.5s, transform 0.5s';
      flashDiv.style.opacity = '0';
      flashDiv.style.transform = 'translateY(-10px)';
      setTimeout(() => flashDiv.remove(), 500);
    }
  }, 5000);
  <?php endif; ?>
</script>

<style>
  @keyframes fade-in {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .animate-fade-in {
    animation: fade-in 0.3s ease-out both;
  }
  
  /* Highlight nhân viên theo workload */
  #employeeSelect option:nth-child(-n+3) {
    font-weight: 600;
  }
  
  /* Smooth transitions */
  button, select, input {
    transition: all 0.2s ease;
  }
  
  button:hover {
    transform: translateY(-1px);
  }
  
  button:active {
    transform: translateY(0);
  }
</style>
