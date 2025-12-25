<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include '../../database/config.php';

// Get manager's branch ID
$managerBranchId = null;
if (isset($_SESSION['ID_TK'])) {
    $getBranchStmt = $conn->prepare("SELECT nv.ID_CN FROM nhan_vien nv WHERE nv.ID_TK = ? LIMIT 1");
    $getBranchStmt->bind_param("s", $_SESSION['ID_TK']);
    $getBranchStmt->execute();
    $result = $getBranchStmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $managerBranchId = $row['ID_CN'];
    }
}

if (!$managerBranchId) {
    echo "<div class='p-6 bg-red-50 border border-red-200 text-red-700 rounded'>Lỗi: Không tìm thấy chi nhánh của bạn.</div>";
    exit;
}
?>

<div class="max-w-7xl mx-auto px-4 py-6">
    <!-- Page Header with Stats -->
    <div class="mb-8">
        <div class="flex justify-between items-center mb-4">
            <div>
                <h1 class="text-4xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-trash-alt text-red-500 mr-3"></i>Nhân Viên Đã Xóa
                </h1>
                <p class="text-gray-600 text-sm">
                    <i class="fas fa-info-circle mr-2"></i>Xem danh sách nhân viên đã bị xóa mềm. Có thể hoàn tác trong vòng 30 ngày.
                </p>
            </div>
            <a href="manager_dashboard.php?page=employees" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold transition-colors duration-200">
                <i class="fas fa-arrow-left"></i>
                <span>Quay Lại</span>
            </a>
        </div>

        <!-- Stats Card -->
        <div class="bg-gradient-to-r from-red-50 to-orange-50 border-l-4 border-red-500 rounded-lg p-4">
            <div class="flex items-center gap-3">
                <div class="bg-red-100 text-red-600 p-3 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Tổng nhân viên đã xóa</p>
                    <p id="deletedCount" class="text-2xl font-bold text-gray-800">0</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Container -->
    <div id="alertContainer" class="mb-6"></div>

    <!-- Search Bar -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border border-gray-200">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-search text-gray-400 mr-2"></i>Tìm Kiếm
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-3">
                <input 
                    type="text" 
                    id="searchInput" 
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-indigo-500 focus:outline-none transition-colors duration-200" 
                    placeholder="Nhập ID tài khoản, họ tên, hoặc email..."
                >
            </div>
            <div>
                <button id="searchBtn" class="w-full px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold transition-colors duration-200 flex items-center justify-center gap-2">
                    <i class="fas fa-search"></i>
                    <span>Tìm Kiếm</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Table Container -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table id="employeeTable" class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">ID Tài Khoản</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Họ Tên</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Email</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Ngày Xóa</th>
                        <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Hành Động</th>
                    </tr>
                </thead>
                <tbody id="tableBody" class="divide-y divide-gray-200">
                    <tr class="text-center py-8">
                        <td colspan="5" class="px-6 py-8">
                            <div class="flex flex-col items-center justify-center gap-3">
                                <div class="animate-spin">
                                    <i class="fas fa-spinner text-indigo-600 text-2xl"></i>
                                </div>
                                <span class="text-gray-600">Đang tải dữ liệu...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <nav aria-label="Page navigation" class="mt-8 flex justify-center">
        <ul id="pagination" class="flex items-center gap-2">
            <!-- Dynamic pagination -->
        </ul>
    </nav>
</div>

