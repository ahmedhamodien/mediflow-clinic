// ============================================
// DOCTOR DASHBOARD JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    const refreshBtn = document.getElementById('refreshUpcoming');
    const notificationBell = document.querySelector('.notification-bell');
    const userMenu = document.querySelector('.user-menu');
    const actionButtons = document.querySelectorAll('.btn-action');
    
    // State
    let sidebarOpen = true;
    
    // ============================================
    // INITIALIZATION
    // ============================================
    function init() {
        setupEventListeners();
        checkMobileView();
        updateTimeDisplay();
        setupAutoRefresh();
        markCurrentDaySchedule();
    }
    
    // ============================================
    // EVENT LISTENERS
    // ============================================
    function setupEventListeners() {
        // Menu toggle
        if (menuToggle) {
            menuToggle.addEventListener('click', toggleSidebar);
        }
        
        // Refresh upcoming appointments
        if (refreshBtn) {
            refreshBtn.addEventListener('click', refreshUpcomingAppointments);
        }
        
        // Notification bell
        if (notificationBell) {
            notificationBell.addEventListener('click', showNotifications);
        }
        
        // User menu
        if (userMenu) {
            userMenu.addEventListener('click', toggleUserMenu);
        }
        
        // Appointment action buttons
        actionButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const appointmentId = this.getAttribute('data-id');
                showAppointmentActions(appointmentId);
            });
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 992 && sidebarOpen) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    closeSidebar();
                }
            }
        });
        
        // Window resize
        window.addEventListener('resize', checkMobileView);
        
        // Keyboard shortcuts
        document.addEventListener('keydown', handleKeyboardShortcuts);
    }
    
    // ============================================
    // SIDEBAR FUNCTIONS
    // ============================================
    function toggleSidebar() {
        if (window.innerWidth <= 992) {
            sidebar.classList.toggle('active');
            sidebarOpen = sidebar.classList.contains('active');
        } else {
            if (sidebarOpen) {
                sidebar.style.width = '70px';
                mainContent.style.marginLeft = '70px';
                hideSidebarText();
            } else {
                sidebar.style.width = '250px';
                mainContent.style.marginLeft = '250px';
                showSidebarText();
            }
            sidebarOpen = !sidebarOpen;
        }
        
        // Update menu toggle icon
        const icon = menuToggle.querySelector('i');
        if (sidebarOpen) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-times');
        } else {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    }
    
    function closeSidebar() {
        sidebar.classList.remove('active');
        const icon = menuToggle.querySelector('i');
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
        sidebarOpen = false;
    }
    
    function hideSidebarText() {
        const navItems = sidebar.querySelectorAll('.sidebar-nav a span');
        const doctorInfo = sidebar.querySelector('.doctor-info div');
        
        navItems.forEach(item => item.style.display = 'none');
        if (doctorInfo) doctorInfo.style.display = 'none';
        
        // Center icons
        const sidebarNav = sidebar.querySelector('.sidebar-nav');
        sidebarNav.style.textAlign = 'center';
    }
    
    function showSidebarText() {
        const navItems = sidebar.querySelectorAll('.sidebar-nav a span');
        const doctorInfo = sidebar.querySelector('.doctor-info div');
        
        navItems.forEach(item => item.style.display = 'inline');
        if (doctorInfo) doctorInfo.style.display = 'block';
        
        // Reset text alignment
        const sidebarNav = sidebar.querySelector('.sidebar-nav');
        sidebarNav.style.textAlign = 'left';
    }
    
    function checkMobileView() {
        if (window.innerWidth <= 992) {
            sidebar.style.width = '';
            mainContent.style.marginLeft = '0';
            sidebar.classList.remove('active');
            sidebarOpen = false;
            
            // Reset sidebar text
            showSidebarText();
            const sidebarNav = sidebar.querySelector('.sidebar-nav');
            sidebarNav.style.textAlign = 'left';
        } else {
            if (!sidebarOpen) {
                sidebar.style.width = '70px';
                mainContent.style.marginLeft = '70px';
                hideSidebarText();
            } else {
                sidebar.style.width = '250px';
                mainContent.style.marginLeft = '250px';
                showSidebarText();
            }
        }
    }
    
    // ============================================
    // DASHBOARD FUNCTIONS
    // ============================================
    function refreshUpcomingAppointments() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        // Simulate API call
        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt"></i>';
            showToast('Upcoming appointments refreshed!', 'success');
            
            // Update badge count (simulated)
            const badge = document.querySelector('.notification-bell .badge');
            if (badge) {
                const currentCount = parseInt(badge.textContent) || 0;
                badge.textContent = Math.max(0, currentCount - 1);
            }
        }, 1000);
    }
    
    function showAppointmentActions(appointmentId) {
        // Create dropdown menu
        const dropdown = document.createElement('div');
        dropdown.className = 'action-dropdown';
        dropdown.innerHTML = `
            <button onclick="confirmAppointment(${appointmentId})">
                <i class="fas fa-check-circle"></i> Confirm
            </button>
            <button onclick="cancelAppointment(${appointmentId})">
                <i class="fas fa-times-circle"></i> Cancel
            </button>
            <button onclick="viewAppointmentDetails(${appointmentId})">
                <i class="fas fa-eye"></i> View Details
            </button>
            <button onclick="addMedicalNotes(${appointmentId})">
                <i class="fas fa-file-medical"></i> Add Notes
            </button>
        `;
        
        // Position and show dropdown
        dropdown.style.cssText = `
            position: absolute;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            z-index: 1000;
            padding: 10px;
            min-width: 180px;
        `;
        
        // Add event listener to close dropdown when clicking outside
        document.addEventListener('click', function closeDropdown(e) {
            if (!dropdown.contains(e.target) && !e.target.closest('.btn-action')) {
                dropdown.remove();
                document.removeEventListener('click', closeDropdown);
            }
        });
        
        document.body.appendChild(dropdown);
    }
    
    function showNotifications() {
        showToast('You have new notifications', 'info');
        
        // Update badge
        const badge = notificationBell.querySelector('.badge');
        if (badge) {
            badge.textContent = '0';
            badge.style.display = 'none';
        }
    }
    
    function toggleUserMenu() {
        // In production, this would show a dropdown menu
        showToast('User menu clicked', 'info');
    }
    
    // ============================================
    // HELPER FUNCTIONS
    // ============================================
    function updateTimeDisplay() {
        const timeElements = document.querySelectorAll('.sidebar-footer small:last-child');
        timeElements.forEach(el => {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
            el.innerHTML = `<i class="fas fa-clock"></i> ${timeString}`;
        });
    }
    
    function markCurrentDaySchedule() {
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const today = new Date().getDay();
        const todayName = days[today];
        
        const dayHeaders = document.querySelectorAll('.day-header strong');
        dayHeaders.forEach(header => {
            if (header.textContent.trim() === todayName) {
                header.parentElement.parentElement.style.background = 'rgba(52, 152, 219, 0.1)';
                header.parentElement.parentElement.style.borderLeft = '3px solid #3498db';
            }
        });
    }
    
    function setupAutoRefresh() {
        // Refresh dashboard every 10 minutes
        setInterval(() => {
            if (!document.hidden) {
                updateTimeDisplay();
                // In production, you could fetch new data here
                console.log('Dashboard auto-refresh at ' + new Date().toLocaleTimeString());
            }
        }, 600000); // 10 minutes
    }
    
    function showToast(message, type = 'info') {
        // Remove existing toasts
        document.querySelectorAll('.toast').forEach(toast => toast.remove());
        
        // Create toast
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <i class="fas fa-${getToastIcon(type)}"></i>
            <span>${message}</span>
            <button class="toast-close">&times;</button>
        `;
        
        // Add styles
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${getToastColor(type)};
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        
        document.body.appendChild(toast);
        
        // Add close button event
        toast.querySelector('.toast-close').addEventListener('click', () => {
            toast.remove();
        });
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
        
        // Add animations if not already present
        if (!document.querySelector('#toast-animations')) {
            const style = document.createElement('style');
            style.id = 'toast-animations';
            style.textContent = `
                @keyframes slideIn {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                
                @keyframes slideOut {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }
    
    function getToastIcon(type) {
        switch(type) {
            case 'success': return 'check-circle';
            case 'error': return 'exclamation-circle';
            case 'warning': return 'exclamation-triangle';
            default: return 'info-circle';
        }
    }
    
    function getToastColor(type) {
        switch(type) {
            case 'success': return '#28a745';
            case 'error': return '#dc3545';
            case 'warning': return '#ffc107';
            default: return '#17a2b8';
        }
    }
    
    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    function handleKeyboardShortcuts(e) {
        // Alt + M: Toggle menu
        if (e.altKey && e.key === 'm') {
            e.preventDefault();
            toggleSidebar();
        }
        
        // Alt + R: Refresh
        if (e.altKey && e.key === 'r') {
            e.preventDefault();
            refreshUpcomingAppointments.call(refreshBtn);
        }
        
        // Alt + N: Notifications
        if (e.altKey && e.key === 'n') {
            e.preventDefault();
            showNotifications();
        }
        
        // Escape: Close sidebar on mobile
        if (e.key === 'Escape' && window.innerWidth <= 992 && sidebarOpen) {
            closeSidebar();
        }
    }
    
    // ============================================
    // GLOBAL FUNCTIONS (for dropdown actions)
    // ============================================
    window.confirmAppointment = function(appointmentId) {
        if (confirm('Confirm this appointment?')) {
            // In production, send API request
            showToast('Appointment confirmed!', 'success');
            updateAppointmentStatus(appointmentId, 'confirmed');
        }
    };
    
    window.cancelAppointment = function(appointmentId) {
        const reason = prompt('Please enter cancellation reason:');
        if (reason) {
            // In production, send API request
            showToast('Appointment cancelled!', 'success');
            updateAppointmentStatus(appointmentId, 'cancelled');
        }
    };
    
    window.viewAppointmentDetails = function(appointmentId) {
        showToast(`Viewing appointment ${appointmentId} details`, 'info');
        // In production, open modal or navigate to details page
    };
    
    window.addMedicalNotes = function(appointmentId) {
        const notes = prompt('Enter medical notes:');
        if (notes) {
            // In production, send API request
            showToast('Notes added successfully!', 'success');
        }
    };
    
    function updateAppointmentStatus(appointmentId, status) {
        // Find and update the status badge
        const appointmentItem = document.querySelector(`.btn-action[data-id="${appointmentId}"]`)
            ?.closest('.appointment-item, .upcoming-item');
        
        if (appointmentItem) {
            const statusBadge = appointmentItem.querySelector('.status-badge');
            if (statusBadge) {
                statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                statusBadge.className = `status-badge ${status}`;
            }
        }
    }
    
    // ============================================
    // INITIALIZE
    // ============================================
    init();
});