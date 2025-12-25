/**
 * Assignment Management API Client
 * Handles all AJAX calls for assignment operations
 */

class AssignmentAPI {
    constructor(baseUrl = '/api') {
        this.baseUrl = baseUrl;
    }

    /**
     * Get all assignments with filters
     */
    async getAssignments(filters = {}) {
        const params = new URLSearchParams();
        
        if (filters.search) params.append('search', filters.search);
        if (filters.status) params.append('status', filters.status);
        if (filters.date_from) params.append('date_from', filters.date_from);
        if (filters.date_to) params.append('date_to', filters.date_to);
        if (filters.sort) params.append('sort', filters.sort);
        if (filters.page) params.append('page', filters.page);
        
        const url = `${this.baseUrl}/assignments?${params.toString()}`;
        return this._fetch(url);
    }

    /**
     * Create new assignment
     */
    async createAssignment(data) {
        return this._fetch(`${this.baseUrl}/assignments`, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    /**
     * Update assignment
     */
    async updateAssignment(scheduleId, employeeId, data) {
        return this._fetch(`${this.baseUrl}/assignments/${scheduleId}/${employeeId}`, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    /**
     * Delete assignment
     */
    async deleteAssignment(scheduleId, employeeId) {
        return this._fetch(`${this.baseUrl}/assignments/${scheduleId}/${employeeId}`, {
            method: 'DELETE'
        });
    }

    /**
     * Get unassigned schedules
     */
    async getUnassignedSchedules() {
        return this._fetch(`${this.baseUrl}/assignments/unassigned/list`);
    }

    /**
     * Get employees for assignment
     */
    async getEmployees(branchId = null) {
        const url = branchId 
            ? `${this.baseUrl}/assignments/employees/list?branch=${branchId}`
            : `${this.baseUrl}/assignments/employees/list`;
        return this._fetch(url);
    }

    /**
     * Get assignment statistics
     */
    async getStats() {
        return this._fetch(`${this.baseUrl}/assignments/stats`);
    }

    /**
     * Private fetch wrapper with error handling
     */
    async _fetch(url, options = {}) {
        const defaultHeaders = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };

        const config = {
            ...options,
            headers: {
                ...defaultHeaders,
                ...(options.headers || {})
            }
        };

        try {
            const response = await fetch(url, config);
            
            if (!response.ok) {
                const error = await response.json().catch(() => ({
                    message: `HTTP ${response.status}`
                }));
                throw new Error(error.message || 'API request failed');
            }

            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }
}

// Create global instance
const assignmentAPI = new AssignmentAPI('/api');
