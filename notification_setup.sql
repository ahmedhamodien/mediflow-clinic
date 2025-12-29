-- 1. First, check if notifications table has necessary columns
ALTER TABLE notifications 
ADD COLUMN IF NOT EXISTS retry_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS error_message TEXT DEFAULT NULL;

-- 2. Create stored procedure to schedule notifications
DELIMITER $$

DROP PROCEDURE IF EXISTS ScheduleAppointmentNotifications$$

CREATE PROCEDURE ScheduleAppointmentNotifications()
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 @sqlstate = RETURNED_SQLSTATE, 
        @errno = MYSQL_ERRNO, @text = MESSAGE_TEXT;
        INSERT INTO audit_logs (action, table_name, message) 
        VALUES ('notification_scheduling_error', 'notifications', 
                CONCAT(@errno, ': ', @text));
    END;
    
    -- Schedule 24-hour reminders
    INSERT INTO notifications (
        user_id,
        title,
        message,
        notification_type,
        appointment_id,
        status,
        scheduled_time,
        channel
    )
    SELECT 
        a.patient_id,
        'Appointment Reminder',
        CONCAT(
            'Reminder: Your appointment with Dr. ', 
            ud.first_name, ' ', ud.last_name,
            ' is scheduled for tomorrow at ',
            TIME_FORMAT(a.appointment_time, '%h:%i %p'),
            ' at ', c.name,
            '. Please arrive 10 minutes early.'
        ),
        'appointment_reminder',
        a.id,
        'pending',
        DATE_ADD(NOW(), INTERVAL 1 HOUR), -- Send in 1 hour
        'email'
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users ud ON d.user_id = ud.id
    JOIN clinics c ON a.clinic_id = c.id
    WHERE a.status IN ('scheduled', 'confirmed')
    AND a.appointment_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) -- Tomorrow
    AND NOT EXISTS (
        SELECT 1 FROM notifications n 
        WHERE n.appointment_id = a.id 
        AND n.notification_type = 'appointment_reminder'
        AND DATE(n.scheduled_time) = CURDATE()
    );
    
    -- Log the scheduling
    INSERT INTO audit_logs (action, table_name, message)
    VALUES ('scheduled_notifications', 'notifications', 
            CONCAT('Scheduled ', ROW_COUNT(), ' notifications'));
    
END$$

DELIMITER ;

-- 3. Create appointment confirmation trigger
DELIMITER $$

DROP TRIGGER IF EXISTS after_appointment_insert$$

CREATE TRIGGER after_appointment_insert
AFTER INSERT ON appointments
FOR EACH ROW
BEGIN
    DECLARE doctor_name VARCHAR(100);
    DECLARE clinic_name VARCHAR(100);
    
    -- Get doctor and clinic names
    SELECT CONCAT(ud.first_name, ' ', ud.last_name) INTO doctor_name
    FROM doctors d
    JOIN users ud ON d.user_id = ud.id
    WHERE d.id = NEW.doctor_id;
    
    SELECT name INTO clinic_name
    FROM clinics
    WHERE id = NEW.clinic_id;
    
    -- Insert confirmation notification
    INSERT INTO notifications (
        user_id,
        title,
        message,
        notification_type,
        appointment_id,
        status,
        scheduled_time,
        channel
    ) VALUES (
        NEW.patient_id,
        'Appointment Confirmed',
        CONCAT(
            'Your appointment has been confirmed with Dr. ', 
            doctor_name,
            ' on ', 
            DATE_FORMAT(NEW.appointment_date, '%M %d, %Y'),
            ' at ',
            TIME_FORMAT(NEW.appointment_time, '%h:%i %p'),
            '. Location: ', clinic_name
        ),
        'appointment_confirmation',
        NEW.id,
        'pending',
        NOW(),
        'email'
    );
END$$

DELIMITER ;

-- 4. Create appointment update trigger
DELIMITER $$

DROP TRIGGER IF EXISTS after_appointment_update$$

