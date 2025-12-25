<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include '../../database/config.php';

// Get manager's branch ID
$managerBranchId = null;
$managerBranchName = '';
if (isset($_SESSION['ID_TK'])) {
    $getBranchStmt = $conn->prepare("SELECT nv.ID_CN, cn.TEN_CN FROM nhan_vien nv 
                                     INNER JOIN chi_nhanh cn ON nv.ID_CN = cn.ID_CN 
                                     WHERE nv.ID_TK = ? LIMIT 1");
    $getBranchStmt->bind_param("s", $_SESSION['ID_TK']);
    $getBranchStmt->execute();
    $result = $getBranchStmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $managerBranchId = $row['ID_CN'];
        $managerBranchName = $row['TEN_CN'];
    }
}

if (!$managerBranchId) {
    echo "<div class='p-6 bg-red-50 border border-red-200 text-red-700 rounded'>Lỗi: Không tìm thấy chi nhánh của bạn.</div>";
    exit;
}

$defaultLimit = 6;
$allowedPageSizes = [6, 10, 15, 25];
$perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : $defaultLimit;
$perPage = in_array($perPage, $allowedPageSizes, true) ? $perPage : $defaultLimit;
$limit = $perPage;
$page = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$roleFilter = isset($_GET['role']) ? trim($_GET['role']) : '';

