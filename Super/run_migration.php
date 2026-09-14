<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('config.php');

if (!isset($_SESSION['admin_id'])) {
    die("<div style='font-family:sans-serif; padding:20px; color:red;'>Access Denied. Please login to Super Admin panel first, then access this URL.</div>");
}

echo "<h2>Executing Database Migration...</h2>";
echo "<ul style='font-family:sans-serif;'>";

// 1. Check & Alter loans table columns
$existing_columns = [];
try {
    $res = $conn->query("SHOW COLUMNS FROM loans");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $existing_columns[] = $row['Field'];
        }
    }
} catch (Throwable $e) {
    echo "<li style='color:red;'>Error fetching loans table structure: " . htmlspecialchars($e->getMessage()) . "</li>";
}

$alter_queries = [];
if (!in_array('loan_type', $existing_columns)) {
    $alter_queries[] = "ALTER TABLE loans ADD COLUMN loan_type ENUM('standard', 'interest_only', 'gold') NOT NULL DEFAULT 'standard' AFTER id";
}
if (!in_array('interest_calculation_type', $existing_columns)) {
    $alter_queries[] = "ALTER TABLE loans ADD COLUMN interest_calculation_type ENUM('flat_total', 'monthly_interest_only') NOT NULL DEFAULT 'flat_total' AFTER loan_type";
}
if (!in_array('gold_weight_grams', $existing_columns)) {
    $alter_queries[] = "ALTER TABLE loans ADD COLUMN gold_weight_grams DECIMAL(10,3) DEFAULT NULL AFTER tenure";
}
if (!in_array('gold_photo_path', $existing_columns)) {
    $alter_queries[] = "ALTER TABLE loans ADD COLUMN gold_photo_path VARCHAR(255) DEFAULT NULL AFTER gold_weight_grams";
}
if (!in_array('gold_rate_per_gram', $existing_columns)) {
    $alter_queries[] = "ALTER TABLE loans ADD COLUMN gold_rate_per_gram DECIMAL(10,2) DEFAULT NULL AFTER gold_photo_path";
}
if (!in_array('processing_fee', $existing_columns)) {
    $alter_queries[] = "ALTER TABLE loans ADD COLUMN processing_fee DECIMAL(10,2) DEFAULT 0.00 AFTER gold_rate_per_gram";
}
if (!in_array('loan_start_date', $existing_columns)) {
    $alter_queries[] = "ALTER TABLE loans ADD COLUMN loan_start_date DATE DEFAULT NULL AFTER approval_date";
}

if (empty($alter_queries)) {
    echo "<li style='color:green;'>`loans` table columns already updated.</li>";
} else {
    foreach ($alter_queries as $q) {
        try {
            if ($conn->query($q)) {
                echo "<li style='color:green;'>Successfully executed: <code>" . htmlspecialchars($q) . "</code></li>";
            } else {
                echo "<li style='color:red;'>Error executing query: " . htmlspecialchars($conn->error) . "</li>";
            }
        } catch (Throwable $e) {
            echo "<li style='color:red;'>Exception executing query <code>" . htmlspecialchars($q) . "</code>: " . htmlspecialchars($e->getMessage()) . "</li>";
        }
    }
}

// 2. Create system_settings table
$create_table_sql = "CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    if ($conn->query($create_table_sql)) {
        echo "<li style='color:green;'>`system_settings` table checked/created successfully.</li>";
    } else {
        echo "<li style='color:red;'>Error creating system_settings table: " . htmlspecialchars($conn->error) . "</li>";
    }
} catch (Throwable $e) {
    echo "<li style='color:red;'>Exception creating system_settings: " . htmlspecialchars($e->getMessage()) . "</li>";
}

// 3. Seed default settings
$seed_sql = "INSERT INTO system_settings (setting_key, setting_value) 
VALUES ('gold_rate_per_gram', '5500.00'), ('gold_loan_processing_fee_percent', '1.50')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);";

try {
    if ($conn->query($seed_sql)) {
        echo "<li style='color:green;'>Default gold rate and processing fee seeded into `system_settings`.</li>";
    } else {
        echo "<li style='color:red;'>Error seeding system_settings: " . htmlspecialchars($conn->error) . "</li>";
    }
} catch (Throwable $e) {
    echo "<li style='color:red;'>Exception seeding system_settings: " . htmlspecialchars($e->getMessage()) . "</li>";
}

// 4. Update existing agent-collected payments to 'approved' by default
$update_payments_sql = "UPDATE payments SET status = 'approved' WHERE (status IS NULL OR status = '' OR status = 'pending') AND (collected_by_agent_id != 'self' AND collected_by_agent_id IS NOT NULL AND collected_by_agent_id != 0)";
try {
    if ($conn->query($update_payments_sql)) {
        $affected = $conn->affected_rows;
        echo "<li style='color:green;'>Updated $affected agent-collected payment(s) to 'approved' status.</li>";
    } else {
        echo "<li style='color:red;'>Error updating payment statuses: " . htmlspecialchars($conn->error) . "</li>";
    }
} catch (Throwable $e) {
    echo "<li style='color:red;'>Exception updating payment statuses: " . htmlspecialchars($e->getMessage()) . "</li>";
}

// 5. Clean up any invalid '0' values in gold_photo_path
try {
    $conn->query("UPDATE loans SET gold_photo_path = NULL WHERE gold_photo_path = '0'");
    echo "<li style='color:green;'>Cleaned up invalid photo path entries.</li>";
} catch (Throwable $e) {
    echo "<li style='color:red;'>Exception cleaning invalid photo paths: " . htmlspecialchars($e->getMessage()) . "</li>";
}

// 6. Create fixed_deposits table
$create_fd_sql = "CREATE TABLE IF NOT EXISTS fixed_deposits (
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    INDEX idx_agent (agent_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    if ($conn->query($create_fd_sql)) {
        echo "<li style='color:green;'>`fixed_deposits` table checked/created successfully.</li>";
    } else {
        echo "<li style='color:red;'>Error creating fixed_deposits table: " . htmlspecialchars($conn->error) . "</li>";
    }
} catch (Throwable $e) {
    echo "<li style='color:red;'>Exception creating fixed_deposits: " . htmlspecialchars($e->getMessage()) . "</li>";
}

// 7. Create fd_payouts table
$create_fd_payouts_sql = "CREATE TABLE IF NOT EXISTS fd_payouts (
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
    INDEX idx_fd (fd_id),
    INDEX idx_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    if ($conn->query($create_fd_payouts_sql)) {
        echo "<li style='color:green;'>`fd_payouts` table checked/created successfully.</li>";
    } else {
        echo "<li style='color:red;'>Error creating fd_payouts table: " . htmlspecialchars($conn->error) . "</li>";
    }
} catch (Throwable $e) {
    echo "<li style='color:red;'>Exception creating fd_payouts: " . htmlspecialchars($e->getMessage()) . "</li>";
}

echo "</ul>";
echo "<h3 style='font-family:sans-serif; color:green;'>Migration Finished Successfully!</h3>";
echo "<p style='font-family:sans-serif;'><a href='settings.php'>Go to System Settings</a> | <a href='all-loans.php'>Go to All Loans</a> | <a href='all-fds.php'>Go to All FDs</a></p>";
?>
