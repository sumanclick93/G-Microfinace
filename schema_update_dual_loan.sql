-- Database Migration Script for Dual-Type Loan System (Gold & Interest-Based Loans)
-- Database: microfinance_fund

-- 1. Update loans table to support loan types, collateral tracking, and interest models
ALTER TABLE loans 
ADD COLUMN loan_type ENUM('standard', 'interest_only', 'gold') NOT NULL DEFAULT 'standard' AFTER id,
ADD COLUMN interest_calculation_type ENUM('flat_total', 'monthly_interest_only') NOT NULL DEFAULT 'flat_total' AFTER loan_type,
ADD COLUMN gold_weight_grams DECIMAL(10,3) DEFAULT NULL AFTER tenure,
ADD COLUMN gold_photo_path VARCHAR(255) DEFAULT NULL AFTER gold_weight_grams,
ADD COLUMN gold_rate_per_gram DECIMAL(10,2) DEFAULT NULL AFTER gold_photo_path,
ADD COLUMN processing_fee DECIMAL(10,2) DEFAULT 0.00 AFTER gold_rate_per_gram;

-- 2. Create global settings table for dynamic admin-controlled parameters
CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default gold rate & processing fee if not present
INSERT INTO system_settings (setting_key, setting_value) 
VALUES ('gold_rate_per_gram', '5500.00'), ('gold_loan_processing_fee_percent', '1.50')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- 3. Mark all agent-collected payments as 'approved' by default (excluding customer self-uploads)
UPDATE payments 
SET status = 'approved' 
WHERE (status IS NULL OR status = '' OR status = 'pending') 
  AND (collected_by_agent_id != 'self' AND collected_by_agent_id IS NOT NULL AND collected_by_agent_id != 0);
