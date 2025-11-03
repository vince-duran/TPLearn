-- TPLearn Database Schema
-- Run this in phpMyAdmin to set up your database

CREATE DATABASE IF NOT EXISTS tplearn;
USE tplearn;

-- Users table for authentication with comprehensive duplicate prevention
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(20) UNIQUE NOT NULL, -- Structured ID: TPA/TPS/TPT + Year + 3 Random Digits
    -- Format: TP (Tisa and Pisara) + A/S/T (Admin/Student/Tutor) + YYYY-XXX (Year-Random)
    -- Examples: TPA2025-847, TPS2025-392, TPT2025-156
    username VARCHAR(50) UNIQUE NOT NULL, -- Keep for backward compatibility
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'tutor', 'student') DEFAULT 'student',
    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for performance and duplicate prevention
    INDEX idx_user_id (user_id),
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    
    -- Ensure no duplicate combinations
    UNIQUE KEY unique_user_id (user_id),
    UNIQUE KEY unique_username (username),
    UNIQUE KEY unique_email (email)
);

-- Student profiles table (from register.php) with full name duplicate prevention
CREATE TABLE student_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    birthday DATE,
    age INT,
    is_pwd BOOLEAN DEFAULT FALSE,
    address TEXT,
    medical_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Index for performance on name searches
    INDEX idx_full_name (first_name, last_name),
    INDEX idx_first_name (first_name),
    INDEX idx_last_name (last_name),
    
    -- Unique constraint to prevent duplicate full names (case-insensitive handled by triggers)
    UNIQUE KEY unique_student_fullname (first_name, last_name)
);

-- Parent profiles table (from register.php)
CREATE TABLE parent_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    facebook_name VARCHAR(100),
    contact_number VARCHAR(20) NOT NULL,
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tutor profiles table with full name duplicate prevention
CREATE TABLE tutor_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    specializations TEXT,
    bio TEXT,
    contact_number VARCHAR(20),
    address TEXT,
    hourly_rate DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Index for performance on name searches
    INDEX idx_full_name (first_name, last_name),
    INDEX idx_first_name (first_name),
    INDEX idx_last_name (last_name),
    
    -- Unique constraint to prevent duplicate full names (case-insensitive handled by triggers)
    UNIQUE KEY unique_tutor_fullname (first_name, last_name)
);

-- Programs table
CREATE TABLE programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    duration_weeks INT,
    fee DECIMAL(10,2),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Enrollments table
CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT NOT NULL,
    program_id INT NOT NULL,
    tutor_user_id INT,
    enrollment_date DATE NOT NULL,
    start_date DATE,
    end_date DATE,
    status ENUM('pending', 'active', 'completed', 'cancelled') DEFAULT 'pending',
    total_fee DECIMAL(10,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Payments table
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'gcash', 'bank_transfer', 'other') DEFAULT 'cash',
    reference_number VARCHAR(100),
    status ENUM('pending', 'validated', 'rejected') DEFAULT 'pending',
    notes TEXT,
    validated_by INT,
    validated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY (validated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Payment attachments table for storing receipt images
CREATE TABLE payment_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);

-- Sessions table for tracking student-tutor sessions
CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled', 'missed') DEFAULT 'scheduled',
    notes TEXT,
    student_attended BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE
);

-- Default admin account for initial system setup
-- Password is 'admin123' (change after first login)
-- User ID: TPA2025-001 (Tisa Pisara Admin + Year + Random digits)
INSERT INTO users (user_id, username, email, password, role, status) VALUES 
('TPA2025-001', 'admin', 'tplearnph@gmail.com', '$2y$12$SeOPp.27O/gUqRh4zUZvCOfBEk8t3XwUUG2KOobf5t.6p/VP1zgW6', 'admin', 'active');

-- Triggers for additional duplicate prevention
DELIMITER $$

