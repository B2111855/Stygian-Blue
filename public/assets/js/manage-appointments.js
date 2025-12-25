/**
 * Manage Appointments JavaScript
 * Handles all appointment CRUD operations and interactions
 * 
 * Handlers:
 * - handleAddAppointmentSubmit() - Create appointment
 * - handleEditAppointmentSubmit() - Edit appointment
 * - deleteAppointment() - Delete with confirmation
 * - handleStatusSubmit() - Update appointment status
 * - handleAddServiceSubmit() - Add service to appointment
 * - deleteService() - Remove service from appointment
 * - handleAddNoteSubmit() - Add note to appointment
 * - deleteNote() - Delete note from appointment
 * - handleAppointmentSearch() - Real-time search with debounce
 * - DOMContentLoaded listener - Initialize all handlers
 */

// Add appointment form submission
async function handleAddAppointmentSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const customerId = form.querySelector('[name="customer_id"]')?.value;
    const serviceId = form.querySelector('[name="service_id"]')?.value;
    const startTime = form.querySelector('[name="start_time"]')?.value;
    const address = form.querySelector('[name="address"]')?.value;
    const branchId = form.querySelector('[name="branch_id"]')?.value;
    
    // Client-side validation
    if (!customerId) {
        showNotification('Please select a customer', 'error');
        return;
    }
    if (!serviceId) {
        showNotification('Please select a service', 'error');
        return;
    }
    if (!startTime) {
        showNotification('Please select a date and time', 'error');
        return;
    }
    if (!address || address.trim().length < 5) {
        showNotification('Address must be at least 5 characters', 'error');
        return;
    }
    
    try {
        showSpinner();
        
        const formData = new FormData();
        formData.append('customer_id', customerId);
        formData.append('service_id', serviceId);
        formData.append('start_time', startTime);
        formData.append('address', address);
        if (branchId) {
            formData.append('branch_id', branchId);
        }
        
        const response = await fetch('/api/appointments', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (!response.ok || !result.success) {
            showNotification(result.message || 'Failed to create appointment', 'error');
            return;
        }
        
        showNotification('Appointment created successfully', 'success');
        form.reset();
        
        // Redirect to appointment detail page
        setTimeout(() => {
            window.location.href = `?page=appointment_detail&ID_LICHHEN=${result.data.id}`;
        }, 1000);
        
    } catch (error) {
        console.error('Error creating appointment:', error);
        showNotification('Error creating appointment. Please try again.', 'error');
    }
}

// Edit appointment form submission
async function handleEditAppointmentSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const appointmentId = form.dataset.appointmentId;
    
    if (!appointmentId) {
        showNotification('Appointment ID not found', 'error');
        return;
    }
    
    const customerId = form.querySelector('[name="customer_id"]')?.value;
    const serviceId = form.querySelector('[name="service_id"]')?.value;
    const startTime = form.querySelector('[name="start_time"]')?.value;
    const address = form.querySelector('[name="address"]')?.value;
    const branchId = form.querySelector('[name="branch_id"]')?.value;
    
    // Client-side validation
    if (!customerId) {
        showNotification('Please select a customer', 'error');
        return;
    }
    if (!serviceId) {
        showNotification('Please select a service', 'error');
        return;
    }
    if (!startTime) {
        showNotification('Please select a date and time', 'error');
        return;
    }
    if (!address || address.trim().length < 5) {
        showNotification('Address must be at least 5 characters', 'error');
        return;
    }
    
    try {
        showSpinner();
        
        const formData = new FormData();
        formData.append('customer_id', customerId);
        formData.append('service_id', serviceId);
        formData.append('start_time', startTime);
        formData.append('address', address);
        if (branchId) {
            formData.append('branch_id', branchId);
        }
        
        const response = await fetch(`/api/appointments/${appointmentId}`, {
            method: 'PUT',
            body: formData
        });
        
        const result = await response.json();
        
        if (!response.ok || !result.success) {
            showNotification(result.message || 'Failed to update appointment', 'error');
            return;
        }
        
        showNotification('Appointment updated successfully', 'success');
        
        // Reload page after success
        setTimeout(() => {
            location.reload();
        }, 1000);
        
    } catch (error) {
        console.error('Error updating appointment:', error);
        showNotification('Error updating appointment. Please try again.', 'error');
    }
}

// Delete appointment with confirmation
async function deleteAppointment(appointmentId, customerName) {
    if (!appointmentId) {
        showNotification('Appointment ID not found', 'error');
        return;
    }
    
    const message = `Are you sure you want to delete the appointment for ${customerName}? This action cannot be undone.`;
    
    showConfirm(message, async () => {
        try {
            showSpinner();
            
            const response = await fetch(`/api/appointments/${appointmentId}`, {
                method: 'DELETE'
            });
            
            const result = await response.json();
            
            if (!response.ok || !result.success) {
                showNotification(result.message || 'Failed to delete appointment', 'error');
                return;
            }
            
            showNotification('Appointment deleted successfully', 'success');
            
            // Reload page after success
            setTimeout(() => {
                location.reload();
            }, 1000);
            
        } catch (error) {
            console.error('Error deleting appointment:', error);
            showNotification('Error deleting appointment. Please try again.', 'error');
        }
    });
}

