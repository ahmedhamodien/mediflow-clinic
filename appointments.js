document.addEventListener('DOMContentLoaded', function() {
    // element
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const mainNav = document.querySelector('.main-nav');
    const searchInput = document.getElementById('searchInput');
    const appointmentsTable = document.getElementById('appointmentsTable');
    const modal = document.getElementById('appointmentModal');
    const closeModalBtn = document.querySelector('.close-modal');
    const appointmentDetails = document.getElementById('appointmentDetails');
    
    // State
    let appointmentsData = [];
    
    
    function init() {
        setupEventListeners();
        loadAppointmentsData();
    }
    
   
    function setupEventListeners() {
        // Mobile menu toggle
        if (mobileMenuBtn && mainNav) {
            mobileMenuBtn.addEventListener('click', toggleMobileMenu);
        }
        
        // Search functionality
        if (searchInput) {
            searchInput.addEventListener('input', filterAppointments);
        }
        
        // Modal close
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', closeModal);
        }
        
        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                closeModal();
            }
        });
        
        // Click outside to close mobile menu
        document.addEventListener('click', function(event) {
            if (mainNav && mainNav.classList.contains('active') && 
                !event.target.closest('.main-nav') && 
                !event.target.closest('.mobile-menu-btn')) {
                closeMobileMenu();
            }
        });
        
        // Handle form submissions with loading states
        document.querySelectorAll('form.inline-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    submitBtn.disabled = true;
                    
                    // Re-enable button after 3 seconds if submission fails
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 3000);
                }
            });
        });
    }
    
   
    // manage data
    function loadAppointmentsData() {
        // Extract data from table rows
        const rows = appointmentsTable.querySelectorAll('tbody tr');
        appointmentsData = Array.from(rows).map(row => {
            const cells = row.querySelectorAll('td');
            return {
                id: cells[0]?.textContent.replace('#', '') || '',
                patient: cells[1]?.textContent || '',
                doctor: cells[2]?.textContent || '',
                specialization: cells[3]?.textContent || '',
                clinic: cells[4]?.textContent || '',
                date: cells[5]?.textContent || '',
                time: cells[6]?.textContent || '',
                status: cells[7]?.querySelector('.status-badge')?.textContent || '',
                statusClass: cells[7]?.querySelector('.status-badge')?.className || ''
            };
        });
    }
    
    // search function
    function filterAppointments() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const rows = appointmentsTable.querySelectorAll('tbody tr');
        
        if (!searchTerm) {
            // Show all rows
            rows.forEach(row => {
                row.style.display = '';
            });
            return;
        }
        
        rows.forEach((row, index) => {
            const appointment = appointmentsData[index];
            if (!appointment) return;
            
            // Check if search term matches any field
            const matches = Object.values(appointment).some(value => 
                String(value).toLowerCase().includes(searchTerm)
            );
            
            row.style.display = matches ? '' : 'none';
        });
    }
    
    // model functions
    function viewAppointment(appointmentId) {
        // In a real application, you would fetch appointment details via AJAX
        // For now, we'll simulate with the data we have
        const appointment = appointmentsData.find(a => a.id == appointmentId);
        
        if (appointment) {
            const modalContent = `
                <div class="appointment-details">
                    <div class="detail-group">
                        <h3><i class="fas fa-id-card"></i> Appointment Information</h3>
                        <div class="detail-row">
                            <span class="detail-label">Appointment ID:</span>
                            <span class="detail-value">#${appointment.id}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value"><span class="status-badge ${appointment.statusClass}">${appointment.status}</span></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Date:</span>
                            <span class="detail-value">${appointment.date}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Time:</span>
                            <span class="detail-value">${appointment.time}</span>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <h3><i class="fas fa-user-injured"></i> Patient Details</h3>
                        <div class="detail-row">
                            <span class="detail-label">Name:</span>
                            <span class="detail-value">${appointment.patient}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Contact:</span>
                            <span class="detail-value">+1-555-XXXX</span>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <h3><i class="fas fa-user-md"></i> Doctor Details</h3>
                        <div class="detail-row">
                            <span class="detail-label">Name:</span>
                            <span class="detail-value">${appointment.doctor}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Specialization:</span>
                            <span class="detail-value">${appointment.specialization}</span>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <h3><i class="fas fa-hospital"></i> Clinic Information</h3>
                        <div class="detail-row">
                            <span class="detail-label">Clinic:</span>
                            <span class="detail-value">${appointment.clinic}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Address:</span>
                            <span class="detail-value">123 Healthcare Blvd, City Center</span>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <h3><i class="fas fa-file-medical"></i> Additional Information</h3>
                        <div class="detail-row">
                            <span class="detail-label">Reason for Visit:</span>
                            <span class="detail-value">Routine checkup</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Notes:</span>
                            <span class="detail-value">Follow-up appointment scheduled</span>
                        </div>
                    </div>
                </div>
                
                <style>
                    .appointment-details {
                        display: flex;
                        flex-direction: column;
                        gap: 25px;
                    }
                    
                    .detail-group {
                        background: #f8f9fa;
                        padding: 20px;
                        border-radius: 10px;
                        border-left: 4px solid #0047AB;
                    }
                    
                    .detail-group h3 {
                        color: #0047AB;
                        margin-bottom: 15px;
                        font-size: 1.2rem;
                        display: flex;
                        align-items: center;
                        gap: 10px;
                    }
                    
                    .detail-row {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        padding: 10px 0;
                        border-bottom: 1px solid #e9ecef;
                    }
                    
                    .detail-row:last-child {
                        border-bottom: none;
                    }
                    
                    .detail-label {
                        font-weight: 500;
                        color: #666;
                    }
                    
                    .detail-value {
                        color: #333;
                        text-align: right;
                    }
                </style>
            `;
            
            appointmentDetails.innerHTML = modalContent;
            openModal();
        }
    }
    
    function openModal() {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    
    // mobile menu 
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
    
    // additional utility functions
    function showNotification(message, type = 'success') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
            <button class="close-notification">&times;</button>
        `;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Add close functionality
        notification.querySelector('.close-notification').addEventListener('click', () => {
            notification.remove();
        });
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
    
    // initialize
    init();
});