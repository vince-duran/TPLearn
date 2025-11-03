-- Assignment Submissions Database Schema
-- Run this in phpMyAdmin to create assignment submission tables

USE tplearn;

-- Assignment submissions table for tracking student assignment submissions
CREATE TABLE IF NOT EXISTS assignment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL, -- References program_materials.id where material_type = 'assignment'
    student_id INT NOT NULL, -- References users.id
    file_upload_id INT NULL, -- References file_uploads.id for the submitted file
    submission_text TEXT NULL, -- For text-based submissions
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_late BOOLEAN DEFAULT FALSE,
    grade DECIMAL(5,2) NULL, -- Grade out of max_score
    max_score DECIMAL(5,2) DEFAULT 100.00, -- Maximum possible score
    feedback TEXT NULL,
    status ENUM('submitted', 'graded', 'returned', 'resubmit') DEFAULT 'submitted',
    graded_by INT NULL, -- References users.id (tutor who graded)
    graded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES program_materials(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (file_upload_id) REFERENCES file_uploads(id) ON DELETE SET NULL,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_submission (assignment_id, student_id), -- One submission per student per assignment
    INDEX idx_assignment (assignment_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    INDEX idx_submitted_date (submitted_at)
);

-- Add due_date and max_score to program_materials for assignments
ALTER TABLE program_materials 
ADD COLUMN IF NOT EXISTS due_date DATETIME NULL AFTER description,
ADD COLUMN IF NOT EXISTS max_score DECIMAL(5,2) DEFAULT 100.00 AFTER due_date,
ADD COLUMN IF NOT EXISTS allow_late_submission BOOLEAN DEFAULT TRUE AFTER max_score,
ADD COLUMN IF NOT EXISTS assignment_instructions TEXT NULL AFTER allow_late_submission;

-- Update file_uploads table to support assignment submissions
ALTER TABLE file_uploads 
MODIFY upload_type ENUM('profile_photo', 'document', 'payment_proof', 'assignment', 'program_material', 'assignment_submission') DEFAULT 'document';

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_material_type_due ON program_materials(material_type, due_date);
CREATE INDEX IF NOT EXISTS idx_upload_assignment ON file_uploads(upload_type, related_id);

-- Sample assignment data (for testing - remove in production)
UPDATE program_materials 
SET due_date = DATE_ADD(NOW(), INTERVAL 7 DAY),
    max_score = 100.00,
    allow_late_submission = TRUE,
    assignment_instructions = 'Complete the assignment and submit your work in PDF or DOC format. Make sure to follow the guidelines provided in class.'
WHERE material_type = 'assignment';