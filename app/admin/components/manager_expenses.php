<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

include '../../database/config.php';
require_once __DIR__ . '/../../helpers/assets.php';

$idTk = $_SESSION['ID_TK'] ?? null;

if (!$idTk) {
  die("<p class='text-red-600'>Lỗi: Bạn cần đăng nhập để theo dõi chi phí.</p>");
}

$branchStmt = $conn->prepare("SELECT nv.ID_CN, cn.TEN_CN FROM nhan_vien nv JOIN chi_nhanh cn ON cn.ID_CN = nv.ID_CN WHERE nv.ID_TK = ?");
$branchStmt->bind_param('i', $idTk);
$branchStmt->execute();
$branchData = $branchStmt->get_result()->fetch_assoc();

if (!$branchData) {
  die("<p class='text-red-600'>Lỗi: Không xác định được chi nhánh của nhân viên!</p>");
}

$idCn = (int)$branchData['ID_CN'];
$branchName = $branchData['TEN_CN'] ?? 'Chi nhánh';

$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
  $selectedMonth = date('Y-m');
}
$selectedMonthObj = DateTime::createFromFormat('Y-m', $selectedMonth);
$selectedMonthLabel = $selectedMonthObj ? $selectedMonthObj->format('m/Y') : date('m/Y');

$baseCategories = [
  'rent' => [
    'label' => 'Chi phí mặt bằng',
    'placeholder' => 'Ví dụ: 25.000.000',
    'helper' => 'Tiền thuê, phí dịch vụ tòa nhà.',
    'aliases' => ['Chi phí mặt bằng', 'Chi phí thuê mặt bằng'],
  ],
  'utilities' => [
    'label' => 'Chi phí điện nước',
    'placeholder' => 'Ví dụ: 6.500.000',
    'helper' => 'Điện, nước, internet, vệ sinh.',
    'aliases' => ['Chi phí điện nước', 'Chi phí tiện ích'],
  ],
  'marketing' => [
    'label' => 'Chi phí marketing',
    'placeholder' => 'Ví dụ: 4.000.000',
    'helper' => 'Quảng cáo online/offline.',
    'aliases' => ['Chi phí marketing', 'Chi phí quảng cáo'],
  ],
  'maintenance' => [
    'label' => 'Chi phí bảo trì thiết bị',
    'placeholder' => 'Ví dụ: 2.000.000',
    'helper' => 'Sửa chữa, bảo dưỡng trang thiết bị.',
    'aliases' => ['Chi phí bảo trì thiết bị', 'Chi phí bảo trì'],
  ],
  'personnel' => [
    'label' => 'Chi phí nhân sự hỗ trợ',
    'placeholder' => 'Ví dụ: 8.000.000',
    'helper' => 'CTV, bảo vệ, hỗ trợ sự kiện.',
    'aliases' => ['Chi phí nhân sự hỗ trợ', 'Chi phí nhân sự'],
  ],
  'supplies' => [
    'label' => 'Chi phí vật tư - văn phòng phẩm',
    'placeholder' => 'Ví dụ: 1.500.000',
    'helper' => 'Vật tư tiêu hao, văn phòng phẩm.',
    'aliases' => ['Chi phí vật tư - văn phòng phẩm', 'Chi phí vật tư'],
  ],
];

$prefilledExpenses = [];
foreach ($baseCategories as $key => $definition) {
  $prefilledExpenses[$key] = ['amount' => 0, 'note' => ''];
}

$expenseStmt = $conn->prepare("SELECT TEN_CP, MOTA_CP, GIA_TRI FROM chi_phi_phat_sinh WHERE ID_CN = ? AND DATE_FORMAT(NGAY_GIO, '%Y-%m') = ? ORDER BY TEN_CP");
$expenseStmt->bind_param('is', $idCn, $selectedMonth);
$expenseStmt->execute();
$expenseResult = $expenseStmt->get_result();

$additionalExpenses = [];
$totalExpenses = 0;
$taxExpense = 0;
$taxNote = '';

