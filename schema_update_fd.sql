-- Database Migration Script for Fixed Deposit (FD) Module
-- Database: microfinance_fund

-- 1. Create fixed_deposits table
CREATE TABLE IF NOT EXISTS fixed_deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fd_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    agent_id INT DEFAULT NULL,
    deposit_amount DECIMAL(10,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    tenure INT NOT NULL, -- Tenure in months
    payout_frequency ENUM('on_maturity', 'monthly', 'quarterly', 'annually') NOT NULL DEFAULT 'on_maturity',
    total_interest DECIMAL(10,2) NOT NULL,
    maturity_amount DECIMAL(10,2) NOT NULL,
    start_date DATE NOT NULL,
    maturity_date DATE NOT NULL,
    status ENUM('pending', 'active', 'matured', 'closed', 'rejected') NOT NULL DEFAULT 'pending',
    approval_date DATETIME DEFAULT NULL,
    approved_by INT DEFAULT NULL,
    rejection_reason TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    INDEX idx_agent (agent_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Create fd_payouts table for interest/maturity payouts log
CREATE TABLE IF NOT EXISTS fd_payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fd_id INT NOT NULL,
    customer_id INT NOT NULL,
    agent_id INT DEFAULT NULL,
    payout_amount DECIMAL(10,2) NOT NULL,
    payout_type ENUM('interest', 'maturity', 'partial') NOT NULL DEFAULT 'maturity',
    payout_date DATE NOT NULL,
    payment_mode ENUM('cash', 'bank_transfer', 'cheque', 'wallet') NOT NULL DEFAULT 'cash',
    remarks TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fd_id) REFERENCES fixed_deposits(id) ON DELETE CASCADE,
    INDEX idx_fd (fd_id),
    INDEX idx_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
