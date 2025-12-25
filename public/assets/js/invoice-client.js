/**
 * Invoice AJAX Client
 * Handles all invoice operations via AJAX
 */

class InvoiceClient {
    constructor(ajaxUrl = '/StygianBlue/app/admin/ajax_invoices.php') {
        this.ajaxUrl = ajaxUrl;
    }

    /**
     * Confirm invoice payment
     */
    async confirmPayment(invoiceId) {
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'confirm_payment',
                    id_hd: invoiceId
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Lỗi xác nhận thanh toán');
            }

            return data;
        } catch (error) {
            console.error('confirmPayment error:', error);
            throw error;
        }
    }

    /**
     * Refund invoice
     */
    async refundInvoice(invoiceId, refundAmount, refundReason = '') {
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'refund_invoice',
                    id_hd: invoiceId,
                    refund_amount: refundAmount,
                    refund_reason: refundReason
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Lỗi hoàn tiền');
            }

            return data;
        } catch (error) {
            console.error('refundInvoice error:', error);
            throw error;
        }
    }

    /**
     * Get invoice detail
     */
    async getInvoiceDetail(invoiceId) {
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_invoice_detail',
                    id_hd: invoiceId
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Lỗi lấy thông tin hóa đơn');
            }

            return data;
        } catch (error) {
            console.error('getInvoiceDetail error:', error);
            throw error;
        }
    }

    /**
     * Bulk confirm multiple invoices
     */
    async bulkConfirm(invoiceIds) {
        try {
            const formData = new URLSearchParams();
            formData.append('action', 'bulk_confirm');
            invoiceIds.forEach(id => formData.append('ids[]', id));

            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString()
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Lỗi xác nhận hàng loạt');
            }

            return data;
        } catch (error) {
            console.error('bulkConfirm error:', error);
            throw error;
        }
    }
}

// Initialize globally
const invoiceClient = new InvoiceClient();

/**
 * Notification System
 */
function showNotification(message, type = 'success', duration = 5000) {
    const container = document.getElementById('notificationContainer') || createNotificationContainer();
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    const iconSvg = type === 'success' 
        ? '<svg class="notification-icon" fill="none" stroke="#10b981" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
        : '<svg class="notification-icon" fill="none" stroke="#ef4444" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
    
    notification.innerHTML = `
        ${iconSvg}
        <div class="notification-content">${message}</div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M.293.293a1 1 0 111.414 1.414L1.414 2.12l.293.293a1 1 0 11-1.414 1.414L0 3.534.293.293zm15.414 0a1 1 0 01-1.414 1.414l-.293-.293.293.293a1 1 0 01-1.414-1.414l.293-.293-.293.293a1 1 0 011.414-1.414l.293.293-.293-.293z"/>
            </svg>
        </button>
        <div class="notification-progress"></div>
    `;
    
    container.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => notification.classList.add('show'), 10);
    
    // Auto remove
    setTimeout(() => {
        notification.classList.add('hide');
        setTimeout(() => notification.remove(), 300);
    }, duration);
}

function createNotificationContainer() {
    const container = document.createElement('div');
    container.id = 'notificationContainer';
    container.className = 'notification-container';
    document.body.appendChild(container);
    return container;
}

/**
 * Action handlers
 */
async function confirmInvoicePayment(invoiceId) {
    try {
        if (!confirm('Xác nhận đánh dấu hóa đơn #' + invoiceId + ' là đã thanh toán?')) {
            return;
        }
        
        const result = await invoiceClient.confirmPayment(invoiceId);
        
        if (result.success) {
            showNotification(result.message, 'success');
            // Reload table after 1 second
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(result.message || 'Lỗi không xác định', 'error');
        }
    } catch (error) {
        showNotification(error.message || 'Lỗi kết nối', 'error');
    }
}

async function refundInvoiceAmount(invoiceId) {
    const amount = prompt('Nhập số tiền cần hoàn (VND):');
    if (!amount || isNaN(amount) || parseInt(amount) <= 0) {
        showNotification('Số tiền không hợp lệ', 'error');
        return;
    }
    
    const reason = prompt('Lý do hoàn tiền (tuỳ chọn):');
    
    try {
        const result = await invoiceClient.refundInvoice(invoiceId, parseInt(amount), reason || '');
        
        if (result.success) {
            showNotification(result.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(result.message || 'Lỗi hoàn tiền', 'error');
        }
    } catch (error) {
        showNotification(error.message || 'Lỗi kết nối', 'error');
    }
}

// Bulk actions
async function bulkConfirmInvoices() {
    const checkboxes = document.querySelectorAll('.invoice-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    if (ids.length === 0) {
        showNotification('Chưa chọn hóa đơn nào', 'error');
        return;
    }
    
    if (!confirm('Xác nhận thanh toán cho ' + ids.length + ' hóa đơn?')) {
        return;
    }
    
    try {
        const result = await invoiceClient.bulkConfirm(ids);
        
        if (result.success) {
            showNotification(result.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(result.message || 'Lỗi xác nhận hàng loạt', 'error');
        }
    } catch (error) {
        showNotification(error.message || 'Lỗi kết nối', 'error');
    }
}