function getEmployeesSearch($conn, $branchId, $search = "", $role = "", $limit = 6, $offset = 0)
{
    $query = "
    SELECT nv.ID_TK,
           nv.ID_CN,
           nv.CHUYEN_MON,
           nv.LOAI_NV,      
           tk.HO_TEN,
           tk.NGAY_SINH,
           tk.DIA_CHI,
           tk.EMAIL,
           tk.SDT,
           cn.TEN_CN
    FROM nhan_vien nv
    INNER JOIN tai_khoan tk ON nv.ID_TK = tk.ID_TK
    INNER JOIN chi_nhanh cn ON nv.ID_CN = cn.ID_CN
    WHERE nv.ID_CN = ? AND nv.IS_DELETED = 0
    ";

    $params = [$branchId];
    $types = 's';

    if (!empty($search)) {
        $search_param = "%$search%";
        $query .= " AND (tk.ID_TK LIKE ? OR tk.HO_TEN LIKE ? OR tk.EMAIL LIKE ?)";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'sss';
    }

    if (!empty($role)) {
        $query .= " AND nv.LOAI_NV = ?";
        $params[] = $role;
        $types .= 's';
    }

    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

function countEmployees($conn, $branchId, $search = "", $role = "")
{
    $query = "SELECT COUNT(*) as total FROM nhan_vien nv 
              INNER JOIN tai_khoan tk ON nv.ID_TK = tk.ID_TK 
              WHERE nv.ID_CN = ? AND nv.IS_DELETED = 0";
    $params = [$branchId];
    $types = 's';

    if (!empty($search)) {
        $search_param = "%$search%";
        $query .= " AND (tk.ID_TK LIKE ? OR tk.HO_TEN LIKE ? OR tk.EMAIL LIKE ?)";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'sss';
    }

    if (!empty($role)) {
        $query .= " AND nv.LOAI_NV = ?";
        $params[] = $role;
        $types .= 's';
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['total'];
}

function getEmployeeSummary($conn, $branchId)
{
    $summary = [
        'total' => 0,
        'manager' => 0,
        'staff' => 0,
    ];

    $query = "SELECT LOAI_NV, COUNT(*) AS total FROM nhan_vien WHERE ID_CN = ? AND IS_DELETED = 0 GROUP BY LOAI_NV";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $branchId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $summary['total'] += (int)$row['total'];
        if ($row['LOAI_NV'] === 'quan_ly') {
            $summary['manager'] = (int)$row['total'];
        } else {
            $summary['staff'] += (int)$row['total'];
        }
    }

    return $summary;
}

$employees = getEmployeesSearch($conn, $managerBranchId, $search, $roleFilter, $limit, $offset);
$totalRecords = countEmployees($conn, $managerBranchId, $search, $roleFilter);
$totalPages = max(1, ceil($totalRecords / $limit));
$showingStart = $totalRecords ? $offset + 1 : 0;
$showingEnd = min($offset + $limit, $totalRecords);

$selectedRole = (string)$roleFilter;
$employeeSummary = getEmployeeSummary($conn, $managerBranchId);

?>

<body class="bg-gray-100 p-6">
    <div class="max-w-7xl mx-auto">
        <div class="mb-6 flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-extrabold text-indigo-700">Quản lý nhân viên</h1>
                <p class="text-gray-600 mt-1">Chi nhánh: <strong><?= htmlspecialchars($managerBranchName) ?></strong></p>
            </div>
            <a href="manager_dashboard.php?page=deleted_employees"
                class="inline-flex items-center gap-2 px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-semibold transition-colors duration-200" title="Xem nhân viên đã bị xóa mềm">
                <i class="fas fa-trash-alt"></i>
                <span>Đã Xóa</span>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Tổng nhân viên</p>
                <p class="text-3xl font-bold text-indigo-700 mt-2"><?= number_format($employeeSummary['total']) ?></p>
                <p class="text-sm text-gray-500 mt-1">Trong chi nhánh của bạn</p>
            </div>
            <div class="bg-white rounded-xl shadow p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Quản lý</p>
                <p class="text-3xl font-bold text-emerald-600 mt-2"><?= number_format($employeeSummary['manager']) ?></p>
                <p class="text-sm text-gray-500 mt-1">Nhân viên quản lý</p>
            </div>
            <div class="bg-white rounded-xl shadow p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Nhân sự</p>
                <p class="text-3xl font-bold text-blue-600 mt-2"><?= number_format($employeeSummary['staff']) ?></p>
                <p class="text-sm text-gray-500 mt-1">Nhân viên thường</p>
            </div>
        </div>

        <!-- Bộ lọc -->
        <div class="flex flex-wrap gap-4 mb-6">
            <form action="manager_dashboard.php" method="GET" class="w-full md:flex-1 bg-white rounded-xl shadow-lg p-4 space-y-4">
                <input type="hidden" name="page" value="employees">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="flex flex-col">
                        <label for="search" class="text-sm font-medium text-gray-700 mb-1">Tìm kiếm</label>
                        <input type="text" id="search" name="search" placeholder="Nhập tên hoặc email"
                            value="<?= htmlspecialchars($search) ?>"
                            class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500 shadow-sm">
                    </div>
                    <div class="flex flex-col">
                        <label for="role" class="text-sm font-medium text-gray-700 mb-1">Vai trò</label>
                        <select id="role" name="role" class="border border-gray-300 rounded-lg px-4 py-2 shadow-sm">
                            <option value="">Tất cả vai trò</option>
                            <option value="chuyen_trach" <?= $selectedRole === 'chuyen_trach' ? 'selected' : '' ?>>Nhân sự thường</option>
                            <option value="quan_ly" <?= $selectedRole === 'quan_ly' ? 'selected' : '' ?>>Quản lý</option>
                        </select>
                    </div>
                    <div class="flex flex-col">
                        <label for="per_page" class="text-sm font-medium text-gray-700 mb-1">Số bản ghi/trang</label>
                        <select id="per_page" name="per_page" class="border border-gray-300 rounded-lg px-4 py-2 shadow-sm">
                            <?php foreach ($allowedPageSizes as $size): ?>
                                <option value="<?= $size ?>" <?= $size === $perPage ? 'selected' : '' ?>><?= $size ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3 justify-end pt-2 border-t border-gray-100">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg shadow">Áp dụng bộ lọc</button>
                    <a href="manager_dashboard.php?page=employees" class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg shadow hover:bg-gray-50">Đặt lại</a>
                </div>
            </form>
            <div class="w-full md:w-64">
                <div class="bg-white rounded-xl shadow-lg p-4 h-full flex flex-col gap-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 mb-1">Tùy chọn nhanh</p>
                        <p class="text-lg font-bold text-gray-800">Quản lý nhân viên</p>
                    </div>
                    <button onclick="openAddEmployeeModal()"
                        class="inline-flex items-center justify-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold shadow">
                        + Thêm nhân viên
                    </button>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap justify-between items-center text-sm text-gray-600 bg-white px-4 py-3 rounded-xl shadow mb-6">
            <p>Hiển thị <?= $showingStart ?> - <?= $showingEnd ?> trên tổng <?= $totalRecords ?> nhân viên</p>
            <p>Trang <?= $page ?> / <?= $totalPages ?></p>
        </div>

        <!-- Danh sách nhân viên -->
        <div id="employeesTable" class="overflow-x-auto bg-white rounded-xl shadow-lg">
            <table class="min-w-full table-auto text-sm">
                <thead class="bg-indigo-100 text-indigo-700">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Họ tên &amp; chuyên môn</th>
                        <th class="px-4 py-3">Liên hệ</th>
                        <th class="px-4 py-3">Vai trò</th>
                        <th class="px-4 py-3">Hành động</th>
                    </tr>
                </thead>

                <tbody class="divide-y" id="employeesTableBody">
                    <?php if ($employees && mysqli_num_rows($employees) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($employees)): ?>
                            <tr class="hover:bg-gray-50" data-employee-id="<?= htmlspecialchars($row['ID_TK']) ?>">
                                <td class="px-4 py-3 text-left md:text-center font-semibold text-gray-800"><?= htmlspecialchars($row['ID_TK']) ?></td>
                                <td class="px-4 py-3 text-left">
                                    <p class="font-semibold text-gray-900"><?= htmlspecialchars($row['HO_TEN']) ?></p>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($row['CHUYEN_MON'] ?? '') ?></p>
                                </td>
                                <td class="px-4 py-3 text-left">
                                    <p><?= htmlspecialchars($row['EMAIL']) ?></p>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($row['SDT']) ?></p>
                                </td>

                                <td class="px-4 py-3">
                                    <?php
                                    $currentRole = $row['LOAI_NV'] ?? 'chuyen_trach';
                                    $label = ($currentRole === 'quan_ly') ? 'Quản lý' : 'Nhân sự thường';
                                    $badgeClass = ($currentRole === 'quan_ly')
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-blue-100 text-blue-700';
                                    ?>
                                    <span class="inline-block text-xs font-semibold px-2 py-1 rounded <?= $badgeClass ?>">
                                        <?= htmlspecialchars($label) ?>
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-center gap-2">
                                        <button onclick="openEditEmployeeModal('<?= htmlspecialchars($row['ID_TK']) ?>')"
                                            class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-indigo-700 border border-indigo-200 rounded-lg hover:bg-indigo-50">
                                            Sửa
                                        </button>
                                        <button onclick="openDeleteModal('<?= htmlspecialchars($row['ID_TK']) ?>', '<?= htmlspecialchars($row['HO_TEN']) ?>')"
                                            class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-red-600 border border-red-200 rounded-lg hover:bg-red-50">
                                            Xóa
                                        </button>
                                    </div>
                                </td>

                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-red-600 font-bold py-6 text-center">Không tìm thấy nhân viên nào.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        echo '<div class="mt-6 flex flex-wrap justify-center gap-2" id="paginationContainer">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $queryString = http_build_query([
                'page' => 'employees',
                'page_num' => $i,
                'search' => $search,
                'role' => $roleFilter,
                'per_page' => $perPage,
            ]);
            $active = ($i == $page) ? 'bg-indigo-600 text-white' : 'bg-gray-200 hover:bg-gray-300';
            echo "<a href='manager_dashboard.php?$queryString' class='px-3 py-1 rounded $active pagination-link' data-page='$i'>$i</a>";
        }
        echo '</div>';
        ?>

    </div>

