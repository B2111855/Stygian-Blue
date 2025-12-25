<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../database/config.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

$staffId = $_SESSION['ID_TK'] ?? null;
if (!$staffId) {
    echo '<div class="text-center text-red-600 font-semibold">Không xác định được tài khoản nhân viên.</div>';
    return;
}

$timezone = new DateTimeZone('Asia/Ho_Chi_Minh');

if (!function_exists('sbStaffFormatDateTime')) {
    function sbStaffFormatDateTime(?string $value, DateTimeZone $tz, string $pattern = 'H:i d/m')
    {
        if (!$value) {
            return '—';
        }

        try {
            $date = new DateTime($value, $tz);
        } catch (Exception $e) {
            return '—';
        }

        return $date->setTimezone($tz)->format($pattern);
    }
}

$staffInfo = [
    'name'      => $_SESSION['HO_TEN'] ?? 'Nhân viên chuyên trách',
    'specialty' => null,
];

if ($stmt = $conn->prepare('SELECT CHUYEN_MON FROM nhan_vien WHERE ID_TK = ? LIMIT 1')) {
    $stmt->bind_param('s', $staffId);
    if ($stmt->execute()) {
        $stmt->bind_result($specialty);
        if ($stmt->fetch()) {
            if (!empty($specialty)) {
                $staffInfo['specialty'] = $specialty;
            }
        }
    }
    $stmt->close();
}

$metrics = [
    'upcomingAssignments' => 0,
    'completedThisMonth'  => 0,
    'pendingRequests'     => 0,
    'upcomingShifts'      => 0,
    'averageRating'       => null,
    'totalFeedback'       => 0,
];