CREATE TRIGGER after_appointment_update
AFTER UPDATE ON appointments
FOR EACH ROW
BEGIN
    DECLARE doctor_name VARCHAR(100);
    DECLARE clinic_name VARCHAR(100);
    
    -- Get doctor and clinic names
    SELECT CONCAT(ud.first_name, ' ', ud.last_name) INTO doctor_name
    FROM doctors d
    JOIN users ud ON d.user_id = ud.id
    WHERE d.id = NEW.doctor_id;
    
    SELECT name INTO clinic_name
    FROM clinics
    WHERE id = NEW.clinic_id;
    
    -- If appointment is cancelled
    IF NEW.status = 'cancelled' AND OLD.status != 'cancelled' THEN
        INSERT INTO notifications (
            user_id,
            title,
            message,
            notification_type,
            appointment_id,
            status,
            scheduled_time,
            channel
        ) VALUES (
            NEW.patient_id,
            'Appointment Cancelled',
            CONCAT(
                'Your appointment with Dr. ', 
                doctor_name,
                ' scheduled for ', 
                DATE_FORMAT(NEW.appointment_date, '%M %d, %Y'),
                ' at ',
                TIME_FORMAT(NEW.appointment_time, '%h:%i %p'),
                ' has been cancelled.',
                IF(NEW.cancellation_reason IS NOT NULL, 
                   CONCAT(' Reason: ', NEW.cancellation_reason), '')
            ),
            'appointment_cancellation',
            NEW.id,
            'pending',
            NOW(),
            'email'
        );
    END IF;
    
    -- If appointment status changed to confirmed
    IF NEW.status = 'confirmed' AND OLD.status != 'confirmed' THEN
        INSERT INTO notifications (
            user_id,
            title,
            message,
            notification_type,
            appointment_id,
            status,
            scheduled_time,
            channel
        ) VALUES (
            NEW.patient_id,
            'Appointment Confirmed',
            CONCAT(
                'Your appointment with Dr. ', 
                doctor_name,
                ' has been confirmed for ', 
                DATE_FORMAT(NEW.appointment_date, '%M %d, %Y'),
                ' at ',
                TIME_FORMAT(NEW.appointment_time, '%h:%i %p'),
                '. Location: ', clinic_name
            ),
            'appointment_confirmation',
            NEW.id,
            'pending',
            NOW(),
            'email'
        );
    END IF;
    
    -- If appointment time/date changed
    IF (NEW.appointment_date != OLD.appointment_date OR 
        NEW.appointment_time != OLD.appointment_time) AND
        NEW.status IN ('scheduled', 'confirmed') THEN
        
        INSERT INTO notifications (
            user_id,
            title,
            message,
            notification_type,
            appointment_id,
            status,
            scheduled_time,
            channel
        ) VALUES (
            NEW.patient_id,
            'Appointment Rescheduled',
            CONCAT(
                'Your appointment with Dr. ', 
                doctor_name,
                ' has been rescheduled to ', 
                DATE_FORMAT(NEW.appointment_date, '%M %d, %Y'),
                ' at ',
                TIME_FORMAT(NEW.appointment_time, '%h:%i %p'),
                '. Previous time was ',
                DATE_FORMAT(OLD.appointment_date, '%M %d, %Y'),
                ' at ',
                TIME_FORMAT(OLD.appointment_time, '%h:%i %p')
            ),
            'general',
            NEW.id,
            'pending',
            NOW(),
            'email'
        );
    END IF;
END$$

DELIMITER ;

-- 5. Create view for easier querying
CREATE OR REPLACE VIEW upcoming_appointments_for_notification AS
SELECT 
    a.*,
    u.email as patient_email,
    u.phone as patient_phone,
    CONCAT(u.first_name, ' ', u.last_name) as patient_name,
    CONCAT(ud.first_name, ' ', ud.last_name) as doctor_name,
    d.specialization,
    c.name as clinic_name,
    c.address as clinic_address,
    c.phone as clinic_phone,
    TIMESTAMP(a.appointment_date, a.appointment_time) as appointment_datetime
FROM appointments a
JOIN users u ON a.patient_id = u.id
JOIN doctors d ON a.doctor_id = d.id
JOIN users ud ON d.user_id = ud.id
JOIN clinics c ON a.clinic_id = c.id
WHERE a.status IN ('scheduled', 'confirmed')
AND a.appointment_date >= CURDATE();