while ($expenseRow = $expenseResult->fetch_assoc()) {
  $name = $expenseRow['TEN_CP'];
  $amount = (float)$expenseRow['GIA_TRI'];
  $note = $expenseRow['MOTA_CP'] ?? '';
  $totalExpenses += $amount;

  if (stripos($name, 'thuế') !== false) {
    $taxExpense += $amount;
    $taxNote = $note;
    continue;
  }

  $matched = false;
  foreach ($baseCategories as $key => $definition) {
    $aliases = $definition['aliases'];
    if (in_array($name, $aliases, true) || strcasecmp($name, $definition['label']) === 0) {
      $prefilledExpenses[$key]['amount'] += $amount;
      if ($note) {
        $prefilledExpenses[$key]['note'] = $note;
      }
      $matched = true;
      break;
    }
  }

  if (!$matched) {
    $additionalExpenses[] = [
      'name' => $name,
      'amount' => $amount,
      'note' => $note,
    ];
  }
}

$operatingExpenses = max(0, $totalExpenses - $taxExpense);

$financeStmt = $conn->prepare("SELECT 
    SUM(CASE WHEN LOAI_GIAO_DICH = 'doanh thu' THEN SO_TIEN ELSE 0 END) AS total_revenue,
    SUM(CASE WHEN LOAI_GIAO_DICH = 'chi phí' THEN SO_TIEN ELSE 0 END) AS total_cost
  FROM tai_chinh
  WHERE ID_CN = ? AND DATE_FORMAT(NGAY_GIAO_DICH, '%Y-%m') = ?");
$financeStmt->bind_param('is', $idCn, $selectedMonth);
$financeStmt->execute();
$financeData = $financeStmt->get_result()->fetch_assoc() ?? ['total_revenue' => 0, 'total_cost' => 0];

$revenue = (float)$financeData['total_revenue'];
$autoTax = round($revenue * 0.1);
$netProfit = $revenue - $totalExpenses;
$taxValue = $taxExpense > 0 ? $taxExpense : $autoTax;
$taxMode = ($taxExpense > 0 && abs($taxExpense - $autoTax) > 1000) ? 'manual' : 'auto';

$historyStmt = $conn->prepare("SELECT DATE_FORMAT(NGAY_GIO, '%Y-%m') AS month_key, SUM(GIA_TRI) AS total FROM chi_phi_phat_sinh WHERE ID_CN = ? GROUP BY month_key ORDER BY month_key DESC LIMIT 6");
$historyStmt->bind_param('i', $idCn);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();
$historyRows = [];
while ($historyRow = $historyResult->fetch_assoc()) {
  $historyRows[] = $historyRow;
}

function formatCurrency($value)
{
  return number_format((float)$value, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Quản lý chi phí</title>
  <?= sb_tailwind_link_tag(); ?>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen py-10 px-4">
  <div class="max-w-6xl mx-auto bg-white p-8 rounded-2xl shadow-xl fade-in space-y-8">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <p class="text-sm uppercase tracking-wide text-gray-500">Quản lý chi phí</p>
        <h1 class="text-3xl font-extrabold text-indigo-700">📋 <?= htmlspecialchars($branchName) ?></h1>
        <p class="text-sm text-gray-500">Theo dõi chi phí phát sinh theo từng tháng và cập nhật nhanh chóng.</p>
      </div>
      <form method="GET" class="flex items-center gap-3">
        <label class="text-sm font-semibold text-gray-600" for="month">Chọn tháng</label>
        <input type="month" id="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>" class="rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring focus:ring-indigo-200" onchange="this.form.submit()">
      </form>
    </div>

    <?php if (isset($_GET['success'])): ?>
      <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3">✅ Lưu chi phí tháng <?= htmlspecialchars($selectedMonthLabel) ?> thành công.</div>
    <?php elseif (isset($_GET['error'])): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3">⚠️ Không thể lưu chi phí. Vui lòng thử lại.</div>
    <?php endif; ?>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-xl border border-indigo-100 bg-indigo-50/70 p-4">
        <p class="text-xs uppercase text-indigo-500">Doanh thu tháng <?= htmlspecialchars($selectedMonthLabel) ?></p>
        <p class="text-2xl font-bold text-indigo-700"><?= formatCurrency($revenue) ?> đ</p>
      </div>
      <div class="rounded-xl border border-rose-100 bg-rose-50/70 p-4">
        <p class="text-xs uppercase text-rose-500">Chi phí vận hành</p>
        <p class="text-2xl font-bold text-rose-600"><?= formatCurrency($operatingExpenses) ?> đ</p>
      </div>
      <div class="rounded-xl border border-amber-100 bg-amber-50/70 p-4">
        <p class="text-xs uppercase text-amber-500">Thuế dự kiến (10%)</p>
        <p class="text-2xl font-bold text-amber-600"><?= formatCurrency($autoTax) ?> đ</p>
      </div>
      <div class="rounded-xl border border-emerald-100 bg-emerald-50/70 p-4">
        <p class="text-xs uppercase text-emerald-500">Lợi nhuận tạm tính</p>
        <p class="text-2xl font-bold text-emerald-600"><?= formatCurrency($netProfit) ?> đ</p>
      </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-3">
      <form action="./components/save_expenses.php" method="POST" class="lg:col-span-2 space-y-6" id="expenseForm">
        <input type="hidden" name="branch" value="cn<?= $idCn ?>">
        <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
        <input type="hidden" name="redirect_to" value="../manager_dashboard.php?page=expenses">

        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6 space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm uppercase tracking-wide text-gray-500">Danh mục chi phí cố định</p>
              <h2 class="text-xl font-semibold text-gray-800">Nhập số liệu tháng <?= htmlspecialchars($selectedMonthLabel) ?></h2>
            </div>
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-gray-500 shadow-inner">Tự động lưu</span>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <?php foreach ($baseCategories as $key => $definition): ?>
              <?php $prefill = $prefilledExpenses[$key]; ?>
              <div class="rounded-2xl border border-white bg-white/80 p-4 shadow-sm">
                <div class="flex items-center justify-between text-sm text-gray-500">
                  <span><?= htmlspecialchars($definition['label']) ?></span>
                  <span class="font-semibold text-indigo-600" data-prefill>
                    <?= $prefill['amount'] > 0 ? formatCurrency($prefill['amount']) . ' đ' : '---' ?>
                  </span>
                </div>
                <input type="hidden" name="expense_name[]" value="<?= htmlspecialchars($definition['label']) ?>">
                <label class="mt-3 block text-xs font-semibold text-gray-500">Số tiền (VND)</label>
                <input type="number" name="expense_amount[]" min="0" step="1000" value="<?= $prefill['amount'] > 0 ? htmlspecialchars((string)$prefill['amount']) : '' ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200" placeholder="<?= htmlspecialchars($definition['placeholder']) ?>" data-expense-input>
                <label class="mt-3 block text-xs font-semibold text-gray-500">Ghi chú (tuỳ chọn)</label>
                <input type="text" name="expense_note[]" value="<?= htmlspecialchars($prefill['note']) ?>" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-xs" placeholder="<?= htmlspecialchars($definition['helper']) ?>">
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="rounded-2xl border border-dashed border-indigo-200 bg-white p-6" id="customExpenseSection">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm uppercase tracking-wide text-gray-500">Chi phí linh hoạt</p>
              <h2 class="text-xl font-semibold text-gray-800">Thêm, xoá các khoản mục riêng</h2>
            </div>
            <button type="button" id="addCustomExpense" class="rounded-full border border-indigo-200 px-4 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50">+ Thêm mục</button>
          </div>

          <div class="mt-4 space-y-4" id="dynamicExpenses">
            <?php if (empty($additionalExpenses)): ?>
              <p class="text-sm text-gray-500">Chưa có chi phí linh hoạt trong tháng này.</p>
            <?php else: ?>
              <?php foreach ($additionalExpenses as $extra): ?>
                <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-4 space-y-3" data-expense-row>
                  <div class="flex items-center gap-3">
                    <input type="text" name="expense_name[]" value="<?= htmlspecialchars($extra['name']) ?>" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium" required>
                    <button type="button" class="text-xs text-red-500" data-remove-expense>x</button>
                  </div>
                  <div class="grid gap-3 md:grid-cols-2">
                    <div>
                      <label class="text-xs font-semibold text-gray-500">Số tiền (VND)</label>
                      <input type="number" name="expense_amount[]" min="0" step="1000" value="<?= htmlspecialchars((string)$extra['amount']) ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" data-expense-input required>
                    </div>
                    <div>
                      <label class="text-xs font-semibold text-gray-500">Ghi chú</label>
                      <input type="text" name="expense_note[]" value="<?= htmlspecialchars($extra['note']) ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-6 space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-amber-700">Thuế VAT 10%</h2>
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-amber-600">Bắt buộc</span>
          </div>
          <div class="flex flex-wrap gap-4 text-sm text-amber-700">
            <label class="inline-flex items-center gap-2">
              <input type="radio" name="tax_mode" value="auto" <?= $taxMode === 'auto' ? 'checked' : '' ?>>
              Tự động theo doanh thu (<?= formatCurrency($autoTax) ?> đ)
            </label>
            <label class="inline-flex items-center gap-2">
              <input type="radio" name="tax_mode" value="manual" <?= $taxMode === 'manual' ? 'checked' : '' ?>>
              Nhập tay
            </label>
          </div>
          <input type="number" name="tax_value" min="0" step="1000" value="<?= htmlspecialchars((string)$taxValue) ?>" class="w-full rounded-lg border border-amber-300 px-4 py-2 text-lg font-semibold text-amber-700" data-tax-input data-auto-value="<?= htmlspecialchars((string)$autoTax) ?>">
          <?php if ($taxNote): ?>
            <p class="text-xs text-amber-600">Ghi chú: <?= htmlspecialchars($taxNote) ?></p>
          <?php endif; ?>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 space-y-4">
          <label class="block text-sm font-semibold text-gray-600" for="submission_note">Ghi chú chung (không bắt buộc)</label>
          <textarea id="submission_note" name="submission_note" rows="3" class="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm" placeholder="Thêm ghi chú cho đợt cập nhật này (phục vụ kiểm tra sau này)"></textarea>
          <div class="flex items-center justify-between rounded-xl bg-gray-50 p-4">
            <div>
              <p class="text-sm text-gray-500">Tổng dự kiến (bao gồm thuế)</p>
              <p class="text-2xl font-bold text-gray-900" data-total-preview><?= formatCurrency($totalExpenses) ?> đ</p>
            </div>
            <button type="submit" class="rounded-xl bg-indigo-600 px-6 py-3 text-lg font-semibold text-white shadow hover:bg-indigo-700">💾 Lưu chi phí</button>
          </div>
        </div>
      </form>

      <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Chi phí chi tiết tháng <?= htmlspecialchars($selectedMonthLabel) ?></h3>
          <?php if ($expenseResult->num_rows === 0): ?>
            <p class="text-sm text-gray-500">Chưa có chi phí phát sinh trong tháng này.</p>
          <?php else: ?>
            <div class="max-h-80 overflow-y-auto">
              <table class="w-full text-sm">
                <thead class="bg-white sticky top-0">
                  <tr class="text-left text-gray-500">
                    <th class="px-3 py-2">Tên chi phí</th>
                    <th class="px-3 py-2 text-right">Giá trị</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <?php $expenseStmt->execute(); $expenseResult = $expenseStmt->get_result(); ?>
                  <?php while ($row = $expenseResult->fetch_assoc()): ?>
                    <tr class="hover:bg-white">
                      <td class="px-3 py-2">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($row['TEN_CP']) ?></p>
                        <?php if (!empty($row['MOTA_CP'])): ?>
                          <p class="text-xs text-gray-500"><?= htmlspecialchars($row['MOTA_CP']) ?></p>
                        <?php endif; ?>
                      </td>
                      <td class="px-3 py-2 text-right font-semibold text-indigo-700"><?= formatCurrency($row['GIA_TRI']) ?> đ</td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
            <div class="mt-4 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-gray-700">
              Tổng cộng: <?= formatCurrency($totalExpenses) ?> đ (bao gồm thuế <?= formatCurrency($taxExpense) ?> đ)
            </div>
          <?php endif; ?>
        </div>

        <div class="rounded-2xl border border-indigo-100 bg-indigo-50/70 p-6">
          <h3 class="text-lg font-semibold text-indigo-800 mb-4">Diễn biến chi phí 6 tháng gần nhất</h3>
          <?php if (empty($historyRows)): ?>
            <p class="text-sm text-indigo-600">Chưa có số liệu lịch sử.</p>
          <?php else: ?>
            <ul class="space-y-3 text-sm text-indigo-700">
              <?php foreach ($historyRows as $history): ?>
                <?php $historyLabel = DateTime::createFromFormat('Y-m', $history['month_key']); ?>
                <li class="flex items-center justify-between rounded-xl bg-white/80 px-4 py-2">
                  <span><?= $historyLabel ? $historyLabel->format('m/Y') : $history['month_key'] ?></span>
                  <span class="font-semibold"><?= formatCurrency($history['total']) ?> đ</span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const dynamicContainer = document.querySelector('#dynamicExpenses');
      const addButton = document.querySelector('#addCustomExpense');
      const totalPreview = document.querySelector('[data-total-preview]');
      const taxInput = document.querySelector('[data-tax-input]');
      const taxRadios = document.querySelectorAll('input[name="tax_mode"]');

      const currencyFormatter = new Intl.NumberFormat('vi-VN');

      const updatePreview = () => {
        let total = 0;
        document.querySelectorAll('[data-expense-input]').forEach((input) => {
          const value = parseFloat(input.value);
          if (!isNaN(value)) {
            total += value;
          }
        });
        const taxValue = parseFloat(taxInput.value);
        if (!isNaN(taxValue)) {
          total += taxValue;
        }
        totalPreview.textContent = `${currencyFormatter.format(total)} đ`;
      };

      const attachInputListener = (input) => {
        input.addEventListener('input', updatePreview);
      };

      document.querySelectorAll('[data-expense-input]').forEach(attachInputListener);
      taxInput.addEventListener('input', updatePreview);

      const toggleTaxMode = () => {
        const mode = document.querySelector('input[name="tax_mode"]:checked').value;
        const autoValue = Number(taxInput.dataset.autoValue || 0);
        if (mode === 'auto') {
          taxInput.value = autoValue;
          taxInput.readOnly = true;
          taxInput.classList.add('bg-amber-100');
        } else {
          taxInput.readOnly = false;
          taxInput.classList.remove('bg-amber-100');
        }
        updatePreview();
      };

      taxRadios.forEach((radio) => radio.addEventListener('change', toggleTaxMode));
      toggleTaxMode();

      addButton.addEventListener('click', () => {
        dynamicContainer.querySelector('p')?.remove();
        const wrapper = document.createElement('div');
        wrapper.className = 'rounded-xl border border-gray-200 bg-gray-50/60 p-4 space-y-3';
        wrapper.setAttribute('data-expense-row', '');
        wrapper.innerHTML = `
          <div class="flex items-center gap-3">
            <input type="text" name="expense_name[]" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium" placeholder="Tên chi phí" required>
            <button type="button" class="text-xs text-red-500" data-remove-expense>x</button>
          </div>
          <div class="grid gap-3 md:grid-cols-2">
            <div>
              <label class="text-xs font-semibold text-gray-500">Số tiền (VND)</label>
              <input type="number" name="expense_amount[]" min="0" step="1000" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" data-expense-input required>
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-500">Ghi chú</label>
              <input type="text" name="expense_note[]" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Ví dụ: workshop, cải tạo" />
            </div>
          </div>
        `;
        dynamicContainer.appendChild(wrapper);
        attachInputListener(wrapper.querySelector('[data-expense-input]'));
        updatePreview();
      });

      dynamicContainer.addEventListener('click', (event) => {
        const target = event.target.closest('[data-remove-expense]');
        if (target) {
          target.closest('[data-expense-row]').remove();
          if (dynamicContainer.children.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'text-sm text-gray-500';
            empty.textContent = 'Chưa có chi phí linh hoạt trong tháng này.';
            dynamicContainer.appendChild(empty);
          }
          updatePreview();
        }
      });

      updatePreview();
    });
  </script>
</body>
</html>