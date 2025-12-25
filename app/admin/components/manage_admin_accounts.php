<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include '../../database/config.php';

$defaultLimit = 10;
$allowedPageSizes = [10, 20, 30];
$perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : $defaultLimit;
$perPage = in_array($perPage, $allowedPageSizes, true) ? $perPage : $defaultLimit;
$limit = $perPage;
$page = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get all admin accounts
function getAdminAccounts($search = "", $limit = 10, $offset = 0)
{
    global $conn;
    $query = "
    SELECT ID_TK, HO_TEN, EMAIL
    FROM tai_khoan
    WHERE ID_QUYEN = 1
    ";

    if (!empty($search)) {
        $search = mysqli_real_escape_string($conn, $search);
        $query .= " AND (ID_TK LIKE '%$search%' 
                    OR HO_TEN LIKE '%$search%' 
                    OR EMAIL LIKE '%$search%')";
    }

    $query .= " ORDER BY ID_TK DESC LIMIT $limit OFFSET $offset";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        error_log('Database error in getAdminAccounts: ' . mysqli_error($conn));
        return false;
    }
    
    return $result;
}

// Count admin accounts
function countAdminAccounts($search = "")
{
    global $conn;
    $query = "SELECT COUNT(*) as total FROM tai_khoan WHERE ID_QUYEN = 1";

    if (!empty($search)) {
        $search = mysqli_real_escape_string($conn, $search);
        $query .= " AND (ID_TK LIKE '%$search%'
                    OR HO_TEN LIKE '%$search%' 
                    OR EMAIL LIKE '%$search%')";
    }

    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        error_log('Database error in countAdminAccounts: ' . mysqli_error($conn));
        return 0;
    }
    
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

$admins = getAdminAccounts($search, $limit, $offset);
$totalRecords = countAdminAccounts($search);
$totalPages = max(1, ceil($totalRecords / $limit));
$showingStart = $totalRecords ? $offset + 1 : 0;
$showingEnd = min($offset + $limit, $totalRecords);
?>

<body class="bg-gray-100 p-6">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-extrabold text-indigo-700">Quản lý tài khoản Admin</h1>
            <a href="admin_dashboard.php?page=employees" class="inline-flex items-center gap-2 px-3 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg text-sm font-semibold transition-colors duration-200">
                <i class="fas fa-arrow-left"></i>
                <span>Quay lại</span>
            </a>
        </div>

        <!-- Thông báo -->
        <?php if (isset($successMessage)): ?>
            <div class="bg-green-100 text-green-800 px-4 py-3 rounded mb-6 shadow"><?= $successMessage ?></div>
        <?php endif; ?>
        <?php if (isset($errorMessage)): ?>
            <div class="bg-red-100 text-red-700 px-4 py-3 rounded mb-6 shadow"><?= $errorMessage ?></div>
        <?php endif; ?>

        <!-- Tóm tắt Admin -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Tổng tài khoản Admin</p>
                <p class="text-3xl font-bold text-indigo-700 mt-2"><?= number_format($totalRecords) ?></p>
                <p class="text-sm text-gray-500 mt-1">Tài khoản quản trị hệ thống.</p>
            </div>
            <div class="bg-white rounded-xl shadow p-4">
                <p class="text-sm font-semibold text-gray-500 mb-1">Tùy chọn nhanh</p>
                <p class="text-lg font-bold text-gray-800">Tạo Admin mới</p>
                <button onclick="openAddAdminModal()"
                    class="inline-flex items-center justify-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold shadow mt-3 w-full">
                    Thêm Admin
                </button>
            </div>
        </div>

        <!-- Bộ lọc + Tìm kiếm -->
        <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
            <form action="admin_dashboard.php" method="GET" class="space-y-4">
                <input type="hidden" name="page" value="admin_accounts">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col">
                        <label for="search" class="text-sm font-medium text-gray-700 mb-1">Tìm kiếm</label>
                        <input type="text" id="search" name="search" placeholder="Nhập ID, tên hoặc email"
                            value="<?= htmlspecialchars($search) ?>"
                            class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500 shadow-sm">
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
                <div class="flex flex-wrap gap-3 justify-end pt-4 border-t border-gray-100">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg shadow">Áp dụng</button>
                    <a href="?page=admin_accounts" class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg shadow hover:bg-gray-50">Đặt lại</a>
                </div>
            </form>
        </div>

        <div class="flex flex-wrap justify-between items-center text-sm text-gray-600 bg-white px-4 py-3 rounded-xl shadow mb-6">
            <p>Hiển thị <?= $showingStart ?> - <?= $showingEnd ?> trên tổng <?= $totalRecords ?> tài khoản</p>
            <p>Trang <?= $page ?> / <?= $totalPages ?></p>
        </div>

        <!-- Danh sách Admin -->
        <div id="adminTable" class="overflow-x-auto bg-white rounded-xl shadow-lg">
            <table class="min-w-full table-auto text-sm">
                <thead class="bg-indigo-100 text-indigo-700">
                    <tr>
                        <th class="px-4 py-3">ID Tài khoản</th>
                        <th class="px-4 py-3">Tên Admin</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Hành động</th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    <?php if ($admins && mysqli_num_rows($admins) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($admins)): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-semibold text-gray-800"><?= htmlspecialchars($row['ID_TK']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($row['HO_TEN']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($row['EMAIL']) ?></td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-center gap-2">
                                        <button onclick="openEditAdminModal('<?= htmlspecialchars($row['ID_TK']) ?>')"
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
                            <td colspan="5" class="text-red-600 font-bold py-6">Không tìm thấy tài khoản admin nào.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php
        echo '<div class="mt-6 flex flex-wrap justify-center gap-2" id="paginationContainer">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $queryString = http_build_query([
                'page' => 'admin_accounts',
                'page_num' => $i,
                'search' => $search,
                'per_page' => $perPage,
            ]);
            $active = ($i == $page) ? 'bg-indigo-600 text-white' : 'bg-gray-200 hover:bg-gray-300';
            echo "<a href='?$queryString' class='px-3 py-1 rounded $active pagination-link' data-page='$i'>$i</a>";
        }
        echo '</div>';
        ?>

        <!-- Modal: Thêm/Sửa Admin -->
        <div id="adminModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                <form id="adminForm" class="p-6 space-y-6">
                    <div class="flex justify-between items-center border-b pb-4">
                        <h2 class="text-2xl font-bold text-indigo-700" id="modalTitle">Thêm Admin mới</h2>
                        <button type="button" onclick="closeAdminModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                    </div>

                    <input type="hidden" id="modalAction" name="action" value="create">
                    <input type="hidden" id="modalIdTk" name="ID_TK">

                    <div class="space-y-4">
                        <div id="idTkField">
                            <label class="block mb-1 text-sm font-medium text-gray-700">ID Tài khoản</label>
                            <input type="text" id="ID_TK" name="ID_TK" required
                                class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                        </div>

                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">Tên Admin</label>
                            <input type="text" id="HO_TEN" name="HO_TEN" required
                                class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                        </div>

                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">Email</label>
                            <input type="email" id="EMAIL" name="EMAIL" required
                                class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                        </div>

                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700" id="passwordLabel">Mật khẩu</label>
                            <input type="password" id="MAT_KHAU" name="MAT_KHAU"
                                class="w-full border rounded px-4 py-2 shadow-sm focus:ring-indigo-500" />
                        </div>
                    </div>

                    <div id="formError" class="hidden bg-red-100 text-red-700 px-4 py-3 rounded"></div>

                    <div class="flex justify-between items-center border-t pt-4">
                        <button type="button" onclick="closeAdminModal()"
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
                    <h3 class="text-xl font-bold text-gray-800">Xác nhận xóa Admin</h3>
                    <p class="text-gray-600">
                        Bạn có chắc chắn muốn xóa admin <strong id="deleteAdminName"></strong> không? 
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
const API_BASE = './api/api_admin_accounts.php';
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