if ($stmt = $conn->prepare(
    'SELECT COUNT(*) AS total FROM phan_cong_nhan_vien pc
     INNER JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
     WHERE pc.ID_TK = ?
       AND lh.THOI_GIAN_BAT_DAU >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
       AND lh.THOI_GIAN_BAT_DAU < DATE_ADD(NOW(), INTERVAL 7 DAY)
       AND (lh.TRANGTHAI IS NULL OR lh.TRANGTHAI NOT IN (\'Đã hủy\'))'
)) {
    $stmt->bind_param('s', $staffId);
    if ($stmt->execute()) {
        $stmt->bind_result($count);
        if ($stmt->fetch()) {
            $metrics['upcomingAssignments'] = (int) $count;
        }
    }
    $stmt->close();
}

if ($stmt = $conn->prepare(
    'SELECT COUNT(*) AS total FROM phan_cong_nhan_vien pc
     INNER JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
     WHERE pc.ID_TK = ?
       AND lh.TRANGTHAI = \'Đã hoàn thành\'
       AND MONTH(lh.THOI_GIAN_BAT_DAU) = MONTH(CURDATE())
       AND YEAR(lh.THOI_GIAN_BAT_DAU) = YEAR(CURDATE())'
)) {
    $stmt->bind_param('s', $staffId);
    if ($stmt->execute()) {
        $stmt->bind_result($count);
        if ($stmt->fetch()) {
            $metrics['completedThisMonth'] = (int) $count;
        }
    }
    $stmt->close();
}

if ($stmt = $conn->prepare(
    'SELECT COUNT(*) AS total FROM yeu_cau_thay_doi_lich WHERE ID_TK = ? AND TRANGTHAI = \'Chờ duyệt\''
)) {
    $stmt->bind_param('s', $staffId);
    if ($stmt->execute()) {
        $stmt->bind_result($count);
        if ($stmt->fetch()) {
            $metrics['pendingRequests'] = (int) $count;
        }
    }
    $stmt->close();
}

if ($stmt = $conn->prepare(
    'SELECT COUNT(*) AS total FROM lich_lam_viec_nhan_vien
     WHERE ID_TK_NV = ?
       AND START_AT >= CURDATE()
       AND START_AT < DATE_ADD(CURDATE(), INTERVAL 7 DAY)'
)) {
    $stmt->bind_param('s', $staffId);
    if ($stmt->execute()) {
        $stmt->bind_result($count);
        if ($stmt->fetch()) {
            $metrics['upcomingShifts'] = (int) $count;
        }
    }
    $stmt->close();
}

if ($stmt = $conn->prepare(
    'SELECT AVG(ph.XEP_HANG_DV) AS average_rating, COUNT(*) AS total_feedback
     FROM phan_hoi_cua_khach_hang ph
     INNER JOIN lich_hen lh ON ph.ID_TK = lh.ID_TK AND ph.ID_DV = lh.ID_DV
     INNER JOIN phan_cong_nhan_vien pc ON pc.ID_LICHHEN = lh.ID_LICHHEN
     WHERE pc.ID_TK = ?'
)) {
    $stmt->bind_param('s', $staffId);
    if ($stmt->execute()) {
        $stmt->bind_result($averageRating, $totalFeedback);
        if ($stmt->fetch()) {
            $metrics['averageRating'] = $averageRating !== null ? round((float) $averageRating, 1) : null;
            $metrics['totalFeedback'] = (int) $totalFeedback;
        }
    }
    $stmt->close();
}

$upcomingAppointments = [];
if ($stmt = $conn->prepare(
    'SELECT lh.ID_LICHHEN, dv.TEN_DV, lh.THOI_GIAN_BAT_DAU,
            COALESCE(pc.THOI_GIAN_KET_THUC, DATE_ADD(lh.THOI_GIAN_BAT_DAU, INTERVAL dv.THOI_GIAN MINUTE)) AS THOI_GIAN_KET_THUC,
            kh.HO_TEN AS TEN_KHACH_HANG, lh.DIA_CHI_HEN, lh.TRANGTHAI
     FROM phan_cong_nhan_vien pc
     INNER JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
     INNER JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
     INNER JOIN khach_hang kh ON lh.ID_TK = kh.ID_TK
     WHERE pc.ID_TK = ?
       AND lh.THOI_GIAN_BAT_DAU >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
       AND lh.THOI_GIAN_BAT_DAU < DATE_ADD(NOW(), INTERVAL 14 DAY)
       AND (lh.TRANGTHAI IS NULL OR lh.TRANGTHAI NOT IN (\'Đã hủy\'))
     ORDER BY lh.THOI_GIAN_BAT_DAU ASC
     LIMIT 4'
)) {
    $stmt->bind_param('s', $staffId);
    if ($stmt->execute()) {
        if ($result = $stmt->get_result()) {
            $upcomingAppointments = $result->fetch_all(MYSQLI_ASSOC);
        }
    }
    $stmt->close();
}

$workShifts = [];
if ($stmt = $conn->prepare(
    'SELECT ID_LLV, START_AT, END_AT, LOAI, GHI_CHU
     FROM lich_lam_viec_nhan_vien
     WHERE ID_TK_NV = ?
       AND START_AT >= DATE_SUB(NOW(), INTERVAL 12 HOUR)
     ORDER BY START_AT ASC
     LIMIT 5'
)) {
    $stmt->bind_param('s', $staffId);
    if ($stmt->execute()) {
        if ($result = $stmt->get_result()) {
            $workShifts = $result->fetch_all(MYSQLI_ASSOC);
        }
    }
    $stmt->close();
}

$changeRequests = [];
if ($stmt = $conn->prepare(
    'SELECT ID_LICHHEN, NOI_DUNG, TRANGTHAI, NGAY_GUI
     FROM yeu_cau_thay_doi_lich
     WHERE ID_TK = ?
     ORDER BY NGAY_GUI DESC
     LIMIT 5'
)) {
    $stmt->bind_param('s', $staffId);
    if ($stmt->execute()) {
        if ($result = $stmt->get_result()) {
            $changeRequests = $result->fetch_all(MYSQLI_ASSOC);
        }
    }
    $stmt->close();
}

$recentCompleted = [];
if ($stmt = $conn->prepare(
    'SELECT lh.ID_LICHHEN, dv.TEN_DV, lh.THOI_GIAN_BAT_DAU, lh.DIA_CHI_HEN
     FROM phan_cong_nhan_vien pc
     INNER JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
     INNER JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
     WHERE pc.ID_TK = ?
       AND lh.TRANGTHAI = \'Đã hoàn thành\'
     ORDER BY lh.THOI_GIAN_BAT_DAU DESC
     LIMIT 4'
)) {
    $stmt->bind_param('s', $staffId);
    if ($stmt->execute()) {
        if ($result = $stmt->get_result()) {
            $recentCompleted = $result->fetch_all(MYSQLI_ASSOC);
        }
    }
    $stmt->close();
}

$hour = (int) date('G');
if ($hour < 12) {
  $greeting = 'Chào buổi sáng';
} elseif ($hour < 18) {
  $greeting = 'Chào buổi chiều';
} else {
  $greeting = 'Chào buổi tối';
}

$todayLabel = sbStaffFormatDateTime(date('Y-m-d H:i:s'), $timezone, 'l, d/m/Y');
?>

<div class="space-y-8">
  <section class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
    <div>
      <p class="text-sm font-medium text-indigo-500 uppercase tracking-wide"><?= htmlspecialchars($greeting) ?></p>
      <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($staffInfo['name']) ?></h1>
      <?php if (!empty($staffInfo['specialty'])): ?>
        <p class="text-gray-500">Chuyên môn: <?= htmlspecialchars($staffInfo['specialty']) ?></p>
      <?php endif; ?>
    </div>
    <div class="text-sm text-gray-500">
      <span class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 font-medium text-indigo-700"><i class="fas fa-calendar-day"></i><?= htmlspecialchars($todayLabel) ?></span>
    </div>
  </section>

  <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
    <article class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-500 uppercase">Lịch hẹn sắp tới</h2>
        <span class="text-indigo-500"><i class="fas fa-calendar-check text-xl"></i></span>
      </div>
      <p class="mt-4 text-3xl font-bold text-gray-900"><?= number_format($metrics['upcomingAssignments']) ?></p>
      <p class="mt-1 text-sm text-gray-500">Trong vòng 7 ngày tới</p>
    </article>

    <article class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-500 uppercase">Hoàn thành tháng này</h2>
        <span class="text-emerald-500"><i class="fas fa-check-circle text-xl"></i></span>
      </div>
      <p class="mt-4 text-3xl font-bold text-gray-900"><?= number_format($metrics['completedThisMonth']) ?></p>
      <p class="mt-1 text-sm text-gray-500">Tính đến thời điểm hiện tại</p>
    </article>

    <article class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-500 uppercase">Yêu cầu chờ duyệt</h2>
        <span class="text-amber-500"><i class="fas fa-hourglass-half text-xl"></i></span>
      </div>
      <p class="mt-4 text-3xl font-bold text-gray-900"><?= number_format($metrics['pendingRequests']) ?></p>
      <p class="mt-1 text-sm text-gray-500">Nhắc nhở theo dõi phản hồi</p>
    </article>

    <article class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-500 uppercase">Ca trực 7 ngày tới</h2>
        <span class="text-sky-500"><i class="fas fa-briefcase-clock text-xl"></i></span>
      </div>
      <p class="mt-4 text-3xl font-bold text-gray-900"><?= number_format($metrics['upcomingShifts']) ?></p>
      <p class="mt-1 text-sm text-gray-500">Bao gồm ca làm và đào tạo</p>
    </article>
  </section>

  <section class="grid gap-6 md:grid-cols-2">
    <article class="rounded-2xl border border-fuchsia-100 bg-white p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-500 uppercase">Chỉ số hài lòng</h2>
        <span class="text-fuchsia-500"><i class="fas fa-star-half-alt text-xl"></i></span>
      </div>
      <?php if ($metrics['averageRating'] !== null): ?>
        <div class="mt-4 flex items-end gap-4">
          <p class="text-4xl font-bold text-gray-900"><?= htmlspecialchars(number_format((float) $metrics['averageRating'], 1)) ?><span class="text-2xl text-gray-400">/5</span></p>
          <p class="text-sm text-gray-500">Dựa trên <?= number_format($metrics['totalFeedback']) ?> phản hồi</p>
        </div>
      <?php else: ?>
        <p class="mt-4 text-lg font-semibold text-gray-700">Chưa có phản hồi đánh giá</p>
        <p class="text-sm text-gray-500">Hãy tiếp tục tạo ấn tượng tốt với khách hàng nhé!</p>
      <?php endif; ?>
    </article>

    <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-500 uppercase">Ghi chú nhanh</h2>
        <span class="text-slate-500"><i class="fas fa-sticky-note text-xl"></i></span>
      </div>
      <ul class="mt-4 space-y-3 text-sm text-gray-600">
        <li class="flex items-start gap-3"><i class="fas fa-circle text-xs text-indigo-400 pt-1"></i><span>Xem lại các yêu cầu thay đổi lịch trước khi ca làm bắt đầu.</span></li>
        <li class="flex items-start gap-3"><i class="fas fa-circle text-xs text-indigo-400 pt-1"></i><span>Chủ động cập nhật tiến độ ngay sau khi hoàn thành lịch hẹn.</span></li>
        <li class="flex items-start gap-3"><i class="fas fa-circle text-xs text-indigo-400 pt-1"></i><span>Kiểm tra thông tin khách hàng trước khi liên hệ để đảm bảo trải nghiệm tốt nhất.</span></li>
      </ul>
    </article>
  </section>

  <section class="grid gap-6 lg:grid-cols-3">
    <article class="lg:col-span-2 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-800">Lịch hẹn sắp diễn ra</h2>
        <a href="?page=staff" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">Xem tất cả</a>
      </div>
      <div class="mt-4 space-y-4">
        <?php if (empty($upcomingAppointments)): ?>
          <p class="text-sm text-gray-500">Bạn chưa có lịch hẹn nào trong thời gian tới.</p>
        <?php else: ?>
          <?php foreach ($upcomingAppointments as $appointment): ?>
            <div class="rounded-xl border border-indigo-50 bg-indigo-50/40 p-4">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <h3 class="text-base font-semibold text-gray-900"><?= htmlspecialchars($appointment['TEN_DV']) ?></h3>
                  <p class="text-sm text-gray-500">Khách hàng: <?= htmlspecialchars($appointment['TEN_KHACH_HANG']) ?></p>
                </div>
                <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-indigo-600">#<?= htmlspecialchars($appointment['ID_LICHHEN']) ?></span>
              </div>
              <div class="mt-3 grid gap-3 text-sm text-gray-600 md:grid-cols-3">
                <div class="flex items-center gap-2"><i class="fas fa-clock text-indigo-500"></i><span><?= htmlspecialchars(sbStaffFormatDateTime($appointment['THOI_GIAN_BAT_DAU'], $timezone, 'H:i d/m')) ?> - <?= htmlspecialchars(sbStaffFormatDateTime($appointment['THOI_GIAN_KET_THUC'], $timezone, 'H:i d/m')) ?></span></div>
                <div class="flex items-center gap-2"><i class="fas fa-map-marker-alt text-indigo-500"></i><span><?= htmlspecialchars($appointment['DIA_CHI_HEN'] ?: 'Đang cập nhật') ?></span></div>
                <div class="flex items-center gap-2"><i class="fas fa-info-circle text-indigo-500"></i><span><?= htmlspecialchars($appointment['TRANGTHAI'] ?: 'Đang chờ') ?></span></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </article>

    <article class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-800">Ca trực & lịch làm việc</h2>
        <a href="?page=staff" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">Quản lý</a>
      </div>
      <ul class="mt-4 space-y-3 text-sm text-gray-600">
        <?php if (empty($workShifts)): ?>
          <li class="rounded-lg bg-slate-50 p-3 text-slate-500">Chưa có ca làm nào được sắp tới.</li>
        <?php else: ?>
          <?php foreach ($workShifts as $shift): ?>
            <li class="rounded-lg border border-slate-100 bg-slate-50/60 p-3">
              <p class="font-semibold text-gray-800"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $shift['LOAI']))) ?></p>
              <p class="text-xs text-gray-500 mt-1 flex items-center gap-2"><i class="fas fa-clock text-slate-400"></i><?= htmlspecialchars(sbStaffFormatDateTime($shift['START_AT'], $timezone, 'H:i d/m')) ?> - <?= htmlspecialchars(sbStaffFormatDateTime($shift['END_AT'], $timezone, 'H:i d/m')) ?></p>
              <?php if (!empty($shift['GHI_CHU'])): ?>
                <p class="mt-1 text-xs text-gray-500 flex items-start gap-2"><i class="fas fa-sticky-note text-slate-400 pt-0.5"></i><span><?= htmlspecialchars($shift['GHI_CHU']) ?></span></p>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        <?php endif; ?>
      </ul>
    </article>
  </section>

  <section class="grid gap-6 lg:grid-cols-2">
    <article class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-800">Yêu cầu thay đổi lịch</h2>
        <a href="?page=staff" class="text-sm font-medium text-amber-600 hover:text-amber-700">Quản lý</a>
      </div>
      <ul class="mt-4 space-y-3 text-sm text-gray-600">
        <?php if (empty($changeRequests)): ?>
          <li class="rounded-lg bg-amber-50 p-3 text-amber-700">Chưa có yêu cầu nào.</li>
        <?php else: ?>
          <?php foreach ($changeRequests as $request): ?>
            <li class="rounded-lg border border-amber-100 bg-amber-50/60 p-3">
              <div class="flex items-center justify-between">
                <p class="font-semibold text-gray-800">Lịch hẹn #<?= htmlspecialchars($request['ID_LICHHEN']) ?></p>
                <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-amber-600"><?= htmlspecialchars($request['TRANGTHAI']) ?></span>
              </div>
              <p class="mt-2 text-sm text-gray-600"><?= htmlspecialchars($request['NOI_DUNG']) ?></p>
              <p class="mt-2 text-xs text-gray-500 flex items-center gap-2"><i class="fas fa-paper-plane text-amber-400"></i>Gửi lúc <?= htmlspecialchars(sbStaffFormatDateTime($request['NGAY_GUI'], $timezone, 'H:i d/m')) ?></p>
            </li>
          <?php endforeach; ?>
        <?php endif; ?>
      </ul>
    </article>

    <article class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-800">Đã hoàn thành gần đây</h2>
        <span class="text-emerald-500"><i class="fas fa-trophy"></i></span>
      </div>
      <ul class="mt-4 space-y-3 text-sm text-gray-600">
        <?php if (empty($recentCompleted)): ?>
          <li class="rounded-lg bg-emerald-50 p-3 text-emerald-700">Chưa có lịch hẹn nào hoàn thành gần đây.</li>
        <?php else: ?>
          <?php foreach ($recentCompleted as $completed): ?>
            <li class="rounded-lg border border-emerald-100 bg-emerald-50/60 p-3">
              <p class="font-semibold text-gray-800"><?= htmlspecialchars($completed['TEN_DV']) ?></p>
              <p class="text-xs text-gray-500 mt-1 flex items-center gap-2"><i class="fas fa-clock text-emerald-400"></i><?= htmlspecialchars(sbStaffFormatDateTime($completed['THOI_GIAN_BAT_DAU'], $timezone, 'H:i d/m')) ?></p>
              <p class="mt-1 text-xs text-gray-500 flex items-start gap-2"><i class="fas fa-map-marker-alt text-emerald-400 pt-0.5"></i><span><?= htmlspecialchars($completed['DIA_CHI_HEN'] ?: 'Địa điểm không xác định') ?></span></p>
            </li>
          <?php endforeach; ?>
        <?php endif; ?>
      </ul>
    </article>
  </section>

  <section>
    <h2 class="text-lg font-semibold text-gray-800">Lối tắt nhanh</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <a href="?page=staff" class="group flex items-center gap-3 rounded-xl border border-indigo-100 bg-white p-4 text-indigo-600 shadow-sm hover:border-indigo-200 hover:bg-indigo-50">
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600"><i class="fas fa-clipboard-list"></i></span>
        <div>
          <p class="font-semibold">Quản lý lịch làm</p>
          <p class="text-xs text-gray-500">Xem phân công chi tiết</p>
        </div>
      </a>
      <a href="?page=staff_appoinments" class="group flex items-center gap-3 rounded-xl border border-indigo-100 bg-white p-4 text-indigo-600 shadow-sm hover:border-indigo-200 hover:bg-indigo-50">
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600"><i class="fas fa-calendar-alt"></i></span>
        <div>
          <p class="font-semibold">Lịch hẹn của tôi</p>
          <p class="text-xs text-gray-500">Kiểm tra thông tin khách hàng</p>
        </div>
      </a>
      <a href="?page=feedback" class="group flex items-center gap-3 rounded-xl border border-indigo-100 bg-white p-4 text-indigo-600 shadow-sm hover:border-indigo-200 hover:bg-indigo-50">
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600"><i class="fas fa-comment-dots"></i></span>
        <div>
          <p class="font-semibold">Phản hồi khách hàng</p>
          <p class="text-xs text-gray-500">Theo dõi đánh giá, góp ý</p>
        </div>
      </a>
      <a href="?page=selfInfo" class="group flex items-center gap-3 rounded-xl border border-indigo-100 bg-white p-4 text-indigo-600 shadow-sm hover:border-indigo-200 hover:bg-indigo-50">
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600"><i class="fas fa-user-cog"></i></span>
        <div>
          <p class="font-semibold">Cập nhật hồ sơ</p>
          <p class="text-xs text-gray-500">Thông tin cá nhân & liên hệ</p>
        </div>
      </a>
    </div>
  </section>
</div>