<!-- Restore Confirm Modal -->
<div id="restoreModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-green-50 to-blue-50 border-b-2 border-gray-100 px-6 py-4 flex justify-between items-start">
            <h5 class="text-xl font-bold text-gray-800">
                <i class="fas fa-undo-alt text-green-600 mr-2"></i>Hoàn Tác Xóa Nhân Viên
            </h5>
            <button type="button" class="text-gray-500 hover:text-gray-700 text-xl" onclick="closeRestoreModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6">
            <div class="mb-4 p-4 bg-blue-50 border-l-4 border-blue-500 rounded">
                <p id="restoreMessage" class="text-gray-700 font-semibold"></p>
            </div>
            <div class="p-4 bg-green-50 border-l-4 border-green-500 rounded flex items-start gap-3">
                <i class="fas fa-check-circle text-green-600 text-xl mt-1"></i>
                <div>
                    <p class="font-semibold text-gray-800">Nhân viên sẽ được khôi phục</p>
                    <p class="text-sm text-gray-600 mt-1">Trạng thái sẽ được đặt lại thành ACTIVE và có thể gán công việc ngay lập tức.</p>
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="border-t-2 border-gray-100 bg-gray-50 px-6 py-4 flex justify-end gap-3">
            <button type="button" class="px-6 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg font-semibold transition-colors duration-200" onclick="closeRestoreModal()">
                <i class="fas fa-times mr-2"></i>Hủy
            </button>
            <button type="button" id="confirmRestoreBtn" class="px-6 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-semibold transition-colors duration-200">
                <i class="fas fa-check mr-2"></i>Xác Nhận Hoàn Tác
            </button>
        </div>
    </div>
</div>


