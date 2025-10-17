-- Run this SQL script in phpMyAdmin to create the payment_attachments table

USE tplearn;

-- Create the payment_attachments table
CREATE TABLE IF NOT EXISTS payment_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);

-- Also add updated_at column to payments table if it doesn't exist
ALTER TABLE payments ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Verify the table was created
DESCRIBE payment_attachments;

SELECT 'payment_attachments table created successfully!' as Result;