-- Trigger to prevent duplicate accounts before insert
CREATE TRIGGER prevent_duplicate_users_insert
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
    DECLARE duplicate_count INT DEFAULT 0;
    DECLARE error_msg VARCHAR(255);
    
    -- Check for duplicate email (case-insensitive)
    SELECT COUNT(*) INTO duplicate_count 
    FROM users 
    WHERE LOWER(email) = LOWER(NEW.email);
    
    IF duplicate_count > 0 THEN
        SET error_msg = CONCAT('Duplicate email detected: ', NEW.email, ' already exists');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    -- Check for duplicate username (case-insensitive)
    SELECT COUNT(*) INTO duplicate_count 
    FROM users 
    WHERE LOWER(username) = LOWER(NEW.username);
    
    IF duplicate_count > 0 THEN
        SET error_msg = CONCAT('Duplicate username detected: ', NEW.username, ' already exists');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    -- Check for duplicate user_id
    SELECT COUNT(*) INTO duplicate_count 
    FROM users 
    WHERE user_id = NEW.user_id;
    
    IF duplicate_count > 0 THEN
        SET error_msg = CONCAT('Duplicate User ID detected: ', NEW.user_id, ' already exists');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
END$$

-- Trigger to prevent duplicate accounts before update
CREATE TRIGGER prevent_duplicate_users_update
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    DECLARE duplicate_count INT DEFAULT 0;
    DECLARE error_msg VARCHAR(255);
    
    -- Check for duplicate email (excluding current record)
    SELECT COUNT(*) INTO duplicate_count 
    FROM users 
    WHERE LOWER(email) = LOWER(NEW.email) AND id != NEW.id;
    
    IF duplicate_count > 0 THEN
        SET error_msg = CONCAT('Duplicate email detected: ', NEW.email, ' already exists');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    -- Check for duplicate username (excluding current record)
    SELECT COUNT(*) INTO duplicate_count 
    FROM users 
    WHERE LOWER(username) = LOWER(NEW.username) AND id != NEW.id;
    
    IF duplicate_count > 0 THEN
        SET error_msg = CONCAT('Duplicate username detected: ', NEW.username, ' already exists');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    -- Check for duplicate user_id (excluding current record)
    SELECT COUNT(*) INTO duplicate_count 
    FROM users 
    WHERE user_id = NEW.user_id AND id != NEW.id;
    
    IF duplicate_count > 0 THEN
        SET error_msg = CONCAT('Duplicate User ID detected: ', NEW.user_id, ' already exists');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
END$$

-- Trigger to prevent duplicate full names in student_profiles
CREATE TRIGGER prevent_duplicate_student_names_insert
BEFORE INSERT ON student_profiles
FOR EACH ROW
BEGIN
    DECLARE duplicate_count INT DEFAULT 0;
    DECLARE existing_user_id VARCHAR(20);
    DECLARE error_msg VARCHAR(500);
    
    -- Check for duplicate full name in student_profiles (case-insensitive)
    SELECT COUNT(*), MIN(u.user_id) INTO duplicate_count, existing_user_id
    FROM student_profiles sp 
    JOIN users u ON sp.user_id = u.id
    WHERE LOWER(TRIM(sp.first_name)) = LOWER(TRIM(NEW.first_name)) 
    AND LOWER(TRIM(sp.last_name)) = LOWER(TRIM(NEW.last_name));
    
    IF duplicate_count > 0 THEN
        SET error_msg = CONCAT('Duplicate student name detected: ', NEW.first_name, ' ', NEW.last_name, ' already exists for user ID ', existing_user_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    -- Check for duplicate full name in tutor_profiles (case-insensitive)
    SELECT COUNT(*), MIN(u.user_id) INTO duplicate_count, existing_user_id
    FROM tutor_profiles tp 
    JOIN users u ON tp.user_id = u.id
    WHERE LOWER(TRIM(tp.first_name)) = LOWER(TRIM(NEW.first_name)) 
    AND LOWER(TRIM(tp.last_name)) = LOWER(TRIM(NEW.last_name));
    
    IF duplicate_count > 0 THEN
        SET error_msg = CONCAT('Duplicate name detected: ', NEW.first_name, ' ', NEW.last_name, ' already exists as tutor with user ID ', existing_user_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
END$$

-- Trigger to prevent duplicate full names in tutor_profiles
CREATE TRIGGER prevent_duplicate_tutor_names_insert
BEFORE INSERT ON tutor_profiles
FOR EACH ROW
BEGIN
    DECLARE duplicate_count INT DEFAULT 0;
    DECLARE existing_user_id VARCHAR(20);
    DECLARE error_msg VARCHAR(500);
    
    -- Check for duplicate full name in tutor_profiles (case-insensitive)
    SELECT COUNT(*), MIN(u.user_id) INTO duplicate_count, existing_user_id
    FROM tutor_profiles tp 
    JOIN users u ON tp.user_id = u.id
    WHERE LOWER(TRIM(tp.first_name)) = LOWER(TRIM(NEW.first_name)) 
    AND LOWER(TRIM(tp.last_name)) = LOWER(TRIM(NEW.last_name));
    
    IF duplicate_count > 0 THEN
        SET error_msg = CONCAT('Duplicate tutor name detected: ', NEW.first_name, ' ', NEW.last_name, ' already exists for user ID ', existing_user_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    -- Check for duplicate full name in student_profiles (case-insensitive)
    SELECT COUNT(*), MIN(u.user_id) INTO duplicate_count, existing_user_id
    FROM student_profiles sp 
    JOIN users u ON sp.user_id = u.id
    WHERE LOWER(TRIM(sp.first_name)) = LOWER(TRIM(NEW.first_name)) 
    AND LOWER(TRIM(sp.last_name)) = LOWER(TRIM(NEW.last_name));
    
    IF duplicate_count > 0 THEN
        SET error_msg = CONCAT('Duplicate name detected: ', NEW.first_name, ' ', NEW.last_name, ' already exists as student with user ID ', existing_user_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
END$$

DELIMITER ;

-- Additional tables for advanced features

-- Activity logs table for audit trail
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_action (user_id, action),
    INDEX idx_created_at (created_at)
);

-- Login attempts tracking table
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    attempts INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_username (username)
);

-- Password reset tokens table
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

-- File uploads table for managing documents
CREATE TABLE file_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    upload_type ENUM('profile_photo', 'document', 'payment_proof', 'assignment') DEFAULT 'document',
    related_id INT NULL, -- Can reference enrollment_id, payment_id, etc.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_type (user_id, upload_type),
    INDEX idx_related (related_id)
);

-- System settings table for configuration
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Notifications table for system messages
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created_at (created_at)
);

