<?php
$conn = new mysqli('localhost', 'root', '', 'microfinance_fund');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->query("DROP TABLE IF EXISTS fixed_deposits");
$conn->query("DROP TABLE IF EXISTS customers");
$conn->query("DROP TABLE IF EXISTS agents");
$conn->query("DROP TABLE IF EXISTS admins");

// Create admins table
$conn->query("CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) DEFAULT '',
    email VARCHAR(100) DEFAULT '',
    avatar VARCHAR(255) DEFAULT '',
    status VARCHAR(20) DEFAULT 'active'
)");

// Create agents table
$conn->query("CREATE TABLE IF NOT EXISTS agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) DEFAULT '',
    phone VARCHAR(20) DEFAULT '',
    email VARCHAR(100) DEFAULT '',
    avatar VARCHAR(255) DEFAULT '',
    status VARCHAR(20) DEFAULT 'active'
)");

// Create customers table
$conn->query("CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT '',
    email VARCHAR(100) DEFAULT '',
    agent_id INT DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'active'
)");

// Create fixed_deposits table
$conn->query("CREATE TABLE IF NOT EXISTS fixed_deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fd_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    agent_id INT DEFAULT NULL,
    deposit_amount DECIMAL(10,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    tenure INT NOT NULL,
    payout_frequency ENUM('on_maturity', 'monthly', 'quarterly', 'annually') NOT NULL DEFAULT 'on_maturity',
    total_interest DECIMAL(10,2) NOT NULL,
    maturity_amount DECIMAL(10,2) NOT NULL,
    start_date DATE NOT NULL,
    maturity_date DATE NOT NULL,
    status ENUM('pending', 'active', 'matured', 'closed', 'rejected') NOT NULL DEFAULT 'pending',
    approval_date DATETIME DEFAULT NULL,
    approved_by INT DEFAULT NULL,
    rejection_reason TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Clear & seed test data
$conn->query("TRUNCATE TABLE admins");
$conn->query("TRUNCATE TABLE agents");
$conn->query("TRUNCATE TABLE customers");
$conn->query("TRUNCATE TABLE fixed_deposits");

$conn->query("INSERT INTO admins (id, first_name, last_name, email) VALUES (1, 'Super', 'Admin', 'admin@test.com')");
$conn->query("INSERT INTO agents (id, first_name, last_name, phone, email) VALUES (1, 'Romesh', 'Roy', '9876543210', 'romesh@test.com')");
$conn->query("INSERT INTO customers (id, full_name, phone, email, agent_id) VALUES (101, 'Anita Sharma', '9988776655', 'anita@test.com', 1)");
$conn->query("INSERT INTO fixed_deposits (id, fd_number, customer_id, agent_id, deposit_amount, interest_rate, tenure, payout_frequency, total_interest, maturity_amount, start_date, maturity_date, status) 
VALUES (1, 'FD-20260914-5001', 101, 1, 50000.00, 8.50, 12, 'on_maturity', 4250.00, 54250.00, '2026-09-14', '2027-09-14', 'active')");

echo "Database seeded successfully with admins!\n";
$conn->close();
?>