// Update appointment status
async function handleStatusSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const appointmentId = form.dataset.appointmentId;
    const status = form.querySelector('[name="status"]')?.value;
    
    if (!appointmentId) {
        showNotification('Appointment ID not found', 'error');
        return;
    }
    
    if (!status) {
        showNotification('Please select a status', 'error');
        return;
    }
    
    try {
        showSpinner();
        
        const formData = new FormData();
        formData.append('status', status);
        
        const response = await fetch(`/api/appointments/${appointmentId}/status`, {
            method: 'PUT',
            body: formData
        });
        
        const result = await response.json();
        
        if (!response.ok || !result.success) {
            showNotification(result.message || 'Failed to update status', 'error');
            return;
        }
        
        showNotification('Status updated successfully', 'success');
        
        // Reload page after success
        setTimeout(() => {
            location.reload();
        }, 1000);
        
    } catch (error) {
        console.error('Error updating status:', error);
        showNotification('Error updating status. Please try again.', 'error');
    }
}

// Add service to appointment
async function handleAddServiceSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const appointmentId = form.dataset.appointmentId;
    const serviceId = form.querySelector('[name="service_id"]')?.value;
    
    if (!appointmentId) {
        showNotification('Appointment ID not found', 'error');
        return;
    }
    
    if (!serviceId) {
        showNotification('Please select a service', 'error');
        return;
    }
    
    try {
        showSpinner();
        
        const formData = new FormData();
        formData.append('service_id', serviceId);
        
        const response = await fetch(`/api/appointments/${appointmentId}/services`, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (!response.ok || !result.success) {
            showNotification(result.message || 'Failed to add service', 'error');
            return;
        }
        
        showNotification('Service added successfully', 'success');
        
        // Reset form and reload
        form.reset();
        setTimeout(() => {
            location.reload();
        }, 1000);
        
    } catch (error) {
        console.error('Error adding service:', error);
        showNotification('Error adding service. Please try again.', 'error');
    }
}

// Delete service from appointment
async function deleteService(appointmentId, serviceId, serviceName) {
    if (!appointmentId || !serviceId) {
        showNotification('Appointment or Service ID not found', 'error');
        return;
    }
    
    const message = `Remove ${serviceName} from this appointment?`;
    
    showConfirm(message, async () => {
        try {
            showSpinner();
            
            const response = await fetch(`/api/appointments/${appointmentId}/services/${serviceId}`, {
                method: 'DELETE'
            });
            
            const result = await response.json();
            
            if (!response.ok || !result.success) {
                showNotification(result.message || 'Failed to remove service', 'error');
                return;
            }
            
            showNotification('Service removed successfully', 'success');
            
            // Reload page after success
            setTimeout(() => {
                location.reload();
            }, 1000);
            
        } catch (error) {
            console.error('Error removing service:', error);
            showNotification('Error removing service. Please try again.', 'error');
        }
    });
}

// Add note to appointment
async function handleAddNoteSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const appointmentId = form.dataset.appointmentId;
    const noteText = form.querySelector('[name="note_text"]')?.value;
    
    if (!appointmentId) {
        showNotification('Appointment ID not found', 'error');
        return;
    }
    
    if (!noteText || noteText.trim().length === 0) {
        showNotification('Note text is required', 'error');
        return;
    }
    
    if (noteText.length > 1000) {
        showNotification('Note must not exceed 1000 characters', 'error');
        return;
    }
    
    try {
        showSpinner();
        
        const formData = new FormData();
        formData.append('note_text', noteText);
        
        const response = await fetch(`/api/appointments/${appointmentId}/notes`, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (!response.ok || !result.success) {
            showNotification(result.message || 'Failed to add note', 'error');
            return;
        }
        
        showNotification('Note added successfully', 'success');
        
        // Reset form and reload
        form.reset();
        setTimeout(() => {
            location.reload();
        }, 1000);
        
    } catch (error) {
        console.error('Error adding note:', error);
        showNotification('Error adding note. Please try again.', 'error');
    }
}

// Delete note from appointment
async function deleteNote(appointmentId, noteId, notePreview) {
    if (!appointmentId || !noteId) {
        showNotification('Appointment or Note ID not found', 'error');
        return;
    }
    
    const message = `Delete this note: "${notePreview.substring(0, 50)}..."?`;
    
    showConfirm(message, async () => {
        try {
            showSpinner();
            
            const response = await fetch(`/api/appointments/${appointmentId}/notes/${noteId}`, {
                method: 'DELETE'
            });
            
            const result = await response.json();
            
            if (!response.ok || !result.success) {
                showNotification(result.message || 'Failed to delete note', 'error');
                return;
            }
            
            showNotification('Note deleted successfully', 'success');
            
            // Reload page after success
            setTimeout(() => {
                location.reload();
            }, 1000);
            
        } catch (error) {
            console.error('Error deleting note:', error);
            showNotification('Error deleting note. Please try again.', 'error');
        }
    });
}

