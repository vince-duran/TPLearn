-- Create program_materials table to store learning materials for programs
CREATE TABLE IF NOT EXISTS program_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    file_upload_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    material_type ENUM('document', 'video', 'image', 'slides', 'assignment', 'other') DEFAULT 'document',
    is_required BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    visibility ENUM('students', 'tutors', 'both') DEFAULT 'students',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (file_upload_id) REFERENCES file_uploads(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    
    INDEX idx_program_materials_program (program_id),
    INDEX idx_program_materials_type (material_type),
    INDEX idx_program_materials_visibility (visibility),
    INDEX idx_program_materials_order (sort_order)
);

-- Update file_uploads table to include program materials as upload purpose
ALTER TABLE file_uploads 
MODIFY COLUMN upload_purpose ENUM('profile_picture','document','assignment','payment_proof','program_material','other') DEFAULT 'other';
