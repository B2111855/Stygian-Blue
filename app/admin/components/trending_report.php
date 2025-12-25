<?php
include '../../database/config.php';

$validFilters = ['month', 'quarter', 'year'];
$currentFilter = $_GET['filter'] ?? 'year';
if (!in_array($currentFilter, $validFilters, true)) {
    $currentFilter = 'year';
}

$currentBranch = $_GET['branch'] ?? 'all';
if ($currentBranch !== 'all' && !preg_match('/^cn\d+$/i', $currentBranch)) {
    $currentBranch = 'all';
}

$branchQuery = mysqli_query($conn, "SELECT ID_CN, TEN_CN FROM chi_nhanh ORDER BY TEN_CN ASC");
$branches = [];
while ($row = mysqli_fetch_assoc($branchQuery)) {
    $branches[] = $row;
}
?>

<?php if (!defined('ADMIN_CHART_JS')): ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <?php define('ADMIN_CHART_JS', true); ?>
<?php endif; ?>

<div class="space-y-6" id="adminReport">
  <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
    <div>
      <h1 class="text-3xl font-semibold text-indigo-900">Báo cáo xu hướng</h1>
      <p class="text-sm text-gray-500">Theo dõi sức khỏe tài chính, dịch vụ và hiệu suất nhân viên theo thời gian thực.</p>
    </div>
    <div class="flex gap-2">
      <button id="refreshReport" class="px-4 py-2 text-sm font-semibold text-indigo-700 bg-white border border-indigo-200 rounded-lg shadow-sm hover:bg-indigo-50">Làm mới</button>
      <button id="exportReport" class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg shadow hover:bg-indigo-500">Xuất báo cáo</button>
    </div>
  </div>

  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <label class="flex flex-col gap-1 text-sm font-semibold text-gray-600">
      <span>Chi nhánh</span>
      <select id="branchFilter" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="all" <?= $currentBranch === 'all' ? 'selected' : '' ?>>Tất cả</option>
        <?php foreach ($branches as $branch): ?>
          <?php $value = 'cn' . $branch['ID_CN']; ?>
          <option value="<?= $value ?>" <?= $currentBranch === $value ? 'selected' : '' ?>><?= htmlspecialchars($branch['TEN_CN']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="flex flex-col gap-1 text-sm font-semibold text-gray-600">
      <span>Chu kỳ</span>
      <select id="timeFilter" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="month" <?= $currentFilter === 'month' ? 'selected' : '' ?>>Theo tháng</option>
        <option value="quarter" <?= $currentFilter === 'quarter' ? 'selected' : '' ?>>Theo quý</option>
        <option value="year" <?= $currentFilter === 'year' ? 'selected' : '' ?>>Theo năm</option>
      </select>
    </label>

    <div class="flex flex-col gap-1 text-sm font-semibold text-gray-600">
      <span>Trạng thái dữ liệu</span>
      <div id="reportLoading" class="flex items-center gap-3 rounded-lg border border-dashed border-indigo-200 bg-indigo-50 px-3 py-2 text-indigo-700">
        <span class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-indigo-600 border-t-transparent"></span>
        <span>Đang tải dữ liệu...</span>
      </div>
      <div id="reportError" class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-600" aria-live="polite"></div>
    </div>

    <div class="flex flex-col gap-1 text-sm font-semibold text-gray-600">
      <span>Đồng bộ</span>
      <div class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-500">
        Cập nhật gần nhất: <span id="reportTimestamp">—</span>
      </div>
    </div>
  </div>

  <div id="reportContent" class="space-y-6 opacity-0 pointer-events-none hidden transition-opacity duration-300">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
        <p class="text-xs uppercase tracking-wide text-gray-400">Doanh thu</p>
        <p id="metricRevenue" class="mt-2 text-2xl font-semibold text-gray-900">—</p>
        <p class="text-xs text-gray-500">So với kỳ trước <span id="metricRevenueTrend" class="ml-1 font-semibold">0%</span></p>
      </div>
      <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
        <p class="text-xs uppercase tracking-wide text-gray-400">Chi phí</p>
        <p id="metricExpense" class="mt-2 text-2xl font-semibold text-gray-900">—</p>
        <p class="text-xs text-gray-500">So với kỳ trước <span id="metricExpenseTrend" class="ml-1 font-semibold">0%</span></p>
      </div>
      <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
        <p class="text-xs uppercase tracking-wide text-gray-400">Lợi nhuận</p>
        <p id="metricProfit" class="mt-2 text-2xl font-semibold text-gray-900">—</p>
        <p class="text-xs text-gray-500">So với kỳ trước <span id="metricProfitTrend" class="ml-1 font-semibold">0%</span></p>
      </div>
      <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
        <p class="text-xs uppercase tracking-wide text-gray-400">Chi phí lương</p>
        <p id="metricSalary" class="mt-2 text-2xl font-semibold text-gray-900">—</p>
        <p class="text-xs text-gray-500">Tổng lũy kế trong phạm vi</p>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-indigo-900">Biểu đồ tài chính</h2>
          <span class="text-xs text-gray-400">Chi tiết dòng tiền</span>
        </div>
        <canvas id="financialChart" class="mt-4 h-64"></canvas>
        <div id="summary" class="mt-4 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700"></div>
      </div>

      <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-indigo-900">Phân công nhân viên</h2>
          <span class="text-xs text-gray-400">Tần suất đảm nhiệm</span>
        </div>
        <canvas id="staffAssignChart" class="mt-4 h-64"></canvas>
        <div id="staffAssignSummary" class="mt-4 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700"></div>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-indigo-900">Tỉ lệ dịch vụ</h2>
          <span class="text-xs text-gray-400">Phân bổ đặt lịch</span>
        </div>
        <canvas id="serviceShareChart" class="mt-4 h-64"></canvas>
        <div id="serviceSummary" class="mt-4 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700"></div>
      </div>

      <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-indigo-900">Khung giờ đặt lịch</h2>
          <span class="text-xs text-gray-400">Độ nóng theo giờ</span>
        </div>
        <canvas id="timeSlotChart" class="mt-4 h-64"></canvas>
        <div id="timeSlotSummary" class="mt-4 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700"></div>
      </div>
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold text-indigo-900">Góc nhìn nhanh</h2>
          <p class="text-sm text-gray-500">Máy học nhỏ gợi ý các điểm đáng chú ý trong kỳ được chọn.</p>
        </div>
      </div>
      <ul id="insightList" class="mt-4 list-disc space-y-2 pl-5 text-sm text-gray-700"></ul>
    </div>
  </div>
</div>

<script>
(function () {
  const branchSelect = document.getElementById('branchFilter');
  const filterSelect = document.getElementById('timeFilter');
  const refreshBtn = document.getElementById('refreshReport');
  const exportBtn = document.getElementById('exportReport');

  if (!branchSelect || !filterSelect || !refreshBtn || !exportBtn) {
    return;
  }

  const els = {
    loading: document.getElementById('reportLoading'),
    error: document.getElementById('reportError'),
    content: document.getElementById('reportContent'),
    timestamp: document.getElementById('reportTimestamp'),
    summary: document.getElementById('summary'),
    serviceSummary: document.getElementById('serviceSummary'),
    slotSummary: document.getElementById('timeSlotSummary'),
    staffSummary: document.getElementById('staffAssignSummary'),
    insightList: document.getElementById('insightList'),
    metrics: {
      revenue: document.getElementById('metricRevenue'),
      expense: document.getElementById('metricExpense'),
      profit: document.getElementById('metricProfit'),
      salary: document.getElementById('metricSalary'),
      revenueTrend: document.getElementById('metricRevenueTrend'),
      expenseTrend: document.getElementById('metricExpenseTrend'),
      profitTrend: document.getElementById('metricProfitTrend')
    }
  };

  const state = {
    branch: branchSelect.value,
    filter: filterSelect.value,
    charts: { financial: null, service: null, slot: null, staff: null },
    snapshot: null
  };

  refreshBtn.addEventListener('click', updateDashboard);
  exportBtn.addEventListener('click', exportReport);
  branchSelect.addEventListener('change', () => {
    state.branch = branchSelect.value;
    updateDashboard();
  });
  filterSelect.addEventListener('change', () => {
    state.filter = filterSelect.value;
    updateDashboard();
  });

  updateDashboard();

  async function updateDashboard() {
    setLoading(true);
    clearError();
    try {
      const [finance, service, slots, staff] = await Promise.all([
        fetchJSON(`components/get_financial_data.php?filter=${state.filter}&branch=${state.branch}`),
        fetchJSON(`components/get_service_share.php?branch=${state.branch}&filter=${state.filter}`),
        fetchJSON(`components/get_time_slot_trends.php?branch=${state.branch}&filter=${state.filter}`),
        fetchJSON(`components/get_staff_assignments.php?branch=${state.branch}&filter=${state.filter}`)
      ]);

      state.snapshot = { finance, service, slots, staff };
      renderFinance(finance);
      renderService(service);
      renderSlot(slots);
      renderStaff(staff);
      updateInsights(finance, service, slots, staff);
      setContentVisibility(true);
      els.timestamp.textContent = new Date().toLocaleString('vi-VN');
    } catch (error) {
      showError(error.message || 'Không thể tải dữ liệu báo cáo.');
      if (!state.snapshot) {
        setContentVisibility(false);
      }
    } finally {
      setLoading(false);
    }
  }

  async function fetchJSON(url) {
    const response = await fetch(url);
    if (!response.ok) {
      throw new Error(`Máy chủ phản hồi lỗi ${response.status}`);
    }
    const payload = await response.json();
    if (payload && payload.success === false) {
      throw new Error(payload.message || 'Máy chủ trả về lỗi.');
    }
    return payload;
  }

  function renderFinance(data) {
    const ctx = document.getElementById('financialChart').getContext('2d');
    destroyChart('financial');
    const labels = Array.isArray(data.labels) && data.labels.length ? data.labels : ['Chưa có dữ liệu'];
    const revenue = Array.isArray(data.revenue) && data.revenue.length ? data.revenue : [0];
    const expense = Array.isArray(data.expense) && data.expense.length ? data.expense : [0];

    state.charts.financial = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: 'Doanh thu', data: revenue, borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,0.1)', tension: 0.3 },
          { label: 'Chi phí', data: expense, borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,0.08)', tension: 0.3 }
        ]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true } }
      }
    });

    updateFinancialSummary(data);
  }

  function renderService(dataset) {
    const ctx = document.getElementById('serviceShareChart').getContext('2d');
    destroyChart('service');
    if (!Array.isArray(dataset) || !dataset.length) {
      clearCanvas(ctx);
      els.serviceSummary.textContent = 'Chưa có dữ liệu đặt dịch vụ cho kỳ được chọn.';
      return;
    }

    state.charts.service = new Chart(ctx, {
      type: 'pie',
      data: {
        labels: dataset.map(item => item.label),
        datasets: [{
          label: 'Số lượt đặt',
          data: dataset.map(item => item.value),
          backgroundColor: ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#3b82f6'],
          borderWidth: 1
        }]
      },
      options: { plugins: { legend: { position: 'bottom' } } }
    });

    const total = dataset.reduce((sum, item) => sum + item.value, 0);
    const top = dataset[0];
    const percent = total ? ((top.value / total) * 100).toFixed(1) : 0;
    els.serviceSummary.textContent = `Dịch vụ nổi bật: ${top.label} (${percent}% của ${total.toLocaleString()} lượt).`;
  }

  function renderSlot(dataset) {
    const ctx = document.getElementById('timeSlotChart').getContext('2d');
    destroyChart('slot');
    if (!Array.isArray(dataset) || !dataset.length) {
      clearCanvas(ctx);
      els.slotSummary.textContent = 'Chưa có dữ liệu đặt lịch theo khung giờ.';
      return;
    }

    state.charts.slot = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: dataset.map(item => item.label),
        datasets: [{
          label: 'Số lượt',
          data: dataset.map(item => item.value),
          backgroundColor: '#6366f1',
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
      }
    });

    const total = dataset.reduce((sum, item) => sum + item.value, 0);
    const peak = dataset.reduce((max, item) => (item.value > max.value ? item : max), dataset[0]);
    els.slotSummary.textContent = `${peak.label} là khung giờ bận nhất (${peak.value.toLocaleString()} lượt trong tổng ${total.toLocaleString()} lượt).`;
  }

  function renderStaff(dataset) {
    const ctx = document.getElementById('staffAssignChart').getContext('2d');
    destroyChart('staff');
    if (!Array.isArray(dataset) || !dataset.length) {
      clearCanvas(ctx);
      els.staffSummary.textContent = 'Chưa có dữ liệu phân công nhân viên.';
      return;
    }

    state.charts.staff = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: dataset.map(item => item.name),
        datasets: [{
          label: 'Số lần phân công',
          data: dataset.map(item => item.count),
          backgroundColor: '#10b981',
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
      }
    });

    const total = dataset.reduce((sum, item) => sum + item.count, 0);
    const top = dataset.reduce((max, item) => (item.count > max.count ? item : max), dataset[0]);
    els.staffSummary.textContent = `${top.name} được phân công nhiều nhất với ${top.count.toLocaleString()} lượt trên ${total.toLocaleString()} lượt tổng.`;
  }

  function updateFinancialSummary(data) {
    const totalRevenue = sum(data.revenue);
    const totalOperatingExpense = sum(data.expense);
    
    // FIXED: Separate salary calculation - use array data if available
    const salarySeries = Array.isArray(data.salary_expense) ? data.salary_expense : [];
    const totalSalaryExpense = salarySeries.reduce((acc, value) => acc + Number(value || 0), 0);
    
    // FIXED: Profit = Revenue - Operating Expense - Salary
    const totalProfit = totalRevenue - totalOperatingExpense - totalSalaryExpense;

    els.summary.innerHTML = `
      <div class="grid gap-2 text-sm md:grid-cols-2">
        <div><strong>Doanh thu:</strong> ${formatCurrency(totalRevenue)}</div>
        <div><strong>Chi phí hoạt động:</strong> ${formatCurrency(totalOperatingExpense)}</div>
        <div><strong>Chi phí lương:</strong> ${formatCurrency(totalSalaryExpense)}</div>
        <div><strong>Lợi nhuận ròng:</strong> ${formatCurrency(totalProfit)}</div>
      </div>
    `;

    els.metrics.revenue.textContent = formatCurrency(totalRevenue);
    els.metrics.expense.textContent = formatCurrency(totalOperatingExpense);
    els.metrics.profit.textContent = formatCurrency(totalProfit);
    els.metrics.salary.textContent = formatCurrency(totalSalaryExpense);

    const revenueSeries = Array.isArray(data.revenue) ? data.revenue : [];
    const expenseSeries = Array.isArray(data.expense) ? data.expense : [];
    // FIXED: Profit per period = Revenue - Operating - Salary
    const profitSeries = revenueSeries.map((val, idx) => 
      Number(val || 0) - Number(expenseSeries[idx] || 0) - Number(salarySeries[idx] || 0)
    );

    setTrendBadge(els.metrics.revenueTrend, calculateGrowth(revenueSeries));
    setTrendBadge(els.metrics.expenseTrend, calculateGrowth(expenseSeries));
    setTrendBadge(els.metrics.profitTrend, calculateGrowth(profitSeries));
  }

  function updateInsights(finance, service, slots, staff) {
    const insights = [];

    if (Array.isArray(finance.labels) && finance.labels.length) {
      const lastLabel = finance.labels[finance.labels.length - 1];
      const growthObj = calculateGrowth(finance.revenue);
      const growthValue = growthObj.value || 0;
      const growthText = Number.isNaN(growthValue) || !Number.isFinite(growthValue) ? 'N/A' : Math.abs(growthValue).toFixed(1);
      const direction = growthValue >= 0 ? 'tăng' : 'giảm';
      insights.push(`Doanh thu kỳ "${lastLabel}" ${direction} ${growthText}% so với kỳ trước.`);
    } else {
      insights.push('Chưa có số liệu tài chính cho bộ lọc hiện tại.');
    }

    if (Array.isArray(service) && service.length) {
      const total = service.reduce((sum, item) => sum + item.value, 0);
      const share = total ? ((service[0].value / total) * 100).toFixed(1) : 0;
      insights.push(`Dịch vụ "${service[0].label}" dẫn đầu với ${share}% tổng lượt đặt.`);
    }

    if (Array.isArray(slots) && slots.length) {
      const peak = slots.reduce((max, item) => (item.value > max.value ? item : max), slots[0]);
      insights.push(`${peak.label} tiếp tục là khung giờ cao điểm với ${peak.value.toLocaleString()} lượt.`);
    }

    if (Array.isArray(staff) && staff.length) {
      const top = staff.reduce((max, item) => (item.count > max.count ? item : max), staff[0]);
      insights.push(`${top.name} đang đảm nhiệm nhiều ca nhất (${top.count.toLocaleString()} lượt), cân nhắc phân bổ lại nếu cần.`);
    }

    if (!insights.length) {
      insights.push('Không có insight nào cho bộ lọc này.');
    }

    els.insightList.innerHTML = insights.map(item => `<li>${escapeHtml(item)}</li>`).join('');
  }

  function exportReport() {
    if (!state.snapshot) {
      alert('Chưa có dữ liệu để xuất.');
      return;
    }

    const { finance, service, staff } = state.snapshot;
    const totalRevenue = formatCurrency(sum(finance.revenue));
    const totalExpense = formatCurrency(sum(finance.expense));
    const salaryExpense = Array.isArray(finance.salary_expense)
      ? formatCurrency(finance.salary_expense.reduce((acc, value) => acc + Number(value || 0), 0))
      : formatCurrency(Number(finance.salary_expense || 0));
    const profit = formatCurrency(sum(finance.revenue) - sum(finance.expense));

    const serviceTop = Array.isArray(service) && service[0]
      ? `${service[0].label} (${service[0].value.toLocaleString()} lượt)`
      : 'Không có dữ liệu';
    const staffTop = Array.isArray(staff) && staff[0]
      ? `${staff[0].name} (${staff[0].count.toLocaleString()} lượt)`
      : 'Không có dữ liệu';

    const printWindow = window.open('', '', 'width=900,height=700');
    printWindow.document.write(`
      <html>
        <head>
          <title>Báo cáo xu hướng</title>
          <style>
            body { font-family: Arial, sans-serif; padding: 32px; color: #111827; }
            h1 { text-align: center; color: #1e3a8a; }
            table { width: 100%; border-collapse: collapse; margin-top: 24px; }
            th, td { border: 1px solid #e5e7eb; padding: 12px; text-align: left; }
            th { background: #f3f4f6; }
          </style>
        </head>
        <body>
          <h1>STYGIAN BLUE - BÁO CÁO XU HƯỚNG</h1>
          <table>
            <tbody>
              <tr><th>Doanh thu</th><td>${escapeHtml(totalRevenue)}</td></tr>
              <tr><th>Chi phí</th><td>${escapeHtml(totalExpense)}</td></tr>
              <tr><th>Chi phí lương</th><td>${escapeHtml(salaryExpense)}</td></tr>
              <tr><th>Lợi nhuận</th><td>${escapeHtml(profit)}</td></tr>
              <tr><th>Dịch vụ tiêu biểu</th><td>${escapeHtml(serviceTop)}</td></tr>
              <tr><th>Nhân sự nổi bật</th><td>${escapeHtml(staffTop)}</td></tr>
            </tbody>
          </table>
        </body>
      </html>
    `);
    printWindow.document.close();
    printWindow.print();
  }

  function destroyChart(key) {
    if (state.charts[key]) {
      state.charts[key].destroy();
      state.charts[key] = null;
    }
  }

  function setLoading(isLoading) {
    if (!els.loading) return;
    els.loading.classList.toggle('hidden', !isLoading);
  }

  function setContentVisibility(show) {
    if (!els.content) return;
    els.content.classList.toggle('hidden', !show);
    els.content.classList.toggle('opacity-0', !show);
    els.content.classList.toggle('pointer-events-none', !show);
  }

  function showError(message) {
    if (!els.error) return;
    els.error.textContent = message;
    els.error.classList.remove('hidden');
  }

  function clearError() {
    if (!els.error) return;
    els.error.textContent = '';
    els.error.classList.add('hidden');
  }

  function formatCurrency(value) {
    if (value === undefined || value === null || Number.isNaN(value)) {
      return '—';
    }
    return `${Number(value).toLocaleString('vi-VN')} VND`;
  }

  function sum(arr) {
    if (!Array.isArray(arr) || !arr.length) {
      return 0;
    }
    return arr.reduce((total, value) => total + Number(value || 0), 0);
  }

  // FIXED: Handle edge cases like division by zero, flat data, insufficient data
  function calculateGrowth(arr) {
    if (!Array.isArray(arr) || arr.length < 2) {
      return { value: 0, status: 'insufficient' };
    }
    
    const previous = Number(arr[arr.length - 2] || 0);
    const current = Number(arr[arr.length - 1] || 0);
    
    // Case 1: Both periods have no data
    if (previous === 0 && current === 0) {
      return { value: 0, status: 'flat' };
    }
    
    // Case 2: Previous was 0, current has data (growth from zero)
    if (previous === 0) {
      return { value: 100, status: 'growth_from_zero' };
    }
    
    // Case 3: Normal case - calculate percentage change
    const growth = ((current - previous) / Math.abs(previous)) * 100;
    
    // Validate result is a valid number
    if (!isFinite(growth)) {
      return { value: 0, status: 'error' };
    }
    
    return { value: growth, status: 'normal' };
  }

  function setTrendBadge(element, growthObj) {
    if (!element) return;
    
    // Handle both old format (number) and new format (object) for backward compatibility
    let value, status;
    if (typeof growthObj === 'number') {
      value = growthObj;
      status = 'normal';
    } else {
      value = growthObj.value || 0;
      status = growthObj.status || 'normal';
    }
    
    let text = '—';
    let colorClass = 'text-gray-500';
    
    switch (status) {
      case 'insufficient':
        text = 'N/A';
        colorClass = 'text-gray-400';
        break;
      case 'flat':
        text = '0%';
        colorClass = 'text-gray-500';
        break;
      case 'growth_from_zero':
        text = 'Mới';
        colorClass = 'text-green-600';
        break;
      case 'normal':
        // Validate value is a valid number
        if (Number.isNaN(value) || !Number.isFinite(value)) {
          text = 'N/A';
          colorClass = 'text-gray-400';
        } else {
          const rounded = value.toFixed(1);
          const prefix = value >= 0 ? '+' : '';
          text = `${prefix}${rounded}%`;
          colorClass = value >= 0 ? 'text-green-600' : 'text-red-600';
        }
        break;
      case 'error':
        text = 'Lỗi';
        colorClass = 'text-red-600';
        break;
    }
    
    element.className = `ml-1 font-semibold ${colorClass}`;
    element.textContent = text;
    element.classList.toggle('text-red-600', value < 0);
  }

  function clearCanvas(ctx) {
    if (ctx && ctx.canvas) {
      ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
    }
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
})();
</script>
