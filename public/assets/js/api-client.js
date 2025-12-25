/**
 * API Client Utilities for Phase 5B UI Integration
 * Reusable fetch utilities for all dashboard pages
 * 
 * Usage:
 *   const result = await apiCall('GET', '/packages');
 *   const result = await apiCall('POST', '/packages', { name: 'test' });
 *   showNotification('Success!', 'success');
 */

// ============================================
// API CONFIGURATION
// ============================================

// Resolve API base URL so it works when the app is served from a subdirectory (e.g., /StygianBlue)
function normalizeBaseUrl(base) {
  if (!base) return '/api';
  let normalized = base.trim();
  if (!normalized.startsWith('/')) {
    normalized = '/' + normalized;
  }
  normalized = normalized.replace(/\/+$/, '');
  return normalized || '/api';
}

function resolveApiBaseUrl() {
  // Highest priority: global override
  if (window.API_BASE_URL) {
    return normalizeBaseUrl(window.API_BASE_URL);
  }

  // Optional meta tag override
  const metaBase = document.querySelector('meta[name="api-base"]')?.getAttribute('content');
  if (metaBase) {
    return normalizeBaseUrl(metaBase);
  }

  // If hosted under /StygianBlue (typical XAMPP path), prepend that segment
  const parts = window.location.pathname.split('/').filter(Boolean);
  if (parts[0] && parts[0].toLowerCase() === 'stygianblue') {
    return '/StygianBlue/api';
  }

  // Default for root deployments
  return '/api';
}

const API_CONFIG = {
  baseUrl: resolveApiBaseUrl(),
  timeout: 30000, // 30 seconds
  headers: {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  }
};

// ============================================
// MAIN API CALL FUNCTION
// ============================================

/**
 * Generic API call function with error handling
 * @param {string} method - HTTP method (GET, POST, PUT, DELETE)
 * @param {string} endpoint - API endpoint (e.g., '/packages', '/services/{id}')
 * @param {object|null} data - Request body (for POST/PUT)
 * @param {object|null} options - Additional options
 * @returns {Promise<object>} - Response data
 */
async function apiCall(method, endpoint, data = null, options = {}) {
  const fetchOptions = {
    method: method.toUpperCase(),
    headers: { ...API_CONFIG.headers, ...options.headers }
  };

  // Add body for POST/PUT/PATCH
  if (data && ['POST', 'PUT', 'PATCH'].includes(method.toUpperCase())) {
    fetchOptions.body = JSON.stringify(data);
  }

  // Add query parameters for GET
  if (method.toUpperCase() === 'GET' && data) {
    const params = new URLSearchParams(data);
    endpoint = `${endpoint}?${params.toString()}`;
  }

  const url = `${API_CONFIG.baseUrl}${endpoint}`;

  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), API_CONFIG.timeout);

    const response = await fetch(url, {
      ...fetchOptions,
      signal: controller.signal
    });

    clearTimeout(timeoutId);

    // Parse response
    let responseData = null;
    const contentType = response.headers.get('content-type');
    if (contentType && contentType.includes('application/json')) {
      responseData = await response.json();
    } else {
      responseData = await response.text();
    }

    // Handle errors
    if (!response.ok) {
      const errorMessage = responseData?.message || responseData?.error || `HTTP ${response.status}`;
      const error = new ApiError(errorMessage, response.status, responseData);
      throw error;
    }

    return responseData;
  } catch (error) {
    if (error instanceof ApiError) {
      throw error;
    }

    if (error.name === 'AbortError') {
      throw new ApiError('Request timeout', 408, null);
    }

    throw new ApiError(`Network error: ${error.message}`, 0, null);
  }
}

// ============================================
// API ERROR CLASS
// ============================================

class ApiError extends Error {
  constructor(message, status = 0, data = null) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.data = data;
  }
}

// ============================================
// CONVENIENCE METHODS
// ============================================

/**
 * GET request
 */
async function apiGet(endpoint, params = null) {
  return apiCall('GET', endpoint, params);
}

/**
 * POST request
 */
