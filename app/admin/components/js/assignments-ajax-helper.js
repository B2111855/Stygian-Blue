/**
 * Assignment Management AJAX Helper
 * Provides utilities for integrating API calls into UI components
 */

class AssignmentAjaxHelper {
    constructor() {
        this.baseUrl = '/api';
        this.showToast = this.showToast.bind(this);
        this.showLoading = this.showLoading.bind(this);
        this.hideLoading = this.hideLoading.bind(this);
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 px-4 py-3 rounded-lg shadow-lg text-white z-50 
            ${type === 'success' ? 'bg-emerald-600' : type === 'error' ? 'bg-rose-600' : 'bg-blue-600'}`;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, duration);
    }

    /**
     * Show loading overlay
     */
    showLoading(message = 'Đang xử lý...') {
        let loader = document.getElementById('apiLoader');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'apiLoader';
            loader.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50';
            loader.innerHTML = `
                <div class="bg-white rounded-lg p-6 text-center">
                    <div class="animate-spin w-10 h-10 border-4 border-indigo-600 border-t-transparent rounded-full mx-auto mb-3"></div>
                    <p class="text-gray-600">${message}</p>
                </div>
            `;
            document.body.appendChild(loader);
        } else {
            loader.style.display = 'flex';
        }
        return loader;
    }

    /**
     * Hide loading overlay
     */
    hideLoading() {
        const loader = document.getElementById('apiLoader');
        if (loader) {
            loader.style.display = 'none';
        }
    }

    /**
     * Handle delete with confirmation
     */
    async handleDelete(scheduleId, employeeId, options = {}) {
        const {
            onSuccess = () => window.location.reload(),
            onError = (err) => this.showToast(err.message, 'error')
        } = options;

        if (!confirm('Bạn chắc chắn muốn xóa phân công này?')) {
            return;
        }

        this.showLoading('Đang xóa phân công...');

        try {
            await assignmentAPI.deleteAssignment(scheduleId, employeeId);
            this.hideLoading();
            this.showToast('Xóa phân công thành công!', 'success');
            onSuccess();
        } catch (error) {
            this.hideLoading();
            onError(error);
        }
    }

    /**
     * Handle form submit for create/update
     */
    async handleFormSubmit(form, action = 'create', options = {}) {
        const {
            onSuccess = () => window.location.reload(),
            onError = (err) => this.showToast(err.message, 'error')
        } = options;

        // Validate form
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        this.showLoading('Đang xử lý...');

        try {
            let result;
            
            if (action === 'create') {
                result = await assignmentAPI.createAssignment(data);
            } else if (action === 'update') {
                const { schedule_id, employee_id } = data;
                result = await assignmentAPI.updateAssignment(schedule_id, employee_id, data);
            }

            this.hideLoading();
            this.showToast('Thành công!', 'success');
            onSuccess(result);
        } catch (error) {
            this.hideLoading();
            onError(error);
        }
    }

    /**
     * Fetch and populate select options
     */
    async populateSelect(selectElement, source = 'employees', filters = {}) {
        try {
            this.showLoading('Đang tải dữ liệu...');
            
            let data;
            if (source === 'employees') {
                data = await assignmentAPI.getEmployees(filters.branchId);
            } else if (source === 'unassigned') {
                data = await assignmentAPI.getUnassignedSchedules();
            }

            this.hideLoading();

            // Clear existing options (keep placeholder)
            const placeholder = selectElement.querySelector('[value=""]');
            selectElement.innerHTML = '';
            if (placeholder) {
                selectElement.appendChild(placeholder);
            }

            // Add new options
            if (data.data && Array.isArray(data.data)) {
                data.data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id || item.ID_TK;
                    option.textContent = item.name || item.HO_TEN || item.title;
                    selectElement.appendChild(option);
                });
            }

            selectElement.disabled = false;
        } catch (error) {
            this.hideLoading();
            this.showToast('Không thể tải dữ liệu: ' + error.message, 'error');
            selectElement.disabled = true;
        }
    }

    /**
     * Update stats display
     */
    async refreshStats(containerSelector) {
        try {
            const stats = await assignmentAPI.getStats();
            const container = document.querySelector(containerSelector);
            
            if (!container || !stats.data) return;

            // Update stat elements
            Object.entries(stats.data).forEach(([key, value]) => {
                const element = container.querySelector(`[data-stat="${key}"]`);
                if (element) {
                    element.textContent = value.toLocaleString('vi-VN');
                }
            });
        } catch (error) {
            console.error('Failed to refresh stats:', error);
        }
    }

    /**
     * Setup AJAX delete buttons
     * Usage: Add data-delete="scheduleId|employeeId" to delete buttons
     */
    setupDeleteButtons() {
        document.querySelectorAll('[data-delete]').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const [scheduleId, employeeId] = btn.dataset.delete.split('|');
                await this.handleDelete(scheduleId, employeeId);
            });
        });
    }

    /**
     * Setup form auto-submit via AJAX
     * Usage: Add data-ajax-form="action" to forms
     */
    setupAjaxForms() {
        document.querySelectorAll('[data-ajax-form]').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const action = form.dataset.ajaxForm || 'create';
                await this.handleFormSubmit(form, action);
            });
        });
    }
}

// Create global instance
const assignmentAjax = new AssignmentAjaxHelper();

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    assignmentAjax.setupDeleteButtons();
    assignmentAjax.setupAjaxForms();
});
