<?php
/**
 * DEPRECATED: This page is no longer needed.
 * 
 * The expense category management has been consolidated with the main expense management system.
 * Expense categories are now managed through the existing 'loai_chi_phi' table shared across
 * the entire system, not branch-specific categories.
 * 
 * Please use:
 * - manager_expenses_v2.php for the main expense management dashboard
 * - The existing category management in manage_finances.php for expense category administration
 * 
 * This file remains for reference but should not be used in production.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../database/config.php';
require_once __DIR__ . '/../../helpers/assets.php';

$idTk = $_SESSION['ID_TK'] ?? null;

if (!$idTk) {
    die("<p class='text-red-600'>Lỗi: Bạn cần đăng nhập.</p>");
}

// Lấy thông tin chi nhánh
$branchStmt = $conn->prepare("SELECT nv.ID_CN, cn.TEN_CN FROM nhan_vien nv JOIN chi_nhanh cn ON cn.ID_CN = nv.ID_CN WHERE nv.ID_TK = ?");
$branchStmt->bind_param('i', $idTk);
$branchStmt->execute();
$branchData = $branchStmt->get_result()->fetch_assoc();

if (!$branchData) {
    die("<p class='text-red-600'>Lỗi: Không xác định được chi nhánh!</p>");
}

$idCn = (int)$branchData['ID_CN'];
$branchName = $branchData['TEN_CN'] ?? 'Chi nhánh';

// Lấy danh sách loại chi phí
$categoryStmt = $conn->prepare("SELECT ID_LOAI_CP, TEN_LOAI_CP, ICON_LOAI_CP, MO_TA, THU_TU_HIEN_THI FROM loai_chi_phi_branch WHERE ID_CN = ? ORDER BY THU_TU_HIEN_THI ASC");
$categoryStmt->bind_param('i', $idCn);
$categoryStmt->execute();
$categoryResult = $categoryStmt->get_result();
$categories = [];
while ($row = $categoryResult->fetch_assoc()) {
    $categories[] = $row;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Loại Chi Phí</title>
    <?= sb_tailwind_link_tag(); ?>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen py-8 px-4">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <p class="text-sm uppercase tracking-widest text-slate-500 font-semibold">⚙️ Cấu hình</p>
            <h1 class="text-4xl font-bold text-slate-900 mt-2">Quản lý loại chi phí</h1>
            <p class="text-slate-600 mt-2">Chi nhánh: <span class="font-semibold"><?= htmlspecialchars($branchName) ?></span></p>
        </div>

        <!-- Add Category Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-8">
            <h2 class="text-xl font-bold text-slate-900 mb-6">➕ Thêm loại chi phí mới</h2>

            <form id="addCategoryForm" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-slate-600 mb-2">Tên loại chi phí *</label>
                        <input 
                            type="text" 
                            id="categoryName" 
                            name="name" 
                            required
                            class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                            placeholder="Ví dụ: Chi phí nhân viên">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-600 mb-2">Biểu tượng</label>
                        <input 
                            type="text" 
                            id="categoryIcon" 
                            name="icon" 
                            maxlength="2"
                            class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                            placeholder="👥"
                            value="📌">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-600 mb-2">Mô tả (không bắt buộc)</label>
                    <textarea 
                        id="categoryDescription" 
                        name="description"
                        rows="3"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                        placeholder="Mô tả chi tiết về loại chi phí này..."></textarea>
                </div>

                <button 
                    type="submit"
                    class="w-full rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-700 transition shadow-md">
                    ➕ Thêm loại chi phí
                </button>
            </form>
        </div>

        <!-- Categories List -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h2 class="text-xl font-bold text-slate-900 mb-6">📂 Danh sách loại chi phí</h2>

            <?php if (empty($categories)): ?>
                <div class="text-center py-8">
                    <p class="text-slate-500">Chưa có loại chi phí nào</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($categories as $index => $category): ?>
                        <div class="rounded-lg border border-slate-200 bg-gradient-to-r from-slate-50 to-transparent p-4 flex items-center justify-between hover:bg-slate-50 transition" data-category-id="<?= (int)$category['ID_LOAI_CP'] ?>">
                            <div class="flex items-start gap-4 flex-1">
                                <div class="text-3xl"><?= htmlspecialchars($category['ICON_LOAI_CP']) ?></div>
                                <div>
                                    <p class="font-bold text-slate-900"><?= htmlspecialchars($category['TEN_LOAI_CP']) ?></p>
                                    <?php if (!empty($category['MO_TA'])): ?>
                                        <p class="text-sm text-slate-600 mt-1"><?= htmlspecialchars($category['MO_TA']) ?></p>
                                    <?php endif; ?>
                                    <p class="text-xs text-slate-500 mt-2">Thứ tự: #<?= (int)$category['THU_TU_HIEN_THI'] ?></p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button 
                                    onclick="editCategory(<?= (int)$category['ID_LOAI_CP'] ?>)"
                                    class="rounded-lg bg-indigo-100 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-200 transition">
                                    ✏️ Sửa
                                </button>
                                <button 
                                    onclick="deleteCategory(<?= (int)$category['ID_LOAI_CP'] ?>)"
                                    class="rounded-lg bg-red-100 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-200 transition">
                                    🗑️ Xóa
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black/50 hidden flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
            <h2 class="text-2xl font-bold text-slate-900 mb-4">Sửa loại chi phí</h2>
            
            <form id="editForm" class="space-y-4">
                <input type="hidden" id="editCategoryId" name="id">

                <div>
                    <label class="block text-sm font-semibold text-slate-600 mb-2">Tên loại chi phí *</label>
                    <input 
                        type="text" 
                        id="editCategoryName" 
                        name="name" 
                        required
                        class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-600 mb-2">Biểu tượng</label>
                    <input 
                        type="text" 
                        id="editCategoryIcon" 
                        name="icon" 
                        maxlength="2"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-600 mb-2">Mô tả (không bắt buộc)</label>
                    <textarea 
                        id="editCategoryDescription" 
                        name="description"
                        rows="3"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-600 mb-2">Thứ tự hiển thị</label>
                    <input 
                        type="number" 
                        id="editCategoryOrder" 
                        name="order" 
                        min="1"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                </div>

                <div class="flex gap-3 pt-4">
                    <button 
                        type="button" 
                        onclick="closeEditModal()"
                        class="flex-1 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Hủy
                    </button>
                    <button 
                        type="submit"
                        class="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition">
                        Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const API_URL = './components/api_expense_categories.php';
        let categories = <?= json_encode($categories) ?>;

        // Add Category
        document.getElementById('addCategoryForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const data = {
                name: document.getElementById('categoryName').value,
                icon: document.getElementById('categoryIcon').value || '📌',
                description: document.getElementById('categoryDescription').value
            };

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Lỗi: ' + result.error);
                }
            } catch (error) {
                alert('Lỗi: ' + error.message);
            }
        });

        // Edit Category
        async function editCategory(id) {
            const category = categories.find(c => c.ID_LOAI_CP == id);
            if (!category) return;

            document.getElementById('editCategoryId').value = id;
            document.getElementById('editCategoryName').value = category.TEN_LOAI_CP;
            document.getElementById('editCategoryIcon').value = category.ICON_LOAI_CP;
            document.getElementById('editCategoryDescription').value = category.MO_TA || '';
            document.getElementById('editCategoryOrder').value = category.THU_TU_HIEN_THI || 999;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        document.getElementById('editForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const id = document.getElementById('editCategoryId').value;
            const data = {
                id: parseInt(id),
                name: document.getElementById('editCategoryName').value,
                icon: document.getElementById('editCategoryIcon').value,
                description: document.getElementById('editCategoryDescription').value,
                order: parseInt(document.getElementById('editCategoryOrder').value) || 999
            };

            try {
                const response = await fetch(API_URL, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Lỗi: ' + result.error);
                }
            } catch (error) {
                alert('Lỗi: ' + error.message);
            }
        });

        // Delete Category
        async function deleteCategory(id) {
            if (!confirm('Bạn có chắc chắn muốn xóa loại chi phí này? Chi phí hiện tại sẽ được đánh dấu là "Chưa phân loại"')) return;

            try {
                const response = await fetch(API_URL, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });

                const result = await response.json();
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Lỗi: ' + result.error);
                }
            } catch (error) {
                alert('Lỗi: ' + error.message);
            }
        }

        // Close modal on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeEditModal();
        });
    </script>
</body>
</html>
