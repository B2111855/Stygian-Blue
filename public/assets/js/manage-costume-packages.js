/**
 * manage_costume_packages.php - API Integration
 * 
 * Migrates form submissions from POST to API calls
 * Uses api-client.js utilities
 */

(function() {
  'use strict';

  // ============================================
  // INITIALIZATION
  // ============================================

  document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing manage_costume_packages API integration');
    
    // Setup form handlers
    setupCreatePackageForm();
    setupToggleStatusForms();
    setupDeletePackageForms();
    setupSearchForm();
    
    console.log('Initialization complete');
  });

  // ============================================
  // CREATE PACKAGE MODAL
  // ============================================

  function setupCreatePackageForm() {
    // Modal is now handled by PHP (id="create-modal")
    // This function is kept for future API migration if needed
    return;
  }

  // The following functions are disabled as the form is handled by PHP
  // They can be re-enabled when migrating to full API-based approach

  function createPackageModal() {
    // Disabled - PHP handles modal rendering
    return null;
  }

  async function handleCreatePackageSubmit(e) {
    // Disabled - PHP handles form submission
    return;
  }

  // ============================================
  // TOGGLE STATUS
  // ============================================

  function setupToggleStatusForms() {
    // Disabled: let server-side POST handle status toggle
    return;
  }

  async function handleToggleStatus(e) {
    // Disabled
    return;
  }

  // ============================================
  // DELETE PACKAGE
  // ============================================

  function setupDeletePackageForms() {
    // Disabled: let server-side POST handle hard delete
    return;
  }

  async function handleDeletePackage(e) {
    // Disabled
    return;
  }

  // ============================================
  // SEARCH FORM
  // ============================================

  function setupSearchForm() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
      const searchInput = form.querySelector('input[name="search"]');
      
      // Only setup for the search/filter form (not for other forms)
      if (searchInput && !form.querySelector('input[name="action"]')) {
        // Keep default form submission behavior for search
        // The server will handle pagination and filtering
      }
    });
  }

  // ============================================
  // LOAD BRANCHES INTO DROPDOWN
  // ============================================

  async function loadBranchesIntoSelect(selectElement) {
    try {
      const branches = await apiGet('/branches');
      
      if (!Array.isArray(branches)) {
        console.error('Branches not an array', branches);
        return;
      }

      // Clear existing options (keep the first one)
      while (selectElement.options.length > 1) {
        selectElement.remove(1);
      }

      // Add branch options
      branches.forEach(branch => {
        const option = document.createElement('option');
        option.value = branch.ID_CN;
        option.textContent = branch.TEN_CN;
        selectElement.appendChild(option);
      });
    } catch (error) {
      console.error('Failed to load branches:', error);
      showNotification('Không thể tải danh sách chi nhánh', 'error');
    }
  }

  // ============================================
  // AUTO-LOAD BRANCHES WHEN MODAL OPENS
  // ============================================

  // Monitor modal visibility and load branches on first open
  let branchesLoaded = false;

  const observer = new MutationObserver(() => {
    const modal = document.getElementById('create-package-modal');
    if (modal && !modal.classList.contains('hidden') && !branchesLoaded) {
      const branchSelect = modal.querySelector('select[name="ID_CN_OWNER"]');
      if (branchSelect) {
        loadBranchesIntoSelect(branchSelect);
        branchesLoaded = true;
      }
    }
  });

  document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('create-package-modal');
    if (modal) {
      observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
    }
  });

})();
