/**
 * Page-Specific Integration: manage-costumes.js
 * Handles costume CRUD operations via REST API
 * Date: December 3, 2025
 */

// ============================================================
// 1. ADD COSTUME FORM HANDLER
// ============================================================

/**
 * Handle add costume form submission
 */
async function handleAddCostumeSubmit(e) {
    const form = e.target;
    
    // Check if user is uploading images - if so, use traditional form submit
    const coverImageInput = form.querySelector('input[name="COVER_IMAGE"]');
    const galleryImagesInput = form.querySelector('input[name="GALLERY_IMAGES[]"]');
    
    const hasCoverImage = coverImageInput && coverImageInput.files && coverImageInput.files.length > 0;
    const hasGalleryImages = galleryImagesInput && galleryImagesInput.files && galleryImagesInput.files.length > 0;
    
    // If uploading files, allow traditional form submission (don't prevent default)
    if (hasCoverImage || hasGalleryImages) {
        // Let the form submit naturally with enctype="multipart/form-data"
        return;
    }
    
    // Otherwise, use API for JSON-only operations
    e.preventDefault();
    
    const tenTp = form.querySelector('input[name="TEN_TP"]')?.value?.trim();
    const donGia = form.querySelector('input[name="DON_GIA"]')?.value?.trim();
    const idLoai = form.querySelector('select[name="ID_LOAI"]')?.value;
    const size = form.querySelector('input[name="SIZE"]')?.value?.trim();
    const mau = form.querySelector('input[name="MAU"]')?.value?.trim();
    const ghiChu = form.querySelector('textarea[name="GHI_CHU"]')?.value?.trim() || '';
    const scopeType = form.querySelector('input[name="SCOPE_TYPE"]:checked')?.value || 'global';
    const idCn = form.querySelector('select[name="ID_CN"]')?.value;

    // Validation
    if (!tenTp) {
        showNotification('Vui lòng nhập tên trang phục', 'error');
        return;
    }
    if (!donGia || parseInt(donGia) <= 0) {
        showNotification('Vui lòng nhập giá thuê hợp lệ', 'error');
        return;
    }
    if (scopeType === 'local' && !idCn) {
        showNotification('Vui lòng chọn chi nhánh cho trang phục riêng', 'error');
        return;
    }

    const spinner = showSpinner('Đang thêm trang phục...');

    try {
        const payload = {
            TEN: tenTp,
            GIA_THUE: parseInt(donGia),
            ID_LOAI: idLoai ? parseInt(idLoai) : null,
            SIZE: size,
            MAU_SAC: mau,
            GHI_CHU: ghiChu,
            SCOPE_TYPE: scopeType,
            ID_CN: scopeType === 'local' ? parseInt(idCn) : null
        };

        const result = await apiPost('/costumes', payload);

        if (result.success && result.data) {
            showNotification('Thêm trang phục thành công', 'success');
            form.reset();
            
            // Reload or redirect to edit page
            setTimeout(() => {
                window.location.href = `?page=costumes&edit=${result.data.ID_TRANG_PHUC}`;
            }, 1500);
        } else {
            showNotification(result.message || 'Không thể thêm trang phục', 'error');
        }
    } catch (error) {
        console.error('Error adding costume:', error);
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        if (spinner && spinner.remove) spinner.remove();
    }
}

// ============================================================
// 2. EDIT COSTUME FORM HANDLER
// ============================================================

/**
 * Handle edit costume form submission
 */
