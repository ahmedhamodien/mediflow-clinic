// ============================================
// ADMIN DOCTORS JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const addDoctorBtn = document.getElementById('addDoctorBtn');
    const clearFiltersBtn = document.getElementById('clearFilters');
    const selectAllCheckbox = document.getElementById('selectAll');
    const doctorCheckboxes = document.querySelectorAll('.doctor-checkbox');
    const viewButtons = document.querySelectorAll('.view-btn');
    const editButtons = document.querySelectorAll('.edit-btn');
    const deleteButtons = document.querySelectorAll('.delete-btn');
    const scheduleButtons = document.querySelectorAll('.schedule-btn');
    const modalCloseButtons = document.querySelectorAll('.close-modal');
    const modals = document.querySelectorAll('.modal');
    const addDoctorModal = document.getElementById('addDoctorModal');
    const viewDoctorModal = document.getElementById('viewDoctorModal');
    const searchInput = document.querySelector('.search-box input');
    const messageCloseButtons = document.querySelectorAll('.close-btn');
    const exportBtn = document.getElementById('exportBtn');
    const togglePasswordBtn = document.querySelector('.toggle-password');
    const passwordInput = document.getElementById('password');
    
    // State
    let selectedDoctors = new Set();
    
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
        // Add Doctor Modal
        if (addDoctorBtn) {
            addDoctorBtn.addEventListener('click', () => {
                showModal(addDoctorModal);
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
        
        // View Doctor Details
        viewButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const doctorId = this.getAttribute('data-id');
                viewDoctorDetails(doctorId);
            });
        });
        
        // Edit Doctor
        editButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const doctorId = this.getAttribute('data-id');
                editDoctor(doctorId);
            });
        });
        
        // Manage Schedule
        scheduleButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const doctorId = this.getAttribute('data-id');
                manageSchedule(doctorId);
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
            exportBtn.addEventListener('click', exportDoctors);
        }
        
        // Toggle password visibility
        if (togglePasswordBtn && passwordInput) {
            togglePasswordBtn.addEventListener('click', () => {
                togglePasswordVisibility(passwordInput, togglePasswordBtn);
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', handleKeyboardShortcuts);
        
        // Form validation for add doctor
        const addDoctorForm = document.querySelector('.modal-form');
        if (addDoctorForm) {
            addDoctorForm.addEventListener('submit', handleAddDoctorFormSubmit);
        }
    }
    
    // ============================================
    // CHECKBOX LOGIC
    // ============================================
    function setupCheckboxLogic() {
        // Select All checkbox
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                doctorCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                    updateSelectedDoctors(checkbox, isChecked);
                });
                updateBulkActions();
            });
        }
        
        // Individual doctor checkboxes
        doctorCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateSelectedDoctors(this, this.checked);
                updateSelectAllState();
                updateBulkActions();
            });
        });
    }
    
    function updateSelectedDoctors(checkbox, isChecked) {
        const doctorId = checkbox.getAttribute('data-id');
        if (isChecked) {
            selectedDoctors.add(doctorId);
        } else {
            selectedDoctors.delete(doctorId);
        }
    }
    
    function updateSelectAllState() {
        if (!selectAllCheckbox) return;
        
        const allChecked = doctorCheckboxes.length > 0 && 
                          Array.from(doctorCheckboxes).every(cb => cb.checked);
        const someChecked = Array.from(doctorCheckboxes).some(cb => cb.checked);
        
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
    // DOCTOR OPERATIONS
    // ============================================
    function viewDoctorDetails(doctorId) {
        // In a real application, this would be an AJAX call
        // For now, we'll show sample data
        const doctorDetails = document.getElementById('doctorDetails');
        doctorDetails.innerHTML = `
            <div class="doctor-detail-view">
                <div class="doctor-header">
                    <img src="https://ui-avatars.com/api/?name=Dr+Robert+Williams&background=0047AB&color=fff" 
                         alt="Doctor" class="detail-avatar">
                    <div>
                        <h4>Dr. Robert Williams</h4>
                        <p>Doctor ID: #${doctorId} | License: MED001234</p>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h5><i class="fas fa-info-circle"></i> Professional Information</h5>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Specialization:</span>
                            <span class="detail-value badge">General Physician</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Experience:</span>
                            <span class="detail-value">15 years</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Consultation Fee:</span>
                            <span class="detail-value">$120.00</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value status-badge active">Active</span>
                        </div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h5><i class="fas fa-clinic-medical"></i> Clinic Assignments</h5>
                    <div class="clinic-list">
                        <div class="clinic-item">
                            <strong>City Medical Center</strong>
                            <span>Primary Clinic</span>
                        </div>
                        <div class="clinic-item">
                            <strong>Westside Clinic</strong>
                            <span>Secondary Clinic</span>
                        </div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h5><i class="fas fa-calendar-alt"></i> Availability</h5>
                    <div class="availability-grid">
                        <div class="availability-item">
                            <span class="day">Monday</span>
                            <span class="time">09:00 AM - 05:00 PM</span>
                        </div>
                        <div class="availability-item">
                            <span class="day">Tuesday</span>
                            <span class="time">09:00 AM - 05:00 PM</span>
                        </div>
                        <div class="availability-item">
                            <span class="day">Wednesday</span>
                            <span class="time">09:00 AM - 05:00 PM</span>
                        </div>
                        <div class="availability-item">
                            <span class="day">Thursday</span>
                            <span class="time">10:00 AM - 02:00 PM</span>
                        </div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h5><i class="fas fa-chart-line"></i> Statistics</h5>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-number">0</span>
                            <span class="stat-label">Total Appointments</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">0</span>
                            <span class="stat-label">Upcoming</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">0</span>
                            <span class="stat-label">Completed</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">0%</span>
                            <span class="stat-label">Occupancy Rate</span>
                        </div>
                    </div>
                </div>
                
                <div class="detail-actions">
                    <button class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                    <button class="btn btn-outline">
                        <i class="fas fa-calendar-alt"></i> Manage Schedule
                    </button>
                    <button class="btn btn-outline">
                        <i class="fas fa-file-medical"></i> View Appointments
                    </button>
                </div>
            </div>
        `;
        
        showModal(viewDoctorModal);
    }
    
    function editDoctor(doctorId) {
        // This would redirect to an edit page or open an edit modal
        alert(`Edit doctor #${doctorId} - This would open an edit form in a real application.`);
    }
    
    function manageSchedule(doctorId) {
        // This would redirect to schedule management page
        alert(`Manage schedule for doctor #${doctorId} - This would open schedule management in a real application.`);
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
        if (selectedDoctors.size > 0) {
            if (bulkActions) bulkActions.style.display = 'flex';
        } else {
            if (bulkActions) bulkActions.style.display = 'none';
        }
    }
    
    // ============================================
    // EXPORT FUNCTION
    // ============================================
    function exportDoctors() {
        const format = prompt('Select export format:\n1. CSV\n2. Excel\n3. PDF', '1');
        
        switch(format) {
            case '1':
                alert('Exporting as CSV...');
                // In real app: window.location.href = 'export_doctors.php?format=csv';
                break;
            case '2':
                alert('Exporting as Excel...');
                // In real app: window.location.href = 'export_doctors.php?format=excel';
                break;
            case '3':
                alert('Exporting as PDF...');
                // In real app: window.location.href = 'export_doctors.php?format=pdf';
                break;
            default:
                return;
        }
    }
    
    // ============================================
    // FORM HANDLING
    // ============================================
    function handleAddDoctorFormSubmit(event) {
        const form = event.target;
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        // Basic validation
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                highlightError(field);
            } else {
                removeError(field);
            }
        });
        
        // Email validation
        const emailField = form.querySelector('input[type="email"]');
        if (emailField && emailField.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailField.value)) {
                isValid = false;
                highlightError(emailField, 'Please enter a valid email address');
            }
        }
        
        // Phone validation for Egypt
        const phoneField = form.querySelector('input[type="tel"]');
        if (phoneField && phoneField.value) {
            const phoneRegex = /^(01)[0-9]{9}$/;
            if (!phoneRegex.test(phoneField.value)) {
                isValid = false;
                highlightError(phoneField, 'Please enter a valid Egyptian phone (01XXXXXXXXX)');
            }
        }
        
        if (!isValid) {
            event.preventDefault();
            alert('Please fill in all required fields correctly.');
        }
    }
    
    function highlightError(field, message = 'This field is required') {
        field.style.borderColor = '#dc3545';
        
        // Add error message if not already present
        if (!field.parentNode.querySelector('.field-error')) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'field-error';
            errorDiv.style.color = '#dc3545';
            errorDiv.style.fontSize = '0.85rem';
            errorDiv.style.marginTop = '5px';
            errorDiv.textContent = message;
            field.parentNode.appendChild(errorDiv);
        }
    }
    
    function removeError(field) {
        field.style.borderColor = '';
        const errorDiv = field.parentNode.querySelector('.field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
    
    // ============================================
    // UTILITY FUNCTIONS
    // ============================================
    function togglePasswordVisibility(input, button) {
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        
        const icon = button.querySelector('i');
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    }
    
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
        
        // Ctrl/Cmd + A: Select all doctors
        if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
            e.preventDefault();
            if (selectAllCheckbox) {
                selectAllCheckbox.click();
            }
        }
        
        // Ctrl/Cmd + N: Add new doctor
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            if (addDoctorBtn) {
                addDoctorBtn.click();
            }
        }
    }
    
    // ============================================
    // INITIALIZE
    // ============================================
    init();
});