<?php
// --- START: Add these lines for debugging ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- END: Debugging lines ---
// Database Configuration
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'microfinance_fund'); // Your database username
}
if (!defined('DB_PASS')) {
    define('DB_PASS', 'ys!bnLg0j.T[');     // Your database password
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'microfinance_fund'); // Your database name
}

// Create a connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!function_exists('ensure_db_connection')) {
    function ensure_db_connection(&$conn) {
        $is_alive = false;
        if ($conn instanceof mysqli) {
            try {
                $is_alive = @$conn->ping();
            } catch (Throwable $e) {
                $is_alive = false;
            }
        }
        if (!$is_alive) {
            try {
                if ($conn instanceof mysqli) {
                    @$conn->close();
                }
            } catch (Throwable $e) {}
            
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
        }
        return $conn;
    }
}

// Start the session for login management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auto-ensure loan_start_date column & FD tables exist
if (isset($conn) && $conn instanceof mysqli) {
    static $loan_start_date_ensured = false;
    if (!$loan_start_date_ensured) {
        $loan_start_date_ensured = true;
        try {
            $colCheck = $conn->query("SHOW COLUMNS FROM loans LIKE 'loan_start_date'");
            if ($colCheck && $colCheck->num_rows === 0) {
                $conn->query("ALTER TABLE loans ADD COLUMN loan_start_date DATE DEFAULT NULL AFTER approval_date");
                $conn->query("UPDATE loans SET loan_start_date = DATE(approval_date) WHERE loan_start_date IS NULL AND approval_date IS NOT NULL");
            }
        } catch (Throwable $e) {
            // Ignore error if table doesn't exist yet or column already exists
        }

        try {
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
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_customer (customer_id),
                INDEX idx_agent (agent_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $conn->query("CREATE TABLE IF NOT EXISTS fd_payouts (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {
            // Ignore error
        }
    }
}

if (!function_exists('get_system_setting')) {
    function get_system_setting($conn, $key, $default = '') {
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        if ($stmt) {
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $row = $res->fetch_assoc()) {
                $stmt->close();
                return $row['setting_value'];
            }
            $stmt->close();
        }
        return $default;
    }
}

if (!function_exists('set_system_setting')) {
    function set_system_setting($conn, $key, $value) {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        if ($stmt) {
            $stmt->bind_param("ss", $key, $value);
            $success = $stmt->execute();
            $stmt->close();
            return $success;
        }
        return false;
    }
}
?>