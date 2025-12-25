/**
 * Page-Specific Integration: manage-services.js
 * Handles service CRUD operations, categories, and tags management
 * Date: December 3, 2025
 */

// ============================================================
// 1. SERVICE CRUD HANDLERS
// ============================================================

/**
 * Handle add service form submission
 */
async function handleAddServiceSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const tenDv = form.querySelector('input[name="TEN_DV"]')?.value?.trim();
    const motaDv = form.querySelector('textarea[name="MOTA_DV"]')?.value?.trim();
    const donGiaInput = form.querySelector('input[name="GIA"]') || form.querySelector('input[name="DON_GIA"]');
    const donGia = donGiaInput?.value?.trim();
    const thoiGian = form.querySelector('input[name="THOI_GIAN"]')?.value?.trim();
    const idDanhMuc = form.querySelector('select[name="ID_DANH_MUC"]')?.value;
    const status = form.querySelector('select[name="TRANG_THAI"]')?.value || 'draft';

    // Validation
    if (!tenDv) {
        showNotification('Vui lòng nhập tên dịch vụ', 'error');
        return;
    }
    if (!motaDv) {
        showNotification('Vui lòng nhập mô tả dịch vụ', 'error');
        return;
    }
    if (!donGia || parseInt(donGia) <= 0) {
        showNotification('Vui lòng nhập giá hợp lệ', 'error');
        return;
    }
    if (!thoiGian || parseInt(thoiGian) <= 0) {
        showNotification('Vui lòng nhập thời gian hợp lệ', 'error');
        return;
    }

    let spinner;
    try {
        spinner = showSpinner('Đang thêm dịch vụ...');
        const formData = new FormData(form);
        formData.append('add_service', '1');

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (response.ok) {
            showNotification('Thêm dịch vụ thành công', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Lỗi khi thêm dịch vụ', 'error');
        }
    } catch (error) {
        console.error('Error adding service:', error);
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        if (spinner && typeof spinner.close === 'function') {
            spinner.close();
        }
    }
}

/**
 * Handle edit service form submission
 */
async function handleEditServiceSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const tenDv = form.querySelector('input[name="TEN_DV"]')?.value?.trim();
    const motaDv = form.querySelector('textarea[name="MOTA_DV"]')?.value?.trim();
    const donGiaInput = form.querySelector('input[name="GIA"]') || form.querySelector('input[name="DON_GIA"]');
    const donGia = donGiaInput?.value?.trim();
    const thoiGian = form.querySelector('input[name="THOI_GIAN"]')?.value?.trim();
    const idDanhMuc = form.querySelector('select[name="ID_DANH_MUC"]')?.value;
    const status = form.querySelector('select[name="TRANG_THAI"]')?.value || 'draft';

    // Validation
    if (!tenDv) {
        showNotification('Vui lòng nhập tên dịch vụ', 'error');
        return;
    }
    if (!motaDv) {
        showNotification('Vui lòng nhập mô tả dịch vụ', 'error');
        return;
    }
    if (!donGia || parseInt(donGia) <= 0) {
        showNotification('Vui lòng nhập giá hợp lệ', 'error');
        return;
    }
    if (!thoiGian || parseInt(thoiGian) <= 0) {
        showNotification('Vui lòng nhập thời gian hợp lệ', 'error');
        return;
    }

    let spinner;
    try {
        spinner = showSpinner('Đang cập nhật dịch vụ...');
        const formData = new FormData(form);
        formData.append('edit_service', '1');

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (response.ok) {
            showNotification('Cập nhật dịch vụ thành công', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Lỗi khi cập nhật dịch vụ', 'error');
        }
    } catch (error) {
        console.error('Error updating service:', error);
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        if (spinner && typeof spinner.close === 'function') {
            spinner.close();
        }
    }
}

/**
 * Delete service with confirmation
 */
async function deleteService(serviceId, serviceName) {
    if (!showConfirm(`Xác nhận xóa dịch vụ "${serviceName}"?`)) {
        return;
    }

    let spinner;
    try {
        spinner = showSpinner('Đang xóa dịch vụ...');
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: new URLSearchParams({
                'delete_service': serviceId
            })
        });

        if (response.ok) {
            showNotification('Xóa dịch vụ thành công', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Lỗi khi xóa dịch vụ', 'error');
        }
    } catch (error) {
        console.error('Error deleting service:', error);
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        if (spinner && typeof spinner.close === 'function') {
            spinner.close();
        }
    }
}