</body>

<!-- ======================== MODALS & TOASTS ======================== -->

<!-- Modal: Thêm/Sửa nhân viên -->
<div id="employeeModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <form id="employeeForm" class="p-6 space-y-6">
            <div class="flex justify-between items-center border-b pb-4">
                <h2 class="text-2xl font-bold text-indigo-700" id="modalTitle">Thêm nhân viên mới</h2>
                <button type="button" onclick="closeEmployeeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
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
                    <input type="date" id="NGAY_SINH" name="NGAY_SINH"
                        class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                </div>

                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700">Địa chỉ</label>
                    <input type="text" id="DIA_CHI" name="DIA_CHI"
                        class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                </div>

                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700">Số điện thoại</label>
                    <input type="text" id="SDT" name="SDT" required
                        class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                </div>

                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700">Email</label>
                    <input type="email" id="EMAIL" name="EMAIL" required
                        class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                </div>

                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700">Chuyên môn</label>
                    <input type="text" id="CHUYEN_MON" name="CHUYEN_MON" required
                        class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                </div>

                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700">Vai trò</label>
                    <select id="LOAI_NV" name="LOAI_NV"
                        class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500">
                        <option value="chuyen_trach">Nhân sự thường</option>
                        <option value="quan_ly">Quản lý</option>
                    </select>
                </div>

                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700" id="passwordLabel">Mật khẩu <span class="text-red-500">*</span></label>
                    <input type="password" id="MAT_KHAU" name="MAT_KHAU" required
                        class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                </div>
            </div>

            <div id="formError" class="hidden bg-red-100 text-red-700 px-4 py-3 rounded text-sm"></div>

            <div class="flex justify-between items-center border-t pt-4">
                <button type="button" onclick="closeEmployeeModal()"
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
            <h3 class="text-xl font-bold text-gray-800">Xác nhận xóa nhân viên</h3>
            <p class="text-gray-600">
                Bạn có chắc chắn muốn xóa nhân viên <strong id="deleteEmployeeName"></strong> không? 
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

