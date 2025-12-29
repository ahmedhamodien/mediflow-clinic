// patient dashboard JS

document.addEventListener('DOMContentLoaded', function() {
    // State variables
    let currentSort = 'date_asc';
    let currentFilter = getUrlParameter('filter') || 'upcoming';
    
    function init() {
        setupEventListeners();
        setupSearch();
        setupTableSorting();
        setupStatusFilters();
        setupAutoRefresh();
        checkUpcomingAppointments();
        
        // Initialize sort select
        const sortSelect = document.getElementById('sortSelect');
        if (sortSelect) {
            sortSelect.value = currentSort;
        }
    }
    
    function setupEventListeners() {
        // Mobile menu
        const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
        const mainNav = document.querySelector('.main-nav');
        
        if (mobileMenuBtn && mainNav) {
            mobileMenuBtn.addEventListener('click', () => {
                mainNav.classList.toggle('active');
                const icon = mobileMenuBtn.querySelector('i');
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            });
        }
        
        // Close message buttons
        document.querySelectorAll('.close-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.message').style.opacity = '0';
                setTimeout(() => {
                    this.closest('.message').style.display = 'none';
                }, 300);
            });
        });
        
        // Close modal buttons
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', closeAllModals);
        });
        
        // Cancel reason selection
        const cancelReasonSelect = document.getElementById('cancellation_reason');
        if (cancelReasonSelect) {
            cancelReasonSelect.addEventListener('change', function() {
                const otherGroup = document.getElementById('otherReasonGroup');
                otherGroup.style.display = this.value === 'Other' ? 'block' : 'none';
            });
        }
        
        // Click outside to close modals
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                closeAllModals();
            }
        });
        
        // Escape key to close modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAllModals();
            }
        });
        
        // Cancel form submission
        const cancelForm = document.getElementById('cancelForm');
        if (cancelForm) {
            cancelForm.addEventListener('submit', function(e) {
                const reasonSelect = document.getElementById('cancellation_reason');
                if (!reasonSelect.value) {
                    e.preventDefault();
                    showToast('Please select a cancellation reason', 'error');
                    reasonSelect.focus();
                    return;
                }
                
                if (reasonSelect.value === 'Other') {
                    const otherReason = document.getElementById('other_reason');
                    if (!otherReason.value.trim()) {
                        e.preventDefault();
                        showToast('Please specify the cancellation reason', 'error');
                        otherReason.focus();
                        return;
                    }
                }
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
                
                // Restore button after 2 seconds (form will submit)
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 2000);
            });
        }
    }
    
    // search functionality
    function setupSearch() {
        const searchInput = document.getElementById('searchInput');
        if (!searchInput) return;
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#appointmentsTable tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const doctor = row.querySelector('.doctor-clinic-info strong')?.textContent.toLowerCase() || '';
                const clinic = row.querySelector('.clinic-details')?.textContent.toLowerCase() || '';
                const reason = row.querySelector('.appointment-details')?.textContent.toLowerCase() || '';
                const text = doctor + ' ' + clinic + ' ' + reason;
                
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update summary
            updateTableSummary(visibleCount, searchTerm ? `searching for "${searchTerm}"` : '');
        });
        
        // Add clear search button
        const searchBox = searchInput.parentElement;
        const clearBtn = document.createElement('button');
        clearBtn.innerHTML = '<i class="fas fa-times"></i>';
        clearBtn.style.cssText = `
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            display: none;
        `;
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
            searchInput.focus();
        });
        searchBox.appendChild(clearBtn);
        
        searchInput.addEventListener('input', function() {
            clearBtn.style.display = this.value ? 'block' : 'none';
        });
    }
    
    // sort functionality
    function setupTableSorting() {
        // Add click handlers to table headers
        const headers = document.querySelectorAll('#appointmentsTable th');
        headers.forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                const sortTypes = ['doctor', 'clinic', 'date', 'details', 'status'];
                if (index < sortTypes.length) {
                    const currentDir = header.dataset.sortDir || 'asc';
                    const newDir = currentDir === 'asc' ? 'desc' : 'asc';
                    header.dataset.sortDir = newDir;
                    
                    sortTableByColumn(index, newDir);
                }
            });
        });
    }
    
    window.sortTable = function(sortType) {
        currentSort = sortType;
        
        switch(sortType) {
            case 'date_asc':
                sortTableByColumn(1, 'asc');
                break;
            case 'date_desc':
                sortTableByColumn(1, 'desc');
                break;
            case 'doctor_asc':
                sortTableByColumn(0, 'asc');
                break;
            case 'doctor_desc':
                sortTableByColumn(0, 'desc');
                break;
            case 'status':
                sortTableByStatus();
                break;
        }
    };
    
    function sortTableByColumn(columnIndex, direction) {
        const table = document.getElementById('appointmentsTable');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        rows.sort((a, b) => {
            let aValue, bValue;
            
            switch(columnIndex) {
                case 0: // Doctor column
                    aValue = a.querySelector('.doctor-clinic-info strong')?.textContent.toLowerCase() || '';
                    bValue = b.querySelector('.doctor-clinic-info strong')?.textContent.toLowerCase() || '';
                    break;
                case 1: // Date column
                    aValue = new Date(a.dataset.date + ' ' + a.dataset.time);
                    bValue = new Date(b.dataset.date + ' ' + b.dataset.time);
                    break;
                case 2: // Details column
                    aValue = a.querySelector('.appointment-details')?.textContent.toLowerCase() || '';
                    bValue = b.querySelector('.appointment-details')?.textContent.toLowerCase() || '';
                    break;
                case 4: // Status column
                    aValue = a.dataset.status || '';
                    bValue = b.dataset.status || '';
                    break;
                default:
                    aValue = a.cells[columnIndex]?.textContent.toLowerCase() || '';
                    bValue = b.cells[columnIndex]?.textContent.toLowerCase() || '';
            }
            
            if (direction === 'asc') {
                return aValue > bValue ? 1 : aValue < bValue ? -1 : 0;
            } else {
                return aValue < bValue ? 1 : aValue > bValue ? -1 : 0;
            }
        });
        
        // Clear and re-add rows
        tbody.innerHTML = '';
        rows.forEach(row => tbody.appendChild(row));
        
        // Update sort indicators
        updateSortIndicators(columnIndex, direction);
    }
    
    function sortTableByStatus() {
        const table = document.getElementById('appointmentsTable');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        const statusOrder = {
            'confirmed': 1,
            'scheduled': 2,
            'completed': 3,
            'cancelled': 4
        };
        
        rows.sort((a, b) => {
            const aStatus = a.dataset.status || '';
            const bStatus = b.dataset.status || '';
            const aOrder = statusOrder[aStatus] || 5;
            const bOrder = statusOrder[bStatus] || 5;
            
            return aOrder - bOrder;
        });
        
        tbody.innerHTML = '';
        rows.forEach(row => tbody.appendChild(row));
    }
    
    function updateSortIndicators(columnIndex, direction) {
        // Remove existing indicators
        document.querySelectorAll('.sort-indicator').forEach(indicator => {
            indicator.remove();
        });
        
        // Add new indicator
        const headers = document.querySelectorAll('#appointmentsTable th');
        const indicator = document.createElement('span');
        indicator.className = 'sort-indicator';
        indicator.innerHTML = direction === 'asc' ? ' ↑' : ' ↓';
        indicator.style.cssText = `
            margin-left: 5px;
            font-weight: bold;
            color: white;
        `;
        headers[columnIndex].appendChild(indicator);
    }
    
    // appointment actions
    window.showCancelModal = function(appointmentId, appointmentInfo) {
        const modal = document.getElementById('cancelModal');
        const appointmentIdInput = document.getElementById('cancelAppointmentId');
        const appointmentInfoSpan = document.getElementById('modalAppointmentInfo');
        
        if (modal && appointmentIdInput && appointmentInfoSpan) {
            appointmentIdInput.value = appointmentId;
            appointmentInfoSpan.textContent = appointmentInfo;
            modal.classList.add('active');
            
            // Reset form
            const form = document.getElementById('cancelForm');
            if (form) form.reset();
            
            // Hide other reason field
            const otherGroup = document.getElementById('otherReasonGroup');
            if (otherGroup) otherGroup.style.display = 'none';
            
            // Focus on select
            setTimeout(() => {
                const reasonSelect = document.getElementById('cancellation_reason');
                if (reasonSelect) reasonSelect.focus();
            }, 100);
        }
    };
    
    window.confirmAppointment = function(appointmentId) {
        if (confirm('Are you sure you want to confirm this appointment?')) {
            submitAppointmentAction('confirm', appointmentId);
        }
    };
    
    window.rescheduleAppointment = function(appointmentId) {
        if (confirm('Would you like to reschedule this appointment?')) {
            setTimeout(() => {
                window.location.href = 'booking.php?reschedule=' + appointmentId;
            }, 100);
        }
    };
    
    window.deleteAppointment = function(appointmentId) {
        if (confirm('Are you sure you want to remove this cancelled appointment from your list?')) {
            window.location.href = 'my_appointments.php?delete=' + appointmentId + '&filter=' + currentFilter;
        }
    };
    
    function submitAppointmentAction(action, appointmentId) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'appointment_id';
        idInput.value = appointmentId;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
    
    // status filters
    function setupStatusFilters() {
        // Highlight active filter in URL
        const currentFilter = getUrlParameter('filter') || 'upcoming';
        document.querySelectorAll('.tab').forEach(tab => {
            const href = tab.getAttribute('href');
            if (href && href.includes(`filter=${currentFilter}`)) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
        
        // Add filter indicators to table
        updateFilterIndicators(currentFilter);
    }
    
    function updateFilterIndicators(filter) {
        const rows = document.querySelectorAll('#appointmentsTable tbody tr');
        rows.forEach(row => {
            const status = row.dataset.status;
            let shouldShow = true;
            
            switch(filter) {
                case 'upcoming':
                    shouldShow = status === 'scheduled' || status === 'confirmed';
                    break;
                case 'past':
                    shouldShow = status === 'completed';
                    break;
                case 'cancelled':
                    shouldShow = status === 'cancelled';
                    break;
                case 'scheduled':
                    shouldShow = status === 'scheduled';
                    break;
                case 'confirmed':
                    shouldShow = status === 'confirmed';
                    break;
            }
            
            row.style.display = shouldShow ? '' : 'none';
        });
        
        updateTableSummary(
            document.querySelectorAll('#appointmentsTable tbody tr[style=""]').length,
            filter !== 'upcoming' ? `filtered by ${filter}` : ''
        );
    }
    
    // auto refresh
    function setupAutoRefresh() {
        // Refresh page every 5 minutes to get updates
        setInterval(() => {
            // Check if user is active on page
            if (!document.hidden) {
                // Refresh only if there are upcoming appointments
                const upcomingCount = document.querySelectorAll('.status.confirmed, .status.scheduled').length;
                if (upcomingCount > 0) {
                    checkUpcomingAppointments();
                }
            }
        }, 300000); // 5 minutes
    }
    
    function checkUpcomingAppointments() {
        const now = new Date();
        const soonThreshold = 2 * 60 * 60 * 1000; // 2 hours in milliseconds
        const todayThreshold = 24 * 60 * 60 * 1000; // 24 hours
        
        document.querySelectorAll('#appointmentsTable tbody tr').forEach(row => {
            const status = row.dataset.status;
            const dateStr = row.dataset.date;
            const timeStr = row.dataset.time;
            
            if ((status === 'confirmed' || status === 'scheduled') && dateStr && timeStr) {
                try {
                    const appointmentDate = new Date(dateStr + 'T' + timeStr);
                    const timeDiff = appointmentDate - now;
                    
                    // Remove existing reminders
                    row.querySelectorAll('.time-indicator').forEach(indicator => {
                        indicator.remove();
                    });
                    
                    // Add new reminder if needed
                    if (timeDiff > 0 && timeDiff <= todayThreshold) {
                        const timeCell = row.querySelector('.datetime-info');
                        if (timeCell) {
                            const indicator = document.createElement('span');
                            indicator.className = 'time-indicator';
                            
                            if (timeDiff <= soonThreshold) {
                                const minutes = Math.ceil(timeDiff / (60 * 1000));
                                indicator.classList.add('soon');
                                indicator.textContent = `Soon (${minutes} min)`;
                                indicator.title = 'Appointment starting soon!';
                            } else if (timeDiff <= todayThreshold) {
                                const hours = Math.floor(timeDiff / (60 * 60 * 1000));
                                indicator.classList.add('today');
                                indicator.textContent = 'Today';
                                indicator.title = `Appointment in ${hours} hours`;
                            }
                            
                            timeCell.appendChild(indicator);
                        }
                    }
                } catch (e) {
                    console.error('Error checking appointment time:', e);
                }
            }
        });
    }
    
    // helper functions
    function closeAllModals() {
        document.querySelectorAll('.modal.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
    
    function updateTableSummary(count, filterText) {
        const summaryElement = document.querySelector('.table-summary p');
        if (summaryElement) {
            let text = `Showing ${count} appointment(s)`;
            if (filterText) {
                text += ` <span id="filterInfo">${filterText}</span>`;
            }
            summaryElement.innerHTML = text;
        }
    }
    
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        const results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }
    
    function showToast(message, type = 'info', duration = 3000) {
        // Remove existing toasts
        document.querySelectorAll('.toast').forEach(toast => toast.remove());
        
        // Create toast
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(toast);
        
        // Auto-remove after duration
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
    
    // ============================================
    // INITIALIZE
    // ============================================
    init();
});
// Close modal when clicking X or outside
const modals = document.querySelectorAll('.modal');
modals.forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal') || 
            e.target.classList.contains('close-modal')) {
            modal.style.display = 'none';
        }
    });
});

