<?php
include '../../database/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function fetchScalar(string $sql, float $default = 0): float
{
    global $conn;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        error_log('[admin_overview] SQL scalar error: ' . mysqli_error($conn) . ' | ' . $sql);
        return $default;
    }
    $row = mysqli_fetch_row($result);
    mysqli_free_result($result);
    return ($row && $row[0] !== null) ? (float)$row[0] : $default;
}

function fetchRows(string $sql): array
{
    global $conn;
    $rows = [];
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        error_log('[admin_overview] SQL rows error: ' . mysqli_error($conn) . ' | ' . $sql);
        return $rows;
    }
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_free_result($result);
    return $rows;
}

function calcPercentChange(float $current, float $previous): ?float
{
    if ($previous <= 0) {
        return null;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

function formatCurrency(float $value): string
{
    return number_format($value, 0, ',', '.');
}

function formatChange(?float $value): string
{
    if ($value === null) {
        return 'N/A';
    }
    $prefix = $value > 0 ? '+' : '';
    return $prefix . $value . '%';
}

function growthBadgeClass(?float $value): string
{
    if ($value === null) {
        return 'bg-gray-100 text-gray-600';
    }
    return $value >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700';
}

$adminName = $_SESSION['HO_TEN'] ?? ($_SESSION['ID_TK'] ?? 'Quản trị viên');
$now = new DateTime('now');

$currentMonthRevenue = fetchScalar("SELECT COALESCE(SUM(TONG_TIEN), 0) FROM hoa_don WHERE TRANGTHAI_THANHTOAN = 'Đã thanh toán' AND YEAR(NGAY_GIO) = YEAR(CURDATE()) AND MONTH(NGAY_GIO) = MONTH(CURDATE())");
$previousMonthRevenue = fetchScalar("SELECT COALESCE(SUM(TONG_TIEN), 0) FROM hoa_don WHERE TRANGTHAI_THANHTOAN = 'Đã thanh toán' AND YEAR(NGAY_GIO) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(NGAY_GIO) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
$revenueGrowth = calcPercentChange($currentMonthRevenue, $previousMonthRevenue);

$bookingsThisWeek = fetchScalar("SELECT COUNT(*) FROM lich_hen WHERE YEARWEEK(THOI_GIAN_BAT_DAU, 1) = YEARWEEK(CURDATE(), 1)");
$bookingsLastWeek = fetchScalar("SELECT COUNT(*) FROM lich_hen WHERE YEARWEEK(THOI_GIAN_BAT_DAU, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)");
$bookingGrowth = calcPercentChange($bookingsThisWeek, $bookingsLastWeek);

$activeCustomers = (int) fetchScalar('SELECT COUNT(*) FROM khach_hang');
$activeEmployees = (int) fetchScalar('SELECT COUNT(*) FROM nhan_vien');
$totalServices = (int) fetchScalar('SELECT COUNT(*) FROM dich_vu');
$totalBranches = (int) fetchScalar('SELECT COUNT(*) FROM chi_nhanh');
$pendingInvoices = (int) fetchScalar("SELECT COUNT(DISTINCT hd.ID_HD) FROM hoa_don hd JOIN thanh_toan_truc_tuyen tt ON tt.ID_HD = hd.ID_HD WHERE hd.TRANGTHAI_THANHTOAN <> 'Đã thanh toán' AND tt.TRANG_THAI = 'pending'");
$overdueInvoices = (int) fetchScalar("SELECT COUNT(*) FROM hoa_don hd JOIN lich_hen lh ON lh.ID_LICHHEN = hd.ID_LICHHEN WHERE hd.TRANGTHAI_THANHTOAN <> 'Đã thanh toán' AND lh.THOI_GIAN_BAT_DAU < NOW()");
$appointmentsToday = (int) fetchScalar('SELECT COUNT(*) FROM lich_hen WHERE DATE(THOI_GIAN_BAT_DAU) = CURDATE()');
$appointmentsNeedConfirm = (int) fetchScalar("SELECT COUNT(*) FROM lich_hen WHERE TRANGTHAI = 'Đang chờ' AND THOI_GIAN_BAT_DAU BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)");
$uniqueCustomersMonth = (int) fetchScalar('SELECT COUNT(DISTINCT ID_TK) FROM lich_hen WHERE YEAR(THOI_GIAN_BAT_DAU) = YEAR(CURDATE()) AND MONTH(THOI_GIAN_BAT_DAU) = MONTH(CURDATE())');

$avgTicket = $bookingsThisWeek > 0 ? $currentMonthRevenue / max($bookingsThisWeek, 1) : fetchScalar("SELECT COALESCE(AVG(TONG_TIEN), 0) FROM hoa_don WHERE TRANGTHAI_THANHTOAN = 'Đã thanh toán'");
$totalInvoices = (int) fetchScalar('SELECT COUNT(*) FROM hoa_don');
$paidInvoices = (int) fetchScalar("SELECT COUNT(*) FROM hoa_don WHERE TRANGTHAI_THANHTOAN = 'Đã thanh toán'");
$collectionRate = $totalInvoices > 0 ? round(($paidInvoices / $totalInvoices) * 100, 1) : 0;
$avgRating = fetchScalar('SELECT COALESCE(AVG(XEP_HANG_DV), 0) FROM phan_hoi_cua_khach_hang');
$satisfactionRate = round(($avgRating / 5) * 100, 1);

$capacityRatio = $activeEmployees > 0 ? min(100, round(($appointmentsToday / ($activeEmployees * 2)) * 100, 1)) : 0;

$timeline = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-$i month"));
    $timeline[$key] = [
        'label' => date('m/Y', strtotime("-$i month")),
        'revenue' => 0,
        'bookings' => 0,
    ];
}

$timelineRevenueRows = fetchRows("SELECT DATE_FORMAT(NGAY_GIO, '%Y-%m') AS bucket, SUM(TONG_TIEN) AS total FROM hoa_don WHERE TRANGTHAI_THANHTOAN = 'Đã thanh toán' AND NGAY_GIO >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY bucket");
foreach ($timelineRevenueRows as $row) {
    $key = $row['bucket'];
    if (isset($timeline[$key])) {
        $timeline[$key]['revenue'] = (float) $row['total'];
    }
}

$timelineBookingRows = fetchRows("SELECT DATE_FORMAT(THOI_GIAN_BAT_DAU, '%Y-%m') AS bucket, COUNT(*) AS total FROM lich_hen WHERE THOI_GIAN_BAT_DAU >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY bucket");
foreach ($timelineBookingRows as $row) {
    $key = $row['bucket'];
    if (isset($timeline[$key])) {
        $timeline[$key]['bookings'] = (int) $row['total'];
    }
}
$chartPayload = array_values($timeline);

$serviceShare = fetchRows("SELECT dv.TEN_DV AS name, COUNT(*) AS total FROM lich_hen lh JOIN dich_vu dv ON dv.ID_DV = lh.ID_DV WHERE lh.THOI_GIAN_BAT_DAU >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY dv.ID_DV ORDER BY total DESC LIMIT 6");
$branchPerformance = fetchRows("SELECT cn.TEN_CN AS name, COUNT(DISTINCT lh.ID_LICHHEN) AS bookings, SUM(CASE WHEN hd.TRANGTHAI_THANHTOAN = 'Đã thanh toán' THEN hd.TONG_TIEN ELSE 0 END) AS revenue, SUM(CASE WHEN lh.TRANGTHAI = 'Đã hoàn thành' THEN 1 ELSE 0 END) AS completed FROM chi_nhanh cn LEFT JOIN lich_hen lh ON lh.ID_CHINHANH = cn.ID_CN LEFT JOIN hoa_don hd ON hd.ID_LICHHEN = lh.ID_LICHHEN GROUP BY cn.ID_CN ORDER BY revenue DESC LIMIT 5");
$recentInvoices = fetchRows("SELECT hd.ID_HD, hd.TONG_TIEN, hd.TRANGTHAI_THANHTOAN, hd.NGAY_GIO, tk.HO_TEN FROM hoa_don hd JOIN lich_hen lh ON lh.ID_LICHHEN = hd.ID_LICHHEN JOIN tai_khoan tk ON tk.ID_TK = lh.ID_TK ORDER BY hd.NGAY_GIO DESC LIMIT 5");
$upcomingAppointments = fetchRows("SELECT lh.ID_LICHHEN, lh.THOI_GIAN_BAT_DAU, lh.TRANGTHAI, tk.HO_TEN, dv.TEN_DV, cn.TEN_CN FROM lich_hen lh JOIN tai_khoan tk ON tk.ID_TK = lh.ID_TK JOIN dich_vu dv ON dv.ID_DV = lh.ID_DV LEFT JOIN chi_nhanh cn ON cn.ID_CN = lh.ID_CHINHANH WHERE lh.THOI_GIAN_BAT_DAU >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY lh.THOI_GIAN_BAT_DAU ASC LIMIT 6");
$feedbackList = fetchRows("SELECT kh.HO_TEN, dv.TEN_DV, ph.NOI_DUNG, ph.XEP_HANG_DV, ph.NGAY_GUI FROM phan_hoi_cua_khach_hang ph JOIN khach_hang kh ON kh.ID_TK = ph.ID_TK JOIN dich_vu dv ON dv.ID_DV = ph.ID_DV ORDER BY ph.NGAY_GUI DESC LIMIT 3");

$systemAlerts = [];
if ($pendingInvoices > 0) {
  $systemAlerts[] = "Có {$pendingInvoices} giao dịch VNPay đang chờ IPN";
}
if ($overdueInvoices > 0) {
    $systemAlerts[] = "{$overdueInvoices} hóa đơn đã quá hạn";
}
if ($appointmentsNeedConfirm > 0) {
    $systemAlerts[] = "{$appointmentsNeedConfirm} lịch hẹn đang chờ xác nhận";
}
if ($capacityRatio > 85) {
    $systemAlerts[] = 'Tải nhân sự cao, cân nhắc phân bổ thêm nhân viên';
}
if (empty($systemAlerts)) {
    $systemAlerts[] = 'Hệ thống ổn định · không có cảnh báo mới';
}

$tasks = [
    [
      'title' => 'Theo dõi VNPay',
      'description' => 'Kiểm tra các giao dịch đang pending và xử lý nếu quá hạn.',
      'count' => $pendingInvoices,
      'link' => '?page=payments&trangthai=pending_confirm',
    ],
    [
        'title' => 'Nhắc khách thanh toán',
        'description' => 'Liên hệ các hóa đơn quá hạn để đảm bảo dòng tiền.',
        'count' => $overdueInvoices,
        'link' => '?page=payments&state=overdue',
    ],
    [
        'title' => 'Phân bổ nhân sự',
        'description' => 'Đảm bảo lịch hẹn trong 3 ngày tới có đủ ekip.',
        'count' => $appointmentsNeedConfirm,
        'link' => '?page=assignments',
    ],
];

$aiInsight = sprintf(
    'Doanh thu tháng này đạt %s₫ (%s so với tháng trước). Tuần này đã có %d lịch hẹn được đặt và %d khách hàng hoạt động.',
    formatCurrency($currentMonthRevenue),
    formatChange($revenueGrowth),
    $bookingsThisWeek,
    $uniqueCustomersMonth
);
?>
<div class="space-y-6">
  <header class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-3xl p-8 shadow-xl flex flex-col gap-3">
    <p class="text-sm uppercase tracking-[0.2em] opacity-80">Tổng quan điều hành</p>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-3xl md:text-4xl font-extrabold">Xin chào, <?= htmlspecialchars($adminName) ?></h1>
        <p class="text-white/80">Cập nhật lúc <?= $now->format('H:i d/m/Y') ?></p>
      </div>
      <div class="flex gap-3 text-sm">
        <div class="px-4 py-2 bg-white/20 rounded-xl backdrop-blur">Chi nhánh: <?= $totalBranches ?></div>
        <div class="px-4 py-2 bg-white/20 rounded-xl backdrop-blur">Dịch vụ: <?= $totalServices ?></div>
      </div>
    </div>
    <div class="mt-4 text-sm bg-white/10 rounded-2xl p-4 backdrop-blur">
      <p class="font-semibold">Trợ lý AI</p>
      <p class="text-white/90"><?= htmlspecialchars($aiInsight) ?></p>
    </div>
  </header>

  <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
    <article class="bg-white rounded-2xl p-6 shadow-md border border-gray-100">
      <p class="text-sm text-gray-500">Doanh thu tháng</p>
      <h2 class="text-3xl font-bold text-gray-900 mt-2"><?= formatCurrency($currentMonthRevenue) ?> ₫</h2>
      <span class="inline-flex items-center text-xs font-semibold px-3 py-1 rounded-full mt-3 <?= growthBadgeClass($revenueGrowth) ?>">
        <?= formatChange($revenueGrowth) ?> vs. tháng trước
      </span>
    </article>
    <article class="bg-white rounded-2xl p-6 shadow-md border border-gray-100">
      <p class="text-sm text-gray-500">Lịch hẹn tuần</p>
      <h2 class="text-3xl font-bold text-gray-900 mt-2"><?= (int) $bookingsThisWeek ?></h2>
      <span class="inline-flex items-center text-xs font-semibold px-3 py-1 rounded-full mt-3 <?= growthBadgeClass($bookingGrowth) ?>">
        <?= formatChange($bookingGrowth) ?> vs. tuần trước
      </span>
    </article>
    <article class="bg-white rounded-2xl p-6 shadow-md border border-gray-100">
      <p class="text-sm text-gray-500">Khách hàng hoạt động</p>
      <h2 class="text-3xl font-bold text-gray-900 mt-2"><?= $uniqueCustomersMonth ?></h2>
      <p class="text-xs text-emerald-600 mt-2">Tổng cơ sở dữ liệu: <?= $activeCustomers ?></p>
    </article>
    <article class="bg-white rounded-2xl p-6 shadow-md border border-gray-100">
      <p class="text-sm text-gray-500">Hóa đơn cần duyệt</p>
      <h2 class="text-3xl font-bold text-gray-900 mt-2"><?= $pendingInvoices ?></h2>
      <p class="text-xs text-slate-500 mt-2">Quá hạn: <?= $overdueInvoices ?></p>
    </article>
  </section>

  <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl p-6 shadow-md border border-gray-100 lg:col-span-2">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-lg font-semibold text-gray-900">Doanh thu & Booking 6 tháng</h3>
          <p class="text-sm text-gray-500">Theo dõi xu hướng để chủ động kế hoạch nhân sự</p>
        </div>
      </div>
      <canvas id="trendChart" height="140"></canvas>
    </div>
    <div class="bg-white rounded-2xl p-6 shadow-md border border-gray-100 flex flex-col">
      <h3 class="text-lg font-semibold text-gray-900">Cơ cấu dịch vụ</h3>
      <p class="text-sm text-gray-500 mb-4">Top dịch vụ được đặt trong 90 ngày</p>
      <canvas id="serviceChart" height="200"></canvas>
      <ul class="mt-6 space-y-2 text-sm text-gray-700">
        <?php foreach ($serviceShare as $share): ?>
          <li class="flex justify-between">
            <span><?= htmlspecialchars($share['name']) ?></span>
            <span class="font-semibold"><?= $share['total'] ?> lịch</span>
          </li>
        <?php endforeach; ?>
        <?php if (empty($serviceShare)): ?>
          <li class="text-gray-400">Chưa có dữ liệu đặt dịch vụ.</li>
        <?php endif; ?>
      </ul>
    </div>
  </section>

  <section class="grid grid-cols-1 xl:grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl p-6 shadow-md border border-gray-100 xl:col-span-2">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Hiệu suất chi nhánh</h3>
        <p class="text-xs text-gray-500">Booking đã hoàn tất / Doanh thu</p>
      </div>
      <div class="space-y-4">
        <?php foreach ($branchPerformance as $branch): ?>
          <?php
          $revenue = (float) ($branch['revenue'] ?? 0);
          $bookings = (int) ($branch['bookings'] ?? 0);
          $completed = (int) ($branch['completed'] ?? 0);
          $completionRate = $bookings > 0 ? round(($completed / $bookings) * 100) : 0;
          ?>
          <div class="p-4 rounded-xl border border-gray-100 flex flex-col gap-2">
            <div class="flex items-center justify-between">
              <div>
                <p class="font-semibold text-gray-900"><?= htmlspecialchars($branch['name'] ?? 'Chưa cập nhật') ?></p>
                <p class="text-xs text-gray-500"><?= $bookings ?> booking · <?= $completionRate ?>% hoàn tất</p>
              </div>
              <span class="text-base font-semibold text-indigo-600"><?= formatCurrency($revenue) ?> ₫</span>
            </div>
            <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
              <div class="h-full bg-indigo-500" style="width: <?= min(100, $completionRate) ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (empty($branchPerformance)): ?>
          <p class="text-gray-400 text-sm">Chưa có dữ liệu chi nhánh.</p>
        <?php endif; ?>
      </div>
    </div>
    <div class="bg-white rounded-2xl p-6 shadow-md border border-gray-100">
      <h3 class="text-lg font-semibold text-gray-900">Sức khỏe hệ thống</h3>
      <div class="mt-4 space-y-4">
        <div>
          <p class="text-sm text-gray-500">Tỷ lệ thu hồi</p>
          <div class="flex items-end gap-2">
            <span class="text-3xl font-bold text-gray-900"><?= $collectionRate ?>%</span>
            <span class="text-xs text-gray-500">Hóa đơn đã thanh toán</span>
          </div>
          <div class="w-full bg-gray-100 h-2 rounded-full mt-2">
            <div class="h-full bg-emerald-500" style="width: <?= min(100, $collectionRate) ?>%"></div>
          </div>
        </div>
        <div>
          <p class="text-sm text-gray-500">Mức độ hài lòng</p>
          <div class="flex items-end gap-2">
            <span class="text-3xl font-bold text-gray-900"><?= $satisfactionRate ?>%</span>
            <span class="text-xs text-gray-500">Điểm trung bình <?= round($avgRating, 1) ?>/5</span>
          </div>
          <div class="w-full bg-gray-100 h-2 rounded-full mt-2">
            <div class="h-full bg-amber-500" style="width: <?= min(100, $satisfactionRate) ?>%"></div>
          </div>
        </div>
        <div>
          <p class="text-sm text-gray-500">Tải nhân sự hôm nay</p>
          <div class="flex items-end gap-2">
            <span class="text-3xl font-bold text-gray-900"><?= $capacityRatio ?>%</span>
            <span class="text-xs text-gray-500"><?= $appointmentsToday ?> lịch / <?= $activeEmployees ?> nhân sự</span>
          </div>
          <div class="w-full bg-gray-100 h-2 rounded-full mt-2">
            <div class="h-full bg-rose-500" style="width: <?= min(100, $capacityRatio) ?>%"></div>
          </div>
        </div>
      </div>
      <div class="mt-6 bg-slate-50 rounded-xl p-4">
        <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Cảnh báo</p>
        <ul class="mt-3 space-y-2 text-sm text-slate-700">
          <?php foreach ($systemAlerts as $alert): ?>
            <li class="flex items-center gap-2">
              <span class="h-2 w-2 rounded-full bg-rose-500"></span>
              <?= htmlspecialchars($alert) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </section>

  <section class="grid grid-cols-1 xl:grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl p-6 shadow-md border border-gray-100">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Hoạt động sắp diễn ra</h3>
        <span class="text-xs text-gray-500">Cập nhật realtime</span>
      </div>
      <div class="space-y-4">
        <?php foreach ($upcomingAppointments as $item): ?>
          <div class="flex gap-3">
            <div class="w-2 rounded-full" style="background-image: linear-gradient(to bottom, #6366f1, #a855f7);"></div>
            <div class="flex-1">
              <p class="text-sm font-semibold text-gray-900">#<?= $item['ID_LICHHEN'] ?> · <?= htmlspecialchars($item['TEN_DV']) ?></p>
              <p class="text-xs text-gray-500"><?= htmlspecialchars($item['HO_TEN']) ?> · <?= htmlspecialchars($item['TEN_CN'] ?? 'Chi nhánh đang cập nhật') ?></p>
              <p class="text-xs text-indigo-600 mt-1"><?= date('H:i d/m', strtotime($item['THOI_GIAN_BAT_DAU'])) ?> · <?= htmlspecialchars($item['TRANGTHAI']) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (empty($upcomingAppointments)): ?>
          <p class="text-gray-400 text-sm">Không có lịch nào sắp diễn ra.</p>
        <?php endif; ?>
      </div>
    </div>
    <div class="bg-white rounded-2xl p-6 shadow-md border border-gray-100">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Hóa đơn gần nhất</h3>
        <a href="?page=payments" class="text-xs text-indigo-600 font-semibold">Xem tất cả</a>
      </div>
      <div class="divide-y">
        <?php foreach ($recentInvoices as $invoice): ?>
          <div class="py-3 flex items-center justify-between text-sm">
            <div>
              <p class="font-semibold text-gray-900">HD#<?= $invoice['ID_HD'] ?> · <?= htmlspecialchars($invoice['HO_TEN']) ?></p>
              <p class="text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($invoice['NGAY_GIO'])) ?></p>
            </div>
            <div class="text-right">
              <p class="font-semibold text-gray-900"><?= formatCurrency((float)$invoice['TONG_TIEN']) ?> ₫</p>
              <?php
              $paid = trim($invoice['TRANGTHAI_THANHTOAN']) === 'Đã thanh toán';
              $statusClass = $paid ? 'text-emerald-600 bg-emerald-50' : 'text-amber-600 bg-amber-50';
              ?>
              <span class="text-xs px-2 py-1 rounded-full <?= $statusClass ?>"><?= htmlspecialchars($invoice['TRANGTHAI_THANHTOAN']) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (empty($recentInvoices)): ?>
          <p class="py-4 text-gray-400 text-sm">Chưa có hóa đơn nào.</p>
        <?php endif; ?>
      </div>
    </div>
    <div class="bg-white rounded-2xl p-6 shadow-md border border-gray-100">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Nhiệm vụ ưu tiên</h3>
        <span class="text-xs text-gray-500">Tự động từ dữ liệu</span>
      </div>
      <div class="space-y-4">
        <?php foreach ($tasks as $task): ?>
          <a href="<?= $task['link'] ?>" class="block border border-gray-100 rounded-xl p-4 hover:border-indigo-200 transition">
            <div class="flex items-center justify-between">
              <p class="font-semibold text-gray-900"><?= htmlspecialchars($task['title']) ?></p>
              <span class="text-xs font-semibold px-2 py-1 rounded-full bg-slate-100 text-slate-700">
                <?= $task['count'] ?> việc
              </span>
            </div>
            <p class="text-xs text-gray-500 mt-2"><?= htmlspecialchars($task['description']) ?></p>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl p-6 shadow-md border border-gray-100">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Phản hồi khách hàng</h3>
      <div class="space-y-4">
        <?php foreach ($feedbackList as $feedback): ?>
          <div class="border border-gray-100 rounded-xl p-4">
            <div class="flex items-center justify-between text-sm">
              <p class="font-semibold text-gray-900"><?= htmlspecialchars($feedback['HO_TEN']) ?></p>
              <span class="text-amber-500 font-semibold"><?= str_repeat('★', (int)$feedback['XEP_HANG_DV']) ?></span>
            </div>
            <p class="text-xs text-gray-500">Dịch vụ: <?= htmlspecialchars($feedback['TEN_DV']) ?> · <?= date('d/m', strtotime($feedback['NGAY_GUI'])) ?></p>
            <p class="text-sm text-gray-700 mt-2 leading-relaxed">“<?= htmlspecialchars($feedback['NOI_DUNG']) ?>”</p>
          </div>
        <?php endforeach; ?>
        <?php if (empty($feedbackList)): ?>
          <p class="text-gray-400 text-sm">Chưa có phản hồi mới.</p>
        <?php endif; ?>
      </div>
    </div>
    <div class="bg-white rounded-2xl p-6 shadow-md border border-gray-100">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Chỉ số vận hành nhanh</h3>
      <ul class="space-y-3 text-sm text-gray-700">
        <li class="flex items-center justify-between">
          <span>Nhân sự toàn hệ thống</span>
          <span class="font-semibold"><?= $activeEmployees ?></span>
        </li>
        <li class="flex items-center justify-between">
          <span>Bookings đang mở</span>
          <span class="font-semibold"><?= (int) fetchScalar("SELECT COUNT(*) FROM lich_hen WHERE TRANGTHAI IN ('Đang chờ','Đã xác nhận')") ?></span>
        </li>
        <li class="flex items-center justify-between">
          <span>Doanh thu TB/booking</span>
          <span class="font-semibold"><?= formatCurrency($avgTicket) ?> ₫</span>
        </li>
        <li class="flex items-center justify-between">
          <span>Tệp khách hàng tổng</span>
          <span class="font-semibold"><?= $activeCustomers ?></span>
        </li>
      </ul>
      <div class="mt-6 bg-slate-50 rounded-xl p-4 text-xs text-slate-600 leading-5">
        Tip: Tận dụng AI Chat trong hệ thống để tạo kịch bản upsell theo từng dịch vụ hot.
      </div>
    </div>
    <div class="bg-white rounded-2xl p-6 shadow-md border border-gray-100">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Lối tắt quản trị</h3>
      <div class="grid grid-cols-2 gap-3">
        <a href="?page=appointments" class="rounded-2xl border border-gray-100 p-4 text-center hover:border-indigo-200 transition">
          <i class="fas fa-calendar-check text-indigo-600 text-xl"></i>
          <p class="mt-2 text-sm font-semibold text-gray-900">Lịch hẹn</p>
        </a>
        <a href="?page=employees" class="rounded-2xl border border-gray-100 p-4 text-center hover:border-indigo-200 transition">
          <i class="fas fa-user-tie text-indigo-600 text-xl"></i>
          <p class="mt-2 text-sm font-semibold text-gray-900">Nhân sự</p>
        </a>
        <a href="?page=finances" class="rounded-2xl border border-gray-100 p-4 text-center hover:border-indigo-200 transition">
          <i class="fas fa-donate text-indigo-600 text-xl"></i>
          <p class="mt-2 text-sm font-semibold text-gray-900">Tài chính</p>
        </a>
        <a href="?page=services" class="rounded-2xl border border-gray-100 p-4 text-center hover:border-indigo-200 transition">
          <i class="fas fa-concierge-bell text-indigo-600 text-xl"></i>
          <p class="mt-2 text-sm font-semibold text-gray-900">Dịch vụ</p>
        </a>
      </div>
      <a href="/app/AI" class="mt-4 inline-flex items-center justify-center gap-2 w-full bg-indigo-600 text-white text-sm font-semibold py-3 rounded-2xl shadow hover:bg-indigo-700 transition">
        <i class="fas fa-robot"></i> Mở trợ lý AI nội bộ
      </a>
    </div>
  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const trendPayload = <?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE) ?>;
  const servicePayload = <?= json_encode($serviceShare, JSON_UNESCAPED_UNICODE) ?>;

  const trendCtx = document.getElementById('trendChart');
  if (trendCtx && trendPayload.length) {
    const labels = trendPayload.map(item => item.label);
    const revenueData = trendPayload.map(item => item.revenue);
    const bookingData = trendPayload.map(item => item.bookings);

    new Chart(trendCtx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Doanh thu',
            data: revenueData,
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79,70,229,0.15)',
            tension: 0.4,
            fill: true,
            yAxisID: 'y'
          },
          {
            label: 'Số lịch',
            data: bookingData,
            borderColor: '#f97316',
            backgroundColor: 'rgba(249,115,22,0.15)',
            tension: 0.4,
            fill: true,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'bottom'
          }
        },
        scales: {
          y: {
            position: 'left',
            ticks: {
              callback: value => value.toLocaleString('vi-VN') + '₫'
            }
          },
          y1: {
            position: 'right',
            grid: { drawOnChartArea: false }
          }
        }
      }
    });
  }

  const serviceCtx = document.getElementById('serviceChart');
  if (serviceCtx && servicePayload.length) {
    new Chart(serviceCtx, {
      type: 'doughnut',
      data: {
        labels: servicePayload.map(item => item.name),
        datasets: [{
          data: servicePayload.map(item => item.total),
          backgroundColor: ['#c084fc', '#a855f7', '#7c3aed', '#6366f1', '#22d3ee', '#34d399']
        }]
      },
      options: {
        plugins: {
          legend: {
            display: false
          }
        }
      }
    });
  }
</script>