<script>
// ==================== SMOOTH SCROLL ON PAGE LOAD ====================
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const pageNum = urlParams.get('page_num');
    
    if (pageNum && pageNum !== '1') {
        const employeesTable = document.getElementById('employeesTable');
        if (employeesTable) {
            setTimeout(() => {
                employeesTable.scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'start'
                });
            }, 300);
        }
    }
});

// API base URL
const API_BASE = './api/api_employees.php';
let deleteConfirmData = { id_tk: null, name: null };

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

// ==================== MODAL: THÊM/SỬA NHÂN VIÊN ====================
function openAddEmployeeModal() {
    resetEmployeeForm();
    document.getElementById('modalAction').value = 'create';
    document.getElementById('modalTitle').textContent = 'Thêm nhân viên mới';
    document.getElementById('submitBtnText').textContent = 'Thêm mới';
    document.getElementById('passwordLabel').innerHTML = 'Mật khẩu <span class="text-red-500">*</span>';
    document.getElementById('MAT_KHAU').required = true;
    document.getElementById('idTkField').style.display = 'block';
    document.getElementById('ID_TK').required = true;
    document.getElementById('employeeModal').classList.remove('hidden');
    document.getElementById('employeeModal').classList.add('flex');
}

function openEditEmployeeModal(idTk) {
    const row = document.querySelector(`tr[data-employee-id="${idTk}"]`);
    
    if (!row) {
        showToast('Không tìm thấy dữ liệu nhân viên', 'error');
        return;
    }

    fetch(`${API_BASE}?action=search&limit=1000`)
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.data.employees) {
                throw new Error('Invalid API response');
            }
            const employee = data.data.employees.find(e => e.ID_TK === idTk);
            
            if (employee) {
                document.getElementById('modalAction').value = 'update';
                document.getElementById('modalTitle').textContent = 'Sửa thông tin nhân viên';
                document.getElementById('submitBtnText').textContent = 'Cập nhật';
                document.getElementById('passwordLabel').innerHTML = 'Mật khẩu mới (bỏ trống nếu không đổi)';
                document.getElementById('MAT_KHAU').required = false;
                document.getElementById('idTkField').style.display = 'none';
                
                document.getElementById('modalIdTk').value = employee.ID_TK;
                document.getElementById('ID_TK').value = employee.ID_TK;
                document.getElementById('HO_TEN').value = employee.HO_TEN || '';
                document.getElementById('NGAY_SINH').value = employee.NGAY_SINH || '';
                document.getElementById('DIA_CHI').value = employee.DIA_CHI || '';
                document.getElementById('EMAIL').value = employee.EMAIL || '';
                document.getElementById('SDT').value = employee.SDT || '';
                document.getElementById('CHUYEN_MON').value = employee.CHUYEN_MON || '';
                document.getElementById('LOAI_NV').value = employee.LOAI_NV || 'chuyen_trach';
                document.getElementById('MAT_KHAU').value = '';
                
                document.getElementById('employeeModal').classList.remove('hidden');
                document.getElementById('employeeModal').classList.add('flex');
            } else {
                showToast('Không tìm thấy dữ liệu nhân viên trong hệ thống', 'error');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            showToast('Lỗi khi tải dữ liệu: ' + err.message, 'error');
        });
}

function closeEmployeeModal() {
    document.getElementById('employeeModal').classList.add('hidden');
    document.getElementById('employeeModal').classList.remove('flex');
    resetEmployeeForm();
}

function resetEmployeeForm() {
    document.getElementById('employeeForm').reset();
    document.getElementById('formError').classList.add('hidden');
    document.getElementById('submitBtn').disabled = false;
    document.getElementById('submitBtnSpinner').classList.add('hidden');
}

document.getElementById('employeeForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const action = document.getElementById('modalAction').value;
    const submitBtn = document.getElementById('submitBtn');
    const spinnerEl = document.getElementById('submitBtnSpinner');
    const errorEl = document.getElementById('formError');
    
    submitBtn.disabled = true;
    spinnerEl.classList.remove('hidden');
    errorEl.classList.add('hidden');

    const formData = new FormData(document.getElementById('employeeForm'));
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
            closeEmployeeModal();
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
    document.getElementById('deleteEmployeeName').textContent = name;
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
        closeEmployeeModal();
        closeDeleteModal();
    }
});

// Close modal when clicking outside
document.getElementById('employeeModal').addEventListener('click', (e) => {
    if (e.target === document.getElementById('employeeModal')) {
        closeEmployeeModal();
    }
});

document.getElementById('deleteConfirmModal').addEventListener('click', (e) => {
    if (e.target === document.getElementById('deleteConfirmModal')) {
        closeDeleteModal();
    }
});
</script>