// Also close with Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        modals.forEach(modal => {
            modal.style.display = 'none';
        });
    }
});
// Function to show all appointments in modal
function showAllAppointments() {
    // Show loading
    document.getElementById('allAppointmentsList').innerHTML = 
        '<div class="loading" style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading appointments...</div>';
    
    // Show modal
    document.getElementById('allAppointmentsModal').style.display = 'block';
    
    // Fetch all appointments via AJAX
    fetch('get_all_appointments.php')
        .then(response => response.json())
        .then(data => {
            displayAllAppointments(data);
        })
        .catch(error => {
            document.getElementById('allAppointmentsList').innerHTML = 
                '<div class="error">Error loading appointments. Please try again.</div>';
            console.error('Error:', error);
        });
}

// Function to display all appointments in modal
function displayAllAppointments(appointments) {
    const container = document.getElementById('allAppointmentsList');
    
    if (!appointments || appointments.length === 0) {
        container.innerHTML = '<div class="no-data">No appointments found.</div>';
        return;
    }
    
    let html = '<div class="appointments-grid">';
    
    appointments.forEach(appointment => {
        const date = new Date(appointment.appointment_date + ' ' + appointment.appointment_time);
        const formattedDate = date.toLocaleDateString('en-US', { 
            weekday: 'short', 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
        const formattedTime = date.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        
        // Status color
        let statusColor = '#6c757d';
        switch(appointment.status) {
            case 'confirmed': statusColor = '#28a745'; break;
            case 'scheduled': statusColor = '#ffc107'; break;
            case 'completed': statusColor = '#17a2b8'; break;
            case 'cancelled': statusColor = '#dc3545'; break;
        }
        
        html += `
            <div class="appointment-card" data-search="${appointment.doctor_name} ${appointment.clinic_name} ${appointment.reason}">
                <div class="appointment-header">
                    <h4>Dr. ${appointment.doctor_name}</h4>
                    <span class="status-badge" style="background-color: ${statusColor}">
                        ${appointment.status}
                    </span>
                </div>
                <div class="appointment-body">
                    <p><i class="fas fa-hospital"></i> ${appointment.clinic_name}</p>
                    <p><i class="fas fa-calendar"></i> ${formattedDate}</p>
                    <p><i class="fas fa-clock"></i> ${formattedTime}</p>
                    <p><i class="fas fa-stethoscope"></i> ${appointment.specialization}</p>
                    ${appointment.reason ? `<p><i class="fas fa-comment-medical"></i> ${appointment.reason}</p>` : ''}
                    ${appointment.consultation_fee ? `<p><i class="fas fa-money-bill"></i> $${parseFloat(appointment.consultation_fee).toFixed(2)}</p>` : ''}
                </div>
                <div class="appointment-actions">
                    <button class="btn-small" onclick="viewAppointmentDetails(${appointment.id})">
                        <i class="fas fa-eye"></i> View Details
                    </button>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// Function to filter appointments in modal
function filterModalAppointments() {
    const searchTerm = document.getElementById('modalSearch').value.toLowerCase();
    const cards = document.querySelectorAll('.appointment-card');
    
    cards.forEach(card => {
        const searchData = card.getAttribute('data-search').toLowerCase();
        if (searchData.includes(searchTerm)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}