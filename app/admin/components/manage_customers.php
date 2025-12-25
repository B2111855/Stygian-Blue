<?php
include '../../database/config.php';
require_once __DIR__ . '/../../helpers/assets.php';

if (!defined('CUSTOMER_PAGE_SIZE')) {
    define('CUSTOMER_PAGE_SIZE', 8);
}

function escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function clean_input($value)
{
    if (is_string($value)) {
        return trim($value);
    }

    if (is_numeric($value)) {
        return (string) $value;
    }

    return '';
}

function getPaginatedCustomers($search, $page, $limit = CUSTOMER_PAGE_SIZE)
{
    global $conn;

    $page = max(1, (int) $page);
    $limit = max(1, (int) $limit);
    $offset = ($page - 1) * $limit;
    $search = clean_input($search);

    $whereClause = 'WHERE kh.IS_DELETED = 0';
    $keyword = null;

    if ($search !== '') {
        $whereClause .= ' AND (kh.ID_TK LIKE ? OR kh.HO_TEN LIKE ? OR kh.EMAIL LIKE ? OR kh.SDT LIKE ?)';
        $keyword = '%' . $search . '%';
    }

    $countSql = "SELECT COUNT(*) AS total
                 FROM khach_hang kh
                 INNER JOIN tai_khoan tk ON kh.ID_TK = tk.ID_TK
                 $whereClause";
    $countStmt = mysqli_prepare($conn, $countSql);

    if (!$countStmt) {
        error_log("Prepare error: " . $conn->error);
        return ['rows' => [], 'totalPages' => 1, 'total' => 0, 'limit' => $limit, 'page' => 1];
    }

    if ($keyword !== null) {
        mysqli_stmt_bind_param($countStmt, 'ssss', $keyword, $keyword, $keyword, $keyword);
    }

    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    $total = $countResult ? (int) mysqli_fetch_assoc($countResult)['total'] : 0;
    mysqli_stmt_close($countStmt);

    $totalPages = max(1, (int) ceil(max(1, $total) / $limit));
    if ($total === 0) {
        $totalPages = 1;
        $page = 1;
        $offset = 0;
    } elseif ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    $listSql = "SELECT kh.ID_TK, kh.HO_TEN, kh.NGAY_SINH, kh.DIA_CHI, kh.EMAIL, kh.SDT
                FROM khach_hang kh
                INNER JOIN tai_khoan tk ON kh.ID_TK = tk.ID_TK
                $whereClause
                ORDER BY kh.HO_TEN ASC
                LIMIT " . intval($limit) . " OFFSET " . intval($offset);
    
    $listStmt = mysqli_prepare($conn, $listSql);

    if (!$listStmt) {
        error_log("Prepare error: " . $conn->error);
        return ['rows' => [], 'totalPages' => $totalPages, 'total' => $total, 'limit' => $limit, 'page' => $page];
    }

    if ($keyword !== null) {
        mysqli_stmt_bind_param($listStmt, 'ssss', $keyword, $keyword, $keyword, $keyword);
    }

    mysqli_stmt_execute($listStmt);
    $listResult = mysqli_stmt_get_result($listStmt);
    $rows = $listResult ? mysqli_fetch_all($listResult, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($listStmt);

    return [
        'rows' => $rows,
        'totalPages' => $totalPages,
        'total' => $total,
        'limit' => $limit,
        'page' => $page,
    ];
}

function formatDateForDisplay($date)
{
    if (!$date) {
        return '';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    return date('d/m/Y', $timestamp);
}

function formatDateForInput($date)
{
    if (!$date) {
        return '';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d', $timestamp);
}

function countCustomers($search = '')
{
    global $conn;
    $search = clean_input($search);
    $whereClause = 'WHERE kh.IS_DELETED = 0';

    if ($search !== '') {
        $whereClause .= ' AND (kh.ID_TK LIKE ? OR kh.HO_TEN LIKE ? OR kh.EMAIL LIKE ? OR kh.SDT LIKE ?)';
        $keyword = '%' . $search . '%';

        $countSql = "SELECT COUNT(*) AS total FROM khach_hang kh INNER JOIN tai_khoan tk ON kh.ID_TK = tk.ID_TK $whereClause";
        $countStmt = mysqli_prepare($conn, $countSql);
        
        if (!$countStmt) {
            error_log("Prepare error: " . $conn->error);
            return 0;
        }
        
        mysqli_stmt_bind_param($countStmt, 'ssss', $keyword, $keyword, $keyword, $keyword);
    } else {
        $countSql = "SELECT COUNT(*) AS total FROM khach_hang kh INNER JOIN tai_khoan tk ON kh.ID_TK = tk.ID_TK $whereClause";
        $countStmt = mysqli_prepare($conn, $countSql);
        
        if (!$countStmt) {
            error_log("Prepare error: " . $conn->error);
            return 0;
        }
    }

    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    $total = $countResult ? (int) mysqli_fetch_assoc($countResult)['total'] : 0;
    mysqli_stmt_close($countStmt);

    return $total;
}

$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$pageNumber = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
$pagination = getPaginatedCustomers($search, $pageNumber);
$customers = $pagination['rows'];
$pageNumber = $pagination['page'];
$totalPages = $pagination['totalPages'];
$totalCustomers = $pagination['total'];
$perPage = $pagination['limit'];
$firstItemIndex = $totalCustomers ? (($pageNumber - 1) * $perPage) + 1 : 0;
$lastItemIndex = $totalCustomers ? min($totalCustomers, $pageNumber * $perPage) : 0;
$showingStart = $firstItemIndex;
$showingEnd = $lastItemIndex;
$maxAllowedBirthDate = date('Y-m-d', strtotime('-18 years'));
$customerSummary = countCustomers();
?>
<body class="bg-gray-100 min-h-screen p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-extrabold text-indigo-700">Quản lý khách hàng</h1>
            <a href="admin_dashboard.php?page=deleted_customers" class="inline-flex items-center gap-2 px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-semibold transition-colors duration-200" title="Xem khách hàng đã bị xóa mềm">
                <i class="fas fa-trash-alt"></i>
                <span>Đã Xóa</span>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Tổng khách hàng</p>
                <p class="text-3xl font-bold text-indigo-700 mt-2"><?= number_format($totalCustomers) ?></p>
                <p class="text-sm text-gray-500 mt-1">Tất cả khách hàng đã đăng ký trong hệ thống.</p>
            </div>
        </div>

        <!-- Search + Add button -->
        <div class="flex flex-wrap gap-4 mb-6">
            <form action="admin_dashboard.php" method="GET" class="w-full md:flex-1 bg-white rounded-xl shadow-lg p-4">
                <input type="hidden" name="page" value="customers">
                <div class="flex flex-wrap gap-3">
                    <input type="text" name="search" placeholder="Tìm theo ID, tên, email, số điện thoại" 
                        value="<?= escape($search) ?>"
                        class="flex-1 min-w-64 border border-gray-300 rounded-lg px-4 py-2 shadow-sm focus:ring-indigo-500">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg shadow">Tìm kiếm</button>
                    <a href="?page=customers" class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg shadow hover:bg-gray-50">Đặt lại</a>
                </div>
            </form>
            <div class="w-full md:w-64">
                <button onclick="openAddCustomerModal()"
                    class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold shadow">
                    Thêm khách hàng
                </button>
            </div>
        </div>

        <div class="flex flex-wrap justify-between items-center text-sm text-gray-600 bg-white px-4 py-3 rounded-xl shadow mb-6">
            <p>Hiển thị <?= $showingStart ?> - <?= $showingEnd ?> trên tổng <?= $totalCustomers ?> khách hàng</p>
            <p>Trang <?= $pageNumber ?> / <?= $totalPages ?></p>
        </div>

        <!-- Customer table -->
        <div id="customersTable" class="overflow-x-auto bg-white rounded-xl shadow-lg">
            <table class="min-w-full table-auto text-sm">
                <thead class="bg-indigo-100 text-indigo-700">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Họ tên</th>
                        <th class="px-4 py-3">Ngày sinh</th>
                        <th class="px-4 py-3">Địa chỉ</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Số điện thoại</th>
                        <th class="px-4 py-3">Hành động</th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    <?php if (empty($customers)) : ?>
                        <tr>
                            <td colspan="7" class="text-red-600 font-bold py-6 text-center">Không tìm thấy khách hàng nào.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($customers as $customer) : ?>
                            <tr class="hover:bg-gray-50" data-customer-id="<?= escape($customer['ID_TK']) ?>">
                                <td class="px-4 py-3 text-left md:text-center font-semibold text-gray-800"><?= escape($customer['ID_TK']) ?></td>
                                <td class="px-4 py-3 text-left font-semibold text-gray-900"><?= escape($customer['HO_TEN']) ?></td>
                                <td class="px-4 py-3 text-left"><?= escape(formatDateForDisplay($customer['NGAY_SINH'])) ?></td>
                                <td class="px-4 py-3 text-left"><?= escape($customer['DIA_CHI']) ?></td>
                                <td class="px-4 py-3 text-left"><?= escape($customer['EMAIL']) ?></td>
                                <td class="px-4 py-3 text-left"><?= escape($customer['SDT']) ?></td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-center gap-2">
                                        <button onclick="openEditCustomerModal('<?= escape($customer['ID_TK']) ?>')"
                                            class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-indigo-700 border border-indigo-200 rounded-lg hover:bg-indigo-50">
                                            Sửa
                                        </button>
                                        <button onclick="openDeleteModal('<?= escape($customer['ID_TK']) ?>', '<?= escape($customer['HO_TEN']) ?>')"
                                            class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-red-600 border border-red-200 rounded-lg hover:bg-red-50">
                                            Xóa
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php
        echo '<div class="mt-6 flex flex-wrap justify-center gap-2" id="paginationContainer">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $queryString = http_build_query([
                'page' => 'customers',
                'p' => $i,
                'search' => $search,
            ]);
            $active = ($i == $pageNumber) ? 'bg-indigo-600 text-white' : 'bg-gray-200 hover:bg-gray-300';
            echo "<a href='?$queryString' class='px-3 py-1 rounded $active pagination-link' data-page='$i'>$i</a>";
        }
        echo '</div>';
        ?>

        <!-- Modal: Thêm/Sửa khách hàng -->
        <div id="customerModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <form id="customerForm" class="p-6 space-y-6">
                    <div class="flex justify-between items-center border-b pb-4">
                        <h2 class="text-2xl font-bold text-indigo-700" id="modalTitle">Thêm khách hàng mới</h2>
                        <button type="button" onclick="closeCustomerModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                    </div>

                    <input type="hidden" id="modalAction" name="action" value="create">
                    <input type="hidden" id="modalIdTk" name="ID_TK">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- ID Tài khoản (chỉ hiển thị khi thêm) -->
                        <div id="idTkField">
                            <label class="block mb-1 text-sm font-medium text-gray-700">ID Tài khoản</label>
                            <input type="text" id="ID_TK" name="ID_TK" required
                                class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                        </div>

                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">Họ tên</label>
                            <input type="text" id="HO_TEN" name="HO_TEN" required
                                class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                        </div>

                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">Ngày sinh</label>
                            <input type="date" id="NGAY_SINH" name="NGAY_SINH" max="<?= escape($maxAllowedBirthDate) ?>"
                                class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                        </div>

                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">Địa chỉ</label>
                            <input type="text" id="DIA_CHI" name="DIA_CHI" required
                                class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                        </div>

                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">Email</label>
                            <input type="email" id="EMAIL" name="EMAIL" required
                                class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                        </div>

                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">Số điện thoại</label>
                            <input type="text" id="SDT" name="SDT" required
                                class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                        </div>

                        <div class="md:col-span-2">
                            <label class="block mb-1 text-sm font-medium text-gray-700" id="passwordLabel">Mật khẩu</label>
                            <input type="password" id="MAT_KHAU" name="MAT_KHAU"
                                class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                        </div>
                    </div>

                    <div id="formError" class="hidden bg-red-100 text-red-700 px-4 py-3 rounded"></div>

                    <div class="flex justify-between items-center border-t pt-4">
                        <button type="button" onclick="closeCustomerModal()"
                            class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold px-6 py-2 rounded-lg shadow">
                            Đóng
                        </button>
                        <button type="submit" id="submitBtn"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2 rounded-lg shadow flex items-center gap-2">
                            <span id="submitBtnText">Thêm mới</span>
                            <span id="submitBtnSpinner" class="hidden animate-spin">⟳</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Xác nhận xóa -->
        <div id="deleteConfirmModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
                <div class="p-6 space-y-4">
                    <h3 class="text-xl font-bold text-gray-800">Xác nhận xóa khách hàng</h3>
                    <p class="text-gray-600">
                        Bạn có chắc chắn muốn xóa khách hàng <strong id="deleteCustomerName"></strong> không? 
                    </p>
                    <div id="deleteError" class="hidden bg-red-100 text-red-700 px-4 py-3 rounded text-sm"></div>
                </div>
                <div class="flex gap-3 bg-gray-50 px-6 py-4 rounded-b-xl">
                    <button type="button" onclick="closeDeleteModal()"
                        class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold px-4 py-2 rounded">
                        Hủy
                    </button>
                    <button type="button" onclick="confirmDelete()"
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2 rounded flex items-center justify-center gap-2">
                        <span id="deleteBtnText">Xóa</span>
                        <span id="deleteBtnSpinner" class="hidden animate-spin">⟳</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Toast Notifications -->
        <div id="toastContainer" class="fixed top-4 right-4 z-[9999] space-y-3 max-w-md"></div>
    </div>
