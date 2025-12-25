/**
 * Invoice API Client
 * Helper functions for calling invoice API endpoints
 */

class InvoiceAPI {
    constructor(basePath = '/StygianBlue/api/invoices.php') {
        this.basePath = basePath;
    }

    /**
     * Get list of invoices with filters
     * @param {Object} params - Query parameters
     * @returns {Promise}
     */
    async getInvoices(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = `${this.basePath}${queryString ? '?' + queryString : ''}`;
        
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching invoices:', error);
            return {
                success: false,
                message: 'Lỗi tải danh sách hóa đơn',
                error: error.message
            };
        }
    }

    /**
     * Get invoice details
     * @param {number} invoiceId - Invoice ID
     * @returns {Promise}
     */
    async getInvoiceDetail(invoiceId) {
        const url = `${this.basePath}?id_hd=${invoiceId}`;
        
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching invoice detail:', error);
            return {
                success: false,
                message: 'Lỗi tải chi tiết hóa đơn',
                error: error.message
            };
        }
    }

    /**
     * Confirm payment manually
     * @param {number} invoiceId - Invoice ID
     * @returns {Promise}
     */
    async confirmPayment(invoiceId) {
        const url = `${this.basePath}/${invoiceId}/confirm`;
        
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error confirming payment:', error);
            return {
                success: false,
                message: 'Lỗi xác nhận thanh toán',
                error: error.message
            };
        }
    }

    /**
     * Process refund request
     * @param {number} invoiceId - Invoice ID
     * @param {Object} refundData - Refund details
     * @returns {Promise}
     */
    async processRefund(invoiceId, refundData) {
        const url = `${this.basePath}/${invoiceId}/refund`;
        
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(refundData)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error processing refund:', error);
            return {
                success: false,
                message: 'Lỗi xử lý hoàn tiền',
                error: error.message
            };
        }
    }
}

// Create global instance
const invoiceAPI = new InvoiceAPI();
