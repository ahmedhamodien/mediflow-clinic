// ============================================
// PATIENT RECORDS PAGE JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    const modalTriggers = document.querySelectorAll('[data-modal]');
    const modals = document.querySelectorAll('.modal');
    const modalCloses = document.querySelectorAll('.modal-close');
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const mainNav = document.querySelector('.main-nav');
    const closeBtns = document.querySelectorAll('.close-btn');
    
    // State
    let currentTab = 'medical-records';
    
    // ============================================
    // INITIALIZATION
    // ============================================
    function init() {
        setupEventListeners();
        setupModals();
        
        // Auto-close messages after 5 seconds
        autoCloseMessages();
        
        // Set current date for date inputs in modals
        setCurrentDate();
        
        // Highlight active patient in list
        highlightActivePatient();
    }
    
    // ============================================
    // EVENT LISTENERS
    // ============================================
    function setupEventListeners() {
        // Tab switching
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => switchTab(btn.dataset.tab));
        });
        
        // Mobile menu toggle
        if (mobileMenuBtn && mainNav) {
            mobileMenuBtn.addEventListener('click', toggleMobileMenu);
        }
        
        // Close message buttons
        closeBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.message').style.display = 'none';
            });
        });
        
        // Click outside to close mobile menu
        document.addEventListener('click', function(event) {
            if (mainNav && mainNav.classList.contains('active') && 
                !event.target.closest('.main-nav') && 
                !event.target.closest('.mobile-menu-btn')) {
                closeMobileMenu();
            }
        });
    }
    
    // ============================================
    // TAB MANAGEMENT
    // ============================================
    function switchTab(tabId) {
        // Update tab buttons
        tabBtns.forEach(btn => {
            if (btn.dataset.tab === tabId) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
        
        // Update tab contents
        tabContents.forEach(content => {
            if (content.id === tabId) {
                content.classList.add('active');
            } else {
                content.classList.remove('active');
            }
        });
        
        currentTab = tabId;
    }
    
    // ============================================
    // MODAL MANAGEMENT
    // ============================================
    function setupModals() {
        // Open modal triggers
        modalTriggers.forEach(trigger => {
            trigger.addEventListener('click', function() {
                const modalId = this.dataset.modal;
                openModal(modalId);
            });
        });
        
        // Close modal buttons
        modalCloses.forEach(close => {
            close.addEventListener('click', function() {
                const modal = this.closest('.modal');
                closeModal(modal);
            });
        });
        
        // Close modal on outside click
        modals.forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this);
                }
            });
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.active');
                if (openModal) {
                    closeModal(openModal);
                }
            }
        });
        
        // Form validation for modals
        setupModalForms();
    }
    
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Focus on first input
            const firstInput = modal.querySelector('input, select, textarea');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        }
    }
    
    function closeModal(modal) {
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            
            // Clear form errors
            clearModalErrors(modal);
            
            // Clear form if it's an add form
            const form = modal.querySelector('form');
            if (form) {
                form.reset();
                // Reset date to today
                const dateInputs = form.querySelectorAll('input[type="date"]');
                dateInputs.forEach(input => {
                    if (input.name === 'record_date' || input.name === 'diagnosed_date' || 
                        input.name === 'med_start_date') {
                        input.value = new Date().toISOString().split('T')[0];
                    }
                });
            }
        }
    }
    
    function setupModalForms() {
        // Add validation for medical record form
        const recordForm = document.querySelector('#addRecordModal form');
        if (recordForm) {
            recordForm.addEventListener('submit', function(e) {
                if (!validateRecordForm()) {
                    e.preventDefault();
                }
            });
        }
        
        // Add validation for condition form
        const conditionForm = document.querySelector('#addConditionModal form');
        if (conditionForm) {
            conditionForm.addEventListener('submit', function(e) {
                if (!validateConditionForm()) {
                    e.preventDefault();
                }
            });
        }
        
        // Add validation for medication form
        const medicationForm = document.querySelector('#addMedicationModal form');
        if (medicationForm) {
            medicationForm.addEventListener('submit', function(e) {
                if (!validateMedicationForm()) {
                    e.preventDefault();
                }
            });
        }
    }
    
    function validateRecordForm() {
        const recordDate = document.getElementById('record_date');
        const diagnosis = document.getElementById('diagnosis');
        let isValid = true;
        
        clearModalErrors(document.getElementById('addRecordModal'));
        
        if (!recordDate.value) {
            showModalError(recordDate, 'Record date is required');
            isValid = false;
        }
        
        if (!diagnosis.value.trim()) {
            showModalError(diagnosis, 'Diagnosis is required');
            isValid = false;
        }
        
        // Validate height if provided
        const height = document.getElementById('height');
        if (height.value && (height.value < 0 || height.value > 300)) {
            showModalError(height, 'Height must be between 0 and 300 cm');
            isValid = false;
        }
        
        // Validate weight if provided
        const weight = document.getElementById('weight');
        if (weight.value && (weight.value < 0 || weight.value > 300)) {
            showModalError(weight, 'Weight must be between 0 and 300 kg');
            isValid = false;
        }
        
        return isValid;
    }
    
    function validateConditionForm() {
        const conditionName = document.getElementById('condition_name');
        const diagnosedDate = document.getElementById('diagnosed_date');
        let isValid = true;
        
        clearModalErrors(document.getElementById('addConditionModal'));
        
        if (!conditionName.value.trim()) {
            showModalError(conditionName, 'Condition name is required');
            isValid = false;
        }
        
        if (!diagnosedDate.value) {
            showModalError(diagnosedDate, 'Diagnosed date is required');
            isValid = false;
        }
        
        return isValid;
    }
    
    function validateMedicationForm() {
        const medName = document.getElementById('new_medication_name');
        const dosage = document.getElementById('new_dosage');
        const frequency = document.getElementById('new_frequency');
        const startDate = document.getElementById('med_start_date');
        let isValid = true;
        
        clearModalErrors(document.getElementById('addMedicationModal'));
        
        if (!medName.value.trim()) {
            showModalError(medName, 'Medication name is required');
            isValid = false;
        }
        
        if (!dosage.value.trim()) {
            showModalError(dosage, 'Dosage is required');
            isValid = false;
        }
        
        if (!frequency.value.trim()) {
            showModalError(frequency, 'Frequency is required');
            isValid = false;
        }
        
        if (!startDate.value) {
            showModalError(startDate, 'Start date is required');
            isValid = false;
        }
        
        // Validate end date if provided
        const endDate = document.getElementById('med_end_date');
        if (endDate.value && startDate.value) {
            if (new Date(endDate.value) < new Date(startDate.value)) {
                showModalError(endDate, 'End date must be after start date');
                isValid = false;
            }
        }
        
        return isValid;
    }
    
    function showModalError(input, message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-text';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        errorDiv.style.color = '#dc3545';
        errorDiv.style.fontSize = '0.85rem';
        errorDiv.style.marginTop = '5px';
        
        const parent = input.parentNode;
        parent.appendChild(errorDiv);
        input.style.borderColor = '#dc3545';
    }
    
    function clearModalErrors(modal) {
        const errors = modal.querySelectorAll('.error-text');
        errors.forEach(error => error.remove());
        
        const inputs = modal.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.style.borderColor = '#e1e5eb';
        });
    }
    
    // ============================================
    // HELPER FUNCTIONS
    // ============================================
    function toggleMobileMenu() {
        mainNav.classList.toggle('active');
        const icon = mobileMenuBtn.querySelector('i');
        icon.classList.toggle('fa-bars');
        icon.classList.toggle('fa-times');
    }
    
    function closeMobileMenu() {
        mainNav.classList.remove('active');
        const icon = mobileMenuBtn.querySelector('i');
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
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
    
    function setCurrentDate() {
        const today = new Date().toISOString().split('T')[0];
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            const dateInputs = modal.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                if (!input.value && (input.name === 'record_date' || 
                    input.name === 'diagnosed_date' || 
                    input.name === 'med_start_date')) {
                    input.value = today;
                }
            });
        });
    }
    
    function highlightActivePatient() {
        // This function is handled by PHP in the template
        // It adds 'active' class to the current patient item
    }
    
    // ============================================
    // RECORD VIEWING FUNCTIONS
    // ============================================
    window.viewRecordDetails = function(recordId) {
        // In a real application, this would fetch and display detailed record information
        alert(`Viewing detailed record for ID: ${recordId}\n\nThis feature would show a detailed view of the medical record in a full-screen modal or new page.`);
        
        // Example implementation:
        // fetch(`get_record_details.php?id=${recordId}`)
        //     .then(response => response.json())
        //     .then(data => {
        //         // Show modal with detailed information
        //         showRecordModal(data);
        //     })
        //     .catch(error => {
        //         console.error('Error:', error);
        //         alert('Failed to load record details');
        //     });
    };
    
    window.viewConditionDetails = function(conditionId) {
        alert(`Viewing condition details for ID: ${conditionId}\n\nThis would show complete information about the medical condition, including treatment history and progress notes.`);
    };
    
    window.viewMedicationDetails = function(medicationId) {
        alert(`Viewing medication details for ID: ${medicationId}\n\nThis would show complete prescription details, refill history, and patient compliance information.`);
    };
    
    // ============================================
    // PRINT FUNCTIONALITY
    // ============================================
    window.printPatientSummary = function() {
        // Get patient information
        const patientName = document.querySelector('.patient-header-info h2').textContent;
        const patientMeta = document.querySelector('.patient-meta-large').textContent;
        
        // Create print content
        const printContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Patient Summary - ${patientName}</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
                    .patient-info { margin-bottom: 30px; }
                    .section { margin-bottom: 25px; }
                    .section-title { background: #f0f0f0; padding: 10px; font-weight: bold; margin-bottom: 10px; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .print-date { text-align: right; font-size: 12px; color: #666; margin-top: 40px; }
                    @media print {
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>MediFlow Clinic</h1>
                    <h2>Patient Medical Summary</h2>
                </div>
                
                <div class="patient-info">
                    <h3>Patient Information</h3>
                    <p><strong>Name:</strong> ${patientName}</p>
                    <p><strong>Details:</strong> ${patientMeta}</p>
                    <p><strong>Doctor:</strong> ${document.querySelector('.record-doctor')?.textContent || 'Current Doctor'}</p>
                </div>
                
                <div class="section">
                    <div class="section-title">Medical Summary</div>
                    <p><em>This is a summary of the patient's medical records as of ${new Date().toLocaleDateString()}</em></p>
                </div>
                
                <div class="print-date">
                    Printed on: ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}
                </div>
                
                <div class="no-print" style="margin-top: 30px;">
                    <button onclick="window.print()">Print Now</button>
                    <button onclick="window.close()">Close</button>
                </div>
                
                <script>
                    window.onload = function() {
                        // Auto-print after a short delay
                        setTimeout(() => {
                            window.print();
                        }, 500);
                    };
                </script>
            </body>
            </html>
        `;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(printContent);
        printWindow.document.close();
    };
    
    // ============================================
    // FORM AUTO-COMPLETE SUGGESTIONS
    // ============================================
    function setupAutoComplete() {
        const diagnosisInput = document.getElementById('diagnosis');
        const conditionInput = document.getElementById('condition_name');
        const medicationInput = document.getElementById('new_medication_name');
        
        // In a real application, these would fetch suggestions from the server
        // based on common medical terms or previous entries
        
        if (diagnosisInput) {
            diagnosisInput.addEventListener('input', debounce(function() {
                // Fetch diagnosis suggestions
            }, 300));
        }
        
        if (conditionInput) {
            conditionInput.addEventListener('input', debounce(function() {
                // Fetch condition suggestions
            }, 300));
        }
        
        if (medicationInput) {
            medicationInput.addEventListener('input', debounce(function() {
                // Fetch medication suggestions
            }, 300));
        }
    }
    
    // Utility function for debouncing
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // ============================================
    // INITIALIZE
    // ============================================
    init();
});