async function apiPost(endpoint, data) {
  return apiCall('POST', endpoint, data);
}

/**
 * PUT request
 */
async function apiPut(endpoint, data) {
  return apiCall('PUT', endpoint, data);
}

/**
 * DELETE request
 */
async function apiDelete(endpoint) {
  return apiCall('DELETE', endpoint);
}

/**
 * PATCH request
 */
async function apiPatch(endpoint, data) {
  return apiCall('PATCH', endpoint, data);
}

// ============================================
// RESPONSE HANDLING
// ============================================

/**
 * Handle API response and show appropriate notification
 * @param {Promise} apiPromise - The API call promise
 * @param {object} messages - Custom messages { success: '...', error: '...' }
 * @param {function} onSuccess - Callback on success
 * @param {function} onError - Callback on error
 */
async function handleApiResponse(apiPromise, messages = {}, onSuccess = null, onError = null) {
  try {
    const response = await apiPromise;
    const successMsg = messages.success || 'Thành công!';
    showNotification(successMsg, 'success');
    
    if (onSuccess) {
      onSuccess(response);
    }
    
    return response;
  } catch (error) {
    const errorMsg = messages.error || error.message || 'Có lỗi xảy ra';
    showNotification(errorMsg, 'error');
    
    if (onError) {
      onError(error);
    }
    
    throw error;
  }
}

// ============================================
// NOTIFICATION SYSTEM
// ============================================

/**
 * Show notification message
 * @param {string} message - Message to show
 * @param {string} type - Type: 'success', 'error', 'info', 'warning'
 * @param {number} duration - Auto-close duration in ms (0 = no auto-close)
 */
function showNotification(message, type = 'info', duration = 5000) {
  // Color classes based on type
  const typeClasses = {
    success: 'bg-green-100 border-green-300 text-green-700',
    error: 'bg-red-100 border-red-300 text-red-700',
    info: 'bg-blue-100 border-blue-300 text-blue-700',
    warning: 'bg-yellow-100 border-yellow-300 text-yellow-700'
  };

  const icon = {
    success: '✓',
    error: '✕',
    info: 'ℹ',
    warning: '⚠'
  };

  const classes = typeClasses[type] || typeClasses.info;
  const iconChar = icon[type] || icon.info;

  // Create notification element
  const notification = document.createElement('div');
  notification.className = `fixed top-4 right-4 max-w-md p-4 rounded-lg border ${classes} shadow-lg z-50 animate-slide-in`;
  notification.innerHTML = `
    <div class="flex items-start gap-3">
      <span class="font-bold text-lg flex-shrink-0">${iconChar}</span>
      <div class="flex-1">
        <p class="font-medium">${escapeHtml(message)}</p>
      </div>
      <button type="button" onclick="this.parentElement.parentElement.remove()" class="flex-shrink-0 font-bold opacity-50 hover:opacity-100 transition">×</button>
    </div>
  `;

  // Add to page
  document.body.appendChild(notification);

  // Auto-close if duration specified
  if (duration > 0) {
    setTimeout(() => {
      notification.classList.add('animate-fade-out');
      setTimeout(() => notification.remove(), 300);
    }, duration);
  }

  return notification;
}

/**
 * Show confirmation dialog
 * @param {string} message - Confirmation message
 * @param {function} onConfirm - Callback if user confirms
 * @param {function} onCancel - Callback if user cancels
 */
function showConfirm(message, onConfirm = null, onCancel = null) {
  const confirmed = confirm(message);
  
  if (confirmed && onConfirm) {
    onConfirm();
  } else if (!confirmed && onCancel) {
    onCancel();
  }
  
  return confirmed;
}

// ============================================
// LOADING INDICATORS
// ============================================

/**
 * Show loading state on element
 * @param {HTMLElement|string} element - Element or selector
 */
function showLoading(element) {
  if (typeof element === 'string') {
    element = document.querySelector(element);
  }

  if (!element) return;

  element.classList.add('opacity-50', 'pointer-events-none');
  element.dataset.loading = 'true';
}

/**
 * Hide loading state on element
 * @param {HTMLElement|string} element - Element or selector
 */
