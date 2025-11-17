<?php
include '../../database/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$branchStmt = $conn->prepare('SELECT nv.ID_CN, cn.TEN_CN FROM nhan_vien nv JOIN chi_nhanh cn ON nv.ID_CN = cn.ID_CN WHERE nv.ID_TK = ? LIMIT 1');
$branchStmt->bind_param('s', $currentAccount);
$branchStmt->execute();
$branchStmt->bind_result($branchId, $branchName);
$branchStmt->fetch();
$branchStmt->close();

if (!$branchId) {
    echo '<div class="rounded-xl bg-white p-8 text-center text-red-600 shadow">Tài khoản của bạn chưa được gán vào chi nhánh nào. Hãy liên hệ quản trị viên.</div>';
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

        $checkStmt = $conn->prepare('SELECT 1 FROM yeu_cau_thay_doi_lich yc JOIN lich_hen lh ON yc.ID_LICHHEN = lh.ID_LICHHEN WHERE yc.ID_YEUCAU = ? AND lh.ID_CHINHANH = ? LIMIT 1');
        $checkStmt->bind_param('ii', $requestId, $branchId);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows === 0) {
            $checkStmt->close();
            redirectWithFlash('error', 'Không tìm thấy yêu cầu đổi lịch thuộc chi nhánh của bạn.');
        }
        $checkStmt->close();

        $updateStmt = $conn->prepare('UPDATE yeu_cau_thay_doi_lich SET TRANGTHAI = ? WHERE ID_YEUCAU = ?');
        if (!$updateStmt) {
            redirectWithFlash('error', 'Không thể cập nhật trạng thái yêu cầu.');
        }
        $updateStmt->bind_param('si', $decisionLabel, $requestId);
        $ok = $updateStmt->execute();
        $updateStmt->close();

        $msg = $decisionLabel === 'Đã duyệt' ? 'Đã duyệt yêu cầu đổi lịch.' : 'Đã từ chối yêu cầu đổi lịch.';
        redirectWithFlash($ok ? 'success' : 'error', $ok ? $msg : 'Không thể xử lý yêu cầu đổi lịch.');
    }
}

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
    'scope'    => $_GET['scope'] ?? 'upcoming',
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

$countSql = 'SELECT COUNT(*) ' . $fromClause . ' ' . $whereClause;
$countStmt = $conn->prepare($countSql);
bindParams($countStmt, $types, $params);
$countStmt->execute();
$countStmt->bind_result($totalRows);
$countStmt->fetch();
$countStmt->close();
$totalRows = (int)($totalRows ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$dataSql = 'SELECT pc.ID_LICHHEN, pc.ID_TK, pc.THOI_GIAN_BAT_DAU, pc.THOI_GIAN_KET_THUC, tk.HO_TEN AS STAFF_NAME, tk.SDT, tk.EMAIL, lh.DIA_CHI_HEN, lh.TRANGTHAI, dv.TEN_DV, hd.ID_HD ' . $fromClause . ' ' . $whereClause . ' ORDER BY pc.THOI_GIAN_BAT_DAU DESC LIMIT ? OFFSET ?';
$dataStmt = $conn->prepare($dataSql);
$dataTypes = $types . 'ii';
$dataParams = array_merge($params, [$perPage, $offset]);
bindParams($dataStmt, $dataTypes, $dataParams);
$dataStmt->execute();
$dataResult = $dataStmt->get_result();
$assignments = $dataResult ? $dataResult->fetch_all(MYSQLI_ASSOC) : [];
$dataStmt->close();

$employees = [];
$empStmt = $conn->prepare('SELECT tk.ID_TK, tk.HO_TEN, tk.SDT, nv.CHUYEN_MON FROM nhan_vien nv JOIN tai_khoan tk ON nv.ID_TK = tk.ID_TK WHERE nv.ID_CN = ? ORDER BY tk.HO_TEN ASC');
$empStmt->bind_param('i', $branchId);
$empStmt->execute();
$empRes = $empStmt->get_result();
if ($empRes) {
    $employees = $empRes->fetch_all(MYSQLI_ASSOC);
}
$empStmt->close();

$unassigned = [];
$unStmt = $conn->prepare("SELECT lh.ID_LICHHEN, lh.THOI_GIAN_BAT_DAU, lh.DIA_CHI_HEN, dv.TEN_DV FROM lich_hen lh LEFT JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV WHERE lh.ID_CHINHANH = ? AND lh.TRANGTHAI = 'Đã xác nhận' AND NOT EXISTS (SELECT 1 FROM phan_cong_nhan_vien pc WHERE pc.ID_LICHHEN = lh.ID_LICHHEN) ORDER BY lh.THOI_GIAN_BAT_DAU ASC LIMIT 10");
$unStmt->bind_param('i', $branchId);
$unStmt->execute();
$unRes = $unStmt->get_result();
if ($unRes) {
    $unassigned = $unRes->fetch_all(MYSQLI_ASSOC);
}
$unStmt->close();

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
    'unassigned' => singleValue($conn, "SELECT COUNT(*) FROM lich_hen lh WHERE lh.ID_CHINHANH = ? AND lh.TRANGTHAI = 'Đã xác nhận' AND NOT EXISTS (SELECT 1 FROM phan_cong_nhan_vien pc WHERE pc.ID_LICHHEN = lh.ID_LICHHEN)", 'i', [$branchId]),
];
?>

