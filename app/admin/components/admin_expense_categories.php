<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../helpers/assets.php';

// Ensure DB connection for branch lookup when this component is embedded elsewhere
if (!isset($conn)) {
  require_once __DIR__ . '/../../database/config.php';
}

$branches = [];
if (isset($conn)) {
  $branchResult = $conn->query("SELECT ID_CN, TEN_CN FROM chi_nhanh ORDER BY TEN_CN");
  if ($branchResult) {
    while ($row = $branchResult->fetch_assoc()) {
      $branches[] = $row;
    }
  }
}

$currentMonth = date('Y-m');
$currentMonthLabel = date('m/Y');
?>
<div class="space-y-6">
  <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <div>
      <p class="text-sm uppercase tracking-wide text-slate-500 font-semibold">Cấu hình hệ thống</p>
      <h1 class="text-3xl font-extrabold text-slate-900">Quản lý loại chi phí phát sinh</h1>
    </div>
    <div class="flex gap-2">
      <button type="button" onclick="openExpenseTypeModal()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold shadow hover:bg-indigo-700 transition">
        <span class="text-lg">＋</span>
        <span>Thêm loại chi phí</span>
      </button>
      <button type="button" onclick="loadExpenseTypes()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 font-semibold hover:bg-slate-50 transition">
        <i class="fas fa-sync-alt"></i>
        <span>Tải lại</span>
      </button>
    </div>
  </div>

  <div class="grid gap-4 md:grid-cols-3">
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Tổng loại chi phí</p>
      <p id="statTotalTypes" class="text-3xl font-bold text-slate-900 mt-1">0</p>
    </div>
    <div class="rounded-xl border border-green-100 bg-green-50 p-4 shadow-sm">
      <p class="text-xs uppercase tracking-wide text-green-600 font-semibold">Đang kích hoạt</p>
      <p id="statActiveTypes" class="text-3xl font-bold text-green-700 mt-1">0</p>
    </div>
    <div class="rounded-xl border border-amber-100 bg-amber-50 p-4 shadow-sm">
      <p class="text-xs uppercase tracking-wide text-amber-600 font-semibold">Đang tạm ngưng</p>
      <p id="statInactiveTypes" class="text-3xl font-bold text-amber-700 mt-1">0</p>
    </div>
  </div>

  <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between px-6 py-4 bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
      <div>
        <h2 class="text-lg font-bold text-slate-900">Danh sách loại chi phí</h2>
        <p class="text-sm text-slate-600">Các loại chi phí active sẽ xuất hiện trong màn nhập của chi nhánh.</p>
      </div>
      <div class="flex items-center gap-2">
        <label class="text-sm text-slate-600" for="expenseTypeSearch">Tìm kiếm</label>
        <input id="expenseTypeSearch" type="text" placeholder="Nhập tên..." class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition" oninput="filterExpenseTypes()" />
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-6 py-3 text-left font-semibold text-slate-700">Tên loại</th>
            <th class="px-6 py-3 text-left font-semibold text-slate-700">Mô tả</th>
            <th class="px-6 py-3 text-center font-semibold text-slate-700">Trạng thái</th>
            <th class="px-6 py-3 text-center font-semibold text-slate-700">Hành động</th>
          </tr>
        </thead>
        <tbody id="expenseTypeTbody" class="divide-y divide-slate-100"></tbody>
      </table>
    </div>
  </div>

  <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">
    <div class="px-6 py-4 border-b border-slate-200 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div>
        <h3 class="text-lg font-bold text-slate-900">Tổng hợp chi phí theo tháng</h3>
        <p class="text-sm text-slate-600">Lọc nhanh tổng chi phí từng loại theo tháng và chi nhánh.</p>
      </div>
      <div class="flex items-center gap-3 flex-wrap">
        <input type="month" id="summaryMonth" value="<?= htmlspecialchars($currentMonth) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition" />
        <select id="summaryBranch" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition min-w-[200px]">
          <option value="">Tất cả chi nhánh</option>
          <?php foreach ($branches as $branch): ?>
            <option value="<?= (int)$branch['ID_CN'] ?>"><?= htmlspecialchars($branch['TEN_CN'] ?? '') ?></option>
          <?php endforeach; ?>
        </select>
        <button type="button" onclick="loadExpenseSummary()" class="px-4 py-2 rounded-lg bg-slate-800 text-white font-semibold hover:bg-slate-900 transition">Xem</button>
      </div>
    </div>
    <div id="expenseSummaryContainer" class="p-6 space-y-3">
      <p class="text-sm text-slate-500">Tổng hợp tháng <?= htmlspecialchars($currentMonthLabel) ?> sẽ hiển thị tại đây.</p>
    </div>
  </div>
