// booking JS
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const form = document.getElementById('bookingForm');
    const serviceSelect = document.getElementById('service');
    const doctorSelect = document.getElementById('doctor');
    const dateInput = document.getElementById('date');
    const reasonInput = document.getElementById('reason');
    const timeSlotInput = document.getElementById('time_slot');
    const timeNote = document.querySelector('.time-note');
    
    // Step elements
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const step3 = document.getElementById('step3');
    
    // Button elements
    const nextStep1Btn = document.getElementById('nextStep1');
    const nextStep2Btn = document.getElementById('nextStep2');
    const prevStep2Btn = document.getElementById('prevStep2');
    const prevStep3Btn = document.getElementById('prevStep3');
    const submitBtn = document.getElementById('submitBtn');
    
    // Display elements
    const doctorInfo = document.getElementById('doctorInfo');
    const docSpecialization = document.getElementById('docSpecialization');
    const docFee = document.getElementById('docFee');
    const timeSlotsGrid = document.getElementById('timeSlotsGrid');
    
    // Confirmation elements
    const confirmService = document.getElementById('confirmService');
    const confirmDoctor = document.getElementById('confirmDoctor');
    const confirmDate = document.getElementById('confirmDate');
    const confirmTime = document.getElementById('confirmTime');
    const confirmFee = document.getElementById('confirmFee');
    const confirmReason = document.getElementById('confirmReason');
    
    // Progress elements
    const progressFill = document.getElementById('progressFill');
    const stepIndicators = document.querySelectorAll('.step-indicator');
    
    // State
    let currentStep = 1;
    let selectedTimeSlot = null;
    let selectedDoctorData = null;
    
   
    function init() {
        setupEventListeners();
        updateProgress();
        setMinDate();
        
        // Initialize doctor info if a doctor is already selected
        if (doctorSelect.value) {
            handleDoctorChange();
        }
    }
    
    // ============================================
    // EVENT LISTENERS
    // ============================================
    function setupEventListeners() {
        // Navigation buttons
        nextStep1Btn.addEventListener('click', goToStep2FromStep1);
        nextStep2Btn.addEventListener('click', goToStep3);
        prevStep2Btn.addEventListener('click', goToStep1FromStep2);
        prevStep3Btn.addEventListener('click', goToStep2FromStep3);
        
        // Doctor selection
        doctorSelect.addEventListener('change', handleDoctorChange);
        
        // Date selection
        dateInput.addEventListener('change', handleDateChange);
        
        // Time slot selection (event delegation)
        timeSlotsGrid.addEventListener('click', handleTimeSlotClick);
        
        // Form submission
        form.addEventListener('submit', handleFormSubmit);
        
        // Mobile menu (if exists)
        const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
        const mainNav = document.querySelector('.main-nav');
        const closeBtns = document.querySelectorAll('.close-btn');
        
        if (mobileMenuBtn && mainNav) {
            mobileMenuBtn.addEventListener('click', toggleMobileMenu);
        }
        
        // Close message buttons
        closeBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.message').style.display = 'none';
            });
        });
        
        // Auto-close messages after 5 seconds
        autoCloseMessages();
    }
    
    // validation for steps
    function goToStep2FromStep1() {
        console.log('Going to step 2 from step 1');
        if (validateStep1()) {
            currentStep = 2;
            showStep(2);
            updateProgress();
            
            // Load time slots if doctor and date are already selected
            if (doctorSelect.value && dateInput.value) {
                loadTimeSlots();
            }
        }
    }
    
    function goToStep3() {
        console.log('Going to step 3');
        if (validateStep2()) {
            updateConfirmationDetails();
            currentStep = 3;
            showStep(3);
            updateProgress();
        }
    }
    
    function goToStep1FromStep2() {
        console.log('Going back to step 1');
        currentStep = 1;
        showStep(1);
        updateProgress();
    }
    
    function goToStep2FromStep3() {
        console.log('Going back to step 2');
        currentStep = 2;
        showStep(2);
        updateProgress();
    }
    
    function showStep(stepNumber) {
        // Hide all steps
        step1.classList.remove('active');
        step2.classList.remove('active');
        step3.classList.remove('active');
        
        // Show selected step
        const stepElement = document.getElementById(`step${stepNumber}`);
        if (stepElement) {
            stepElement.classList.add('active');
        }
    }
    
    // ============================================
    // VALIDATION FUNCTIONS
    // ============================================
    function validateStep1() {
        const service = serviceSelect.value;
        const doctor = doctorSelect.value;
        
        let isValid = true;
        
        if (!service) {
            showError(serviceSelect, 'Please select a service');
            isValid = false;
        } else {
            clearError(serviceSelect);
        }
        
        if (!doctor) {
            showError(doctorSelect, 'Please select a doctor');
            isValid = false;
        } else {
            clearError(doctorSelect);
        }
        
        return isValid;
    }
    
    function validateStep2() {
        const date = dateInput.value;
        const reason = reasonInput.value.trim();
        const timeSlot = timeSlotInput.value;
        
        let isValid = true;
        
        if (!date) {
            showError(dateInput, 'Please select a date');
            isValid = false;
        } else {
            clearError(dateInput);
        }
        
        if (!reason) {
            showError(reasonInput, 'Please describe your reason for appointment');
            isValid = false;
        } else {
            clearError(reasonInput);
        }
        
        if (!timeSlot) {
            showError(timeSlotsGrid, 'Please select a time slot');
            isValid = false;
        } else {
            clearError(timeSlotsGrid);
        }
        
        return isValid;
    }
    
    // doctor selection
    function handleDoctorChange() {
        console.log('Doctor changed:', doctorSelect.value);
        const selectedOption = doctorSelect.options[doctorSelect.selectedIndex];
        
        if (selectedOption.value) {
            // Extract data attributes
            selectedDoctorData = {
                specialization: selectedOption.dataset.specialization,
                fee: selectedOption.dataset.fee,
                name: selectedOption.text.split(' - ')[0]
            };
            
            // Update doctor info display
            docSpecialization.textContent = selectedDoctorData.specialization;
            docFee.textContent = selectedDoctorData.fee;
            doctorInfo.style.display = 'block';
        } else {
            doctorInfo.style.display = 'none';
            selectedDoctorData = null;
        }
        
        // Clear time slots when doctor changes
        clearTimeSlots();
        
        // If date is already selected, load time slots
        if (dateInput.value) {
            loadTimeSlots();
        }
    }
    
    // selection handle
    function handleDateChange() {
        console.log('Date changed:', dateInput.value);
        if (doctorSelect.value && dateInput.value) {
            loadTimeSlots();
        } else if (!doctorSelect.value) {
            timeSlotsGrid.innerHTML = '<p class="time-note">Please select a doctor first</p>';
        } else {
            timeSlotsGrid.innerHTML = '<p class="time-note">Please select a date</p>';
        }
    }
    
    // time slot management
    async function loadTimeSlots() {
        const doctorId = doctorSelect.value;
        const date = dateInput.value;
        
        console.log('Loading time slots for doctor:', doctorId, 'date:', date);
        
        if (!doctorId || !date) {
            timeSlotsGrid.innerHTML = '<p class="time-note">Please select a doctor and date first</p>';
            return;
        }
        
        // Show loading state
        timeSlotsGrid.innerHTML = '<div class="loading">Loading available slots...</div>';
        
        try {
            const response = await fetch(`booking.php?ajax=getTimeSlots&doctor_id=${doctorId}&date=${date}`);
            console.log('Response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('Time slots data:', data);
            
            if (data.success) {
                displayTimeSlots(data.slots);
            } else {
                throw new Error(data.error || 'Failed to load time slots');
            }
        } catch (error) {
            console.error('Error loading time slots:', error);
            timeSlotsGrid.innerHTML = `
                <div class="error-note">
                    <i class="fas fa-exclamation-circle"></i>
                    Error loading time slots. Please try again.
                    <br><small>${error.message}</small>
                </div>
            `;
        }
    }
    
    function displayTimeSlots(slots) {
        console.log('Displaying slots:', slots);
        
        if (!slots || slots.length === 0) {
            timeSlotsGrid.innerHTML = '<p class="error-note">No available slots for this date. Please select another date.</p>';
            return;
        }
        
        let html = '';
        slots.forEach(slot => {
            const slotClass = slot.available ? 'time-slot' : 'time-slot unavailable';
            
            html += `
                <div class="${slotClass}" 
                     data-time="${slot.time}"
                     data-formatted="${slot.formatted}"
                     data-available="${slot.available}">
                    ${slot.formatted}
                    ${!slot.available ? '<div class="slot-status">Booked</div>' : ''}
                </div>
            `;
        });
        
        timeSlotsGrid.innerHTML = html;
        
        // Hide the "Please select first" note if it exists
        if (timeNote) {
            timeNote.style.display = 'none';
        }
        
        // If a time slot was previously selected, reselect it if available
        if (selectedTimeSlot) {
            const slotElement = timeSlotsGrid.querySelector(`[data-time="${selectedTimeSlot.time}"]`);
            if (slotElement && slotElement.dataset.available === 'true') {
                selectTimeSlot(selectedTimeSlot.time, selectedTimeSlot.formatted);
            } else {
                selectedTimeSlot = null;
                timeSlotInput.value = '';
            }
        }
    }
    
    function clearTimeSlots() {
        selectedTimeSlot = null;
        timeSlotInput.value = '';
        timeSlotsGrid.innerHTML = '<p class="time-note">Please select a doctor and date first</p>';
        
        // Show the "Please select first" note
        if (timeNote) {
            timeNote.style.display = 'block';
        }
        
        // Clear any selected time slot styling
        document.querySelectorAll('.time-slot.selected').forEach(slot => {
            slot.classList.remove('selected');
        });
    }
    
    function handleTimeSlotClick(event) {
        const timeSlot = event.target.closest('.time-slot');
        if (!timeSlot || timeSlot.classList.contains('unavailable')) {
            return;
        }
        
        const time = timeSlot.dataset.time;
        const formatted = timeSlot.dataset.formatted;
        
        selectTimeSlot(time, formatted);
    }
    
    function selectTimeSlot(time, formatted) {
        console.log('Selecting time slot:', time, formatted);
        
        // Clear previous selection
        document.querySelectorAll('.time-slot.selected').forEach(slot => {
            slot.classList.remove('selected');
        });
        
        // Set new selection
        const slotElement = timeSlotsGrid.querySelector(`[data-time="${time}"]`);
        if (slotElement) {
            slotElement.classList.add('selected');
        }
        
        // Update state
        selectedTimeSlot = { time, formatted };
        timeSlotInput.value = time;
        
        // Clear any error
        clearError(timeSlotsGrid);
    }
    
    // confirm details
    function updateConfirmationDetails() {
        confirmService.textContent = serviceSelect.options[serviceSelect.selectedIndex].text;
        confirmDoctor.textContent = doctorSelect.options[doctorSelect.selectedIndex].text.split(' - ')[0];
        confirmDate.textContent = formatDate(dateInput.value);
        confirmTime.textContent = selectedTimeSlot ? selectedTimeSlot.formatted : '-';
        confirmFee.textContent = selectedDoctorData ? `$${selectedDoctorData.fee}` : '-';
        confirmReason.textContent = reasonInput.value || '-';
    }
    
    // ============================================
    // FORM SUBMISSION HANDLER
    // ============================================
    function handleFormSubmit(event) {
        if (!validateStep1() || !validateStep2()) {
            event.preventDefault();
            
            // Go back to the first step with error
            if (!validateStep1()) {
                goToStep1FromStep2();
            } else if (!validateStep2()) {
                goToStep2FromStep3();
            }
            
            return;
        }
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Booking Appointment...';
        
        // Form will submit normally
    }
    
    //helper fun
    function setMinDate() {
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        // Format as YYYY-MM-DD
        const minDate = tomorrow.toISOString().split('T')[0];
        dateInput.min = minDate;
        
        // Set default to tomorrow if empty
        if (!dateInput.value) {
            dateInput.value = minDate;
        }
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString + 'T00:00:00'); // Add time to avoid timezone issues
        return date.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
    
    function showError(input, message) {
        // Remove previous error
        clearError(input);
        
        // Add error class
        input.classList.add('error');
        
        // Create error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-text';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        
        // Insert after input
        input.parentNode.appendChild(errorDiv);
        
        // Scroll to error
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    function clearError(input) {
        input.classList.remove('error');
        const errorElement = input.parentNode.querySelector('.error-text');
        if (errorElement) {
            errorElement.remove();
        }
    }
    
    function updateProgress() {
        // Update progress bar
        const progressWidth = (currentStep / 3) * 100;
        progressFill.style.width = `${progressWidth}%`;
        
        // Update step indicators
        stepIndicators.forEach(indicator => {
            const step = parseInt(indicator.dataset.step);
            if (step <= currentStep) {
                indicator.classList.add('active');
            } else {
                indicator.classList.remove('active');
            }
        });
    }
    
    function toggleMobileMenu() {
        const mainNav = document.querySelector('.main-nav');
        const icon = document.querySelector('.mobile-menu-btn i');
        
        mainNav.classList.toggle('active');
        icon.classList.toggle('fa-bars');
        icon.classList.toggle('fa-times');
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
    
    // initialize
    init();
});