<div class="space-y-8">
  <header class="flex flex-col gap-2 rounded-2xl bg-white/90 p-6 shadow">
    <div class="text-sm font-semibold text-indigo-600">Chi nhánh phụ trách</div>
    <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($branchName, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="text-sm text-gray-600">Theo dõi ca/kíp, phân công nhân sự và xử lý yêu cầu đổi lịch ngay tại một nơi.</p>
  </header>

  <?php if (!empty($flash)): ?>
    <div class="rounded-xl border px-4 py-3 shadow <?php echo $flash['type'] === 'success' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-700'; ?>">
      <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <p class="text-xs font-semibold uppercase text-gray-500">Phân công hiện có</p>
      <p class="mt-2 text-3xl font-bold text-indigo-700"><?= number_format($stats['total']) ?></p>
      <p class="text-xs text-gray-500">Tổng ca thuộc chi nhánh</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <p class="text-xs font-semibold uppercase text-gray-500">Ca sắp diễn ra</p>
      <p class="mt-2 text-3xl font-bold text-emerald-700"><?= number_format($stats['upcoming']) ?></p>
      <p class="text-xs text-gray-500">Từ thời điểm hiện tại</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <p class="text-xs font-semibold uppercase text-gray-500">Trong 7 ngày tới</p>
      <p class="mt-2 text-3xl font-bold text-blue-700"><?= number_format($stats['week']) ?></p>
      <p class="text-xs text-gray-500">Giúp chủ động chuẩn bị</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <p class="text-xs font-semibold uppercase text-gray-500">Lịch chưa phân công</p>
      <p class="mt-2 text-3xl font-bold text-rose-700"><?= number_format($stats['unassigned']) ?></p>
      <p class="text-xs text-gray-500">Cần xử lý sớm</p>
    </div>
  </section>

  <div class="grid gap-6 lg:grid-cols-3">
    <section class="lg:col-span-2 space-y-6">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
          <div>
            <h2 class="text-xl font-semibold text-gray-900">Phân công nhanh</h2>
            <p class="text-sm text-gray-500">Chỉ hiển thị lịch đã xác nhận và nhân sự thuộc chi nhánh.</p>
          </div>
          <span class="text-xs font-semibold text-gray-500">Nhân sự: <?= number_format($stats['staff']) ?></span>
        </div>
        <?php if (empty($unassigned)): ?>
          <p class="text-sm text-gray-500">Hiện không có lịch nào chờ phân công.</p>
        <?php else: ?>
        <form method="POST" class="grid gap-4 md:grid-cols-2">
          <input type="hidden" name="action" value="create_assignment">
          <label class="text-sm font-medium text-gray-700">
            Lịch hẹn
            <select id="scheduleSelect" name="schedule_id" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
              <option value="">-- Chọn lịch --</option>
              <?php foreach ($unassigned as $schedule): ?>
                <option value="<?= (int)$schedule['ID_LICHHEN'] ?>" data-start="<?= htmlspecialchars(str_replace(' ', 'T', $schedule['THOI_GIAN_BAT_DAU']), ENT_QUOTES, 'UTF-8') ?>">
                  #<?= (int)$schedule['ID_LICHHEN'] ?> · <?= htmlspecialchars($schedule['TEN_DV'] ?? 'Chưa rõ', ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="text-sm font-medium text-gray-700">
            Nhân viên
            <select name="employee_id" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
              <option value="">-- Chọn nhân viên --</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?= htmlspecialchars($emp['ID_TK'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($emp['HO_TEN'] ?? $emp['ID_TK'], ENT_QUOTES, 'UTF-8') ?><?= $emp['CHUYEN_MON'] ? ' · ' . htmlspecialchars($emp['CHUYEN_MON'], ENT_QUOTES, 'UTF-8') : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="text-sm font-medium text-gray-700">
            Bắt đầu
            <input id="assignStart" type="datetime-local" name="start_time" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
          </label>
          <label class="text-sm font-medium text-gray-700">
            Kết thúc
            <input id="assignEnd" type="datetime-local" name="end_time" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
          </label>
          <div class="md:col-span-2 flex justify-end gap-3">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700">Lưu phân công</button>
          </div>
        </form>
        <?php endif; ?>
      </div>

      <form method="GET" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <input type="hidden" name="page" value="assignments">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-xl font-semibold text-gray-900">Bộ lọc phân công</h2>
          <a href="?page=assignments" class="text-sm text-indigo-600 hover:underline">Đặt lại</a>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
          <label class="text-sm font-medium text-gray-700">
            Nhân viên
            <input type="text" name="staff" value="<?= htmlspecialchars($filters['staff'], ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Nhập tên">
          </label>
          <label class="text-sm font-medium text-gray-700">
            Dịch vụ
            <input type="text" name="service" value="<?= htmlspecialchars($filters['service'], ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Tên dịch vụ">
          </label>
          <label class="text-sm font-medium text-gray-700">
            Trạng thái lịch
            <select name="status" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
              <option value="">Tất cả</option>
              <?php foreach ($statusOptions as $status): ?>
                <option value="<?= $status ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="text-sm font-medium text-gray-700">
            Phạm vi thời gian
            <select name="scope" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
              <option value="upcoming" <?= $filters['scope'] === 'upcoming' ? 'selected' : '' ?>>Từ hiện tại</option>
              <option value="seven" <?= $filters['scope'] === 'seven' ? 'selected' : '' ?>>Trong 7 ngày</option>
              <option value="today" <?= $filters['scope'] === 'today' ? 'selected' : '' ?>>Trong ngày</option>
              <option value="past" <?= $filters['scope'] === 'past' ? 'selected' : '' ?>>Đã diễn ra</option>
              <option value="all" <?= $filters['scope'] === 'all' ? 'selected' : '' ?>>Tất cả</option>
            </select>
          </label>
          <label class="text-sm font-medium text-gray-700">
            Từ ngày
            <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
          </label>
          <label class="text-sm font-medium text-gray-700">
            Đến ngày
            <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
          </label>
        </div>
        <div class="mt-4 flex justify-end">
          <button type="submit" class="rounded-lg bg-slate-900 px-6 py-2 text-sm font-semibold text-white hover:bg-slate-800">Áp dụng</button>
        </div>
      </form>
    </section>

    <aside class="space-y-6">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-xl font-semibold text-gray-900">Lịch chờ phân công</h2>
        <p class="text-xs text-gray-500">Tự động lọc theo chi nhánh.</p>
        <div class="mt-4 space-y-3 max-h-80 overflow-y-auto pr-2">
          <?php if (empty($unassigned)): ?>
            <p class="text-sm text-gray-500">Tuyệt vời! Không còn lịch chờ.</p>
          <?php else: ?>
            <?php foreach ($unassigned as $slot): ?>
              <div class="rounded-lg border border-slate-100 bg-slate-50 p-3 text-sm">
                <div class="font-semibold text-gray-800">#<?= (int)$slot['ID_LICHHEN'] ?> · <?= htmlspecialchars($slot['TEN_DV'] ?? 'Chưa rõ', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="text-gray-600"><?= humanDateTime($slot['THOI_GIAN_BAT_DAU'] ?? '') ?></div>
                <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($slot['DIA_CHI_HEN'] ?? 'Chưa có địa chỉ', ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-xl font-semibold text-gray-900">Yêu cầu đổi lịch</h2>
        <p class="text-xs text-gray-500">Duyệt từ nhân sự trong chi nhánh.</p>
        <div class="mt-4 space-y-4 max-h-80 overflow-y-auto pr-2">
          <?php if (empty($requests)): ?>
            <p class="text-sm text-gray-500">Chưa có yêu cầu nào.</p>
          <?php else: ?>
            <?php foreach ($requests as $req): ?>
              <div class="rounded-lg border border-amber-100 bg-amber-50 p-3 text-sm">
                <div class="font-semibold text-gray-800">#<?= (int)$req['ID_LICHHEN'] ?> · <?= htmlspecialchars($req['STAFF_NAME'] ?? $req['STAFF_ID'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="text-xs text-gray-500">Gửi lúc <?= humanDateTime($req['NGAY_GUI'] ?? '') ?></div>
                <p class="mt-2 text-gray-700 line-clamp-3"><?= nl2br(htmlspecialchars($req['NOI_DUNG'] ?? 'Không có nội dung', ENT_QUOTES, 'UTF-8')) ?></p>
                <form method="POST" class="mt-3 flex gap-2">
                  <input type="hidden" name="action" value="request_decision">
                  <input type="hidden" name="request_id" value="<?= (int)$req['ID_YEUCAU'] ?>">
                  <button name="decision" value="approve" class="flex-1 rounded-lg bg-emerald-600 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-700">Duyệt</button>
                  <button name="decision" value="reject" class="flex-1 rounded-lg bg-rose-600 px-3 py-1 text-xs font-semibold text-white hover:bg-rose-700">Từ chối</button>
                </form>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </aside>
  </div>

  <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
      <div>
        <h2 class="text-xl font-semibold text-gray-900">Danh sách phân công</h2>
        <p class="text-xs text-gray-500">Hiển thị <?= count($assignments) ?> / <?= $totalRows ?> kết quả</p>
      </div>
    </div>

    <?php if (empty($assignments)): ?>
      <p class="px-6 py-10 text-center text-sm text-gray-500">Không có phân công phù hợp bộ lọc.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full table-auto text-left text-sm">
          <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
            <tr>
              <th class="px-6 py-3">Nhân viên</th>
              <th class="px-6 py-3">Dịch vụ</th>
              <th class="px-6 py-3">Thời gian</th>
              <th class="px-6 py-3">Địa điểm</th>
              <th class="px-6 py-3">Trạng thái</th>
              <th class="px-6 py-3 text-center">Thao tác</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($assignments as $row): ?>
              <?php $formId = 'edit-' . $row['ID_LICHHEN'] . '-' . $row['ID_TK']; ?>
              <tr class="hover:bg-slate-50">
                <td class="px-6 py-4">
                  <div class="font-semibold text-gray-900"><?= htmlspecialchars($row['STAFF_NAME'] ?? $row['ID_TK'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="text-xs text-gray-500">Mã NV: <?= htmlspecialchars($row['ID_TK'], ENT_QUOTES, 'UTF-8') ?></div>
                </td>
                <td class="px-6 py-4">
                  <div class="text-gray-800"><?= htmlspecialchars($row['TEN_DV'] ?? 'Chưa rõ', ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="text-xs text-gray-500">Hóa đơn: <?= $row['ID_HD'] ? '#' . (int)$row['ID_HD'] : '—' ?></div>
                </td>
                <td class="px-6 py-4 text-gray-800">
                  <div><?= humanDateTime($row['THOI_GIAN_BAT_DAU'] ?? '') ?></div>
                  <div class="text-xs text-gray-500">→ <?= humanDateTime($row['THOI_GIAN_KET_THUC'] ?? '') ?></div>
                </td>
                <td class="px-6 py-4">
                  <div class="text-gray-800"><?= htmlspecialchars($row['DIA_CHI_HEN'] ?? 'Chưa cập nhật', ENT_QUOTES, 'UTF-8') ?></div>
                </td>
                <td class="px-6 py-4">
                  <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600"><?= htmlspecialchars($row['TRANGTHAI'] ?? 'Không rõ', ENT_QUOTES, 'UTF-8') ?></span>
                </td>
                <td class="px-6 py-4 text-center text-sm font-semibold text-indigo-600">
                  <div class="flex flex-col gap-2">
                    <button type="button" data-toggle-edit="<?= $formId ?>" class="rounded-lg border border-indigo-200 px-3 py-1 text-indigo-600 hover:bg-indigo-50">Chỉnh giờ</button>
                    <form method="POST" onsubmit="return confirm('Bạn chắc muốn xóa phân công này?')">
                      <input type="hidden" name="action" value="delete_assignment">
                      <input type="hidden" name="schedule_id" value="<?= (int)$row['ID_LICHHEN'] ?>">
                      <input type="hidden" name="employee_id" value="<?= htmlspecialchars($row['ID_TK'], ENT_QUOTES, 'UTF-8') ?>">
                      <button type="submit" class="rounded-lg border border-rose-200 px-3 py-1 text-rose-600 hover:bg-rose-50">Hủy</button>
                    </form>
                  </div>
                </td>
              </tr>
              <tr id="<?= $formId ?>" class="hidden bg-slate-50/70">
                <td colspan="6" class="px-6 py-4">
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
    <?php endif; ?>
  </section>
</div>

<script>
  document.querySelectorAll('[data-toggle-edit]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = document.getElementById(btn.dataset.toggleEdit);
      if (target) {
        target.classList.toggle('hidden');
      }
    });
  });

  const scheduleSelect = document.getElementById('scheduleSelect');
  if (scheduleSelect) {
    scheduleSelect.addEventListener('change', () => {
      const option = scheduleSelect.options[scheduleSelect.selectedIndex];
      const start = option ? option.getAttribute('data-start') : '';
      if (start) {
        const startField = document.getElementById('assignStart');
        const endField = document.getElementById('assignEnd');
        startField.value = start;
        endField.value = start;
      }
    });
  }
</script>
