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
  <title>Dashboard Tài Chính - Đơn giản</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <?= sb_tailwind_link_tag(); ?>
</head>

<body class="bg-gradient-to-br from-blue-100 to-indigo-200 min-h-screen py-10 px-4 sm:px-6">
  <div class="max-w-6xl mx-auto bg-white/90 p-5 sm:p-8 rounded-2xl shadow-2xl">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between mb-8 border-b pb-5">
      <div>
        <p class="text-xs sm:text-sm uppercase tracking-wide text-indigo-500 font-semibold">Tổng quan tài chính</p>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900">Báo cáo tài chính</h1>
      </div>
      <div class="flex items-center gap-3">
        <div class="text-xs sm:text-sm text-gray-500 space-y-1">
          <p><span class="font-medium text-gray-700">Chi nhánh:</span> <span id="currentBranchName">Tất cả chi nhánh</span></p>
          <p><span class="font-medium text-gray-700">Cập nhật:</span> <span id="lastUpdated">--</span></p>
        </div>
        <button onclick="printReport()" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700 flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
          </svg>
          Xuất báo cáo
        </button>
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
          <div>
            <label class="text-gray-700 font-semibold mb-1 block">Chu kỳ báo cáo</label>
            <select id="timeFilter" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500">
              <option value="month">Theo tháng</option>
              <option value="quarter">Theo quý</option>
              <option value="year" selected>Theo năm</option>
            </select>
          </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2" id="statGrid">
          <article class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 shadow-sm">
            <p class="text-sm font-medium text-emerald-700">Doanh thu thực tế</p>
            <p id="statRevenueActual" class="text-3xl font-semibold text-emerald-900 mt-2">--</p>
            <p class="text-xs text-emerald-600 mt-1">Đã nhận tiền</p>
          </article>
          <article class="rounded-xl border border-orange-100 bg-orange-50 p-4 shadow-sm">
            <p class="text-sm font-medium text-orange-700">Chi phí phát sinh</p>
            <p id="statExpense" class="text-3xl font-semibold text-orange-900 mt-2">--</p>
            <p class="text-xs text-orange-600 mt-1">Điện, nước, mặt bằng...</p>
          </article>
          <article class="rounded-xl border border-blue-100 bg-blue-50 p-4 shadow-sm">
            <p class="text-sm font-medium text-blue-700">Lương nhân viên</p>
            <p id="statSalary" class="text-3xl font-semibold text-blue-900 mt-2">--</p>
            <p class="text-xs text-blue-600 mt-1">Tổng lương</p>
          </article>
          <article class="rounded-xl border border-red-100 bg-red-50 p-4 shadow-sm">
            <p class="text-sm font-medium text-red-700">Lợi nhuận ròng</p>
            <p id="statProfit" class="text-3xl font-semibold text-red-900 mt-2">--</p>
            <p class="text-xs text-red-600 mt-1">Doanh thu - Chi phí - Lương</p>
          </article>
        </div>

        <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-5">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold text-gray-900">Tóm tắt</h2>
            <span class="text-xs text-gray-500">Tự động cập nhật</span>
          </div>
          <div id="summary" class="text-sm sm:text-base text-gray-700 leading-relaxed"></div>
        </div>
      </section>

      <section class="xl:col-span-8 bg-white border border-gray-100 rounded-2xl shadow-lg p-4 sm:p-6">
        <div class="flex items-center justify-between mb-3">
          <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Biểu đồ tổng hợp</p>
            <h2 class="text-xl font-semibold text-gray-900">Doanh thu & Chi phí</h2>
          </div>
          <button id="refreshBtn" class="text-sm text-indigo-600 font-medium hover:underline">Tải lại</button>
        </div>
        <div class="relative h-[220px] sm:h-[260px] lg:h-[280px]">
          <div id="chartLoading" class="absolute inset-0 bg-white/80 rounded-xl hidden items-center justify-center text-sm text-gray-500">Đang tải dữ liệu...</div>
          <canvas id="financialChart" class="h-full w-full" height="140"></canvas>
        </div>
      </section>
    </div>
  </div>

  <script>
    // Trạng thái toàn cục cho biểu đồ, dữ liệu và bộ lọc
    let globalState = {
      charts: {
        performance: null
      },
      datasets: {
        primary: null
      },
      currentBranch: 'all',
      currentFilter: 'year'
    };

    // Đăng ký khởi tạo khi DOM sẵn sàng
    document.addEventListener('DOMContentLoaded', init);

    // Khởi tạo trang: cache DOM, gắn sự kiện và tải dữ liệu đầu tiên
    function init() {
      cacheDom();
      bindEvents();
      bootstrapState();
      refreshPrimaryDataset();
    }

    const dom = {};

    // Lưu tham chiếu các phần tử giao diện để dùng lại
    function cacheDom() {
      dom.branchFilter = document.getElementById('branchFilter');
      dom.timeFilter = document.getElementById('timeFilter');
      dom.branchName = document.getElementById('currentBranchName');
      dom.lastUpdated = document.getElementById('lastUpdated');
      dom.chartLoading = document.getElementById('chartLoading');
      dom.performanceCanvas = document.getElementById('financialChart');
      dom.statRevenueActual = document.getElementById('statRevenueActual');
      dom.statExpense = document.getElementById('statExpense');
      dom.statSalary = document.getElementById('statSalary');
      dom.statProfit = document.getElementById('statProfit');
      dom.summary = document.getElementById('summary');
      dom.refreshBtn = document.getElementById('refreshBtn');
    }

    // Gắn sự kiện cho bộ lọc thời gian, chi nhánh và nút tải lại
    function bindEvents() {
      dom.timeFilter.addEventListener('change', async () => {
        globalState.currentFilter = dom.timeFilter.value;
        await refreshPrimaryDataset();
      });

      dom.branchFilter.addEventListener('change', async () => {
        globalState.currentBranch = dom.branchFilter.value;
        dom.branchName.innerText = dom.branchFilter.options[dom.branchFilter.selectedIndex].text;
        await refreshPrimaryDataset();
      });

      dom.refreshBtn.addEventListener('click', refreshPrimaryDataset);
    }

    // Thiết lập trạng thái ban đầu dựa trên lựa chọn hiện tại trong UI
    function bootstrapState() {
      globalState.currentBranch = dom.branchFilter.value;
      dom.branchName.innerText = dom.branchFilter.options[dom.branchFilter.selectedIndex].text;
    }

    // Lấy dataset chính từ API và cập nhật biểu đồ, thẻ số liệu, tóm tắt
    async function refreshPrimaryDataset() {
      setLoadingState(true);
      try {
        globalState.datasets.primary = await fetchDataset(globalState.currentBranch, globalState.currentFilter);
        renderPrimaryOutputs();
        dom.lastUpdated.innerText = new Date().toLocaleString('vi-VN');
      } catch (error) {
        console.error('Lỗi tải dữ liệu tài chính:', error);
        dom.summary.innerHTML = '<p class="text-red-600">Không thể tải dữ liệu. Vui lòng thử lại sau.</p>';
      } finally {
        setLoadingState(false);
      }
    }

    // Gọi API PHP để lấy dữ liệu tài chính theo chi nhánh và chu kỳ
    async function fetchDataset(branch, filter) {
      const res = await fetch(`components/get_financial_data.php?filter=${filter}&branch=${branch}`);
      if (!res.ok) throw new Error('Không nhận được dữ liệu tài chính');
      return res.json();
    }

    // Vẽ biểu đồ và cập nhật các khu vực hiển thị chính
    function renderPrimaryOutputs() {
      if (!globalState.datasets.primary) return;
      renderPerformanceChart();
      updateStatCards();
      updateSummary();
    }

    // Vẽ biểu đồ đường: Doanh thu thực tế vs Tổng chi phí (chi phí + lương)
    function renderPerformanceChart() {
      if (!globalState.datasets.primary || !dom.performanceCanvas) return;
      const ctx = dom.performanceCanvas.getContext('2d');
      const labels = safeSeries(globalState.datasets.primary.labels);
      const revenueSeries = safeSeries(globalState.datasets.primary.revenue_actual);
      const expenseSeries = safeSeries(globalState.datasets.primary.expense);
      const salarySeries = safeSeries(globalState.datasets.primary.salary_expense);
      const totalExpenseSeries = expenseSeries.map((val, idx) => Number(val || 0) + Number(salarySeries[idx] || 0));

      if (globalState.charts.performance) {
        globalState.charts.performance.destroy();
      }

      globalState.charts.performance = new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [{
              label: 'Doanh thu thực tế',
              data: revenueSeries,
              borderColor: '#10b981',
              backgroundColor: 'rgba(16, 185, 129, 0.1)',
              tension: 0.35,
              fill: false,
              borderWidth: 2
            },
            {
              label: 'Tổng chi phí',
              data: totalExpenseSeries,
              borderColor: '#ef4444',
              backgroundColor: 'rgba(239, 68, 68, 0.1)',
              tension: 0.35,
              fill: false,
              borderWidth: 2
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom'
            },
            tooltip: {
              callbacks: {
                label: (ctx) => `${ctx.dataset.label}: ${formatCurrency(ctx.parsed.y)}`
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: (val) => formatCurrency(val)
              }
            }
          }
        }
      });
    }

    // Cập nhật các thẻ thống kê nhanh (doanh thu, chi phí, lương, lợi nhuận)
    function updateStatCards() {
      const totals = computeTotals(globalState.datasets.primary);
      if (!totals) return;
      dom.statRevenueActual.innerText = formatCurrency(totals.totalRevenueActual);
      dom.statExpense.innerText = formatCurrency(totals.totalExpense);
      dom.statSalary.innerText = formatCurrency(totals.salaryExpense);
      dom.statProfit.innerText = formatCurrency(totals.profit);
    }

    // Cập nhật phần tóm tắt bằng văn bản, hiển thị số liệu chính
    function updateSummary() {
      const totals = computeTotals(globalState.datasets.primary);
      if (!totals) {
        dom.summary.innerHTML = '<p class="text-gray-500">Chưa có dữ liệu.</p>';
        return;
      }
      dom.summary.innerHTML = `
        <div class="space-y-2">
          <p>Doanh thu thực tế: <span class="font-semibold text-emerald-700">${formatCurrency(totals.totalRevenueActual)}</span></p>
          <p>Tổng chi phí phát sinh: <span class="font-semibold text-orange-700">${formatCurrency(totals.totalExpense)}</span></p>
          <p>Lương nhân viên: <span class="font-semibold text-blue-700">${formatCurrency(totals.salaryExpense)}</span></p>
          <p class="font-semibold ${totals.profit >= 0 ? 'text-emerald-700' : 'text-red-700'}">Lợi nhuận ròng: ${formatCurrency(totals.profit)}</p>
        </div>`;
    }

    // Tính tổng: doanh thu thực tế, chi phí, lương và lợi nhuận ròng
    function computeTotals(dataset) {
      if (!dataset) return null;
      const revenueActual = safeSeries(dataset.revenue_actual);
      const expense = safeSeries(dataset.expense);
      const salary = safeSeries(dataset.salary_expense);
      const totalRevenueActual = revenueActual.reduce((sum, v) => sum + Number(v || 0), 0);
      const totalExpense = expense.reduce((sum, v) => sum + Number(v || 0), 0);
      const salaryExpense = salary.reduce((sum, v) => sum + Number(v || 0), 0);
      const profit = totalRevenueActual - (totalExpense + salaryExpense);
      return {
        totalRevenueActual,
        totalExpense,
        salaryExpense,
        profit
      };
    }

    // Định dạng số tiền theo chuẩn Việt Nam (VND)
    function formatCurrency(value) {
      const num = Number(value || 0);
      return num.toLocaleString('vi-VN') + ' VND';
    }

    // Trả về mảng an toàn: luôn là mảng, tránh lỗi khi dữ liệu null/undefined
    function safeSeries(series) {
      return Array.isArray(series) ? series : [];
    }

    // Bật/tắt lớp phủ đang tải khi gọi API
    function setLoadingState(isLoading) {
      if (!dom.chartLoading) return;
      dom.chartLoading.classList.toggle('hidden', !isLoading);
      dom.chartLoading.classList.toggle('flex', isLoading);
    }

    // In/Export báo cáo hiện tại bằng trình duyệt
    function printReport() {
      window.print();
    }
  </script>
</body>

</html>