function hideLoading(element) {
  if (typeof element === 'string') {
    element = document.querySelector(element);
  }

  if (!element) return;

  element.classList.remove('opacity-50', 'pointer-events-none');
  delete element.dataset.loading;
}

/**
 * Show loading spinner
 * @param {string} message - Optional loading message
 * @returns {HTMLElement} - The spinner element (call hide on it)
 */
function showSpinner(message = 'Đang tải...') {
  const spinner = document.createElement('div');
  spinner.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50';
  spinner.innerHTML = `
    <div class="bg-white rounded-lg p-8 text-center">
      <div class="animate-spin w-12 h-12 border-4 border-gray-200 border-t-blue-600 rounded-full mx-auto mb-4"></div>
      <p class="text-gray-700 font-medium">${escapeHtml(message)}</p>
    </div>
  `;

  document.body.appendChild(spinner);

  // Add close method
  spinner.close = () => spinner.remove();

  return spinner;
}

// ============================================
// FORM UTILITIES
// ============================================

/**
 * Get form data as object
 * @param {HTMLFormElement|string} form - Form element or selector
 * @returns {object} - Form data
 */
function getFormData(form) {
  if (typeof form === 'string') {
    form = document.querySelector(form);
  }

  if (!form) return {};

  const formData = new FormData(form);
  const data = {};

  for (const [key, value] of formData.entries()) {
    if (key.endsWith('[]')) {
      const arrayKey = key.slice(0, -2);
      if (!data[arrayKey]) {
        data[arrayKey] = [];
      }
      data[arrayKey].push(value);
    } else {
      data[key] = value;
    }
  }

  return data;
}

/**
 * Populate form with data
 * @param {HTMLFormElement|string} form - Form element or selector
 * @param {object} data - Data to populate
 */
function setFormData(form, data) {
  if (typeof form === 'string') {
    form = document.querySelector(form);
  }

  if (!form) return;

  for (const [key, value] of Object.entries(data)) {
    const input = form.querySelector(`[name="${key}"]`);
    if (!input) continue;

    if (input.type === 'checkbox') {
      input.checked = value === true || value === '1' || value === 1;
    } else if (input.type === 'radio') {
      form.querySelector(`[name="${key}"][value="${value}"]`).checked = true;
    } else {
      input.value = value;
    }
  }
}

/**
 * Reset form
 * @param {HTMLFormElement|string} form - Form element or selector
 */
function resetForm(form) {
  if (typeof form === 'string') {
    form = document.querySelector(form);
  }

  if (form) {
    form.reset();
  }
}

/**
 * Validate form field
 * @param {HTMLInputElement|string} field - Field element or selector
 * @returns {object} - { valid: boolean, errors: string[] }
 */
function validateField(field) {
  if (typeof field === 'string') {
    field = document.querySelector(field);
  }

  if (!field) return { valid: true, errors: [] };

  const errors = [];

  // Check required
  if (field.required && !field.value.trim()) {
    errors.push('Trường này không được để trống');
  }

  // Check type
  if (field.type === 'email' && field.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
    errors.push('Email không hợp lệ');
  }

  if (field.type === 'number') {
    if (field.min && Number(field.value) < Number(field.min)) {
      errors.push(`Giá trị tối thiểu là ${field.min}`);
    }
    if (field.max && Number(field.value) > Number(field.max)) {
      errors.push(`Giá trị tối đa là ${field.max}`);
    }
  }

  // Check custom pattern
  if (field.pattern && field.value && !new RegExp(field.pattern).test(field.value)) {
    errors.push(field.title || 'Định dạng không hợp lệ');
  }

  return {
    valid: errors.length === 0,
    errors: errors
  };
}

// ============================================
// TABLE & LIST UTILITIES
// ============================================

/**
 * Format table row
 * @param {object} item - Data item
 * @param {array} columns - Column definitions
 * @returns {string} - HTML table row
 */