// Search appointments with debounce
let appointmentSearchTimeout;
function handleAppointmentSearch(e) {
    clearTimeout(appointmentSearchTimeout);
    
    appointmentSearchTimeout = setTimeout(() => {
        const form = e.target.closest('form');
        if (form) {
            form.submit();
        }
    }, 500);
}

// Filter form submission
async function handleAppointmentFilter(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    
    // Build query string
    const params = new URLSearchParams();
    
    // Add filter parameters from form
    const customerFilter = form.querySelector('[name="ten_khach"]')?.value;
    const serviceFilter = form.querySelector('[name="ten_dv"]')?.value;
    const dateFilter = form.querySelector('[name="ngay"]')?.value;
    const statusFilter = form.querySelector('[name="trangthai"]')?.value;
    const branchFilter = form.querySelector('[name="chi_nhanh"]')?.value;
    
    if (customerFilter) params.append('ten_khach', customerFilter);
    if (serviceFilter) params.append('ten_dv', serviceFilter);
    if (dateFilter) params.append('ngay', dateFilter);
    if (statusFilter) params.append('trangthai', statusFilter);
    if (branchFilter) params.append('chi_nhanh', branchFilter);
    
    // Keep page parameter
    const currentPage = new URLSearchParams(window.location.search).get('page_num');
    if (currentPage) params.append('page_num', currentPage);
    
    // Navigate with filters
    window.location.href = `?page=appointments&${params.toString()}`;
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('✓ manage-appointments.js initialized');
    
    // Register form submit handlers
    const addAppointmentForm = document.querySelector('form[data-action="add_appointment"]');
    if (addAppointmentForm) {
        addAppointmentForm.addEventListener('submit', handleAddAppointmentSubmit);
        console.log('✓ Add appointment form registered');
    }
    
    const editAppointmentForm = document.querySelector('form[data-action="edit_appointment"]');
    if (editAppointmentForm) {
        editAppointmentForm.addEventListener('submit', handleEditAppointmentSubmit);
        console.log('✓ Edit appointment form registered');
    }
    
    const statusForm = document.querySelector('form[data-action="status_appointment"]');
    if (statusForm) {
        statusForm.addEventListener('submit', handleStatusSubmit);
        console.log('✓ Status form registered');
    }
    
    const addServiceForm = document.querySelector('form[data-action="add_service"]');
    if (addServiceForm) {
        addServiceForm.addEventListener('submit', handleAddServiceSubmit);
        console.log('✓ Add service form registered');
    }
    
    const addNoteForm = document.querySelector('form[data-action="add_note"]');
    if (addNoteForm) {
        addNoteForm.addEventListener('submit', handleAddNoteSubmit);
        console.log('✓ Add note form registered');
    }
    
    const filterForm = document.querySelector('form[data-action="filter_appointments"]');
    if (filterForm) {
        filterForm.addEventListener('submit', handleAppointmentFilter);
        console.log('✓ Filter form registered');
    }
    
    // Register search input handler
    const searchInput = document.querySelector('input[data-action="search_appointments"]');
    if (searchInput) {
        searchInput.addEventListener('input', handleAppointmentSearch);
        console.log('✓ Search input registered');
    }
    
    // Register delete buttons (delegated event handling)
    document.addEventListener('click', function(e) {
        // Delete appointment button
        if (e.target.closest('[data-action="delete_appointment"]')) {
            e.preventDefault();
            const button = e.target.closest('[data-action="delete_appointment"]');
            const appointmentId = button.dataset.appointmentId;
            const customerName = button.dataset.customerName;
            deleteAppointment(appointmentId, customerName);
        }
        
        // Delete service button
        if (e.target.closest('[data-action="delete_service"]')) {
            e.preventDefault();
            const button = e.target.closest('[data-action="delete_service"]');
            const appointmentId = button.dataset.appointmentId;
            const serviceId = button.dataset.serviceId;
            const serviceName = button.dataset.serviceName;
            deleteService(appointmentId, serviceId, serviceName);
        }
        
        // Delete note button
        if (e.target.closest('[data-action="delete_note"]')) {
            e.preventDefault();
            const button = e.target.closest('[data-action="delete_note"]');
            const appointmentId = button.dataset.appointmentId;
            const noteId = button.dataset.noteId;
            const noteText = button.dataset.noteText;
            deleteNote(appointmentId, noteId, noteText);
        }
    });
    
    console.log('✓ Event listeners initialized');
    console.log('✓ All appointment handlers ready');
});
