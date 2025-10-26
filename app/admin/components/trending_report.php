<?php
include '../../database/config.php';

$filter = $_GET['filter'] ?? 'year';
$branch = $_GET['branch'] ?? 'all';

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
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gradient-to-br from-blue-100 to-indigo-200 min-h-screen py-10 px-6">
  <div class="max-w-6xl mx-auto bg-white p-8 rounded-xl shadow-2xl">
    <h1 class="text-4xl font-extrabold mb-8 text-center text-indigo-700">
      📊 Biểu đồ Xu Hướng
    </h1>

    <!-- Bộ lọc -->
    <div class="flex flex-wrap gap-6 justify-center items-end mb-8">
      <div class="flex flex-col">
        <label class="text-gray-700 font-semibold mb-1">Chọn chi nhánh:</label>
        <select id="branchFilter" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500">
          <option value="all">Tất cả</option>
          <?php foreach ($branches as $branch): ?>
            <option value="cn<?= $branch['ID_CN'] ?>">
              <?= htmlspecialchars($branch['TEN_CN']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex flex-col">
        <label class="text-gray-700 font-semibold mb-1">Thời gian:</label>
        <select id="timeFilter" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500">
          <option value="month">Theo tháng</option>
          <option value="quarter">Theo quý</option>
          <option value="year" selected>Theo năm</option>
        </select>
      </div>
    </div>

    <!-- Biểu đồ tài chính & phân công nhân viên -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
  <div class="bg-white p-4 rounded-lg shadow border">
    <h2 class="text-xl font-bold text-indigo-700 mb-4">📌 Biểu đồ tài chính</h2>
    <canvas id="financialChart" class="mx-auto" height="250" style="max-width: 100%;"></canvas>
    <div id="summary" class="mt-4 text-center text-sm text-gray-700"></div>
  </div>

  <div class="bg-white p-4 rounded-lg shadow border">
    <h2 class="text-xl font-bold text-indigo-700 mb-4">🧑‍💼 Phân công nhân viên</h2>
    <canvas id="staffAssignChart" class="mx-auto" height="250" style="max-width: 100%;"></canvas>
    <div id="staffAssignSummary" class="mt-4 text-center text-sm text-gray-700"></div>
  </div>
</div>
    </div>

    <!-- Biểu đồ xu hướng -->
    <div class="mt-8">
  <div id="trendCharts" class="mt-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-lg font-bold text-indigo-700 mb-2">📈 Tỉ lệ dịch vụ</h2>
            <canvas id="serviceShareChart" class="mx-auto" height="300"></canvas>
            <div id="serviceSummary" class="mt-4 text-center text-sm text-gray-700"></div>
          </div>

          <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-lg font-bold text-indigo-700 mb-2">🕒 Khung giờ đặt lịch</h2>
            <canvas id="timeSlotChart" class="mx-auto" height="300"></canvas>
            <div id="timeSlotSummary" class="mt-4 text-center text-sm text-gray-700"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    let chart, serviceChart, slotChart, staffChart;

    async function fetchAndRenderChart(filter, branch) {
      const res = await fetch(`components/get_financial_data.php?filter=${filter}&branch=${branch}`);
      const data = await res.json();
      if (chart) chart.destroy();
      const ctx = document.getElementById('financialChart').getContext('2d');
      chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: data.labels,
          datasets: [
            { label: 'Doanh thu', data: data.revenue, borderColor: 'green', fill: false },
            { label: 'Chi phí', data: data.expense, borderColor: 'red', fill: false }
          ]
        }
      });
      const totalRevenue = data.revenue.reduce((a, b) => a + b, 0);
      const totalExpense = data.expense.reduce((a, b) => a + b, 0);
      const tax = totalRevenue * 0.1;
      const profit = totalRevenue - (totalExpense + tax);
      document.getElementById('summary').innerHTML = `
        💰 <strong>Doanh thu:</strong> ${totalRevenue.toLocaleString()} VND &nbsp;&nbsp;
        🧾 <strong>Chi phí:</strong> ${totalExpense.toLocaleString()} VND &nbsp;&nbsp;
        📈 <strong>Lợi nhuận:</strong> ${profit.toLocaleString()} VND`;
    }

    async function fetchServiceShare(branch, filter) {
      if (serviceChart) serviceChart.destroy();
      const res = await fetch(`components/get_service_share.php?branch=${branch}&filter=${filter}`);
      const data = await res.json();
      const ctx = document.getElementById('serviceShareChart').getContext('2d');
      serviceChart = new Chart(ctx, {
        type: 'pie',
        data: {
          labels: data.map(d => d.label),
          datasets: [{
            label: 'Số lượt đặt',
            data: data.map(d => d.value),
            backgroundColor: ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#3b82f6'],
            borderWidth: 1
          }]
        }
      });
      const total = data.reduce((sum, d) => sum + d.value, 0);
      document.getElementById('serviceSummary').innerText = `Tổng lượt đặt: ${total.toLocaleString()} lượt`;
    }

    async function fetchTimeSlotTrends(branch, filter) {
      if (slotChart) slotChart.destroy();
      const res = await fetch(`components/get_time_slot_trends.php?branch=${branch}&filter=${filter}`);
      const data = await res.json();
      const ctx = document.getElementById('timeSlotChart').getContext('2d');
      slotChart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: data.map(d => d.label),
          datasets: [{
            label: 'Số lượng',
            data: data.map(d => d.value),
            backgroundColor: '#6366f1',
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true } }
        }
      });
      const total = data.reduce((sum, d) => sum + d.value, 0);
      document.getElementById('timeSlotSummary').innerText = `Tổng lượt đặt lịch: ${total.toLocaleString()} lượt`;
    }

    async function fetchStaffAssignments(branch, filter) {
      if (staffChart) staffChart.destroy();
      const res = await fetch(`components/get_staff_assignments.php?branch=${branch}&filter=${filter}`);
      const data = await res.json();
      const ctx = document.getElementById('staffAssignChart').getContext('2d');
      staffChart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: data.map(d => d.name),
          datasets: [{
            label: 'Số lần được phân công',
            data: data.map(d => d.count),
            backgroundColor: '#10b981',
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true } }
        }
      });
      const total = data.reduce((sum, d) => sum + d.count, 0);
      document.getElementById('staffAssignSummary').innerText = `Tổng lượt phân công: ${total.toLocaleString()} lượt`;
    }

    function toggleSection(id) {
      document.getElementById(id).classList.toggle('hidden');
    }

    function updateAll() {
      const filter = document.getElementById('timeFilter').value;
      const branch = document.getElementById('branchFilter').value;
      fetchAndRenderChart(filter, branch);
      fetchServiceShare(branch, filter);
      fetchTimeSlotTrends(branch, filter);
      fetchStaffAssignments(branch, filter);
    }

    document.getElementById('branchFilter').addEventListener('change', updateAll);
    document.getElementById('timeFilter').addEventListener('change', updateAll);

    // Load ban đầu
    updateAll();
  </script>
</body>
</html>
