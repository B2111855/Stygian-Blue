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
          <article class="rounded-xl border border-blue-100 bg-blue-50 p-4 shadow-sm">
            <div class="flex items-center justify-between">
              <p class="text-sm font-medium text-blue-700">Doanh thu dự kiến</p>
            </div>
            <p id="statRevenue" class="text-2xl font-semibold text-blue-900">--</p>
            <div class="text-xs text-blue-600 mt-2 bg-blue-100 p-2 rounded border border-blue-200">
              <p class="font-mono">= Doanh thu chờ thanh toán + Doanh thu đã thanh toán</p>
              <p class="text-blue-700 mt-1">Từ quote, booking, appointment (chưa/đã thanh toán)</p>
            </div>
          </article>
          <article class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 shadow-sm">
            <div class="flex items-center justify-between">
              <p class="text-sm font-medium text-emerald-700">Doanh thu thực tế</p>
            </div>
            <p id="statRevenueActual" class="text-2xl font-semibold text-emerald-900">--</p>
            <div class="text-xs text-emerald-600 mt-2 bg-emerald-100 p-2 rounded border border-emerald-200">
              <p class="font-mono">= Doanh thu đã thanh toán</p>
              <p class="text-emerald-700 mt-1">Chỉ những đơn hàng đã nhận tiền (confirmed)</p>
            </div>
          </article>
          <article class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
              <p class="text-sm font-medium text-gray-700">Tổng chi phí</p>
            </div>
            <p id="statExpense" class="text-2xl font-semibold text-gray-900">--</p>
            <div class="text-xs text-gray-600 mt-2 bg-gray-100 p-2 rounded border border-gray-300">
              <p class="font-mono">= Chi phí phát sinh (vận hành) + Lương nhân viên + Thuế</p>
              <p class="text-gray-700 mt-1">Chi phí phát sinh đã bao gồm điện, nước, mặt bằng, bảo trì...</p>
            </div>
          </article>
          <article class="rounded-xl border border-red-100 bg-red-50 p-4 shadow-sm">
            <div class="flex items-center justify-between">
              <p class="text-sm font-medium text-red-700">Lợi nhuận ròng</p>
            </div>
            <p id="statProfit" class="text-2xl font-semibold text-red-900">--</p>
            <div class="text-xs text-red-600 mt-2 bg-red-100 p-2 rounded border border-red-200">
              <p class="font-mono">= Doanh thu thực tế - (Chi phí phát sinh + Lương + Thuế)</p>
              <p class="text-red-700 mt-1">Lợi nhuận thực tế sau tất cả chi phí</p>
            </div>
          </article>
          <article class="rounded-xl border border-orange-100 bg-orange-50 p-4 shadow-sm">
            <div class="flex items-center justify-between">
              <p class="text-sm font-medium text-orange-700">Tỷ suất lợi nhuận</p>
            </div>
            <p id="statMargin" class="text-2xl font-semibold text-orange-900">--</p>
            <div class="text-xs text-orange-600 mt-2 bg-orange-100 p-2 rounded border border-orange-200">
              <p class="font-mono">= (Lợi nhuận ÷ Tổng chi phí) × 100%</p>
              <p class="text-orange-700 mt-1">Tỷ suất lợi nhuận trên chi phí</p>
            </div>
          </article>
        </div>

        <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-5">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold text-gray-900">Tóm tắt chi tiết</h2>
            <span class="text-xs text-gray-500">Tự động cập nhật</span>
          </div>
          <div id="summary" class="text-sm sm:text-base text-gray-700 leading-relaxed mb-4"></div>
          <div class="grid grid-cols-1 gap-2 p-3 bg-gray-50 rounded-lg border border-gray-200 text-xs">
            <p class="font-semibold text-gray-900">Ghi chú quan trọng:</p>
            <p><strong>Doanh thu dự kiến</strong> = Tất cả quote/booking/appointment (chưa/đã thanh toán)</p>
            <p><strong>Doanh thu thực tế</strong> = Chỉ những đơn đã nhận tiền (tính thuế VAT)</p>
            <p><strong>Tỷ suất</strong> = (Lợi nhuận / Chi phí) × 100% - tỷ suất trên chi phí, không phải doanh thu</p>
            <p><strong>Chi phí phát sinh</strong> đang đóng vai trò chi phí vận hành (điện, nước, mặt bằng...)</p>
          </div>
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
              <span class="text-xs text-slate-500">Thuế + lương + chi phí phát sinh</span>
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
    <!-- Section: Quản lý Chi phí Phát sinh -->
    <section class="border-t pt-8">
      <div class="mb-4">
        <button type="button" onclick="toggleExpensePanel()" class="rounded-lg border border-indigo-300 bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 transition flex items-center gap-2">
          Quản lý chi phí phát sinh tháng hiện tại
        </button>
      </div>

      <!-- Expense Management Panel (Collapsible) -->
      <div id="expensePanel" class="border border-indigo-100 rounded-lg bg-indigo-50 p-6 mb-6 hidden">
        <!-- Panel Header -->
        <div class="mb-6 pb-4 border-b border-indigo-200 flex items-center justify-between">
          <div>
            <h2 class="text-xl font-bold text-gray-900">Quản lý Chi phí Phát sinh</h2>
            <p class="text-sm text-gray-600 mt-1">Tháng hiện tại: <strong id="currentExpenseMonth">12/2025</strong></p>
          </div>
          <button onclick="toggleExpensePanel()" class="text-gray-500 hover:text-gray-700 text-xl">×</button>
        </div>

        <!-- Tab Navigation - Giảm từ 5 tab xuống 3 tab chính -->
        <div class="flex border-b border-indigo-200 gap-4 mb-6 overflow-x-auto">
          <button onclick="switchExpensePanel('view')" id="panel-tab-view" class="px-4 py-2 font-medium text-indigo-600 border-b-2 border-indigo-600 text-sm whitespace-nowrap">Xem & Quản lý</button>
          <button onclick="switchExpensePanel('manage')" id="panel-tab-manage" class="px-4 py-2 font-medium text-gray-600 border-b-2 border-transparent hover:text-gray-700 text-sm whitespace-nowrap">Loại chi phí</button>
          <button onclick="switchExpensePanel('stats')" id="panel-tab-stats" class="px-4 py-2 font-medium text-gray-600 border-b-2 border-transparent hover:text-gray-700 text-sm whitespace-nowrap">Thống kê</button>
        </div>

        <!-- Tab: View & Manage -->
        <div id="panel-content-view" class="space-y-4">
          <div class="flex gap-2 mb-4">
            <div class="flex-1">
              <label class="text-sm text-gray-700 font-medium block mb-2">Chọn chi nhánh</label>
              <select id="panelBranchSelect" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" onchange="loadPanelExpenses()">
                <option value="">-- Chọn chi nhánh --</option>
                <?php 
                  $branchQuery = mysqli_query($conn, "SELECT ID_CN, TEN_CN FROM chi_nhanh ORDER BY TEN_CN");
                  while ($b = mysqli_fetch_assoc($branchQuery)) {
                    echo '<option value="' . $b['ID_CN'] . '">' . htmlspecialchars($b['TEN_CN']) . '</option>';
                  }
                ?>
              </select>
            </div>
            <div class="flex items-end">
              <button onclick="copyFromPreviousMonth()" class="rounded-lg bg-green-600 px-4 py-2 font-semibold text-white hover:bg-green-700 transition">Copy tháng trước</button>
            </div>
          </div>
          <div id="panelExpenseTableContainer" class="border border-gray-200 rounded-lg overflow-hidden bg-white"></div>
        </div>

        <!-- Tab: History -->
        <div id="panel-content-history" class="space-y-4" style="display: none;">
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
            <p class="text-sm text-blue-900"><strong>ℹ️ Lịch sử chi phí:</strong> Xem chi phí của từng loại qua các tháng và chi nhánh</p>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
              <label class="text-sm text-gray-700 font-medium block mb-2">Chọn loại chi phí</label>
              <select id="historyExpenseTypeSelect" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" onchange="loadExpenseHistory()">
                <option value="">-- Chọn loại --</option>
              </select>
            </div>
            <div>
              <label class="text-sm text-gray-700 font-medium block mb-2">Sắp xếp theo</label>
              <select id="historySortSelect" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" onchange="loadExpenseHistory()">
                <option value="thang_desc">Tháng mới nhất</option>
                <option value="thang_asc">Tháng cũ nhất</option>
                <option value="branch">Chi nhánh</option>
              </select>
            </div>
          </div>
          <div id="panelHistoryTableContainer" class="border border-gray-200 rounded-lg overflow-hidden bg-white"></div>
        </div>

        <!-- Tab: Summary -->
        <div id="panel-content-summary" class="space-y-4" style="display: none;">
          <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
            <p class="text-sm text-green-900"><strong>ℹ️ Tổng hợp chi phí:</strong> Tổng cộng tất cả chi nhánh theo loại</p>
          </div>
          <div>
            <label class="text-sm text-gray-700 font-medium block mb-2">Chọn tháng</label>
            <input type="month" id="summarySummaryMonthSelect" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" onchange="loadExpenseSummary()">
          </div>
          <div id="panelSummaryContainer" class="space-y-4">
            <!-- Summary cards will be rendered here -->
          </div>
        </div>

        <!-- Tab: Thống kê (Hợp nhất History + Summary) - NEW -->
        <div id="panel-content-stats" class="space-y-4" style="display: none;">
          <div class="flex flex-col sm:flex-row gap-4 mb-4">
            <div class="flex-1">
              <label class="text-sm font-medium text-gray-700 block mb-2">Loại chi phí</label>
              <select id="statsExpenseTypeSelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500" onchange="loadStatsData()">
                <option value="">-- Tất cả loại --</option>
              </select>
            </div>
            <div class="flex-1">
              <label class="text-sm font-medium text-gray-700 block mb-2">Tháng</label>
              <input type="month" id="statsMonthSelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500" onchange="loadStatsData()">
            </div>
            <div class="flex gap-2 mt-auto">
              <button onclick="showStatsHistoryView()" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                Chi tiết
              </button>
              <button onclick="showStatsSummaryView()" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                Tổng hợp
              </button>
            </div>
          </div>
          <div id="statsContentArea" class="border border-gray-200 rounded-lg overflow-hidden bg-white"></div>
        </div>

        <!-- Tab: Add New Expense Type (Admin) - KEEP for backward compat, will be replaced by modal -->
        <div id="panel-content-add" class="space-y-4" style="display: none;">
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
            <p class="text-sm text-blue-900"><strong>ℹ️ Hướng dẫn:</strong> Tạo loại chi phí mới sẽ áp dụng cho <strong>tất cả chi nhánh</strong></p>
          </div>
          <div>
            <label class="text-sm text-gray-700 font-medium block mb-2">Tên loại chi phí</label>
            <input type="text" id="panelAddExpenseName" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Ví dụ: Vệ sinh, Bảo dưỡng, Điện nước...">
          </div>
          <div>
            <label class="text-sm text-gray-700 font-medium block mb-2">Mô tả (tùy chọn)</label>
            <textarea id="panelAddExpenseDesc" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Mô tả chi tiết loại chi phí này" rows="3"></textarea>
          </div>
          <button onclick="confirmPanelAddNewExpenseType()" class="w-full rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700 transition">✅ Tạo loại chi phí</button>
        </div>

        <!-- Tab: Manage Expense Types (Admin) - Hợp nhất Add + Manage -->
        <div id="panel-content-manage" class="space-y-4" style="display: none;">
          <div class="bg-red-50 border border-red-300 rounded-lg p-4 mb-4">
            <p class="text-sm text-red-900 font-semibold mb-2">⚠️ CẢNH BÁO - KHÔNG THỂ KHÔI PHỤC!</p>
            <p class="text-sm text-red-800">Xóa loại chi phí sẽ xóa vĩnh viễn loại chi phí <strong>và tất cả dữ liệu chi phí liên quan</strong> trong hệ thống. Hành động này không thể hoàn tác.</p>
          </div>
          
          <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold text-gray-900">Danh sách loại chi phí</h3>
            <button onclick="showAddExpenseTypeModal()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
              Thêm loại
            </button>
          </div>
          
          <div id="panelManageExpenseList" class="grid gap-3">
            <!-- Dynamic list will be rendered here -->
          </div>
        </div>

        <!-- Tab: Copy from Previous Month -->
        <div id="panel-content-copy" class="space-y-4" style="display: none;">
          <!-- Removed - using inline button instead -->
        </div>
      </div>

      <!-- Modal Dialog: Thêm/Sửa Loại Chi phí -->
      <div id="addExpenseTypeModal" style="display:none" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-sm">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900">Tạo loại chi phí mới</h3>
            <button onclick="closeAddExpenseTypeModal()" class="text-gray-500 hover:text-gray-700 text-xl">×</button>
          </div>
          
          <div class="space-y-3 mb-5">
            <div>
              <label class="text-sm font-medium text-gray-700 block mb-1">Tên loại chi phí <span class="text-red-500">*</span></label>
              <input type="text" id="modalExpenseTypeName" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="VD: Vệ sinh, Bảo dưỡng, Điện nước...">
            </div>
            <div>
              <label class="text-sm font-medium text-gray-700 block mb-1">Mô tả (tùy chọn)</label>
              <textarea id="modalExpenseTypeDesc" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent" rows="2" placeholder="Mô tả chi tiết loại chi phí này"></textarea>
            </div>
          </div>
          
          <div class="flex gap-2">
            <button onclick="closeAddExpenseTypeModal()" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
              Hủy
            </button>
            <button onclick="saveExpenseTypeFromModal()" class="flex-1 px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
              Tạo
            </button>
          </div>
          
          <p class="text-xs text-gray-500 mt-4 text-center">Loại chi phí sẽ áp dụng cho <strong>tất cả chi nhánh</strong></p>
        </div>
      </div>

    </section>

    <!-- Section: Thuế Chi Trả -->
    <section class="border-t pt-8">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900">Thuế chi trả tháng hiện tại</h2>
          <p class="text-sm text-gray-500">Thuế VAT (10% doanh thu thực tế) + Thuế DN (20% lợi nhuận)</p>
        </div>
        <button type="button" onclick="calculateAndRefreshTaxes()" class="self-start rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-100 transition">Tính toán lại thuế</button>
      </div>

      <!-- Tax Explanation -->
      <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
        <p class="font-semibold mb-2">⚠️ Cách tính thuế:</p>
        <ul class="space-y-1 text-xs">
          <li>• <strong>Thuế VAT (10%)</strong> = Doanh thu <u>thực tế đã thanh toán</u> × 10%</li>
          <li>• <strong>Thuế DN (20%)</strong> = Lợi nhuận × 20% (chỉ khi lợi nhuận > 0)</li>
          <li>• <strong>Lưu ý:</strong> Thuế tính trên doanh thu đã nhận tiền, không phải doanh thu dự kiến</li>
        </ul>
      </div>

      <!-- Tax Display Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <!-- Thuế VAT -->
        <div class="border rounded-lg p-4 bg-blue-50 border-blue-200">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm text-blue-600 font-medium">Thuế VAT</p>
            <span title="10% × Doanh thu thực tế đã nhận tiền" class="text-xs text-blue-500 cursor-help">ℹ️</span>
          </div>
          <p id="taxVatAmount" class="text-2xl font-bold text-blue-900">--</p>
          <p class="text-xs text-blue-600 mt-1">10% doanh thu thực tế</p>
        </div>

        <!-- Thuế Doanh Nghiệp -->
        <div class="border rounded-lg p-4 bg-orange-50 border-orange-200">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm text-orange-600 font-medium">Thuế DN</p>
            <span title="20% × Lợi nhuận (nếu > 0)" class="text-xs text-orange-500 cursor-help">ℹ️</span>
          </div>
          <p id="taxCorpAmount" class="text-2xl font-bold text-orange-900">--</p>
          <p class="text-xs text-orange-600 mt-1">20% lợi nhuận (nếu lãi)</p>
        </div>

        <!-- Tổng Thuế -->
        <div class="border rounded-lg p-4 bg-red-50 border-red-200">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm text-red-600 font-medium">Tổng Thuế</p>
            <span title="VAT + Thuế DN" class="text-xs text-red-500 cursor-help">ℹ️</span>
          </div>
          <p id="taxTotalAmount" class="text-2xl font-bold text-red-900">--</p>
          <p class="text-xs text-red-600 mt-1">VAT + Thuế DN</p>
        </div>
      </div>

      <!-- Tax Detail Table -->
      <div id="taxTable" class="mb-6 text-sm text-gray-700"></div>

    </section>

    <!-- Modal Quản lý Chi phí Phát sinh (deprecated - now using collapsible panel) -->
    <!-- REMOVED - Functionality moved to collapsible panel above -->

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
        refreshTaxes();
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
        dom.statRevenueActual = document.getElementById('statRevenueActual');
        dom.statExpense = document.getElementById('statExpense');
        dom.statProfit = document.getElementById('statProfit');
        dom.statMargin = document.getElementById('statMargin');
        dom.trendTable = document.getElementById('trendTable');
        dom.insightList = document.getElementById('insightList');
        dom.compareSummary = document.getElementById('compareSummary');
        dom.advancedMetrics = document.getElementById('advancedMetrics');
        dom.refreshBtn = document.getElementById('refreshBtn');
        dom.downloadCsv = document.getElementById('downloadCsv');
        // Expense table moved to panel - removed old dom reference
        dom.taxVatAmount = document.getElementById('taxVatAmount');
        dom.taxCorpAmount = document.getElementById('taxCorpAmount');
        dom.taxTotalAmount = document.getElementById('taxTotalAmount');
        dom.taxTable = document.getElementById('taxTable');
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
          dom.branchName.innerText = dom.branchFilter.options[dom.branchFilter.selectedIndex].text;
          await refreshPrimaryDataset();
          await refreshTaxes();
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
          await refreshTaxes();
        });

        dom.downloadCsv.addEventListener('click', downloadCsvReport);
      }

      function bootstrapState() {
        state.currentBranch = dom.branchFilter.value;
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
          fetch(`components/get_financial_data.php?filter=${filter}&branch=${branch}`),
          fetch(`components/get_total_tax.php?branch=${branch}`)
        ]);

        if (!financialRes.ok) throw new Error('Không nhận được dữ liệu doanh thu/chi phí');
        if (!taxRes.ok) throw new Error('Không nhận được dữ liệu thuế');

        const data = await financialRes.json();
        const taxPayload = await taxRes.json();
        return { ...data, tax: taxPayload?.tax ? Number(taxPayload.tax) : 0 };
      }

      // refreshExpenses() removed - expense loading moved to panel functions
      // Use loadPanelExpenses() in the collapsible panel instead

      async function refreshTaxes() {
        try {
          const branchId = state.currentBranch === 'all' ? 'all' : state.currentBranch.replace('cn', '');
          const currentMonth = new Date().toISOString().slice(0, 7);
          
          const res = await fetch(`components/get_taxes.php?branch_id=${branchId}&month=${currentMonth}`);
          const data = await res.json();
          
          if (data.status === 'success') {
            let vatAmount = 0, corpAmount = 0;
            
            data.taxes.forEach(tax => {
              if (tax.type === 'Thuế VAT') {
                vatAmount = tax.amount;
              } else if (tax.type === 'Thuế DN') {
                corpAmount = tax.amount;
              }
            });
            
            const totalTax = vatAmount + corpAmount;
            dom.taxVatAmount.innerText = formatCurrency(vatAmount);
            dom.taxCorpAmount.innerText = formatCurrency(corpAmount);
            dom.taxTotalAmount.innerText = formatCurrency(totalTax);
            
            // Render tax table
            let html = '<div class="overflow-x-auto border rounded-lg"><table class="w-full text-sm">';
            html += '<thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left font-semibold">Loại thuế</th><th class="px-4 py-3 text-right font-semibold">Tỷ lệ</th><th class="px-4 py-3 text-right font-semibold">Số tiền</th><th class="px-4 py-3 text-left font-semibold">Cơ sở tính</th></tr></thead>';
            html += '<tbody>';
            
            data.taxes.forEach(tax => {
              html += `<tr class="border-t hover:bg-gray-50">
                <td class="px-4 py-3 font-medium">${tax.type}</td>
                <td class="px-4 py-3 text-right">${tax.rate}%</td>
                <td class="px-4 py-3 text-right font-semibold">${formatCurrency(tax.amount)}</td>
                <td class="px-4 py-3">${tax.baseOn}</td>
              </tr>`;
            });
            
            html += '</tbody></table></div>';
            dom.taxTable.innerHTML = html;
          } else {
            dom.taxVatAmount.innerText = '--';
            dom.taxCorpAmount.innerText = '--';
            dom.taxTotalAmount.innerText = '--';
            dom.taxTable.innerHTML = '<p class="text-gray-500">Chưa có dữ liệu thuế</p>';
          }
        } catch (error) {
          console.error('Lỗi tải thuế:', error);
          dom.taxVatAmount.innerText = '--';
          dom.taxCorpAmount.innerText = '--';
          dom.taxTotalAmount.innerText = '--';
          dom.taxTable.innerHTML = '<p class="text-red-600">Không thể tải dữ liệu thuế.</p>';
        }
      }

      async function calculateAndRefreshTaxes() {
        try {
          const branchId = state.currentBranch === 'all' ? 'all' : state.currentBranch.replace('cn', '');
          const currentMonth = new Date().toISOString().slice(0, 7);
          
          const res = await fetch(`components/calculate_taxes.php?branch_id=${branchId}&month=${currentMonth}`);
          const data = await res.json();
          
          if (data.status === 'success') {
            alert('Đã tính toán lại thuế thành công!');
            await refreshTaxes();
          } else {
            alert('Lỗi: ' + data.message);
          }
        } catch (error) {
          console.error('Lỗi tính thuế:', error);
          alert('Không thể tính lại thuế');
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
            label: 'Chi phí phát sinh',
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
          { label: 'Chi phí phát sinh (vận hành)', value: totals.totalExpense },
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
              backgroundColor: ['#38bdf8', '#fbbf24', '#6366f1', '#f97316', '#14b8a6'],
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
        const incidentalExpense = totals.incidentalExpense;
        dom.summary.innerHTML = `
          <div id="financialReport" class="space-y-2 text-sm">
            <!-- DOANH THU -->
            <div class="pb-2 border-b border-gray-200">
              <p class="flex items-center justify-between">
                <span><strong>Tổng doanh thu:</strong> <span class="text-xs text-gray-500">(đã thanh toán + chờ thanh toán)</span></span>
                <span class="font-semibold text-green-700">${formatCurrency(totals.totalRevenue)}</span>
              </p>
              <p class="text-xs text-gray-500 ml-4 mt-1">
                = Doanh thu dự kiến (chờ thanh toán) + Doanh thu thực tế (đã thanh toán)<br>
                = SUM(revenue_predicted + revenue_actual) từ get_financial_data.php
              </p>
            </div>

            <!-- CHI PHÍ PHÁT SINH (VẬN HÀNH) -->
            <div class="pb-2 border-b border-gray-200">
              <p class="flex items-center justify-between">
                <span><strong>Chi phí phát sinh:</strong> <span class="text-xs text-gray-500">(điện, nước, mặt bằng, bảo trì...)</span></span>
                <span class="font-semibold text-orange-700">${formatCurrency(totals.totalExpense)}</span>
              </p>
              <p class="text-xs text-gray-500 ml-4 mt-1">
                = SUM(SO_TIEN) từ tai_chinh<br>
                WHERE LOAI_GIAO_DICH='chi phí' AND LOAI_CHI_TIET='Chi phí phát sinh'<br>
                <span class="text-blue-600">💡 Đây là chi phí vận hành chính (đã gộp toàn bộ điện, nước, mặt bằng...)</span>
              </p>
            </div>

            <!-- CHI PHÍ LƯƠNG -->
            <div class="pb-2 border-b border-gray-200">
              <p class="flex items-center justify-between">
                <span><strong>Chi phí lương nhân viên:</strong> <span class="text-xs text-gray-500">(tiền lương toàn bộ nhân viên)</span></span>
                <span class="font-semibold text-orange-700">${formatCurrency(totals.salaryExpense)}</span>
              </p>
              <p class="text-xs text-gray-500 ml-4 mt-1">
                = SUM(SO_TIEN) từ tai_chinh<br>
                WHERE LOAI_CHI_TIET='Lương nhân viên'<br>
                <span class="text-blue-600">💡 Lưu từ manage_salaries.php khi tính lương</span>
              </p>
            </div>

            <!-- THUẾ -->
            <div class="pb-2 border-b border-gray-200">
              <p class="flex items-center justify-between">
                <span><strong>Thuế:</strong> <span class="text-xs text-gray-500">(thuế VAT + thuế doanh nghiệp)</span></span>
                <span class="font-semibold text-orange-700">${formatCurrency(totals.tax)}</span>
              </p>
              <p class="text-xs text-gray-500 ml-4 mt-1">
                = SUM(SO_TIEN) từ tai_chinh<br>
                WHERE LOAI_CHI_TIET='Thuế VAT' OR LOAI_CHI_TIET='Thuế DN'<br>
                <span class="text-blue-600">💡 Lưu từ save_expenses.php hoặc tính toán thuế</span>
              </p>
            </div>

            <!-- TỔNG CHI PHÍ -->
            <div class="pb-2 border-b border-gray-200 bg-red-50 p-2 rounded">
              <p class="flex items-center justify-between">
                <span><strong>Tổng chi phí (gồm cả thuế):</strong></span>
                <span class="font-bold text-red-800">${formatCurrency(totals.totalExpenseWithTax)}</span>
              </p>
              <p class="text-xs text-gray-600 ml-4 mt-1">
                = Chi phí phát sinh (vận hành) + Lương + Thuế<br>
                = ${formatCurrency(totals.totalExpense)} + ${formatCurrency(totals.salaryExpense)} + ${formatCurrency(totals.tax)}<br>
                <span class="text-red-600">⚠️ Đây là tổng chi phí thực tế đã chi trả</span>
              </p>
            </div>

            <!-- LỢI NHUẬN -->
            <div class="bg-green-50 p-2 rounded">
              <p class="flex items-center justify-between">
                <span><strong>Lợi nhuận ròng:</strong> <span class="text-xs text-gray-500">(doanh thu - chi phí)</span></span>
                <span class="font-bold text-green-800 text-lg">${formatCurrency(totals.profit)}</span>
              </p>
              <p class="text-xs text-gray-600 ml-4 mt-1">
                = Doanh thu thực tế (đã thanh toán) - Tổng chi phí<br>
                = ${formatCurrency(totals.totalRevenueActual)} - ${formatCurrency(totals.totalExpenseWithTax)}<br>
                <span class="text-blue-600">💡 Giá trị âm = lỗ, giá trị dương = lãi</span>
              </p>
            </div>
          </div>
          <div class="mt-4 p-3 bg-indigo-50 border border-indigo-200 rounded-lg">
            <p class="text-sm text-indigo-900 font-semibold">Tỷ suất lợi nhuận (ROI): <span class="text-lg text-indigo-700">${totals.rate}</span></p>
            <p class="text-xs text-indigo-700 mt-1">
              = (Lợi nhuận ÷ Tổng chi phí) × 100%<br>
              = (${formatCurrency(totals.profit)} ÷ ${formatCurrency(totals.totalExpenseWithTax)}) × 100%<br>
              <span class="text-indigo-600">📊 Chỉ báo hiệu quả quản lý chi phí: càng cao càng tốt</span>
            </p>
          </div>`;
      }

      function updateStatCards() {
        const totals = computeTotals(state.datasets.primary);
        if (!totals) return;
        dom.statRevenue.innerText = formatCurrency(totals.totalRevenue);
        dom.statRevenueActual.innerText = formatCurrency(totals.totalRevenueActual);
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
            hint: 'Bao gồm chi phí phát sinh + lương + thuế'
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
          `Chi phí bình quân mỗi kỳ khoảng ${formatCurrency(avgExpense)}. Chi phí phát sinh hiện là ${formatCurrency(totals.incidentalExpense)}.`
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
      }

      function showMessage(message, color) {
        // Old function - no longer needed
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
        // FIXED: Doanh thu dự kiến = chờ thanh toán + đã thanh toán (từ revenue_predicted + revenue)
        const revenuePredictedSeries = safeSeries(dataset.revenue_predicted || []);
        const revenueActualSeries = safeSeries(dataset.revenue_actual || []);
        const expenseSeries = safeSeries(dataset.expense);
        const salaryExpenseSeries = safeSeries(dataset.salary_expense || []);
        
        // Tổng doanh thu hiển thị: tất cả doanh thu đã ghi nhận (đã thanh toán + chờ thanh toán)
        const revenueSeriesAll = revenuePredictedSeries.map((pred, idx) => 
          Number(pred || 0) + Number(revenueActualSeries[idx] || 0)
        );
        
        // QUAN TRỌNG: Khi filter='month', chỉ lấy **kỳ cuối cùng** trong mảng (tháng hiện tại)
        // Khi filter='year', lấy **tổng toàn bộ** mảng
        // Điều này được xác định bởi số lượng phần tử trong mảng
        const isMonthlyView = (expenseSeries.length < 12); // Nếu < 12 phần tử, chứng tỏ chỉ có dữ liệu từng tháng cụ thể
        
        let totalRevenue, totalRevenueActual, totalExpense, salaryExpense;
        
        if (isMonthlyView && expenseSeries.length > 0) {
          // Lấy phần tử cuối cùng (tháng hiện tại)
          const lastIdx = expenseSeries.length - 1;
          totalRevenue = revenueSeriesAll.reduce((sum, v) => sum + Number(v || 0), 0); // Tổng doanh thu năm
          totalRevenueActual = revenueActualSeries.reduce((sum, v) => sum + Number(v || 0), 0); // Tổng doanh thu năm
          totalExpense = Number(expenseSeries[lastIdx] || 0); // Chi phí của tháng hiện tại
          salaryExpense = Number(salaryExpenseSeries[lastIdx] || 0); // Lương của tháng hiện tại
        } else {
          // Lấy tổng toàn bộ (cho view năm/quý)
          totalRevenue = revenueSeriesAll.reduce((sum, value) => sum + Number(value || 0), 0);
          totalRevenueActual = revenueActualSeries.reduce((sum, value) => sum + Number(value || 0), 0);
          totalExpense = expenseSeries.reduce((sum, value) => sum + Number(value || 0), 0);
          salaryExpense = salaryExpenseSeries.reduce((sum, v) => sum + Number(v || 0), 0);
        }
        
        // Chi phí phát sinh tổng - KHÔNG dùng nữa (chỉ giữ cho backward compatibility nếu cần)
        // const incidentalExpense = Number(dataset.incidental_expense || totalExpense || 0);
        const tax = Number(dataset.tax || 0);
        const totalExpenseWithTax = totalExpense + salaryExpense + tax;
        // Lợi nhuận thực tế dựa trên doanh thu đã thanh toán
        const profit = totalRevenueActual - totalExpenseWithTax;
        const rate = totalExpenseWithTax === 0 ? 'N/A' : ((profit / totalExpenseWithTax) * 100).toFixed(2) + '%';
        return { totalRevenue, totalRevenueActual, totalExpense, salaryExpense, tax, totalExpenseWithTax, profit, rate };
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

      // Variables để lưu state khi sửa chi phí
      let editingExpenseName = null;

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

    // Expense Manager Panel Functions (Collapsible) - New System
    function toggleExpensePanel() {
      const panel = document.getElementById('expensePanel');
      
      if (panel.classList.contains('hidden')) {
        panel.classList.remove('hidden');
        initExpensePanel();
      } else {
        panel.classList.add('hidden');
      }
    }

    function initExpensePanel() {
      // Load danh sách loại chi phí cho tab Add
      loadExpenseTypes();
      // Load danh sách tháng cho tab Copy
      loadMonthsForCopy();
    }

    function switchExpensePanel(tab) {
      // Hide all tabs (3 main tabs only)
      document.getElementById('panel-content-view').style.display = 'none';
      document.getElementById('panel-content-manage').style.display = 'none';
      document.getElementById('panel-content-stats').style.display = 'none';
      // Hide old tabs
      document.getElementById('panel-content-history').style.display = 'none';
      document.getElementById('panel-content-summary').style.display = 'none';
      document.getElementById('panel-content-add').style.display = 'none';

      // Remove active state from all tabs (3 main tabs only)
      ['view', 'manage', 'stats'].forEach(t => {
        const btn = document.getElementById(`panel-tab-${t}`);
        if (btn) {
          btn.classList.remove('border-indigo-600', 'text-indigo-600');
          btn.classList.add('border-transparent', 'text-gray-600');
        }
      });

      // Show selected tab
      const tabContent = document.getElementById('panel-content-' + tab);
      if (tabContent) {
        tabContent.style.display = 'block';
      }

      // Set active state
      const activeBtn = document.getElementById('panel-tab-' + tab);
      if (activeBtn) {
        activeBtn.classList.remove('border-transparent', 'text-gray-600');
        activeBtn.classList.add('border-indigo-600', 'text-indigo-600');
      }

      // Load data for specific tabs
      if (tab === 'stats') {
        // Initialize stats tab with filters
        const now = new Date();
        const currentMonth = now.toISOString().slice(0, 7);
        document.getElementById('statsMonthSelect').value = currentMonth;
        loadStatsData();
        loadExpenseTypesForStats();
      } else if (tab === 'manage') {
        loadManageExpenseTypesList();
      }
    }

    function loadExpenseTypes() {
      fetch('components/api_chi_phi_loai.php?action=list')
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            const select = document.getElementById('panelBranchSelect');
            // Load danh sách loại chi phí vào select của View tab
            // (nếu cần trong tương lai)
          }
        })
        .catch(err => console.error('Error loading expense types:', err));
    }

    function confirmPanelAddNewExpenseType() {
      // Tạo loại chi phí mới (Admin function)
      const tenLoai = document.getElementById('panelAddExpenseName').value.trim();
      const motaLoai = document.getElementById('panelAddExpenseDesc').value.trim();

      if (!tenLoai) {
        alert('Vui lòng nhập tên loại chi phí');
        return;
      }

      const formData = new FormData();
      formData.append('ten_loai', tenLoai);
      formData.append('mota_loai', motaLoai);

      fetch('components/api_chi_phi_loai.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          alert('✅ Loại chi phí đã được tạo thành công!');
          document.getElementById('panelAddExpenseName').value = '';
          document.getElementById('panelAddExpenseDesc').value = '';
          // Reload loại chi phí ở View tab
          loadExpenseTypesForView();
        } else {
          alert('❌ Lỗi: ' + data.message);
        }
      })
      .catch(err => {
        console.error('Error:', err);
        alert('❌ Lỗi kết nối');
      });
    }

    function loadExpenseTypesForView() {
      // Load danh sách loại chi phí từ API
      fetch('components/api_chi_phi_loai.php?action=list')
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            // Cập nhật UI nếu cần (ví dụ: hiển thị danh sách loại chi phí)
            console.log('Loại chi phí đã được cập nhật:', data.data);
          }
        })
        .catch(err => console.error('Error:', err));
    }

    function loadPanelExpenses() {
      const branchSelect = document.getElementById('panelBranchSelect').value;
      const branchId = branchSelect ? branchSelect : '';
      const currentMonth = new Date().toISOString().slice(0, 7);

      if (!branchId) {
        document.getElementById('panelExpenseTableContainer').innerHTML = '<p class="text-gray-500 py-4 px-4 text-center text-sm">Vui lòng chọn chi nhánh</p>';
        return;
      }

      fetch(`components/api_chi_phi_gia_tri.php?action=list&id_cn=${branchId}&thang=${currentMonth}`)
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            renderExpenseTable(data.data, branchId);
          } else {
            document.getElementById('panelExpenseTableContainer').innerHTML = '<p class="text-red-500 py-4 px-4">Lỗi tải dữ liệu</p>';
          }
        })
        .catch(err => {
          console.error('Error:', err);
          document.getElementById('panelExpenseTableContainer').innerHTML = '<p class="text-red-500 py-4 px-4">Lỗi kết nối</p>';
        });
    }

    function renderExpenseTable(expenses, branchId) {
      if (expenses.length === 0) {
        document.getElementById('panelExpenseTableContainer').innerHTML = '<p class="text-gray-500 py-4 px-4 text-center text-sm">Chưa có chi phí trong tháng này</p>';
        return;
      }

      let html = '<div class="overflow-x-auto">';
      html += '<table class="w-full text-sm">';
      html += '<thead class="bg-gradient-to-r from-indigo-50 to-indigo-100 border-b-2 border-indigo-200">';
      html += '<tr>';
      html += '<th class="px-4 py-3 text-left font-semibold text-gray-700">Loại chi phí</th>';
      html += '<th class="px-4 py-3 text-right font-semibold text-gray-700">Giá trị (VND)</th>';
      html += '<th class="px-4 py-3 text-left font-semibold text-gray-700">Ghi chú</th>';
      html += '<th class="px-4 py-3 text-center font-semibold text-gray-700">Hành động</th>';
      html += '</tr>';
      html += '</thead>';
      html += '<tbody>';

      let totalAmount = 0;
      expenses.forEach((exp, idx) => {
        const amount = parseInt(exp.GIA_TRI) || 0;
        totalAmount += amount;
        const giaVND = amount.toLocaleString('vi-VN');
        // Alternating row colors
        const bgClass = idx % 2 === 0 ? 'bg-white' : 'bg-gray-50';
        
        html += `<tr class="${bgClass} border-t hover:bg-indigo-50 transition">
          <td class="px-4 py-3 font-medium text-gray-900">${exp.TEN_LOAI}</td>
          <td class="px-4 py-3 text-right font-semibold text-indigo-600">${giaVND}</td>
          <td class="px-4 py-3 text-sm text-gray-600">${exp.MOTA_CP ? '<span class="text-xs bg-gray-100 px-2 py-1 rounded">' + exp.MOTA_CP.substring(0, 30) + (exp.MOTA_CP.length > 30 ? '...' : '') + '</span>' : '<span class="text-gray-400">-</span>'}</td>
          <td class="px-4 py-3">
            <div class="flex gap-2 justify-center">
              <button onclick="editPanelExpenseNew(${exp.ID_CP}, ${exp.ID_LOAI}, ${exp.GIA_TRI}, '${(exp.MOTA_CP || '').replace(/'/g, "\\'")}' )" class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition font-medium">
                Sửa
              </button>
              <button onclick="deletePanelExpenseNew(${exp.ID_CP})" class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200 transition font-medium">
                Xóa
              </button>
            </div>
          </td>
        </tr>`;
      });

      html += '</tbody>';
      html += '</table>';
      html += '</div>';
      
      // Add total row below table
      html += `<div class="bg-gradient-to-r from-indigo-50 to-indigo-100 border-t-2 border-indigo-200 px-4 py-3 flex items-center justify-between">
        <span class="font-semibold text-gray-700">Tổng chi phí:</span>
        <span class="text-lg font-bold text-indigo-600">${totalAmount.toLocaleString('vi-VN')} VND</span>
      </div>`;

      document.getElementById('panelExpenseTableContainer').innerHTML = html;
    }

    function copyFromPreviousMonth() {
      const branchId = document.getElementById('panelBranchSelect').value;
      
      if (!branchId) {
        alert('Vui lòng chọn chi nhánh');
        return;
      }

      if (!confirm('Sao chép chi phí từ tháng trước? Những chi phí chưa có trong tháng này sẽ được thêm.')) {
        return;
      }

      const formData = new FormData();
      formData.append('id_cn', parseInt(branchId));
      formData.append('thang_dich', new Date().toISOString().slice(0, 7));

      fetch('components/controller_copy_previous_month.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          alert(`✅ Đã sao chép ${data.da_copy} chi phí từ tháng ${data.thang_nguon}`);
          refreshPrimaryDataset();
          loadPanelExpenses();
        } else if (data.status === 'info') {
          alert('ℹ️ ' + data.message);
        } else {
          alert('❌ Lỗi: ' + data.message);
        }
      })
      .catch(err => {
        console.error('Error:', err);
        alert('❌ Lỗi kết nối');
      });
    }

    function editPanelExpenseNew(idCp, idLoai, giaTriCu, ghiChu) {
      const newAmount = prompt('Số tiền mới:', giaTriCu);
      if (!newAmount || newAmount <= 0) {
        alert('Số tiền không hợp lệ');
        return;
      }

      const branchId = parseInt(document.getElementById('panelBranchSelect').value);
      const currentMonth = new Date().toISOString().slice(0, 7);

      const formData = new FormData();
      formData.append('id_loai', idLoai);
      formData.append('id_cn', branchId);
      formData.append('thang', currentMonth);
      formData.append('gia_tri', newAmount);
      formData.append('mota', ghiChu);

      fetch('components/api_chi_phi_gia_tri.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          alert('Chi phí đã được cập nhật');
          refreshPrimaryDataset();
          loadPanelExpenses();
        } else {
          alert('Lỗi: ' + data.message);
        }
      })
      .catch(err => {
        console.error('Error:', err);
        alert('Lỗi kết nối');
      });
    }

    function deletePanelExpenseNew(idCp) {
      if (!confirm('Bạn chắc chắn muốn xóa chi phí này?')) {
        return;
      }

      fetch(`components/api_chi_phi_gia_tri.php?id=${idCp}`, {
        method: 'DELETE'
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          alert('Chi phí đã được xóa');
          refreshPrimaryDataset();
          loadPanelExpenses();
        } else {
          alert('Lỗi: ' + data.message);
        }
      })
      .catch(err => {
        console.error('Error:', err);
        alert('Lỗi kết nối');
      });
    }

    function loadMonthsForCopy() {
      // Deprecated - using auto-copy instead
    }

    function loadPanelCopyExpensesNew() {
      // Deprecated - using auto-copy instead
    }

    function renderCopyTable() {
      // Deprecated - using auto-copy instead
    }

    function confirmCopyExpense() {
      // Deprecated - using auto-copy instead
    }

    // New function: Load and manage expense types list
    function loadManageExpenseTypesList() {
      fetch('components/api_chi_phi_loai.php?action=list&all=1')
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            renderManageExpenseTypesList(data.data);
          } else {
            document.getElementById('panelManageExpenseList').innerHTML = '<p class="text-red-500">Lỗi tải danh sách</p>';
          }
        })
        .catch(err => {
          console.error('Error:', err);
          document.getElementById('panelManageExpenseList').innerHTML = '<p class="text-red-500">Lỗi kết nối</p>';
        });
    }

    function renderManageExpenseTypesList(types) {
      if (types.length === 0) {
        document.getElementById('panelManageExpenseList').innerHTML = '<p class="text-gray-500 text-center py-4">Chưa có loại chi phí nào</p>';
        return;
      }

      let html = '<div class="grid gap-3">';
      types.forEach(type => {
        const isActive = type.TRANG_THAI === 'active';
        html += `
          <div class="flex items-start justify-between bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
            <div class="flex-1">
              <h4 class="font-semibold text-gray-900">${type.TEN_LOAI}</h4>
              <p class="text-sm text-gray-600 mt-1">${type.MOTA_LOAI || 'Không có mô tả'}</p>
              <p class="text-xs text-gray-500 mt-2">
                <span class="inline-block px-2 py-1 rounded ${isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                  ${isActive ? 'Kích hoạt' : 'Vô hiệu'}
                </span>
              </p>
            </div>
            <div class="flex gap-2 ml-4">
              <button onclick="editPanelExpenseType(${type.ID_LOAI}, '${type.TEN_LOAI.replace(/'/g, "\\'")}', '${(type.MOTA_LOAI || '').replace(/'/g, "\\'")}')" 
                      class="text-blue-600 hover:font-semibold text-sm">Sửa</button>
              <button onclick="deletePanelExpenseType(${type.ID_LOAI})" 
                      class="text-red-600 hover:font-semibold text-sm">Xóa</button>
            </div>
          </div>
        `;
      });
      html += '</div>';
      document.getElementById('panelManageExpenseList').innerHTML = html;
    }

    function editPanelExpenseType(idLoai, tenLoai, motaLoai) {
      // Tạo modal form để sửa
      const newName = prompt('Tên loại chi phí:', tenLoai);
      if (!newName || newName.trim() === '') {
        return;
      }

      const newDesc = prompt('Mô tả loại chi phí:', motaLoai);
      
      const formData = new FormData();
      formData.append('id_loai', idLoai);
      formData.append('ten_loai', newName.trim());
      formData.append('mota_loai', (newDesc || '').trim());

      fetch('components/api_chi_phi_loai.php', {
        method: 'PUT',
        body: new URLSearchParams({
          'id_loai': idLoai,
          'ten_loai': newName.trim(),
          'mota_loai': (newDesc || '').trim()
        })
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          alert('✅ Loại chi phí đã được cập nhật');
          loadManageExpenseTypesList();
        } else {
          alert('❌ Lỗi: ' + data.message);
        }
      })
      .catch(err => {
        console.error('Error:', err);
        alert('❌ Lỗi kết nối');
      });
    }

    function deletePanelExpenseType(idLoai) {
      if (!confirm('⚠️ CẢNH BÁO: Xóa loại chi phí này sẽ xóa VĨnh viễn loại chi phí và TẤT CẢ dữ liệu chi phí liên quan! Không thể khôi phục sau khi xóa. Bạn chắc chắn muốn tiếp tục?')) {
        return;
      }

      fetch(`components/api_chi_phi_loai.php?id=${idLoai}`, {
        method: 'DELETE'
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          alert('✅ ' + data.message);
          loadManageExpenseTypesList();
        } else {
          alert('❌ Lỗi: ' + data.message);
        }
      })
      .catch(err => {
        console.error('Error:', err);
        alert('❌ Lỗi kết nối');
      });
    }

    // ========== HISTORY TAB FUNCTIONS ==========
    function loadExpenseTypesForHistory() {
      fetch('components/api_chi_phi_loai.php?action=list')
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success' && data.data) {
            const select = document.getElementById('historyExpenseTypeSelect');
            select.innerHTML = '<option value="">-- Chọn loại --</option>';
            data.data.forEach(type => {
              const option = document.createElement('option');
              option.value = type.ID_LOAI;
              option.textContent = type.TEN_LOAI;
              select.appendChild(option);
            });
          }
        })
        .catch(err => console.error('Error loading expense types:', err));
    }

    function loadExpenseHistory() {
      const idLoai = document.getElementById('historyExpenseTypeSelect').value;
      const sortBy = document.getElementById('historySortSelect').value || 'thang_desc';

      if (!idLoai) {
        document.getElementById('panelHistoryTableContainer').innerHTML = '<p class="text-gray-500 py-4 px-4 text-center">Vui lòng chọn loại chi phí</p>';
        return;
      }

      fetch(`components/api_chi_phi_gia_tri.php?action=history&id_loai=${idLoai}`)
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            renderHistoryTable(data.data, data.ten_loai, sortBy);
          } else {
            document.getElementById('panelHistoryTableContainer').innerHTML = '<p class="text-red-500 py-4 px-4">Lỗi tải dữ liệu</p>';
          }
        })
        .catch(err => {
          console.error('Error:', err);
          document.getElementById('panelHistoryTableContainer').innerHTML = '<p class="text-red-500 py-4 px-4">Lỗi kết nối</p>';
        });
    }

    function renderHistoryTable(history, tenLoai, sortBy) {
      if (!history || history.length === 0) {
        document.getElementById('panelHistoryTableContainer').innerHTML = '<p class="text-gray-500 py-4 px-4 text-center">Chưa có dữ liệu lịch sử</p>';
        return;
      }

      // Sort data
      const sorted = [...history];
      if (sortBy === 'thang_asc') {
        sorted.sort((a, b) => a.THANG.localeCompare(b.THANG));
      } else if (sortBy === 'branch') {
        sorted.sort((a, b) => (a.TEN_CN || '').localeCompare(b.TEN_CN || ''));
      } else {
        sorted.sort((a, b) => b.THANG.localeCompare(a.THANG));
      }

      let html = `<div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-4 py-3 text-left font-semibold">Tháng</th>
              <th class="px-4 py-3 text-left font-semibold">Chi nhánh</th>
              <th class="px-4 py-3 text-right font-semibold">Giá trị (VND)</th>
              <th class="px-4 py-3 text-left font-semibold">Ngày ghi nhận</th>
            </tr>
          </thead>
          <tbody>`;

      let currentMonth = '';
      let monthTotal = 0;

      sorted.forEach((item, idx) => {
        if (item.THANG !== currentMonth && currentMonth !== '') {
          html += `<tr class="bg-indigo-50 border-t">
            <td colspan="2" class="px-4 py-3 font-semibold text-right">Tổng tháng ${currentMonth}:</td>
            <td class="px-4 py-3 text-right font-bold text-indigo-700">${monthTotal.toLocaleString('vi-VN')}</td>
            <td></td>
          </tr>`;
        }

        if (item.THANG !== currentMonth) {
          currentMonth = item.THANG;
          monthTotal = 0;
        }

        monthTotal += parseInt(item.GIA_TRI || 0);
        const giaVND = parseInt(item.GIA_TRI).toLocaleString('vi-VN');
        const ngayGhi = item.NGAY_GIO ? new Date(item.NGAY_GIO).toLocaleDateString('vi-VN') : '-';

        html += `<tr class="border-t hover:bg-gray-50">
          <td class="px-4 py-3">${item.THANG}</td>
          <td class="px-4 py-3">${item.TEN_CN || 'N/A'}</td>
          <td class="px-4 py-3 text-right">${giaVND}</td>
          <td class="px-4 py-3 text-sm text-gray-600">${ngayGhi}</td>
        </tr>`;
      });

      if (currentMonth) {
        html += `<tr class="bg-indigo-50 border-t">
          <td colspan="2" class="px-4 py-3 font-semibold text-right">Tổng tháng ${currentMonth}:</td>
          <td class="px-4 py-3 text-right font-bold text-indigo-700">${monthTotal.toLocaleString('vi-VN')}</td>
          <td></td>
        </tr>`;
      }

      // Grand total
      const grandTotal = sorted.reduce((sum, item) => sum + parseInt(item.GIA_TRI || 0), 0);
      html += `<tr class="bg-indigo-100 border-t font-bold">
        <td colspan="2" class="px-4 py-3 text-right">TỔNG CỘNG ${tenLoai}:</td>
        <td class="px-4 py-3 text-right text-indigo-900">${grandTotal.toLocaleString('vi-VN')}</td>
        <td></td>
      </tr>`;

      html += '</tbody></table></div>';
      document.getElementById('panelHistoryTableContainer').innerHTML = html;
    }

    // ========== SUMMARY TAB FUNCTIONS ==========
    function loadExpenseSummary() {
      const thang = document.getElementById('summarySummaryMonthSelect').value;

      if (!thang) {
        document.getElementById('panelSummaryContainer').innerHTML = '<p class="text-gray-500">Vui lòng chọn tháng</p>';
        return;
      }

      fetch(`components/api_chi_phi_gia_tri.php?action=summary&thang=${thang}`)
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            renderSummaryCards(data.data, data.tong_chung, thang);
          } else {
            document.getElementById('panelSummaryContainer').innerHTML = '<p class="text-red-500">Lỗi tải dữ liệu</p>';
          }
        })
        .catch(err => {
          console.error('Error:', err);
          document.getElementById('panelSummaryContainer').innerHTML = '<p class="text-red-500">Lỗi kết nối</p>';
        });
    }

    function renderSummaryCards(summary, tong_chung, thang) {
      if (!summary || summary.length === 0) {
        document.getElementById('panelSummaryContainer').innerHTML = '<p class="text-gray-500">Chưa có chi phí trong tháng này</p>';
        return;
      }

      let html = `<div class="grid grid-cols-1 md:grid-cols-2 gap-4">`;

      summary.forEach(item => {
        const giaVND = parseInt(item.TONG_GIA_TRI).toLocaleString('vi-VN');
        const phanTram = ((parseInt(item.TONG_GIA_TRI) / parseInt(tong_chung)) * 100).toFixed(1);

        html += `<div class="border border-indigo-200 rounded-lg p-4 bg-indigo-50 hover:shadow-md transition">
          <div class="flex justify-between items-start mb-2">
            <h3 class="font-semibold text-gray-900">${item.TEN_LOAI}</h3>
            <span class="bg-indigo-200 text-indigo-900 text-xs font-bold px-2 py-1 rounded">${phanTram}%</span>
          </div>
          <p class="text-2xl font-bold text-indigo-700 mb-1">${giaVND}</p>
          <p class="text-xs text-gray-600">Từ ${item.SO_CHI_NHANH} chi nhánh</p>
        </div>`;
      });

      html += '</div>';

      // Add grand total
      html += `<div class="mt-6 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-lg p-6 shadow-lg">
        <p class="text-sm font-medium opacity-90">Tổng chi phí tháng ${thang}</p>
        <p class="text-3xl font-bold">${parseInt(tong_chung).toLocaleString('vi-VN')} VND</p>
        <p class="text-sm opacity-90 mt-2">Tổng ${summary.length} loại chi phí</p>
      </div>`;

      document.getElementById('panelSummaryContainer').innerHTML = html;
    }

    // ===== New functions for unified Stats Tab (History + Summary combined) =====
    function loadExpenseTypesForStats() {
      fetch('components/api_chi_phi_loai.php?action=list&all=1')
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            const select = document.getElementById('statsExpenseTypeSelect');
            select.innerHTML = '<option value="">-- Tất cả loại --</option>';
            data.data.forEach(type => {
              const option = document.createElement('option');
              option.value = type.ID_LOAI;
              option.textContent = type.TEN_LOAI;
              select.appendChild(option);
            });
          }
        })
        .catch(err => console.error('Error:', err));
    }

    function loadStatsData() {
      const idLoai = document.getElementById('statsExpenseTypeSelect').value || '';
      const thang = document.getElementById('statsMonthSelect').value || new Date().toISOString().slice(0, 7);
      
      if (!thang) {
        document.getElementById('statsContentArea').innerHTML = '<p class="text-gray-500 py-4 px-4">Vui lòng chọn tháng</p>';
        return;
      }

      // Default to history view
      showStatsHistoryView();
    }

    function showStatsHistoryView() {
      const idLoai = document.getElementById('statsExpenseTypeSelect').value;
      const thang = document.getElementById('statsMonthSelect').value;
      
      let url = `components/api_chi_phi_gia_tri.php?action=details&thang=${thang}`;
      if (idLoai) {
        url += `&id_loai=${idLoai}`;
      }

      fetch(url)
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            renderStatsHistoryTable(data.data, thang);
          } else {
            document.getElementById('statsContentArea').innerHTML = '<p class="text-red-500 py-4 px-4">Lỗi tải dữ liệu</p>';
          }
        })
        .catch(err => {
          console.error('Error:', err);
          document.getElementById('statsContentArea').innerHTML = '<p class="text-red-500 py-4 px-4">Lỗi kết nối</p>';
        });
    }

    function showStatsSummaryView() {
      const thang = document.getElementById('statsMonthSelect').value;
      
      fetch(`components/api_chi_phi_gia_tri.php?action=summary&thang=${thang}`)
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            renderStatsSummaryCards(data.data, thang);
          } else {
            document.getElementById('statsContentArea').innerHTML = '<p class="text-red-500 py-4 px-4">Lỗi tải dữ liệu</p>';
          }
        })
        .catch(err => {
          console.error('Error:', err);
          document.getElementById('statsContentArea').innerHTML = '<p class="text-red-500 py-4 px-4">Lỗi kết nối</p>';
        });
    }

    function renderStatsHistoryTable(expenses, thang) {
      if (!expenses || expenses.length === 0) {
        document.getElementById('statsContentArea').innerHTML = '<p class="text-gray-500 py-4 px-4 text-center">Chưa có dữ liệu tháng này</p>';
        return;
      }

      let html = '<div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-100"><tr>';
      html += '<th class="px-3 py-2 text-left font-semibold">Chi nhánh</th>';
      html += '<th class="px-3 py-2 text-left font-semibold">Loại chi phí</th>';
      html += '<th class="px-3 py-2 text-right font-semibold">Giá trị</th>';
      html += '</tr></thead><tbody>';

      let totalAmount = 0;
      expenses.forEach((exp, idx) => {
        const amount = parseInt(exp.GIA_TRI) || 0;
        totalAmount += amount;
        const bgClass = idx % 2 === 0 ? 'bg-white' : 'bg-gray-50';
        html += `<tr class="${bgClass} border-t hover:bg-indigo-50 transition">
          <td class="px-3 py-2 text-gray-700">${exp.TEN_CN || 'N/A'}</td>
          <td class="px-3 py-2 text-gray-700">${exp.TEN_LOAI || 'N/A'}</td>
          <td class="px-3 py-2 text-right font-medium text-gray-900">${amount.toLocaleString('vi-VN')}</td>
        </tr>`;
      });

      html += '</tbody></table></div>';
      html += `<div class="bg-indigo-50 px-3 py-3 text-sm font-semibold text-gray-900 border-t">
        Tổng: <span class="text-indigo-600 text-lg">${totalAmount.toLocaleString('vi-VN')} VND</span>
      </div>`;

      document.getElementById('statsContentArea').innerHTML = html;
    }

    function renderStatsSummaryCards(summary, thang) {
      if (!summary || summary.length === 0) {
        document.getElementById('statsContentArea').innerHTML = '<p class="text-gray-500 py-4 px-4">Chưa có dữ liệu</p>';
        return;
      }

      let html = '<div class="space-y-3">';
      let tong_chung = 0;

      summary.forEach(item => {
        const amount = parseInt(item.TONG_GIA_TRI) || 0;
        const soCN = parseInt(item.SO_CHI_NHANH) || 0;
        tong_chung += amount;
        
        html += `<div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
          <div class="flex items-center justify-between">
            <div>
              <p class="font-semibold text-gray-900">${item.TEN_LOAI || 'N/A'}</p>
              <p class="text-xs text-gray-500">${soCN} chi nhánh</p>
            </div>
            <p class="text-xl font-bold text-indigo-600">${amount.toLocaleString('vi-VN')} VND</p>
          </div>
        </div>`;
      });

      html += '</div>';
      html += `<div class="mt-6 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-lg p-6 shadow-lg">
        <p class="text-sm font-medium opacity-90">Tổng chi phí tháng ${thang}</p>
        <p class="text-3xl font-bold">${parseInt(tong_chung).toLocaleString('vi-VN')} VND</p>
      </div>`;

      document.getElementById('statsContentArea').innerHTML = html;
    }

    // ===== Modal Dialog Functions for Add/Edit Expense Type =====
    function showAddExpenseTypeModal() {
      document.getElementById('modalExpenseTypeName').value = '';
      document.getElementById('modalExpenseTypeDesc').value = '';
      document.getElementById('addExpenseTypeModal').style.display = 'flex';
      document.getElementById('modalExpenseTypeName').focus();
    }

    function closeAddExpenseTypeModal() {
      document.getElementById('addExpenseTypeModal').style.display = 'none';
    }

    function saveExpenseTypeFromModal() {
      const tenLoai = document.getElementById('modalExpenseTypeName').value.trim();
      const motaLoai = document.getElementById('modalExpenseTypeDesc').value.trim();

      if (!tenLoai) {
        alert('❌ Vui lòng nhập tên loại chi phí');
        document.getElementById('modalExpenseTypeName').focus();
        return;
      }

      const formData = new FormData();
      formData.append('ten_loai', tenLoai);
      formData.append('mota_loai', motaLoai);

      fetch('components/api_chi_phi_loai.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          alert('✅ ' + data.message);
          closeAddExpenseTypeModal();
          loadManageExpenseTypesList();
        } else {
          alert('❌ Lỗi: ' + data.message);
        }
      })
      .catch(err => {
        console.error('Error:', err);
        alert('❌ Lỗi kết nối');
      });
    }

    // Close modal when clicking outside
    document.addEventListener('DOMContentLoaded', () => {
      const modal = document.getElementById('addExpenseTypeModal');
      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          closeAddExpenseTypeModal();
        }
      });
    });
  </script>
</body>

</html>