function formatTableRow(item, columns) {
  return `
    <tr>
      ${columns.map(col => {
        let value = item[col.key];
        
        if (col.format) {
          value = col.format(value, item);
        } else if (col.type === 'date') {
          value = new Date(value).toLocaleDateString('vi-VN');
        } else if (col.type === 'currency') {
          value = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
        }
        
        return `<td class="${col.classes || ''}">${escapeHtml(value)}</td>`;
      }).join('')}
    </tr>
  `;
}

/**
 * Render table
 * @param {HTMLTableElement|string} table - Table element or selector
 * @param {array} items - Data items
 * @param {array} columns - Column definitions
 * @param {boolean} clearFirst - Clear existing rows first
 */
function renderTable(table, items, columns, clearFirst = true) {
  if (typeof table === 'string') {
    table = document.querySelector(table);
  }

  if (!table) return;

  const tbody = table.querySelector('tbody');
  if (!tbody) return;

  if (clearFirst) {
    tbody.innerHTML = '';
  }

  if (items.length === 0) {
    tbody.innerHTML = `<tr><td colspan="${columns.length}" class="text-center text-gray-500 py-4">Không có dữ liệu</td></tr>`;
    return;
  }

  tbody.innerHTML = items.map(item => formatTableRow(item, columns)).join('');
}

// ============================================
// URL & QUERY UTILITIES
// ============================================

/**
 * Get URL query parameters
 * @returns {object} - Query parameters
 */
function getQueryParams() {
  const params = new URLSearchParams(window.location.search);
  const result = {};

  for (const [key, value] of params.entries()) {
    result[key] = value;
  }

  return result;
}

/**
 * Update URL query parameter
 * @param {string} key - Parameter key
 * @param {string|null} value - Parameter value (null to remove)
 * @param {boolean} replace - Use replaceState instead of pushState
 */
function setQueryParam(key, value, replace = false) {
  const params = getQueryParams();

  if (value === null || value === '') {
    delete params[key];
  } else {
    params[key] = value;
  }

  const queryString = new URLSearchParams(params).toString();
  const newUrl = queryString ? `${window.location.pathname}?${queryString}` : window.location.pathname;

  if (replace) {
    window.history.replaceState({}, '', newUrl);
  } else {
    window.history.pushState({}, '', newUrl);
  }
}

// ============================================
// STRING UTILITIES
// ============================================

/**
 * Escape HTML special characters
 * @param {string} text - Text to escape
 * @returns {string} - Escaped text
 */
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };

  return String(text).replace(/[&<>"']/g, m => map[m]);
}

/**
 * Format currency
 * @param {number} value - Value to format
 * @param {string} currency - Currency code (default: VND)
 * @returns {string} - Formatted currency
 */
function formatCurrency(value, currency = 'VND') {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currency
  }).format(value);
}

/**
 * Format date
 * @param {string|Date} date - Date to format
 * @param {string} format - Format string (default: 'dd/MM/yyyy')
 * @returns {string} - Formatted date
 */
function formatDate(date, format = 'dd/MM/yyyy') {
  if (typeof date === 'string') {
    date = new Date(date);
  }

  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();

  return format
    .replace('dd', day)
    .replace('MM', month)
    .replace('yyyy', year);
}

// ============================================
// INITIALIZATION
// ============================================

/**
 * Initialize API client
 * Call this once on page load
 */
function initApiClient() {
  // Add CSS animations
  if (!document.getElementById('api-client-styles')) {
    const style = document.createElement('style');
    style.id = 'api-client-styles';
    style.innerHTML = `
      @keyframes slide-in {
        from {
          transform: translateX(400px);
          opacity: 0;
        }
        to {
          transform: translateX(0);
          opacity: 1;
        }
      }

      @keyframes fade-out {
        from {
          opacity: 1;
          transform: translateY(0);
        }
        to {
          opacity: 0;
          transform: translateY(-10px);
        }
      }

      .animate-slide-in {
        animation: slide-in 0.3s ease-out;
      }

      .animate-fade-out {
        animation: fade-out 0.3s ease-out;
      }
    `;
    document.head.appendChild(style);
  }

  console.log('API Client initialized');
}

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApiClient);
} else {
  initApiClient();
}
