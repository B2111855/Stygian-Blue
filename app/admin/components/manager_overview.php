<?php
include '../../database/config.php';
/**
 * GIẢ ĐỊNH:
 * - Khi quản lý chi nhánh đăng nhập, session có lưu ID chi nhánh họ phụ trách.
 *   Ví dụ: $_SESSION['branch_id'] = 3;
 *   và tên chi nhánh: $_SESSION['branch_name'] = "Stygian Blue - Cần Thơ"
 *
 * Nếu bạn dùng cấu trúc khác thì chỉ cần thay 2 biến dưới đây.
 */
$branchId   = isset($_SESSION['branch_id']) ? intval($_SESSION['branch_id']) : 0;
$branchName = isset($_SESSION['branch_name']) ? $_SESSION['branch_name'] : 'Chi nhánh của tôi';

// fallback an toàn (nếu chưa cấu hình session):
if ($branchId <= 0) {
  // Ở môi trường dev bạn có thể hardcode tạm 1 chi nhánh
  $branchId = 1;
  $branchName = "Chi nhánh Demo";
}

?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <title>Tài chính chi nhánh - <?= htmlspecialchars($branchName) ?></title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gradient-to-br from-blue-100 to-indigo-200 min-h-screen py-10 px-6 text-gray-800">
  <div class="max-w-6xl mx-auto bg-white p-8 rounded-xl shadow-2xl ring-1 ring-indigo-100 relative">

    <!-- Header -->
    <header class="mb-8 flex flex-col md:flex-row md:items-start md:justify-between gap-4">
      <div>
        <h1 class="text-3xl md:text-4xl font-extrabold text-indigo-700 leading-tight">
          📍 <?= htmlspecialchars($branchName) ?>
        </h1>
        <p class="text-sm text-gray-500 mt-1">
          Báo cáo hoạt động tài chính nội bộ · chỉ hiển thị dữ liệu chi nhánh của bạn
        </p>
      </div>

      <div class="flex flex-col items-start md:items-end text-sm text-gray-600">
        <div class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-lg font-medium shadow-sm border border-indigo-200">
          ID chi nhánh: CN<?= $branchId ?>
        </div>
        <div class="mt-2 text-xs text-gray-400">
          Cập nhật: <span id="nowText"></span>
        </div>
      </div>
    </header>

    <!-- KPI hàng đầu -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
      <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200 rounded-lg p-4 shadow">
        <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wide">Doanh thu hôm nay</p>
        <p id="kpiTodayRevenue" class="text-2xl font-bold text-emerald-800 mt-1">0 VND</p>
        <p class="text-[11px] text-emerald-700/70 mt-1">Chỉ tính các giao dịch có LOẠI_GIAO_DICH = 'doanh thu'</p>
      </div>

      <div class="bg-gradient-to-br from-rose-50 to-rose-100 border border-rose-200 rounded-lg p-4 shadow">
        <p class="text-xs font-semibold text-rose-700 uppercase tracking-wide">Chi phí hôm nay</p>
        <p id="kpiTodayExpense" class="text-2xl font-bold text-rose-800 mt-1">0 VND</p>
        <p class="text-[11px] text-rose-700/70 mt-1">Đã bao gồm chi phí phát sinh</p>
      </div>

      <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200 rounded-lg p-4 shadow">
        <p class="text-xs font-semibold text-indigo-700 uppercase tracking-wide">Lũy kế tháng này</p>
        <p id="kpiMonthProfit" class="text-2xl font-bold text-indigo-800 mt-1">0 VND</p>
        <p class="text-[11px] text-indigo-700/70 mt-1">Doanh thu - (Chi phí + Thuế)</p>
      </div>

      <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 border border-yellow-200 rounded-lg p-4 shadow">
        <p class="text-xs font-semibold text-yellow-700 uppercase tracking-wide">Tình trạng</p>
        <p id="kpiStatus" class="text-base font-semibold text-yellow-800 mt-1">Đang tính...</p>
        <p class="text-[11px] text-yellow-700/70 mt-1">So với tổng chi</p>
      </div>
    </section>

    <!-- Bộ lọc thời gian -->
    <section class="flex flex-wrap gap-6 justify-center items-end mb-8">
      <div class="flex flex-col">
        <label class="text-gray-700 font-semibold mb-1">Chi nhánh:</label>
        <input
          class="border border-gray-300 rounded-lg px-4 py-2 bg-gray-100 text-gray-600 font-semibold cursor-not-allowed"
          value="<?= htmlspecialchars($branchName) ?>"
          disabled />
        <input type="hidden" id="branchFilter" value="cn<?= $branchId ?>" />
      </div>

      <div class="flex flex-col">
        <label class="text-gray-700 font-semibold mb-1">Thời gian:</label>
        <select id="timeFilter" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500">
          <option value="month">Theo tháng</option>
          <option value="quarter">Theo quý</option>
          <option value="year" selected>Theo năm</option>
        </select>
      </div>

      <div class="flex flex-col">
        <label class="text-gray-700 font-semibold mb-1 invisible select-none">_</label>
        <button
          id="btnReportAdmin"
          class="bg-indigo-600 text-white px-4 py-2 rounded-lg shadow hover:bg-indigo-700 transition font-semibold text-sm">
          📤 Gửi báo cáo cho admin
        </button>
      </div>
    </section>

    <!-- Biểu đồ -->
    <section class="bg-white p-4 rounded-lg shadow border">
      <canvas id="financialChart" height="120"></canvas>
    </section>

    <!-- Tóm tắt -->
    <section class="mt-8 bg-indigo-50 p-6 rounded-lg shadow-inner">
      <h2 class="text-xl font-bold text-indigo-700 mb-4 text-center">Tổng quan</h2>
      <p id="summary" class="text-base text-gray-700 whitespace-pre-line leading-relaxed"></p>
      <p id="profitRate" class="text-green-600 font-semibold mt-2 text-center"></p>
    </section>

    <!-- Chi phí phát sinh -->
    <section class="mt-10 border-t pt-8">
      <h2 class="text-2xl font-bold mb-4 text-indigo-700">
        💸 Chi phí phát sinh tháng hiện tại
      </h2>

      <div id="expenseTable" class="mb-6 text-sm"></div>

      <!-- Form cập nhật chi phí -->
      <button
        onclick="toggleExpenseForm()"
        class="mb-4 bg-yellow-500 text-white px-4 py-2 rounded-lg shadow hover:bg-yellow-600 transition text-sm font-medium">
        ✏️ Cập nhật chi phí phát sinh
      </button>

      <div id="expenseFormWrapper" class="hidden">
        <form id="expenseForm" class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-yellow-50 border border-yellow-200 p-4 rounded-lg shadow-inner">
          <input type="hidden" id="selectedBranch" name="branch" value="cn<?= $branchId ?>">

          <div>
            <label class="block text-sm text-gray-600 font-medium mb-1">Chi phí mặt bằng (VND):</label>
            <input type="number" name="rent" class="w-full px-4 py-2 border rounded-lg focus:ring-blue-400" min="0">
          </div>

          <div>
            <label class="block text-sm text-gray-600 font-medium mb-1">Chi phí điện nước (VND):</label>
            <input type="number" name="utilities" class="w-full px-4 py-2 border rounded-lg focus:ring-blue-400" min="0">
          </div>

          <div class="md:col-span-2 flex justify-between mt-2">
            <button type="submit"
              class="bg-blue-500 text-white px-5 py-2 rounded-lg hover:bg-blue-600 transition shadow-md text-sm font-semibold">
              📂 Lưu chi phí
            </button>

            <button type="button"
              onclick="toggleExpenseForm()"
              class="bg-gray-400 text-white px-5 py-2 rounded-lg hover:bg-gray-500 transition shadow-md text-sm font-semibold">
              ✖️ Đóng
            </button>
          </div>
        </form>

        <div id="expenseMessage" class="mt-4 text-sm font-medium text-center"></div>
      </div>
    </section>

    <!-- In báo cáo -->
    <div class="text-center mt-10">
      <button onclick="printReport()"
        class="bg-indigo-600 text-white px-5 py-2 rounded-lg font-semibold hover:bg-indigo-700 shadow-md text-sm">
        📄 In báo cáo chi nhánh
      </button>
    </div>

  </div><!-- /card container -->

  <script>
    // ========== Helpers giao diện ==========
    function toggleExpenseForm() {
      const wrapper = document.getElementById('expenseFormWrapper');
      wrapper.classList.toggle('hidden');
    }

    function showMessage(message, color) {
      const box = document.getElementById('expenseMessage');
      box.innerText = message;
      box.className = `mt-4 text-sm font-medium text-center text-${color}-600`;
    }

    // Hiển thị thời gian hiện tại ở header
    (function setNow() {
      const now = new Date();
      document.getElementById('nowText').textContent = now.toLocaleString('vi-VN');
    })();


    // ========== Chart + Summary ==========
    const ctx = document.getElementById('financialChart').getContext('2d');
    let chart;

    async function fetchAndRenderChart() {
      const filter = document.getElementById('timeFilter').value;
      const branch = document.getElementById('branchFilter').value;

      // 1. Lấy data tài chính (doanh thu / chi phí / lương)
      const res = await fetch(`./components/get_financial_data.php?filter=${filter}&branch=${branch}`);
      const data = await res.json();

      // 2. Lấy thuế
      const taxRes = await fetch(`./components/get_total_tax.php?branch=${branch}`);
      const taxData = await taxRes.json();
      const tax = taxData.tax || 0;

      // Hủy chart cũ nếu có
      if (chart) chart.destroy();

      chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: data.labels,
          datasets: [
            {
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

      // Tổng hợp
      const totalRevenue = data.revenue.reduce((a, b) => a + b, 0);
      const totalExpense = data.expense.reduce((a, b) => a + b, 0);
      const salaryExpense = data.salary_expense || 0;
      const totalExpenseWithTax = totalExpense + tax;
      const profit = totalRevenue - totalExpenseWithTax;
      const rate =
        totalExpenseWithTax === 0
          ? 'N/A'
          : ((profit / totalExpenseWithTax) * 100).toFixed(2) + '%';

      // render đoạn summary (cũng dùng lại trong print)
      const summaryHTML = `
        <div id="financialReport" class="text-left leading-relaxed space-y-1 text-sm md:text-base">
          <div>💰 <strong>Tổng doanh thu:</strong> ${totalRevenue.toLocaleString()} VND</div>
          <div>🧾 <strong>Chi phí:</strong> ${totalExpense.toLocaleString()} VND</div>
          <div>💼 <strong>Chi phí lương nhân viên:</strong> ${salaryExpense.toLocaleString()} VND</div>
          <div>🧮 <strong>Thuế (10%):</strong> ${tax.toLocaleString()} VND</div>
          <div>📊 <strong>Tổng chi phí (gồm thuế):</strong> ${totalExpenseWithTax.toLocaleString()} VND</div>
          <div>📈 <strong>Lợi nhuận:</strong> ${profit.toLocaleString()} VND</div>
        </div>
      `;
      document.getElementById('summary').innerHTML = summaryHTML;

      // Cập nhật KPI top
      document.getElementById('kpiMonthProfit').textContent = profit.toLocaleString() + ' VND';

      // Tính trạng thái
      let statusText = 'Ổn định';
      if (profit < 0) statusText = 'Lỗ - cần kiểm soát chi';
      else if (profit < totalRevenue * 0.1) statusText = 'Biên lợi nhuận thấp';
      else statusText = 'Khỏe mạnh';

      document.getElementById('kpiStatus').textContent = statusText;

      // (Bonus) KPI hôm nay
      // bạn có thể mở rộng get_financial_data.php trả thêm todayRevenue, todayExpense
      // nếu có thì set vào đây. nếu chưa có thì tạm dùng 0:
      document.getElementById('kpiTodayRevenue').textContent =
        (data.today_revenue || 0).toLocaleString() + ' VND';
      document.getElementById('kpiTodayExpense').textContent =
        (data.today_expense || 0).toLocaleString() + ' VND';
    }


    // ========== Bảng chi phí phát sinh ==========
    async function loadExpenses() {
      const branch = document.getElementById('branchFilter').value;
      const res = await fetch(`./components/get_expense_table.php?branch=${branch}`);
      const html = await res.text();
      document.getElementById('expenseTable').innerHTML = html;
    }


    // ========== Gửi form thêm chi phí ==========
    document.addEventListener('DOMContentLoaded', () => {
      const expenseForm = document.getElementById('expenseForm');

      expenseForm.addEventListener('submit', async function (e) {
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

          // reload data
          await fetchAndRenderChart();
          await loadExpenses();
        } catch (error) {
          console.error('❌ Lỗi:', error.message);
          showMessage('❌ ' + error.message, 'red');
        }
      });
    });


    // ========== In báo cáo ==========
    function printReport() {
      const today = new Date();
      const summaryContent = document.getElementById('financialReport');
      const expenseTable = document.getElementById('expenseTable');

      const rows = [];

      // lấy từ summary (bỏ dòng Thuế vì bảng phát sinh có thể cũng có VAT)
      summaryContent.querySelectorAll('div').forEach(div => {
        const strong = div.querySelector('strong');
        if (!strong) return;
        const label = strong.innerText.replace(':', '').trim();
        if (label.toLowerCase().includes('thuế')) return;
        const value = div.textContent.split(':').pop().trim();
        rows.push({ label, value });
      });

      // lấy từ bảng chi phí phát sinh tháng
      expenseTable.querySelectorAll('tbody tr').forEach(tr => {
        const cells = tr.querySelectorAll('td');
        if (cells.length >= 2) {
          const name = cells[0].innerText.trim();
          const value = cells[1].innerText.trim();
          rows.push({ label: name, value: value });
        }
      });

      let combinedTableHTML = `
        <table>
          <thead>
            <tr>
              <th>Khoản thu / chi</th>
              <th>Giá trị (VND)</th>
            </tr>
          </thead>
          <tbody>
            ${rows
              .map(
                row => `
              <tr>
                <td>${row.label}</td>
                <td>${row.value}</td>
              </tr>`
              )
              .join('')}
          </tbody>
        </table>
      `;

      const newWin = window.open('', '', 'width=900,height=700');
      newWin.document.write(`
        <html>
          <head>
            <title>Báo cáo tài chính chi nhánh</title>
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
            <h2>BÁO CÁO TÀI CHÍNH CHI NHÁNH <?= htmlspecialchars($branchName) ?> - THÁNG ${today.getMonth() + 1}/${today.getFullYear()}</h2>

            ${combinedTableHTML}

            <div class="signatures">
              <div>Người lập báo cáo</div>
              <div>Quản lý chi nhánh</div>
            </div>

            <div class="footer-note">
              Báo cáo được tạo từ hệ thống Stygian Blue • ${today.toLocaleString('vi-VN')}
            </div>
          </body>
        </html>
      `);
      newWin.document.close();
      newWin.print();
    }


    // ========== Event listeners ==========
    document.getElementById('timeFilter').addEventListener('change', async () => {
      await fetchAndRenderChart();
    });

    // nút "gửi báo cáo cho admin"
    document.getElementById('btnReportAdmin').addEventListener('click', () => {
      // ở đây bạn có thể mở modal, hoặc window.location tới 1 endpoint gửi notification
      alert('Đã ghi nhận yêu cầu gửi báo cáo cho admin.');
    });

    // init lần đầu
    (async function init() {
      await fetchAndRenderChart();
      await loadExpenses();
    })();
  </script>
</body>
</html>
