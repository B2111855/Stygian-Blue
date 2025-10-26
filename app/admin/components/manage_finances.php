<?php
include '../../database/config.php';

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
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gradient-to-br from-blue-100 to-indigo-200 min-h-screen py-10 px-6">
  <div class="max-w-6xl mx-auto bg-white p-8 rounded-xl shadow-2xl">
    <h1 class="text-4xl font-extrabold mb-8 text-center text-indigo-700">📊 Báo Cáo Tài Chính</h1>

    <!-- Bộ lọc -->
    <div class="flex flex-wrap gap-6 justify-center items-end mb-8">
      <div class="flex flex-col">
        <label class="text-gray-700 font-semibold mb-1">Chọn chi nhánh:</label>
        <select id="branchFilter" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500">
          <option value="all">Tất cả</option>
          <?php foreach ($branches as $branch): ?>
            <option value="cn<?= $branch['ID_CN'] ?>"><?= htmlspecialchars($branch['TEN_CN']) ?></option>
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

    <!-- Biểu đồ -->
    <div class="bg-white p-4 rounded-lg shadow border">
      <canvas id="financialChart" height="120"></canvas>
    </div>

    <!-- Tổng kết -->
    <div class="mt-8 bg-indigo-50 p-6 rounded-lg shadow-inner">
      <p id="summary" class="text-base text-gray-700 whitespace-pre-line leading-relaxed"></p>
      <p id="profitRate" class="text-green-600 font-semibold mt-2 text-center"></p>

      <!-- Chi phí -->
    <div class="mt-10 border-t pt-8">
      <h2 class="text-2xl font-bold mb-4 text-indigo-700">💸 Chi phí phát sinh tháng hiện tại</h2>
      <div id="expenseTable" class="mb-6"></div>

      <button onclick="toggleExpenseForm()" class="mb-4 bg-yellow-500 text-white px-4 py-2 rounded-lg shadow hover:bg-yellow-600 transition">
        ✏️ Cập nhật chi phí phát sinh
      </button>
      <div id="expenseFormWrapper" class="hidden">
        <form id="expenseForm" class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <input type="hidden" id="selectedBranch" name="branch" value="">
          <div>
            <label class="block text-sm text-gray-600 font-medium mb-1">Chi phí mặt bằng (VND):</label>
            <input type="number" name="rent" class="w-full px-4 py-2 border rounded-lg focus:ring-blue-400" min="0">
          </div>
          <div>
            <label class="block text-sm text-gray-600 font-medium mb-1">Chi phí điện nước (VND):</label>
            <input type="number" name="utilities" class="w-full px-4 py-2 border rounded-lg focus:ring-blue-400" min="0">
          </div>
          <div class="md:col-span-2 flex justify-between mt-2">
            <button type="submit" class="bg-blue-500 text-white px-5 py-2 rounded-lg hover:bg-blue-600 transition shadow-md">
              📂 Lưu chi phí
            </button>
            <button type="button" onclick="toggleExpenseForm()" class="bg-gray-400 text-white px-5 py-2 rounded-lg hover:bg-gray-500 transition shadow-md">
              ✖️ Đóng
            </button>
          </div>
        </form>
        <div id="expenseMessage" class="mt-4 text-sm font-medium text-center"></div>
      </div>
    </div>

    <!-- In báo cáo -->
    <div class="text-center mt-10">
      <button onclick="printReport()" class="bg-indigo-600 text-white px-5 py-2 rounded-lg font-semibold hover:bg-indigo-700 shadow-md">
        📄 In báo cáo tài chính
      </button>
    </div>
  </div>


  <script>
    function toggleExpenseForm() {
  const wrapper = document.getElementById('expenseFormWrapper');
  wrapper.classList.toggle('hidden');
    }
    const ctx = document.getElementById('financialChart').getContext('2d');
    let chart;

    async function fetchAndRenderChart(filter = 'year', branch = 'all') {
      const res = await fetch(`./components/get_financial_data.php?filter=${filter}&branch=${branch}`);
      const data = await res.json();

      if (chart) chart.destroy();

      chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: data.labels,
          datasets: [{
              label: 'Doanh thu',
              data: data.revenue,
              borderColor: 'green',
              fill: false,
              tension: 0.3
            },
            {
              label: 'Chi phí',
              data: data.expense,
              borderColor: 'red',
              fill: false,
              tension: 0.3
            }
          ]
        }
      });

      const totalRevenue = data.revenue.reduce((a, b) => a + b, 0);
      const totalExpense = data.expense.reduce((a, b) => a + b, 0);
      const salaryExpense = data.salary_expense || 0;
      const taxRate = 0.1;
      const taxRes = await fetch(`./components/get_total_tax.php?branch=${branch}`);
  const taxData = await taxRes.json();
  const tax = taxData.tax;
      const totalExpenseWithTax = totalExpense + tax;
      const profit = totalRevenue - totalExpenseWithTax;
      const rate = totalExpenseWithTax === 0 ? 'N/A' : ((profit / totalExpenseWithTax) * 100).toFixed(2) + '%';

      const summaryHTML = `
  <div id="financialReport" class="text-left leading-relaxed space-y-1">
    <div>💰 <strong>Tổng doanh thu:</strong> ${totalRevenue.toLocaleString()} VND</div>
    <div>🧾 <strong>Chi phí:</strong> ${totalExpense.toLocaleString()} VND</div>
    <div>💼 <strong>Chi phí lương nhân viên:</strong> ${salaryExpense.toLocaleString()} VND</div>
    <div>🧮 <strong>Thuế (10%):</strong> ${tax.toLocaleString()} VND</div>
    <div>📊 <strong>Tổng chi phí (gồm thuế):</strong> ${totalExpenseWithTax.toLocaleString()} VND</div>
    <div>📈 <strong>Lợi nhuận:</strong> ${profit.toLocaleString()} VND</div>
  </div>
`;

      document.getElementById('summary').innerHTML = summaryHTML;
    }



    async function loadExpenses(branch) {
      const res = await fetch(`./components/get_expense_table.php?branch=${branch}`);
      const html = await res.text();
      document.getElementById('expenseTable').innerHTML = html;
    }

    document.getElementById('timeFilter').addEventListener('change', () => {
      const filter = document.getElementById('timeFilter').value;
      const branch = document.getElementById('branchFilter').value;
      fetchAndRenderChart(filter, branch);
    });

    document.getElementById('branchFilter').addEventListener('change', () => {
      const filter = document.getElementById('timeFilter').value;
      const branch = document.getElementById('branchFilter').value;
      document.getElementById('selectedBranch').value = branch;
      fetchAndRenderChart(filter, branch);
      loadExpenses(branch);
    });

    document.getElementById('expenseForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);

  try {
    const response = await fetch('./components/save_expenses.php', {
      method: 'POST',
      body: formData
    });
    const result = await response.json();

    if (result.status !== 'success') throw new Error(result.message);

    showMessage('✅ ' + result.message, 'green');

    const branch = document.getElementById('branchFilter').value;
    const filter = document.getElementById('timeFilter').value;

    // 🔁 Gọi lại để cập nhật ngay biểu đồ và summary
    await fetchAndRenderChart(filter, branch);
    await loadExpenses(branch);
  } catch (error) {
    console.error('❌ Lỗi:', error.message);
    showMessage('❌ ' + error.message, 'red');
  }
});


    const initialBranch = document.getElementById('branchFilter').value;
    document.getElementById('selectedBranch').value = initialBranch;
    fetchAndRenderChart();
    loadExpenses(initialBranch);

    function showMessage(message, color) {
      const box = document.getElementById('expenseMessage');
      box.innerText = message;
      box.className = `mt-4 text-sm font-medium text-center text-${color}-600`;
    }

    function printReport() {
  const today = new Date();
  const formattedDate = today.toLocaleDateString('vi-VN');

  const summaryContent = document.getElementById('financialReport');
  const expenseTable = document.getElementById('expenseTable');

  // Gom tất cả nội dung liên quan thu/chi/thue vào 1 bảng mới
  const rows = [];

  // Lấy các dòng từ phần summary (định dạng: <div>📌 <strong>Label:</strong> value</div>)
  // Lấy các dòng từ phần summary, trừ dòng thuế
summaryContent.querySelectorAll('div').forEach(div => {
  const strong = div.querySelector('strong');
  if (!strong) return;
  const label = strong.innerText.replace(':', '').trim();

  // ❌ Bỏ dòng Thuế (đã có trong bảng phát sinh)
  if (label.toLowerCase().includes('thuế')) return;

  const value = div.textContent.split(':').pop().trim();
  rows.push({ label, value });
});


  // Lấy thêm từ bảng chi phí phát sinh
  expenseTable.querySelectorAll('tbody tr').forEach(tr => {
    const cells = tr.querySelectorAll('td');
    if (cells.length >= 2) {
      const name = cells[0].innerText.trim();
      const value = cells[1].innerText.trim();
      rows.push({ label: name, value: value });
    }
  });

  // Tạo HTML bảng
  let combinedTableHTML = `
    <table>
      <thead>
        <tr>
          <th>Khoản thu / chi</th>
          <th>Giá trị (VND)</th>
        </tr>
      </thead>
      <tbody>
        ${rows.map(row => `
          <tr>
            <td>${row.label}</td>
            <td>${row.value}</td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;

  const newWin = window.open('', '', 'width=900,height=700');
  newWin.document.write(`
    <html>
      <head>
        <title>Báo cáo tài chính</title>
        <style>
          body {
            font-family: 'Arial', sans-serif;
            padding: 40px;
            line-height: 1.6;
            color: #333;
            background: #f9fafb;
          }
          h1 {
            text-align: center;
            font-size: 28px;
            color: #1e3a8a;
            margin-bottom: 10px;
          }
          h2 {
            text-align: center;
            font-size: 20px;
            color: #444;
            margin-bottom: 30px;
          }
          .section {
            margin-top: 40px;
          }
          table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
          }
          th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
          }
          th {
            background-color: #edf2f7;
            font-weight: bold;
            text-align: center;
          }
          td:last-child {
            text-align: right;
            font-weight: 500;
            color: #2d3748;
          }
          .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
            padding: 0 10%;
          }
          .signatures div {
            text-align: center;
            font-size: 14px;
          }
          .signatures div::after {
            content: "_________________________";
            display: block;
            margin-top: 40px;
          }
          .footer-note {
            text-align: center;
            margin-top: 40px;
            font-size: 12px;
            color: #777;
          }
        </style>
      </head>
      <body>
        <h1>STYGIAN BLUE STUDIO</h1>
        <h2>BÁO CÁO TÀI CHÍNH THÁNG ${today.getMonth() + 1}/${today.getFullYear()}</h2>

        <div class="section">
          ${combinedTableHTML}
        </div>

        <div class="signatures">
          <div>Người lập báo cáo</div>
          <div>Quản lý chi nhánh</div>
        </div>

        <div class="footer-note">
          Báo cáo được tạo tự động từ hệ thống Stygian Blue • ${today.toLocaleString()}
        </div>
      </body>
    </html>
  `);

  newWin.document.close();
  newWin.print();
}

  </script>
</body>

</html>