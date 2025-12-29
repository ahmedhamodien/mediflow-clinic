// Highlight current page link
const navLinks = document.querySelectorAll("nav a");

navLinks.forEach(link => {
    if (link.href === window.location.href) {
        link.classList.add("active");
    }
});

// index page
// main section  animation
const hero = document.querySelector(".index-main"); 
if (hero) {
    window.addEventListener("load", () => {
        hero.classList.add("fade-in"); 
    });
}

// smooth anchor
const anchorLinks = document.querySelectorAll('a[href^="#"]');

anchorLinks.forEach(link => {
    link.addEventListener("click", function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute("href"));
        if (target) {
            target.scrollIntoView({ behavior: "smooth" });
        }
    });
});

// log in
// Select inputs

const email = document.getElementById("email");
const password = document.getElementById("password");

// Select login button (first button inside login-container)
const loginBtn = document.querySelector(".login-container button");

if (loginBtn) {
    loginBtn.addEventListener("click", function (e) {
        let valid = true;

        // Clear previous errors
        clearErrors();

        // Email validation
        if (email.value.trim() === "") {
            showError(email, "Email is required");
            valid = false;
        } else if (!/^[\w\.-]+@[\w\.-]+\.\w{2,}$/.test(email.value)) {
            showError(email, "Invalid email format");
            valid = false;
        }

        // Password validation
        if (password.value.trim() === "") {
            showError(password, "Password is required");
            valid = false;
        }

        if (!valid) {
            e.preventDefault(); // Prevent any default action
        } else {
            alert("Login successful ");
        }
    });
}

// Show inline errors
function showError(input, message) {
    let error = document.createElement("small");
    error.classList.add("error-message");
    error.style.color = "red";
    error.innerText = message;

    input.insertAdjacentElement("afterend", error);
}

// Clear previous error messages
function clearErrors() {
    document.querySelectorAll(".error-message").forEach(err => err.remove());
}

// register page
// Select form elements
const registerForm = document.querySelector(".register-box form");

const fullName = document.getElementById("full-name");
const phone = document.getElementById("phone");
const confirmPassword = document.getElementById("confirm-password");

registerForm.addEventListener("submit", function (e) {
    let valid = true;
    clearErrors();

    // Full Name
    if (fullName.value.trim().length < 3) {
        showError(fullName, "Full name must be at least 3 characters");
        valid = false;
    }

    
    // Phone 
    const phonePattern = /^(01)[0-9]{9}$/; 
    if (!phonePattern.test(phone.value.trim())) {
        showError(phone, "Please enter a valid Egyptian phone: 01XXXXXXXXX");
        valid = false;
    }

    // Password Strength 
    // Must be: 8+ characters, letters + numbers
    const passwordPattern = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/;

    if (!passwordPattern.test(password.value)) {
        showError(password, "Password must be at least 8 characters and contain letters and numbers");
        valid = false;
    }

    // Confirm Password
    if (confirmPassword.value !== password.value) {
        showError(confirmPassword, "Passwords do not match");
        valid = false;
    }

    //  Final Validation 
    if (!valid) {
        e.preventDefault(); // Stop register
    } else {
        alert("Account created successfully! ");
    }
});


// Show Error Under each Input 
function showError(input, message) {
    const error = document.createElement("small");
    error.classList.add("error-message");
    error.style.color = "red";
    error.style.display = "block";
    error.style.marginTop = "5px";
    error.innerText = message;

    input.insertAdjacentElement("afterend", error);
}


// Remove Previous Errors 
function clearErrors() {
    document.querySelectorAll(".error-message").forEach(el => el.remove());
}

// contact page
// Select form elements
const contactForm = document.querySelector(".contact-form form");
const nameInput = document.getElementById("name");
const emailInput = document.getElementById("email");
const messageInput = document.getElementById("message");

contactForm.addEventListener("submit", function (e) {
    let valid = true;
    clearErrors();

    // Name Validation 
    if (nameInput.value.trim().length < 2) {
        showError(nameInput, "Please enter your name");
        valid = false;
    }

    // Email Validation 
    const emailPattern = /^[\w.-]+@[\w.-]+\.\w{2,}$/;
    if (!emailPattern.test(emailInput.value.trim())) {
        showError(emailInput, "Please enter a valid email");
        valid = false;
    }

    // Message Validation 
    if (messageInput.value.trim().length < 5) {
        showError(messageInput, "Please enter your message (at least 5 characters)");
        valid = false;
    }

    // Stop submission if invalid 
    if (!valid) {
        e.preventDefault();
    } else {
        alert("Message sent successfully!");
    }
});

// Helper Functions 
function showError(input, message) {
    const error = document.createElement("small");
    error.classList.add("error-message");
    error.style.color = "red";
    error.style.display = "block";
    error.style.marginTop = "5px";
    error.innerText = message;

    input.insertAdjacentElement("afterend", error);
}

function clearErrors() {
    document.querySelectorAll(".error-message").forEach(el => el.remove());
}


