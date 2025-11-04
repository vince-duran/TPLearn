-- Cash Settings Table
-- Add this table to the existing TPLearn database for configurable cash payment options

USE tplearn;

-- Cash settings table for configurable cash payment information
CREATE TABLE IF NOT EXISTS cash_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE, -- address, hours, contact_person, phone, etc.
    setting_value TEXT NOT NULL,
    setting_description VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_setting_key (setting_key),
    INDEX idx_is_active (is_active)
);

-- Insert default cash payment settings
INSERT INTO cash_settings (setting_key, setting_value, setting_description) VALUES
('office_address', 'Tisa, Labangon, Cebu City', 'Office address for cash payments'),
('business_hours', 'Monday-Friday, 8:00 AM - 5:00 PM', 'Business hours for cash payments'),
('contact_person', 'Administrative Office', 'Contact person for cash payments'),
('phone_number', '+63 XXX-XXX-XXXX', 'Contact phone number'),
('additional_instructions', 'Please bring a valid ID when making cash payments. Receipt will be provided upon payment.', 'Additional payment instructions')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    updated_at = CURRENT_TIMESTAMP;

SELECT 'Cash settings table created successfully!' as Result;