<script>
    let currentPage = 1;
    let selectedEmployeeId = null;
    const branchId = <?php echo json_encode($managerBranchId); ?>;
    
    // Modal control functions
    function openRestoreModal(id, name) {
        selectedEmployeeId = id;
        document.getElementById('restoreMessage').innerHTML = 
            `Bạn có chắc muốn khôi phục nhân viên <strong>${name}</strong> không?`;
        document.getElementById('restoreModal').style.display = 'flex';
    }
    
    function closeRestoreModal() {
        document.getElementById('restoreModal').style.display = 'none';
        selectedEmployeeId = null;
    }
    
    // Close modal when clicking outside
    document.getElementById('restoreModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRestoreModal();
        }
    });

    // Load deleted employees
    function loadDeletedEmployees(page = 1, search = '') {
        const params = new URLSearchParams({
            action: 'get_deleted',
            page: page,
            search: search,
            branch_id: branchId
        });

        fetch(`api/api_employees.php?${params}`, {
            credentials: 'include'
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentPage = page;
                    renderTable(data.data.employees);
                    renderPagination(data.data.pagination);
                    document.getElementById('deletedCount').textContent = data.data.pagination.total || 0;
                } else {
                    showAlert('danger', data.message);
                    document.getElementById('tableBody').innerHTML = 
                        '<tr class="text-center"><td colspan="5" class="text-muted px-6 py-8">Không có dữ liệu</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Lỗi khi tải dữ liệu');
            });
    }

    // Render table
    function renderTable(employees) {
        const tableBody = document.getElementById('tableBody');
        
        if (!employees || employees.length === 0) {
            tableBody.innerHTML = `
                <tr class="text-center">
                    <td colspan="5" class="px-6 py-8">
                        <div class="flex flex-col items-center justify-center gap-3">
                            <i class="fas fa-inbox text-gray-400 text-4xl"></i>
                            <span class="text-gray-600">Không có nhân viên đã xóa</span>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = employees.map(emp => `
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium bg-gray-100 text-gray-800">
                        ${emp.ID_TK}
                    </span>
                </td>
                <td class="px-6 py-4 font-semibold text-gray-800">${emp.HO_TEN || 'N/A'}</td>
                <td class="px-6 py-4 text-gray-600">${emp.EMAIL || 'N/A'}</td>
                <td class="px-6 py-4 text-gray-600">
                    <i class="fas fa-calendar-alt text-gray-400 mr-2"></i>${new Date(emp.DELETED_AT).toLocaleDateString('vi-VN')}
                </td>
                <td class="px-6 py-4 text-center">
                    <button onclick="openRestoreModal('${emp.ID_TK}', '${emp.HO_TEN}')" class="inline-flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-semibold transition-colors duration-200">
                        <i class="fas fa-undo"></i>
                        Khôi Phục
                    </button>
                </td>
            </tr>
        `).join('');
    }

    // Render pagination
    function renderPagination(pagination) {
        const paginationEl = document.getElementById('pagination');
        paginationEl.innerHTML = '';

        if (pagination.pages <= 1) return;

        // Previous button
        const prevBtn = document.createElement('li');
        prevBtn.className = `${pagination.page === 1 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:bg-gray-100'}`;
        prevBtn.innerHTML = `
            <a class="px-4 py-2 rounded-lg inline-flex items-center gap-2 font-semibold transition-colors" href="#" onclick="event.preventDefault(); ${pagination.page > 1 ? `loadDeletedEmployees(${pagination.page - 1}, '${document.getElementById('searchInput').value}')` : ''}" ${pagination.page === 1 ? 'disabled' : ''}>
                <i class="fas fa-chevron-left"></i>
                <span>Trước</span>
            </a>
        `;
        paginationEl.appendChild(prevBtn);

        // Page info
        const infoSpan = document.createElement('span');
        infoSpan.className = 'px-4 py-2 text-gray-600 font-semibold';
        infoSpan.textContent = `${pagination.page} / ${pagination.pages}`;
        paginationEl.appendChild(infoSpan);

        // Next button
        const nextBtn = document.createElement('li');
        nextBtn.className = `${pagination.page === pagination.pages ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:bg-gray-100'}`;
        nextBtn.innerHTML = `
            <a class="px-4 py-2 rounded-lg inline-flex items-center gap-2 font-semibold transition-colors" href="#" onclick="event.preventDefault(); ${pagination.page < pagination.pages ? `loadDeletedEmployees(${pagination.page + 1}, '${document.getElementById('searchInput').value}')` : ''}" ${pagination.page === pagination.pages ? 'disabled' : ''}>
                <span>Tiếp</span>
                <i class="fas fa-chevron-right"></i>
            </a>
        `;
        paginationEl.appendChild(nextBtn);
    }

    // Confirm restore
    document.getElementById('confirmRestoreBtn').addEventListener('click', () => {
        if (!selectedEmployeeId) return;

        const formData = new FormData();
        formData.append('action', 'restore');
        formData.append('ID_TK', selectedEmployeeId);

        fetch('api/api_employees.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            closeRestoreModal();
            if (data.success) {
                showAlert('success', data.message);
                setTimeout(() => {
                    loadDeletedEmployees(currentPage, document.getElementById('searchInput').value);
                }, 1000);
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            closeRestoreModal();
            showAlert('danger', 'Lỗi khi hoàn tác xóa');
        });
    });

    // Search functionality
    document.getElementById('searchBtn').addEventListener('click', () => {
        const search = document.getElementById('searchInput').value;
        loadDeletedEmployees(1, search);
    });

    document.getElementById('searchInput').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            document.getElementById('searchBtn').click();
        }
    });

    // Show alert
    function showAlert(type, message) {
        const bgColor = type === 'success' ? 'bg-green-50 border-l-4 border-green-500' : 'bg-red-50 border-l-4 border-red-500';
        const textColor = type === 'success' ? 'text-green-800' : 'text-red-800';
        const icon = type === 'success' ? 'fa-check-circle text-green-600' : 'fa-exclamation-circle text-red-600';
        
        const alertHtml = `
            <div class="p-4 rounded-lg ${bgColor} ${textColor} flex items-start justify-between" role="alert">
                <div class="flex items-start gap-3">
                    <i class="fas ${icon} text-xl mt-0.5"></i>
                    <span class="font-semibold">${message}</span>
                </div>
                <button type="button" class="ml-4 text-lg opacity-70 hover:opacity-100 transition-opacity">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        const alertDiv = document.createElement('div');
        alertDiv.className = 'fixed top-4 right-4 z-50 max-w-md animate-fadeIn';
        alertDiv.innerHTML = alertHtml;
        document.body.appendChild(alertDiv);
        
        // Close button handler
        alertDiv.querySelector('button').addEventListener('click', () => {
            alertDiv.style.opacity = '0';
            alertDiv.style.transition = 'opacity 0.3s ease-out';
            setTimeout(() => alertDiv.remove(), 300);
        });
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentElement) {
                alertDiv.style.opacity = '0';
                alertDiv.style.transition = 'opacity 0.3s ease-out';
                setTimeout(() => alertDiv.remove(), 300);
            }
        }, 5000);
    }

    // Initial load
    loadDeletedEmployees(1);
</script>

<style>
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .animate-fadeIn {
        animation: fadeIn 0.3s ease-out;
    }
</style>
