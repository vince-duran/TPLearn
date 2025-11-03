-- Payment Methods Tables
-- Add these tables to the existing TPLearn database

USE tplearn;

-- E-Wallet accounts table
CREATE TABLE IF NOT EXISTS ewallet_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL, -- GCash, PayMaya, GrabPay, etc.
    account_number VARCHAR(100) NOT NULL, -- Mobile number or account identifier
    account_name VARCHAR(100) NOT NULL, -- Account holder name
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_provider (provider),
    INDEX idx_is_active (is_active)
);

-- Bank accounts table
CREATE TABLE IF NOT EXISTS bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_name VARCHAR(100) NOT NULL, -- BPI, BDO, Metrobank, etc.
    account_number VARCHAR(100) NOT NULL,
    account_name VARCHAR(100) NOT NULL, -- Account holder name
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_bank_name (bank_name),
    INDEX idx_is_active (is_active)
);

-- Insert sample data
INSERT INTO ewallet_accounts (provider, account_number, account_name) VALUES
('GCash', '0917-123-4567', 'Tisa at Pagara'),
('Maya', '0918-765-4321', 'Tisa at Pagara');

INSERT INTO bank_accounts (bank_name, account_number, account_name) VALUES
('BPI', '1234-5678-90', 'Tisa at Pagara Academic Services'),
('BDO', '9876-5432-10', 'Tisa at Pagara Academic Services');