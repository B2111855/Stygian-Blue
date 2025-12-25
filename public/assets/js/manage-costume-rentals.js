/**
 * Page-Specific Integration: manage_costume_rentals.js
 * Handles rental status management, cost calculation, conflict detection
 * Date: December 3, 2025
 */

// ============================================================
// 1. COST CALCULATION HELPERS
// ============================================================

/**
 * Calculate rental cost based on date range and costume prices
 * Shows result in a formatted display
 */
async function calculateRentalCost(orderId) {
    const form = document.querySelector(`form input[value="${orderId}"]`)?.closest('form');
    if (!form) {
        showNotification('Form không tồn tại', 'error');
        return;
    }

    showSpinner('Tính toán chi phí...');

    try {
        const result = await apiPost('/rentals/calculate-cost', {
            order_id: orderId
        });

        if (result.success && result.data) {
            const totalCostInput = form.querySelector('input[name="actual_total"]');
            if (totalCostInput) {
                totalCostInput.value = result.data.total_cost || '';
            }
            showNotification(`Chi phí: ${formatCurrency(result.data.total_cost)}`, 'success');
        } else {
            showNotification('Không thể tính toán', 'error');
        }
    } catch (error) {
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        showSpinner(false);
    }
}

/**
 * Calculate late fee for overdue rental
 */
async function calculateLateFee(orderId, returnedAt) {
    if (!returnedAt) {
        showNotification('Vui lòng nhập ngày trả', 'warning');
        return;
    }

    showSpinner('Tính phụ phí trễ hạn...');

    try {
        const result = await apiPost('/rentals/calculate-late-fee', {
            order_id: orderId,
            returned_at: returnedAt
        });

        if (result.success && result.data) {
            showNotification(
                `Phụ phí: ${formatCurrency(result.data.late_fee)} (Quá hạn: ${result.data.days_late} ngày)`,
                'info'
            );
        } else {
            showNotification('Không có phụ phí (đúng hạn)', 'success');
        }
    } catch (error) {
        showNotification('Lỗi: ' + error.message, 'error');
    } finally {
        showSpinner(false);
    }
}

// ============================================================
// 2. CONFLICT DETECTION
// ============================================================

/**
 * Check for booking conflicts for a rental order
 */
async function detectBookingConflicts(orderId) {
    try {
        const result = await apiPost('/rentals/detect-conflicts', {
            order_id: orderId
        });

        if (result.success && result.data) {
            if (result.data.has_conflicts) {
                let conflictHtml = '<div class="bg-rose-50 border border-rose-200 rounded-lg p-4 space-y-2">';
                conflictHtml += '<div class="font-semibold text-rose-700">⚠️ Phát hiện xung đột lịch:</div>';
                
                result.data.conflicts?.forEach(conflict => {
                    conflictHtml += `
                        <div class="text-sm text-rose-600">
                            <strong>${escapeHtml(conflict.costume_name)}</strong>
                            <br />
                            Từ ${conflict.start_date} đến ${conflict.end_date}
                            <br />
                            (Đơn #${conflict.other_order_id})
                        </div>
                    `;
                });

                conflictHtml += '</div>';
                
                const alertArea = document.querySelector('[data-conflict-alert]');
                if (alertArea) {
                    alertArea.innerHTML = conflictHtml;
                }

                return result.data.conflicts || [];
            }
        }
        return [];
    } catch (error) {
        console.error('Error detecting conflicts:', error);
        return [];
    }
}

// ============================================================
// 3. SEARCH & FILTER
// ============================================================

/**
 * Handle real-time search with debounce
 */
let searchTimeout;
function handleSearchInput(e) {
    clearTimeout(searchTimeout);
    const searchTerm = e.target.value.trim();

    searchTimeout = setTimeout(() => {
        // Submit form naturally (backwards compatible)
        const form = e.target.closest('form');
        if (form) {
            form.submit();
        }
    }, 500);
}

// ============================================================
// 4. PAGE INITIALIZATION
// ============================================================

/**
 * Initialize page on load
 */
document.addEventListener('DOMContentLoaded', () => {
    // Setup detail toggles
    document.querySelectorAll('[data-toggle-detail]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const detailId = btn.getAttribute('data-toggle-detail');
            const detailRow = document.getElementById(detailId);
            if (detailRow) {
                detailRow.classList.toggle('hidden');
            }
        });
    });

    // Setup status change forms (in detail rows)
    // These forms already exist in the detail view and POST to same page
    // We keep the current POST behavior for backwards compatibility
    // Future enhancement: convert to API calls
    document.querySelectorAll('form[data-action="update_rental_status"]').forEach(form => {
        // Optionally add client-side validation here
        form.addEventListener('submit', (e) => {
            const newStatus = form.querySelector('select[name="new_status"]')?.value;
            const returnedAt = form.querySelector('input[name="returned_at"]')?.value;
            const actualTotal = form.querySelector('input[name="actual_total"]')?.value;

            // Validate return fields for da_tra/tre_hen transitions
            if (['da_tra', 'tre_hen'].includes(newStatus)) {
                if (!returnedAt) {
                    e.preventDefault();
                    alert('Vui lòng nhập ngày trả thực tế');
                    return false;
                }
                if (!actualTotal) {
                    e.preventDefault();
                    alert('Vui lòng nhập tổng chi phí thực tế');
                    return false;
                }
            }

            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Đang xử lý...';
                
                // Reset after response (handled by page redirect)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }, 3000);
            }
        });
    });

    // Setup search input
    const searchInput = document.querySelector('[data-action="search"]');
    if (searchInput) {
        searchInput.addEventListener('input', handleSearchInput);
    }

    // Setup flash message close button
    document.querySelectorAll('[data-flash-close]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.target.closest('[role="alert"]')?.remove();
        });
    });

    // Log successful initialization
    console.log('✓ manage-costume-rentals.js initialized');
});