</body>

<script>
// API base URL
const API_BASE = './api/api_customers.php';
let deleteConfirmData = { id_tk: null, name: null };
let showingStart = <?= $firstItemIndex ?>;
let showingEnd = <?= $lastItemIndex ?>;

// ==================== TOAST NOTIFICATIONS ====================
function showToast(message, type = 'info', duration = 4000) {
    const toastContainer = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    
    const bgColor = {
        'success': 'bg-green-500',
        'error': 'bg-red-500',
        'info': 'bg-blue-500',
        'warning': 'bg-yellow-500'
    }[type] || 'bg-blue-500';

    toast.className = `${bgColor} text-white px-4 py-3 rounded-lg shadow-lg flex justify-between items-center animate-pulse`;
    toast.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="ml-4 text-lg font-bold">&times;</button>
    `;
    
    toastContainer.appendChild(toast);
    
    if (duration > 0) {
        setTimeout(() => toast.remove(), duration);
    }
}

// ==================== MODAL: THÊM/SỬA KHÁCH HÀNG ====================
function openAddCustomerModal() {
    resetCustomerForm();
    document.getElementById('modalAction').value = 'create';
    document.getElementById('modalTitle').textContent = 'Thêm khách hàng mới';
    document.getElementById('submitBtnText').textContent = 'Thêm mới';
    document.getElementById('passwordLabel').innerHTML = 'Mật khẩu <span class="text-red-500">*</span>';
    document.getElementById('MAT_KHAU').required = true;
    document.getElementById('idTkField').style.display = 'block';
    document.getElementById('ID_TK').required = true;
    document.getElementById('customerModal').classList.remove('hidden');
    document.getElementById('customerModal').classList.add('flex');
}

function openEditCustomerModal(idTk) {
    const row = document.querySelector(`tr[data-customer-id="${idTk}"]`);
    
    if (!row) {
        showToast('Không tìm thấy dữ liệu khách hàng', 'error');
        return;
    }

    const cells = row.querySelectorAll('td');
    const hoTen = cells[1]?.textContent?.trim() || '';
    const ngaySinh = cells[2]?.textContent?.trim() || '';
    const diaChi = cells[3]?.textContent?.trim() || '';
    const email = cells[4]?.textContent?.trim() || '';
    const sdt = cells[5]?.textContent?.trim() || '';

    document.getElementById('modalAction').value = 'update';
    document.getElementById('modalTitle').textContent = 'Sửa thông tin khách hàng';
    document.getElementById('submitBtnText').textContent = 'Cập nhật';
    document.getElementById('passwordLabel').innerHTML = 'Mật khẩu mới (bỏ trống nếu không đổi)';
    document.getElementById('MAT_KHAU').required = false;
    document.getElementById('idTkField').style.display = 'none';
    
    document.getElementById('modalIdTk').value = idTk;
    document.getElementById('ID_TK').value = idTk;
    document.getElementById('HO_TEN').value = hoTen;
    document.getElementById('NGAY_SINH').value = ngaySinh ? convertDateForInput(ngaySinh) : '';
    document.getElementById('DIA_CHI').value = diaChi;
    document.getElementById('EMAIL').value = email;
    document.getElementById('SDT').value = sdt;
    document.getElementById('MAT_KHAU').value = '';
    
    document.getElementById('customerModal').classList.remove('hidden');
    document.getElementById('customerModal').classList.add('flex');
}

function convertDateForInput(dateStr) {
    // Convert dd/mm/yyyy to yyyy-mm-dd
    const parts = dateStr.split('/');
    if (parts.length === 3) {
        return `${parts[2]}-${parts[1]}-${parts[0]}`;
    }
    return dateStr;
}

function closeCustomerModal() {
    document.getElementById('customerModal').classList.add('hidden');
    document.getElementById('customerModal').classList.remove('flex');
    resetCustomerForm();
}

function resetCustomerForm() {
    document.getElementById('customerForm').reset();
    document.getElementById('formError').classList.add('hidden');
    document.getElementById('submitBtn').disabled = false;
    document.getElementById('submitBtnSpinner').classList.add('hidden');
}

document.getElementById('customerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const action = document.getElementById('modalAction').value;
    const submitBtn = document.getElementById('submitBtn');
    const spinnerEl = document.getElementById('submitBtnSpinner');
    const errorEl = document.getElementById('formError');
    
    submitBtn.disabled = true;
    spinnerEl.classList.remove('hidden');
    errorEl.classList.add('hidden');

    const formData = new FormData(document.getElementById('customerForm'));
    formData.set('action', action);
    
    try {
        const res = await fetch(API_BASE, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        
        const result = await res.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            closeCustomerModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            errorEl.textContent = result.message;
            errorEl.classList.remove('hidden');
            showToast(result.message, 'error', 5000);
        }
    } catch (err) {
        console.error('Error:', err);
        errorEl.textContent = 'Lỗi kết nối. Vui lòng thử lại.';
        errorEl.classList.remove('hidden');
        showToast('Lỗi kết nối', 'error');
    } finally {
        submitBtn.disabled = false;
        spinnerEl.classList.add('hidden');
    }
});

// ==================== MODAL: XÁC NHẬN XÓA ====================
function openDeleteModal(idTk, name) {
    deleteConfirmData = { id_tk: idTk, name: name };
    document.getElementById('deleteCustomerName').textContent = name;
    document.getElementById('deleteError').classList.add('hidden');
    document.getElementById('deleteConfirmModal').classList.remove('hidden');
    document.getElementById('deleteConfirmModal').classList.add('flex');
}

function closeDeleteModal() {
    document.getElementById('deleteConfirmModal').classList.add('hidden');
    document.getElementById('deleteConfirmModal').classList.remove('flex');
    deleteConfirmData = { id_tk: null, name: null };
}

async function confirmDelete() {
    if (!deleteConfirmData.id_tk) return;
    
    const deleteBtn = document.querySelector('#deleteConfirmModal button[onclick="confirmDelete()"]');
    const spinnerEl = document.getElementById('deleteBtnSpinner');
    const errorEl = document.getElementById('deleteError');
    
    deleteBtn.disabled = true;
    spinnerEl.classList.remove('hidden');
    errorEl.classList.add('hidden');

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('ID_TK', deleteConfirmData.id_tk);

    try {
        const res = await fetch(API_BASE, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        
        const result = await res.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            closeDeleteModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            errorEl.textContent = result.message;
            errorEl.classList.remove('hidden');
            showToast(result.message, 'error', 5000);
        }
    } catch (err) {
        console.error('Error:', err);
        errorEl.textContent = 'Lỗi kết nối. Vui lòng thử lại.';
        errorEl.classList.remove('hidden');
        showToast('Lỗi kết nối', 'error');
    } finally {
        deleteBtn.disabled = false;
        spinnerEl.classList.add('hidden');
    }
}

// ==================== MODAL: CLOSE ON ESCAPE ====================
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeCustomerModal();
        closeDeleteModal();
    }
});

// Close modal when clicking outside
document.getElementById('customerModal')?.addEventListener('click', (e) => {
    if (e.target === document.getElementById('customerModal')) {
        closeCustomerModal();
    }
});

document.getElementById('deleteConfirmModal')?.addEventListener('click', (e) => {
    if (e.target === document.getElementById('deleteConfirmModal')) {
        closeDeleteModal();
    }
});

// Smooth scroll to table on pagination
document.querySelectorAll('.pagination-link').forEach(link => {
    link.addEventListener('click', function() {
        sessionStorage.setItem('scrollToTable', 'true');
    });
});

document.addEventListener('DOMContentLoaded', function() {
    if (sessionStorage.getItem('scrollToTable')) {
        sessionStorage.removeItem('scrollToTable');
        const table = document.getElementById('customersTable');
        if (table) {
            setTimeout(() => {
                table.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 300);
        }
    }
});
</script>
</html>
