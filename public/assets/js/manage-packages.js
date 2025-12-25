/**
 * Service Packages Management JavaScript
 * Handles CRUD operations for service packages via REST API
 * 
 * Endpoints:
 * - GET    /api/service-packages (list)
 * - GET    /api/service-packages/{id} (detail)
 * - POST   /api/service-packages (create)
 * - PUT    /api/service-packages/{id} (update)
 * - DELETE /api/service-packages/{id} (delete)
 * - POST   /api/service-packages/{id}/requirements (add requirement)
 * - PUT    /api/service-packages/{id}/requirements/{reqId} (update requirement)
 * - DELETE /api/service-packages/{id}/requirements/{reqId} (delete requirement)
 * - POST   /api/service-packages/{id}/promotions (add promotion)
 * - PUT    /api/service-packages/{id}/promotions/{promoId} (update promotion)
 * - DELETE /api/service-packages/{id}/promotions/{promoId} (delete promotion)
 */

// Parse API responses safely to avoid crashes when server returns HTML errors
async function parseJsonResponse(response) {
  const raw = await response.text();
  try {
    return JSON.parse(raw);
  } catch (err) {
    throw new Error(raw || 'Phản hồi không hợp lệ');
  }
}

// ===== HANDLER: Add Package =====
async function handleAddPackageSubmit(e) {
  e.preventDefault();
  
  try {
    const form = e.target;
    
    // Validate form fields
    const tenGoi = form.querySelector('[name="TEN_GOI"]')?.value.trim();
    const moTa = form.querySelector('[name="MO_TA"]')?.value.trim();
    
    if (!tenGoi || tenGoi.length < 5) {
      showNotification('Tên gói phải từ 5 ký tự trở lên', 'error');
      return;
    }
    
    if (!moTa || moTa.length < 20) {
      showNotification('Mô tả phải từ 20 ký tự trở lên', 'error');
      return;
    }
    
    // Show spinner
    showSpinner('Đang tạo gói dịch vụ...');
    
    // Prepare FormData
    const formData = new FormData(form);
    
    // Call API
    const response = await fetch(`${API_CONFIG.baseUrl}/service-packages`, {
      method: 'POST',
      body: formData
    });
    
    const result = await parseJsonResponse(response);
    
    if (response.ok && result.success) {
      showNotification('Gói dịch vụ đã được tạo thành công', 'success');
      // Redirect to edit page after 1 second
      setTimeout(() => {
        window.location.href = `admin_dashboard.php?page=packages&edit=${result.id}&created=1`;
      }, 1000);
    } else {
      const errorMsg = result.errors?.[0] || result.error || 'Không thể tạo gói dịch vụ';
      showNotification(errorMsg, 'error');
    }
  } catch (error) {
    console.error('Add package error:', error);
    showNotification('Lỗi: ' + error.message, 'error');
  }
}

// ===== HANDLER: Edit Package =====
async function handleEditPackageSubmit(e) {
  e.preventDefault();
  
  try {
    const form = e.target;
    const packageId = form.querySelector('[name="ID_GOI"]')?.value;
    
    if (!packageId) {
      showNotification('Không xác định được ID gói dịch vụ', 'error');
      return;
    }
    
    // Validate form fields
    const tenGoi = form.querySelector('[name="TEN_GOI"]')?.value.trim();
    const moTa = form.querySelector('[name="MO_TA"]')?.value.trim();
    
    if (!tenGoi || tenGoi.length < 5) {
      showNotification('Tên gói phải từ 5 ký tự trở lên', 'error');
      return;
    }
    
    if (!moTa || moTa.length < 20) {
      showNotification('Mô tả phải từ 20 ký tự trở lên', 'error');
      return;
    }
    
    // Show spinner
    showSpinner('Đang cập nhật gói dịch vụ...');
    
    // Prepare FormData
    const formData = new FormData(form);
    
    // Call API
    // Use POST + _method=PUT to ensure PHP parses multipart form-data
    formData.append('_method', 'PUT');
    const response = await fetch(`${API_CONFIG.baseUrl}/service-packages/${packageId}`, {
      method: 'POST',
      body: formData
    });
    
    const result = await parseJsonResponse(response);
    
    if (response.ok && result.success) {
      showNotification('Gói dịch vụ đã được cập nhật thành công', 'success');
      // Reload page after 1 second
      setTimeout(() => {
        location.reload();
      }, 1000);
    } else {
      const errorMsg = result.errors?.[0] || result.error || 'Không thể cập nhật gói dịch vụ';
      showNotification(errorMsg, 'error');
    }
  } catch (error) {
    console.error('Edit package error:', error);
    showNotification('Lỗi: ' + error.message, 'error');
  }
}