</div>

<!-- Modal: Create / Edit Expense Type -->
<div id="expenseTypeModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6" role="dialog" aria-modal="true">
    <div class="flex items-start justify-between mb-4">
      <div>
        <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold" id="expenseTypeModalSubtitle">Tạo mới</p>
        <h2 class="text-2xl font-bold text-slate-900" id="expenseTypeModalTitle">Loại chi phí</h2>
      </div>
      <button type="button" onclick="closeExpenseTypeModal()" class="text-slate-500 hover:text-slate-800">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <form id="expenseTypeForm" class="space-y-4">
      <input type="hidden" id="expenseTypeId" />
      <div>
        <label class="block text-sm font-semibold text-slate-700 mb-2" for="expenseTypeName">Tên loại *</label>
        <input id="expenseTypeName" type="text" required maxlength="255" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition" placeholder="Ví dụ: Chi phí mặt bằng" />
      </div>
      <div>
        <label class="block text-sm font-semibold text-slate-700 mb-2" for="expenseTypeDesc">Mô tả</label>
        <textarea id="expenseTypeDesc" rows="3" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition" placeholder="Ghi chú cho loại chi phí này..."></textarea>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closeExpenseTypeModal()" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">Hủy</button>
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition">Lưu</button>
      </div>
    </form>
  </div>
</div>