/**
 * Change service status
 */
async function changeServiceStatus(serviceId, newStatus) {
    let spinner;
    try {
        spinner = showSpinner('Đang thay đổi trạng thái...');
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: new URLSearchParams({
                'change_status': serviceId,
                'new_status': newStatus
            })
        });

        if (response.ok) {
            showNotification('Thay đổi trạng thái thành công', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Lỗi khi thay đổi trạng thái', 'error');
        }
    } catch (error) {
        console.error('Error changing status:', error);
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        if (spinner && typeof spinner.close === 'function') {
            spinner.close();
        }
    }
}

// ============================================================
// 2. CATEGORY MANAGEMENT (DANH MỤC)
// ============================================================

/**
 * Add category (Danh mục)
 */
async function handleAddCategorySubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const tenDanhMuc = form.querySelector('input[name="TEN_DANH_MUC"]')?.value?.trim();

    if (!tenDanhMuc) {
        showNotification('Vui lòng nhập tên danh mục', 'error');
        return;
    }

    let spinner;
    try {
        spinner = showSpinner('Đang thêm danh mục...');
        const formData = new FormData(form);
        formData.append('add_category', '1');

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (response.ok) {
            showNotification('Thêm danh mục thành công', 'success');
            form.reset();
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Lỗi khi thêm danh mục', 'error');
        }
    } catch (error) {
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        if (spinner && typeof spinner.close === 'function') {
            spinner.close();
        }
    }
}

/**
 * Edit category (Danh mục)
 */
async function handleEditCategorySubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const tenDanhMuc = form.querySelector('input[name="TEN_DANH_MUC"]')?.value?.trim();

    if (!tenDanhMuc) {
        showNotification('Vui lòng nhập tên danh mục', 'error');
        return;
    }

    let spinner;
    try {
        spinner = showSpinner('Đang cập nhật danh mục...');
        const formData = new FormData(form);
        formData.append('edit_category', '1');

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (response.ok) {
            showNotification('Cập nhật danh mục thành công', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Lỗi khi cập nhật danh mục', 'error');
        }
    } catch (error) {
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        if (spinner && typeof spinner.close === 'function') {
            spinner.close();
        }
    }
}

/**
 * Delete category (Danh mục)
 */
async function deleteCategory(categoryId, categoryName) {
    if (!showConfirm(`Xác nhận xóa danh mục "${categoryName}"?`)) {
        return;
    }

    let spinner;
    try {
        spinner = showSpinner('Đang xóa danh mục...');
        const response = await fetch(window.location.href, {
            method: 'GET',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
        });

        // Using GET with query param
        const url = new URL(window.location.href);
        url.searchParams.set('delete_category', categoryId);
        
        const deleteResponse = await fetch(url.toString());
        
        if (deleteResponse.ok) {
            showNotification('Xóa danh mục thành công', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Lỗi khi xóa danh mục', 'error');
        }
    } catch (error) {
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        if (spinner && typeof spinner.close === 'function') {
            spinner.close();
        }
    }
}

// ============================================================
// 3. TAG MANAGEMENT (THẺ)
// ============================================================

/**
 * Add tag (Thẻ)
 */
async function handleAddTagSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const tenThe = form.querySelector('input[name="TEN_THE"]')?.value?.trim();
    const mauSac = form.querySelector('input[name="MAU_SAC"]')?.value || '#3B82F6';

    if (!tenThe) {
        showNotification('Vui lòng nhập tên thẻ', 'error');
        return;
    }

    let spinner;
    try {
        spinner = showSpinner('Đang thêm thẻ...');
        const formData = new FormData(form);
        formData.append('add_tag', '1');

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (response.ok) {
            showNotification('Thêm thẻ thành công', 'success');
            form.reset();
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Lỗi khi thêm thẻ', 'error');
        }
    } catch (error) {
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        if (spinner && typeof spinner.close === 'function') {
            spinner.close();
        }
    }
}

/**
 * Edit tag (Thẻ)
 */
