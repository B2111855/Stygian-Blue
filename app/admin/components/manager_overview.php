<?php
include '../../database/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$branchId   = isset($_SESSION['branch_id']) ? intval($_SESSION['branch_id']) : 0;
$branchName = isset($_SESSION['branch_name']) ? $_SESSION['branch_name'] : 'Chi nhánh của tôi';
$managerName = isset($_SESSION['HO_TEN']) ? $_SESSION['HO_TEN'] : 'Quản lý chi nhánh';

if ($branchId <= 0) {
  $branchId = 1;
  $branchName = 'Chi nhánh Demo';
}

$currentMonth = date('Y-m');
?>

<div class="space-y-8 text-gray-800" id="managerOverview">
  <input type="hidden" id="branchFilter" value="cn<?= $branchId ?>">
  <input type="hidden" id="branchNameField" value="<?= htmlspecialchars($branchName) ?>">

  <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <article class="xl:col-span-2 relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-indigo-500 to-purple-500 p-8 text-white shadow-2xl">
      <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.25),_transparent_60%)] opacity-60"></div>
      <div class="relative z-10 space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-6">
          <div>
            <p class="text-xs uppercase tracking-[0.3em] text-white/70">Xin chào, <?= htmlspecialchars($managerName) ?></p>
            <h1 class="text-3xl sm:text-4xl font-bold leading-tight">Chi nhánh <?= htmlspecialchars($branchName) ?></h1>
            <p class="text-sm text-white/80 mt-1">ID chi nhánh: CN<?= $branchId ?></p>
          </div>
          <div class="text-right">
            <p class="text-xs text-white/70">Cập nhật</p>
            <p class="text-lg font-semibold" id="nowText">--</p>
          </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div class="rounded-2xl bg-white/10 p-4 backdrop-blur">
            <p class="text-xs uppercase text-white/70">Doanh thu thực tế (tháng)</p>
            <p id="kpiRevenueCurrent" class="text-xl font-semibold mt-1">0 VND</p>
            <p class="text-xs text-white/60">Đã thanh toán</p>
          </div>
          <div class="rounded-2xl bg-white/10 p-4 backdrop-blur">
            <p class="text-xs uppercase text-white/70">Chi phí lương (tháng)</p>
            <p id="kpiSalaryCurrent" class="text-xl font-semibold mt-1">0 VND</p>
            <p class="text-xs text-white/60">Lương nhân viên</p>
          </div>
          <div class="rounded-2xl bg-white/10 p-4 backdrop-blur">
            <p class="text-xs uppercase text-white/70">Chi phí phát sinh (tháng)</p>
            <p id="kpiIncidentalCurrent" class="text-xl font-semibold mt-1">0 VND</p>
            <p class="text-xs text-white/60">Điện, nước, mặt bằng...</p>
          </div>
          <div class="rounded-2xl bg-white/10 p-4 backdrop-blur">
            <p class="text-xs uppercase text-white/70">Lợi nhuận ròng (tháng)</p>
            <p id="kpiMonthProfit" class="text-xl font-semibold mt-1">0 VND</p>
            <p class="text-xs text-white/60" id="profitRate">--</p>
          </div>
        </div>
      </div>
    </article>

    <article class="rounded-3xl bg-white p-6 shadow-xl border border-indigo-50">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs uppercase tracking-widest text-indigo-500">Pulse</p>
          <h2 class="text-lg font-semibold text-indigo-950">Ảnh chụp nhanh</h2>
        </div>
        <span class="text-xs px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 font-semibold">Trực tuyến</span>
      </div>
      <ul id="branchPulseList" class="mt-5 space-y-3 text-sm text-gray-600">
        <li class="flex items-center justify-between"><span>Biên lợi nhuận</span><span>--</span></li>
        <li class="flex items-center justify-between"><span>Quỹ lương</span><span>--</span></li>
        <li class="flex items-center justify-between"><span>Chi phí vận hành</span><span>--</span></li>
        <li class="flex items-center justify-between"><span>Chu kỳ dữ liệu</span><span>--</span></li>
      </ul>
    </article>
  </section>

  <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    <article class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm">
      <p class="text-xs font-semibold uppercase text-emerald-600">Tổng doanh thu</p>
      <p id="kpiTotalRevenue" class="mt-2 text-2xl font-bold text-emerald-800">0 VND</p>
      <p id="kpiRevenueInfo" class="text-xs text-gray-500">--</p>
    </article>
    <article class="rounded-2xl border border-rose-100 bg-white p-5 shadow-sm">
      <p class="text-xs font-semibold uppercase text-rose-600">Tổng chi phí</p>
      <p id="kpiTotalExpense" class="mt-2 text-2xl font-bold text-rose-800">0 VND</p>
      <p id="kpiExpenseInfo" class="text-xs text-gray-500">--</p>
    </article>
    <article class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm">
      <p class="text-xs font-semibold uppercase text-sky-600">Quỹ lương</p>
      <p id="kpiSalaryFund" class="mt-2 text-2xl font-bold text-sky-800">0 VND</p>
      <p class="text-xs text-gray-500">Tính đến hiện tại</p>
    </article>
    <article class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm">
      <p class="text-xs font-semibold uppercase text-amber-600">Tình trạng</p>
      <p id="kpiStatus" class="mt-2 text-lg font-semibold text-amber-700">Đang tính...</p>
      <p id="kpiStatusSub" class="text-xs text-gray-500">--</p>
    </article>
  </section>

  <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <article class="xl:col-span-2 rounded-3xl bg-white p-6 shadow-lg border border-gray-100">
      <div class="flex flex-wrap items-center gap-4 justify-between">
        <div>
          <p class="text-xs uppercase text-gray-500 tracking-widest">Cashflow insight</p>
          <h3 class="text-xl font-semibold text-gray-900">Dòng tiền chi nhánh</h3>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <select id="timeFilter" class="rounded-xl border border-gray-200 px-4 py-2 text-sm focus:ring-indigo-500">
            <option value="month">Theo tháng</option>
            <option value="quarter">Theo quý</option>
            <option value="year" selected>Theo năm</option>
          </select>
          <button id="refreshMetrics" class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-100">
            Làm mới
          </button>
        </div>
      </div>
      <canvas id="financialChart" class="mt-6" height="140"></canvas>
      <div class="mt-6 border-t border-gray-100 pt-5">
        <div id="summary" class="text-sm text-gray-700"></div>
      </div>
    </article>

    <div class="space-y-6">
      <article class="rounded-3xl bg-white p-6 shadow-lg border border-gray-100">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">Hành động nhanh</h3>
          <span class="text-xs rounded-full bg-gray-100 px-3 py-1 text-gray-600">1 bước</span>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
          <a href="manager_dashboard.php?page=assignments" class="rounded-2xl border border-indigo-100 px-4 py-3 text-center font-semibold text-indigo-700 hover:bg-indigo-50">Phân công</a>
          <a href="manager_dashboard.php?page=appointments" class="rounded-2xl border border-sky-100 px-4 py-3 text-center font-semibold text-sky-700 hover:bg-sky-50">Lịch hẹn</a>
          <a href="manager_dashboard.php?page=expenses" class="rounded-2xl border border-rose-100 px-4 py-3 text-center font-semibold text-rose-700 hover:bg-rose-50">Chi phí</a>
          <button id="btnReportAdmin" class="rounded-2xl border border-emerald-100 px-4 py-3 font-semibold text-emerald-700 hover:bg-emerald-50">Gửi báo cáo</button>
        </div>
        <div class="mt-5 rounded-2xl bg-indigo-50 p-4 text-sm text-indigo-800" id="kpiStatusBanner">
          Cập nhật trạng thái sau khi đồng bộ dữ liệu.
        </div>
      </article>

      <article class="rounded-3xl bg-white p-6 shadow-lg border border-gray-100">
        <h3 class="text-lg font-semibold text-gray-900">Tiến độ KPI</h3>
        <div class="mt-4 space-y-4 text-sm text-gray-600">
          <div>
            <div class="flex justify-between mb-1"><span>Doanh thu</span><span id="revenueProgressText">0%</span></div>
            <div class="h-2 w-full rounded-full bg-gray-100"><div id="revenueProgress" class="h-full rounded-full bg-emerald-500" style="width:0%"></div></div>
          </div>
          <div>
            <div class="flex justify-between mb-1"><span>Chi phí</span><span id="expenseProgressText">0%</span></div>
            <div class="h-2 w-full rounded-full bg-gray-100"><div id="expenseProgress" class="h-full rounded-full bg-rose-500" style="width:0%"></div></div>
          </div>
          <div>
            <div class="flex justify-between mb-1"><span>Lợi nhuận</span><span id="profitProgressText">0%</span></div>
            <div class="h-2 w-full rounded-full bg-gray-100"><div id="profitProgress" class="h-full rounded-full bg-indigo-500" style="width:0%"></div></div>
          </div>
        </div>
      </article>
    </div>
  </section>

  <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <article class="rounded-3xl bg-white p-6 shadow-lg border border-gray-100">
      <div class="flex flex-wrap items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900">Cơ cấu doanh thu theo dịch vụ</h3>
        <span class="text-xs text-gray-400" id="serviceShareCaption">Đang tải...</span>
      </div>
      <canvas id="serviceShareChart" class="mt-6" height="160"></canvas>
      <p id="serviceShareEmpty" class="mt-4 text-sm text-gray-500 hidden">Chưa có dữ liệu dịch vụ trong kỳ.</p>
    </article>

    <article class="rounded-3xl bg-white p-6 shadow-lg border border-gray-100">
      <div class="flex flex-wrap items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900">Khung giờ cao điểm</h3>
        <span class="text-xs text-gray-400">Theo lịch hẹn đã chốt</span>
      </div>
      <canvas id="timeSlotChart" class="mt-6" height="160"></canvas>
      <p id="timeSlotEmpty" class="mt-4 text-sm text-gray-500 hidden">Chưa ghi nhận lịch hẹn cho thống kê này.</p>
    </article>
  </section>

  <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <article class="xl:col-span-2 rounded-3xl bg-white p-6 shadow-lg border border-gray-100">
      <div class="flex flex-wrap items-center justify-between">
        <div>
          <p class="text-xs uppercase text-gray-500 tracking-widest">Chi phí vận hành</p>
          <h3 class="text-lg font-semibold text-gray-900">Bảng kê & điều chỉnh</h3>
        </div>
        <button type="button" onclick="toggleExpenseForm()" class="rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-100">
          ✏️ Cập nhật chi phí
        </button>
      </div>
      <div id="expenseTable" class="mt-4 text-sm"></div>

      <div id="expenseFormWrapper" class="mt-6 hidden">
        <form id="expenseForm" class="space-y-5 rounded-2xl border border-amber-100 bg-amber-50 p-5">
          <input type="hidden" name="branch" value="cn<?= $branchId ?>">
          <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm font-semibold text-gray-600">Tháng áp dụng
              <input type="month" name="month" value="<?= $currentMonth ?>" class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-2 text-sm focus:ring-indigo-500">
            </label>
            <label class="text-sm font-semibold text-gray-600">Ghi chú chung
              <input type="text" name="submission_note" placeholder="Ví dụ: Điều chỉnh sau kiểm kê" class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-2 text-sm focus:ring-indigo-500">
            </label>
          </div>

          <div>
            <div class="flex items-center justify-between">
              <p class="text-sm font-semibold text-gray-700">Chi tiết phát sinh</p>
              <button type="button" id="addExpenseRow" class="text-sm font-semibold text-indigo-600">+ Thêm dòng</button>
            </div>
            <div id="expenseRows" class="mt-3 space-y-3"></div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm font-semibold text-gray-600">Chế độ tính thuế
              <select name="tax_mode" id="taxMode" class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-2 text-sm focus:ring-indigo-500">
                <option value="auto" selected>Tự động 10% doanh thu</option>
                <option value="manual">Nhập thủ công</option>
              </select>
            </label>
            <label class="text-sm font-semibold text-gray-600">Giá trị thuế (nếu chọn thủ công)
              <input type="number" id="taxValue" name="tax_value" min="0" step="1000" class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-2 text-sm focus:ring-indigo-500" placeholder="Nhập số VND" disabled>
            </label>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-full bg-indigo-600 px-6 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Lưu chi phí</button>
            <button type="button" onclick="toggleExpenseForm()" class="rounded-full border border-gray-300 px-6 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Đóng lại</button>
            <span id="expenseMessage" class="text-sm font-medium text-gray-700"></span>
          </div>
        </form>
      </div>
    </article>

    <article class="rounded-3xl bg-white p-6 shadow-lg border border-gray-100">
      <h3 class="text-lg font-semibold text-gray-900">Nhân sự bận rộn</h3>
      <ul id="staffLoadList" class="mt-4 space-y-3 text-sm text-gray-600">
        <li>Đang tải dữ liệu...</li>
      </ul>
      <div class="mt-6 rounded-2xl bg-indigo-50 p-4 text-center text-sm">
        <p class="font-semibold text-indigo-800">Cần in báo cáo gửi ban điều hành?</p>
        <button type="button" onclick="printReport()" class="mt-3 rounded-full bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">📄 In báo cáo chi nhánh</button>
      </div>
    </article>
  </section>

  <section class="rounded-3xl bg-white p-6 shadow-lg border border-gray-100">
    <div class="flex flex-wrap items-center justify-between">
      <h3 class="text-lg font-semibold text-gray-900">Hoạt động gần đây</h3>
      <span class="text-xs text-gray-400">Tự động tạo từ dữ liệu mới nhất</span>
    </div>
    <ul id="activityFeed" class="mt-5 space-y-4 text-sm text-gray-600">
      <li>Đang tổng hợp ...</li>
    </ul>
  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  (function () {
    const $ = (id) => document.getElementById(id);
    const branchField = $('branchFilter');
    const branchName = $('branchNameField').value;
    const timeFilter = $('timeFilter');
    const charts = { financial: null, service: null, slot: null };

    const els = {
      summary: $('summary'),
      status: $('kpiStatus'),
      statusSub: $('kpiStatusSub'),
      statusBanner: $('kpiStatusBanner'),
      revenueCurrent: $('kpiRevenueCurrent'),
      expenseCurrent: $('kpiExpenseCurrent'),
      totalRevenue: $('kpiTotalRevenue'),
      revenueInfo: $('kpiRevenueInfo'),
      totalExpense: $('kpiTotalExpense'),
      expenseInfo: $('kpiExpenseInfo'),
      salaryFund: $('kpiSalaryFund'),
      trendBadge: $('trendBadge'),
      trendLabel: $('trendLabel'),
      profitRate: $('profitRate'),
      branchPulse: $('branchPulseList'),
      revenueProgress: $('revenueProgress'),
      expenseProgress: $('expenseProgress'),
      profitProgress: $('profitProgress'),
      revenueProgressText: $('revenueProgressText'),
      expenseProgressText: $('expenseProgressText'),
      profitProgressText: $('profitProgressText'),
      monthProfit: $('kpiMonthProfit'),
      salaryCurrent: $('kpiSalaryCurrent'),
      incidentalCurrent: $('kpiIncidentalCurrent'),
      serviceShareCaption: $('serviceShareCaption'),
      serviceShareEmpty: $('serviceShareEmpty'),
      timeSlotEmpty: $('timeSlotEmpty'),
      staffLoadList: $('staffLoadList'),
      activityFeed: $('activityFeed'),
      revenueCurrentCard: $('kpiRevenueCurrent'),
      expenseTable: $('expenseTable'),
    };

    const formatCurrency = (value = 0) => `${Number(value || 0).toLocaleString('vi-VN')} VND`;
    const safeSeries = (series) => Array.isArray(series) ? series : [];
      async function fetchCurrentMonthSnapshot(branch) {
        const res = await fetch(`./components/get_financial_data.php?filter=month&branch=${branch}`);
        const data = await res.json();
        const revenue = safeSeries(data.revenue_actual);
        const expense = safeSeries(data.expense);
        const salary = safeSeries(data.salary_expense);
        const revenueVal = revenue.at(-1) || 0;
        const expenseVal = expense.at(-1) || 0;
        const salaryVal = salary.at(-1) || 0;
        return {
          revenue: revenueVal,
          expense: expenseVal,
          salary: salaryVal,
          profit: revenueVal - (expenseVal + salaryVal)
        };
      }

    function updateClock() {
      $('nowText').textContent = new Date().toLocaleString('vi-VN');
    }

    function setProgress(widthEl, textEl, value, max) {
      const percent = max === 0 ? 0 : Math.round((value / max) * 100);
      widthEl.style.width = `${Math.min(percent, 100)}%`;
      textEl.textContent = `${percent}%`;
    }

    function renderPulse(items) {
      els.branchPulse.innerHTML = items
        .map(item => `
          <li class="flex items-center justify-between">
            <span class="text-gray-600">${item.label}</span>
            <span class="font-semibold ${item.tone === 'bad' ? 'text-rose-600' : (item.tone === 'warn' ? 'text-amber-600' : 'text-emerald-600')}">${item.value}</span>
          </li>
        `)
        .join('');
    }

    function renderActivityFeed(rows) {
      if (!rows.length) {
        els.activityFeed.innerHTML = '<li class="text-gray-500">Chưa có dữ liệu để tạo hoạt động.</li>';
        return;
      }

      els.activityFeed.innerHTML = rows
        .map(row => `
          <li class="rounded-2xl border border-gray-100 p-4">
            <div class="flex items-center justify-between">
              <p class="font-semibold text-gray-900">${row.title}</p>
              <span class="text-xs ${row.tone === 'bad' ? 'text-rose-600' : row.tone === 'warn' ? 'text-amber-600' : 'text-emerald-600'}">${row.badge}</span>
            </div>
            <p class="mt-1 text-sm text-gray-600">${row.detail}</p>
          </li>
        `)
        .join('');
    }

    async function fetchAndRenderChart() {
      try {
        const branch = branchField.value;
        const filter = timeFilter.value;
        const res = await fetch(`./components/get_financial_data.php?filter=${filter}&branch=${branch}`);
        const data = await res.json();
          const monthSnapshot = await fetchCurrentMonthSnapshot(branch);

        if (!data || !Array.isArray(data.labels)) {
          throw new Error('Không thể tải dữ liệu tài chính');
        }

        const labels = safeSeries(data.labels);
        const revenue = safeSeries(data.revenue_actual);
        const expense = safeSeries(data.expense); // Chi phí vận hành (phát sinh)
        const salary = safeSeries(data.salary_expense); // Chi phí lương
        const totalExpenseSeries = expense.map((val, idx) => Number(val || 0) + Number(salary[idx] || 0));

        const totalRevenue = revenue.reduce((sum, val) => sum + Number(val || 0), 0);
        const totalExpense = expense.reduce((sum, val) => sum + Number(val || 0), 0);
        const salaryTotal = salary.reduce((sum, val) => sum + Number(val || 0), 0);
        const totalCost = totalExpense + salaryTotal;
        const profit = totalRevenue - totalCost;

        const latestRevenue = revenue.at(-1) || 0;
        const previousRevenue = revenue.length > 1 ? revenue.at(-2) : latestRevenue;
        const latestExpense = expense.at(-1) || 0;
        const latestSalary = salary.at(-1) || 0;
        const latestTotalExpense = latestExpense + latestSalary;
          const latestProfit = latestRevenue - latestTotalExpense;

        const trend = previousRevenue === 0 ? 0 : ((latestRevenue - previousRevenue) / previousRevenue) * 100;
        const profitMargin = totalRevenue === 0 ? 0 : (profit / totalRevenue) * 100;

        if (charts.financial) charts.financial.destroy();
        const ctx = document.getElementById('financialChart').getContext('2d');
        charts.financial = new Chart(ctx, {
          type: 'line',
          data: {
            labels,
            datasets: [
              {
                label: 'Doanh thu thực tế',
                data: revenue,
                borderColor: '#059669',
                backgroundColor: 'rgba(5,150,105,0.1)',
                fill: true,
                tension: 0.35,
                pointRadius: 3
              },
              {
                label: 'Tổng chi phí',
                data: totalExpenseSeries,
                borderColor: '#dc2626',
                backgroundColor: 'rgba(220,38,38,0.1)',
                fill: true,
                tension: 0.35,
                pointRadius: 3
              }
            ]
          },
          options: {
            plugins: {
              legend: { display: true }
            },
            scales: {
              y: {
                ticks: { callback: value => `${(value / 1_000_000).toFixed(0)}M` }
              }
            }
          }
        });

        els.summary.innerHTML = `
          <div id="financialReport" class="grid gap-3 text-sm space-y-4">
            <div class="rounded-lg bg-blue-50 border border-blue-200 p-3">
              <div class="flex items-center justify-between mb-2">
                <span>💰 Tổng doanh thu</span>
                <strong class="text-blue-900">${formatCurrency(totalRevenue)}</strong>
              </div>
              <div class="text-xs text-blue-600 bg-white p-2 rounded font-mono">
                = SUM(Doanh thu đã thanh toán)
              </div>
            </div>

            <div class="rounded-lg bg-orange-50 border border-orange-200 p-3">
              <div class="flex items-center justify-between mb-2">
                <span>🧾 Chi phí vận hành</span>
                <strong class="text-orange-900">${formatCurrency(totalExpense)}</strong>
              </div>
              <div class="text-xs text-orange-600 bg-white p-2 rounded font-mono">
                = Chi phí phát sinh (điện, nước, mặt bằng...)
              </div>
            </div>

            <div class="rounded-lg bg-sky-50 border border-sky-200 p-3">
              <div class="flex items-center justify-between mb-2">
                <span>👥 Lương nhân viên</span>
                <strong class="text-sky-900">${formatCurrency(salaryTotal)}</strong>
              </div>
              <div class="text-xs text-sky-600 bg-white p-2 rounded font-mono">
                = SUM(LOAI_CHI_TIET='Lương nhân viên')
              </div>
            </div>

            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3">
              <div class="flex items-center justify-between mb-2">
                <span>📈 Lợi nhuận ròng</span>
                <strong class="text-emerald-900">${formatCurrency(profit)}</strong>
              </div>
              <div class="text-xs text-emerald-600 bg-white p-2 rounded font-mono">
                = Doanh thu thực tế - (Chi phí vận hành + Lương)
              </div>
            </div>

            <div class="rounded-lg bg-indigo-50 border border-indigo-200 p-3">
              <div class="flex items-center justify-between mb-2">
                <span>📊 Biên lợi nhuận</span>
                <strong class="text-indigo-900">${profitMargin.toFixed(2)}%</strong>
              </div>
              <div class="text-xs text-indigo-600 bg-white p-2 rounded font-mono">
                = (Lợi nhuận ÷ Doanh thu) × 100%
              </div>
            </div>
          </div>
        `;

        els.revenueCurrent.textContent = formatCurrency(monthSnapshot.revenue);
        els.salaryCurrent.textContent = formatCurrency(monthSnapshot.salary);
        els.incidentalCurrent.textContent = formatCurrency(monthSnapshot.expense);
        els.monthProfit.textContent = formatCurrency(monthSnapshot.profit);
        const monthMargin = monthSnapshot.revenue === 0 ? 0 : (monthSnapshot.profit / monthSnapshot.revenue) * 100;
        els.profitRate.textContent = `${monthMargin.toFixed(1)}% biên lợi nhuận tháng`;
        els.salaryCurrent.textContent = formatCurrency(latestSalary);
        els.incidentalCurrent.textContent = formatCurrency(latestExpense);
        els.monthProfit.textContent = formatCurrency(latestProfit);
        els.totalRevenue.textContent = formatCurrency(totalRevenue);
        els.revenueInfo.textContent = `${labels.length} chu kỳ được thống kê`;
        els.totalExpense.textContent = formatCurrency(totalCost);
        els.expenseInfo.textContent = `Gồm chi phí vận hành + lương`;
        els.salaryFund.textContent = formatCurrency(salaryTotal);
        els.status.textContent = profit < 0 ? 'Lỗ - cần hành động' : (profitMargin < 8 ? 'Biên lợi nhuận thấp' : 'Khỏe mạnh');
        els.statusSub.textContent = profit < 0 ? 'Ưu tiên rà soát chi phí cố định' : `Biên lợi nhuận ${profitMargin.toFixed(1)}%`;
        els.statusBanner.textContent = profit < 0 ? 'Cảnh báo: tổng chi vượt doanh thu, hãy lập kế hoạch tiết giảm.' : 'Dòng tiền ổn định, có thể đề xuất mở rộng dịch vụ.';
        els.trendBadge.textContent = `${trend >= 0 ? '+' : ''}${trend.toFixed(1)}%`;
        els.trendLabel.textContent = trend >= 0 ? 'Doanh thu tăng so với kỳ trước' : 'Doanh thu giảm - theo dõi sát';
        els.profitRate.className = `text-xs font-medium ${monthMargin < 0 ? 'text-rose-100' : monthMargin < 8 ? 'text-amber-100' : 'text-emerald-100'}`;

        const maxValue = Math.max(totalRevenue, totalCost, Math.abs(profit));
        setProgress(els.revenueProgress, els.revenueProgressText, totalRevenue, maxValue);
        setProgress(els.expenseProgress, els.expenseProgressText, totalCost, maxValue);
        setProgress(els.profitProgress, els.profitProgressText, profit > 0 ? profit : 0, maxValue);

        renderPulse([
          { label: 'Biên lợi nhuận', value: `${profitMargin.toFixed(1)}%`, tone: profitMargin < 0 ? 'bad' : profitMargin < 8 ? 'warn' : 'good' },
          { label: 'Chi phí vận hành', value: formatCurrency(totalExpense), tone: 'warn' },
          { label: 'Quỹ lương', value: formatCurrency(salaryTotal), tone: 'warn' },
          { label: 'Chu kỳ', value: timeFilter.options[timeFilter.selectedIndex].text, tone: 'good' }
        ]);

        const activityRows = [];
        if (labels.length) {
          activityRows.push({
            title: `Chốt số liệu ${labels.at(-1)}`,
            badge: profit >= 0 ? 'Ổn định' : 'Cảnh báo',
            detail: `Doanh thu ${formatCurrency(latestRevenue)} | Chi phí ${formatCurrency(latestTotalExpense)}`,
            tone: profit >= 0 ? 'good' : 'bad'
          });
        }
        activityRows.push({
          title: 'Cập nhật KPI quỹ lương',
          badge: `${(salaryTotal / (totalCost || 1) * 100).toFixed(1)}%`,
          detail: 'Tỷ trọng quỹ lương trên tổng chi',
          tone: 'warn'
        });
        activityRows.push({
          title: 'Phân tích xu hướng doanh thu',
          badge: `${trend >= 0 ? '+' : ''}${trend.toFixed(1)}%`,
          detail: trend >= 0 ? 'Doanh thu cải thiện, tiếp tục duy trì chiến dịch hiện tại.' : 'Doanh thu giảm, xem xét gói khuyến mãi.',
          tone: trend >= 0 ? 'good' : 'bad'
        });
        renderActivityFeed(activityRows);
      } catch (error) {
        console.error(error);
        els.summary.innerHTML = '<p class="text-sm text-rose-600">Không thể tải dữ liệu. Vui lòng thử lại sau.</p>';
      }
    }

    async function loadExpenses() {
      try {
        const res = await fetch(`./components/get_expense_table.php?branch=${branchField.value}`);
        els.expenseTable.innerHTML = await res.text();
      } catch (error) {
        els.expenseTable.innerHTML = '<p class="text-sm text-rose-600">Không thể tải bảng chi phí.</p>';
      }
    }

    async function loadServiceShare() {
      try {
        els.serviceShareCaption.textContent = 'Đang tải...';
        const res = await fetch(`./components/get_service_share.php?branch=${branchField.value}&filter=${timeFilter.value}`);
        const data = await res.json();

        if (charts.service) charts.service.destroy();

        if (!Array.isArray(data) || !data.length) {
          els.serviceShareEmpty.classList.remove('hidden');
          els.serviceShareCaption.textContent = 'Không có dữ liệu';
          return;
        }

        els.serviceShareEmpty.classList.add('hidden');
        els.serviceShareCaption.textContent = `${data.length} dịch vụ`;
        const ctx = document.getElementById('serviceShareChart').getContext('2d');
        charts.service = new Chart(ctx, {
          type: 'doughnut',
          data: {
            labels: data.map(item => item.label),
            datasets: [{
              data: data.map(item => item.value),
              backgroundColor: ['#4f46e5', '#22d3ee', '#f97316', '#10b981', '#ec4899', '#a855f7']
            }]
          },
          options: { plugins: { legend: { position: 'bottom' } } }
        });
      } catch (error) {
        console.error(error);
        els.serviceShareCaption.textContent = 'Lỗi tải dữ liệu';
        els.serviceShareEmpty.classList.remove('hidden');
        els.serviceShareEmpty.textContent = 'Không thể tải thống kê dịch vụ.';
      }
    }

    async function loadTimeSlotTrends() {
      try {
        const res = await fetch(`./components/get_time_slot_trends.php?branch=${branchField.value}`);
        const data = await res.json();
        if (charts.slot) charts.slot.destroy();

        if (!Array.isArray(data) || !data.length) {
          els.timeSlotEmpty.classList.remove('hidden');
          return;
        }

        els.timeSlotEmpty.classList.add('hidden');
        const ctx = document.getElementById('timeSlotChart').getContext('2d');
        charts.slot = new Chart(ctx, {
          type: 'bar',
          data: {
            labels: data.map(item => item.label),
            datasets: [{
              label: 'Số lịch hẹn',
              data: data.map(item => item.value),
              backgroundColor: '#6366f1'
            }]
          },
          options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
          }
        });
      } catch (error) {
        console.error(error);
        els.timeSlotEmpty.classList.remove('hidden');
        els.timeSlotEmpty.textContent = 'Không thể tải thống kê khung giờ.';
      }
    }

    async function loadStaffLoad() {
      try {
        const res = await fetch(`./components/get_staff_assignments.php?branch=${branchField.value}&filter=${timeFilter.value}`);
        const data = await res.json();

        if (!Array.isArray(data) || !data.length) {
          els.staffLoadList.innerHTML = '<li class="text-gray-500">Chưa có phân công nào trong kỳ.</li>';
          return;
        }

        els.staffLoadList.innerHTML = data
          .map(item => `
            <li class="flex items-center justify-between rounded-2xl border border-gray-100 px-4 py-3">
              <div>
                <p class="font-semibold text-gray-900">${item.name}</p>
                <p class="text-xs text-gray-500">${item.count} nhiệm vụ</p>
              </div>
              <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">Top</span>
            </li>
          `)
          .join('');
      } catch (error) {
        console.error(error);
        els.staffLoadList.innerHTML = '<li class="text-rose-600">Không thể tải dữ liệu nhân sự.</li>';
      }
    }

    window.toggleExpenseForm = function () {
      document.getElementById('expenseFormWrapper').classList.toggle('hidden');
    };

    window.printReport = function () {
      const summaryRows = Array.from(document.querySelectorAll('#financialReport [data-field]'))
        .map(node => ({
          label: node.querySelector('span').textContent,
          value: node.querySelector('strong').textContent
        }));

      const expenseTable = document.querySelector('#expenseTable table');
      if (expenseTable) {
        Array.from(expenseTable.querySelectorAll('tbody tr')).forEach(tr => {
          const cells = tr.querySelectorAll('td');
          if (cells.length >= 2) {
            summaryRows.push({ label: cells[0].textContent.trim(), value: cells[1].textContent.trim() });
          }
        });
      }

      const today = new Date();
      const reportWindow = window.open('', '', 'width=900,height=700');
      const tableRows = summaryRows
        .map(row => `<tr><td>${row.label}</td><td>${row.value}</td></tr>`)
        .join('');

      reportWindow.document.write(`
        <html>
          <head>
            <title>Báo cáo chi nhánh ${branchName}</title>
            <style>
              body { font-family: Arial, sans-serif; padding: 40px; background: #f8fafc; color: #0f172a; }
              h1 { text-align: center; font-size: 26px; margin-bottom: 4px; }
              h2 { text-align: center; font-size: 18px; margin-bottom: 24px; color: #475569; }
              table { width: 100%; border-collapse: collapse; background: white; border: 1px solid #e2e8f0; }
              th, td { border: 1px solid #e2e8f0; padding: 10px; }
              th { background: #e0e7ff; }
              td:last-child { text-align: right; font-weight: bold; }
              .signatures { display: flex; justify-content: space-between; margin-top: 40px; padding: 0 10%; }
              .signatures div { text-align: center; }
              .signatures div::after { content: "________________"; display: block; margin-top: 40px; }
            </style>
          </head>
          <body>
            <h1>STYGIAN BLUE</h1>
            <h2>BÁO CÁO TÀI CHÍNH - ${branchName} (${today.toLocaleDateString('vi-VN')})</h2>
            <table>
              <thead><tr><th>Mục</th><th>Giá trị</th></tr></thead>
              <tbody>${tableRows}</tbody>
            </table>
            <div class="signatures">
              <div>Người lập báo cáo</div>
              <div>Quản lý chi nhánh</div>
            </div>
          </body>
        </html>
      `);
      reportWindow.document.close();
      reportWindow.print();
    };

    const expenseForm = document.getElementById('expenseForm');
    const expenseRows = document.getElementById('expenseRows');
    const taxMode = document.getElementById('taxMode');
    const taxValue = document.getElementById('taxValue');

    function addExpenseRow(values = {}) {
      const index = expenseRows.children.length;
      const template = `
        <div class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm md:grid-cols-[2fr_1fr]" data-row="${index}">
          <div class="grid gap-3 sm:grid-cols-2">
            <input type="text" name="expense_name[]" placeholder="Tên chi phí" value="${values.name || ''}" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-indigo-500" required>
            <input type="number" name="expense_amount[]" placeholder="Giá trị (VND)" min="0" step="1000" value="${values.amount || ''}" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-indigo-500" required>
          </div>
          <div class="flex items-center gap-2">
            <input type="text" name="expense_note[]" placeholder="Ghi chú" value="${values.note || ''}" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-indigo-500">
            <button type="button" class="text-sm text-rose-600" data-action="remove-row">✕</button>
          </div>
        </div>`;
      expenseRows.insertAdjacentHTML('beforeend', template);
    }

    addExpenseRow();
    addExpenseRow();

    document.getElementById('addExpenseRow').addEventListener('click', () => addExpenseRow());

    expenseRows.addEventListener('click', (event) => {
      const button = event.target.closest('[data-action="remove-row"]');
      if (!button) return;
      const row = button.closest('[data-row]');
      if (row) {
        row.remove();
      }
      if (!expenseRows.children.length) {
        addExpenseRow();
      }
    });

    taxMode.addEventListener('change', () => {
      const manual = taxMode.value === 'manual';
      taxValue.disabled = !manual;
      if (!manual) taxValue.value = '';
    });

    expenseForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const formData = new FormData(expenseForm);
      try {
        const response = await fetch('./components/save_expenses.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.status !== 'success') throw new Error(result.message || 'Không thể lưu');
        document.getElementById('expenseMessage').textContent = 'Đã lưu chi phí thành công.';
        document.getElementById('expenseMessage').className = 'text-sm font-semibold text-emerald-700';
        await loadExpenses();
        await fetchAndRenderChart();
      } catch (error) {
        document.getElementById('expenseMessage').textContent = error.message;
        document.getElementById('expenseMessage').className = 'text-sm font-semibold text-rose-700';
      }
    });

    document.getElementById('refreshMetrics').addEventListener('click', async () => {
      await fetchAndRenderChart();
      await loadServiceShare();
      await loadStaffLoad();
    });

    timeFilter.addEventListener('change', async () => {
      await fetchAndRenderChart();
      await loadServiceShare();
      await loadStaffLoad();
    });

    document.getElementById('btnReportAdmin').addEventListener('click', () => {
      alert('Đã tạo yêu cầu gửi báo cáo cho admin.');
    });

    updateClock();
    setInterval(updateClock, 60 * 1000);

    fetchAndRenderChart();
    loadExpenses();
    loadServiceShare();
    loadTimeSlotTrends();
    loadStaffLoad();
  })();
</script>