// ===== HANDLER: Delete Package =====
async function deletePackage(packageId, packageName) {
  try {
    const confirmed = await showConfirm(
      `Xóa gói dịch vụ "${packageName}"?`,
      'Hành động này không thể hoàn tác. Tất cả dịch vụ, trang phục và khuyến mãi trong gói sẽ bị xóa.',
      'Xóa'
    );
    
    if (!confirmed) return;
    
    showSpinner('Đang xóa gói dịch vụ...');
    
    const response = await fetch(`${API_CONFIG.baseUrl}/service-packages/${packageId}`, {
      method: 'DELETE'
    });
    
    const result = await parseJsonResponse(response);
    
    if (response.ok && result.success) {
      showNotification('Gói dịch vụ đã được xóa thành công', 'success');
      // Reload page after 1 second
      setTimeout(() => {
        location.reload();
      }, 1000);
    } else {
      const errorMsg = result.error || 'Không thể xóa gói dịch vụ';
      showNotification(errorMsg, 'error');
    }
  } catch (error) {
    console.error('Delete package error:', error);
    showNotification('Lỗi: ' + error.message, 'error');
  }
}

// ===== HANDLER: Add Requirement =====
async function handleAddRequirementSubmit(e) {
  e.preventDefault();
  
  try {
    const form = e.target;
    const packageId = form.closest('[data-package-id]')?.getAttribute('data-package-id');
    
    if (!packageId) {
      showNotification('Không xác định được ID gói dịch vụ', 'error');
      return;
    }
    
    // Validate
    const tenYeuCau = form.querySelector('[name="TEN_YEU_CAU"]')?.value.trim();
    if (!tenYeuCau) {
      showNotification('Tên yêu cầu không được để trống', 'error');
      return;
    }
    
    // Show spinner
    showSpinner('Đang thêm yêu cầu trang phục...');
    
    // Prepare FormData
    const formData = new FormData(form);
    
    // Call API
    const response = await fetch(`${API_CONFIG.baseUrl}/service-packages/${packageId}/requirements`, {
      method: 'POST',
      body: formData
    });
    
    const result = await parseJsonResponse(response);
    
    if (response.ok && result.success) {
      showNotification('Yêu cầu trang phục đã được thêm', 'success');
      // Reset form and reload data after 1 second
      form.reset();
      setTimeout(() => {
        location.reload();
      }, 1000);
    } else {
      const errorMsg = result.error || 'Không thể thêm yêu cầu trang phục';
      showNotification(errorMsg, 'error');
    }
  } catch (error) {
    console.error('Add requirement error:', error);
    showNotification('Lỗi: ' + error.message, 'error');
  }
}

// ===== HANDLER: Edit Requirement =====
async function handleEditRequirementSubmit(e) {
  e.preventDefault();
  
  try {
    const form = e.target;
    const packageId = form.closest('[data-package-id]')?.getAttribute('data-package-id');
    const requirementId = form.querySelector('[name="ID_YC"]')?.value;
    
    if (!packageId || !requirementId) {
      showNotification('Không xác định được ID', 'error');
      return;
    }
    
    // Validate
    const tenYeuCau = form.querySelector('[name="TEN_YEU_CAU"]')?.value.trim();
    if (!tenYeuCau) {
      showNotification('Tên yêu cầu không được để trống', 'error');
      return;
    }
    
    // Show spinner
    showSpinner('Đang cập nhật yêu cầu trang phục...');
    
    // Prepare FormData
    const formData = new FormData(form);
    
    // Call API
    const response = await fetch(`${API_CONFIG.baseUrl}/service-packages/${packageId}/requirements/${requirementId}`, {
      method: 'PUT',
      body: formData
    });
    
    const result = await parseJsonResponse(response);
    
    if (response.ok && result.success) {
      showNotification('Yêu cầu trang phục đã được cập nhật', 'success');
      // Reload page after 1 second
      setTimeout(() => {
        location.reload();
      }, 1000);
    } else {
      const errorMsg = result.error || 'Không thể cập nhật yêu cầu trang phục';
      showNotification(errorMsg, 'error');
    }
  } catch (error) {
    console.error('Edit requirement error:', error);
    showNotification('Lỗi: ' + error.message, 'error');
  }
}