async function handleEditCostumeSubmit(e) {
    const form = e.target;
    
    // Check if user is uploading images - if so, use traditional form submit
    const coverImageInput = form.querySelector('input[name="COVER_IMAGE"]');
    const galleryImagesInput = form.querySelector('input[name="GALLERY_IMAGES[]"]');
    
    const hasCoverImage = coverImageInput && coverImageInput.files && coverImageInput.files.length > 0;
    const hasGalleryImages = galleryImagesInput && galleryImagesInput.files && galleryImagesInput.files.length > 0;
    
    // If uploading files, allow traditional form submission (don't prevent default)
    if (hasCoverImage || hasGalleryImages) {
        // Let the form submit naturally with enctype="multipart/form-data"
        return;
    }
    
    // Otherwise, use API for JSON updates
    e.preventDefault();
    
    const idTp = form.querySelector('input[name="ID_TP"]')?.value;
    const tenTp = form.querySelector('input[name="TEN_TP"]')?.value?.trim();
    const donGia = form.querySelector('input[name="DON_GIA"]')?.value?.trim();
    const idLoai = form.querySelector('select[name="ID_LOAI"]')?.value;
    const size = form.querySelector('input[name="SIZE"]')?.value?.trim();
    const mau = form.querySelector('input[name="MAU"]')?.value?.trim();
    const ghiChu = form.querySelector('textarea[name="GHI_CHU"]')?.value?.trim() || '';
    const scopeType = form.querySelector('input[name="SCOPE_TYPE"]:checked')?.value || 'global';
    const idCn = form.querySelector('select[name="ID_CN"]')?.value;
    const trangThai = form.querySelector('select[name="TINH_TRANG"]')?.value || undefined;

    if (!idTp || parseInt(idTp) <= 0) {
        showNotification('ID trang phục không hợp lệ', 'error');
        return;
    }

    // Validation
    if (!tenTp) {
        showNotification('Vui lòng nhập tên trang phục', 'error');
        return;
    }
    if (scopeType === 'local' && !idCn) {
        showNotification('Vui lòng chọn chi nhánh cho trang phục riêng', 'error');
        return;
    }

    const spinner = showSpinner('Đang cập nhật trang phục...');

    try {
        const payload = {
            TEN: tenTp,
            // chỉ gửi giá mới nếu người dùng nhập
            ...(donGia ? { GIA_THUE: parseInt(donGia) } : {}),
            ID_LOAI: idLoai ? parseInt(idLoai) : null,
            SIZE: size,
            MAU_SAC: mau,
            GHI_CHU: ghiChu,
            SCOPE_TYPE: scopeType,
            ID_CN: scopeType === 'local' ? parseInt(idCn) : null,
            TRANG_THAI: trangThai
        };

        const result = await apiPut(`/costumes/${idTp}`, payload);

        if (result.success) {
            showNotification('Cập nhật trang phục thành công', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification(result.message || 'Không thể cập nhật trang phục', 'error');
        }
    } catch (error) {
        console.error('Error updating costume:', error);
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        if (spinner && spinner.remove) spinner.remove();
    }
}

// ============================================================
// 3. DELETE COSTUME HANDLER
// ============================================================

/**
 * Delete costume with confirmation
 */
async function deleteCostume(costumeId, costumeName) {
    if (!showConfirm(`Xác nhận xóa trang phục "${costumeName}"?`)) {
        return;
    }

    showSpinner('Đang xóa trang phục...');

    try {
        const result = await apiDelete(`/costumes/${costumeId}`);

        if (result.success) {
            showNotification('Xóa trang phục thành công', 'success');
            setTimeout(() => {
                window.location.href = '?page=costumes';
            }, 1500);
        } else {
            showNotification(result.message || 'Không thể xóa trang phục', 'error');
        }
    } catch (error) {
        console.error('Error deleting costume:', error);
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        showSpinner(false);
    }
}

// ============================================================
// 4. STATUS CHANGE HANDLER
// ============================================================

/**
 * Change costume status (available/maintenance)
 */
async function changeCostumeStatus(costumeId, newStatus) {
    const statusLabel = newStatus === 'available' ? 'Sẵn sàng' : 'Bảo trì';
    
    showSpinner(`Đang chuyển sang "${statusLabel}"...`);

    try {
        const result = await apiPost(`/costumes/${costumeId}/available`, {
            status: newStatus
        });

        if (result.success) {
            showNotification(`Chuyển sang "${statusLabel}" thành công`, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification(result.message || 'Không thể thay đổi trạng thái', 'error');
        }
    } catch (error) {
        console.error('Error changing status:', error);
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        showSpinner(false);
    }
}

// ============================================================
// 5. CATEGORY & GROUP MANAGEMENT (LOẠI & NHÓM)
// ============================================================

/**
 * Add new category (Loại)
 */
async function addCategory(e) {
    e.preventDefault();
    
    const form = e.target;
    const tenLoai = form.querySelector('input[name="TEN_LOAI"]')?.value?.trim();
    const moTa = form.querySelector('textarea[name="MO_TA"]')?.value?.trim();
    const idNhom = form.querySelector('select[name="ID_NHOM"]')?.value;

    if (!tenLoai) {
        showNotification('Vui lòng nhập tên loại', 'error');
        return;
    }

    showSpinner('Đang thêm loại...');

    try {
        // For now, use POST to same page (backwards compatible)
        const formData = new FormData(form);
        formData.append('action', 'add_category');

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (response.ok) {
            showNotification('Thêm loại thành công', 'success');
            form.reset();
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Lỗi khi thêm loại', 'error');
        }
    } catch (error) {
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        showSpinner(false);
    }
}

/**
 * Delete category (Loại)
 */
async function deleteCategory(categoryId, categoryName) {
    if (!showConfirm(`Xác nhận xóa loại "${categoryName}"?`)) {
        return;
    }

    showSpinner('Đang xóa loại...');

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: JSON.stringify({
                action: 'delete_loai',
                ID_LOAI: categoryId
            }),
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (response.ok) {
            showNotification('Xóa loại thành công', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Lỗi khi xóa loại', 'error');
        }
    } catch (error) {
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        showSpinner(false);
    }
}

/**
 * Add new group (Nhóm)
 */
async function addGroup(e) {
    e.preventDefault();
    
    const form = e.target;
    const tenNhom = form.querySelector('input[name="TEN_NHOM"]')?.value?.trim();
    const moTa = form.querySelector('textarea[name="MO_TA"]')?.value?.trim();

    if (!tenNhom) {
        showNotification('Vui lòng nhập tên nhóm', 'error');
        return;
    }

    showSpinner('Đang thêm nhóm...');

    try {
        const formData = new FormData(form);
        formData.append('action', 'add_group');

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (response.ok) {
            showNotification('Thêm nhóm thành công', 'success');
            form.reset();
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Lỗi khi thêm nhóm', 'error');
        }
    } catch (error) {
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        showSpinner(false);
    }
}

/**
 * Delete group (Nhóm)
 */
async function deleteGroup(groupId, groupName) {
    if (!showConfirm(`Xác nhận xóa nhóm "${groupName}"?`)) {
        return;
    }

    showSpinner('Đang xóa nhóm...');

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: JSON.stringify({
                action: 'delete_nhom',
                ID_NHOM: groupId
            }),
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (response.ok) {
            showNotification('Xóa nhóm thành công', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Lỗi khi xóa nhóm', 'error');
        }
    } catch (error) {
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        showSpinner(false);
    }
}

// ============================================================
// 6. SCOPE TYPE TOGGLE
// ============================================================

/**
 * Toggle branch select visibility based on scope type
 */
function toggleBranchSelect() {
    const scopeType = document.querySelector('input[name="SCOPE_TYPE"]:checked')?.value || 'global';
    const branchSelect = document.getElementById('branchSelect');
    const branchInput = document.querySelector('select[name="ID_CN"]');
    
    if (scopeType === 'local') {
        if (branchSelect) {
            branchSelect.style.display = 'block';
        }
        if (branchInput) {
            branchInput.required = true;
        }
    } else {
        if (branchSelect) {
            branchSelect.style.display = 'none';
        }
        if (branchInput) {
            branchInput.required = false;
            branchInput.value = '';
        }
    }
}

// ============================================================
// 7. SEARCH & FILTER
// ============================================================

/**
 * Handle real-time search with debounce
 */
let searchTimeout;
function handleCostumeSearch(e) {
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
// 8. PAGE INITIALIZATION
// ============================================================

/**
 * Initialize page on load
 */
document.addEventListener('DOMContentLoaded', () => {
    // Setup add costume form
    const addCostumeForm = document.querySelector('form[data-action="add_costume"]');
    if (addCostumeForm) {
        addCostumeForm.addEventListener('submit', handleAddCostumeSubmit);
    }

    // Setup edit costume form
    const editCostumeForm = document.querySelector('form[data-action="edit_costume"]');
    if (editCostumeForm) {
        editCostumeForm.addEventListener('submit', handleEditCostumeSubmit);
    }

    // Setup delete buttons
    document.querySelectorAll('[data-action="delete_costume"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const costumeId = btn.getAttribute('data-costume-id');
            const costumeName = btn.getAttribute('data-costume-name');
            deleteCostume(costumeId, costumeName);
        });
    });

    // Setup status change buttons
    document.querySelectorAll('[data-action="change_status"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const costumeId = btn.getAttribute('data-id');
            const newStatus = btn.getAttribute('data-status');
            changeCostumeStatus(costumeId, newStatus);
        });
    });

    // Category and Group forms now use traditional form submission
    // No JavaScript interception needed - PHP handles everything
    
    // Setup category (loại) forms - REMOVED JAVASCRIPT INTERCEPTION
    // Forms will submit naturally to PHP handler
    
    // Setup group (nhóm) forms - REMOVED JAVASCRIPT INTERCEPTION  
    // Forms will submit naturally to PHP handler

    document.querySelectorAll('[data-action="delete_group"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const groupId = btn.getAttribute('data-id');
            const groupName = btn.getAttribute('data-name');
            deleteGroup(groupId, groupName);
        });
    });

    // Setup scope type toggle
    document.querySelectorAll('input[name="SCOPE_TYPE"]').forEach(radio => {
        radio.addEventListener('change', toggleBranchSelect);
    });

    // Setup search input
    const searchInputs = document.querySelectorAll('[data-action="search"]');
    searchInputs.forEach(input => {
        input.addEventListener('input', handleCostumeSearch);
    });

    // Setup flash message close button
    document.querySelectorAll('[data-flash-close]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.target.closest('[role="alert"]')?.remove();
        });
    });

    // Log successful initialization
    console.log('✓ manage-costumes.js initialized');
});
