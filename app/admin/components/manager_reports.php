<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../../database/config.php';

$branchId   = isset($_SESSION['branch_id']) ? intval($_SESSION['branch_id']) : 0;
$branchName = isset($_SESSION['branch_name']) ? $_SESSION['branch_name'] : 'Chi nhánh của tôi';

if ($branchId <= 0) {
  $branchId   = 1;
  $branchName = 'Chi nhánh Demo';
}
?>
<div id="managerReports" class="space-y-8 text-gray-800">
  <input type="hidden" id="reportBranchFilter" value="cn<?= $branchId ?>">
  <header class="rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-900 p-8 text-white shadow-2xl">
    <div class="flex flex-wrap items-center justify-between gap-6">
      <div>
        <p class="text-xs uppercase tracking-[0.35em] text-white/60">Reports</p>
        <h1 class="text-3xl font-bold">Thống kê &amp; Báo cáo</h1>
        <p class="text-sm text-white/80 mt-1">Chi nhánh: <?= htmlspecialchars($branchName) ?></p>
      </div>
      <div class="flex gap-3">
        <button id="btnExportSnapshot" class="rounded-full border border-white/40 px-5 py-2 text-sm font-semibold text-white/90 hover:bg-white/10">Tải snapshot PDF</button>
        <button id="btnTriggerRefresh" class="rounded-full bg-white/90 px-5 py-2 text-sm font-semibold text-slate-900 hover:bg-white">Đồng bộ dữ liệu</button>
      </div>
    </div>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-sm text-white/90">
      <div class="rounded-2xl bg-white/10 p-4">
        <p class="text-xs uppercase text-white/70">Độ phủ lịch hẹn</p>
        <p id="reportKpiCoverage" class="text-2xl font-semibold">--</p>
      </div>
      <div class="rounded-2xl bg-white/10 p-4">
        <p class="text-xs uppercase text-white/70">Thời gian trung bình</p>
        <p id="reportKpiDuration" class="text-2xl font-semibold">--</p>
      </div>
      <div class="rounded-2xl bg-white/10 p-4">
        <p class="text-xs uppercase text-white/70">Mức độ hài lòng</p>
        <p id="reportKpiFeedback" class="text-2xl font-semibold">--</p>
      </div>
      <div class="rounded-2xl bg-white/10 p-4">
        <p class="text-xs uppercase text-white/70">Tỷ lệ tái đặt</p>
        <p id="reportKpiReturn" class="text-2xl font-semibold">--</p>
      </div>
    </div>
  </header>

  <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <article class="xl:col-span-2 rounded-3xl bg-white p-6 shadow-lg border border-gray-100">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-xs uppercase text-gray-500 tracking-widest">Booking &amp; Revenue</p>
          <h2 class="text-xl font-semibold text-gray-900">Heatmap lịch hẹn</h2>
        </div>
        <div class="flex gap-3">
          <select id="reportViewMode" class="rounded-xl border border-gray-200 px-4 py-2 text-sm">
            <option value="week">Theo tuần</option>
            <option value="month" selected>Theo tháng</option>
            <option value="quarter">Theo quý</option>
          </select>
          <input type="month" id="reportMonthPicker" value="<?= date('Y-m') ?>" class="rounded-xl border border-gray-200 px-4 py-2 text-sm" />
        </div>
      </div>
      <div id="scheduleHeatmap" class="mt-6 min-h-[280px] grid grid-cols-7 gap-2 text-xs"></div>
      <p id="scheduleHeatmapEmpty" class="mt-4 text-sm text-gray-500">Đang tải dữ liệu lịch hẹn...</p>
    </article>

    <article class="rounded-3xl bg-white p-6 shadow-lg border border-gray-100">
      <h2 class="text-lg font-semibold text-gray-900">Top dịch vụ mang doanh thu</h2>
      <canvas id="reportServiceChart" class="mt-6" height="170"></canvas>
      <p id="reportServiceEmpty" class="mt-4 text-sm text-gray-500">Đang tải dữ liệu...</p>
      <ul id="reportServiceList" class="mt-4 space-y-3 text-sm"></ul>
    </article>
  </section>

  <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <article class="rounded-3xl bg-white p-6 shadow-lg border border-gray-100">
      <div class="flex flex-wrap items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900">Hiệu suất nhân sự</h2>
        <select id="staffMetricFilter" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
          <option value="assignments" selected>Số nhiệm vụ</option>
          <option value="hours">Giờ công</option>
        </select>
      </div>
      <canvas id="staffPerformanceChart" class="mt-6" height="160"></canvas>
      <p id="staffPerformanceEmpty" class="mt-4 text-sm text-gray-500">Chưa có dữ liệu.</p>
    </article>

    <article class="rounded-3xl bg-white p-6 shadow-lg border border-gray-100">
      <div class="flex flex-wrap items-center justify-between">
        <div>
          <p class="text-xs uppercase text-gray-500 tracking-widest">Feedback &amp; tỷ lệ phàn nàn</p>
          <h2 class="text-lg font-semibold text-gray-900">Chỉ số hài lòng khách hàng</h2>
        </div>
        <button id="btnExportFeedback" class="rounded-full border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Xuất CSV</button>
      </div>
      <canvas id="feedbackScoreChart" class="mt-6" height="160"></canvas>
      <p id="feedbackScoreEmpty" class="mt-4 text-sm text-gray-500">Chưa có đánh giá nào trong kỳ.</p>
      <ul id="feedbackAlerts" class="mt-4 space-y-3 text-sm text-gray-700"></ul>
    </article>
  </section>

  <section class="rounded-3xl bg-white p-6 shadow-lg border border-gray-100">
    <div class="flex flex-wrap items-center justify-between">
      <div>
        <p class="text-xs uppercase text-gray-500 tracking-widest">Operational intelligence</p>
        <h2 class="text-lg font-semibold text-gray-900">Danh sách cảnh báo &amp; gợi ý hành động</h2>
      </div>
      <button id="btnDownloadFullReport" class="rounded-full bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Tải báo cáo chi tiết</button>
    </div>
    <ul id="reportAlerts" class="mt-6 grid gap-4 md:grid-cols-2 text-sm text-gray-700"></ul>
  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  (function () {
    const $ = (id) => document.getElementById(id);

    const Els = {
      heatmap: $('scheduleHeatmap'),
      heatmapEmpty: $('scheduleHeatmapEmpty'),
      serviceChartEmpty: $('reportServiceEmpty'),
      serviceList: $('reportServiceList'),
      staffEmpty: $('staffPerformanceEmpty'),
      feedbackEmpty: $('feedbackScoreEmpty'),
      feedbackAlerts: $('feedbackAlerts'),
      alerts: $('reportAlerts'),
      kpiCoverage: $('reportKpiCoverage'),
      kpiDuration: $('reportKpiDuration'),
      kpiFeedback: $('reportKpiFeedback'),
      kpiReturn: $('reportKpiReturn'),
    };

    const charts = { service: null, staff: null, feedback: null };

    const branch = $('reportBranchFilter').value;
    const viewMode = $('reportViewMode');
    const monthPicker = $('reportMonthPicker');
    const staffMetricFilter = $('staffMetricFilter');

    async function fetchJSON(url) {
      const res = await fetch(url);
      if (!res.ok) throw new Error('Network error');
      return res.json();
    }

    async function loadHeatmap() {
      Els.heatmapEmpty.textContent = 'Đang tải dữ liệu lịch hẹn...';
      Els.heatmapEmpty.classList.remove('hidden');
      Els.heatmap.innerHTML = '';

      try {
        const params = new URLSearchParams({
          branch,
          mode: viewMode.value,
          month: monthPicker.value,
        });
        const data = await fetchJSON(`./components/report_get_schedule_heatmap.php?${params.toString()}`);

        if (!Array.isArray(data.cells) || !data.cells.length) {
          Els.heatmapEmpty.textContent = 'Chưa có lịch hẹn trong kỳ này.';
          return;
        }

        Els.heatmapEmpty.classList.add('hidden');
        Els.heatmap.innerHTML = data.cells.map(cell => `
          <div class="rounded-xl border border-gray-100 p-3 text-center ${cell.intensity}">
            <p class="text-xs font-semibold text-gray-500">${cell.label}</p>
            <p class="text-lg font-bold text-gray-900">${cell.count}</p>
            <p class="text-[11px] text-gray-500">Lịch hẹn</p>
          </div>
        `).join('');

        Els.kpiCoverage.textContent = data.coverage;
        Els.kpiDuration.textContent = data.avgDuration;
        Els.kpiReturn.textContent = data.returnRate;
      } catch (error) {
        console.error(error);
        Els.heatmapEmpty.textContent = 'Không thể tải dữ liệu heatmap.';
      }
    }

    async function loadServiceRevenue() {
      try {
        const params = new URLSearchParams({ branch, filter: viewMode.value });
        const data = await fetchJSON(`./components/report_get_service_revenue.php?${params.toString()}`);

        if (charts.service) charts.service.destroy();

        if (!Array.isArray(data.items) || !data.items.length) {
          Els.serviceChartEmpty.textContent = 'Chưa có doanh thu dịch vụ.';
          Els.serviceChartEmpty.classList.remove('hidden');
          Els.serviceList.innerHTML = '';
          return;
        }

        Els.serviceChartEmpty.classList.add('hidden');
        Els.serviceList.innerHTML = data.items.map(item => `
          <li class="flex items-center justify-between rounded-2xl border border-gray-100 px-4 py-2">
            <span>${item.label}</span>
            <strong>${item.revenue}</strong>
          </li>
        `).join('');

        const ctx = document.getElementById('reportServiceChart').getContext('2d');
        charts.service = new Chart(ctx, {
          type: 'bar',
          data: {
            labels: data.items.map(i => i.label),
            datasets: [{
              label: 'Doanh thu',
              data: data.items.map(i => i.numeric),
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
        Els.serviceChartEmpty.textContent = 'Không thể tải thống kê dịch vụ.';
        Els.serviceChartEmpty.classList.remove('hidden');
      }
    }

    async function loadStaffPerformance() {
      try {
        const params = new URLSearchParams({ branch, metric: staffMetricFilter.value, filter: viewMode.value });
        const data = await fetchJSON(`./components/report_get_staff_performance.php?${params.toString()}`);

        if (charts.staff) charts.staff.destroy();

        if (!Array.isArray(data.items) || !data.items.length) {
          Els.staffEmpty.classList.remove('hidden');
          return;
        }

        Els.staffEmpty.classList.add('hidden');
        const ctx = document.getElementById('staffPerformanceChart').getContext('2d');
        charts.staff = new Chart(ctx, {
          type: 'radar',
          data: {
            labels: data.items.map(i => i.name),
            datasets: [{
              label: data.label,
              data: data.items.map(i => i.value),
              backgroundColor: 'rgba(59,130,246,0.2)',
              borderColor: '#3b82f6',
            }]
          },
          options: {
            plugins: { legend: { display: false } },
            scales: { r: { beginAtZero: true } }
          }
        });
      } catch (error) {
        console.error(error);
        Els.staffEmpty.classList.remove('hidden');
        Els.staffEmpty.textContent = 'Không thể tải hiệu suất nhân sự.';
      }
    }

    async function loadFeedback() {
      try {
        const params = new URLSearchParams({ branch, filter: viewMode.value });
        const data = await fetchJSON(`./components/report_get_feedback_metrics.php?${params.toString()}`);

        if (charts.feedback) charts.feedback.destroy();

        if (!Array.isArray(data.points) || !data.points.length) {
          Els.feedbackEmpty.classList.remove('hidden');
          Els.feedbackAlerts.innerHTML = '';
          return;
        }

        Els.feedbackEmpty.classList.add('hidden');
        const ctx = document.getElementById('feedbackScoreChart').getContext('2d');
        charts.feedback = new Chart(ctx, {
          type: 'line',
          data: {
            labels: data.points.map(i => i.label),
            datasets: [{
              label: 'Điểm đánh giá trung bình',
              data: data.points.map(i => i.score),
              borderColor: '#0ea5e9',
              tension: 0.4
            }]
          },
          options: { plugins: { legend: { display: false } } }
        });

        Els.kpiFeedback.textContent = data.overallScore || '--';
        Els.feedbackAlerts.innerHTML = data.alerts.map(alert => `
          <li class="rounded-2xl border border-${alert.level}-200 bg-${alert.level}-50 px-4 py-3">
            <p class="font-semibold text-${alert.level}-700">${alert.title}</p>
            <p class="text-${alert.level}-800 text-sm">${alert.message}</p>
          </li>
        `).join('');
      } catch (error) {
        console.error(error);
        Els.feedbackEmpty.classList.remove('hidden');
        Els.feedbackEmpty.textContent = 'Không thể tải dữ liệu đánh giá.';
      }
    }

    async function loadAlerts() {
      try {
        const params = new URLSearchParams({ branch, filter: viewMode.value });
        const data = await fetchJSON(`./components/report_get_alerts.php?${params.toString()}`);

        if (!Array.isArray(data.alerts) || !data.alerts.length) {
          Els.alerts.innerHTML = '<li class="text-sm text-gray-500">Không có cảnh báo nào.</li>';
          return;
        }

        Els.alerts.innerHTML = data.alerts.map(alert => `
          <li class="rounded-3xl border border-${alert.level}-200 bg-${alert.level}-50 p-4">
            <p class="text-xs uppercase text-${alert.level}-600">${alert.category}</p>
            <h4 class="text-base font-semibold text-gray-900">${alert.title}</h4>
            <p class="text-sm text-gray-700 mt-1">${alert.description}</p>
            <p class="text-xs text-gray-500 mt-2">Đề xuất: ${alert.action}</p>
          </li>
        `).join('');
      } catch (error) {
        console.error(error);
        Els.alerts.innerHTML = '<li class="text-sm text-rose-600">Không thể tải danh sách cảnh báo.</li>';
      }
    }

    async function refreshAll() {
      await Promise.all([
        loadHeatmap(),
        loadServiceRevenue(),
        loadStaffPerformance(),
        loadFeedback(),
        loadAlerts(),
      ]);
    }

    document.getElementById('btnTriggerRefresh').addEventListener('click', refreshAll);
    viewMode.addEventListener('change', refreshAll);
    monthPicker.addEventListener('change', loadHeatmap);
    staffMetricFilter.addEventListener('change', loadStaffPerformance);

    document.getElementById('btnExportSnapshot').addEventListener('click', () => {
      alert('Tính năng tải snapshot PDF đang được phát triển.');
    });
    document.getElementById('btnDownloadFullReport').addEventListener('click', () => {
      alert('Bạn có thể xuất báo cáo chi tiết sau khi hoàn thiện backend.');
    });
    document.getElementById('btnExportFeedback').addEventListener('click', () => {
      alert('Xuất CSV đánh giá sẽ khả dụng trong bản tiếp theo.');
    });

    refreshAll();
  })();
</script>
