// ============================================
// ADMIN PATIENTS JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const addPatientBtn = document.getElementById('addPatientBtn');
    const clearFiltersBtn = document.getElementById('clearFilters');
    const selectAllCheckbox = document.getElementById('selectAll');
    const patientCheckboxes = document.querySelectorAll('.patient-checkbox');
    const viewButtons = document.querySelectorAll('.view-btn');
    const editButtons = document.querySelectorAll('.edit-btn');
    const deleteButtons = document.querySelectorAll('.delete-btn');
    const modalCloseButtons = document.querySelectorAll('.close-modal');
    const modals = document.querySelectorAll('.modal');
    const addPatientModal = document.getElementById('addPatientModal');
    const viewPatientModal = document.getElementById('viewPatientModal');
    const searchInput = document.querySelector('.search-box input');
    const messageCloseButtons = document.querySelectorAll('.close-btn');
    const exportBtn = document.getElementById('exportBtn');
    
    // State
    let selectedPatients = new Set();
    
    // ============================================
    // INITIALIZATION
    // ============================================
    function init() {
        setupEventListeners();
        setupCheckboxLogic();
        autoCloseMessages();
    }
    
    // ============================================
    // EVENT LISTENERS
    // ============================================
    function setupEventListeners() {
        // Add Patient Modal
        if (addPatientBtn) {
            addPatientBtn.addEventListener('click', () => {
                showModal(addPatientModal);
            });
        }
        
        // Clear Filters
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', clearFilters);
        }
        
        // Modal Close
        modalCloseButtons.forEach(btn => {
            btn.addEventListener('click', closeAllModals);
        });
        
        // Close modal on background click
        modals.forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeAllModals();
                }
            });
        });
        
        // View Patient Details
        viewButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const patientId = this.getAttribute('data-id');
                viewPatientDetails(patientId);
            });
        });
        
        // Edit Patient
        editButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const patientId = this.getAttribute('data-id');
                editPatient(patientId);
            });
        });
        
        // Search with debounce
        if (searchInput) {
            searchInput.addEventListener('input', debounce(function() {
                this.closest('form').submit();
            }, 500));
        }
        
        // Message close buttons
        messageCloseButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.message').style.display = 'none';
            });
        });
        
        // Export button
        if (exportBtn) {
            exportBtn.addEventListener('click', exportPatients);
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', handleKeyboardShortcuts);
    }
    
    // ============================================
    // CHECKBOX LOGIC
    // ============================================
    function setupCheckboxLogic() {
        // Select All checkbox
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                patientCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                    updateSelectedPatients(checkbox, isChecked);
                });
                updateBulkActions();
            });
        }
        
        // Individual patient checkboxes
        patientCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateSelectedPatients(this, this.checked);
                updateSelectAllState();
                updateBulkActions();
            });
        });
    }
    
    function updateSelectedPatients(checkbox, isChecked) {
        const patientId = checkbox.getAttribute('data-id');
        if (isChecked) {
            selectedPatients.add(patientId);
        } else {
            selectedPatients.delete(patientId);
        }
    }
    
    function updateSelectAllState() {
        if (!selectAllCheckbox) return;
        
        const allChecked = patientCheckboxes.length > 0 && 
                          Array.from(patientCheckboxes).every(cb => cb.checked);
        const someChecked = Array.from(patientCheckboxes).some(cb => cb.checked);
        
        selectAllCheckbox.checked = allChecked;
        selectAllCheckbox.indeterminate = someChecked && !allChecked;
    }
    
    // ============================================
    // MODAL FUNCTIONS
    // ============================================
    function showModal(modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeAllModals() {
        modals.forEach(modal => modal.classList.remove('active'));
        document.body.style.overflow = '';
    }
    
    // ============================================
    // PATIENT OPERATIONS
    // ============================================
    function viewPatientDetails(patientId) {
        // In a real application, this would be an AJAX call
        // For now, we'll show sample data
        const patientDetails = document.getElementById('patientDetails');
        patientDetails.innerHTML = `
            <div class="patient-detail-view">
                <div class="patient-header">
                    <img src="https://ui-avatars.com/api/?name=Patient+Name&background=0047AB&color=fff" 
                         alt="Patient" class="detail-avatar">
                    <div>
                        <h4>John Doe</h4>
                        <p>Patient ID: #${patientId}</p>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h5><i class="fas fa-info-circle"></i> Basic Information</h5>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value">john.doe@example.com</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Phone:</span>
                            <span class="detail-value">+1-555-1001</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Date of Birth:</span>
                            <span class="detail-value">Jan 15, 1990 (34 years)</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Gender:</span>
                            <span class="detail-value">Male</span>
                        </div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h5><i class="fas fa-map-marker-alt"></i> Address</h5>
                    <p>123 Main Street, City, State 12345</p>
                </div>
                
                <div class="detail-section">
                    <h5><i class="fas fa-exclamation-triangle"></i> Emergency Contact</h5>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Name:</span>
                            <span class="detail-value">Jane Doe</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Phone:</span>
                            <span class="detail-value">+1-555-1002</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Relationship:</span>
                            <span class="detail-value">Spouse</span>
                        </div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h5><i class="fas fa-chart-line"></i> Statistics</h5>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Total Appointments:</span>
                            <span class="detail-value badge">12</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Last Visit:</span>
                            <span class="detail-value">Dec 15, 2024</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value status-badge active">Active</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Member Since:</span>
                            <span class="detail-value">Jan 10, 2023</span>
                        </div>
                    </div>
                </div>
                
                <div class="detail-actions">
                    <button class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                    <button class="btn btn-outline">
                        <i class="fas fa-calendar-plus"></i> Schedule Appointment
                    </button>
                    <button class="btn btn-outline">
                        <i class="fas fa-file-medical"></i> View Medical Records
                    </button>
                </div>
            </div>
        `;
        
        showModal(viewPatientModal);
    }
    
    function editPatient(patientId) {
        // This would redirect to an edit page or open an edit modal
        alert(`Edit patient #${patientId} - This would open an edit form in a real application.`);
    }
    
    // ============================================
    // FILTER FUNCTIONS
    // ============================================
    function clearFilters() {
        const form = document.querySelector('.filter-form');
        const inputs = form.querySelectorAll('input, select');
        inputs.forEach(input => {
            if (input.type === 'text' || input.type === 'search') {
                input.value = '';
            } else if (input.type === 'select-one') {
                input.selectedIndex = 0;
            }
        });
        form.submit();
    }
    
    // ============================================
    // BULK ACTIONS
    // ============================================
    function updateBulkActions() {
        // This would enable/disable bulk action buttons based on selection
        const bulkActions = document.querySelector('.bulk-actions');
        if (selectedPatients.size > 0) {
            if (bulkActions) bulkActions.style.display = 'flex';
        } else {
            if (bulkActions) bulkActions.style.display = 'none';
        }
    }
    
    // ============================================
    // EXPORT FUNCTION
    // ============================================
    function exportPatients() {
        const format = prompt('Select export format:\n1. CSV\n2. Excel\n3. PDF', '1');
        
        switch(format) {
            case '1':
                alert('Exporting as CSV...');
                // In real app: window.location.href = 'export.php?format=csv';
                break;
            case '2':
                alert('Exporting as Excel...');
                // In real app: window.location.href = 'export.php?format=excel';
                break;
            case '3':
                alert('Exporting as PDF...');
                // In real app: window.location.href = 'export.php?format=pdf';
                break;
            default:
                return;
        }
    }
    
    // ============================================
    // UTILITY FUNCTIONS
    // ============================================
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    function autoCloseMessages() {
        setTimeout(() => {
            document.querySelectorAll('.message').forEach(msg => {
                msg.style.opacity = '0';
                setTimeout(() => {
                    msg.style.display = 'none';
                }, 300);
            });
        }, 5000);
    }
    
    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    function handleKeyboardShortcuts(e) {
        // Ctrl/Cmd + F: Focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            if (searchInput) searchInput.focus();
        }
        
        // Escape: Close modals
        if (e.key === 'Escape') {
            closeAllModals();
        }
        
        // Ctrl/Cmd + A: Select all patients
        if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
            e.preventDefault();
            if (selectAllCheckbox) {
                selectAllCheckbox.click();
            }
        }
    }
    
    // ============================================
    // INITIALIZE
    // ============================================
    init();
});