// ==================== MODAL: THÊM/SỬA ADMIN ====================
function openAddAdminModal() {
    resetAdminForm();
    document.getElementById('modalAction').value = 'create';
    document.getElementById('modalTitle').textContent = 'Thêm Admin mới';
    document.getElementById('submitBtnText').textContent = 'Thêm mới';
    document.getElementById('passwordLabel').innerHTML = 'Mật khẩu <span class="text-red-500">*</span>';
    document.getElementById('MAT_KHAU').required = true;
    document.getElementById('idTkField').style.display = 'block';
    document.getElementById('ID_TK').required = true;
    document.getElementById('adminModal').classList.remove('hidden');
    document.getElementById('adminModal').classList.add('flex');
}

function openEditAdminModal(idTk) {
    fetch(`${API_BASE}?action=search`, {
        credentials: 'include'
    })
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.data.admins) {
                throw new Error('Invalid API response');
            }
            const admin = data.data.admins.find(a => a.ID_TK === idTk);
            
            if (admin) {
                document.getElementById('modalAction').value = 'update';
                document.getElementById('modalTitle').textContent = 'Sửa thông tin Admin';
                document.getElementById('submitBtnText').textContent = 'Cập nhật';
                document.getElementById('passwordLabel').innerHTML = 'Mật khẩu mới (bỏ trống nếu không đổi)';
                document.getElementById('MAT_KHAU').required = false;
                document.getElementById('idTkField').style.display = 'none';
                
                document.getElementById('modalIdTk').value = admin.ID_TK;
                document.getElementById('ID_TK').value = admin.ID_TK;
                document.getElementById('HO_TEN').value = admin.HO_TEN || '';
                document.getElementById('EMAIL').value = admin.EMAIL || '';
                document.getElementById('MAT_KHAU').value = '';
                
                document.getElementById('adminModal').classList.remove('hidden');
                document.getElementById('adminModal').classList.add('flex');
            } else {
                showToast('Không tìm thấy dữ liệu admin', 'error');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            showToast('Lỗi khi tải dữ liệu: ' + err.message, 'error');
        });
}

function closeAdminModal() {
    document.getElementById('adminModal').classList.add('hidden');
    document.getElementById('adminModal').classList.remove('flex');
    resetAdminForm();
}

function resetAdminForm() {
    document.getElementById('adminForm').reset();
    document.getElementById('formError').classList.add('hidden');
    document.getElementById('submitBtn').disabled = false;
    document.getElementById('submitBtnSpinner').classList.add('hidden');
}

document.getElementById('adminForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const action = document.getElementById('modalAction').value;
    const submitBtn = document.getElementById('submitBtn');
    const spinnerEl = document.getElementById('submitBtnSpinner');
    const errorEl = document.getElementById('formError');
    
    submitBtn.disabled = true;
    spinnerEl.classList.remove('hidden');
    errorEl.classList.add('hidden');

    const formData = new FormData(document.getElementById('adminForm'));
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
            closeAdminModal();
            setTimeout(() => location.reload(), 3000);
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
    document.getElementById('deleteAdminName').textContent = name;
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
        closeAdminModal();
        closeDeleteModal();
    }
});

// Close modal when clicking outside
document.getElementById('adminModal').addEventListener('click', (e) => {
    if (e.target === document.getElementById('adminModal')) {
        closeAdminModal();
    }
});

document.getElementById('deleteConfirmModal').addEventListener('click', (e) => {
    if (e.target === document.getElementById('deleteConfirmModal')) {
        closeDeleteModal();
    }
});
</script>