<script>
  const apiExpenseType = 'components/api_chi_phi_loai.php';
  const apiExpenseValue = 'components/api_chi_phi_gia_tri.php';
  let expenseTypes = [];

  document.addEventListener('DOMContentLoaded', () => {
    // Nạp danh sách loại chi phí và tổng hợp mặc định khi trang load
    loadExpenseTypes();
    loadExpenseSummary();

    document.getElementById('expenseTypeForm').addEventListener('submit', submitExpenseTypeForm);
    document.getElementById('expenseTypeModal').addEventListener('click', (e) => {
      if (e.target.id === 'expenseTypeModal') {
        closeExpenseTypeModal();
      }
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeExpenseTypeModal();
      }
    });
  });

  function escapeHtml(str = '') {
    // Thoát HTML để tránh XSS khi render nội dung từ server
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function openExpenseTypeModal(typeId = null) {
    // Mở modal thêm/sửa loại chi phí, tự điền dữ liệu khi sửa
    const title = document.getElementById('expenseTypeModalTitle');
    const subtitle = document.getElementById('expenseTypeModalSubtitle');
    const idField = document.getElementById('expenseTypeId');
    const nameField = document.getElementById('expenseTypeName');
    const descField = document.getElementById('expenseTypeDesc');

    if (typeId) {
      const found = expenseTypes.find((item) => String(item.ID_LOAI) === String(typeId));
      if (!found) return;
      title.textContent = 'Sửa loại chi phí';
      subtitle.textContent = 'Chỉnh sửa';
      idField.value = found.ID_LOAI;
      nameField.value = found.TEN_LOAI || '';
      descField.value = found.MOTA_LOAI || '';
    } else {
      title.textContent = 'Thêm loại chi phí';
      subtitle.textContent = 'Tạo mới';
      idField.value = '';
      nameField.value = '';
      descField.value = '';
    }

    document.getElementById('expenseTypeModal').classList.remove('hidden');
    document.getElementById('expenseTypeModal').classList.add('flex');
    nameField.focus();
  }

  function closeExpenseTypeModal() {
    const modal = document.getElementById('expenseTypeModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }

  async function submitExpenseTypeForm(event) {
    // Xử lý submit form modal: quyết định thêm mới hay cập nhật
    event.preventDefault();
    const id = document.getElementById('expenseTypeId').value;
    const name = document.getElementById('expenseTypeName').value.trim();
    const desc = document.getElementById('expenseTypeDesc').value.trim();

    if (!name) {
      alert('Vui lòng nhập tên loại chi phí.');
      return;
    }

    if (id) {
      await updateExpenseType(id, name, desc);
    } else {
      await createExpenseType(name, desc);
    }
    closeExpenseTypeModal();
  }

  async function createExpenseType(name, desc) {
    // Gửi request tạo loại chi phí mới
    const formData = new FormData();
    formData.append('ten_loai', name);
    formData.append('mota_loai', desc);

    try {
      const res = await fetch(apiExpenseType, { method: 'POST', body: formData });
      const data = await res.json();
      if (data.status === 'success') {
        alert('Đã tạo loại chi phí mới.');
        loadExpenseTypes();
      } else {
        alert('Lỗi: ' + (data.message || 'Không xác định'));
      }
    } catch (err) {
      console.error(err);
      alert('Lỗi kết nối máy chủ.');
    }
  }

  async function updateExpenseType(id, name, desc) {
    // Gửi request cập nhật tên/mô tả loại chi phí
    const params = new URLSearchParams();
    params.append('id_loai', id);
    params.append('ten_loai', name);
    params.append('mota_loai', desc);

    try {
      const res = await fetch(apiExpenseType, { method: 'PUT', body: params });
      const data = await res.json();
      if (data.status === 'success') {
        alert('Đã cập nhật loại chi phí.');
        loadExpenseTypes();
      } else {
        alert('Lỗi: ' + (data.message || 'Không xác định'));
      }
    } catch (err) {
      console.error(err);
      alert('Lỗi kết nối máy chủ.');
    }
  }

  async function toggleExpenseTypeStatus(id, currentStatus) {
    // Bật/tắt trạng thái active của loại chi phí
    const nextStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const confirmText = nextStatus === 'inactive'
      ? 'Tắt loại chi phí này? Chi nhánh sẽ không còn thấy trên màn nhập.'
      : 'Kích hoạt lại loại chi phí này để hiển thị cho chi nhánh.';
    if (!confirm(confirmText)) return;

    const params = new URLSearchParams();
    params.append('id_loai', id);
    params.append('ten_loai', expenseTypes.find((i) => String(i.ID_LOAI) === String(id))?.TEN_LOAI || '');
    params.append('mota_loai', expenseTypes.find((i) => String(i.ID_LOAI) === String(id))?.MOTA_LOAI || '');
    params.append('trang_thai', nextStatus);

    try {
      const res = await fetch(apiExpenseType, { method: 'PUT', body: params });
      const data = await res.json();
      if (data.status === 'success') {
        loadExpenseTypes();
      } else {
        alert('Lỗi: ' + (data.message || 'Không xác định'));
      }
    } catch (err) {
      console.error(err);
      alert('Lỗi kết nối máy chủ.');
    }
  }

  async function deleteExpenseType(id, name) {
    // Xóa vĩnh viễn một loại chi phí (kèm dữ liệu liên quan)
    if (!confirm('Xóa vĩnh viễn "' + name + '"? Tất cả dữ liệu chi phí liên quan sẽ bị xóa.')) return;
    try {
      const res = await fetch(`${apiExpenseType}?id=${id}`, { method: 'DELETE' });
      const data = await res.json();
      if (data.status === 'success') {
        loadExpenseTypes();
      } else {
        alert('Lỗi: ' + (data.message || 'Không xác định'));
      }
    } catch (err) {
      console.error(err);
      alert('Lỗi kết nối máy chủ.');
    }
  }

  async function loadExpenseTypes() {
    // Tải danh sách loại chi phí để render bảng và thống kê trạng thái
    const tbody = document.getElementById('expenseTypeTbody');
    tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-4 text-center text-slate-500">Đang tải...</td></tr>';
    try {
      const res = await fetch(`${apiExpenseType}?action=list&all=1`);
      const data = await res.json();
      if (data.status !== 'success') {
        tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-4 text-center text-red-500">Không tải được danh sách.</td></tr>';
        return;
      }
      expenseTypes = data.data || [];
      renderExpenseTypes(expenseTypes);
    } catch (err) {
      console.error(err);
      tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-4 text-center text-red-500">Lỗi kết nối.</td></tr>';
    }
  }

  function renderExpenseTypes(list) {
    // Render bảng loại chi phí và cập nhật số liệu thống kê
    const tbody = document.getElementById('expenseTypeTbody');
    if (!list.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-4 text-center text-slate-500">Chưa có loại chi phí nào.</td></tr>';
      document.getElementById('statTotalTypes').textContent = '0';
      document.getElementById('statActiveTypes').textContent = '0';
      document.getElementById('statInactiveTypes').textContent = '0';
      return;
    }

    const activeCount = list.filter((item) => item.TRANG_THAI === 'active').length;
    const inactiveCount = list.length - activeCount;
    document.getElementById('statTotalTypes').textContent = list.length;
    document.getElementById('statActiveTypes').textContent = activeCount;
    document.getElementById('statInactiveTypes').textContent = inactiveCount;

    tbody.innerHTML = list
      .map((item) => {
        const statusLabel = item.TRANG_THAI === 'active'
          ? '<span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700"><span class="w-2 h-2 rounded-full bg-green-500"></span>Active</span>'
          : '<span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700"><span class="w-2 h-2 rounded-full bg-amber-500"></span>Inactive</span>';

        const safeName = escapeHtml(item.TEN_LOAI || '');
        const safeDesc = escapeHtml(item.MOTA_LOAI || '');

        return `
          <tr class="hover:bg-slate-50 transition">
            <td class="px-6 py-4 font-semibold text-slate-900">${safeName}</td>
            <td class="px-6 py-4 text-slate-700">${safeDesc || '<span class="text-slate-400">-</span>'}</td>
            <td class="px-6 py-4 text-center">${statusLabel}</td>
            <td class="px-6 py-4">
              <div class="flex items-center justify-center gap-3">
                <button class="text-indigo-600 hover:font-semibold" onclick="openExpenseTypeModal(${item.ID_LOAI})">Sửa</button>
                <button class="text-amber-600 hover:font-semibold" onclick="toggleExpenseTypeStatus(${item.ID_LOAI}, '${item.TRANG_THAI}')">${item.TRANG_THAI === 'active' ? 'Tạm ngưng' : 'Kích hoạt'}</button>
                <button class="text-red-600 hover:font-semibold" onclick="deleteExpenseType(${item.ID_LOAI}, '${safeName}')">Xóa</button>
              </div>
            </td>
          </tr>`;
      })
      .join('');
  }

  function filterExpenseTypes() {
    // Lọc theo từ khóa tên loại chi phí
    const keyword = document.getElementById('expenseTypeSearch').value.trim().toLowerCase();
    if (!keyword) {
      renderExpenseTypes(expenseTypes);
      return;
    }
    const filtered = expenseTypes.filter((item) => (item.TEN_LOAI || '').toLowerCase().includes(keyword));
    renderExpenseTypes(filtered);
  }

  async function loadExpenseSummary() {
    // Gọi API summary để xem tổng chi phí theo loại, có filter tháng/chi nhánh
    const container = document.getElementById('expenseSummaryContainer');
    const thang = document.getElementById('summaryMonth').value || '<?= htmlspecialchars($currentMonth) ?>';
    const idCn = document.getElementById('summaryBranch').value || '';
    container.innerHTML = '<p class="text-sm text-slate-500">Đang tải dữ liệu tháng ' + thang + '...</p>';
    try {
      const url = new URL(`${apiExpenseValue}`, window.location.href);
      url.searchParams.set('action', 'summary');
      url.searchParams.set('thang', thang);
      if (idCn) {
        url.searchParams.set('id_cn', idCn);
      }

      const res = await fetch(url.toString());
      const data = await res.json();
      if (data.status !== 'success') {
        container.innerHTML = '<p class="text-red-500">Không tải được tổng hợp.</p>';
        return;
      }
      const summary = data.data || [];
      if (!summary.length) {
        container.innerHTML = '<p class="text-slate-500 text-sm">Chưa có dữ liệu cho lựa chọn này.</p>';
        return;
      }
      const cards = summary
        .map((item) => {
          const amount = parseInt(item.TONG_GIA_TRI || 0).toLocaleString('vi-VN');
          const branches = item.SO_CHI_NHANH || 0;
          return `
            <div class="border border-slate-200 rounded-xl p-4 bg-slate-50 hover:bg-slate-100 transition">
              <div class="flex items-center justify-between">
                <h4 class="font-semibold text-slate-900">${escapeHtml(item.TEN_LOAI || 'N/A')}</h4>
                <span class="text-xs font-semibold text-slate-600">${branches} CN</span>
              </div>
              <p class="text-lg font-bold text-indigo-700 mt-2">${amount} đ</p>
            </div>`;
        })
        .join('');
      container.innerHTML = `<div class="grid gap-3 md:grid-cols-2">${cards}</div>`;
    } catch (err) {
      console.error(err);
      container.innerHTML = '<p class="text-red-500">Lỗi kết nối.</p>';
    }
  }
</script>