// ===== HANDLER: Delete Requirement =====
async function deleteRequirement(packageId, requirementId, requirementName) {
  try {
    const confirmed = await showConfirm(
      `Xóa yêu cầu "${requirementName}"?`,
      'Yêu cầu này sẽ bị xóa khỏi gói dịch vụ.',
      'Xóa'
    );
    
    if (!confirmed) return;
    
    showSpinner('Đang xóa yêu cầu trang phục...');
    
    const response = await fetch(`${API_CONFIG.baseUrl}/service-packages/${packageId}/requirements/${requirementId}`, {
      method: 'DELETE'
    });
    
    const result = await parseJsonResponse(response);
    
    if (response.ok && result.success) {
      showNotification('Yêu cầu trang phục đã được xóa', 'success');
      // Reload page after 1 second
      setTimeout(() => {
        location.reload();
      }, 1000);
    } else {
      const errorMsg = result.error || 'Không thể xóa yêu cầu trang phục';
      showNotification(errorMsg, 'error');
    }
  } catch (error) {
    console.error('Delete requirement error:', error);
    showNotification('Lỗi: ' + error.message, 'error');
  }
}

// ===== HANDLER: Add Promotion =====
async function handleAddPromotionSubmit(e) {
  e.preventDefault();
  
  try {
    const form = e.target;
    const packageId = form.closest('[data-package-id]')?.getAttribute('data-package-id');
    
    if (!packageId) {
      showNotification('Không xác định được ID gói dịch vụ', 'error');
      return;
    }
    
    // Validate
    const tenKhuyenMai = form.querySelector('[name="TEN_KHUYEN_MAI"]')?.value.trim();
    if (!tenKhuyenMai) {
      showNotification('Tên khuyến mãi không được để trống', 'error');
      return;
    }
    
    const phanTram = form.querySelector('[name="PHAN_TRAM"]')?.value || 0;
    const tienChietKhau = form.querySelector('[name="TIEN_CHIET_KHAU"]')?.value || 0;
    
    if (parseFloat(phanTram) === 0 && parseFloat(tienChietKhau) === 0) {
      showNotification('Vui lòng nhập tỷ lệ phần trăm hoặc tiền chiết khấu', 'error');
      return;
    }
    
    // Show spinner
    showSpinner('Đang tạo khuyến mãi...');
    
    // Prepare FormData
    const formData = new FormData(form);
    
    // Call API
    const response = await fetch(`${API_CONFIG.baseUrl}/service-packages/${packageId}/promotions`, {
      method: 'POST',
      body: formData
    });
    
    const result = await parseJsonResponse(response);
    
    if (response.ok && result.success) {
      showNotification('Khuyến mãi đã được tạo', 'success');
      // Reset form and reload data after 1 second
      form.reset();
      setTimeout(() => {
        location.reload();
      }, 1000);
    } else {
      const errorMsg = result.error || 'Không thể tạo khuyến mãi';
      showNotification(errorMsg, 'error');
    }
  } catch (error) {
    console.error('Add promotion error:', error);
    showNotification('Lỗi: ' + error.message, 'error');
  }
}