async function handleEditTagSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const tenThe = form.querySelector('input[name="TEN_THE"]')?.value?.trim();
    const mauSac = form.querySelector('input[name="MAU_SAC"]')?.value || '#3B82F6';

    if (!tenThe) {
        showNotification('Vui lòng nhập tên thẻ', 'error');
        return;
    }

    let spinner;
    try {
        spinner = showSpinner('Đang cập nhật thẻ...');
        const formData = new FormData(form);
        formData.append('edit_tag', '1');

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (response.ok) {
            showNotification('Cập nhật thẻ thành công', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Lỗi khi cập nhật thẻ', 'error');
        }
    } catch (error) {
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        if (spinner && typeof spinner.close === 'function') {
            spinner.close();
        }
    }
}

/**
 * Delete tag (Thẻ)
 */
async function deleteTag(tagId, tagName) {
    if (!showConfirm(`Xác nhận xóa thẻ "${tagName}"?`)) {
        return;
    }

    let spinner;
    try {
        spinner = showSpinner('Đang xóa thẻ...');
        const url = new URL(window.location.href);
        url.searchParams.set('delete_tag', tagId);
        
        const response = await fetch(url.toString());

        if (response.ok) {
            showNotification('Xóa thẻ thành công', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Lỗi khi xóa thẻ', 'error');
        }
    } catch (error) {
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        if (spinner && typeof spinner.close === 'function') {
            spinner.close();
        }
    }
}

// ============================================================
// 4. SEARCH & FILTER
// ============================================================

/**
 * Handle real-time search with debounce
 */
let searchTimeout;
function handleServiceSearch(e) {
    clearTimeout(searchTimeout);
    const searchTerm = e.target.value.trim();

    searchTimeout = setTimeout(() => {
        const form = e.target.closest('form');
        if (form) {
            form.submit();
        }
    }, 500);
}

// ============================================================
// 5. PAGE INITIALIZATION
// ============================================================

/**
 * Initialize page on load
 */
document.addEventListener('DOMContentLoaded', () => {
    // Setup add service form
    const addServiceForm = document.querySelector('form[data-action="add_service"]');
    if (addServiceForm) {
        addServiceForm.addEventListener('submit', handleAddServiceSubmit);
    }

    // Setup edit service form
    const editServiceForm = document.querySelector('form[data-action="edit_service"]');
    if (editServiceForm) {
        editServiceForm.addEventListener('submit', handleEditServiceSubmit);
    }

    // Setup delete service buttons
    document.querySelectorAll('[data-action="delete_service"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const serviceId = btn.getAttribute('data-service-id');
            const serviceName = btn.getAttribute('data-service-name');
            deleteService(serviceId, serviceName);
        });
    });

    // Setup change status buttons
    document.querySelectorAll('[data-action="change_status"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const serviceId = btn.getAttribute('data-service-id');
            const newStatus = btn.getAttribute('data-status');
            changeServiceStatus(serviceId, newStatus);
        });
    });

    // Setup category forms
    const addCategoryForm = document.querySelector('form[data-action="add_category"]');
    if (addCategoryForm) {
        addCategoryForm.addEventListener('submit', handleAddCategorySubmit);
    }

    const editCategoryForm = document.querySelector('form[data-action="edit_category"]');
    if (editCategoryForm) {
        editCategoryForm.addEventListener('submit', handleEditCategorySubmit);
    }

    document.querySelectorAll('[data-action="delete_category"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const categoryId = btn.getAttribute('data-category-id');
            const categoryName = btn.getAttribute('data-category-name');
            deleteCategory(categoryId, categoryName);
        });
    });

    // Setup tag forms
    const addTagForm = document.querySelector('form[data-action="add_tag"]');
    if (addTagForm) {
        addTagForm.addEventListener('submit', handleAddTagSubmit);
    }

    const editTagForm = document.querySelector('form[data-action="edit_tag"]');
    if (editTagForm) {
        editTagForm.addEventListener('submit', handleEditTagSubmit);
    }

    document.querySelectorAll('[data-action="delete_tag"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const tagId = btn.getAttribute('data-tag-id');
            const tagName = btn.getAttribute('data-tag-name');
            deleteTag(tagId, tagName);
        });
    });

    // Setup search input
    const searchInputs = document.querySelectorAll('[data-action="search"]');
    searchInputs.forEach(input => {
        input.addEventListener('input', handleServiceSearch);
    });

    // Setup flash message close button
    document.querySelectorAll('[data-flash-close]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.target.closest('[role="alert"]')?.remove();
        });
    });

    // Log successful initialization
    console.log('✓ manage-services.js initialized');
});