-- Update users table to include last_login
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL AFTER updated_at;

-- Essential system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'TPLearn', 'string', 'Website name'),
('max_enrollment_per_student', '5', 'number', 'Maximum enrollments per student'),
('enable_notifications', 'true', 'boolean', 'Enable system notifications'),
('payment_methods', '["cash", "bank_transfer", "gcash", "paypal"]', 'json', 'Supported payment methods'),
('session_reminder_hours', '24', 'number', 'Hours before session to send reminder');

-- Migration commands for existing installations
-- Run these if upgrading from previous version:

-- Add user_id column to existing users table (if needed)
-- ALTER TABLE users ADD COLUMN user_id VARCHAR(20) UNIQUE AFTER id;
-- ALTER TABLE users ADD INDEX idx_user_id (user_id);
-- ALTER TABLE users ADD INDEX idx_role (role);

-- Sample data for testing the User ID system
-- Test users with proper TPT format:

INSERT INTO users (user_id, username, email, password, role, status) VALUES
('TPS2025-847', 'TPS2025-847', 'student1@tplearn.com', '$2y$12$SeOPp.27O/gUqRh4zUZvCOfBEk8t3XwUUG2KOobf5t.6p/VP1zgW6', 'student', 'active'),
('TPS2025-392', 'TPS2025-392', 'student2@tplearn.com', '$2y$12$SeOPp.27O/gUqRh4zUZvCOfBEk8t3XwUUG2KOobf5t.6p/VP1zgW6', 'student', 'active'),
('TPT2025-156', 'TPT2025-156', 'tutor1@tplearn.com', '$2y$12$SeOPp.27O/gUqRh4zUZvCOfBEk8t3XwUUG2KOobf5t.6p/VP1zgW6', 'tutor', 'active'),
('TPA2025-924', 'TPA2025-924', 'admin2@tplearn.com', '$2y$12$SeOPp.27O/gUqRh4zUZvCOfBEk8t3XwUUG2KOobf5t.6p/VP1zgW6', 'admin', 'active');

-- User ID Format Explanation:
-- TP = Tisa and Pisara (organization name)
-- A/S/T = Admin/Student/Tutor (role identifier)  
-- 2025 = Year when account was created
-- XXX = 3 random digits (100-999) to ensure uniqueness