// ===== HANDLER: Edit Promotion =====
async function handleEditPromotionSubmit(e) {
  e.preventDefault();
  
  try {
    const form = e.target;
    const packageId = form.closest('[data-package-id]')?.getAttribute('data-package-id');
    const promotionId = form.querySelector('[name="ID_KHUYEN_MAI"]')?.value;
    
    if (!packageId || !promotionId) {
      showNotification('Không xác định được ID', 'error');
      return;
    }
    
    // Validate
    const tenKhuyenMai = form.querySelector('[name="TEN_KHUYEN_MAI"]')?.value.trim();
    if (!tenKhuyenMai) {
      showNotification('Tên khuyến mãi không được để trống', 'error');
      return;
    }
    
    // Show spinner
    showSpinner('Đang cập nhật khuyến mãi...');
    
    // Prepare FormData
    const formData = new FormData(form);
    
    // Call API
    const response = await fetch(`${API_CONFIG.baseUrl}/service-packages/${packageId}/promotions/${promotionId}`, {
      method: 'PUT',
      body: formData
    });
    
    const result = await parseJsonResponse(response);
    
    if (response.ok && result.success) {
      showNotification('Khuyến mãi đã được cập nhật', 'success');
      // Reload page after 1 second
      setTimeout(() => {
        location.reload();
      }, 1000);
    } else {
      const errorMsg = result.error || 'Không thể cập nhật khuyến mãi';
      showNotification(errorMsg, 'error');
    }
  } catch (error) {
    console.error('Edit promotion error:', error);
    showNotification('Lỗi: ' + error.message, 'error');
  }
}

// ===== HANDLER: Delete Promotion =====
async function deletePromotion(packageId, promotionId, promotionName) {
  try {
    const confirmed = await showConfirm(
      `Xóa khuyến mãi "${promotionName}"?`,
      'Khuyến mãi này sẽ bị xóa khỏi gói dịch vụ.',
      'Xóa'
    );
    
    if (!confirmed) return;
    
    showSpinner('Đang xóa khuyến mãi...');
    
    const response = await fetch(`${API_CONFIG.baseUrl}/service-packages/${packageId}/promotions/${promotionId}`, {
      method: 'DELETE'
    });
    
    const result = await parseJsonResponse(response);
    
    if (response.ok && result.success) {
      showNotification('Khuyến mãi đã được xóa', 'success');
      // Reload page after 1 second
      setTimeout(() => {
        location.reload();
      }, 1000);
    } else {
      const errorMsg = result.error || 'Không thể xóa khuyến mãi';
      showNotification(errorMsg, 'error');
    }
  } catch (error) {
    console.error('Delete promotion error:', error);
    showNotification('Lỗi: ' + error.message, 'error');
  }
}

// ===== HANDLER: Package Search =====
function handlePackageSearch(e) {
  const searchInput = e.target;
  const searchValue = searchInput.value.trim();
  
  // Debounce: clear existing timeout and set new one
  if (window.packageSearchTimeout) {
    clearTimeout(window.packageSearchTimeout);
  }
  
  window.packageSearchTimeout = setTimeout(() => {
    // Submit the parent form
    const form = searchInput.closest('form');
    if (form) {
      form.submit();
    }
  }, 500);
}

// ===== PAGE INITIALIZATION =====
document.addEventListener('DOMContentLoaded', function() {
  
  // Register form handlers via data-action attributes
  document.querySelectorAll('form[data-action="add_package"]').forEach(form => {
    form.addEventListener('submit', handleAddPackageSubmit);
  });
  
  document.querySelectorAll('form[data-action="edit_package"]').forEach(form => {
    form.addEventListener('submit', handleEditPackageSubmit);
  });
  
  document.querySelectorAll('form[data-action="add_requirement"]').forEach(form => {
    form.addEventListener('submit', handleAddRequirementSubmit);
  });
  
  document.querySelectorAll('form[data-action="edit_requirement"]').forEach(form => {
    form.addEventListener('submit', handleEditRequirementSubmit);
  });
  
  document.querySelectorAll('form[data-action="add_promotion"]').forEach(form => {
    form.addEventListener('submit', handleAddPromotionSubmit);
  });
  
  document.querySelectorAll('form[data-action="edit_promotion"]').forEach(form => {
    form.addEventListener('submit', handleEditPromotionSubmit);
  });
  
  // Register search input handler
  document.querySelectorAll('input[data-action="search_packages"]').forEach(input => {
    input.addEventListener('input', handlePackageSearch);
  });
  
  // Register delete buttons (they need onclick handlers with parameters)
  // Example: <button onclick="deletePackage(123, 'Package Name')">Xóa</button>
  // These will be called directly from onclick attributes
  
  console.log('✓ manage-packages.js initialized');
});
