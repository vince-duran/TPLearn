-- Fix script for TPLearn database
-- Created: October 8, 2025

USE tplearn;

-- Create missing tables

-- Program Materials table
CREATE TABLE IF NOT EXISTS program_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    material_type ENUM('document', 'video', 'quiz', 'assignment') NOT NULL,
    content TEXT,
    order_number INT DEFAULT 0,
    is_required BOOLEAN DEFAULT TRUE,
    due_days INT DEFAULT NULL, -- Days after enrollment to complete
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    INDEX idx_program_order (program_id, order_number)
);

-- Program Sessions table for structured learning sessions
CREATE TABLE IF NOT EXISTS program_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    session_number INT NOT NULL,
    duration_minutes INT DEFAULT 60,
    learning_objectives TEXT,
    materials_needed TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_program_session (program_id, session_number)
);

-- Assignments table
CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    material_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    instructions TEXT,
    max_score INT DEFAULT 100,
    passing_score INT DEFAULT 60,
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES program_materials(id) ON DELETE CASCADE
);

-- Assignment Submissions table
CREATE TABLE IF NOT EXISTS assignment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_user_id INT NOT NULL,
    enrollment_id INT NOT NULL,
    submission_text TEXT,
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('draft', 'submitted', 'graded', 'returned') DEFAULT 'draft',
    score INT DEFAULT NULL,
    feedback TEXT,
    graded_by INT DEFAULT NULL,
    graded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Material Assessments table for tracking student progress
CREATE TABLE IF NOT EXISTS material_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    student_user_id INT NOT NULL,
    enrollment_id INT NOT NULL,
    status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
    completion_date TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES program_materials(id) ON DELETE CASCADE,
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assessment (material_id, student_user_id, enrollment_id)
);

-- Assessment Submissions table
CREATE TABLE IF NOT EXISTS assessment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    student_user_id INT NOT NULL,
    enrollment_id INT NOT NULL,
    submission_type ENUM('text', 'file', 'link') NOT NULL,
    submission_content TEXT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    feedback TEXT,
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES program_materials(id) ON DELETE CASCADE,
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Grades table for overall performance tracking
CREATE TABLE IF NOT EXISTS grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    student_user_id INT NOT NULL,
    assignment_id INT NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    max_score DECIMAL(5,2) NOT NULL,
    percentage DECIMAL(5,2) GENERATED ALWAYS AS ((score / max_score) * 100) STORED,
    notes TEXT,
    graded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE NO ACTION
);

-- Recreate triggers

DELIMITER $$

-- Trigger to automatically create assignment record when program material is added
CREATE TRIGGER auto_create_assignment_record
AFTER INSERT ON program_materials
FOR EACH ROW
BEGIN
    IF NEW.material_type = 'assignment' THEN
        INSERT INTO assignments (program_id, material_id, title, description, instructions)
        VALUES (NEW.program_id, NEW.id, NEW.title, NEW.description, NEW.content);
    END IF;
END$$

-- Trigger to update assignment record when program material is updated
CREATE TRIGGER auto_create_assignment_record_on_update
AFTER UPDATE ON program_materials
FOR EACH ROW
BEGIN
    IF NEW.material_type = 'assignment' THEN
        -- Check if assignment exists
        IF NOT EXISTS (SELECT 1 FROM assignments WHERE material_id = NEW.id) THEN
            -- Create new assignment if it doesn't exist
            INSERT INTO assignments (program_id, material_id, title, description, instructions)
            VALUES (NEW.program_id, NEW.id, NEW.title, NEW.description, NEW.content);
        ELSE
            -- Update existing assignment
            UPDATE assignments 
            SET title = NEW.title,
                description = NEW.description,
                instructions = NEW.content
            WHERE material_id = NEW.id;
        END IF;
    END IF;
END$$

DELIMITER ;

-- Add missing columns to existing tables if needed
ALTER TABLE payments
ADD COLUMN IF NOT EXISTS payment_method ENUM('cash', 'gcash', 'bank_transfer', 'bpi', 'seabank', 'other') DEFAULT 'cash',
ADD COLUMN IF NOT EXISTS installment_number INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS total_installments INT DEFAULT NULL;

-- Index for performance
ALTER TABLE program_materials ADD INDEX idx_material_type (material_type);
ALTER TABLE assignments ADD INDEX idx_due_date (due_date);
ALTER TABLE assignment_submissions ADD INDEX idx_status_date (status, submission_date);
ALTER TABLE assessment_submissions ADD INDEX idx_status_date (status, submitted_at);
ALTER TABLE grades ADD INDEX idx_enrollment_student (enrollment_id, student_user_id);