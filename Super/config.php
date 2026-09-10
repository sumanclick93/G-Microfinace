<?php
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

date_default_timezone_set("Asia/Kolkata");

$created_at =  date('Y-m-d h:i:s');

// Create a connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start the session for login management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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