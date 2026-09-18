<?php
// Start output buffering to prevent stray warnings/whitespace from corrupting JSON responses
if (ob_get_level() == 0) {
    ob_start();
}

// Disable inline error display in API output stream to prevent JSON parse errors
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Database Configuration
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'microfinance_fund'); // Your database username
if (!defined('DB_PASS')) define('DB_PASS', 'ys!bnLg0j.T[');     // Your database password
if (!defined('DB_NAME')) define('DB_NAME', 'microfinance_fund'); // Your database name

// Create a connection
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check the connection
if ($conn->connect_error) {
    send_api_json_response(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error], 500);
}

if (!function_exists('ensure_db_connection')) {
    function ensure_db_connection(&$conn) {
        if (!($conn instanceof mysqli) || @!$conn->ping()) {
            $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                if (function_exists('send_api_json_response')) {
                    send_api_json_response(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error], 500);
                } else {
                    die("Connection failed: " . $conn->connect_error);
                }
            }
        }
        return $conn;
    }
}

// Start the session for login management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Send clean JSON response and exit safely
 */
if (!function_exists('send_api_json_response')) {
    function send_api_json_response($response, $http_code = 200, $conn = null) {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code($http_code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
        if ($conn instanceof mysqli) {
            @$conn->close();
        }
        exit();
    }
}

/**
 * Helper to get current authenticated customer ID (Session or Request parameter fallback)
 */
if (!function_exists('get_current_customer_id')) {
    function get_current_customer_id() {
        if (isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id'])) {
            return (int)$_SESSION['customer_id'];
        }
        if (isset($_REQUEST['customer_id']) && is_numeric($_REQUEST['customer_id'])) {
            return (int)$_REQUEST['customer_id'];
        }
        if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) && basename($_SERVER['SCRIPT_NAME']) !== 'loan_details.php' && basename($_SERVER['SCRIPT_NAME']) !== 'rd_details.php' && basename($_SERVER['SCRIPT_NAME']) !== 'fd_details.php') {
            return (int)$_REQUEST['id'];
        }
        $raw_body = file_get_contents('php://input');
        if (!empty($raw_body)) {
            $json = json_decode($raw_body, true);
            if (is_array($json) && isset($json['customer_id']) && is_numeric($json['customer_id'])) {
                return (int)$json['customer_id'];
            }
        }
        return null;
    }
}

// Auto-ensure loan_start_date column exists in loans table
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