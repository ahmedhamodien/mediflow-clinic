
CREATE DATABASE IF NOT EXISTS clinic_system;
USE clinic_system;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    user_type ENUM('patient', 'doctor', 'admin') NOT NULL,
    
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    date_of_birth DATE,
    profile_image VARCHAR(255),
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE clinics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    website VARCHAR(255),
    
    operating_hours JSON,
    timezone VARCHAR(50),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE doctors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    
    specialization VARCHAR(100),
    license_number VARCHAR(50),
    consultation_fee DECIMAL(10,2),
    bio TEXT,
    years_of_experience INT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE doctor_clinic (
    doctor_id INT NOT NULL,
    clinic_id INT NOT NULL,
    
    is_primary BOOLEAN DEFAULT FALSE,
    consultation_fee_at_clinic DECIMAL(10,2),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (doctor_id, clinic_id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (clinic_id) REFERENCES clinics(id) ON DELETE CASCADE
);

CREATE TABLE doctor_availability (
    id INT PRIMARY KEY AUTO_INCREMENT,
    doctor_id INT NOT NULL,
    clinic_id INT NOT NULL,
    
    day_of_week TINYINT NOT NULL, -- 1=Monday, 7=Sunday
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_duration INT DEFAULT 30, -- minutes
    
    is_recurring BOOLEAN DEFAULT TRUE,
    valid_from DATE,
    valid_until DATE,
    
    max_appointments_per_day INT,
    break_start TIME,
    break_end TIME,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (clinic_id) REFERENCES clinics(id) ON DELETE CASCADE
);

CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    clinic_id INT NOT NULL,
    
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('scheduled', 'confirmed', 'cancelled', 'completed', 'no_show') DEFAULT 'scheduled',
    
    reason TEXT,
    notes TEXT,
    symptoms_description TEXT,
    
    buffer_before INT DEFAULT 10, -- minutes
    buffer_after INT DEFAULT 10, -- minutes
    
    cancellation_reason TEXT,
    cancellation_time DATETIME,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    booked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_doctor_slot (doctor_id, clinic_id, appointment_date, appointment_time),
    FOREIGN KEY (patient_id) REFERENCES users(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (clinic_id) REFERENCES clinics(id)
);

CREATE TABLE medical_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_id INT,
    
    height DECIMAL(5,2), -- cm
    weight DECIMAL(5,2), -- kg
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'),
    allergies JSON,
    
    diagnosis TEXT,
    prescription JSON,
    treatment_plan TEXT,
    
    follow_up_date DATE,
    notes TEXT,
    attachments JSON,
    
    record_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES users(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

CREATE TABLE patient_conditions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    
    condition_name VARCHAR(100) NOT NULL,
    diagnosed_date DATE NOT NULL,
    diagnosed_by INT, -- doctor_id
    
    status ENUM('active', 'resolved', 'chronic') DEFAULT 'active',
    severity ENUM('mild', 'moderate', 'severe') DEFAULT 'moderate',
    
    notes TEXT,
    treatment_history JSON,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES users(id),
    FOREIGN KEY (diagnosed_by) REFERENCES doctors(id)
);

CREATE TABLE patient_medications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    
    medication_name VARCHAR(100) NOT NULL,
    dosage VARCHAR(50),
    frequency VARCHAR(50),
    route ENUM('oral', 'injection', 'topical', 'inhalation', 'other') DEFAULT 'oral',
    
    start_date DATE NOT NULL,
    end_date DATE,
    prescribed_by INT, -- doctor_id
    
    refills_remaining INT DEFAULT 0,
    pharmacy_notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES users(id),
    FOREIGN KEY (prescribed_by) REFERENCES doctors(id)
);

CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    
    old_values JSON,
    new_values JSON,
    
    ip_address VARCHAR(45),
    user_agent TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id)
);

