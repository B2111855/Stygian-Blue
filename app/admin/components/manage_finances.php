<?php
include '../../database/config.php';
require_once __DIR__ . '/../../helpers/assets.php';

// Lấy danh sách chi nhánh
$branchQuery = mysqli_query($conn, "SELECT ID_CN, TEN_CN FROM chi_nhanh");
$branches = [];
while ($row = mysqli_fetch_assoc($branchQuery)) {
  $branches[] = $row;
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <title>Dashboard Tài Chính</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <?= sb_tailwind_link_tag(); ?>
</head>

<body class="bg-gradient-to-br from-blue-100 to-indigo-200 min-h-screen py-10 px-4 sm:px-6">
  <div class="max-w-7xl mx-auto bg-white/90 p-5 sm:p-8 rounded-2xl shadow-2xl">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between mb-8 border-b pb-5">
      <div>
        <p class="text-xs sm:text-sm uppercase tracking-wide text-indigo-500 font-semibold">Tổng quan tài chính</p>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900">Báo cáo tài chính</h1>
      </div>
      <div class="text-xs sm:text-sm text-gray-500 space-y-1">
        <p><span class="font-medium text-gray-700">Chi nhánh:</span> <span id="currentBranchName">Tất cả chi nhánh</span></p>
        <p><span class="font-medium text-gray-700">Cập nhật:</span> <span id="lastUpdated">--</span></p>
      </div>
    </header>

    <div class="grid gap-6 xl:grid-cols-12 mb-10">
      <section class="space-y-6 xl:col-span-4">
        <div class="bg-white border border-indigo-100 rounded-xl p-4 shadow-sm space-y-4">
          <div>
            <label class="text-gray-700 font-semibold mb-1 block">Chi nhánh</label>
            <select id="branchFilter" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500">
              <option value="all">Tất cả</option>
              <?php foreach ($branches as $branch): ?>
                <option value="cn<?= $branch['ID_CN'] ?>"><?= htmlspecialchars($branch['TEN_CN']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="text-gray-700 font-semibold mb-1 block">Chu kỳ báo cáo</label>
              <select id="timeFilter" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500">
                <option value="month">Theo tháng</option>
                <option value="quarter">Theo quý</option>
                <option value="year" selected>Theo năm</option>
              </select>
            </div>
            <div>
              <label class="text-gray-700 font-semibold mb-1 block">Loại biểu đồ</label>
              <div class="flex gap-2">
                <button type="button" data-chart-type="line" class="chart-toggle-btn flex-1 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm focus:ring">Đường</button>
                <button type="button" data-chart-type="bar" class="chart-toggle-btn flex-1 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Cột</button>
              </div>
            </div>
          </div>
          <div>
            <label class="text-gray-700 font-semibold mb-1 block">So sánh với chi nhánh</label>
            <select id="compareBranch" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500">
              <option value="none">Không so sánh</option>
              <?php foreach ($branches as $branch): ?>
                <option value="cn<?= $branch['ID_CN'] ?>"><?= htmlspecialchars($branch['TEN_CN']) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="mt-2 text-xs text-gray-500">Chọn một chi nhánh khác để đối chiếu KPI theo cùng chu kỳ.</p>
          </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2" id="statGrid">
          <article class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Tổng doanh thu</p>
            <p id="statRevenue" class="text-2xl font-semibold text-gray-900">--</p>
          </article>
          <article class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Tổng chi phí</p>
            <p id="statExpense" class="text-2xl font-semibold text-gray-900">--</p>
          </article>
          <article class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Lợi nhuận</p>
            <p id="statProfit" class="text-2xl font-semibold text-gray-900">--</p>
          </article>
          <article class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Tỷ suất lợi nhuận</p>
            <p id="statMargin" class="text-2xl font-semibold text-gray-900">--</p>
          </article>
        </div>

        <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-5">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold text-gray-900">Tổng hợp</h2>
            <span class="text-xs text-gray-500">Tự động cập nhật</span>
          </div>
          <div id="summary" class="text-sm sm:text-base text-gray-700 leading-relaxed"></div>
        </div>

        <div class="bg-slate-900 text-white rounded-xl shadow-inner p-5 space-y-3">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Chỉ số chuyên sâu</h2>
            <span class="text-xs uppercase tracking-wide text-slate-300">Advanced KPIs</span>
          </div>
          <div id="advancedMetrics" class="grid gap-3 sm:grid-cols-2"></div>
        </div>
      </section>

      <section class="xl:col-span-8 bg-white border border-gray-100 rounded-2xl shadow-lg p-4 sm:p-6">
        <div class="flex items-center justify-between mb-3">
          <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Biểu đồ tổng hợp</p>
            <h2 class="text-xl font-semibold text-gray-900">Hiệu suất theo thời gian</h2>
          </div>
          <button id="refreshBtn" class="text-sm text-indigo-600 font-medium hover:underline">Tải lại</button>
        </div>
        <div class="relative h-[260px] sm:h-[320px] lg:h-[360px]">
          <div id="chartLoading" class="absolute inset-0 bg-white/80 rounded-xl hidden items-center justify-center text-sm text-gray-500">Đang tải dữ liệu...</div>
          <canvas id="financialChart" class="h-full w-full" height="160"></canvas>
        </div>
        <div class="grid gap-6 lg:grid-cols-2 mt-6">
          <section class="rounded-2xl border border-gray-100 bg-slate-50 p-4">
            <div class="flex items-center justify-between mb-3">
              <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">Cơ cấu</p>
                <h3 class="text-lg font-semibold text-slate-900">Phân bổ chi phí</h3>
              </div>
              <span class="text-xs text-slate-500">Thuế + lương + vận hành</span>
            </div>
            <div class="h-60">
              <canvas id="costStructureChart"></canvas>
            </div>
          </section>
          <section class="rounded-2xl border border-gray-100 bg-white p-4 space-y-4">
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold text-gray-900">Phân tích chuyên sâu</h3>
              <span class="text-xs text-gray-500">Insight Engine</span>
            </div>
            <ul id="insightList" class="space-y-2 text-sm text-gray-700"></ul>
            <div class="border-t pt-3">
              <h4 class="text-sm font-semibold text-gray-900 mb-1">So sánh chi nhánh</h4>
              <div id="compareSummary" class="text-sm text-gray-600"></div>
            </div>
          </section>
        </div>
      </section>
    </div>

    <section class="mb-8">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-xl font-semibold text-gray-900">Bảng diễn biến</h2>
        <p class="text-sm text-gray-500">So sánh doanh thu - chi phí theo từng mốc</p>
      </div>
      <div id="trendTable" class="rounded-xl border border-gray-100 bg-white"></div>
    </section>
    <section class="border-t pt-8">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900">Chi phí phát sinh tháng hiện tại</h2>
          <p class="text-sm text-gray-500">Theo dõi các khoản điều chỉnh thủ công của từng chi nhánh</p>
        </div>
        <button type="button" onclick="toggleExpenseForm()" class="self-start rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-2 text-sm font-semibold text-yellow-700 hover:bg-yellow-100 transition">Cập nhật chi phí phát sinh</button>
      </div>

      <div id="expenseTable" class="mb-6 text-sm text-gray-700"></div>

      <div id="expenseFormWrapper" class="hidden border rounded-xl p-4 sm:p-6 bg-white shadow-sm">
        <form id="expenseForm" class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <input type="hidden" id="selectedBranch" name="branch" value="">
          <div>
            <label class="block text-sm text-gray-600 font-medium mb-1">Chi phí mặt bằng (VND)</label>
            <input type="number" name="rent" class="w-full px-4 py-2 border rounded-lg focus:ring-blue-400" min="0" placeholder="Nhập số tiền">
          </div>
          <div>
            <label class="block text-sm text-gray-600 font-medium mb-1">Chi phí điện nước (VND)</label>
            <input type="number" name="utilities" class="w-full px-4 py-2 border rounded-lg focus:ring-blue-400" min="0" placeholder="Nhập số tiền">
          </div>
          <div class="md:col-span-2 flex flex-wrap gap-3 justify-end">
            <button type="button" onclick="toggleExpenseForm()" class="rounded-lg border border-gray-200 px-5 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Đóng</button>
            <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">Lưu chi phí</button>
          </div>
        </form>
        <div id="expenseMessage" class="mt-4 text-sm font-medium text-center"></div>
      </div>
    </section>

    <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
      <button type="button" id="downloadCsv" class="rounded-lg border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Xuất CSV</button>
      <button type="button" onclick="printReport()" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700">In báo cáo</button>
    </div>
  </div>


  <script>
    (function () {
      const dom = {};
      const state = {
        chartType: 'line',
        currentFilter: 'year',
        currentBranch: 'all',
        compareBranch: 'none',
        compareBranchName: 'Không so sánh',
        datasets: { primary: null, compare: null },
        charts: { performance: null, cost: null }
      };

      document.addEventListener('DOMContentLoaded', init);

      function init() {
        cacheDom();
        bindEvents();
        bootstrapState();
        refreshPrimaryDataset();
        refreshCompareDataset();
        refreshExpenses();
      }

      function cacheDom() {
        dom.branchFilter = document.getElementById('branchFilter');
        dom.compareBranch = document.getElementById('compareBranch');
        dom.timeFilter = document.getElementById('timeFilter');
        dom.branchName = document.getElementById('currentBranchName');
        dom.lastUpdated = document.getElementById('lastUpdated');
        dom.chartButtons = document.querySelectorAll('[data-chart-type]');
        dom.chartLoading = document.getElementById('chartLoading');
        dom.performanceCanvas = document.getElementById('financialChart');
        dom.costCanvas = document.getElementById('costStructureChart');
        dom.summary = document.getElementById('summary');
        dom.statRevenue = document.getElementById('statRevenue');
        dom.statExpense = document.getElementById('statExpense');
        dom.statProfit = document.getElementById('statProfit');
        dom.statMargin = document.getElementById('statMargin');
        dom.trendTable = document.getElementById('trendTable');
        dom.insightList = document.getElementById('insightList');
        dom.compareSummary = document.getElementById('compareSummary');
        dom.advancedMetrics = document.getElementById('advancedMetrics');
        dom.refreshBtn = document.getElementById('refreshBtn');
        dom.downloadCsv = document.getElementById('downloadCsv');
        dom.expenseForm = document.getElementById('expenseForm');
        dom.expenseMessage = document.getElementById('expenseMessage');
        dom.expenseTable = document.getElementById('expenseTable');
        dom.expenseFormWrapper = document.getElementById('expenseFormWrapper');
        dom.selectedBranch = document.getElementById('selectedBranch');
      }

      function bindEvents() {
        dom.chartButtons.forEach(btn => {
          btn.addEventListener('click', () => {
            const type = btn.dataset.chartType;
            if (type === state.chartType) return;
            state.chartType = type;
            setActiveChartButton(type);
            renderPerformanceChart();
          });
        });

        dom.timeFilter.addEventListener('change', async () => {
          state.currentFilter = dom.timeFilter.value;
          await refreshPrimaryDataset();
          await refreshCompareDataset();
        });

        dom.branchFilter.addEventListener('change', async () => {
          state.currentBranch = dom.branchFilter.value;
          dom.selectedBranch.value = state.currentBranch;
          dom.branchName.innerText = dom.branchFilter.options[dom.branchFilter.selectedIndex].text;
          await refreshPrimaryDataset();
          await refreshExpenses();
          await refreshCompareDataset();
        });

        dom.compareBranch.addEventListener('change', async () => {
          state.compareBranch = dom.compareBranch.value;
          state.compareBranchName = dom.compareBranch.options[dom.compareBranch.selectedIndex].text;
          await refreshCompareDataset();
        });

        dom.refreshBtn.addEventListener('click', async () => {
          await refreshPrimaryDataset();
          await refreshCompareDataset();
          await refreshExpenses();
        });

        dom.downloadCsv.addEventListener('click', downloadCsvReport);
        dom.expenseForm.addEventListener('submit', handleExpenseSubmit);
      }

      function bootstrapState() {
        state.currentBranch = dom.branchFilter.value;
        dom.selectedBranch.value = state.currentBranch;
        dom.branchName.innerText = dom.branchFilter.options[dom.branchFilter.selectedIndex].text;
        setActiveChartButton(state.chartType);
      }

      async function refreshPrimaryDataset() {
        setLoadingState(true);
        try {
          state.datasets.primary = await fetchDataset(state.currentBranch, state.currentFilter);
          renderPrimaryOutputs();
          dom.lastUpdated.innerText = new Date().toLocaleString('vi-VN');
        } catch (error) {
          console.error('Lỗi tải dữ liệu tài chính:', error);
          dom.summary.innerHTML = '<p class="text-red-600">Không thể tải dữ liệu. Vui lòng thử lại sau.</p>';
        } finally {
          setLoadingState(false);
        }
      }

      async function refreshCompareDataset() {
        if (!state.compareBranch || state.compareBranch === 'none') {
          state.datasets.compare = null;
          updateCompareSummary();
          return;
        }
        try {
          state.datasets.compare = await fetchDataset(state.compareBranch, state.currentFilter);
          updateCompareSummary();
        } catch (error) {
          console.error('Không thể tải dữ liệu so sánh:', error);
          state.datasets.compare = null;
          updateCompareSummary('Không thể tải dữ liệu đối chiếu.');
        }
      }

      function renderPrimaryOutputs() {
        if (!state.datasets.primary) return;
        renderPerformanceChart();
        renderCostChart();
        updateSummary();
        updateStatCards();
        buildBreakdownTable();
        updateAdvancedMetrics();
        buildInsights();
        updateCompareSummary();
      }

      function setLoadingState(isLoading) {
        if (!dom.chartLoading) return;
        dom.chartLoading.classList.toggle('hidden', !isLoading);
        dom.chartLoading.classList.toggle('flex', isLoading);
      }

      async function fetchDataset(branch, filter) {
        const [financialRes, taxRes] = await Promise.all([
          fetch(`./components/get_financial_data.php?filter=${filter}&branch=${branch}`),
          fetch(`./components/get_total_tax.php?branch=${branch}`)
        ]);

        if (!financialRes.ok) throw new Error('Không nhận được dữ liệu doanh thu/chi phí');
        if (!taxRes.ok) throw new Error('Không nhận được dữ liệu thuế');

        const data = await financialRes.json();
        const taxPayload = await taxRes.json();
        return { ...data, tax: taxPayload?.tax ? Number(taxPayload.tax) : 0 };
      }

      async function refreshExpenses() {
        try {
          const res = await fetch(`./components/get_expense_table.php?branch=${state.currentBranch}`);
          dom.expenseTable.innerHTML = await res.text();
        } catch (error) {
          dom.expenseTable.innerHTML = '<p class="text-red-600">Không thể tải chi phí phát sinh.</p>';
        }
      }

      function renderPerformanceChart() {
        if (!state.datasets.primary || !dom.performanceCanvas) return;
        const ctx = dom.performanceCanvas.getContext('2d');
        const labels = safeSeries(state.datasets.primary.labels);
        const revenueSeries = safeSeries(state.datasets.primary.revenue);
        const expenseSeries = safeSeries(state.datasets.primary.expense);
        const profitSeries = labels.map((_, idx) => Number(revenueSeries[idx] || 0) - Number(expenseSeries[idx] || 0));

        const datasets = [
          {
            label: 'Doanh thu',
            data: revenueSeries,
            borderColor: '#059669',
            backgroundColor: 'rgba(5, 150, 105, 0.15)',
            tension: 0.3,
            fill: state.chartType === 'line'
          },
          {
            label: 'Chi phí',
            data: expenseSeries,
            borderColor: '#dc2626',
            backgroundColor: 'rgba(220, 38, 38, 0.15)',
            tension: 0.3,
            fill: state.chartType === 'line'
          },
          {
            label: 'Lợi nhuận',
            data: profitSeries,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.2)',
            borderDash: [6, 4],
            tension: 0.2,
            type: state.chartType
          }
        ];

        if (state.charts.performance) state.charts.performance.destroy();
        state.charts.performance = new Chart(ctx, {
          type: state.chartType,
          data: { labels, datasets },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
              legend: { position: 'bottom', labels: { usePointStyle: true } }
            },
            scales: {
              y: {
                ticks: {
                  callback: value => Number(value).toLocaleString('vi-VN')
                }
              }
            }
          }
        });
      }

      function renderCostChart() {
        if (!state.datasets.primary || !dom.costCanvas) return;
        const totals = computeTotals(state.datasets.primary);
        if (!totals) return;
        const ctx = dom.costCanvas.getContext('2d');
        const slices = [
          { label: 'Chi phí vận hành', value: totals.totalExpense },
          { label: 'Chi phí lương', value: totals.salaryExpense },
          { label: 'Thuế', value: totals.tax }
        ];
        if (totals.profit >= 0) {
          slices.push({ label: 'Lợi nhuận giữ lại', value: totals.profit });
        } else {
          slices.push({ label: 'Thâm hụt', value: Math.abs(totals.profit) });
        }

        if (state.charts.cost) state.charts.cost.destroy();
        state.charts.cost = new Chart(ctx, {
          type: 'doughnut',
          data: {
            labels: slices.map(item => item.label),
            datasets: [{
              data: slices.map(item => Math.max(item.value, 0)),
              backgroundColor: ['#38bdf8', '#6366f1', '#f97316', '#14b8a6'],
              borderWidth: 0
            }]
          },
          options: {
            plugins: {
              legend: { position: 'bottom' }
            }
          }
        });
      }

      function updateSummary() {
        const totals = computeTotals(state.datasets.primary);
        if (!totals) return;
        dom.summary.innerHTML = `
          <div id="financialReport" class="space-y-1">
            <p><strong>Tổng doanh thu:</strong> ${formatCurrency(totals.totalRevenue)}</p>
            <p><strong>Chi phí hoạt động:</strong> ${formatCurrency(totals.totalExpense)}</p>
            <p><strong>Chi phí lương nhân viên:</strong> ${formatCurrency(totals.salaryExpense)}</p>
            <p><strong>Thuế:</strong> ${formatCurrency(totals.tax)}</p>
            <p><strong>Tổng chi phí (gồm thuế):</strong> ${formatCurrency(totals.totalExpenseWithTax)}</p>
            <p><strong>Lợi nhuận ròng:</strong> ${formatCurrency(totals.profit)}</p>
          </div>
          <p class="mt-3 text-sm text-indigo-700 font-semibold">Tỷ suất lợi nhuận trên tổng chi phí: ${totals.rate}</p>`;
      }

      function updateStatCards() {
        const totals = computeTotals(state.datasets.primary);
        if (!totals) return;
        dom.statRevenue.innerText = formatCurrency(totals.totalRevenue);
        dom.statExpense.innerText = formatCurrency(totals.totalExpenseWithTax);
        dom.statProfit.innerText = formatCurrency(totals.profit);
        dom.statMargin.innerText = totals.rate;
      }

      function buildBreakdownTable() {
        if (!state.datasets.primary) return;
        const labels = safeSeries(state.datasets.primary.labels);
        const revenueSeries = safeSeries(state.datasets.primary.revenue);
        const expenseSeries = safeSeries(state.datasets.primary.expense);
        const rows = labels.map((label, idx) => {
          const revenue = Number(revenueSeries[idx] || 0);
          const expense = Number(expenseSeries[idx] || 0);
          const profit = revenue - expense;
          const margin = revenue === 0 ? 'N/A' : ((profit / revenue) * 100).toFixed(1) + '%';
          return `
            <tr class="border-t">
              <td class="px-4 py-3 text-sm text-gray-600">${label}</td>
              <td class="px-4 py-3 text-right text-sm font-medium text-gray-900">${revenue.toLocaleString('vi-VN')}</td>
              <td class="px-4 py-3 text-right text-sm font-medium text-gray-900">${expense.toLocaleString('vi-VN')}</td>
              <td class="px-4 py-3 text-right text-sm font-semibold ${profit >= 0 ? 'text-emerald-600' : 'text-red-600'}">${profit.toLocaleString('vi-VN')}</td>
              <td class="px-4 py-3 text-right text-xs text-gray-500">${margin}</td>
            </tr>`;
        }).join('');

        const mobileCards = labels.map((label, idx) => {
          const revenue = Number(revenueSeries[idx] || 0).toLocaleString('vi-VN');
          const expense = Number(expenseSeries[idx] || 0).toLocaleString('vi-VN');
          const profit = (Number(revenueSeries[idx] || 0) - Number(expenseSeries[idx] || 0)).toLocaleString('vi-VN');
          const margin = Number(revenueSeries[idx] || 0) === 0 ? 'N/A' : (((Number(revenueSeries[idx] || 0) - Number(expenseSeries[idx] || 0)) / Number(revenueSeries[idx] || 0)) * 100).toFixed(1) + '%';
          const profitPositive = Number(revenueSeries[idx] || 0) - Number(expenseSeries[idx] || 0) >= 0;
          return `
            <div class="p-4 space-y-1">
              <p class="text-sm font-semibold text-gray-800">${label}</p>
              <p class="text-sm text-gray-600">Doanh thu: <span class="font-medium text-gray-900">${revenue}</span></p>
              <p class="text-sm text-gray-600">Chi phí: <span class="font-medium text-gray-900">${expense}</span></p>
              <p class="text-sm ${profitPositive ? 'text-emerald-600' : 'text-red-600'}">Lợi nhuận: ${profit}</p>
              <p class="text-xs text-gray-500">Biên lợi nhuận: ${margin}</p>
            </div>`;
        }).join('');

        const tableMarkup = `
          <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 sticky top-0">
              <tr>
                <th class="px-4 py-2 text-left font-semibold text-gray-600">Mốc thời gian</th>
                <th class="px-4 py-2 text-right font-semibold text-gray-600">Doanh thu</th>
                <th class="px-4 py-2 text-right font-semibold text-gray-600">Chi phí</th>
                <th class="px-4 py-2 text-right font-semibold text-gray-600">Chênh lệch</th>
                <th class="px-4 py-2 text-right font-semibold text-gray-600">Biên lợi nhuận</th>
              </tr>
            </thead>
            <tbody class="bg-white">
              ${rows}
            </tbody>
          </table>`;

        dom.trendTable.innerHTML = `
          <div class="hidden md:block max-h-96 overflow-y-auto overflow-x-auto">
            ${tableMarkup}
          </div>
          <div class="md:hidden divide-y divide-gray-100">
            ${mobileCards}
          </div>`;
      }

      function updateAdvancedMetrics() {
        const dataset = state.datasets.primary;
        if (!dataset || !dom.advancedMetrics) return;
        const totals = computeTotals(dataset);
        if (!totals) return;
        const labels = safeSeries(dataset.labels);
        const revenueSeries = safeSeries(dataset.revenue);
        const periodCount = Math.max(labels.length, 1);
        const lastRevenue = Number(revenueSeries[revenueSeries.length - 1] || 0);
        const prevRevenue = Number(revenueSeries[revenueSeries.length - 2] || 0);
        const growth = prevRevenue === 0 ? null : ((lastRevenue - prevRevenue) / Math.max(prevRevenue, 1)) * 100;
        const expenseRatio = totals.totalRevenue === 0 ? null : (totals.totalExpenseWithTax / totals.totalRevenue) * 100;
        const burnRate = totals.totalExpenseWithTax / periodCount;
        const runway = burnRate === 0 ? null : (totals.profit >= 0 ? 'Dòng tiền dương' : `${Math.max(Math.round(Math.abs(totals.totalRevenue / burnRate)), 1)} kỳ để hòa vốn`);
        const salaryShare = totals.totalExpenseWithTax === 0 ? null : (totals.salaryExpense / totals.totalExpenseWithTax) * 100;

        const cards = [
          {
            label: 'Tăng trưởng doanh thu',
            value: growth === null ? 'N/A' : `${growth >= 0 ? '+' : ''}${growth.toFixed(1)}%`,
            hint: 'So với kỳ liền trước'
          },
          {
            label: 'Tỷ lệ chi phí / doanh thu',
            value: expenseRatio === null ? 'N/A' : `${expenseRatio.toFixed(1)}%`,
            hint: 'Bao gồm thuế & lương'
          },
          {
            label: 'Burn rate mỗi kỳ',
            value: formatCurrency(burnRate),
            hint: `${periodCount} kỳ được tính`
          },
          {
            label: 'Tỷ trọng lương',
            value: salaryShare === null ? 'N/A' : `${salaryShare.toFixed(1)}%`,
            hint: 'Trên tổng chi phí'
          }
        ];

        dom.advancedMetrics.innerHTML = cards.map(card => `
          <div class="rounded-lg border border-white/20 bg-white/10 p-3">
            <p class="text-xs uppercase tracking-wide text-slate-200">${card.label}</p>
            <p class="text-2xl font-semibold text-white">${card.value}</p>
            <p class="text-xs text-slate-300">${card.hint}</p>
          </div>`).join('');

        if (runway) {
          dom.advancedMetrics.insertAdjacentHTML('beforeend', `
            <div class="rounded-lg border border-white/20 bg-white/10 p-3 sm:col-span-2">
              <p class="text-xs uppercase tracking-wide text-slate-200">Dòng tiền dự kiến</p>
              <p class="text-lg font-semibold text-white">${runway}</p>
              <p class="text-xs text-slate-300">Mô phỏng dựa trên burn rate hiện tại</p>
            </div>`);
        }
      }

      function buildInsights() {
        if (!dom.insightList || !state.datasets.primary) return;
        const dataset = state.datasets.primary;
        const totals = computeTotals(dataset);
        if (!totals) {
          dom.insightList.innerHTML = '<li>Chưa có dữ liệu để phân tích.</li>';
          return;
        }
        const revenueSeries = safeSeries(dataset.revenue);
        const expenseSeries = safeSeries(dataset.expense);
        const growth = calcGrowthRate(revenueSeries);
        const expenseRatio = totals.totalRevenue === 0 ? 0 : (totals.totalExpenseWithTax / totals.totalRevenue) * 100;
        const volatility = calcVolatility(revenueSeries);
        const avgExpense = expenseSeries.length === 0 ? 0 : expenseSeries.reduce((a, b) => a + Number(b || 0), 0) / expenseSeries.length;
        const insights = [
          `Doanh thu ${growth >= 0 ? 'tăng' : 'giảm'} ${Math.abs(growth).toFixed(1)}% so với kỳ trước.`,
          `Tỷ lệ chi phí/doanh thu hiện ở mức ${expenseRatio.toFixed(1)}%, ${expenseRatio > 80 ? 'cần kiểm soát chặt' : 'được xem là an toàn'}.`,
          `Độ biến động doanh thu (σ) đạt ${volatility.toLocaleString('vi-VN')} VND, phù hợp để lập kế hoạch quỹ dự phòng.`,
          `Chi phí bình quân mỗi kỳ khoảng ${formatCurrency(avgExpense)}.`
        ];
        dom.insightList.innerHTML = insights.map(item => `<li class="flex gap-2"><span class="text-indigo-500">•</span><span>${item}</span></li>`).join('');
      }

      function updateCompareSummary(message) {
        if (!dom.compareSummary) return;
        if (message) {
          dom.compareSummary.innerText = message;
          return;
        }
        if (!state.datasets.primary || !state.datasets.compare) {
          dom.compareSummary.innerText = 'Chưa chọn chi nhánh để so sánh.';
          return;
        }
        const baseTotals = computeTotals(state.datasets.primary);
        const compareTotals = computeTotals(state.datasets.compare);
        const revenueDiff = baseTotals.totalRevenue - compareTotals.totalRevenue;
        const profitDiff = baseTotals.profit - compareTotals.profit;
        const marginDiff = parseFloat(baseTotals.rate) - parseFloat(compareTotals.rate);
        const signRevenue = revenueDiff >= 0 ? 'cao hơn' : 'thấp hơn';
        const signProfit = profitDiff >= 0 ? 'cao hơn' : 'thấp hơn';
        dom.compareSummary.innerHTML = `
          <p>Doanh thu ${signRevenue} ${formatCurrency(Math.abs(revenueDiff))} so với <strong>${state.compareBranchName}</strong>.</p>
          <p>Lợi nhuận ${signProfit} ${formatCurrency(Math.abs(profitDiff))}; chênh lệch biên lợi nhuận ${isNaN(marginDiff) ? 'N/A' : marginDiff.toFixed(1) + '%'}.</p>`;
      }

      async function handleExpenseSubmit(event) {
        event.preventDefault();
        const formData = new FormData(dom.expenseForm);
        try {
          const response = await fetch('./components/save_expenses.php', { method: 'POST', body: formData });
          const result = await response.json();
          if (result.status !== 'success') throw new Error(result.message);
          showMessage('Đã lưu chi phí thành công', 'green');
          await refreshPrimaryDataset();
          await refreshExpenses();
        } catch (error) {
          console.error('Lỗi lưu chi phí:', error);
          showMessage(error.message || 'Không thể lưu chi phí', 'red');
        }
      }

      function showMessage(message, color) {
        dom.expenseMessage.innerText = message;
        dom.expenseMessage.className = `mt-4 text-sm font-medium text-center text-${color}-600`;
      }

      function downloadCsvReport() {
        if (!state.datasets.primary) return;
        const labels = safeSeries(state.datasets.primary.labels);
        const revenueSeries = safeSeries(state.datasets.primary.revenue);
        const expenseSeries = safeSeries(state.datasets.primary.expense);
        const rows = [['Moc thoi gian', 'Doanh thu', 'Chi phi', 'Loi nhuan']];
        labels.forEach((label, idx) => {
          const revenue = Number(revenueSeries[idx] || 0);
          const expense = Number(expenseSeries[idx] || 0);
          rows.push([label, revenue, expense, revenue - expense]);
        });
        const csvContent = rows.map(row => row.join(',')).join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `bao_cao_tai_chinh_${state.currentBranch}_${state.currentFilter}.csv`;
        anchor.click();
        URL.revokeObjectURL(url);
      }

      function computeTotals(dataset) {
        if (!dataset) return null;
        const revenueSeries = safeSeries(dataset.revenue);
        const expenseSeries = safeSeries(dataset.expense);
        const totalRevenue = revenueSeries.reduce((sum, value) => sum + Number(value || 0), 0);
        const totalExpense = expenseSeries.reduce((sum, value) => sum + Number(value || 0), 0);
        const salaryExpense = Number(dataset.salary_expense || 0);
        const tax = Number(dataset.tax || 0);
        const expenseWithSalary = totalExpense + salaryExpense;
        const totalExpenseWithTax = expenseWithSalary + tax;
        const profit = totalRevenue - totalExpenseWithTax;
        const rate = totalExpenseWithTax === 0 ? 'N/A' : ((profit / totalExpenseWithTax) * 100).toFixed(2) + '%';
        return { totalRevenue, totalExpense, salaryExpense, tax, totalExpenseWithTax, profit, rate };
      }

      function setActiveChartButton(type) {
        dom.chartButtons.forEach(btn => {
          const isActive = btn.dataset.chartType === type;
          btn.classList.toggle('border-indigo-500', isActive);
          btn.classList.toggle('text-indigo-700', isActive);
          btn.classList.toggle('bg-indigo-50', isActive);
          btn.classList.toggle('border-gray-200', !isActive);
          btn.classList.toggle('text-gray-700', !isActive);
          btn.classList.toggle('bg-white', !isActive);
        });
      }

      function safeSeries(series) {
        return Array.isArray(series) ? series : [];
      }

      function formatCurrency(value) {
        return Number(value || 0).toLocaleString('vi-VN') + ' VND';
      }

      function calcGrowthRate(series) {
        if (!series || series.length < 2) return 0;
        const last = Number(series[series.length - 1] || 0);
        const prev = Number(series[series.length - 2] || 0);
        if (prev === 0) return 0;
        return ((last - prev) / Math.max(prev, 1)) * 100;
      }

      function calcVolatility(series) {
        if (!series || series.length === 0) return 0;
        const nums = series.map(value => Number(value || 0));
        const avg = nums.reduce((sum, val) => sum + val, 0) / nums.length;
        const variance = nums.reduce((sum, val) => sum + Math.pow(val - avg, 2), 0) / nums.length;
        return Math.sqrt(variance);
      }

      window.toggleExpenseForm = function () {
        dom.expenseFormWrapper.classList.toggle('hidden');
      };

      window.printReport = function () {
        const today = new Date();
        const summaryContent = document.getElementById('financialReport');
        const expenseTable = document.getElementById('expenseTable');
        const rows = [];

        summaryContent?.querySelectorAll('p').forEach(paragraph => {
          const strong = paragraph.querySelector('strong');
          if (!strong) return;
          const label = strong.innerText.replace(':', '').trim();
          const value = paragraph.textContent.split(':').pop().trim();
          rows.push({ label, value });
        });

        expenseTable?.querySelectorAll('tbody tr').forEach(tr => {
          const cells = tr.querySelectorAll('td');
          if (cells.length >= 2) {
            rows.push({
              label: cells[0].innerText.trim(),
              value: cells[1].innerText.trim()
            });
          }
        });

        const tableHTML = `
          <table>
            <thead>
              <tr>
                <th>Khoản mục</th>
                <th>Giá trị (VND)</th>
              </tr>
            </thead>
            <tbody>
              ${rows.map(row => `
                <tr>
                  <td>${row.label}</td>
                  <td>${row.value}</td>
                </tr>`).join('')}
            </tbody>
          </table>`;

        const popup = window.open('', '', 'width=900,height=700');
        popup.document.write(`
          <html>
            <head>
              <title>Báo cáo tài chính</title>
              <style>
                body { font-family: Arial, sans-serif; padding: 40px; line-height: 1.6; color: #333; background: #f9fafb; }
                h1 { text-align: center; font-size: 28px; color: #1e3a8a; margin-bottom: 10px; }
                h2 { text-align: center; font-size: 20px; color: #444; margin-bottom: 30px; }
                table { width: 100%; border-collapse: collapse; background: white; }
                th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                th { background-color: #edf2f7; font-weight: bold; text-align: center; }
                td:last-child { text-align: right; font-weight: 500; color: #2d3748; }
                .signatures { display: flex; justify-content: space-between; margin-top: 60px; padding: 0 10%; }
                .signatures div { text-align: center; font-size: 14px; }
                .signatures div::after { content: '_________________________'; display: block; margin-top: 40px; }
                .footer-note { text-align: center; margin-top: 40px; font-size: 12px; color: #777; }
              </style>
            </head>
            <body>
              <h1>STYGIAN BLUE STUDIO</h1>
              <h2>BÁO CÁO TÀI CHÍNH THÁNG ${today.getMonth() + 1}/${today.getFullYear()}</h2>
              ${tableHTML}
              <div class="signatures">
                <div>Người lập báo cáo</div>
                <div>Quản lý chi nhánh</div>
              </div>
              <div class="footer-note">Báo cáo được tạo tự động • ${today.toLocaleString('vi-VN')}</div>
            </body>
          </html>`);
        popup.document.close();
        popup.print();
      };
    })();
  </script>
</body>

</html>