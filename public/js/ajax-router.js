/**
 * AJAX Router Helper
 * Giải quyết vấn đề route phức tạp với cấu trúc thư mục nested
 * 
 * Sử dụng:
 * const api = new AjaxRouter();
 * api.post('confirm_completion', { id_lichhen: 123 })
 *    .then(data => console.log(data))
 *    .catch(err => console.error(err));
 */

class AjaxRouter {
    constructor(basePath = './') {
        // Tự động xác định base path dựa trên thư mục hiện tại
        this.basePath = this.detectBasePath(basePath);
        this.timeout = 30000; // 30 giây timeout
    }

    /**
     * Xác định base path tự động
     * @param {string} suggestedPath - Đường dẫn gợi ý
     * @returns {string} Base path để gọi API
     */
    detectBasePath(suggestedPath = './') {
        // Nếu chúng ta đang trong /app/admin/components/
        // Thì các file API cũng nằm trong /app/admin/components/
        return suggestedPath;
    }

    /**
     * Gửi request GET
     * @param {string} endpoint - Tên endpoint/file
     * @param {object} params - Query parameters
     * @returns {Promise}
     */
    async get(endpoint, params = {}) {
        const url = new URL(this.buildUrl(endpoint), window.location.origin);
        Object.entries(params).forEach(([key, value]) => {
            url.searchParams.append(key, value);
        });

        return this.fetchWithTimeout(url.toString(), {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        });
    }

    /**
     * Gửi request POST
     * @param {string} endpoint - Tên endpoint/file
     * @param {object} data - Data gửi
     * @param {string} dataType - Kiểu data ('form' hoặc 'json')
     * @returns {Promise}
     */
    async post(endpoint, data = {}, dataType = 'form') {
        const options = {
            method: 'POST',
            headers: {}
        };

        if (dataType === 'json') {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data);
        } else {
            options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            options.body = new URLSearchParams(data).toString();
        }

        return this.fetchWithTimeout(this.buildUrl(endpoint), options);
    }

    /**
     * Xây dựng URL đầy đủ
     * @param {string} endpoint - Endpoint
     * @returns {string} URL đầy đủ
     */
    buildUrl(endpoint) {
        // Nếu endpoint bắt đầu bằng /, thì là absolute path
        if (endpoint.startsWith('/')) {
            return endpoint;
        }
        // Nếu endpoint chứa .php, thì là filename
        if (endpoint.includes('.php')) {
            return this.basePath + endpoint;
        }
        // Nếu không, thêm .php vào cuối
        return this.basePath + endpoint + '.php';
    }

    /**
     * Fetch với timeout
     * @param {string} url - URL
     * @param {object} options - Fetch options
     * @returns {Promise}
     */
    async fetchWithTimeout(url, options = {}) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), this.timeout);

        try {
            const response = await fetch(url, {
                ...options,
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            // Kiểm tra HTTP status
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            // Parse JSON response
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            }

            return await response.text();
        } catch (error) {
            clearTimeout(timeoutId);
            if (error.name === 'AbortError') {
                throw new Error('Request timeout - API không phản hồi trong thời gian quy định');
            }
            throw error;
        }
    }
}

/**
 * Toast Notification Helper
 * Hiển thị thông báo tạm thời
 */
class ToastNotification {
    static show(message, type = 'info', duration = 4000) {
        const notification = document.createElement('div');
        const bgColor = type === 'success' ? 'bg-emerald-600' :
                        type === 'error' ? 'bg-rose-600' :
                        type === 'warning' ? 'bg-amber-600' :
                        'bg-blue-600';

        notification.className = `fixed top-4 right-4 max-w-md rounded-lg shadow-lg p-4 text-white z-50 ${bgColor}`;
        notification.textContent = message;
        notification.setAttribute('role', 'alert');

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, duration);

        return notification;
    }

    static success(message, duration = 4000) {
        return this.show(message, 'success', duration);
    }

    static error(message, duration = 4000) {
        return this.show(message, 'error', duration);
    }

    static warning(message, duration = 4000) {
        return this.show(message, 'warning', duration);
    }

    static info(message, duration = 4000) {
        return this.show(message, 'info', duration);
    }
}

/**
 * Confirmation Dialog Helper
 */
class ConfirmDialog {
    static async show(title = 'Xác nhận', message = 'Bạn có chắc chắn?', options = {}) {
        return new Promise((resolve) => {
            const result = confirm(`${title}\n\n${message}`);
            resolve(result);
        });
    }
}

// Export cho sử dụng toàn cục
window.AjaxRouter = AjaxRouter;
window.Toast = ToastNotification;
window.Confirm = ConfirmDialog;
